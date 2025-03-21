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

namespace API\Storage\Adapter\Mongo;

use API\Storage\SchemaInterface;
use API\Storage\Query\ActivityStateInterface;

use API\Util;
use API\Controller;
use API\Storage\Provider;
use API\Storage\Query\DocumentResult;

use API\Storage\AdapterException;

// TODO 0.11.x remove header dependency from this layer into parser and submit abstract args from there, like an array of options: put($data, $profileId, $agentIfi, array $options (contentType, if match))

class ActivityState extends Provider implements ActivityStateInterface, SchemaInterface
{
    const COLLECTION_NAME = 'activityStates';

    /**
     * @var array $indexes
     *
     * @see https://docs.mongodb.com/manual/reference/command/createIndexes/
     *  [
     *      name: <index_name>,
     *      key: [
     *          <key-value_pair>,
     *          <key-value_pair>,
     *          ...
     *      ],
     *      <option1-value_pair>,
     *      <option1-value_pair>,
     *      ...
     *  ],
     */
    private $indexes = [
        //stateId is not unique as per spec, only combination of stateId and activityId
    ];

    /**
     * {@inheritDoc}
     */
    public function install()
    {
        $container = $this->getContainer()->get('storage');
        $container->executeCommand(['create' => self::COLLECTION_NAME]);
        $container->createIndexes(self::COLLECTION_NAME, $this->indexes);
    }

    /**
     * {@inheritDoc}
     */
    public function getIndexes()
    {
        return $this->indexes;
    }

    /**
     * {@inheritDoc}
     */
    public function getFiltered($parameters)
    {
        $storage = $this->getContainer()->get('storage');
        $expression = $storage->createExpression();

        $agent = $parameters->get('agent');
        $agent = json_decode($agent, true);

        // TODO 0.11.x move to validator layer, add to jsonschema
        //      from https://github.com/adlnet/xAPI-Spec/blob/master/xAPI-Communication.md#agentprofres
        //      The "agent" parameter is an Agent Object and not a Group. Learning Record Providers wishing to store data against an Identified Group can use the Identified Group's identifier within an Agent Object.
        $uniqueIdentifier = Util\xAPI::extractUniqueIdentifier($agent);
        if (null === $uniqueIdentifier) {
            throw new AdapterException('Invalid `agent` parameter: missing ifi', Controller::STATUS_BAD_REQUEST);
        }

        // Single activity state
        if (isset($parameters['stateId'])) {
            $expression->where('stateId', $parameters->get('stateId'));
            $expression->where('activityId', $parameters->get('activityId'));

            $expression->where('agent.'.$uniqueIdentifier, $agent[$uniqueIdentifier]);

            if (isset($parameters['registration'])) {
                $expression->where('registration', $parameters->get('registration'));
            }

            $cursorCount = $storage->count(self::COLLECTION_NAME, $expression);

            $this->validateCursorCountValid($cursorCount);
            $cursor = $storage->find(self::COLLECTION_NAME, $expression);

            $documentResult = new DocumentResult();
            $documentResult->setCursor($cursor);
            $documentResult->setIsSingle(true);
            $documentResult->setRemainingCount(1);
            $documentResult->setTotalCount(1);

            return $documentResult;
        }

        $expression->where('activityId', $parameters->get('activityId'));
        $expression->where('agent.'.$uniqueIdentifier, $agent[$uniqueIdentifier]);

        if ($parameters->has('registration')) {
            $expression->where('registration', $parameters->get('registration'));
        }

        if ($parameters->has('since')) {
            $since = Util\Date::dateStringToMongoDate($parameters->get('since'));
            $expression->whereGreaterOrEqual('mongoTimestamp', $since);
        }

        // Fetch
        $cursor = $storage->find(self::COLLECTION_NAME, $expression);

        $documentResult = new DocumentResult();
        $documentResult->setCursor($cursor);
        $documentResult->setIsSingle(false);

        return $documentResult;
    }

    /**
     * {@inheritDoc}
     */
    public function post($parameters, $stateObject)
    {
        return $this->put($parameters, $stateObject);
    }

