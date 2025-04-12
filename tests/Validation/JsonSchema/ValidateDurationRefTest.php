<?php
namespace Tests\Validation\JsonSchema;

use Tests\JsonSchemaTestCase;

class ValidateDurationRefTest extends JsonSchemaTestCase
{

    public function testDurationRef(): void
    {
        // @see https://github.com/adlnet/xAPI-Spec/blob/master/xAPI-Data.md#246-context
        //  - all proprties in context are optional, so we need only "language"
        $tests = [
            [
                'pass' => false,
                'data' => '',
            ], [
                'pass' => false,
                'data' => 'invalid',
            ], [
                'pass' => false,
                'data' => 'P',
            ], [
                'pass' => true,
                'data' => 'PT1H0M0.1S',
            ], [
                'pass' => true,
                'data' => 'PT1H0M0S', // lrs-conformance-test-suite,
            ], [
                'pass' => true,
                'data' => 'PT16559.14S',
            ], [
                'pass' => true,
                'data' => 'PT4H35M59.14S',
            ], [
                // flagged as valid in lrs-conformance-test-suite,
                // PHP \DateInterval throws "DateMalformedIntervalStringException: Unknown or bad format (P3Y1M29DT4H35M59.14S)"
                'pass' => true,
                'data' => 'P3Y1M29DT4H35M59.14S'
            ],
        ];

        foreach ($tests as $test) {
            $expect = $test['pass'];
            $data = $test['data'];

            $validator = $this->validateSchema($data, '#/definitions/duration');
            $this->assertEquals($validator->isValid(), $expect, $test['data'] . ': ' . json_encode($validator->getErrors()));
        }
    }

}
