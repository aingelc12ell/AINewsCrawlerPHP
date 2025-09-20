<?php

use DI\Container;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Slim\Factory\AppFactory;

require __DIR__ . '/../vendor/autoload.php';

// Load environment variables
$dotenv = Dotenv\Dotenv::createImmutable(__DIR__ . '/../');
$dotenv->load();

// Create Container
$container = new Container();
AppFactory::setContainer($container);
$app = AppFactory::create();

// Add routing middleware
$app->addRoutingMiddleware();

// Error handler
$errorMiddleware = $app->addErrorMiddleware(
    ($_ENV['APP_DEBUG'] ?? 'true') === 'true',
    true,
    true
);

// Load settings
$settingsFunction = require __DIR__ . '/../app/config/settings.php';
$settingsFunction($app);

// Define routes
$app->get('/', function (Request $request, Response $response) {
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
        $paginatedArticles = $this->get('storageService')->searchArticles($searchQuery, $page, $perPage);
    }
    else {
        $paginatedArticles = $this->get('storageService')->getPaginatedArticles($page, $perPage);
    }

    $response->getBody()->write(
        $this->get('view')->render('index.twig', [
            'baseUrl'      => $request->getUri(),
            'articles'     => $paginatedArticles['articles'],
            'pagination'   => $paginatedArticles,
            'search_query' => $searchQuery, // Pass search query to template
            'title'        => !empty($searchQuery) ? "Search Results for '{$searchQuery}'" : 'AI News Aggregator',
        ])
    );
    return $response;
});

// Add a dedicated search route (optional, for POST requests)
$app->post('/search', function (Request $request, Response $response) {
    $parsedBody = $request->getParsedBody();
    $searchQuery = $parsedBody['q'] ?? '';

    // Redirect to GET with query parameter for better UX
    $uri = $request->getUri();
    $newUri = $uri->withPath('/')->withQuery('q=' . urlencode($searchQuery));
    return $response->withStatus(302)->withHeader('Location', (string)$newUri);
});

$app->get('/article/{slug}', function (Request $request, Response $response, $args) {
    $slug = $args['slug'];
    $article = $this->get('storageService')->getArticleBySlug($slug);

    if (!$article) {
        $response->getBody()->write("Article not found");
        return $response->withStatus(404);
    }

    $response->getBody()->write(
        $this->get('view')->render('article.twig', [
            'article' => $article,
            'title'   => $article['title'],
        ])
    );
    return $response;
});
/*
$app->get('/crawl', function (Request $request, Response $response) {
    $result = $this->get('crawlerService')->crawlAllSources();

    $response->getBody()->write(json_encode([
        'success' => true,
        'message' => 'Crawling completed',
        'stats' => $result
    ]));
    return $response->withHeader('Content-Type', 'application/json');
});*/

// Sitemap.xml endpoint
$app->get('/sitemap.xml', function (Request $request, Response $response) {
    // Get the latest 60 articles
    $articles = $this->get('storageService')->getRecentArticles(60);

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
});

$app->run();