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

// Routes
$app->get('/', [EndpointController::class, 'index']);
$app->post('/search', [EndpointController::class, 'search']);
$app->get('/article/{slug}', [EndpointController::class, 'article']);
/*
$app->get('/crawl', [EndpointController::class, 'crawl']);
*/

$app->get('/sitemap.xml', [EndpointController::class, 'sitemap']);

// Cache clearing and cleanup endpoint
$app->post('/clear-cache', [EndpointController::class, 'clearCacheAndCleanup']);

// Generic error endpoint that handles multiple status codes
$app->get('/error/{code}', [EndpointController::class, 'error']);

$app->run();