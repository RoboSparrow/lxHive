<?php

namespace Tests\Integration\Parser;

use Tests\ApiTestCase;

use Slim\Http\Environment;
use Slim\Http\Request;
use Slim\Http\Uri;
use Slim\Http\Headers;
use Slim\Http\RequestBody;
use Slim\Http\UploadedFile;
use Psr\Http\Message\RequestInterface;

use API\Parser\RequestParser;

class RequestParserTest extends ApiTestCase
{
    const MOCK_STATEMENT = '{"actor":{"objectType":"Agent","name":"Buster Keaton","mbox":"mailto:buster@keaton.com"},"verb":{"id":"http://adlnet.gov/expapi/verbs/voided","display":{"en-US":"voided"}},"object":{"objectType":"StatementRef","id":"{{statementId}}"}}';

    public function testSingleJsonRequest()
    {
        $response = $this->runApp('POST', '/statements', ['Content-Type' => 'application/json'], self::MOCK_STATEMENT);

        $parser = new RequestParser($this->lastRequest());

        $parserResult = $parser->getData();
        $this->assertInstanceOf('\API\Parser\ParserResult', $parserResult);

        $payload = $parserResult->getPayload();
        $this->assertInstanceOf('stdClass', $payload);
    }

}
