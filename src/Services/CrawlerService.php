<?php

declare(strict_types=1);

namespace Calevans\StaticForgeSiteDownloader\Services;

use EICC\Utils\Log;
use Symfony\Component\DomCrawler\Crawler;

class CrawlerService
{
    private Log $logger;
    private array $visited = [];
    private array $queue = [];
    private string $baseUrl;
    private string $baseHost;

    private AssetProcessor $assetProcessor;
    private ContentProcessor $contentProcessor;
    private string $sourceDir;
    private array $collectedCategories = [];

    public function __construct(
        Log $logger,
        AssetProcessor $assetProcessor,
        ContentProcessor $contentProcessor,
        string $sourceDir
    ) {
        $this->logger = $logger;
        $this->assetProcessor = $assetProcessor;
        $this->contentProcessor = $contentProcessor;
        $this->sourceDir = $sourceDir;
    }

    public function crawl(string $startUrl): void
    {
        $this->baseUrl = rtrim($startUrl, '/');
        $parsedUrl = parse_url($this->baseUrl);
        $this->baseHost = $parsedUrl['host'] ?? '';

        if (empty($this->baseHost)) {
            $this->logger->log('ERROR', "Invalid start URL: {$startUrl}");
            return;
        }

        $this->queue[] = $this->baseUrl;

        while (!empty($this->queue)) {
            $url = array_shift($this->queue);

            if (isset($this->visited[$url])) {
                continue;
            }

            $this->visited[$url] = true;
            $this->processUrl($url);
        }

        $this->generateCategoryPages();
    }

    private function processUrl(string $url): void
    {
        $this->logger->log('INFO', "Crawling: {$url}");

        $content = $this->fetchUrl($url);
        if ($content === null) {
            return;
        }

        $crawler = new Crawler($content, $url);

        // Extract links
        $crawler->filter('a')->each(function (Crawler $node) {
            try {
                $link = $node->link();
                $absoluteUrl = $link->getUri();

                // Remove fragment
                $absoluteUrl = strtok($absoluteUrl, '#');

                if ($this->isValidUrl($absoluteUrl) && !isset($this->visited[$absoluteUrl])) {
                    $this->queue[] = $absoluteUrl;
                }
            } catch (\Exception $e) {
                // Ignore invalid links
            }
        });

        // Process Assets and Links
        $this->assetProcessor->process($crawler, $url);

        // Process Content
        // We pass the crawler which now has modified DOM (assets/links rewritten)
        $result = $this->contentProcessor->process($crawler, $url);

        if (isset($result['category_data'])) {
            foreach ($result['category_data'] as $cat) {
                // Key by URL to avoid duplicates
                if (!empty($cat['url'])) {
                    $this->collectedCategories[$cat['url']] = $cat['name'];
                }
            }
        }

        // Skip saving pagination pages
        if (preg_match('/\/page\/\d+\/?$/', $url)) {
            $this->logger->log('INFO', "Skipping save for pagination page: {$url}");
            return;
        }

        // Skip saving category archive pages (we generate our own)
        if (strpos($url, '/category/') !== false) {
            $this->logger->log('INFO', "Skipping save for category archive page: {$url}");
            return;
        }

        $this->saveFile($result, $url);
    }

    private function generateCategoryPages(): void
    {
        $this->logger->log('INFO', "Generating category index pages...");

        foreach ($this->collectedCategories as $url => $name) {
            // Generate content/{slug}.md
            $slug = strtolower($name);
            $slug = preg_replace('/[^a-z0-9]+/', '-', $slug);
            $slug = trim($slug, '-');

            if (empty($slug)) {
                continue;
            }

            $relativePath = $slug . '.md';
            $fullPath = $this->sourceDir . '/' . $relativePath;

            // Don't overwrite if exists? Or maybe we should.
            // For now, let's overwrite to ensure it's correct.

            $content = "---\n";
            $content .= "title: \"{$name}\"\n";
            $content .= "type: \"category\"\n";
            $content .= "template: \"category\"\n";
            $content .= "---\n";

            file_put_contents($fullPath, $content);
            $this->logger->log('INFO', "Generated Category Index: {$relativePath}");
        }
    }