    /**
     * {@inheritDoc}
     *
     */
    public function put($parameters, $stateObject)
    {
        // TODO 0.11.x: optimise (upsert)
        // rawPayload input is a stream...read it
        $stateObject = (string)$stateObject;

        $storage = $this->getContainer()->get('storage');
        $expression = $storage->createExpression();

        // Set up the body to be saved
        $activityStateDocument = new \API\Document\Generic();

        // Check for existing state - then merge if applicable
        $expression = $storage->createExpression();

        // Check for existing state - then merge if applicable
        $expression->where('stateId', $parameters->get('stateId'));
        $expression->where('activityId', $parameters->get('activityId'));

        $agent = $parameters->get('agent');
        $agent = json_decode($agent, true);

        // TODO 0.11.x move to validator layer, add to jsonschema
        //      from https://github.com/adlnet/xAPI-Spec/blob/master/xAPI-Communication.md#agentprofres
        //      The "agent" parameter is an Agent Object and not a Group. Learning Record Providers wishing to store data against an Identified Group can use the Identified Group's identifier within an Agent Object.
        $uniqueIdentifier = Util\xAPI::extractUniqueIdentifier($agent);
        if (null === $uniqueIdentifier) {
            throw new AdapterException('Invalid `agent` parameter: missing ifi', Controller::STATUS_BAD_REQUEST);
        }

        $expression->where('agent.'.$uniqueIdentifier, $agent[$uniqueIdentifier]);

        if ($parameters->has('registration')) {
            $expression->where('registration', $parameters->get('registration'));
        }

        $result = $storage->findOne(self::COLLECTION_NAME, $expression);
        if ($result) {
            $result = new \API\Document\Generic($result);
        }

        // ID exists, merge body
        $contentType = $parameters['headers']['content-type'];
        if ($contentType === null || empty($contentType)) {
            $contentType = 'text/plain';
        } else {
            $contentType = $contentType[0];
        }

        // ID exists, try to merge body if applicable
        if ($result) {
            $this->validateDocumentType($result, $contentType);

            $decodedExisting = json_decode($result->getContent(), true);
            $this->validateJsonDecodeErrors();

            $decodedPosted = json_decode($stateObject, true);
            $this->validateJsonDecodeErrors();

            $stateObject = json_encode(array_merge($decodedExisting, $decodedPosted));
            $activityStateDocument = $result;
        }

        $activityStateDocument->setContent($stateObject);
        // Dates
        $currentDate = Util\Date::dateTimeExact();
        $activityStateDocument->setMongoTimestamp(Util\Date::dateTimeToMongoDate($currentDate));

        $activityStateDocument->setActivityId($parameters->get('activityId'));
        $activityStateDocument->setAgent($agent);
        if ($parameters->has('registration')) {
            $activityStateDocument->setRegistration($parameters->get('registration'));
        }
        $activityStateDocument->setStateId($parameters->get('stateId'));
        $activityStateDocument->setContentType($contentType);
        $activityStateDocument->setHash(sha1($stateObject));

        $storage->upsert(self::COLLECTION_NAME, $expression, $activityStateDocument);

        return $activityStateDocument;
    }

    /**
     * {@inheritDoc}
     */
    public function delete($parameters)
    {
        $storage = $this->getContainer()->get('storage');
        $expression = $storage->createExpression();

        if ($parameters->has('stateId')) {
            $expression->where('stateId', $parameters->get('stateId'));
        }

        $expression->where('activityId', $parameters->get('activityId'));

        $agent = $parameters->get('agent');
        $agent = json_decode($agent, true);

        // TODO 0.11.x move to validator layer, add to jsonschema
        //      from https://github.com/adlnet/xAPI-Spec/blob/master/xAPI-Communication.md#agentprofres
        //      The "agent" parameter is an Agent Object and not a Group. Learning Record Providers wishing to store data against an Identified Group can use the Identified Group's identifier within an Agent Object.
        $uniqueIdentifier = Util\xAPI::extractUniqueIdentifier($agent);
        if (null === $uniqueIdentifier) {
            throw new AdapterException('Invalid `agent` parameter: missing IFI', Controller::STATUS_BAD_REQUEST);
        }

        $expression->where('agent.'.$uniqueIdentifier, $agent[$uniqueIdentifier]);

        if ($parameters->has('registration')) {
            $expression->where('registration', $parameters->get('registration'));
        }

        $deletionResult = $storage->delete(self::COLLECTION_NAME, $expression);
        return $deletionResult;
    }

    private function validateCursorCountValid($cursorCount)
    {
        if ($cursorCount === 0) {
            throw new AdapterException('Activity state does not exist.', Controller::STATUS_NOT_FOUND);
        }
    }

    private function validateJsonDecodeErrors()
    {
        if (json_last_error() !== JSON_ERROR_NONE) {
            throw new AdapterException('Invalid JSON in existing document. Cannot merge!', Controller::STATUS_BAD_REQUEST);
        }
    }

    private function validateDocumentType($document, $contentType)
    {
        if (! Util\Parser::isApplicationJson($document->getContentType())) {
            throw new AdapterException('Original document is not JSON. Cannot merge!', Controller::STATUS_BAD_REQUEST);
        }
        if (! Util\Parser::isApplicationJson($contentType)) {
            throw new AdapterException('Posted document is not JSON. Cannot merge!', Controller::STATUS_BAD_REQUEST);
        }
    }
}
