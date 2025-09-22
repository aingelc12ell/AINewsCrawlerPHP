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
        $fileName = $article->getFileName();
        $filePath = $this->storagePath . '/' . $fileName;

        // Check if article already exists (deduplication)
        if ($this->articleExists($article->url)
            || $this->articleExistsBySlug($article->slug)
        ) {
            return false;
        }

        // Write article to file
        $markdown = $article->toMarkdown();
        $result = file_put_contents($filePath, $markdown);

        return $result !== false;
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

    public function getPaginatedArticles(int $page = 1, int $perPage = 20): array
    {
        $this->cleanupOldArticles();

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
        $pages = ceil($total / $perPage);
        $page = max(1, min($page, $pages));
        $offset = ($page - 1) * $perPage;

        $paginatedArticles = array_slice($articles, $offset, $perPage);

        return [
            'articles' => $paginatedArticles,
            'total' => $total,
            'pages' => $pages,
            'current_page' => $page,
            'per_page' => $perPage,
            'has_next' => $page < $pages,
            'has_prev' => $page > 1,
            'next_page' => $page < $pages ? $page + 1 : null,
            'prev_page' => $page > 1 ? $page - 1 : null,
            'min' => $page > 1 ? $page - 1 : null,
            'max' => $page < $pages ? $page + 1 : null,
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
            if (strpos(basename($file), $slug) !== false) {
                $article = Article::fromMarkdownFile($file);
                if ($article) {
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
                $publishedDate = new \DateTime($article->publishedAt);
                if ($publishedDate < $cutoffDate) {
                    unlink($file);
                    $deletedCount++;
                }
            }
        }

        if ($_ENV['APP_DEBUG'] === 'true') {
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
            
            if ($_ENV['APP_DEBUG'] === 'true') {
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

        $this->cleanupOldArticles();

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

        return [
            'articles' => $paginatedArticles,
            'total' => $total,
            'pages' => $pages,
            'current_page' => $page,
            'per_page' => $perPage,
            'has_next' => $page < $pages,
            'has_prev' => $page > 1,
            'next_page' => $page < $pages ? $page + 1 : null,
            'prev_page' => $page > 1 ? $page - 1 : null,
            'query' => $query
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