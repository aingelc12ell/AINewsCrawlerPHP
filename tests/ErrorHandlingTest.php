<?php

namespace Tests;

use App\Models\Article;
use App\Services\StorageService;
use PHPUnit\Framework\TestCase;
use Slim\Psr7\Factory\ServerRequestFactory;

final class ErrorHandlingTest extends TestCase
{
    private string $testRoot;
    private string $storagePath;

    protected function setUp(): void
    {
        $this->testRoot = sys_get_temp_dir() . '/ai-news-app-' . bin2hex(random_bytes(6));
        $this->storagePath = $this->testRoot . '/articles';
        mkdir($this->storagePath, 0755, true);

        $_ENV['APP_NAME'] = 'AI News Test';
        $_ENV['APP_ENV'] = 'test';
        $_ENV['APP_URL'] = 'http://localhost';
        $_ENV['APP_DEBUG'] = 'false';
        $_ENV['STORAGE_PATH'] = $this->storagePath;
    }

    protected function tearDown(): void
    {
        $this->removeDirectory($this->testRoot);
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

    public function testUnexpectedHostIsRejected(): void
    {
        $app = $this->createApp();
        $factory = new ServerRequestFactory();

        $badHostResponse = $app->handle(
            $factory->createServerRequest('GET', 'http://attacker.example/')
        );
        self::assertSame(400, $badHostResponse->getStatusCode());
    }

    public function testClearCacheRouteDeletesCachedFilesAndRedirectsHome(): void
    {
        $cachePath = $this->testRoot . '/cache';
        mkdir($cachePath, 0755, true);
        $cachedFile = $cachePath . '/compiled-template.php';
        file_put_contents($cachedFile, 'cached');

        $response = $this->createApp()->handle(
            (new ServerRequestFactory())->createServerRequest('GET', 'http://localhost/clear-cache')
        );

        self::assertSame(303, $response->getStatusCode());
        self::assertSame('/', $response->getHeaderLine('Location'));
        self::assertSame('no-store', $response->getHeaderLine('Cache-Control'));
        self::assertFileDoesNotExist($cachedFile);
    }

    public function testArticleListUsesRetainedSourceUrlAndCanonicalMetadata(): void
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
        self::assertSame(
            2,
            substr_count(
                $homeBody,
                'href="https://example.com/reliable-ai" target="_blank" rel="noopener noreferrer"',
            )
        );
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
        self::assertStringContainsString(
            '<a href="https://example.com/reliable-ai" target="_blank" rel="noopener noreferrer" class="article-source">Example</a>',
            $detailBody
        );

        $sitemap = $app->handle($factory->createServerRequest('GET', 'http://localhost/sitemap.xml'));
        self::assertStringContainsString(
            'http://localhost/article/' . $article->slug,
            (string)$sitemap->getBody()
        );

        $robots = $app->handle($factory->createServerRequest('GET', 'http://localhost/robots.txt'));
        self::assertStringContainsString('Sitemap: http://localhost/sitemap.xml', (string)$robots->getBody());
    }

    public function testReaderFiltersArticlesBySelectedSource(): void
    {
        $storage = new StorageService($this->storagePath);
        $alpha = new Article(
            'Alpha source article',
            'https://alpha.example/article',
            'Source Alpha',
            '2026-08-25 12:00:00'
        );
        $beta = new Article(
            'Beta source article',
            'https://beta.example/article',
            'Source Beta',
            '2026-08-24 12:00:00'
        );
        self::assertTrue($storage->saveArticle($alpha));
        self::assertTrue($storage->saveArticle($beta));

        $response = $this->createApp()->handle(
            (new ServerRequestFactory())->createServerRequest(
                'GET',
                'http://localhost/?source=' . rawurlencode('Source Beta')
            )
        );
        $body = (string)$response->getBody();

        self::assertSame(200, $response->getStatusCode());
        self::assertStringContainsString('id="sourceSelect"', $body);
        self::assertStringContainsString('value="Source Beta" selected', $body);
        self::assertStringContainsString('href="https://beta.example/article"', $body);
        self::assertStringNotContainsString('href="https://alpha.example/article"', $body);
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
