<?php

use App\Controllers\EndpointController;
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

$app->get('/', [EndpointController::class, 'index']);
$app->post('/search', [EndpointController::class, 'search']);
$app->get('/article/{slug}', [EndpointController::class, 'article']);
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
$app->get('/sitemap.xml', [EndpointController::class, 'sitemap']);

// Error handling endpoints
/*$app->get('/error/404', function (Request $request, Response $response) {
    $uri = $request->getUri();
    $message = $request->getQueryParams()['message'] ?? null;

    $response->getBody()->write(
        $this->get('view')->render('error/404.twig', [
            'baseUrl' => $uri->getScheme() . '://' . $uri->getHost(),
            'message' => $message,
            'title'   => 'Page Not Found - SystemsByBit - AI News',
        ])
    );
    return $response->withStatus(404);
});

$app->get('/error/403', function (Request $request, Response $response) {
    $uri = $request->getUri();
    $message = $request->getQueryParams()['message'] ?? null;

    $response->getBody()->write(
        $this->get('view')->render('error/403.twig', [
            'baseUrl' => $uri->getScheme() . '://' . $uri->getHost(),
            'message' => $message,
            'title'   => 'Access Forbidden - SystemsByBit - AI News',
        ])
    );
    return $response->withStatus(403);
});

$app->get('/error/500', function (Request $request, Response $response) {
    $uri = $request->getUri();
    $message = $request->getQueryParams()['message'] ?? null;

    $response->getBody()->write(
        $this->get('view')->render('error/500.twig', [
            'baseUrl' => $uri->getScheme() . '://' . $uri->getHost(),
            'message' => $message,
            'title'   => 'Internal Server Error - SystemsByBit - AI News',
        ])
    );
    return $response->withStatus(500);
});*/

// Generic error endpoint that handles multiple status codes
$app->get('/error/{code}', [EndpointController::class, 'error']);

$app->run();