<?php

declare(strict_types=1);

namespace Calevans\StaticForgeSiteDownloader;

use EICC\StaticForge\Core\BaseFeature;
use EICC\StaticForge\Core\ConfigurableFeatureInterface;
use EICC\StaticForge\Core\Events\ConsoleInitEvent;
use EICC\StaticForge\Core\Events\EventListener;
use Calevans\StaticForgeSiteDownloader\Commands\DownloadCommand;
use Calevans\StaticForgeSiteDownloader\Services\AssetProcessor;
use Calevans\StaticForgeSiteDownloader\Services\ContentProcessor;
use Calevans\StaticForgeSiteDownloader\Services\CrawlerService;

class Feature extends BaseFeature implements ConfigurableFeatureInterface
{
    protected string $name = 'SiteDownloader';

    public function getRequiredConfig(): array
    {
        return [];
    }

    public function getRequiredEnv(): array
    {
        return [
            'SOURCE_DIR',
        ];
    }

    #[EventListener('CONSOLE_INIT')]
    public function registerCommands(ConsoleInitEvent $event): void
    {
        $sourceDir = $_ENV['SOURCE_DIR'] ?? 'content';
        $logger = $this->container->get('logger');

        $assetProcessor = new AssetProcessor($sourceDir, $logger);
        $contentProcessor = new ContentProcessor();
        $crawlerService = new CrawlerService($logger, $assetProcessor, $contentProcessor, $sourceDir);

        $event->application->addCommand(new DownloadCommand($this->container, $crawlerService));
    }
}
