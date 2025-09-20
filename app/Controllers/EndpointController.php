<?php

namespace App\Controllers;

use DateTime;
use League\CommonMark\CommonMarkConverter;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;

class EndpointController
{
    private $storageService;
    private $crawlerService;
    private $view;

    public function __construct($storageService, $crawlerService, $view)
    {
        $this->storageService = $storageService;
        $this->crawlerService = $crawlerService;
        $this->view = $view;
    }

    public function index(Request $request, Response $response): Response
    {
        $uri = $request->getUri();
        $queryParams = $request->getQueryParams();

        // Get search query if present
        $searchQuery = $queryParams['q'] ?? '';

        // Get page parameter, default to 1
        $page = (int)($request->getQueryParams()['page'] ?? 1);
        // Get per_page parameter, default to 20
        $perPage = (int)($request->getQueryParams()['per_page'] ?? $_ENV['PAGES_PER_PAGE'] ?? 50);

        // Validate per_page (limit to reasonable values)
        $perPage = max(12, min($perPage, 100)); // Between 12 and 60

        if (!empty($searchQuery)) {
            $paginatedArticles = $this->storageService->searchArticles($searchQuery, $page, $perPage);
        }
        else {
            $paginatedArticles = $this->storageService->getPaginatedArticles($page, $perPage);
        }

        $response->getBody()->write(
            $this->view->render('index.twig', [
                'baseUrl'      => $uri->getScheme() . '://' . $uri->getHost(),
                'articles'     => $paginatedArticles['articles'],
                'pagination'   => $paginatedArticles,
                'search_query' => $searchQuery, // Pass search query to template
                'title'        => $_ENV['APP_NAME'] . (!empty($searchQuery) ? " - {$searchQuery}" : ''),
            ])
        );
        return $response;
    }

    public function crawl(Request $request, Response $response): Response
    {
        $result = $this->crawlerService->crawlAllSources();

        $response->getBody()->write(json_encode([
            'success' => true,
            'message' => 'Crawling completed',
            'stats'   => $result,
        ]));
        return $response->withHeader('Content-Type', 'application/json');
    }

    public function search(Request $request, Response $response): Response
    {
        $parsedBody = $request->getParsedBody();
        $searchQuery = $parsedBody['q'] ?? '';

        // Redirect to GET with query parameter for better UX
        $uri = $request->getUri();
        $newUri = $uri->withPath('/')->withQuery('q=' . urlencode($searchQuery));
        return $response->withStatus(302)->withHeader('Location', (string)$newUri);
    }

    public function article(Request $request, Response $response, $args)
    {
        $slug = $args['slug'];
        $article = $this->storageService->getArticleBySlug($slug);

        if (!$article) {
            $response->getBody()->write("Article not found");
            return $response->withStatus(404);
        }

        $response->getBody()->write(
            $this->view->render('article.twig', [
                'article' => $article,
                'title'   => $article['title'],
            ])
        );
        return $response;
    }

    public function sitemap(Request $request, Response $response): Response
    {
        // Get the latest 60 articles
        $articles = $this->storageService->getRecentArticles(60);

        // Get base URL from request
        $uri = $request->getUri();
        $baseUrl = $uri->getScheme() . '://' . $uri->getHost();
        if (($uri->getScheme() === 'https' && $uri->getPort() !== 443) ||
            ($uri->getScheme() === 'http' && $uri->getPort() !== 80)) {
            $baseUrl .= ':' . $uri->getPort();
        }
        $baseUrl .= '/';

        // Generate sitemap XML
        $xml = '<?xml version="1.0" encoding="UTF-8"?>' . "\n";
        $xml .= '<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">' . "\n";

        // Add homepage
        $xml .= '  <url>' . "\n";
        $xml .= '    <loc>' . htmlspecialchars($baseUrl) . '</loc>' . "\n";
        $xml .= '    <changefreq>daily</changefreq>' . "\n";
        $xml .= '    <priority>1.0</priority>' . "\n";
        $xml .= '  </url>' . "\n";

        // Add each article
        foreach ($articles as $article) {
            $xml .= '  <url>' . "\n";
            $xml .= '    <loc>' . htmlspecialchars($baseUrl . 'article/' . $article['slug']) . '</loc>' . "\n";

            // Format the published date to W3C format
            $publishedDate = new DateTime($article['published_at']);
            $xml .= '    <lastmod>' . $publishedDate->format('c') . '</lastmod>' . "\n";

            $xml .= '    <changefreq>weekly</changefreq>' . "\n";
            $xml .= '    <priority>0.8</priority>' . "\n";
            $xml .= '  </url>' . "\n";
        }

        $xml .= '</urlset>';

        // Set appropriate headers
        $response = $response->withHeader('Content-Type', 'application/xml; charset=utf-8');
        $response->getBody()->write($xml);

        return $response;
    }

