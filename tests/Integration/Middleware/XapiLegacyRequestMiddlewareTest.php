<?php

namespace Tests\Integration\Cors;

use API\Config;
use Tests\ApiTestCase;

use Slim\Http\StatusCode;

// URL: http://example.com/xAPI/statements
// Method: PUT
//
// Query String Parameters:
//     statementId=c70c2b85-c294-464f-baca-cebd4fb9b348
//
// Request Headers:
//     Accept:*/*
//     Accept-Encoding:gzip, deflate, sdch
//     Accept-Language:en-US,en;q=0.8
//     Authorization: Basic VGVzdFVzZXI6cGFzc3dvcmQ=
//     Content-Type: application/json
//     X-Experience-API-Version: 1.0.3
//     Content-Length: 351
//
// Content:
// {"id":"c70c2b85-c2print94-464f-baca-cebd4fb9b348","timestamp":"2014-12-29T12:09:37.468Z","actor":{"objectType":"Agent","mbox":"mailto:example@example.com","name":"Test User"},"verb":{"id":"http://adlnet.gov/expapi/verbs/experienced","display":{"en-US":"experienced"}},"object":{"id":"http://example.com/xAPI/activities/myactivity","objectType":"Activity"}}

class XapiLegacyRequestMiddlewareTest extends ApiTestCase
{
    private $email = 'test@test.test';
    private $key = '3|INLaCrMwyWzMAj9UWCmxleMEVeW5piGSGC74btA';
    private $secret = 'u|c54gUXxEtlcDSvtbJlIn9Hd0MfFD4Ka4qcf740U';

    public function setUp(): void
    {
        $this->createBasicToken('test', $this->email, $this->key, $this->secret, ['super']);
    }

    public function testLegacyAbout(): void
    {
        $response = $this->runApp('POST', '/about?method=GET', ['Content-Type' => 'application/x-www-form-urlencoded']);
        $status = $response->getStatusCode();
        $data = json_decode($response->getBody(), false);

        $this->assertEquals($status, StatusCode::HTTP_OK, 'status code');
        $this->assertTrue(!empty($data->version),  'data');
    }

    public function testLegacyStatement(): void
    {
        // --> PUT

        $statementId = $this->createUuid();
        $now = date('c');
        $moreUrl = null;

        $statement = $this->createStatement($this->email, 'tested/PUT', 'legacyStatement/PUT');
        $data = [
            // headers
            'Content-Type'             => 'application/json',
            'X-Experience-API-Version' => $this->xapiVersion(),
            'Authorization'            => 'Basic '.base64_encode($this->key.':'.$this->secret),
            // query params
            'statementId'              => $statementId,
            // data
            'content'                  => json_encode($statement),
        ];
        $formData = http_build_query($data);

        $response = $this->runApp('POST', '/statements?method=PUT', ['Content-Type' => 'application/x-www-form-urlencoded'], $formData);
        $status = $response->getStatusCode();

        $this->assertEquals($status, StatusCode::HTTP_NO_CONTENT, 'status code');
        $this->assertEquals((string) $response->getBody(), '', 'no body');

        // --> GET

        $data = [
            // headers
            'Content-Type'             => 'application/json',
            'X-Experience-API-Version' => $this->xapiVersion(),
            'Authorization'            => 'Basic '.base64_encode($this->key.':'.$this->secret),
            // query params
            'statementId'              => $statementId,
        ];
        $formData = http_build_query($data);

        $response = $this->runApp('POST', '/statements?method=GET', ['Content-Type' => 'application/x-www-form-urlencoded'], $formData);
        $status = $response->getStatusCode();
        $data = json_decode((string) $response->getBody(), false);

        $this->assertEquals($status, StatusCode::HTTP_OK, 'status code');
        $this->assertEquals($data->id, $statementId, 'statementId');

        // --> HEAD

        $data = [
            // headers
            'Content-Type'             => 'application/json',
            'X-Experience-API-Version' => $this->xapiVersion(),
            'Authorization'            => 'Basic '.base64_encode($this->key.':'.$this->secret),
            // query params
            'statementId'              => $statementId,
        ];
        $formData = http_build_query($data);

        $response = $this->runApp('POST', '/statements?method=HEAD', ['Content-Type' => 'application/x-www-form-urlencoded'], $formData);
        $status = $response->getStatusCode();
        $this->assertEquals($status, StatusCode::HTTP_OK, 'status code');

        // --> POST Yes, it's silly. However, a client sending batches might exactly do this

        $statement = $this->createStatement($this->email, 'tested/POST', 'legacyStatement/POST');
        $data = [
            // headers
            'Content-Type'             => 'application/json',
            'X-Experience-API-Version' => $this->xapiVersion(),
            'Authorization'            => 'Basic '.base64_encode($this->key.':'.$this->secret),
            // data
            'content'                  => json_encode($statement),
        ];
        $formData = http_build_query($data);

        $response = $this->runApp('POST', '/statements?method=POST', ['Content-Type' => 'application/x-www-form-urlencoded'], $formData);
        $status = $response->getStatusCode();

        $data = json_decode((string) $response->getBody(), false);

        $this->assertEquals($status, StatusCode::HTTP_OK, 'status code');
        $this->assertTrue(is_array($data));
        $this->assertEquals(count($data), 1);

        // --> GET ?until&limit => trigger: more URL

        $data = [
            // headers
            'Content-Type'             => 'application/json',
            'X-Experience-API-Version' => $this->xapiVersion(),
            'Authorization'            => 'Basic '.base64_encode($this->key.':'.$this->secret),
            // query params
            'until'                    => $now,
            'limit'                    => 1,
        ];
        $formData = http_build_query($data);

        $response = $this->runApp('POST', '/statements?method=GET', ['Content-Type' => 'application/x-www-form-urlencoded'], $formData);
        $status = $response->getStatusCode();
        $data = json_decode((string) $response->getBody(), false);

        $this->assertEquals($status, StatusCode::HTTP_OK, 'status code');
        $this->assertIsObject($data);
        $this->assertObjectHasProperty('statements', $data);
        $this->assertObjectHasProperty('more', $data);

        $moreUrl = $data->more;

        // --> GET ?method&secondParam reject additional query  param ( 'no other param' rule)

        $url = '/statements?method=GET&secondParam=NotAllowed';

        // --> GET

        $data = [
            // headers
            'Content-Type'             => 'application/json',
            'X-Experience-API-Version' => $this->xapiVersion(),
            'Authorization'            => 'Basic '.base64_encode($this->key.':'.$this->secret),
        ];
        $formData = http_build_query($data);

        $response = $this->runApp('POST', $url, ['Content-Type' => 'application/x-www-form-urlencoded'], $formData);
        $status = $response->getStatusCode();
        $this->assertEquals($status, StatusCode::HTTP_BAD_REQUEST, 'status code');

    }

}
