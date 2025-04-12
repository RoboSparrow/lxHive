<?php
namespace Tests\Validation\JsonSchema;

use Tests\JsonSchemaTestCase;

class ValidateLanguageTest extends JsonSchemaTestCase
{

    public function testContextLanguage(): void
    {
        // @see https://github.com/adlnet/xAPI-Spec/blob/master/xAPI-Data.md#246-context
        //  - all proprties in context are optional, so we need only "language"
        $tests = [
            ['pass' => true,  'data' => '{"language": "cv"}'],
            ['pass' => true,  'data' => '{"language": "ern"}'],
            ['pass' => true,  'data' => '{"language": "er-ER"}'],
            ['pass' => false, 'data' => '{"something": "something"}'],
            ['pass' => false, 'data' => '{"language"}'],
            ['pass' => false, 'data' => '{"language": ""}'],
            ['pass' => false, 'data' => '{"language": "something"}'],
        ];

        foreach ($tests as $test) {
            $expect = $test['pass'];
            $data = json_decode($test['data']);

            $validator = $this->validateSchema($data, '#/definitions/context');
            $this->assertEquals($validator->isValid(), $expect, $test['data'] . ': ' . json_encode($validator->getErrors()));
        }
    }

    public function testLanguageMap(): void
    {
        $tests = [
            ['pass' => true,  'data' => '{"de": "besucht"}'],
            ['pass' => false, 'data' => '{"something": "besucht"}'],
        ];

        foreach ($tests as $test) {
            $expect = $test['pass'];
            $data = json_decode($test['data']);

            $validator = $this->validateSchema($data, '#/definitions/languagemap');
            $this->assertEquals($validator->isValid(), $expect, $test['data'] . ': ' . json_encode($validator->getErrors()));
        }
    }

}
