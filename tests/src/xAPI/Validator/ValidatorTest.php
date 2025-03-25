<?php
namespace Tests\API\Validator;

use Tests\TestCase;

use JsonSchema;
use JsonSchema\Constraints\Factory as JsonSchemaFactory;

use API\Container;
use API\Validator;
use API\Validator\JsonSchema\Constraints\Factory as CustomFactory;

class ValidatorTest extends TestCase
{
    public function testCreateFactory()
    {
        $factory = new CustomFactory();
        $v =  new Validator(new Container());
        $sv = $v->createSchemaValidator($factory);

        $this->assertInstanceOf(JsonSchema\Validator::class, $sv, 'extends JsonSchema\Validator');
        $this->assertTrue(method_exists($sv, 'isValid'), 'extends JsonSchema\Validator');
    }
}