    public function clearCacheAndCleanup(Request $request, Response $response): Response
    {
        try {
            // Clear cache
            $cacheResult = $this->storageService->clearCache();

            // Cleanup old articles
            $this->storageService->cleanupOldArticles();

            /*// Prepare response data
            $responseData = [
                'success'      => $cacheResult['success'],
                'message'      => 'Cache and cleanup operations completed',
                'cache_result' => $cacheResult,
                'timestamp'    => date('c'),
            ];

            $response->getBody()->write(json_encode($responseData, JSON_PRETTY_PRINT));

            $statusCode = $cacheResult['success'] ? 200 : 500;
            return $response
                ->withHeader('Content-Type', 'application/json')
                ->withStatus($statusCode);*/
            return $response
                ->withStatus(301)
                ->withHeader('Location', '/');
        } catch (\Exception $e) {
            error_log("Error in clearCacheAndCleanup: " . $e->getMessage());

            $errorResponse = [
                'success'   => false,
                'message'   => 'An error occurred during cache clearing and cleanup',
                'error'     => $e->getMessage(),
                'timestamp' => date('c'),
            ];

            $response->getBody()->write(json_encode($errorResponse, JSON_PRETTY_PRINT));
            return $response
                ->withHeader('Content-Type', 'application/json')
                ->withStatus(500);
        }
    }

    public function readme(Request $request, Response $response): Response
    {
        $uri = $request->getUri();
        $readmePath = __DIR__ . '/../../README.md';
        
        if (!file_exists($readmePath)) {
            $response->getBody()->write("README.md file not found");
            return $response->withStatus(404);
        }
        
        $readmeContent = file_get_contents($readmePath);
        
        // Convert markdown to HTML
        $converter = new CommonMarkConverter();
        $htmlContent = $converter->convertToHtml($readmeContent);
        
        $response->getBody()->write(
            $this->view->render('readme.twig', [
                'baseUrl' => $uri->getScheme() . '://' . $uri->getHost(),
                'readmeContent' => $htmlContent,
                'title' => 'README - ' . ($_ENV['APP_NAME'] ?? 'AI News Aggregator'),
            ])
        );
        return $response;
    }

    public function error(Request $request, Response $response, $args): Response
    {
        $uri = $request->getUri();
        $code = (int)$args['code'];
        $message = $request->getQueryParams()['message'] ?? null;

        // Map of supported error codes to templates and titles
        $errorMap = [
            400 => ['template' => 'error/404.twig', 'title' => 'Bad Request'],
            401 => ['template' => 'error/403.twig', 'title' => 'Unauthorized'],
            403 => ['template' => 'error/403.twig', 'title' => 'Access Forbidden'],
            404 => ['template' => 'error/404.twig', 'title' => 'Page Not Found'],
            405 => ['template' => 'error/404.twig', 'title' => 'Method Not Allowed'],
            500 => ['template' => 'error/500.twig', 'title' => 'Internal Server Error'],
            502 => ['template' => 'error/500.twig', 'title' => 'Bad Gateway'],
            503 => ['template' => 'error/500.twig', 'title' => 'Service Unavailable'],
        ];

        if (!isset($errorMap[$code])) {
            $code = 404;
        }

        $errorInfo = $errorMap[$code];

        $response->getBody()->write(
            $this->view->render($errorInfo['template'], [
                'baseUrl' => $uri->getScheme() . '://' . $uri->getHost(),
                'message' => $message,
                'title'   => $errorInfo['title'] . ' - SystemsByBit - AI News',
            ])
        );
        return $response->withStatus($code);
    }
}