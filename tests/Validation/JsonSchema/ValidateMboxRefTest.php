<?php
namespace Tests\Validation\JsonSchema;

use Tests\JsonSchemaTestCase;

class ValidateMboxRefTest extends JsonSchemaTestCase
{

    public function testMboxRef(): void
    {
        // @see https://github.com/adlnet/xAPI-Spec/blob/master/xAPI-Data.md#246-context
        //  - all proprties in context are optional, so we need only "language"
        $tests = [
            [
                'pass' => false,
                'data' => '{}'
            ], [
                'pass' => false,
                'data' => '{"mbox":""}'
            ], [
                'pass' => false,
                'data' => '{"mbox":"something"}'
            ], [
                'pass' => false,
                'data' => '"mailto:xapi@adlnet.gov'
            ], [
                'pass' => true,
                'data' => '{"mbox":"mailto:xapi@adlnet.gov"}'
            ], [
                'pass' => true,
                'data' => '{"mbox":"mailto: xapi@adlnet.gov"}'
            ], [
                'pass' => false,
                'data' => '{"mbox":"mailto:xapi.adlnet.gov"}'
            ], [
                'pass' => false,
                'data' => '{"mbox": "mailto.xapi@adlnet.gov"}'
            ],[
                'pass' => false,
                'data' => '{"something":"mailto@xapi@adlnet.gov"}'
            ], [
                'pass' => false,
                'data' => '{
                    "mbox":"mailto@xapi@adlnet.gov",
                    "isNotAllowed": "something"
                }'
            ],
        ];

        foreach ($tests as $test) {
            $expect = $test['pass'];
            $data = json_decode($test['data']);

            $validator = $this->validateSchema($data, '#/definitions/mbox');
            $this->assertEquals($validator->isValid(), $expect, $test['data'] . ': ' . json_encode($validator->getErrors()));
        }
    }

}
