<?php

declare(strict_types=1);

namespace Calevans\StaticForgeSiteDownloader\Services;

use League\HTMLToMarkdown\HtmlConverter;
use Symfony\Component\DomCrawler\Crawler;

class ContentProcessor
{
    private HtmlConverter $converter;

    public function __construct()
    {
        $this->converter = new HtmlConverter();
        $this->converter->getConfig()->setOption('strip_tags', true);
    }

    public function process(Crawler $crawler, string $url): array
    {
        // Extract Metadata

        // Title: Prefer specific content headings over <title> tag
        $title = 'Untitled';

        // Try specific classes first, as they are more likely to be the post title
        $titleNode = $crawler->filter('.post-title, .entry-title');
        if ($titleNode->count() > 0) {
            $title = $titleNode->first()->text();
        } else {
            // Fallback to h1 if no specific class found
            $h1 = $crawler->filter('h1');
            if ($h1->count() > 0) {
                $title = $h1->first()->text();
            } elseif ($crawler->filter('title')->count() > 0) {
                // Fallback to <title> tag
                $title = $crawler->filter('title')->text();
                $parts = preg_split('/( » | - )/', $title);
                if (!empty($parts)) {
                    $title = trim($parts[0]);
                }
            }
        }

        $description = '';
        $crawler->filter('meta[name="description"]')->each(function (Crawler $node) use (&$description) {
            $description = $node->attr('content');
        });

        // Extract Categories and Tags
        $categoryNodes = $crawler->filter('a[rel="category tag"]');
        $categories = $categoryNodes->each(function (Crawler $node) {
            return $node->text();
        });
        $categories = array_values(array_unique($categories));

        $categoryData = $categoryNodes->each(function (Crawler $node) {
            return [
                'name' => $node->text(),
                'url' => $node->attr('href')
            ];
        });

        // StaticForge expects a single 'category' field for indexing
        $primaryCategory = !empty($categories) ? $categories[0] : 'Uncategorized';

        $tags = $crawler->filter('a[rel="tag"]')->each(function (Crawler $node) {
            return $node->text();
        });
        $tags = array_values(array_unique($tags));

        // Clean the DOM (remove scripts, styles, nav, etc.)
        $crawler->filter(
            'script, style, nav, header, footer, .sidebar, .widget, .menu, #comments, ' .
            '.comments-area, iframe, noscript, head, .post-title, .entry-title'
        )->each(function (Crawler $node) {
            foreach ($node as $n) {
                if ($n->parentNode) {
                    $n->parentNode->removeChild($n);
                }
            }
        });

        // Extract Body
        // Try specific content selectors first
        $selectors = [
            '.entry-content',
            '.post-content',
            'article',
            '#content',
            'main',
            'body'
        ];

        $bodyNode = null;
        foreach ($selectors as $selector) {
            if ($crawler->filter($selector)->count() > 0) {
                $bodyNode = $crawler->filter($selector);
                break;
            }
        }

        // Fallback to body if nothing else found
        if (!$bodyNode) {
            $bodyNode = $crawler->filter('body');
        }

        // If still empty (e.g. frameset?), fallback to empty string
        $bodyHtml = $bodyNode->count() ? $bodyNode->html() : '';

        // Convert to Markdown
        $markdown = $this->converter->convert($bodyHtml);

        // Clean up "Posted in ... | Comments Off"
        $markdown = preg_replace('/(Posted in \[.*?\]\(.*?\)) \*\*\|\*\* Comments Off on .*/', '$1', $markdown);

        // Fix category links
        $markdown = preg_replace('/\/category\/([a-zA-Z0-9-]+)\/?/', '/$1.html', $markdown);

        // Remove "Comments are closed."
        $markdown = str_replace('Comments are closed.', '', $markdown);

        return [
            'metadata' => [
                'title' => $title,
                'description' => $description,
                'original_url' => $url,
                'date' => date('Y-m-d H:i:s'),
                'category' => $primaryCategory,
                'tags' => $tags,
            ],
            'category_data' => $categoryData,
            'content' => $markdown
        ];
    }
}
