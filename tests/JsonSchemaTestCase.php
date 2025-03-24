<?php

/*
 * This file is part of lxHive LRS - http://lxhive.org/
 *
 * Copyright (C) 2017 G3 International
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
namespace Tests;

use JsonSchema\Validator;
use JsonSchema\SchemaStorage;
use JsonSchema\Constraints\Factory;

use Tests\TestCase;

abstract class JsonSchemaTestCase extends TestCase
{

    private $schemaFile;
    private $schemaStorage;
    private $debugData = null;

    public function initSchema($fp = null): void
    {
        $appRoot = parent::getAppRoot();
        $schemaFile = $appRoot . '/src/xAPI/Validator/V10/Schema/Statements.json';

        $this->assertFileExists($schemaFile);
        $this->assertIsObject(json_decode(file_get_contents($schemaFile)));

        $this->schemaFile = $schemaFile;
        $this->schemaStorage = new SchemaStorage();
        $this->debugData = null;
    }

    public function validateSchema($data, $fragment = '')
    {
        $this->debugData = null;

        $uri = 'file://'. $this->schemaFile . $fragment;

        $schema = $this->schemaStorage->getSchema($uri);
        $validator = new Validator(new Factory($this->schemaStorage));
        $res = $validator->check($data, $schema);

        $this->debugData = [
            'hasErrors' => count($validator->getErrors()),
            'errors' => ($data) ? $validator->getErrors() : [],
            'uri' => $uri,
            'schema' => $schema,
            'data' => $data,
        ];

        return $validator;
    }

}
