<?php

namespace App\Services;

use App\Models\Article;
use DateTime;
use Exception;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\RequestException;
use Symfony\Component\DomCrawler\Crawler;

class CrawlerService
{
    private $httpClient;
    private $sources;
    private $storageService;
    private $maxArticlesPerSource;
    private $requestCount           = 0;
    private $requestStartTime;
    private $maxRequestsPerMinute;
    private $consecutiveFailures    = 0;
    private $maxConsecutiveFailures = 3;

    public function __construct()
    {
        $this->maxRequestsPerMinute = intval($_ENV['MAX_REQUESTS_PER_MINUTE'] ?? 30);
        $this->requestStartTime = microtime(true);

        $config = [
            'timeout'         => 15,
            'connect_timeout' => 10,
            'headers'         => [
                'User-Agent' => $_ENV['HTTP_USER_AGENT'] ?? 'AI News Aggregator Bot 1.0 (+https://yourdomain.com/bot)',
            ],
        ];

        // SSL certificate verification configuration
        $sslVerify = $_ENV['SSL_VERIFY'] ?? 'true';

        if ($sslVerify === 'false' || $sslVerify === false || $sslVerify === '0') {
            $config['verify'] = false;
            error_log("WARNING: SSL certificate verification is disabled");
        }
        elseif ($sslVerify !== 'true' && file_exists($sslVerify)) {
            $config['verify'] = $sslVerify;
            error_log("Using custom CA bundle: " . $sslVerify);
        }

        $this->httpClient = new Client($config);

        $this->sources = require __DIR__ . '/../config/sources.php';
        $this->maxArticlesPerSource = intval($_ENV['MAX_ARTICLES_PER_SOURCE'] ?? 10);
    }

    public function setCrawlerDependencies($storageService)
    {
        $this->storageService = $storageService;
    }

    public function crawlAllSources(): array
    {
        $stats = [
            'total_processed' => 0,
            'total_saved'     => 0,
            'sources'         => [],
            'failed_sources'  => [],
        ];

        echo "Starting crawl of all sources...\n";
        echo "Crawling " . count($this->sources) . " sources\n\n";

        foreach ($this->sources as $source) {
            echo "Crawling {$source['name']}...\n";

            try {
                $sourceStats = $this->crawlSource($source);
                $stats['sources'][$source['name']] = $sourceStats;
                $stats['total_processed'] += $sourceStats['processed'];
                $stats['total_saved'] += $sourceStats['saved'];

                echo "Completed {$source['name']}: {$sourceStats['saved']} articles saved ({$sourceStats['errors']} errors)\n\n";

                // Apply delay between sources
                $delayBetweenSources = intval($_ENV['CRAWL_DELAY_BETWEEN_SOURCES'] ?? 3000000);
                if ($delayBetweenSources > 0) {
                    echo "  ⏳ Waiting " . ($delayBetweenSources / 1000000) . " seconds before next source...\n";
                    $this->applyRandomizedDelay($delayBetweenSources);
                }
            } catch (Exception $e) {
                // Log the error but continue with next source
                $errorStats = [
                    'processed'     => 0,
                    'saved'         => 0,
                    'errors'        => 1,
                    'error_message' => $e->getMessage(),
                ];

                $stats['sources'][$source['name']] = $errorStats;
                $stats['failed_sources'][] = $source['name'];

                echo "FAILED {$source['name']}: " . $e->getMessage() . "\n";
                echo "Bypassing this source and continuing with next...\n\n";

                // Log to file
                error_log("Source failed: {$source['name']} - " . $e->getMessage());
            }
        }

        echo "Crawl completed! ";
        if (!empty($stats['failed_sources'])) {
            echo count($stats['failed_sources']) . " sources failed, ";
        }
        echo "{$stats['total_saved']} articles saved from " . (count($this->sources) - count(
                    $stats['failed_sources']
                )) . " successful sources\n";

        if (!empty($stats['failed_sources'])) {
            echo "\nFailed sources: " . implode(', ', $stats['failed_sources']) . "\n";
        }

        return $stats;
    }

