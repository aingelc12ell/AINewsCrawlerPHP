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
        if ($this->articleExists($article->url)) {
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
}