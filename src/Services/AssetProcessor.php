<?php

declare(strict_types=1);

namespace Calevans\StaticForgeSiteDownloader\Services;

use Symfony\Component\DomCrawler\Crawler;
use EICC\Utils\Log;

class AssetProcessor
{
    private string $sourceDir;
    private Log $logger;
    private array $downloadedAssets = [];

    public function __construct(string $sourceDir, Log $logger)
    {
        $this->sourceDir = $sourceDir;
        $this->logger = $logger;
    }

    public function process(Crawler $crawler, string $baseUrl): void
    {
        // Process Images
        $crawler->filter('img')->each(function (Crawler $node) use ($baseUrl) {
            $src = $node->attr('src');
            if ($src) {
                $localPath = $this->downloadAsset($src, $baseUrl, 'images');
                if ($localPath) {
                    $node->getNode(0)->setAttribute('src', $localPath);
                }
            }
        });

        // Process Internal Links
        $crawler->filter('a')->each(function (Crawler $node) use ($baseUrl) {
            $href = $node->attr('href');
            if ($href) {
                $newHref = $this->rewriteLink($href, $baseUrl);
                if ($newHref !== $href) {
                    $node->getNode(0)->setAttribute('href', $newHref);
                }
            }
        });
    }

    private function rewriteLink(string $href, string $baseUrl): string
    {
        // Skip anchors
        if (str_starts_with($href, '#')) {
            return $href;
        }

        // Resolve to absolute to check domain
        $absoluteUrl = $this->resolveUrl($href, $baseUrl);
        if (!$absoluteUrl) {
            return $href;
        }

        $parsedBase = parse_url($baseUrl);
        $baseHost = $parsedBase['host'] ?? '';
        $parsedUrl = parse_url($absoluteUrl);
        $host = $parsedUrl['host'] ?? '';

        // If external, return original
        if ($host !== $baseHost) {
            return $href;
        }

        // It's internal. Get the path.
        $path = $parsedUrl['path'] ?? '/';

        // Strip extension (.html, .php, etc)
        $path = preg_replace('/\.(html|php|jsp|asp|aspx)$/i', '', $path);

        // Return absolute path (root relative)
        return $path;
    }

    private function downloadAsset(string $src, string $baseUrl, string $type): ?string
    {
        $absoluteUrl = $this->resolveUrl($src, $baseUrl);
        if (!$absoluteUrl) {
            return null;
        }

        if (isset($this->downloadedAssets[$absoluteUrl])) {
            return $this->downloadedAssets[$absoluteUrl];
        }

        $content = $this->fetchAsset($absoluteUrl);
        if ($content === null) {
            return null;
        }

        $extension = pathinfo(parse_url($absoluteUrl, PHP_URL_PATH), PATHINFO_EXTENSION);
        if (!$extension) {
            $extension = 'jpg'; // Default fallback
        }

        $filename = md5($absoluteUrl) . '.' . $extension;
        $relativePath = "/assets/{$type}/{$filename}";
        $fullPath = $this->sourceDir . $relativePath;

        if (!is_dir(dirname($fullPath))) {
            mkdir(dirname($fullPath), 0755, true);
        }

        file_put_contents($fullPath, $content);

        $this->logger->log('INFO', "Downloaded asset: {$absoluteUrl} -> {$relativePath}");

        $this->downloadedAssets[$absoluteUrl] = $relativePath;

        return $relativePath;
    }

    private function resolveUrl(string $src, string $baseUrl): ?string
    {
        // Use DomCrawler Link to resolve if possible, but we don't have a Link object for img src easily
        // Manual resolution similar to CrawlerService

        if (str_starts_with($src, 'http')) {
            return $src;
        }

        if (str_starts_with($src, '//')) {
            return 'https:' . $src;
        }

        $parsedBase = parse_url($baseUrl);
        $scheme = $parsedBase['scheme'] ?? 'https';
        $host = $parsedBase['host'] ?? '';

        if (str_starts_with($src, '/')) {
            return $scheme . '://' . $host . $src;
        }

        // Relative to current path
        $path = $parsedBase['path'] ?? '/';
        $basePath = substr($path, 0, strrpos($path, '/') + 1);

        return $scheme . '://' . $host . $basePath . $src;
    }

    private function fetchAsset(string $url): ?string
    {
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
        curl_setopt($ch, CURLOPT_USERAGENT, 'StaticForge/1.0');

        $content = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);

        if (curl_errno($ch) || $httpCode >= 400) {
            curl_close($ch);
            return null;
        }

        curl_close($ch);
        return $content;
    }
}