    private function crawlSource(array $source): array
    {
        $stats = [
            'processed' => 0,
            'saved'     => 0,
            'errors'    => 0,
        ];

        try {
            // Build URL - handle search queries if present
            if (!empty($source['search_query'])) {
                $url = $source['base_url'] . $source['endpoint'] . '?' . $source['search_query'];
            }
            else {
                $url = $source['base_url'] . $source['endpoint'];
            }

            echo "  Fetching: {$url}\n";

            // Enforce rate limiting
            $this->enforceRateLimit();

            // Make HTTP request with error handling
            try {
                $response = $this->httpClient->get($url);

                // Check for 429 Too Many Requests
                if ($response->getStatusCode() == 429) {
                    // Extract retry-after header if available
                    $retryAfter = $response->getHeader('Retry-After');
                    $waitTime = !empty($retryAfter) ? (int)$retryAfter[0] : 60;

                    echo "  ⚠️  429 Too Many Requests - waiting {$waitTime} seconds...\n";
                    sleep($waitTime);

                    // Retry the request
                    $response = $this->httpClient->get($url);
                }

                // Check if response is successful
                if ($response->getStatusCode() !== 200) {
                    throw new Exception("HTTP {$response->getStatusCode()}: " . $response->getReasonPhrase());
                }

                $html = (string)$response->getBody();

                if (empty($html)) {
                    throw new Exception("Empty response received");
                }
            } catch (RequestException $e) {
                // Handle 429 errors specifically
                if ($e->getCode() == 429 || strpos($e->getMessage(), '429') !== false) {
                    echo "  ⚠️  429 Too Many Requests error detected\n";

                    // Extract retry-after from exception if possible
                    $waitTime = 60; // Default wait time

                    if (method_exists($e, 'getResponse') && $e->getResponse()) {
                        $retryAfter = $e->getResponse()->getHeader('Retry-After');
                        if (!empty($retryAfter)) {
                            $waitTime = (int)$retryAfter[0];
                        }
                    }

                    echo "  ⏳ Waiting {$waitTime} seconds before retrying...\n";
                    $this->applyRandomizedDelay($waitTime);

                    // Retry once
                    try {
                        $response = $this->httpClient->get($url);
                        $html = (string)$response->getBody();
                    } catch (Exception $retryException) {
                        throw new Exception("Retry failed: " . $retryException->getMessage());
                    }
                }
                // Handle SSL certificate error specifically
                elseif (strpos($e->getMessage(), 'SSL certificate problem') !== false) {
                    throw new Exception(
                        "SSL Certificate Error: " . $e->getMessage() . ". Check your SSL_VERIFY setting in .env file."
                    );
                }
                else {
                    throw new Exception("HTTP request failed: " . $e->getMessage());
                }
            } catch (Exception $e) {
                throw new Exception("Failed to fetch page: " . $e->getMessage());
            }

            // Parse HTML
            try {
                $crawler = new Crawler($html);
            } catch (Exception $e) {
                throw new Exception("Failed to parse HTML: " . $e->getMessage());
            }

            // Find all articles with enhanced selector support
            $articleNodes = $this->filterWithEnhancedSelectors($crawler, $source['selectors']['articles']);

            if ($articleNodes->count() === 0) {
                echo "  No articles found with selector: {$source['selectors']['articles']}\n";
                // This is not an error, just no articles found
                return $stats;
            }

            // Limit the number of articles to process
            $articleCount = min(
                $articleNodes->count(),
                intval(
                    $source['selectors']['count']
                    ?? $this->maxArticlesPerSource
                )
            );

            $aggressive = $_ENV['CRAWL_AGGRESSIVE'] ?? false;
            $articleCount = $aggressive ? $articleNodes->count() : $articleCount;

            echo "  Found {$articleNodes->count()} articles, processing {$articleCount}...\n";


            for ($i = 0; $i < $articleCount; $i++) {
                try {
                    $articleNode = $articleNodes->eq($i);
                    $article = $this->extractArticleData($articleNode, $source);

                    if ($article) {
                        $stats['processed']++;

                        // Save article if it doesn't exist
                        if ($this->storageService->saveArticle($article)) {
                            $stats['saved']++;
                            echo "  ✓ Saved: "
                                 . substr($article->title, 0, 70)
                                 . (strlen($article->title) > 70
                                    ? "..."
                                    : "") . "\n";
                        }
                        else {
                            echo "  ℹ Skipped (duplicate): "
                                 . substr($article->title, 0, 50)
                                 . (strlen($article->title) > 50
                                    ? "..."
                                    : "") . "\n";
                        }
                    }
                    else {
                        echo "  ℹ Skipped (missing required data)\n";
                    }
                } catch (Exception $e) {
                    $stats['errors']++;
                    echo "  ✗ Error processing article: " . $e->getMessage() . "\n";
                    error_log("Error processing article from {$source['name']}: " . $e->getMessage());
                }

                // Apply delay between articles
                $delayBetweenArticles = intval($_ENV['CRAWL_DELAY_BETWEEN_ARTICLES'] ?? 500000);
                $this->applyDelay($delayBetweenArticles);

                // Enforce rate limiting
                $this->enforceRateLimit();
            }
        } catch (Exception $e) {
            $stats['errors']++;
            throw $e; // Re-throw to be caught by crawlAllSources
        }

        return $stats;
    }

