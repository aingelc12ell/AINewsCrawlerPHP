<?php

use App\Controllers\EndpointController;
use DI\Container;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Slim\Exception\HttpMethodNotAllowedException;
use Slim\Exception\HttpNotFoundException;
use Slim\Factory\AppFactory;
use Slim\Psr7\Factory\ResponseFactory;

require __DIR__ . '/../vendor/autoload.php';

// Load environment variables
$dotenv = Dotenv\Dotenv::createImmutable(__DIR__ . '/../');
$dotenv->safeLoad();

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

// Catch HttpNotFoundException and render custom 404 page
$errorMiddleware->setErrorHandler(
    HttpNotFoundException::class,
    function (Request $request, \Throwable $exception) use ($app) {
        $response = (new ResponseFactory())->createResponse(404);

        $container = $app->getContainer();
        if ($container && $container->has('view')) {
            $view = $container->get('view');
            $uri = $request->getUri();
            $baseUrl = $uri->getScheme() . '://' . $uri->getHost();
            if (($uri->getScheme() === 'https' && $uri->getPort() !== 443) ||
                ($uri->getScheme() === 'http' && $uri->getPort() !== 80)) {
                if ($uri->getPort()) {
                    $baseUrl .= ':' . $uri->getPort();
                }
            }

            $message = $exception->getMessage();
            if (empty($message) || $message === 'Not found.') {
                $message = "The page you are looking for doesn't exist or has been moved.";
            }

            $appName = $_ENV['APP_NAME'] ?? 'AI News Aggregator';
            $body = $view->render('error/404.twig', [
                'baseUrl' => $baseUrl,
                'message' => $message,
                'title'   => 'Page Not Found - ' . $appName,
            ]);
            $response->getBody()->write($body);
            return $response->withHeader('Content-Type', 'text/html; charset=utf-8');
        }

        $response->getBody()->write('404 Not Found');
        return $response->withHeader('Content-Type', 'text/plain; charset=utf-8');
    }
);

// Catch HttpMethodNotAllowedException and redirect to GET on the same path
// when GET is among the route's allowed methods. Security notes:
// - Redirect target is the request's own path (no host/scheme from user input),
//   so this cannot be abused as an open redirect.
// - 303 See Other forces the client to issue a GET and drop the original
//   request body, which is the correct semantic for method downgrade.
// - The original query string is preserved; no untrusted Location data is
//   echoed back beyond the already-validated request URI path.
// - If GET is NOT allowed on the route, fall through to the default 405
//   handler so we don't redirect to a non-existent handler or mask the error.
$errorMiddleware->setErrorHandler(
    HttpMethodNotAllowedException::class,
    function (Request $request, \Throwable $exception) use ($app) {
        /** @var HttpMethodNotAllowedException $exception */
        $allowedMethods = array_map('strtoupper', $exception->getAllowedMethods());

        if (!in_array('GET', $allowedMethods, true)) {
            // GET is not allowed on this route; return a proper 405 with Allow header.
            $response = (new ResponseFactory())->createResponse(405);
            return $response
                ->withHeader('Allow', implode(', ', $allowedMethods))
                ->withHeader('Content-Type', 'text/plain; charset=utf-8')
                ->withBody((new \Slim\Psr7\Factory\StreamFactory())->createStream('405 Method Not Allowed'));
        }

        // Build a same-origin Location using only the request's own URI path/query.
        $uri = $request->getUri();
        $location = '/' . ltrim($uri->getPath(), '/');
        $query = $uri->getQuery();
        if ($query !== '') {
            $location .= '?' . $query;
        }

        $response = (new ResponseFactory())->createResponse(303);
        return $response
            ->withHeader('Location', $location)
            ->withHeader('Allow', implode(', ', $allowedMethods))
            ->withHeader('Cache-Control', 'no-store');
    }
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
$app->get('/readme', [EndpointController::class, 'readme']);

// Cache clearing and cleanup endpoint
$app->get('/clear-cache', [EndpointController::class, 'clearCacheAndCleanup']);

// Generic error endpoint that handles multiple status codes
$app->get('/error/{code}', [EndpointController::class, 'error']);

$app->run();