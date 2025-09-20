# AI News Aggregator

A news aggregator website built with Slim PHP Framework that fetches and displays news articles about Artificial Intelligence.

## Features

- Crawls AI news from multiple trusted sources
- Stores articles as markdown files with YAML front matter
- Deduplication to prevent storing duplicate articles
- Automatic cleanup of articles older than 30 days
- Twig templating for beautiful article listings and details
- CLI command for manual or scheduled crawling
- Configurable crawling frequency and article limits

## Installation

1. Clone the repository:
```bash

git clone https://github.com/aingelc12ell/ainewscrawlerphp.git
cd ai-news-aggregator
```
2. Install dependencies:
```bash

composer install
```
3. Copy and configure environment variables:
```bash

cp .env.example .env
# Edit .env file as needed
```
4. Make the CLI script executable:
```bash

chmod +x cli/crawl.php
```
5. Set up web server to point to the `public` directory.

### Usage

#### Web Interface
Visit your website to see the latest AI articles:
- Homepage: `/` - Lists all recent articles
- Article detail: `/article/{slug}` - Shows full article content
- Manual crawl: `/crawl` - Triggers crawling from web (returns JSON)

#### CLI Command
Run the crawler manually:
```bash

php cli/crawl.php
```

##### Scheduling
Set up a cron job to crawl automatically. For example, to crawl every hour:
```bash

# Edit crontab
crontab -e

# Add this line (adjust path as needed)
0 * * * * cd /path/to/ai-news-aggregator && php cli/crawl.php >> /path/to/ai-news-aggregator/storage/logs/crawl.log 2>&1
```

### Configuration
Edit the `.env` file to configure:

- `STORAGE_PATH` - Where markdown files are stored
- `MAX_ARTICLES_PER_SOURCE` - Maximum articles to fetch per source (default: 10)
- `CRAWL_FREQUENCY` - How often to crawl in seconds (used for scheduling)
- `DELETE_OLDER_THAN_DAYS` - Articles older than this many days will be deleted (default: 30)

### Supported News Sources

The aggregator currently crawls from these AI-focused news sources:

1. **TechCrunch** - AI category
2. **The Verge** - AI section
3. **MIT Technology Review** - AI topic
4. **Wired** - Artificial Intelligence category
5. **Ars Technica** - AI tag
6. **VentureBeat** - AI section
7. **ZDNet** - AI topic
8. **IEEE Spectrum** - AI topic
9. **Analytics India Magazine** - AI category
10. **Synced** - AI category
11. **AI Trends** - AI search results
12. **MarkTechPost** - AI category
13. **Towards AI** - AI category
14. **DeepLearning.AI** - Blog section
15. **Google AI Blog** - Main page
16. **OpenAI Blog** - Blog section
17. **Machine Learning Mastery** - Blog
18. **KDnuggets** - AI search results
19. **Data Science Central** - Blog
20. **The Gradient** - Main page

You can add more sources by editing the `app/config/sources.php` file.

### Adding New Sources
To add a new news source, edit `app/config/sources.php` and add a new source configuration:
```php
[
    'name' => 'Source Name',
    'base_url' => 'https://example.com',
    'endpoint' => '/ai-news/',
    'selectors' => [
        'articles' => 'css-selector-for-article-container',
        'title' => 'css-selector-for-title',
        'url' => 'css-selector-for-url',
        'summary' => 'css-selector-for-summary',
        'date' => 'css-selector-for-date',
        'date_format' => 'PHP date format'
        ]
]
```

#### File Storage Format
Articles are stored as markdown files in the format YYYY-MM-DD-slug.md with YAML front matter:
```markdown
---
title: "Article Title"
url: "https://example.com/article-url"
source: "Source Name"
published_at: "2023-10-15 14:30:00"
summary: "Brief summary of the article"
slug: "article-title-slug"
---
Full article content here...
```

## Author notes
This project has been generated from Qwen with modifications from Claude and JetBrain's Junie.
Some changes were made to fit trivial details.

## AI post-conversation Prompt
"Using the Slim PHP Framework, develop a comprehensive, production-ready news aggregator website 
focused on Artificial Intelligence. The system must crawl articles from a curated list of AI news 
sources—including The Register (specifically from https://www.theregister.com/software/ai_ml/, 
where sample content includes headlines like “AIs have a favorite number, and it's not 42” 
published on “30 Jun 2025”)—and store each article as a uniquely named markdown file (YYYY-MM-DD-slug.md) 
with YAML front matter containing title, URL, publication date, source, summary, and image URL.

Implement robust deduplication (based on URL), automatic cleanup of articles older than 30 days, 
and a responsive UI using Twig that displays articles in 240px-wide cards (max 12 per row) with 
optional list view. Cards must intelligently adapt: if no summary is available (like The 
Register’s sample article), the title should fill the entire card in larger font; if an image is 
available, it should be used as a background with readability overlays.

Provide a CLI script to trigger crawling manually or via cron, with comprehensive logging, 
error handling (bypass failing sources), and built-in rate limiting to avoid 429 errors 
(using delays, jitter, and Retry-After header respect). Support dark/light themes, pagination 
(20 articles per page default), and user-configurable items per page. Ensure all CSS is external, 
and selectors support wildcards/partial matches for resilient scraping.

The final deliverable should be a maintainable, well-documented codebase ready for deployment, 
with all components (models, services, controllers, templates, CLI) fully integrated and tested. "

## License
MIT License