    private function enforceRateLimit()
    {
        $currentTime = microtime(true);
        $elapsedTime = $currentTime - $this->requestStartTime;

        // If we've made too many requests in the last minute
        if ($this->requestCount >= $this->maxRequestsPerMinute && $elapsedTime < 60) {
            $sleepTime = 60 - $elapsedTime;
            echo "  ⏸️  Rate limit approaching - pausing for " . round($sleepTime, 1) . " seconds...\n";
            sleep($sleepTime);

            // Reset counters
            $this->requestCount = 0;
            $this->requestStartTime = microtime(true);
        }

        // Increment request counter
        $this->requestCount++;
    }

    private function applyRandomizedDelay($baseDelayMicroseconds, $jitterPercentage = 0.3)
    {
        if ($baseDelayMicroseconds > 0) {
            // Add jitter (random variation) to avoid detectable patterns
            $jitter = $baseDelayMicroseconds * $jitterPercentage;
            $randomDelay = $baseDelayMicroseconds + rand(-$jitter, $jitter);
            $randomDelay = max(0, $randomDelay); // Ensure delay is not negative

            usleep((int)$randomDelay);
        }
    }

    /**
     * Enhanced CSS selector filtering that supports wildcards and partial class names
     *
     * Supports:
     * - [class*="partial"] - contains partial class name
     * - [class^="starts"] - starts with class name
     * - [class$="ends"] - ends with class name
     * - div[class~="exact"] - contains exact class name
     * - Regular CSS selectors
     */
    private function filterWithEnhancedSelectors(Crawler $crawler, string $selector): Crawler
    {
        // Check if selector contains attribute wildcard patterns
        if (preg_match('/\[class\*="([^"]+)"\]/', $selector, $matches)) {
            // Handle contains partial class name: [class*="partial"]
            $partialClass = $matches[1];
            $tagName = preg_replace('/\[class\*="[^"]+"\]/', '', $selector);
            $tagName = trim($tagName) ?: '*';

            return $crawler->filter($tagName)->reduce(function (Crawler $node) use ($partialClass) {
                $classAttr = $node->attr('class');
                return $classAttr !== null && strpos($classAttr, $partialClass) !== false;
            });
        }

        if (preg_match('/\[class\^="([^"]+)"\]/', $selector, $matches)) {
            // Handle starts with class name: [class^="starts"]
            $startsWith = $matches[1];
            $tagName = preg_replace('/\[class\^="[^"]+"\]/', '', $selector);
            $tagName = trim($tagName) ?: '*';

            return $crawler->filter($tagName)->reduce(function (Crawler $node) use ($startsWith) {
                $classAttr = $node->attr('class');
                return $classAttr !== null && strpos($classAttr, $startsWith) === 0;
            });
        }

        if (preg_match('/\[class\$="([^"]+)"\]/', $selector, $matches)) {
            // Handle ends with class name: [class$="ends"]
            $endsWith = $matches[1];
            $tagName = preg_replace('/\[class\$="[^"]+"\]/', '', $selector);
            $tagName = trim($tagName) ?: '*';

            return $crawler->filter($tagName)->reduce(function (Crawler $node) use ($endsWith) {
                $classAttr = $node->attr('class');
                return $classAttr !== null && substr($classAttr, -strlen($endsWith)) === $endsWith;
            });
        }

        if (preg_match('/\[class~="([^"]+)"\]/', $selector, $matches)) {
            // Handle contains exact class name: [class~="exact"]
            $exactClass = $matches[1];
            $tagName = preg_replace('/\[class~="[^"]+"\]/', '', $selector);
            $tagName = trim($tagName) ?: '*';

            return $crawler->filter($tagName)->reduce(function (Crawler $node) use ($exactClass) {
                $classAttr = $node->attr('class');
                if ($classAttr === null) {
                    return false;
                }
                $classes = explode(' ', $classAttr);
                return in_array($exactClass, $classes);
            });
        }

        // For The Register, add specific handling for their structure
        if (strpos($selector, '.ai_ml') !== false) {
            // Try multiple approaches for The Register
            $selectorsToTry = [
                $selector,
                'article', // Try generic article tag
                '.article', // Try common class
                'div[class*="story"]', // Try partial class match
                'div[class*="teaser"]', // Try partial class match
                'div[class*="item"]', // Try partial class match
            ];

            foreach ($selectorsToTry as $trySelector) {
                try {
                    $result = $crawler->filter($trySelector);
                    if ($result->count() > 0) {
                        return $result;
                    }
                } catch (Exception $e) {
                    // Continue to next selector
                }
            }
        }

        // Default: use regular CSS selector
        try {
            return $crawler->filter($selector);
        } catch (Exception $e) {
            // Return empty crawler if selector fails
            return new Crawler();
        }
    }

