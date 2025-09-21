<?php

namespace App\Services;

use SendGrid;
use SendGrid\Mail\Mail;
use Exception;

class EmailService
{
    private $sendgrid;
    private $fromEmail;
    private $fromName;
    private $toEmail;

    public function __construct()
    {
        $apiKey = $_ENV['SENDGRID_API_KEY'] ?? null;
        if (!$apiKey) {
            throw new Exception('SendGrid API key not configured');
        }

        $this->sendgrid = new SendGrid($apiKey);
        $this->fromEmail = $_ENV['SENDGRID_FROM_EMAIL'] ?? 'no-reply@ainews.com';
        $this->fromName = $_ENV['SENDGRID_FROM_NAME'] ?? 'AI News Crawler';
        $this->toEmail = $_ENV['SENDGRID_TO_EMAIL'] ?? null;

        if (!$this->toEmail) {
            throw new Exception('SendGrid recipient email not configured');
        }
    }

    /**
     * Send crawl results summary via email
     */
    public function sendCrawlResults(array $stats, float $duration): bool
    {
        try {
            $subject = "AI News Crawler Results - " . date('Y-m-d H:i:s');
            $htmlContent = $this->generateCrawlResultsHtml($stats, $duration);
            $textContent = $this->generateCrawlResultsText($stats, $duration);

            return $this->sendEmail($subject, $htmlContent, $textContent);
        } catch (Exception $e) {
            error_log("Failed to send crawl results email: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Send list of saved articles via email
     */
    public function sendArticlesList(array $articles): bool
    {
        try {
            $subject = "AI News Articles List - " . date('Y-m-d H:i:s');
            $htmlContent = $this->generateArticlesListHtml($articles);
            $textContent = $this->generateArticlesListText($articles);

            return $this->sendEmail($subject, $htmlContent, $textContent);
        } catch (Exception $e) {
            error_log("Failed to send articles list email: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Send combined crawl results and articles list
     */
    public function sendCombinedReport(array $stats, float $duration, array $articles): bool
    {
        try {
            $subject = "AI News Crawler Complete Report - " . date('Y-m-d H:i:s');
            $htmlContent = $this->generateCombinedReportHtml($stats, $duration, $articles);
            $textContent = $this->generateCombinedReportText($stats, $duration, $articles);

            return $this->sendEmail($subject, $htmlContent, $textContent);
        } catch (Exception $e) {
            error_log("Failed to send combined report email: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Send email using SendGrid
     */
    private function sendEmail(string $subject, string $htmlContent, string $textContent): bool
    {
        try {
            $email = new Mail();
            $email->setFrom($this->fromEmail, $this->fromName);
            $email->setSubject($subject);
            $email->addTo($this->toEmail);
            $email->addContent("text/plain", $textContent);
            $email->addContent("text/html", $htmlContent);

            $response = $this->sendgrid->send($email);

            if ($response->statusCode() >= 200 && $response->statusCode() < 300) {
                return true;
            } else {
                error_log("SendGrid API error - Status: " . $response->statusCode() . ", Body: " . $response->body());
                return false;
            }
        } catch (Exception $e) {
            error_log("SendGrid send error: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Generate HTML content for crawl results
     */
    private function generateCrawlResultsHtml(array $stats, float $duration): string
    {
        $html = "<html><body>";
        $html .= "<h2>AI News Crawler Results</h2>";
        $html .= "<p><strong>Date:</strong> " . date('Y-m-d H:i:s') . "</p>";
        $html .= "<p><strong>Duration:</strong> {$duration} seconds</p>";
        $html .= "<p><strong>Total Articles Processed:</strong> {$stats['total_processed']}</p>";
        $html .= "<p><strong>Total Articles Saved:</strong> {$stats['total_saved']}</p>";
        $html .= "<p><strong>Successful Sources:</strong> " . (count($stats['sources']) - count($stats['failed_sources'] ?? [])) . "</p>";
        $html .= "<p><strong>Failed Sources:</strong> " . count($stats['failed_sources'] ?? []) . "</p>";

        if (!empty($stats['failed_sources'])) {
            $html .= "<h3>Failed Sources:</h3><ul>";
            foreach ($stats['failed_sources'] as $failedSource) {
                $html .= "<li>{$failedSource}</li>";
            }
            $html .= "</ul>";
        }

        $html .= "<h3>Detailed Stats per Source:</h3><ul>";
        foreach ($stats['sources'] as $sourceName => $sourceStats) {
            $status = !in_array($sourceName, $stats['failed_sources'] ?? []) ? "✓" : "✗";
            $html .= "<li>{$status} <strong>{$sourceName}:</strong> {$sourceStats['saved']} saved";
            
            if (!empty($sourceStats['error_message'])) {
                $html .= " - ERROR: " . htmlspecialchars($sourceStats['error_message']);
            } else {
                if ($sourceStats['errors'] > 0) {
                    $html .= ", {$sourceStats['errors']} errors";
                }
                if ($sourceStats['processed'] > $sourceStats['saved']) {
                    $duplicates = $sourceStats['processed'] - $sourceStats['saved'];
                    $html .= ", {$duplicates} duplicates skipped";
                }
            }
            $html .= "</li>";
        }
        $html .= "</ul></body></html>";

        return $html;
    }

    /**
     * Generate plain text content for crawl results
     */
    private function generateCrawlResultsText(array $stats, float $duration): string
    {
        $text = "AI News Crawler Results\n";
        $text .= "========================\n\n";
        $text .= "Date: " . date('Y-m-d H:i:s') . "\n";
        $text .= "Duration: {$duration} seconds\n";
        $text .= "Total Articles Processed: {$stats['total_processed']}\n";
        $text .= "Total Articles Saved: {$stats['total_saved']}\n";
        $text .= "Successful Sources: " . (count($stats['sources']) - count($stats['failed_sources'] ?? [])) . "\n";
        $text .= "Failed Sources: " . count($stats['failed_sources'] ?? []) . "\n\n";

        if (!empty($stats['failed_sources'])) {
            $text .= "Failed Sources:\n";
            $text .= "--------------\n";
            foreach ($stats['failed_sources'] as $failedSource) {
                $text .= "✗ {$failedSource}\n";
            }
            $text .= "\n";
        }

        $text .= "Detailed Stats per Source:\n";
        $text .= "--------------------------\n";
        foreach ($stats['sources'] as $sourceName => $sourceStats) {
            $status = !in_array($sourceName, $stats['failed_sources'] ?? []) ? "✓" : "✗";
            $text .= "{$status} {$sourceName}: {$sourceStats['saved']} saved";
            
            if (!empty($sourceStats['error_message'])) {
                $text .= " - ERROR: " . $sourceStats['error_message'];
            } else {
                if ($sourceStats['errors'] > 0) {
                    $text .= ", {$sourceStats['errors']} errors";
                }
                if ($sourceStats['processed'] > $sourceStats['saved']) {
                    $duplicates = $sourceStats['processed'] - $sourceStats['saved'];
                    $text .= ", {$duplicates} duplicates skipped";
                }
            }
            $text .= "\n";
        }

        return $text;
    }

    /**
     * Generate HTML content for articles list
     */
    private function generateArticlesListHtml(array $articles): string
    {
        $html = "<html><body>";
        $html .= "<h2>AI News Articles List</h2>";
        $html .= "<p><strong>Date:</strong> " . date('Y-m-d H:i:s') . "</p>";
        $html .= "<p><strong>Total Articles:</strong> " . count($articles) . "</p>";

        if (!empty($articles)) {
            $html .= "<h3>Recent Articles:</h3><ul>";
            foreach ($articles as $article) {
                $html .= "<li>";
                $html .= "<strong><a href='" . htmlspecialchars($article['url']) . "'>" . htmlspecialchars($article['title']) . "</a></strong><br>";
                $html .= "<em>Source: " . htmlspecialchars($article['source']) . "</em><br>";
                $html .= "<em>Published: " . htmlspecialchars($article['published_at']) . "</em><br>";
                if (!empty($article['summary'])) {
                    $html .= "<p>" . htmlspecialchars(substr($article['summary'], 0, 200)) . "...</p>";
                }
                $html .= "</li>";
            }
            $html .= "</ul>";
        }

        $html .= "</body></html>";
        return $html;
    }

    /**
     * Generate plain text content for articles list
     */
    private function generateArticlesListText(array $articles): string
    {
        $text = "AI News Articles List\n";
        $text .= "=====================\n\n";
        $text .= "Date: " . date('Y-m-d H:i:s') . "\n";
        $text .= "Total Articles: " . count($articles) . "\n\n";

        if (!empty($articles)) {
            $text .= "Recent Articles:\n";
            $text .= "----------------\n";
            foreach ($articles as $article) {
                $text .= "Title: " . $article['title'] . "\n";
                $text .= "URL: " . $article['url'] . "\n";
                $text .= "Source: " . $article['source'] . "\n";
                $text .= "Published: " . $article['published_at'] . "\n";
                if (!empty($article['summary'])) {
                    $text .= "Summary: " . substr($article['summary'], 0, 200) . "...\n";
                }
                $text .= "\n";
            }
        }

        return $text;
    }

    /**
     * Generate HTML content for combined report
     */
    private function generateCombinedReportHtml(array $stats, float $duration, array $articles): string
    {
        $html = $this->generateCrawlResultsHtml($stats, $duration);
        $html = str_replace("</body></html>", "", $html);
        
        $articlesHtml = $this->generateArticlesListHtml($articles);
        $articlesHtml = str_replace("<html><body>", "", $articlesHtml);
        $articlesHtml = str_replace("<h2>AI News Articles List</h2>", "<h2>Recently Saved Articles</h2>", $articlesHtml);
        
        return $html . $articlesHtml;
    }

    /**
     * Generate plain text content for combined report
     */
    private function generateCombinedReportText(array $stats, float $duration, array $articles): string
    {
        $text = $this->generateCrawlResultsText($stats, $duration);
        $text .= "\n\n";
        
        $articlesText = $this->generateArticlesListText($articles);
        $articlesText = str_replace("AI News Articles List", "Recently Saved Articles", $articlesText);
        
        return $text . $articlesText;
    }
}