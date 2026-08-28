<?php

namespace Tests;

use App\Models\Article;
use App\Services\StorageService;
use PHPUnit\Framework\TestCase;

final class StorageServiceTest extends TestCase
{
    private string $storagePath;
    private StorageService $storage;

    protected function setUp(): void
    {
        $this->storagePath = sys_get_temp_dir() . '/ai-news-storage-' . bin2hex(random_bytes(6));
        $this->storage = new StorageService($this->storagePath);
        $_ENV['APP_DEBUG'] = 'false';
    }

    protected function tearDown(): void
    {
        $lockFile = $this->storagePath . '/.storage.lock';
        if (is_file($lockFile)) {
            unlink($lockFile);
        }
        foreach (glob($this->storagePath . '/*') ?: [] as $file) {
            if (is_file($file)) {
                unlink($file);
            }
        }
        if (is_dir($this->storagePath)) {
            rmdir($this->storagePath);
        }
    }

    public function testSaveIsDeduplicatedAndArticleLookupRequiresExactSlug(): void
    {
        $article = new Article(
            'A safer AI system',
            'https://example.com/safer-ai',
            'Example',
            '2026-08-25 12:00:00',
            'Summary',
            'Full content'
        );

        self::assertTrue($this->storage->saveArticle($article));
        self::assertFalse($this->storage->saveArticle($article));
        self::assertNotNull($this->storage->getArticleBySlug($article->slug));
        self::assertNull($this->storage->getArticleBySlug('safer-ai'));
    }

    public function testDifferentUrlsWithTheSameTitleAreDeduplicated(): void
    {
        $first = new Article('Shared headline', 'https://example.com/first', 'Example', '2026-08-25');
        $second = new Article('Shared headline', 'https://example.com/second', 'Example', '2026-08-25');

        self::assertTrue($this->storage->saveArticle($first));
        self::assertFalse($this->storage->saveArticle($second));
        self::assertCount(1, glob($this->storagePath . '/*.md') ?: []);
    }

    public function testListingDoesNotDeleteOldArticlesButExplicitCleanupDoes(): void
    {
        $_ENV['DELETE_OLDER_THAN_DAYS'] = '30';
        $article = new Article(
            'Historical AI article',
            'https://example.com/historical-ai',
            'Example',
            '2020-01-01 12:00:00'
        );
        self::assertTrue($this->storage->saveArticle($article));

        self::assertCount(1, $this->storage->getPaginatedArticles()['articles']);
        self::assertCount(1, glob($this->storagePath . '/*.md') ?: []);

        $this->storage->cleanupOldArticles();
        self::assertCount(0, glob($this->storagePath . '/*.md') ?: []);
    }

    public function testArticlesCanBeFilteredByAnAvailableSource(): void
    {
        self::assertTrue($this->storage->saveArticle(
            new Article('Alpha report', 'https://alpha.example/report', 'Source Alpha', '2026-08-25')
        ));
        self::assertTrue($this->storage->saveArticle(
            new Article('Beta report', 'https://beta.example/report', 'Source Beta', '2026-08-24')
        ));
        self::assertTrue($this->storage->saveArticle(
            new Article('Alpha update', 'https://alpha.example/update', 'Source Alpha', '2026-08-23')
        ));

        self::assertSame(['Source Alpha', 'Source Beta'], $this->storage->getSources());

        $filtered = $this->storage->getPaginatedArticles(1, 20, 'Source Alpha');
        self::assertSame(2, $filtered['total']);
        self::assertSame(
            ['Alpha report', 'Alpha update'],
            array_column($filtered['articles'], 'title')
        );

        $searched = $this->storage->searchArticles('report', 1, 20, 'Source Beta');
        self::assertSame(1, $searched['total']);
        self::assertSame('Beta report', $searched['articles'][0]['title']);
    }

    public function testNonLatinTitleGetsStableFallbackSlug(): void
    {
        $article = new Article(
            '人工智能新闻',
            'https://example.com/chinese-ai',
            'Example',
            '2026-08-25 12:00:00'
        );

        self::assertMatchesRegularExpression('/^article-[a-f0-9]{12}$/', $article->slug);
    }
}
