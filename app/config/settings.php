<?php


use App\Controllers\EndpointController;
use App\Services\CrawlerService;
use App\Services\StorageService;
use Slim\App;
use Twig\Environment;
use Twig\Extension\DebugExtension;
use Twig\Loader\FilesystemLoader;

return function (App $app) {
    $container = $app->getContainer();

    // Twig settings
    $container->set('view', function () {
        $loader = new FilesystemLoader(__DIR__ . '/../../templates');
        $debug = filter_var($_ENV['APP_DEBUG'] ?? false, FILTER_VALIDATE_BOOLEAN);
        $isTest = ($_ENV['APP_ENV'] ?? '') === 'test';
        $autoReload = filter_var($_ENV['TWIG_AUTO_RELOAD'] ?? true, FILTER_VALIDATE_BOOLEAN);
        $twig = new Environment($loader, [
            'cache' => $isTest ? false : __DIR__ . '/../../storage/cache',
            'debug' => $debug,
            'auto_reload' => $autoReload || $debug || $isTest,
            'autoescape' => 'html',
            'strict_variables' => false,
        ]);

        if ($debug) {
            $twig->addExtension(new DebugExtension());
        }

        return $twig;
    });

    // Register services
    $container->set('storageService', function () {
        $configuredPath = $_ENV['STORAGE_PATH'] ?? 'storage/articles';
        if (!preg_match('~^(?:[A-Za-z]:[\\\\/]|/)~', $configuredPath)) {
            $configuredPath = __DIR__ . '/../../' . ltrim($configuredPath, '/\\');
        }

        return new StorageService($configuredPath);
    });

    $container->set('crawlerService', function ($container) {
        $crawler = new CrawlerService();
        $crawler->setCrawlerDependencies($container->get('storageService'));
        return $crawler;
    });

    // Register EndpointController with dependency injection
    $container->set(EndpointController::class, function ($container) {
        return new EndpointController(
            $container->get('storageService'),
            $container->get('crawlerService'),
            $container->get('view')
        );
    });
};
