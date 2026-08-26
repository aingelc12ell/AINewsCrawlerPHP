<?php

namespace Tests;

use App\Models\Article;
use App\Services\StorageService;
use PHPUnit\Framework\TestCase;
use Slim\Psr7\Factory\ServerRequestFactory;

final class ErrorHandlingTest extends TestCase
{
    private string $storagePath;

    protected function setUp(): void
    {
        $this->storagePath = sys_get_temp_dir() . '/ai-news-app-' . bin2hex(random_bytes(6));
        mkdir($this->storagePath, 0755, true);

        $_ENV['APP_NAME'] = 'AI News Test';
        $_ENV['APP_ENV'] = 'test';
        $_ENV['APP_URL'] = 'http://localhost';
        $_ENV['APP_DEBUG'] = 'false';
        $_ENV['STORAGE_PATH'] = $this->storagePath;
    }

    protected function tearDown(): void
    {
        $this->removeDirectory($this->storagePath);
    }

    public function testUnknownRouteUsesSafeDefaultPageAndSecurityHeaders(): void
    {
        $app = $this->createApp();
        $request = (new ServerRequestFactory())->createServerRequest(
            'GET',
            'http://localhost/unknown-route'
        );

        $response = $app->handle($request);

        self::assertSame(404, $response->getStatusCode());
        self::assertStringContainsString('Page Not Found', (string)$response->getBody());
        self::assertStringContainsString(
            "The page you are looking for doesn't exist or has been moved.",
            html_entity_decode((string)$response->getBody(), ENT_QUOTES | ENT_HTML5, 'UTF-8')
        );
        self::assertSame('nosniff', $response->getHeaderLine('X-Content-Type-Options'));
        self::assertSame('DENY', $response->getHeaderLine('X-Frame-Options'));
    }

    public function testSearchRedirectIsRootRelative(): void
    {
        $app = $this->createApp();
        $request = (new ServerRequestFactory())
            ->createServerRequest('POST', 'http://localhost/search')
            ->withParsedBody(['q' => 'AI safety']);

        $response = $app->handle($request);

        self::assertSame(303, $response->getStatusCode());
        self::assertSame('/?q=AI%20safety', $response->getHeaderLine('Location'));
    }

    public function testUnexpectedHostIsRejectedAndMaintenanceRouteIsNotPublic(): void
    {
        $app = $this->createApp();
        $factory = new ServerRequestFactory();

        $badHostResponse = $app->handle(
            $factory->createServerRequest('GET', 'http://attacker.example/')
        );
        self::assertSame(400, $badHostResponse->getStatusCode());

        $maintenanceResponse = $app->handle(
            $factory->createServerRequest('GET', 'http://localhost/clear-cache')
        );
        self::assertSame(404, $maintenanceResponse->getStatusCode());
    }

    public function testPopulatedReaderUsesInternalDetailRouteAndCanonicalMetadata(): void
    {
        $article = new Article(
            'AI systems become more reliable',
            'https://example.com/reliable-ai',
            'Example',
            '2026-08-25 12:00:00',
            'A concise summary.',
            'The full article text.'
        );
        self::assertTrue((new StorageService($this->storagePath))->saveArticle($article));

        $app = $this->createApp();
        $factory = new ServerRequestFactory();
        $home = $app->handle($factory->createServerRequest('GET', 'http://localhost/'));
        $homeBody = (string)$home->getBody();
        self::assertSame(200, $home->getStatusCode());
        self::assertStringContainsString('href="/css/style.css"', $homeBody);
        self::assertStringContainsString('href="/article/' . $article->slug . '"', $homeBody);
        self::assertStringContainsString('aria-pressed="true">Grid', $homeBody);
        self::assertStringContainsString('id="cardImageToggle"', $homeBody);

        $detail = $app->handle(
            $factory->createServerRequest('GET', 'http://localhost/article/' . $article->slug)
        );
        self::assertSame(200, $detail->getStatusCode());
        $detailBody = (string)$detail->getBody();
        self::assertStringContainsString('The full article text.', $detailBody);
        self::assertStringContainsString('src="/js/js.js"', $detailBody);
        self::assertStringContainsString('id="speechPlayBtn"', $detailBody);
        self::assertStringContainsString('data-reader-content', $detailBody);

        $sitemap = $app->handle($factory->createServerRequest('GET', 'http://localhost/sitemap.xml'));
        self::assertStringContainsString(
            'http://localhost/article/' . $article->slug,
            (string)$sitemap->getBody()
        );

        $robots = $app->handle($factory->createServerRequest('GET', 'http://localhost/robots.txt'));
        self::assertStringContainsString('Sitemap: http://localhost/sitemap.xml', (string)$robots->getBody());
    }

    private function createApp()
    {
        $createApp = require __DIR__ . '/../app/bootstrap.php';
        return $createApp();
    }

    private function removeDirectory(string $directory): void
    {
        if (!is_dir($directory)) {
            return;
        }

        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($directory, \FilesystemIterator::SKIP_DOTS),
            \RecursiveIteratorIterator::CHILD_FIRST
        );
        foreach ($iterator as $item) {
            $item->isDir() ? rmdir($item->getPathname()) : unlink($item->getPathname());
        }
        rmdir($directory);
    }
}
