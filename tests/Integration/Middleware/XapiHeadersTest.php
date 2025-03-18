<?php

namespace Tests\Integration\Cors;

use API\Config;
use Tests\ApiTestCase;

class XapiHeadersTest extends ApiTestCase
{
    public function testXapiHeaders(): void
    {
        $response = $this->runApp('GET', '/about');
        $version = Config::get(['xAPI', 'latest_version'], '<invalid>'); // call after runApp

        $this->assertEquals($response->getHeader('X-Experience-API-Version'), [$version]);
        $this->assertTrue($response->hasHeader('X-Experience-API-Consistent-Through'));
    }
}
