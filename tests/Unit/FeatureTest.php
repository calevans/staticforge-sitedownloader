<?php

declare(strict_types=1);

namespace Calevans\StaticForgeSiteDownloader\Tests\Unit;

use Calevans\StaticForgeSiteDownloader\Feature;
use Calevans\StaticForgeSiteDownloader\Tests\TestCase;
use EICC\StaticForge\Core\Events\ConsoleInitEvent;
use EICC\StaticForge\Core\EventManager;
use EICC\StaticForge\Core\FeatureFactory;
use Symfony\Component\Console\Application;

class FeatureTest extends TestCase
{
    private Feature $feature;
    private EventManager $eventManager;

    protected function setUp(): void
    {
        parent::setUp();

        $this->eventManager = new EventManager();

        $feature = (new FeatureFactory($this->container))->make(Feature::class);
        $this->assertInstanceOf(Feature::class, $feature);
        $feature->setContainer($this->container);
        $this->feature = $feature;
        $this->feature->register($this->eventManager);
    }

    public function testRegisterRegistersConsoleInitListener(): void
    {
        $listeners = $this->eventManager->getListeners('CONSOLE_INIT');
        $this->assertNotEmpty($listeners);
        $this->assertEquals([$this->feature, 'registerCommands'], $listeners[0]['callback']);
    }

    public function testRegisterCommandsAddsDownloadCommand(): void
    {
        $app = new Application();
        $event = new ConsoleInitEvent('CONSOLE_INIT', $app);

        $this->feature->registerCommands($event);

        $this->assertTrue($app->has('site:download'));
    }
}
