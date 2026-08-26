<?php

namespace App\Services;

use App\Models\Article;

class StorageService
{
    private string $storagePath;

    public function __construct(string $storagePath)
    {
        $this->storagePath = $storagePath;

        // Create storage directory if it doesn't exist
        if (!file_exists($this->storagePath)) {
            mkdir($this->storagePath, 0755, true);
        }
    }

    public function saveArticle(Article $article): bool
    {
        $lockHandle = fopen($this->storagePath . '/.storage.lock', 'c');
        if ($lockHandle === false) {
            throw new \RuntimeException('Unable to open the article storage lock.');
        }

        $temporaryPath = null;
        try {
            if (!flock($lockHandle, LOCK_EX)) {
                throw new \RuntimeException('Unable to lock article storage.');
            }

            if (!$this->prepareUniqueArticle($article)) {
                return false;
            }

            $filePath = $this->storagePath . '/' . $article->getFileName();
            $temporaryPath = tempnam($this->storagePath, '.article-');
            if ($temporaryPath === false
                || file_put_contents($temporaryPath, $article->toMarkdown(), LOCK_EX) === false
                || !rename($temporaryPath, $filePath)
            ) {
                throw new \RuntimeException('Unable to save article atomically.');
            }
            $temporaryPath = null;
            return true;
        } finally {
            if (is_string($temporaryPath) && file_exists($temporaryPath)) {
                unlink($temporaryPath);
            }
            flock($lockHandle, LOCK_UN);
            fclose($lockHandle);
        }
    }

    public function articleExists(string $url): bool
    {
        $files = glob($this->storagePath . '/*.md');

        foreach ($files as $file) {
            $article = Article::fromMarkdownFile($file);
            if ($article && $article->url === $url) {
                return true;
            }
        }

        return false;
    }

    public function articleExistsBySlug(string $slug): bool
    {
        $files = glob($this->storagePath . '/*.md');

        foreach ($files as $file) {
            $article = Article::fromMarkdownFile($file);
            if ($article && $article->slug === $slug) {
                return true;
            }
        }

        return false;
    }

    private function prepareUniqueArticle(Article $candidate): bool
    {
        $existingSlugs = [];
        foreach (glob($this->storagePath . '/*.md') ?: [] as $file) {
            $article = Article::fromMarkdownFile($file);
            if (!$article) {
                continue;
            }
            if (hash_equals($article->url, $candidate->url)) {
                return false;
            }
            $existingSlugs[$article->slug] = true;
        }

        if (isset($existingSlugs[$candidate->slug])) {
            $candidate->slug .= '-' . substr(hash('sha256', $candidate->url), 0, 8);
        }

        return true;
    }

    public function getPaginatedArticles(int $page = 1, int $perPage = 20): array
    {
        $files = glob($this->storagePath . '/*.md');
        $articles = [];

        foreach ($files as $file) {
            $article = Article::fromMarkdownFile($file);
            if ($article) {
                $articles[] = [
                    'title' => $article->title,
                    'url' => $article->url,
                    'source' => $article->source,
                    'published_at' => $article->publishedAt,
                    'summary' => $article->summary == $article->title ? '' : $article->summary,
                    'slug' => $article->slug,
                    'image_url' => $article->imageUrl ?? '',
                    'filename' => basename($file)
                ];
            }
        }

        // Sort by published date (newest first)
        usort($articles, function ($a, $b) {
            return strtotime($b['published_at']) - strtotime($a['published_at']);
        });

        // Calculate pagination
        $total = count($articles);
        $pages = (int)ceil($total / $perPage);
        $page = max(1, min($page, $pages));
        $offset = ($page - 1) * $perPage;

        $paginatedArticles = array_slice($articles, $offset, $perPage);

        $startPage = max(1, $page - 2);
        $endPage = min($pages, $page + 2);

        return [
            'articles' => $paginatedArticles,
            'total' => $total,
            'pages' => (int)$pages,
            'current_page' => $page,
            'per_page' => $perPage,
            'has_next' => $page < $pages,
            'has_prev' => $page > 1,
            'next_page' => $page < $pages ? $page + 1 : null,
            'prev_page' => $page > 1 ? $page - 1 : null,
            'start_page' => (int)$startPage,
            'end_page' => (int)$endPage,
        ];
    }

    public function getRecentArticles(int $limit = 50): array
    {
        $paginated = $this->getPaginatedArticles(1, $limit);
        return $paginated['articles'];
    }

    public function getArticleBySlug(string $slug): ?array
    {
        $files = glob($this->storagePath . '/*.md');

        foreach ($files as $file) {
            $article = Article::fromMarkdownFile($file);
            if ($article && hash_equals($article->slug, $slug)) {
                return [
                    'title' => $article->title,
                    'url' => $article->url,
                    'source' => $article->source,
                    'published_at' => $article->publishedAt,
                    'summary' => $article->summary,
                    'content' => $article->content,
                    'slug' => $article->slug
                ];
            }
        }

        return null;
    }

    public function cleanupOldArticles(): void
    {
        $days = intval($_ENV['DELETE_OLDER_THAN_DAYS'] ?? 30);
        $cutoffDate = new \DateTime();
        $cutoffDate->modify("-{$days} days");

        $files = glob($this->storagePath . '/*.md');
        $deletedCount = 0;

        foreach ($files as $file) {
            $article = Article::fromMarkdownFile($file);
            if ($article) {
                try {
                    $publishedDate = new \DateTime($article->publishedAt);
                    if ($publishedDate < $cutoffDate && unlink($file)) {
                        $deletedCount++;
                    }
                } catch (\Exception $e) {
                    error_log('Skipping article with invalid publication date: ' . basename($file));
                }
            }
        }

        if (filter_var($_ENV['APP_DEBUG'] ?? false, FILTER_VALIDATE_BOOLEAN)) {
            error_log("Cleaned up {$deletedCount} old articles");
        }
    }

