<?php


use App\Services\CrawlerService;
use App\Services\StorageService;
use DI\ContainerBuilder;
use Slim\App;
use Twig\Environment;
use Twig\Extension\DebugExtension;
use Twig\Loader\FilesystemLoader;

return function (App $app) {
    $container = $app->getContainer();

    /*// Load environment variables
    $dotenv = Dotenv\Dotenv::createImmutable(__DIR__ . '/../../');
    $dotenv->load();*/

    // Twig settings
    $container->set('view', function () {
        $loader = new FilesystemLoader(__DIR__ . '/../../templates');
        $twig = new Environment($loader, [
            'cache' => __DIR__ . '/../../storage/cache',
            'debug' => $_ENV['APP_DEBUG'] === 'true',
        ]);

        if ($_ENV['APP_DEBUG'] === 'true') {
            $twig->addExtension(new DebugExtension());
        }

        return $twig;
    });

    // Register services
    $container->set('crawlerService', function () {
        return new CrawlerService();
    });

    $container->set('storageService', function () {
        return new StorageService(__DIR__ . '/../../' . $_ENV['STORAGE_PATH']);
    });
};