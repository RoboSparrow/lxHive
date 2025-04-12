<?php
namespace Tests\Validation\JsonSchema;

use Tests\JsonSchemaTestCase;

class ValidateStatementRefTest extends JsonSchemaTestCase
{

    public function testStatement(): void
    {
        // @see https://github.com/adlnet/xAPI-Spec/blob/master/xAPI-Data.md#246-context
        //  - all properties in context are optional, so we need only "language"
        $tests = [
            [
                'pass' => true,
                'data' => '{"actor":{"objectType":"Agent","name":"xAPI account","mbox":"mailto:xapi@adlnet.gov"},"verb":{"id":"http://adlnet.gov/expapi/verbs/attended","display":{"en-GB":"attended","en-US":"attended"}},"object":{"objectType":"StatementRef","id":"8f87ccde-bb56-4c2e-ab83-44982ef22df0"}}'
            ],
        ];

        foreach ($tests as $test) {
            $expect = $test['pass'];
            $data = json_decode($test['data']);

            $validator = $this->validateSchema($data, '#/definitions/statement');
            $this->assertEquals($validator->isValid(), $expect, $test['data'] . ': ' . json_encode($validator->getErrors()));
        }
    }

    public function testStatementRef(): void
    {
        // @see https://github.com/adlnet/xAPI-Spec/blob/master/xAPI-Data.md#246-context
        //  - all proprties in context are optional, so we need only "language"
        $tests = [
            [
                'pass' => true,
                'data' => '{
                    "objectType": "StatementRef",
                    "id": "8f87ccde-bb56-4c2e-ab83-44982ef22df0"
                }'
            ],
        ];

        foreach ($tests as $test) {
            $expect = $test['pass'];
            $data = json_decode($test['data']);

            $validator = $this->validateSchema($data, '#/definitions/statementref');
            $this->assertEquals($validator->isValid(), $expect, $test['data'] . ': ' . json_encode($validator->getErrors()));
        }
    }

}
