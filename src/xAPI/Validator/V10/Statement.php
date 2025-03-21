<?php

/*
 * This file is part of lxHive LRS - http://lxhive.org/
 *
 * Copyright (C) 2016 G3 International
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with lxHive. If not, see <http://www.gnu.org/licenses/>.
 *
 * For authorship information, please view the AUTHORS
 * file that was distributed with this source code.
 */

namespace API\Validator\V10;

use API\Validator;

class Statement extends Validator
{
    protected function validateBySchemaFragment($data, $fragment, $debug = false)
    {
        $fragment = ($fragment) ? '#'.$fragment : '';

        return $this->validateSchema($data, 'file://'.__DIR__.'/Schema/Statements.json'.$fragment, $debug);
    }

    // Handles the validation of GET /statements
    public function validateGetRequest()
    {
        $data = $this->getContainer()->get('parser')->getData()->getQueryParams();

        foreach ($data as $key => $value) {
            $decodedValue = json_decode($value);
            if (json_last_error() == JSON_ERROR_NONE) {
                $data[$key] = $decodedValue;
            }
        }

        if (!empty($data)) {
            $data = (object) $data;
        }

        $validator = $this->validateBySchemaFragment($data, 'getParameters');
        if (!$validator->isValid()) {
            $this->throwSchemaErrors('GET parameters do not validate.', $validator);
        }
    }

    // POST-ing a statement validation
    public function validatePostRequest()
    {
        $data = $this->getContainer()->get('parser')->getData()->getPayload();

        $validator = $this->validateBySchemaFragment($data, 'postBody');
        if (!$validator->isValid()) {
            $this->throwSchemaErrors('Statements do not validate.', $validator);
        }
    }

    // PUT-ing one or more statements validation
    public function validatePutRequest()
    {
        // Then do specific validation
        $data = $this->getContainer()->get('parser')->getData()->getQueryParams();
        $validator = $this->validateBySchemaFragment($data, 'putParameters');
        if (!$validator->isValid()) {
            $this->throwSchemaErrors('PUT parameters do not validate.', $validator);
        }

        $data = $this->getContainer()->get('parser')->getData()->getPayload();

        $validator = $this->validateBySchemaFragment($data, 'putBody');
        if (!$validator->isValid()) {
            $this->throwSchemaErrors('Statements do not validate.', $validator);
        }
    }
}
