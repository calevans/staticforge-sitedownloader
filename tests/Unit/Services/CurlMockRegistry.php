<?php

declare(strict_types=1);

namespace Calevans\StaticForgeSiteDownloader\Tests\Unit\Services;

class CurlMockRegistry
{
    public static $response = '';
    public static $httpCode = 200;
    public static $headerSize = 0;
    public static $errno = 0;
    
    public static function reset()
    {
        self::$response = '';
        self::$httpCode = 200;
        self::$headerSize = 0;
        self::$errno = 0;
    }
}
