<?php

namespace App\Controllers;

use DateTime;
use League\CommonMark\CommonMarkConverter;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Slim\Exception\HttpNotFoundException;

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
        $queryParams = $request->getQueryParams();
        $searchQuery = trim((string)($queryParams['q'] ?? ''));
        if (mb_strlen($searchQuery) > 200) {
            $searchQuery = mb_substr($searchQuery, 0, 200);
        }
        $page = max(1, (int)($queryParams['page'] ?? 1));
        $perPage = max(12, min((int)($queryParams['per_page'] ?? $_ENV['PAGES_PER_PAGE'] ?? 20), 100));
        $sources = $this->storageService->getSources();
        $selectedSource = trim((string)($queryParams['source'] ?? ''));
        if (!in_array($selectedSource, $sources, true)) {
            $selectedSource = '';
        }

        if (!empty($searchQuery)) {
            $paginatedArticles = $this->storageService->searchArticles(
                $searchQuery,
                $page,
                $perPage,
                $selectedSource
            );
        }
        else {
            $paginatedArticles = $this->storageService->getPaginatedArticles($page, $perPage, $selectedSource);
        }

        $response->getBody()->write(
            $this->view->render('index.twig', [
                'articles'     => $paginatedArticles['articles'],
                'pagination'   => $paginatedArticles,
                'search_query' => $searchQuery, // Pass search query to template
                'sources' => $sources,
                'selected_source' => $selectedSource,
                'title'        => $_ENV['APP_NAME'] . (!empty($searchQuery) ? " - {$searchQuery}" : ''),
            ])
        );
        return $response->withHeader('Content-Type', 'text/html; charset=utf-8');
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

    public function clearCache(Request $request, Response $response): Response
    {
        $result = $this->storageService->clearCache();

        if (!$result['success']) {
            $response->getBody()->write(json_encode([
                'success' => false,
                'message' => 'Cache could not be cleared',
            ]));

            return $response
                ->withStatus(500)
                ->withHeader('Content-Type', 'application/json')
                ->withHeader('Cache-Control', 'no-store');
        }

        return $response
            ->withStatus(303)
            ->withHeader('Location', '/')
            ->withHeader('Cache-Control', 'no-store');
    }

    public function search(Request $request, Response $response): Response
    {
        $parsedBody = (array)$request->getParsedBody();
        $searchQuery = mb_substr(trim((string)($parsedBody['q'] ?? '')), 0, 200);
        $location = $searchQuery === '' ? '/' : '/?q=' . rawurlencode($searchQuery);

        return $response->withStatus(303)->withHeader('Location', $location);
    }

    public function article(Request $request, Response $response, $args)
    {
        $slug = $args['slug'];
        $article = $this->storageService->getArticleBySlug($slug);

        if (!$article) {
            throw new HttpNotFoundException($request, 'Article not found');
        }

        $response->getBody()->write(
            $this->view->render('article.twig', [
                'article' => $article,
                'title'   => $article['title'],
            ])
        );
        return $response->withHeader('Content-Type', 'text/html; charset=utf-8');
    }

    public function sitemap(Request $request, Response $response): Response
    {
        $limit = max(1, min((int)($_ENV['SITEMAP_LIMIT'] ?? 5000), 50000));
        $articles = $this->storageService->getRecentArticles($limit);
        $baseUrl = $this->getAppUrl() . '/';

        // Generate sitemap XML
        $xml = '<?xml version="1.0" encoding="UTF-8"?>' . "\n";
        $xml .= '<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">' . "\n";

        // Add homepage
        $xml .= '  <url>' . "\n";
        $xml .= '    <loc>' . htmlspecialchars($baseUrl, ENT_XML1 | ENT_QUOTES, 'UTF-8') . '</loc>' . "\n";
        $xml .= '    <changefreq>daily</changefreq>' . "\n";
        $xml .= '    <priority>1.0</priority>' . "\n";
        $xml .= '  </url>' . "\n";

        // Add each article
        foreach ($articles as $article) {
            $xml .= '  <url>' . "\n";
            $articleUrl = $baseUrl . 'article/' . rawurlencode($article['slug']);
            $xml .= '    <loc>' . htmlspecialchars($articleUrl, ENT_XML1 | ENT_QUOTES, 'UTF-8') . '</loc>' . "\n";

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

    public function robots(Request $request, Response $response): Response
    {
        $body = "User-agent: *\nAllow: /\n\nSitemap: " . $this->getAppUrl() . "/sitemap.xml\n";
        $response->getBody()->write($body);
        return $response->withHeader('Content-Type', 'text/plain; charset=utf-8');
    }

    public function readme(Request $request, Response $response): Response
    {
        $readmePath = __DIR__ . '/../../README.md';
        
        if (!file_exists($readmePath)) {
            throw new HttpNotFoundException($request, 'README.md file not found');
        }
        
        $readmeContent = file_get_contents($readmePath);
        
        // Convert markdown to HTML
        $converter = new CommonMarkConverter([
            'html_input' => 'strip',
            'allow_unsafe_links' => false,
        ]);
        $htmlContent = $converter->convertToHtml($readmeContent);
        
        $response->getBody()->write(
            $this->view->render('readme.twig', [
                'readmeContent' => $htmlContent,
                'title' => 'README - ' . ($_ENV['APP_NAME'] ?? 'AI News Aggregator'),
            ])
        );
        return $response->withHeader('Content-Type', 'text/html; charset=utf-8');
    }

    public function error(Request $request, Response $response, $args): Response
    {
        $code = (int)$args['code'];

        // Map of supported error codes to templates and titles
        $errorMap = [
            400 => ['template' => 'error/404.twig', 'title' => 'Bad Request', 'message' => 'The request could not be understood.'],
            401 => ['template' => 'error/403.twig', 'title' => 'Unauthorized', 'message' => 'Authentication is required to access this resource.'],
            403 => ['template' => 'error/403.twig', 'title' => 'Access Forbidden', 'message' => "You don't have permission to access this resource."],
            404 => ['template' => 'error/404.twig', 'title' => 'Page Not Found', 'message' => "The page you are looking for doesn't exist or has been moved."],
            405 => ['template' => 'error/404.twig', 'title' => 'Method Not Allowed', 'message' => 'That action is not available for this page.'],
            500 => ['template' => 'error/500.twig', 'title' => 'Internal Server Error', 'message' => 'Something went wrong on our end. Please try again later.'],
            502 => ['template' => 'error/500.twig', 'title' => 'Bad Gateway', 'message' => 'A service needed by this page returned an invalid response.'],
            503 => ['template' => 'error/500.twig', 'title' => 'Service Unavailable', 'message' => 'The service is temporarily unavailable. Please try again later.'],
        ];

        if (!isset($errorMap[$code])) {
            $code = 404;
        }

        $errorInfo = $errorMap[$code];

        $response->getBody()->write(
            $this->view->render($errorInfo['template'], [
                'message' => $errorInfo['message'],
                'title'   => $errorInfo['title'] . ' - SystemsByBit - AI News',
            ])
        );
        return $response
            ->withStatus($code)
            ->withHeader('Content-Type', 'text/html; charset=utf-8');
    }

    private function getAppUrl(): string
    {
        $appUrl = rtrim((string)($_ENV['APP_URL'] ?? ''), '/');
        $parts = $appUrl !== '' ? parse_url($appUrl) : null;
        if (!is_array($parts)
            || !in_array(strtolower((string)($parts['scheme'] ?? '')), ['http', 'https'], true)
            || empty($parts['host'])
            || isset($parts['user'])
            || isset($parts['pass'])
        ) {
            throw new \RuntimeException('APP_URL must be an HTTP(S) URL without credentials.');
        }

        return $appUrl;
    }
}
