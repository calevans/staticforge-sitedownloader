<?php

declare(strict_types=1);

namespace Calevans\StaticForgeSiteDownloader\Services;

use Calevans\StaticForgeSiteDownloader\Tests\Unit\Services\CurlMockRegistry;

if (!function_exists('Calevans\StaticForgeSiteDownloader\Services\curl_exec')) {
    function curl_exec($ch) {
        return CurlMockRegistry::$response;
    }

    function curl_getinfo($ch, $opt = null) {
        if ($opt === CURLINFO_HTTP_CODE) {
            return CurlMockRegistry::$httpCode;
        }
        if ($opt === CURLINFO_HEADER_SIZE) {
            return CurlMockRegistry::$headerSize;
        }
        return null;
    }

    function curl_init() { return 'resource'; }
    function curl_setopt() {}
    function curl_close() {}
    function curl_errno() { return CurlMockRegistry::$errno; }
    function curl_error() { return 'Mock Error'; }
}
