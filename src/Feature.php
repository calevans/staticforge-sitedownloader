<?php

declare(strict_types=1);

namespace Calevans\StaticForgeSiteDownloader;

use EICC\StaticForge\Core\BaseFeature;
use EICC\StaticForge\Core\EventManager;
use EICC\Utils\Container;
use Symfony\Component\Console\Application;
use Calevans\StaticForgeSiteDownloader\Commands\DownloadCommand;
use Calevans\StaticForgeSiteDownloader\Services\AssetProcessor;
use Calevans\StaticForgeSiteDownloader\Services\ContentProcessor;
use Calevans\StaticForgeSiteDownloader\Services\CrawlerService;

class Feature extends BaseFeature
{
    protected string $name = 'SiteDownloader';

    public function register(EventManager $eventManager, Container $container): void
    {
        parent::register($eventManager, $container);
        $eventManager->registerListener('CONSOLE_INIT', [$this, 'registerCommands']);
    }

    public function registerCommands(Container $container, array $data): array
    {
        /** @var Application $application */
        $application = $data['application'];

        $sourceDir = $_ENV['SOURCE_DIR'] ?? 'content';
        $logger = $container->get('logger');

        $assetProcessor = new AssetProcessor($sourceDir, $logger);
        $contentProcessor = new ContentProcessor();
        $crawlerService = new CrawlerService($logger, $assetProcessor, $contentProcessor, $sourceDir);

        $application->add(new DownloadCommand($container, $crawlerService));
        return $data;
    }
}