    private function fetchUrl(string $url): ?string
    {
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
        curl_setopt($ch, CURLOPT_USERAGENT, 'StaticForge/1.0');
        curl_setopt($ch, CURLOPT_HEADER, true); // We need headers to check Content-Type

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $headerSize = curl_getinfo($ch, CURLINFO_HEADER_SIZE);

        if (curl_errno($ch)) {
            $this->logger->log('ERROR', "Curl error for {$url}: " . curl_error($ch));
            curl_close($ch);
            return null;
        }

        curl_close($ch);

        if ($httpCode >= 400) {
            $this->logger->log('WARNING', "HTTP {$httpCode} for {$url}");
            return null;
        }

        $header = substr($response, 0, $headerSize);
        $body = substr($response, $headerSize);

        if (!$this->isValidContentType($header)) {
            $this->logger->log('INFO', "Skipping non-HTML content: {$url}");
            return null;
        }

        return $body;
    }

    private function saveFile(array $data, string $url): void
    {
        $relativePath = $this->determineFilePath($url, $data);
        $fullPath = $this->sourceDir . '/' . ltrim($relativePath, '/');

        // If this is the homepage, remove category metadata to prevent it from being moved
        if ($relativePath === 'index.md' || $relativePath === '/index.md') {
            unset($data['metadata']['category']);
            unset($data['metadata']['categories']);
        }

        if (!is_dir(dirname($fullPath))) {
            mkdir(dirname($fullPath), 0755, true);
        }

        $content = "---\n";
        foreach ($data['metadata'] as $key => $value) {
            if (is_array($value)) {
                if (empty($value)) {
                    continue;
                }
                $content .= "{$key}:\n";
                foreach ($value as $item) {
                    // Escape quotes if necessary, simple version for now
                    $content .= "  - \"{$item}\"\n";
                }
            } else {
                $content .= "{$key}: \"{$value}\"\n";
            }
        }
        $content .= "---\n\n";
        $content .= $data['content'];

        file_put_contents($fullPath, $content);
        $this->logger->log('INFO', "Saved: {$relativePath}");
    }

    private function determineFilePath(string $url, array $data): string
    {
        $path = parse_url($url, PHP_URL_PATH) ?? '/';

        // Homepage check
        if ($path === '/' || $path === '/index.html' || $path === '/index.php') {
            return 'index.md';
        }

        // Get slug from URL
        $slug = basename($path);

        // Remove extension if present
        if (str_ends_with($slug, '.html')) {
            $slug = substr($slug, 0, -5);
        } elseif (str_ends_with($slug, '.php')) {
            $slug = substr($slug, 0, -4);
        }

        // If slug is empty or index, try to get parent
        if ($slug === '' || $slug === 'index') {
             $parts = explode('/', trim($path, '/'));
             // Remove last empty or index part
             array_pop($parts);
             $slug = end($parts);
        }

        // Get Category
        $category = $data['metadata']['category'] ?? 'Uncategorized';
        $categorySlug = $this->slugify($category);

        return $categorySlug . '/' . $slug . '.md';
    }

    private function slugify(string $text): string
    {
        $slug = strtolower($text);
        $slug = preg_replace('/[^a-z0-9]+/', '-', $slug);
        return trim($slug, '-');
    }

    private function isValidContentType(string $header): bool
    {
        // Simple check for text/html
        return stripos($header, 'Content-Type: text/html') !== false;
    }

    private function isValidUrl(string $url): bool
    {
        $parsed = parse_url($url);
        $host = $parsed['host'] ?? '';

        // Check if host matches base host exactly (strict subdomain)
        return $host === $this->baseHost;
    }
}
