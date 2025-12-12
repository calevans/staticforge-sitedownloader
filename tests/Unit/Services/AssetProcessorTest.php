<?php

declare(strict_types=1);

namespace Calevans\StaticForgeSiteDownloader\Tests\Unit\Services;

require_once __DIR__ . '/CurlMock.php';

use Calevans\StaticForgeSiteDownloader\Services\AssetProcessor;
use EICC\Utils\Log;
use org\bovigo\vfs\vfsStream;
use PHPUnit\Framework\TestCase;
use Symfony\Component\DomCrawler\Crawler;

class AssetProcessorTest extends TestCase
{
    private $root;
    private $logger;
    private $processor;

    protected function setUp(): void
    {
        $this->root = vfsStream::setup('root');
        $this->logger = $this->createMock(Log::class);
        $this->processor = new AssetProcessor(vfsStream::url('root'), $this->logger);
        
        CurlMockRegistry::reset();
    }

    public function testProcessDownloadsImages(): void
    {
        CurlMockRegistry::$response = 'image-content';
        CurlMockRegistry::$httpCode = 200;

        $html = '<img src="/img/test.jpg">';
        $crawler = new Crawler($html, 'https://example.com');
        
        $this->processor->process($crawler, 'https://example.com');
        
        $this->assertTrue($this->root->hasChild('assets/images'));
        
        // Filename is md5 of absolute url
        // https://example.com/img/test.jpg
        $expectedFilename = md5('https://example.com/img/test.jpg') . '.jpg';
        $this->assertTrue($this->root->getChild('assets/images')->hasChild($expectedFilename));
        
        // Check if src was updated
        $img = $crawler->filter('img')->first();
        $this->assertStringContainsString('/assets/images/' . $expectedFilename, $img->attr('src'));
    }

    public function testProcessRewritesInternalLinks(): void
    {
        $html = '<a href="/about.html">About</a><a href="https://google.com">Google</a>';
        $crawler = new Crawler($html, 'https://example.com');
        
        $this->processor->process($crawler, 'https://example.com');
        
        $links = $crawler->filter('a');
        $this->assertEquals('/about', $links->eq(0)->attr('href')); // .html stripped
        $this->assertEquals('https://google.com', $links->eq(1)->attr('href')); // External untouched
    }
}
