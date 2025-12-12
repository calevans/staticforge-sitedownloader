<?php

declare(strict_types=1);

namespace Calevans\StaticForgeSiteDownloader\Tests\Unit\Services;

require_once __DIR__ . '/CurlMock.php';

use Calevans\StaticForgeSiteDownloader\Services\AssetProcessor;
use Calevans\StaticForgeSiteDownloader\Services\ContentProcessor;
use Calevans\StaticForgeSiteDownloader\Services\CrawlerService;
use EICC\Utils\Log;
use org\bovigo\vfs\vfsStream;
use PHPUnit\Framework\TestCase;

class CrawlerServiceTest extends TestCase
{
    private $root;
    private $logger;
    private $assetProcessor;
    private $contentProcessor;
    private $crawlerService;

    protected function setUp(): void
    {
        $this->root = vfsStream::setup('root');
        $this->logger = $this->createMock(Log::class);
        $this->assetProcessor = $this->createMock(AssetProcessor::class);
        $this->contentProcessor = $this->createMock(ContentProcessor::class);
        
        $this->crawlerService = new CrawlerService(
            $this->logger,
            $this->assetProcessor,
            $this->contentProcessor,
            vfsStream::url('root')
        );
        
        CurlMockRegistry::reset();
    }

    public function testCrawlProcessesUrlAndSavesContent(): void
    {
        $html = '<html><body><a href="/page2">Link</a></body></html>';
        $headers = "HTTP/1.1 200 OK\r\nContent-Type: text/html\r\n\r\n";
        
        CurlMockRegistry::$response = $headers . $html;
        CurlMockRegistry::$headerSize = strlen($headers);
        CurlMockRegistry::$httpCode = 200;

        $this->contentProcessor->method('process')->willReturn([
            'metadata' => ['title' => 'Test', 'category' => 'General'],
            'content' => 'Markdown Content',
            'category_data' => [['name' => 'General', 'url' => '/cat/general']]
        ]);

        $this->crawlerService->crawl('https://example.com');

        // Check if file was saved
        // Slug for https://example.com is index.md (at root, not under category)
        $this->assertTrue($this->root->hasChild('index.md'));
        
        // Check category page generation
        $this->assertTrue($this->root->hasChild('general.md'));
    }

    public function testCrawlSkipsInvalidContentType(): void
    {
        $headers = "HTTP/1.1 200 OK\r\nContent-Type: image/jpeg\r\n\r\n";
        CurlMockRegistry::$response = $headers . 'image data';
        CurlMockRegistry::$headerSize = strlen($headers);
        
        $this->crawlerService->crawl('https://example.com/image.jpg');
        
        $this->assertFalse($this->root->hasChildren());
    }
}
