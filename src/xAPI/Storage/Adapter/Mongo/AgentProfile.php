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
use API\Storage\Query\AgentProfileInterface;

use API\Util;
use API\Controller;
use API\Storage\Provider;
use API\Storage\Query\DocumentResult;

use API\Storage\AdapterException;

// TODO 0.11.x remove header dependency from this layer into parser and submit abstract args from there, like an array of options: put($data, $profileId, $agentIfi, array $options (contentType, if match))

class AgentProfile extends Provider implements AgentProfileInterface, SchemaInterface
{
    const COLLECTION_NAME = 'agentProfiles';

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
        //profileId is not unique as per spec, only combination of profileId and agent
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

        // Single activity profile
        if ($parameters->has('profileId')) {
            $expression->where('profileId', $parameters['profileId']);
            $agent = $parameters['agent'];
            $agent = json_decode($agent, true);

            // TODO 0.11.x move to validator layer, add to jsonschema
            //      from https://github.com/adlnet/xAPI-Spec/blob/master/xAPI-Communication.md#agentprofres
            //      The "agent" parameter is an Agent Object and not a Group. Learning Record Providers wishing to store data against an Identified Group can use the Identified Group's identifier within an Agent Object.
            $uniqueIdentifier = Util\xAPI::extractUniqueIdentifier($agent);
            if (null === $uniqueIdentifier) {
                throw new AdapterException('Invalid `agent` parameter: missing ifi', Controller::STATUS_BAD_REQUEST);
            }

            $expression->where('agent.'.$uniqueIdentifier, $agent[$uniqueIdentifier]);

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

        $agent = $parameters['agent'];
        $agent = json_decode($agent);
        $expression->where('agent', $agent);

        if ($parameters->has('since')) {
            $since = Util\Date::dateStringToMongoDate($parameters['since']);
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
    public function post($parameters, $profileObject)
    {
        return $this->put($parameters, $profileObject);
    }

    /**
     * {@inheritDoc}
     */
    public function put($parameters, $profileObject)
    {
        // TODO 0.11.x: optimise (upsert)
        // rawPayload input is a stream...read it
        $profileObject = (string)$profileObject;
        $agent = $parameters['agent'];
        $agent = json_decode($agent, true);

        // TODO 0.11.x move to validator layer, add to jsonschema
        //      from https://github.com/adlnet/xAPI-Spec/blob/master/xAPI-Communication.md#agentprofres
        //      The "agent" parameter is an Agent Object and not a Group. Learning Record Providers wishing to store data against an Identified Group can use the Identified Group's identifier within an Agent Object.
        $uniqueIdentifier = Util\xAPI::extractUniqueIdentifier($agent);
        if (null === $uniqueIdentifier) {
            throw new AdapterException('Invalid `agent` parameter: missing ifi', Controller::STATUS_BAD_REQUEST);
        }

        $storage = $this->getContainer()->get('storage');

        // Set up the body to be saved
        $agentProfileDocument = new \API\Document\Generic();

        // Check for existing state - then merge if applicable
        $expression = $storage->createExpression();
        $expression->where('profileId', $parameters['profileId']);
        $expression->where('agent.'.$uniqueIdentifier, $agent[$uniqueIdentifier]);

        $result = $storage->findOne(self::COLLECTION_NAME, $expression);
        if ($result) {
            $result = new \API\Document\Generic($result);
        }

        $ifMatchHeader = isset($parameters['headers']['if-match']) ? $parameters['headers']['if-match'] : false;
        $ifNoneMatchHeader = isset($parameters['headers']['if-none-match']) ? $parameters['headers']['if-none-match'] : false;
        $this->validateMatchHeaderExists($ifMatchHeader, $ifNoneMatchHeader, $result);
        $this->validateMatchHeaders($ifMatchHeader, $ifNoneMatchHeader, $result);

        // ID exists, merge body
        $contentType = $parameters['headers']['content-type'];
        if ($contentType === null || empty($contentType)) {
            $contentType = 'text/plain';
        } else {
            $contentType = $contentType[0];
        }

        // ID exists, try to merge body if applicable
        if ($result) {
            $this->validateSourceDocumentType($contentType);
            $this->validateTargetDocumentType($result);

            $decodedExisting = json_decode($result->getContent(), true);
            $this->validateJsonDecodeErrors();

            $decodedPosted = json_decode($profileObject, true);
            $this->validateJsonDecodeErrors();

            $profileObject = json_encode(array_merge($decodedExisting, $decodedPosted));
            $agentProfileDocument = $result;
        }

        $agentProfileDocument->setContent($profileObject);
        // Dates
        $currentDate = Util\Date::dateTimeExact();
        $agentProfileDocument->setMongoTimestamp(Util\Date::dateTimeToMongoDate($currentDate));
        $agentProfileDocument->setAgent($agent);
        $agentProfileDocument->setProfileId($parameters['profileId']);
        $agentProfileDocument->setContentType($contentType);
        $agentProfileDocument->setHash(sha1($profileObject));

        $storage->upsert(self::COLLECTION_NAME, $expression, $agentProfileDocument);

        return $agentProfileDocument;
    }

    /**
     * {@inheritDoc}
     */
    public function delete($parameters)
    {
        $storage = $this->getContainer()->get('storage');
        $expression = $storage->createExpression();

        $expression->where('profileId', $parameters['profileId']);
        $agent = $parameters['agent'];
        $agent = json_decode($agent, true);

        // TODO 0.11.x move to validator layer, add to jsonschema
        //      from https://github.com/adlnet/xAPI-Spec/blob/master/xAPI-Communication.md#agentprofres
        //      The "agent" parameter is an Agent Object and not a Group. Learning Record Providers wishing to store data against an Identified Group can use the Identified Group's identifier within an Agent Object.
        $uniqueIdentifier = Util\xAPI::extractUniqueIdentifier($agent);
        if (null === $uniqueIdentifier) {
            throw new AdapterException('Invalid `agent` parameter: missing ifi', Controller::STATUS_BAD_REQUEST);
        }

        $expression->where('agent.'.$uniqueIdentifier, $agent[$uniqueIdentifier]);

        $result = $storage->findOne(self::COLLECTION_NAME, $expression);

        if ($result) {
            $result = new \API\Document\Generic($result);
        } else {
            throw new AdapterException('Profile does not exist!.', Controller::STATUS_NOT_FOUND);
        }

        $ifMatchHeader = isset($parameters['headers']['if-match']) ? $parameters['headers']['if-match'] : false;
        $ifNoneMatchHeader = isset($parameters['headers']['if-none-match']) ? $parameters['headers']['if-none-match'] : false;
        $this->validateMatchHeaderExists($ifMatchHeader, $ifNoneMatchHeader, $result);
        $this->validateMatchHeaders($ifMatchHeader, $ifNoneMatchHeader, $result);

        $deletionResult = $storage->delete(self::COLLECTION_NAME, $expression);
    }

    private function validateMatchHeaders($ifMatch, $ifNoneMatch, $result)
    {
        // If-Match first
        $ifMatch = (is_array($ifMatch) && isset($ifMatch[0])) ? $ifMatch[0] : $ifMatch;
        if ($ifMatch && $result && ($this->trimHeader($ifMatch) !== $result->getHash())) {
            throw new AdapterException('If-Match header doesn\'t match the current) ETag.', Controller::STATUS_PRECONDITION_FAILED);
        }

        // Then If-None-Match
        $ifNoneMatch = (is_array($ifNoneMatch) && isset($ifNoneMatch[0])) ? $ifNoneMatch[0] : $ifNoneMatch;
        if ($ifNoneMatch) {
            if ($this->trimHeader($ifNoneMatch) === '*' && $result) {
                throw new AdapterException('If-None-Match header is *, but a resource already exists.', Controller::STATUS_PRECONDITION_FAILED);
            } elseif ($result && $this->trimHeader($ifNoneMatch) === $result->getHash()) {
                throw new AdapterException('If-None-Match header matches the current ETag.', Controller::STATUS_PRECONDITION_FAILED);
            }
        }
    }

    private function validateMatchHeaderExists($ifMatch, $ifNoneMatch, $result)
    {
        // Check If-Match and If-None-Match here
        if (!$ifMatch && !$ifNoneMatch && $result) {
            throw new AdapterException('There was a conflict. Check the current state of the resource and set the "If-Match" header with the current ETag to resolve the conflict.', Controller::STATUS_CONFLICT);
        }
    }

    private function validateTargetDocumentType($document)
    {
        if (! Util\Parser::isApplicationJson($document->getContentType())) {
            throw new AdapterException('Original document is not JSON. Cannot merge!', Controller::STATUS_BAD_REQUEST);
        }
    }

    private function validateSourceDocumentType($documentType)
    {
        if (! Util\Parser::isApplicationJson($documentType)) {
            throw new AdapterException('Posted document is not JSON. Cannot merge!', Controller::STATUS_BAD_REQUEST);
        }
    }

    private function validateCursorCountValid($cursorCount)
    {
        if ($cursorCount === 0) {
            throw new AdapterException('Agent profile does not exist.', Controller::STATUS_NOT_FOUND);
        }
    }

    private function validateJsonDecodeErrors()
    {
        if (json_last_error() !== JSON_ERROR_NONE) {
            throw new AdapterException('Invalid JSON in existing document. Cannot merge!', Controller::STATUS_BAD_REQUEST);
        }
    }

    /**
     * Trims quotes from the header.
     *
     * @param string $headerString Header
     *
     * @return string Trimmed header
     */
    private function trimHeader($headerString)
    {
        return trim($headerString, '"');
    }
}
