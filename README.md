# AI News Aggregator

A lightweight AI-news reader and crawler built with Slim 4, Twig, Guzzle, and filesystem-backed Markdown storage.

## Features

- Crawls 18 configured AI and technology sources while isolating source failures
- Validates outbound destinations and redirects to prevent private-network requests
- Uses TLS verification, timeouts, request pacing, per-minute limits, jitter, and `Retry-After`
- Stores articles as Markdown with YAML front matter
- Deduplicates by URL and title and writes under an exclusive lock with atomic replacement
- Provides search, source filtering, pagination, grid/list views, article detail pages, and light/dark themes
- Generates canonical `robots.txt` and sitemap responses from `APP_URL`
- Cleans up expired articles from the CLI crawl workflow, not public page requests
- Optionally sends crawl reports through SendGrid
- Includes PHPUnit coverage for HTTP security behavior, storage, and URL safety

## Requirements

- PHP 8.0 or newer with `mbstring`
- Composer
- A web server whose document root points to `public/`

## Installation

```bash
git clone https://github.com/aingelc12ell/AINewsCrawlerPHP.git
cd AINewsCrawlerPHP
composer install
cp .env-sample .env
```

Set `APP_URL` in `.env` to the exact public origin, for example `https://news.example.com`. Requests with a different Host header are rejected when this setting is present.

Create writable runtime directories if your deployment process does not create them automatically:

```bash
mkdir -p storage/articles storage/cache storage/logs
```

Point Apache, IIS, or Nginx at `public/`. The included rewrite configurations send non-file requests to `public/index.php`.

## Usage

### Web reader

- `/` — latest articles, search, and pagination
- `/article/{slug}` — locally stored article detail
- `/readme` — rendered project documentation
- `/sitemap.xml` and `/robots.txt` — canonical crawler metadata
- `/clear-cache` — force-clears the Twig cache, then redirects to the home page

Crawling is not exposed as a public HTTP endpoint. The cache-clearing route is public and should be used only when a forced Twig recompilation is needed.

### Crawl from the CLI

```bash
php cli/crawl.php
```

Schedule that command with cron or your platform scheduler. The application does not run an internal background scheduler. Example:

```cron
0 */2 * * * cd /path/to/AINewsCrawlerPHP && php cli/crawl.php >> storage/logs/crawl.log 2>&1
```

The CLI returns success when the overall crawl completes even if individual sources fail; failures remain visible in its report and log.

## Configuration

Important `.env` settings:

- `APP_URL` — trusted canonical origin used for host validation, sitemap, and robots output
- `STORAGE_PATH` — article directory; relative paths resolve from the repository root
- `MAX_ARTICLES_PER_SOURCE` — normal per-source processing limit
- `CRAWL_AGGRESSIVE` — boolean; process every discovered item when `true`
- `CRAWL_TIMEOUT` — HTTP timeout in seconds
- `MAX_REQUESTS_PER_MINUTE` — process-wide outbound request ceiling
- `CRAWL_DELAY_BETWEEN_SOURCES`, `CRAWL_DELAY_BETWEEN_ARTICLES`, `CRAWL_DELAY_BETWEEN_REQUESTS` — delays in microseconds
- `SSL_VERIFY` — keep `true`; it may also point to a custom CA bundle
- `HTTP_PROXY`, `HTTPS_PROXY`, `NO_PROXY` — optional proxy configuration
- `DELETE_OLDER_THAN_DAYS` — retention period applied after CLI crawls
- `PAGES_PER_PAGE` — default reader page size; requests are clamped to 12–100
- `SITEMAP_LIMIT` — maximum sitemap entries, clamped to 50,000
- `SENDGRID_*` — optional email-report settings

Sources and selectors live in `app/config/sources.php`. Article links are restricted to the configured source host and its subdomains. Add `allowed_hosts` to a source when it legitimately redirects to another controlled host:

```php
[
    'name' => 'Example',
    'base_url' => 'https://news.example.com',
    'endpoint' => '/ai',
    'allowed_hosts' => ['example.com', 'cdn.example.com'],
    'selectors' => [
        'articles' => 'article',
        'title' => 'h2 a',
        'url' => 'h2 a',
        'summary' => 'p',
        'date' => 'time',
        'date_format' => 'Y-m-d',
    ],
]
```

## Testing and security checks

```bash
composer test
composer validate --strict
composer audit --locked
```

The committed `composer.lock` makes deployments reproducible. Run dependency auditing as part of CI and refresh locked packages regularly.

## Configured sources

FutureTools.IO, Analytics India Magazine, The Register, ZDNet, Tom's Hardware, TechCrunch, The Verge, Wired, Ars Technica, IEEE Spectrum, Synced, MarkTechPost, Towards AI, DeepLearning.AI, Google AI Blog, The Gradient, NVIDIA Blog, and KDnuggets.

## License

MIT
