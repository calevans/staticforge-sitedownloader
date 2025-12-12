<?php

declare(strict_types=1);

namespace Calevans\StaticForgeSiteDownloader\Commands;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use EICC\Utils\Container;
use Calevans\StaticForgeSiteDownloader\Services\CrawlerService;

class DownloadCommand extends Command
{
    protected static $defaultName = 'site:download';
    protected static $defaultDescription = 'Download a static site and convert to Markdown.';
    private Container $container;
    private CrawlerService $crawlerService;

    public function __construct(Container $container, CrawlerService $crawlerService)
    {
        parent::__construct();
        $this->container = $container;
        $this->crawlerService = $crawlerService;
    }

    protected function configure(): void
    {
        $this
            ->addArgument('url', InputArgument::REQUIRED, 'The target URL to spider.')
            ->addOption(
                'no-clean',
                null,
                InputOption::VALUE_NONE,
                'If set, do not empty the SOURCE_DIR before starting.'
            );
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $url = $input->getArgument('url');
        $noClean = $input->getOption('no-clean');

        // Default protocol if missing
        if (!preg_match('/^https?:\/\//', $url)) {
            $url = 'https://' . $url;
        }

        $output->writeln("Target URL: " . $url);
        $sourceDir = $_ENV['SOURCE_DIR'] ?? 'content';
        $output->writeln("Source Dir: " . $sourceDir);

        if (!$noClean) {
            $output->writeln("Cleaning source directory...");
            $this->cleanDirectory($sourceDir);
        }

        $output->writeln("Starting crawl...");
        $this->crawlerService->crawl($url);

        $output->writeln("Done.");

        return Command::SUCCESS;
    }

    private function cleanDirectory(string $dir): void
    {
        if (!is_dir($dir)) {
            return;
        }

        $files = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($dir, \RecursiveDirectoryIterator::SKIP_DOTS),
            \RecursiveIteratorIterator::CHILD_FIRST
        );

        foreach ($files as $fileinfo) {
            $todo = ($fileinfo->isDir() ? 'rmdir' : 'unlink');
            $todo($fileinfo->getPathname());
        }
    }
}
