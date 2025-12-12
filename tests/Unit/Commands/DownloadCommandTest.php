<?php

declare(strict_types=1);

namespace Calevans\StaticForgeSiteDownloader\Tests\Unit\Commands;

use Calevans\StaticForgeSiteDownloader\Commands\DownloadCommand;
use Calevans\StaticForgeSiteDownloader\Services\CrawlerService;
use EICC\Utils\Container;
use org\bovigo\vfs\vfsStream;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Application;
use Symfony\Component\Console\Tester\CommandTester;

class DownloadCommandTest extends TestCase
{
    private $container;
    private $crawlerService;
    private $command;
    private $root;

    protected function setUp(): void
    {
        $this->root = vfsStream::setup('root');
        $this->container = $this->createMock(Container::class);
        $this->crawlerService = $this->createMock(CrawlerService::class);
        
        $this->command = new DownloadCommand($this->container, $this->crawlerService);
    }

    public function testExecuteRunsCrawl(): void
    {
        $application = new Application();
        $application->add($this->command);
        
        $command = $application->find('site:download');
        $commandTester = new CommandTester($command);
        
        $_ENV['SOURCE_DIR'] = vfsStream::url('root/content');
        mkdir($_ENV['SOURCE_DIR']);

        $this->crawlerService->expects($this->once())
            ->method('crawl')
            ->with('https://example.com');

        $commandTester->execute([
            'url' => 'example.com'
        ]);

        $output = $commandTester->getDisplay();
        $this->assertStringContainsString('Target URL: https://example.com', $output);
        $this->assertStringContainsString('Starting crawl...', $output);
    }

    public function testExecuteCleansDirectory(): void
    {
        $application = new Application();
        $application->add($this->command);
        
        $command = $application->find('site:download');
        $commandTester = new CommandTester($command);
        
        $sourceDir = vfsStream::url('root/content');
        $_ENV['SOURCE_DIR'] = $sourceDir;
        mkdir($sourceDir);
        file_put_contents($sourceDir . '/old.md', 'content');

        $this->crawlerService->expects($this->once())->method('crawl');

        $commandTester->execute([
            'url' => 'https://example.com'
        ]);

        $this->assertFileDoesNotExist($sourceDir . '/old.md');
    }

    public function testExecuteSkipsCleanWhenOptionSet(): void
    {
        $application = new Application();
        $application->add($this->command);
        
        $command = $application->find('site:download');
        $commandTester = new CommandTester($command);
        
        $sourceDir = vfsStream::url('root/content');
        $_ENV['SOURCE_DIR'] = $sourceDir;
        mkdir($sourceDir);
        file_put_contents($sourceDir . '/keep.md', 'content');

        $this->crawlerService->expects($this->once())->method('crawl');

        $commandTester->execute([
            'url' => 'https://example.com',
            '--no-clean' => true
        ]);

        $this->assertFileExists($sourceDir . '/keep.md');
    }
}
