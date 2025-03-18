<?php

namespace Tests\Integration\Cors;

use Tests\ApiTestCase;

class CorsHeadersTest extends ApiTestCase
{

    protected function setUp(): void
    {
        $this->corsHeaders = [
            'Access-Control-Allow-Origin',
            'Access-Control-Allow-Methods',
            'Access-Control-Allow-Headers',
            'Access-Control-Allow-Credential',
            'Access-Control-Expose-Headers',
        ];
    }

    public function testCorsHeaders(): void
    {
        $response = $this->runApp('GET', '/about');

        foreach($this->corsHeaders as $header) {
            $this->assertTrue($response->hasHeader($header), 'has header \''.$header.'\'');
        }
    }
}
