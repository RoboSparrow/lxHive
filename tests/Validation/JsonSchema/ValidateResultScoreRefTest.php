<?php
namespace Tests\Validation\JsonSchema;

use Tests\JsonSchemaTestCase;

class ValidateResultScoreRefTest extends JsonSchemaTestCase
{

    public function testScore(): void
    {
        // @see https://github.com/adlnet/xAPI-Spec/blob/master/xAPI-Data.md#2451-score
        $tests = [
            ['pass' => true,  'data' => '{"scaled": 0}'],

            ['pass' => true,  'data' => '{}'],
            ['pass' => true,  'data' => '{"scaled": 0}'],
            ['pass' => true,  'data' => '{"scaled": -1}'],
            ['pass' => true,  'data' => '{"scaled": 1}'],
            ['pass' => false, 'data' => '{"scaled": 1.0001}'],
            ['pass' => false, 'data' => '{"scaled": -1.0001}'],

            // invalid type
            ['pass' => false, 'data' => '{"scaled": "invalid"}'],
            ['pass' => false, 'data' => '{"raw": "invalid"}'],
            ['pass' => false, 'data' => '{"min": "invalid"}'],
            ['pass' => false, 'data' => '{"max": "invalid"}'],

            // one invalid type prevents further conditional validation
            ['pass' => false, 'data' => '{score": {"min": "something", "max": 1, "raw": 1.5}'],
            ['pass' => false, 'data' => '{score": {"min": 0, "max": "something", "raw": 1.5}'],
            ['pass' => false, 'data' => '{score": {"min": 0, "max": 1, "raw": "something"}'],

            // TODO

            // min/max
            // ['pass' => true, 'data' => '{"min": 0, "max": 1}'],
            // ['pass' => false, 'data' => '{"min": 1, "max": 1}'],
            // ['pass' => false, 'data' => '{"min": 1, "max": 0}'],

            // raw
            // ['pass' => true, 'data' => '{"min": 0, "max": 1, "raw": 0.5}'],
            // ['pass' => true, 'data' => '{"min": 0, "max": 1, "raw": 1.5}'],

        ];

        foreach ($tests as $test) {
            $expect = $test['pass'];
            $data = json_decode($test['data']);

            $validator = $this->validateSchema($data, '#/definitions/score');
            // print(json_encode($data)."\n");
            $this->assertEquals($validator->isValid(), $expect, $test['data'] . ' failed with: ' . json_encode($validator->getErrors()));
        }
    }

}
