<?php

namespace App\Models;

class Article
{
    public string $title;
    public string $url;
    public string $source;
    public string $publishedAt;
    public string $summary;
    public string $content;
    public string $slug;
    public string $imageUrl; // New property for image URL

    public function __construct(
        string $title,
        string $url,
        string $source,
        string $publishedAt,
        string $summary = '',
        string $content = '',
        string $imageUrl = ''
    ) {
        $this->title = $title;
        $this->url = $url;
        $this->source = $source;
        $this->publishedAt = $publishedAt;
        $this->summary = $summary;
        $this->content = $content;
        $this->imageUrl = $imageUrl; // Initialize image URL
        $this->slug = $this->generateSlug($title);
    }

    private function generateSlug(string $title): string
    {
        // Convert to lowercase
        $slug = strtolower($title);

        // Remove special characters
        $slug = preg_replace('/[^a-z0-9]+/', '-', $slug);

        // Remove leading and trailing hyphens
        $slug = trim($slug, '-');

        return $slug;
    }

    public function getFileName(): string
    {
        $date = new \DateTime($this->publishedAt);
        return $date->format('Y-m-d') . '-' . $this->slug . '.md';
    }

    public function toMarkdown(): string
    {
        $frontMatter = [
            'title' => $this->title,
            'url' => $this->url,
            'source' => $this->source,
            'published_at' => $this->publishedAt,
            'summary' => $this->summary,
            'slug' => $this->slug,
            'image_url' => $this->imageUrl // Add image URL to front matter
        ];

        $frontMatterStr = "---\n";
        foreach ($frontMatter as $key => $value) {
            if (is_string($value)) {
                // Escape quotes and newlines in YAML
                $value = str_replace('"', '\"', $value);
                $value = str_replace("\n", ' ', $value);
                $frontMatterStr .= "{$key}: \"{$value}\"\n";
            } else {
                $frontMatterStr .= "{$key}: {$value}\n";
            }
        }
        $frontMatterStr .= "---\n\n";

        return $frontMatterStr . $this->content;
    }

    public static function fromMarkdownFile(string $filePath): ?self
    {
        if (!file_exists($filePath)) {
            return null;
        }

        $content = file_get_contents($filePath);

        // Split front matter and content
        if (!preg_match('/^---\n(.*?)\n---\n\n(.*)$/s', $content, $matches)) {
            return null;
        }

        $frontMatter = $matches[1];
        $articleContent = $matches[2];

        // Parse YAML front matter
        $lines = explode("\n", $frontMatter);
        $data = [];

        foreach ($lines as $line) {
            if (preg_match('/^([^:]+):\s*"(.*)"$/s', $line, $parts)) {
                $key = trim($parts[1]);
                $value = trim($parts[2]);
                // Unescape quotes
                $value = str_replace('\"', '"', $value);
                $data[$key] = $value;
            } elseif (preg_match('/^([^:]+):\s*(.*)$/', $line, $parts)) {
                $key = trim($parts[1]);
                $value = trim($parts[2]);
                $data[$key] = $value;
            }
        }

        // Validate required fields
        if (!isset($data['title'], $data['url'], $data['source'], $data['published_at'])) {
            return null;
        }

        $article = new self(
            $data['title'],
            $data['url'],
            $data['source'],
            $data['published_at'],
            $data['summary'] ?? '',
            $articleContent,
            $data['image_url'] ?? '' // Extract image URL from front matter
        );

        if (isset($data['slug'])) {
            $article->slug = $data['slug'];
        }

        return $article;
    }
}