    private function extractArticleData(Crawler $articleNode, array $source): ?Article
    {
        $selectors = $source['selectors'];

        try {
            // Extract title with enhanced selector support
            $titleElement = $this->filterWithEnhancedSelectors($articleNode, $selectors['title']);
            /*if (PHP_SAPI === 'cli') {
                echo 'Selector:' . $selectors['title'] . ": " . print_r($titleElement,true);
            }*/
            if ($titleElement->count() === 0) {
                throw new Exception("Title element not found");
            }
            $title = trim($titleElement->text());
            if (empty($title)) {
                throw new Exception("Empty title");
            }

            // Extract URL with enhanced selector support
            $urlElement = $this->filterWithEnhancedSelectors($articleNode, $selectors['url']);
            if ($urlElement->count() === 0) {
                throw new Exception("URL element not found");
            }
            $url = $urlElement->attr('href');
            if (empty($url)) {
                throw new Exception("Empty URL");
            }

            // Make sure URL is absolute
            if ($url && strpos($url, 'http') !== 0) {
                if (strpos($url, '/') === 0) {
                    $url = $source['base_url'] . $url;
                }
                else {
                    $url = $source['base_url'] . '/' . $url;
                }
            }
            // Extract image URL
            $imageUrl = '';
            if (!empty($selectors['image'])) {
                $imageElements = $this->filterWithEnhancedSelectors($articleNode, $selectors['image']);
                if ($imageElements->count() > 0) {
                    // Try different attributes for image URL
                    $imageUrl = $imageElements->attr('src') ?:
                        $imageElements->attr('data-src') ?:
                            $imageElements->attr('data-lazy-src') ?:
                                $imageElements->attr('data-original') ?: '';

                    // Make image URL absolute if it's relative
                    if ($imageUrl && strpos($imageUrl, 'http') !== 0) {
                        if (strpos($imageUrl, '//') === 0) {
                            $imageUrl = 'https:' . $imageUrl;
                        }
                        elseif (strpos($imageUrl, '/') === 0) {
                            $imageUrl = $source['base_url'] . $imageUrl;
                        }
                        else {
                            $imageUrl = $source['base_url'] . '/' . $imageUrl;
                        }
                    }
                }
            }

            // Extract summary with enhanced selector support
            $summary = $title;
            if (!empty($selectors['summary'])) {
                $summaryElements = $this->filterWithEnhancedSelectors($articleNode, $selectors['summary']);
                if ($summaryElements->count() > 0) {
                    $summary = trim($summaryElements->text());
                    // Clean up the summary
                    $summary = preg_replace('/\s+/', ' ', $summary);
                    // Limit summary length if too long
                    if (strlen($summary) > 500) {
                        $summary = substr($summary, 0, 500) . '...';
                    }
                }
            }

            // Extract and parse date
            $publishedAt = date('Y-m-d H:i:s');
            if (!empty($selectors['date'])) {
                $dateElements = $this->filterWithEnhancedSelectors($articleNode, $selectors['date']);
                if ($dateElements->count() > 0) {
                    // Try to get datetime attribute first, then text content
                    $dateText = $dateElements->attr('datetime') ?: $dateElements->attr(
                        'content'
                    ) ?: $dateElements->text();

                    if ($dateText) {
                        try {
                            $dateFormat = $selectors['date_format'] ?? 'Y-m-d H:i:s';

                            // Handle different date formats
                            $date = null;
                            if ($dateFormat === 'F j, Y') {
                                // Handle "October 15, 2023" format
                                $date = DateTime::createFromFormat('F j, Y', trim($dateText));
                            }
                            elseif ($dateFormat === 'M j, Y') {
                                // Handle "Oct 15, 2023" format
                                $date = DateTime::createFromFormat('M j, Y', trim($dateText));
                            }
                            elseif ($dateFormat === 'Y-m-d') {
                                // Handle "2023-10-15" format
                                $date = DateTime::createFromFormat('Y-m-d', trim($dateText));
                            }
                            elseif ($dateFormat === 'j M Y') {
                                // Handle "30 Jun 2025" format (for The Register)
                                $date = DateTime::createFromFormat('j M Y', trim($dateText));
                            }
                            else {
                                // Try the specified format
                                $date = DateTime::createFromFormat($dateFormat, trim($dateText));
                            }

                            // If specific format failed, try common formats
                            if (!$date) {
                                // List of common date formats to try
                                $formatsToTry = [
                                    'Y-m-d\TH:i:sP',
                                    'Y-m-d\TH:i:s',
                                    'Y-m-d H:i:s',
                                    'F j, Y',
                                    'M j, Y',
                                    'Y-m-d',
                                    'm/d/Y',
                                    'd/m/Y',
                                    'j F Y',
                                    'j M Y',
                                    'jS M Y',
                                    'Y-m-d\TH:i:s.v\Z',
                                ];

                                foreach ($formatsToTry as $format) {
                                    $date = DateTime::createFromFormat($format, trim($dateText));
                                    if ($date) {
                                        break;
                                    }
                                }
                            }

                            if ($date) {
                                $publishedAt = $date->format('Y-m-d H:i:s');
                            }
                            else {
                                error_log(
                                    "Could not parse date '{$dateText}' 
                                    for article '{$title}' from {$source['name']}"
                                );
                            }
                        } catch (Exception $e) {
                            // Use current date if parsing fails
                            error_log(
                                "Error parsing date '{$dateText}' 
                                for article '{$title}' from {$source['name']}: "
                                . $e->getMessage(
                                )
                            );
                        }
                    }
                }
            }

            // Fetch full content (optional - could be done later)
            $content = $this->fetchFullContent($url) ?: $summary;

            return new Article(
                $title,
                $url,
                $source['name'],
                $publishedAt,
                $summary,
                $content,
                $imageUrl // Pass image URL to constructor
            );
        } catch (Exception $e) {
            error_log("Error extracting article data from {$source['name']}: " . $e->getMessage());
            return null;
        }
    }

