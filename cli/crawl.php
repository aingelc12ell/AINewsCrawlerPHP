<?php

require __DIR__ . '/../vendor/autoload.php';

use App\Services\CrawlerService;
use App\Services\StorageService;
use Dotenv\Dotenv;

// Load environment variables
$dotenv = Dotenv::createImmutable(__DIR__ . '/..');
$dotenv->load();

class CrawlCommand
{
    private $crawlerService;
    private $storageService;

    public function __construct()
    {
        $this->storageService = new StorageService($_ENV['STORAGE_PATH']);
        $this->crawlerService = new CrawlerService();
        $this->crawlerService->setCrawlerDependencies($this->storageService);
    }

    public function run()
    {
        $startTime = microtime(true);
        $date = date('Y-m-d H:i:s');

        echo "=========================================\n";
        echo "      AI NEWS AGGREGATOR CRAWLER\n";
        echo "=========================================\n";
        echo "Starting at: {$date}\n";
        echo "Max articles per source: " . ($_ENV['MAX_ARTICLES_PER_SOURCE'] ?? 10) . "\n";
        echo "Storage path: " . ($_ENV['STORAGE_PATH'] ?? './storage/articles') . "\n";
        echo "-----------------------------------------\n\n";

        try {
            $stats = $this->crawlerService->crawlAllSources();

            $endTime = microtime(true);
            $duration = round($endTime - $startTime, 2);

            // Cleanup old articles
            echo "\nCleaning up old articles (older than " . ($_ENV['DELETE_OLDER_THAN_DAYS'] ?? 30) . " days)...\n";
            $this->storageService->cleanupOldArticles();

            echo "\n=========================================\n";
            echo "CRAWL COMPLETED\n";
            echo "=========================================\n";
            echo "Start time: {$date}\n";
            echo "End time: " . date('Y-m-d H:i:s') . "\n";
            echo "Duration: {$duration} seconds\n";
            echo "Total articles processed: {$stats['total_processed']}\n";
            echo "Total articles saved: {$stats['total_saved']}\n";
            echo "Successful sources: " . (count($stats['sources']) - count($stats['failed_sources'] ?? [])) . "\n";
            echo "Failed sources: " . count($stats['failed_sources'] ?? []) . "\n";

            if (!empty($stats['failed_sources'])) {
                echo "\nFAILED SOURCES:\n";
                echo str_repeat("-", 30) . "\n";
                foreach ($stats['failed_sources'] as $failedSource) {
                    echo "✗ {$failedSource}\n";
                }
            }

            // Print detailed stats per source
            echo "\nDETAILED STATS PER SOURCE:\n";
            echo str_repeat("-", 50) . "\n";
            foreach ($stats['sources'] as $sourceName => $sourceStats) {
                $status = !in_array($sourceName, $stats['failed_sources'] ?? []) ? "✓" : "✗";
                $result = "{$status} {$sourceName}: {$sourceStats['saved']} saved";

                if (!empty($sourceStats['error_message'])) {
                    $result .= " - ERROR: " . $sourceStats['error_message'];
                } else {
                    if ($sourceStats['errors'] > 0) {
                        $result .= ", {$sourceStats['errors']} errors";
                    }
                    if ($sourceStats['processed'] > $sourceStats['saved']) {
                        $duplicates = $sourceStats['processed'] - $sourceStats['saved'];
                        $result .= ", {$duplicates} duplicates skipped";
                    }
                }
                echo $result . "\n";
            }

            echo "\nStorage location: " . realpath($_ENV['STORAGE_PATH']) . "\n";
            $files = glob($_ENV['STORAGE_PATH'] . '/*.md');
            echo "Total articles in storage: " . count($files) . "\n";

            // Log to file
            $logMessage = "[" . date('Y-m-d H:i:s') . "] Crawl completed: {$stats['total_saved']} articles saved, " .
                          count($stats['failed_sources'] ?? []) . " sources failed, in {$duration} seconds\n";
            file_put_contents(__DIR__ . '/../storage/logs/crawl.log', $logMessage, FILE_APPEND | LOCK_EX);

            // Exit with status code 0 (success) even if some sources failed
            exit(0);

        } catch (Exception $e) {
            echo "\n=========================================\n";
            echo "CRAWL FAILED\n";
            echo "=========================================\n";
            echo "Error: " . $e->getMessage() . "\n";

            // Log error
            $errorMessage = "[" . date('Y-m-d H:i:s') . "] CRITICAL ERROR: " . $e->getMessage() . "\n";
            file_put_contents(__DIR__ . '/../storage/logs/crawl.log', $errorMessage, FILE_APPEND | LOCK_EX);

            exit(1);
        }
    }
}

// Create logs directory if it doesn't exist
$logDir = __DIR__ . '/../storage/logs';
if (!file_exists($logDir)) {
    mkdir($logDir, 0755, true);
}

// Run the command
$crawlCommand = new CrawlCommand();
$crawlCommand->run();