    public function clearCache(): array
    {
        $cacheDir = dirname($this->storagePath) . '/cache';
        $deletedFiles = 0;
        $deletedDirs = 0;

        if (!is_dir($cacheDir)) {
            return [
                'success' => true,
                'message' => 'Cache directory does not exist',
                'deleted_files' => 0,
                'deleted_directories' => 0
            ];
        }

        try {
            $deletedFiles += $this->deleteDirectoryContents($cacheDir);
            
            if (filter_var($_ENV['APP_DEBUG'] ?? false, FILTER_VALIDATE_BOOLEAN)) {
                error_log("Cleared cache: {$deletedFiles} files deleted");
            }

            return [
                'success' => true,
                'message' => 'Cache cleared successfully',
                'deleted_files' => $deletedFiles,
                'deleted_directories' => $deletedDirs
            ];
        } catch (\Exception $e) {
            error_log("Error clearing cache: " . $e->getMessage());
            return [
                'success' => false,
                'message' => 'Failed to clear cache: ' . $e->getMessage(),
                'deleted_files' => $deletedFiles,
                'deleted_directories' => $deletedDirs
            ];
        }
    }

    private function deleteDirectoryContents(string $dir): int
    {
        $deletedCount = 0;
        
        if (!is_dir($dir)) {
            return 0;
        }

        $files = scandir($dir);
        if ($files === false) {
            return 0;
        }

        foreach ($files as $file) {
            if ($file === '.' || $file === '..') {
                continue;
            }

            $path = $dir . DIRECTORY_SEPARATOR . $file;
            
            if (is_dir($path)) {
                $deletedCount += $this->deleteDirectoryContents($path);
                if (rmdir($path)) {
                    $deletedCount++;
                }
            } else {
                if (unlink($path)) {
                    $deletedCount++;
                }
            }
        }

        return $deletedCount;
    }
    public function searchArticles(string $query, int $page = 1, int $perPage = 20): array
    {
        if (empty(trim($query))) {
            return $this->getPaginatedArticles($page, $perPage);
        }

        $files = glob($this->storagePath . '/*.md');
        $articles = [];
        $query = strtolower(trim($query));

        foreach ($files as $file) {
            $article = Article::fromMarkdownFile($file);
            if ($article) {
                // Search in title, summary, and content
                $titleMatch = stripos($article->title, $query) !== false;
                $summaryMatch = !empty($article->summary) && stripos($article->summary, $query) !== false;
                $contentMatch = !empty($article->content) && stripos($article->content, $query) !== false;
                $sourceMatch = stripos($article->source, $query) !== false;

                if ($titleMatch || $summaryMatch || $contentMatch || $sourceMatch) {
                    $articles[] = [
                        'title' => $article->title,
                        'url' => $article->url,
                        'source' => $article->source,
                        'published_at' => $article->publishedAt,
                        'summary' => $article->summary,
                        'slug' => $article->slug,
                        'image_url' => $article->imageUrl ?? '',
                        'filename' => basename($file),
                        'relevance_score' => $this->calculateRelevanceScore($article, $query)
                    ];
                }
            }
        }

        // Sort by relevance score (highest first)
        usort($articles, function ($a, $b) {
            return $b['relevance_score'] - $a['relevance_score'];
        });

        // Calculate pagination
        $total = count($articles);
        $pages = ceil($total / $perPage);
        $page = max(1, min($page, $pages));
        $offset = ($page - 1) * $perPage;

        $paginatedArticles = array_slice($articles, $offset, $perPage);

        $startPage = max(1, $page - 2);
        $endPage = min($pages, $page + 2);

        return [
            'articles' => $paginatedArticles,
            'total' => $total,
            'pages' => (int)$pages,
            'current_page' => $page,
            'per_page' => $perPage,
            'has_next' => $page < $pages,
            'has_prev' => $page > 1,
            'next_page' => $page < $pages ? $page + 1 : null,
            'prev_page' => $page > 1 ? $page - 1 : null,
            'query' => $query,
            'start_page' => (int)$startPage,
            'end_page' => (int)$endPage,
        ];
    }

    private function calculateRelevanceScore(Article $article, string $query): int
    {
        $score = 0;
        $query = strtolower($query);
        $title = strtolower($article->title);
        $summary = !empty($article->summary) ? strtolower($article->summary) : '';
        $content = !empty($article->content) ? strtolower($article->content) : '';
        $source = strtolower($article->source);

        // Title match is most important
        if (stripos($article->title, $query) !== false) {
            $score += 100;

            // Bonus for exact word match or beginning of title
            if ($title === $query || strpos($title, $query) === 0) {
                $score += 50;
            }
        }

        // Summary match
        if (!empty($summary) && stripos($summary, $query) !== false) {
            $score += 50;
        }

        // Content match
        if (!empty($content) && stripos($content, $query) !== false) {
            $score += 30;
        }

        // Source match
        if (stripos($source, $query) !== false) {
            $score += 20;
        }

        // Bonus for recent articles
        $publishedDate = new \DateTime($article->publishedAt);
        $now = new \DateTime();
        $interval = $now->diff($publishedDate);
        $daysAgo = $interval->days;

        if ($daysAgo <= 7) {
            $score += 30; // Very recent
        } elseif ($daysAgo <= 30) {
            $score += 15; // Recent
        }

        return $score;
    }
}
