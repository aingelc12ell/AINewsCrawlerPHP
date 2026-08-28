<?php

use App\Controllers\EndpointController;
use DI\Container;
use Dotenv\Dotenv;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;
use Slim\App;
use Slim\Exception\HttpMethodNotAllowedException;
use Slim\Exception\HttpNotFoundException;
use Slim\Factory\AppFactory;
use Slim\Psr7\Factory\ResponseFactory;
use Slim\Psr7\Factory\StreamFactory;

return static function (): App {
    if (($_ENV['APP_ENV'] ?? null) !== 'test') {
        Dotenv::createImmutable(dirname(__DIR__))->safeLoad();
    }

    $_ENV['APP_NAME'] = $_ENV['APP_NAME'] ?? 'AI News Aggregator';
    $_ENV['APP_DEBUG'] = $_ENV['APP_DEBUG'] ?? 'false';
    $_ENV['STORAGE_PATH'] = $_ENV['STORAGE_PATH'] ?? 'storage/articles';

    $container = new Container();
    AppFactory::setContainer($container);
    $app = AppFactory::create();
    $app->addRoutingMiddleware();

    $displayErrors = filter_var($_ENV['APP_DEBUG'], FILTER_VALIDATE_BOOLEAN);
    $errorMiddleware = $app->addErrorMiddleware($displayErrors, true, true);

    $errorMiddleware->setErrorHandler(
        HttpNotFoundException::class,
        function (ServerRequestInterface $request, Throwable $exception) use ($app): ResponseInterface {
            $response = (new ResponseFactory())->createResponse(404);
            $container = $app->getContainer();

            if ($container && $container->has('view')) {
                $message = rtrim($exception->getMessage(), '.');
                if ($message === '' || strcasecmp($message, 'Not found') === 0) {
                    $message = "The page you are looking for doesn't exist or has been moved.";
                }

                $body = $container->get('view')->render('error/404.twig', [
                    'message' => $message,
                    'title' => 'Page Not Found - ' . $_ENV['APP_NAME'],
                ]);
                $response->getBody()->write($body);

                return $response->withHeader('Content-Type', 'text/html; charset=utf-8');
            }

            $response->getBody()->write('404 Not Found');
            return $response->withHeader('Content-Type', 'text/plain; charset=utf-8');
        }
    );

    $errorMiddleware->setErrorHandler(
        HttpMethodNotAllowedException::class,
        function (ServerRequestInterface $request, Throwable $exception): ResponseInterface {
            /** @var HttpMethodNotAllowedException $exception */
            $allowedMethods = array_map('strtoupper', $exception->getAllowedMethods());
            if (!in_array('GET', $allowedMethods, true)) {
                return (new ResponseFactory())->createResponse(405)
                    ->withHeader('Allow', implode(', ', $allowedMethods))
                    ->withHeader('Content-Type', 'text/plain; charset=utf-8')
                    ->withBody((new StreamFactory())->createStream('405 Method Not Allowed'));
            }

            $location = '/' . ltrim($request->getUri()->getPath(), '/');
            if ($request->getUri()->getQuery() !== '') {
                $location .= '?' . $request->getUri()->getQuery();
            }

            return (new ResponseFactory())->createResponse(303)
                ->withHeader('Location', $location)
                ->withHeader('Allow', implode(', ', $allowedMethods))
                ->withHeader('Cache-Control', 'no-store');
        }
    );

    $settings = require __DIR__ . '/config/settings.php';
    $settings($app);

    $app->get('/', [EndpointController::class, 'index']);
    $app->post('/search', [EndpointController::class, 'search']);
    $app->get('/article/{slug}', [EndpointController::class, 'article']);
    $app->get('/sitemap.xml', [EndpointController::class, 'sitemap']);
    $app->get('/robots.txt', [EndpointController::class, 'robots']);
    $app->get('/readme', [EndpointController::class, 'readme']);
    $app->get('/clear-cache', [EndpointController::class, 'clearCache']);
    $app->get('/error/{code}', [EndpointController::class, 'error']);

    $app->add(function (
        ServerRequestInterface $request,
        RequestHandlerInterface $handler
    ): ResponseInterface {
        $configuredUrl = rtrim($_ENV['APP_URL'] ?? '', '/');
        $configuredParts = $configuredUrl !== '' ? parse_url($configuredUrl) : null;
        if ($configuredParts !== null
            && (!is_array($configuredParts)
                || !in_array(strtolower((string)($configuredParts['scheme'] ?? '')), ['http', 'https'], true)
                || empty($configuredParts['host'])
                || isset($configuredParts['user'])
                || isset($configuredParts['pass']))
        ) {
            throw new RuntimeException('APP_URL must be an HTTP(S) URL without credentials.');
        }

        $configuredHost = is_array($configuredParts) ? ($configuredParts['host'] ?? null) : null;
        if (is_string($configuredHost)
            && $configuredHost !== ''
            && strcasecmp($request->getUri()->getHost(), $configuredHost) !== 0
        ) {
            $response = (new ResponseFactory())->createResponse(400);
            $response->getBody()->write('Invalid Host header');
            return $response->withHeader('Content-Type', 'text/plain; charset=utf-8');
        }

        $response = $handler->handle($request)
            ->withHeader('X-Content-Type-Options', 'nosniff')
            ->withHeader('X-Frame-Options', 'DENY')
            ->withHeader('Referrer-Policy', 'strict-origin-when-cross-origin')
            ->withHeader('Permissions-Policy', 'camera=(), geolocation=(), microphone=()')
            ->withHeader(
                'Content-Security-Policy',
                "default-src 'self'; base-uri 'self'; object-src 'none'; frame-ancestors 'none'; " .
                "form-action 'self'; img-src 'self' https: data:; script-src 'self'; style-src 'self' 'unsafe-inline'"
            );

        if (strtolower($request->getUri()->getScheme()) === 'https') {
            $response = $response->withHeader(
                'Strict-Transport-Security',
                'max-age=31536000; includeSubDomains'
            );
        }

        return $response;
    });

    return $app;
};