    private function fetchFullContent(string $url): string
    {
        try {
            $response = $this->httpClient->get($url, ['timeout' => 8]);

            // Check if response is successful
            if ($response->getStatusCode() !== 200) {
                throw new Exception("HTTP {$response->getStatusCode()}");
            }

            $html = (string)$response->getBody();

            if (empty($html)) {
                throw new Exception("Empty response");
            }

            $crawler = new Crawler($html);

            // Try common selectors for article content
            $contentSelectors = [
                'article[itemprop="articleBody"]',
                'article .article-content',
                'article .post-content',
                'article .entry-content',
                'article main',
                'div[itemprop="articleBody"]',
                '.article-body',
                '.post-content',
                '.entry-content',
                'main article',
                'article',
                'main',
                '.content',
                '.body-content',
                '.article-content',
                '.page-content',
                '.blog-post'
            ];

            foreach ($contentSelectors as $selector) {
                try {
                    $contentNodes = $this->filterWithEnhancedSelectors($crawler, $selector);
                    if ($contentNodes->count() > 0) {
                        // Clone the crawler to avoid modifying the original
                        $contentCrawler = clone $contentNodes;

                        // Remove unwanted elements (ads, comments, navigation, etc.)
                        $elementsToRemove = [
                            'script',
                            'style',
                            'nav',
                            'footer',
                            '.comments',
                            '.advertisement',
                            '.ads',
                            '.social-share',
                            '.related-posts',
                            '.author-bio',
                            '.tags',
                            '.categories',
                            '#comments',
                            '.comment',
                            '.sidebar',
                            '.widget',
                            '.header',
                            '.byline',
                            '.meta',
                        ];

                        foreach ($elementsToRemove as $removeSelector) {
                            try {
                                $contentCrawler->filter($removeSelector)->each(function (Crawler $node) {
                                    if ($node->getNode(0) && $node->getNode(0)->parentNode) {
                                        $node->getNode(0)->parentNode->removeChild($node->getNode(0));
                                    }
                                });
                            } catch (Exception $e) {
                                // Ignore errors in element removal
                            }
                        }

                        // Get text content
                        $content = trim($contentCrawler->text());

                        // Clean up whitespace
                        $content = preg_replace('/\s+/', ' ', $content);
                        $content = trim($content);

                        if (strlen($content) > 50) {
                            return $content;
                        }
                    }
                } catch (Exception $e) {
                    // Continue to next selector if this one fails
                    continue;
                }
            }

            // If no specific content container found, use body but remove common non-content elements
            try {
                $bodyCrawler = clone $crawler->filter('body');

                // Remove header, footer, nav, sidebar
                $bodyCrawler->filter('header, footer, nav, aside, .sidebar, .header')->each(function (Crawler $node) {
                    if ($node->getNode(0) && $node->getNode(0)->parentNode) {
                        $node->getNode(0)->parentNode->removeChild($node->getNode(0));
                    }
                });

                $bodyContent = trim($bodyCrawler->text());
                $bodyContent = preg_replace('/\s+/', ' ', $bodyContent);

                if (strlen($bodyContent) > 100) {
                    return $bodyContent;
                }
            } catch (Exception $e) {
                // Fall back to empty string
            }

            return '';
        } catch (Exception $e) {
            error_log("Error fetching full content from {$url}: " . $e->getMessage());
            return '';
        }
    }

    private function applyDelay($delayMicroseconds)
    {
        $this->applyRandomizedDelay($delayMicroseconds);
    }

    private function handleSourceFailure($sourceName, $errorMessage)
    {
        $this->consecutiveFailures++;

        if ($this->consecutiveFailures >= $this->maxConsecutiveFailures) {
            $cooldownTime = 5 * 60; // 5 minutes cooldown
            echo "  🚨 Too many consecutive failures. Cooling down for {$cooldownTime} seconds...\n";
            sleep($cooldownTime);
            $this->consecutiveFailures = 0; // Reset after cooldown
        }

        throw new Exception("Source {$sourceName} failed: {$errorMessage}");
    }
}