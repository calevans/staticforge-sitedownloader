<?php

declare(strict_types=1);

namespace Calevans\StaticForgeSiteDownloader\Tests\Unit\Services;

use Calevans\StaticForgeSiteDownloader\Services\ContentProcessor;
use PHPUnit\Framework\TestCase;
use Symfony\Component\DomCrawler\Crawler;

class ContentProcessorTest extends TestCase
{
    private ContentProcessor $processor;

    protected function setUp(): void
    {
        $this->processor = new ContentProcessor();
    }

    public function testProcessExtractsMetadataAndContent(): void
    {
        $html = <<<'HTML'
<!DOCTYPE html>
<html>
<head>
    <title>Test Page Title - My Site</title>
    <meta name="description" content="This is a test description.">
</head>
<body>
    <div class="post-title">Actual Post Title</div>
    <div class="entry-content">
        <p>This is the <strong>content</strong>.</p>
        <a href="/category/tech" rel="category tag">Tech</a>
        <a href="/tag/php" rel="tag">PHP</a>
    </div>
    <div class="sidebar">Sidebar content</div>
</body>
</html>
HTML;

        $crawler = new Crawler($html, 'https://example.com/post');
        $result = $this->processor->process($crawler, 'https://example.com/post');

        $this->assertEquals('Actual Post Title', $result['metadata']['title']);
        $this->assertEquals('This is a test description.', $result['metadata']['description']);
        $this->assertEquals('Tech', $result['metadata']['category']);
        $this->assertContains('PHP', $result['metadata']['tags']);
        $this->assertStringContainsString('This is the **content**.', $result['content']);
        $this->assertStringNotContainsString('Sidebar content', $result['content']);
    }

    public function testProcessHandlesMissingMetadata(): void
    {
        $html = '<html><body>Content</body></html>';
        $crawler = new Crawler($html, 'https://example.com/post');
        $result = $this->processor->process($crawler, 'https://example.com/post');

        $this->assertEquals('Untitled', $result['metadata']['title']);
        $this->assertEquals('Uncategorized', $result['metadata']['category']);
    }
}
