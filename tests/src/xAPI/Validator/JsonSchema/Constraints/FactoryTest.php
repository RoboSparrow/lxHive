<?php
namespace Tests\API\Validator\JsonSchema\Constraints;

use Tests\TestCase;

use JsonSchema;
use JsonSchema\Constraints\Factory as JsonSchemaFactory;

use API\Validator\JsonSchema\Constraints\Factory as CustomFactory;

class FactoryTest extends TestCase
{
    public function testCreateFactory()
    {
        $f = new CustomFactory();

        $this->assertInstanceOf(JsonSchemaFactory::class, $f, 'extends JsonSchema\Constraints\Factory');
        $this->assertTrue(method_exists($f, 'setConstraintClass'), 'extends JsonSchema\Constraints\Factory');
        // print_r($f->getConstraintMap());
    }
}
