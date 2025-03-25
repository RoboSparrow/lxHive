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
use API\Storage\Query\StatementInterface;

use API\Config;
use API\Controller;
use API\Util;
use API\Storage\Provider;
use API\Storage\Query\StatementResult;

use API\Storage\AdapterException as AdapterException;

use Ramsey\Uuid\Uuid;

class Statement extends Provider implements StatementInterface, SchemaInterface
{
    const COLLECTION_NAME = 'statements';


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
        [
            'name' => 'statementId.unique',
            'key'  => [
                'statement.id' => 1
            ],
            'unique' => true,
        ]
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
    public function get($parameters)
    {
        $storage = $this->getContainer()->get('storage');
        $expression = $storage->createExpression();
        $queryOptions = [];

        $parameters = new Util\Collection($parameters);

        // Single statement
        if ($parameters->has('statementId')) {
            $rawStatementId = $parameters->get('statementId');
            $normalizedStatementId = Util\xAPI::normalizeUuid($rawStatementId);
            $expression->where('statement.id', $normalizedStatementId);
            $expression->where('voided', false);

            $this->validateStatementId($parameters['statementId']);

            $cursor = $storage->find(self::COLLECTION_NAME, $expression);

            $cursor = $this->validateCursorNotEmpty($cursor);

            $statementResult = new StatementResult();
            $statementResult->setCursor($cursor);
            $statementResult->setRemainingCount(1);
            $statementResult->setTotalCount(1);
            $statementResult->setHasMore(false);
            $statementResult->setSingleStatementRequest(true);

            return $statementResult;
        }

        if ($parameters->has('voidedStatementId')) {
            $rawStatementId = $parameters->get('voidedStatementId');
            $normalizedStatementId = Util\xAPI::normalizeUuid($rawStatementId);
            $expression->where('statement.id', $normalizedStatementId);
            $expression->where('voided', true);

            $this->validateStatementId($parameters['voidedStatementId']);

            $cursor = $storage->find(self::COLLECTION_NAME, $expression);

            $cursor = $this->validateCursorNotEmpty($cursor);

            $statementResult = new StatementResult();
            $statementResult->setCursor($cursor);
            $statementResult->setRemainingCount(1);
            $statementResult->setTotalCount(1);
            $statementResult->setHasMore(false);
            $statementResult->setSingleStatementRequest(true);

            return $statementResult;
        }

        // New StatementResult for non-single statement queries
        $statementResult = new StatementResult();

        $expression->where('voided', false);

        // Multiple statements
        if ($parameters->has('agent')) {
            $agent = $parameters->get('agent');
            $agent = json_decode($agent, true);

            $uniqueIdentifier = Util\xAPI::extractUniqueIdentifier($agent);
            $objectType = Util\xAPI::extractIriObjectType($agent);

            // TODO 0.11.x conformance validation: move into validation layer
            if (null === $uniqueIdentifier && $objectType === 'Group') {
                throw new AdapterException('No support for querying Anonymous Groups', Controller::STATUS_BAD_REQUEST);
            }
            // TODO 0.11.x move into validation layer
            if (null === $uniqueIdentifier) {
                throw new AdapterException('Unknown or invalid agent type', Controller::STATUS_BAD_REQUEST);
            }

            if ($parameters->has('related_agents') && $parameters->get('related_agents') === 'true') {
                if ($uniqueIdentifier === 'account') {
                    $expression->whereAnd(
                        $expression->expression()->whereOr(
                            $expression->expression()->whereAnd(
                                $expression->expression()->where('statement.actor.'.$uniqueIdentifier.'.homePage', $agent[$uniqueIdentifier]['homePage']),
                                $expression->expression()->where('statement.actor.'.$uniqueIdentifier.'.name', $agent[$uniqueIdentifier]['name'])
                            ),
                            $expression->expression()->whereAnd(
                                $expression->expression()->where('statement.object.'.$uniqueIdentifier.'.homePage', $agent[$uniqueIdentifier]['homePage']),
                                $expression->expression()->where('statement.object.'.$uniqueIdentifier.'.name', $agent[$uniqueIdentifier]['name'])
                            ),
                            $expression->expression()->whereAnd(
                                $expression->expression()->where('statement.authority.'.$uniqueIdentifier.'.homePage', $agent[$uniqueIdentifier]['homePage']),
                                $expression->expression()->where('statement.authority.'.$uniqueIdentifier.'.name', $agent[$uniqueIdentifier]['name'])
                            ),
                            $expression->expression()->whereAnd(
                                $expression->expression()->where('statement.context.team.'.$uniqueIdentifier.'.homePage', $agent[$uniqueIdentifier]['homePage']),
                                $expression->expression()->where('statement.context.team.'.$uniqueIdentifier.'.name', $agent[$uniqueIdentifier]['name'])
                            ),
                            $expression->expression()->whereAnd(
                                $expression->expression()->where('statement.context.instructor.'.$uniqueIdentifier.'.homePage', $agent[$uniqueIdentifier]['homePage']),
                                $expression->expression()->where('statement.context.instructor.'.$uniqueIdentifier.'.name', $agent[$uniqueIdentifier]['name'])
                            ),
                            $expression->expression()->whereAnd(
                                $expression->expression()->where('statement.object.objectType', 'SubStatement'),
                                $expression->expression()->where('statement.object.object.'.$uniqueIdentifier.'.homePage', $agent[$uniqueIdentifier]['homePage']),
                                $expression->expression()->where('statement.object.object.'.$uniqueIdentifier.'.name', $agent[$uniqueIdentifier]['name'])
                            ),
                            $expression->expression()->whereAnd(
                                $expression->expression()->where('references.actor.'.$uniqueIdentifier.'.homePage', $agent[$uniqueIdentifier]['homePage']),
                                $expression->expression()->where('references.actor.'.$uniqueIdentifier.'.name', $agent[$uniqueIdentifier]['name'])
                            ),
                            $expression->expression()->whereAnd(
                                $expression->expression()->where('references.object.'.$uniqueIdentifier.'.homePage', $agent[$uniqueIdentifier]['homePage']),
                                $expression->expression()->where('references.object.'.$uniqueIdentifier.'.name', $agent[$uniqueIdentifier]['name'])
                            ),
                            $expression->expression()->whereAnd(
                                $expression->expression()->where('references.authority.'.$uniqueIdentifier.'.homePage', $agent[$uniqueIdentifier]['homePage']),
                                $expression->expression()->where('references.authority.'.$uniqueIdentifier.'.name', $agent[$uniqueIdentifier]['name'])
                            ),
                            $expression->expression()->whereAnd(
                                $expression->expression()->where('references.context.team.'.$uniqueIdentifier.'.homePage', $agent[$uniqueIdentifier]['homePage']),
                                $expression->expression()->where('references.context.team.'.$uniqueIdentifier.'.name', $agent[$uniqueIdentifier]['name'])
                            ),
                            $expression->expression()->whereAnd(
                                $expression->expression()->where('references.context.instructor.'.$uniqueIdentifier.'.homePage', $agent[$uniqueIdentifier]['homePage']),
                                $expression->expression()->where('references.context.instructor.'.$uniqueIdentifier.'.name', $agent[$uniqueIdentifier]['name'])
                            ),
                            $expression->expression()->whereAnd(
                                $expression->expression()->where('references.object.objectType', 'SubStatement'),
                                $expression->expression()->where('references.object.object.'.$uniqueIdentifier.'.homePage', $agent[$uniqueIdentifier]['homePage']),
                                $expression->expression()->where('references.object.object.'.$uniqueIdentifier.'.name', $agent[$uniqueIdentifier]['name'])
                            )
                        )
                    );
                } else {
                    $expression->whereAnd(
                        $expression->expression()->whereOr(
                            $expression->expression()->where('statement.actor.'.$uniqueIdentifier, $agent[$uniqueIdentifier]),
                            $expression->expression()->where('statement.object.'.$uniqueIdentifier, $agent[$uniqueIdentifier]),
                            $expression->expression()->where('statement.authority.'.$uniqueIdentifier, $agent[$uniqueIdentifier]),
                            $expression->expression()->where('statement.context.team.'.$uniqueIdentifier, $agent[$uniqueIdentifier]),
                            $expression->expression()->where('statement.context.instructor.'.$uniqueIdentifier, $agent[$uniqueIdentifier]),
                            $expression->expression()->whereAnd(
                                $expression->expression()->where('statement.object.objectType', 'SubStatement'),
                                $expression->expression()->where('statement.object.object.'.$uniqueIdentifier, $agent[$uniqueIdentifier])
                            ),
                            $expression->expression()->where('references.actor.'.$uniqueIdentifier, $agent[$uniqueIdentifier]),
                            $expression->expression()->where('references.object.'.$uniqueIdentifier, $agent[$uniqueIdentifier]),
                            $expression->expression()->where('references.authority.'.$uniqueIdentifier, $agent[$uniqueIdentifier]),
                            $expression->expression()->where('references.context.team.'.$uniqueIdentifier, $agent[$uniqueIdentifier]),
                            $expression->expression()->where('references.context.instructor.'.$uniqueIdentifier, $agent[$uniqueIdentifier]),
                            $expression->expression()->whereAnd(
                                $expression->expression()->where('references.object.objectType', 'SubStatement'),
                                $expression->expression()->where('references.object.object.'.$uniqueIdentifier, $agent[$uniqueIdentifier])
                            )
                        )
                    );
                }
            } else {
                if ($uniqueIdentifier === 'account') {
                    $expression->whereAnd(
                        $expression->expression()->whereOr(
                            $expression->expression()->whereAnd(
                                $expression->expression()->where('statement.actor.'.$uniqueIdentifier.'.homePage', $agent[$uniqueIdentifier]['homePage']),
                                $expression->expression()->where('statement.actor.'.$uniqueIdentifier.'.name', $agent[$uniqueIdentifier]['name'])
                            ),
                            $expression->expression()->whereAnd(
                                $expression->expression()->where('statement.object.'.$uniqueIdentifier.'.homePage', $agent[$uniqueIdentifier]['homePage']),
                                $expression->expression()->where('statement.object.'.$uniqueIdentifier.'.name', $agent[$uniqueIdentifier]['name'])
                            ),
                            $expression->expression()->whereAnd(
                                $expression->expression()->where('references.actor.'.$uniqueIdentifier.'.homePage', $agent[$uniqueIdentifier]['homePage']),
                                $expression->expression()->where('references.actor.'.$uniqueIdentifier.'.name', $agent[$uniqueIdentifier]['name'])
                            ),
                            $expression->expression()->whereAnd(
                                $expression->expression()->where('references.object.'.$uniqueIdentifier.'.homePage', $agent[$uniqueIdentifier]['homePage']),
                                $expression->expression()->where('references.object.'.$uniqueIdentifier.'.name', $agent[$uniqueIdentifier]['name'])
                            )
                        )
                    );
                } else {
                    $expression->whereAnd(
                        $expression->expression()->whereOr(
                            $expression->expression()->where('statement.actor.'.$uniqueIdentifier, $agent[$uniqueIdentifier]),
                            $expression->expression()->where('statement.object.'.$uniqueIdentifier, $agent[$uniqueIdentifier]),
                            $expression->expression()->where('references.actor.'.$uniqueIdentifier, $agent[$uniqueIdentifier]),
                            $expression->expression()->where('references.object.'.$uniqueIdentifier, $agent[$uniqueIdentifier])
                        )
                    );
                }
            }
        }

        if ($parameters->has('verb')) {
            $expression->whereAnd(
                $expression->expression()->whereOr(
                    $expression->expression()->where('statement.verb.id', $parameters->get('verb')),
                    $expression->expression()->where('references.verb.id', $parameters->get('verb'))
                )
            );
        }

        if ($parameters->has('activity')) {
            // Handle related
            if ($parameters->has('related_activities') && $parameters->get('related_activities') === 'true') {
                $expression->whereAnd(
                    $expression->expression()->whereOr(
                        $expression->expression()->where('statement.object.id', $parameters->get('activity')),
                        $expression->expression()->where('statement.context.contextActivities.parent.id', $parameters->get('activity')),
                        $expression->expression()->where('statement.context.contextActivities.category.id', $parameters->get('activity')),
                        $expression->expression()->where('statement.context.contextActivities.grouping.id', $parameters->get('activity')),
                        $expression->expression()->where('statement.context.contextActivities.other.id', $parameters->get('activity')),
                        $expression->expression()->where('statement.context.contextActivities.parent.id', $parameters->get('activity')),
                        $expression->expression()->where('statement.context.contextActivities.parent.id', $parameters->get('activity')),
                        $expression->expression()->whereAnd(
                            $expression->expression()->where('statement.object.objectType', 'SubStatement'),
                            $expression->expression()->where('statement.object.object', $parameters->get('activity'))
                        ),
                        $expression->expression()->where('references.object.id', $parameters->get('activity')),
                        $expression->expression()->where('references.context.contextActivities.parent.id', $parameters->get('activity')),
                        $expression->expression()->where('references.context.contextActivities.category.id', $parameters->get('activity')),
                        $expression->expression()->where('references.context.contextActivities.grouping.id', $parameters->get('activity')),
                        $expression->expression()->where('references.context.contextActivities.other.id', $parameters->get('activity')),
                        $expression->expression()->where('references.context.contextActivities.parent.id', $parameters->get('activity')),
                        $expression->expression()->where('references.context.contextActivities.parent.id', $parameters->get('activity')),
                        $expression->expression()->whereAnd(
                            $expression->expression()->where('references.object.objectType', 'SubStatement'),
                            $expression->expression()->where('references.object.object', $parameters->get('activity'))
                        )
                    )
                );
            } else {
                $expression->whereAnd(
                    $expression->expression()->whereOr(
                        $expression->expression()->where('statement.object.id', $parameters->get('activity')),
                        $expression->expression()->where('references.object.id', $parameters->get('activity'))
                    )
                );
            }
        }

        if ($parameters->has('registration')) {
            $rawRegistrationId = $parameters->get('registration');
            $normalizedRegistrationId = Util\xAPI::normalizeUuid($rawRegistrationId);
            $expression->whereAnd(
                $expression->expression()->whereOr(
                    $expression->expression()->where('statement.context.registration', $normalizedRegistrationId),
                    $expression->expression()->where('references.context.registration', $normalizedRegistrationId)
                )
            );
        }

        // Date based filters
        if ($parameters->has('since')) {
            $since = Util\Date::dateStringToMongoDate($parameters->get('since'));
            $expression->whereGreaterOrEqual('mongo_timestamp', $since);
        }

        if ($parameters->has('until')) {
            $until = Util\Date::dateStringToMongoDate($parameters->get('until'));
            $expression->whereLessOrEqual('mongo_timestamp', $until);
        }

        // Count before paginating
        $statementResult->setTotalCount($storage->count(self::COLLECTION_NAME, $expression, $queryOptions));

        // Handle pagination
        if ($parameters->has('since_id')) {
            $id = new \MongoDB\BSON\ObjectID($parameters->get('since_id'));
            $expression->whereGreater('_id', $id);
        }

        if ($parameters->has('until_id')) {
            $id = new \MongoDB\BSON\ObjectID($parameters->get('until_id'));
            $expression->whereLess('_id', $id);
        }

        $statementResult->setRequestedFormat(Config::get(['xAPI', 'default_statement_get_format']));
        if ($parameters->has('format')) {
            $statementResult->setRequestedFormat($parameters->get('format'));
        }

        $statementResult->setSortDescending(true);
        $statementResult->setSortAscending(false);
        $queryOptions['sort'] = ['_id' => -1];
        if ($parameters->has('ascending')) {
            $asc = $parameters->get('ascending');
            if (strtolower($asc) === 'true' || $asc === '1') {
                $queryOptions['sort'] = ['_id' => 1];
                $statementResult->setSortDescending(false);
                $statementResult->setSortAscending(true);
            }
        }

        if ($parameters->has('limit') && $parameters->get('limit') < Config::get(['xAPI', 'statement_get_limit']) && $parameters->get('limit') > 0) {
            $limit = $parameters->get('limit');
        } else {
            $limit = Config::get(['xAPI', 'statement_get_limit']);
        }

        // Remaining includes the current page!
        $statementResult->setRemainingCount($storage->count(self::COLLECTION_NAME, $expression, $queryOptions));

        if ($statementResult->getRemainingCount() > $limit) {
            $statementResult->setHasMore(true);
        } else {
            $statementResult->setHasMore(false);
        }

        $queryOptions['limit'] = (int)$limit;

        // TODO 0.11.x improve following or abstract it into method
        $auth = $this->getContainer()->get('auth');
        if ($auth->hasPermission('statements/read/mine') && !$auth->hasPermission('statements/read')) {
            $expression->where('userId', $this->getAccessToken()->getUserId());
        }

        $cursor = $storage->find(self::COLLECTION_NAME, $expression, $queryOptions);

        $statementResult->setCursor($cursor);

        return $statementResult;
    }

    /**
     * Get StatementResult by id. Allow null return.
     *
     * @return StatementResult|null
     */
    private function _getById($statementId)
    {
        $storage = $this->getContainer()->get('storage');
        $expression = $storage->createExpression();
        $expression->where('statement.id', $statementId);
        $requestedStatement = $storage->findOne('statements', $expression);

        return $requestedStatement;
    }

    /**
     * {@inheritDoc}
     */
    public function getById($statementId)
    {
        $requestedStatement = $this->_getById($statementId);
        if (null === $requestedStatement) {
            throw new AdapterException('Requested statement does not exist!', Controller::STATUS_BAD_REQUEST);
        }

        return $requestedStatement;
    }

    /**
     * {@inheritDoc}
     * TODO make this rather private and remove from interface
     * TODO break down itno smaller units and separate validation
     */
    public function transformForInsert($statementObject)
    {
        $storage = $this->getContainer()->get('storage');
        $version = $this->getContainer()->get('xapi-version');

        $uri = $this->getContainer()->get('request')->getUri();
        $attachmentBase = $uri->getBaseUrl().Config::get(['filesystem', 'exposed_url']);

        if (isset($statementObject->{'id'})) {
            $expression = $storage->createExpression();
            $normalizedStatementId = Util\xAPI::normalizeUuid($statementObject->{'id'});
            $expression->where('statement.id', $normalizedStatementId);

            $result = $storage->findOne(self::COLLECTION_NAME, $expression);

            // ID exists, validate if different or conflict
            if ($result) {
                $existingStatement = $result->statement;
                $this->validateStatementMatches($statementObject, $existingStatement);
            }
        }

        $statementDocument = new \API\Document\Statement();
        $statementDocument->setVersion($version);

        // Object

        // Overwrite authority - unless it's a super token and manual authority is set
        if (!($this->getAuth()->hasPermission('super') && isset($statementObject->{'authority'})) || !isset($statementObject->{'authority'})) {
            $statementObject->{'authority'} = $this->getAccessToken()->generateAuthority();
        }
        $statementDocument->setStatement($statementObject);

        // TODO move to JsonSchema, if possible
        $this->validateStatementObjectDefinition($statementDocument);

        // Dates

        $currentDate = Util\Date::dateTimeExact();
        $statementDocument->normalizeExistingIds();
        $statementDocument->setVoided(false);
        $statementDocument->setStored(Util\Date::dateTimeToISO8601($currentDate));
        $statementDocument->setMongoTimestamp(Util\Date::dateTimeToMongoDate($currentDate));
        $statementDocument->setDefaultTimestamp();
        $statementDocument->fixAttachmentLinks($attachmentBase);
        $statementDocument->convertExtensionKeysToUnicode();
        $statementDocument->setDefaultId();
        $statementDocument->legacyContextActivities();

        // referenced statement

        $referencedStatementId = null;
        $referencedStatement = null;

        if ($statementDocument->isReferencing()) {

            $referencedStatementId = $statementDocument->getReferencedStatementId();
            $referencedStatement = $this->_getById($referencedStatementId);

            // #244 => 1.0.3: There is no requirement for the LRS to validate that the UUID matches a Statement that exists.
            if ($referencedStatement) {
                $referencedStatement = new \API\Document\Statement($referencedStatement);

                $existingReferences = [];
                if (null !== $referencedStatement->getReferences()) {
                    $existingReferences = $referencedStatement->getReferences();
                }
                $existingReferences[] = $referencedStatement->getStatement();
                $statementDocument->setReferences($existingReferences);
            }

        }

        // voiding statement (requires of referenced statement)

        if ($statementDocument->hasVoided()) {

            if (!$statementDocument->isReferencing()) {
                throw new AdapterException('Voiding statement does not use object type "StatementRef"', Controller::STATUS_BAD_REQUEST);
            }

            $referencedStatementId = $statementDocument->getReferencedStatementId();
            $referencedStatement = $this->_getById($referencedStatementId);

            if (version_compare($version , '1.0.3') < 0) {
                if (null === $referencedStatement) {
                    throw new AdapterException('Voiding statement: voided statement does not exist!', Controller::STATUS_BAD_REQUEST);
                }
            }

            // #244 => 1.0.3: There is no requirement for the LRS to validate that the UUID matches a Statement that exists.
            if ($referencedStatement) {
                $referencedStatement = new \API\Document\Statement($referencedStatement);

                $this->validateVoidedStatementNotVoiding($referencedStatement);
                $referencedStatement->setVoided(true);
                $expression = $storage->createExpression();
                $expression->where('statement.id', $referencedStatementId);

                $storage->update(self::COLLECTION_NAME, $expression, $referencedStatement);
            }

        }

        // activity

        if ($this->getAuth()->hasPermission('define')) {
            $activities = $statementDocument->extractActivities();
            if (count($activities) > 0) {
                // TODO 0.11.x  Possibly optimize this using a bulk update (using executeBulkWrite)
                // TODO 0.11.x Create upsertMultiple and updateMultiple methods on CRUD layer!
                foreach ($activities as $activity) {
                    $storage->upsert(Activity::COLLECTION_NAME, ['id' => $activity->id], $activity);
                }
            }
        }

        $statementDocument->setUserId($this->getAccessToken()->getUserId());

        // Add to log (disabled)
        // $this->getContainer()->get('requestLog')->addRelation('statements', $statementDocument)->save();

        return $statementDocument;
    }

    /**
     * {@inheritDoc}
     */
    public function insertOne($statementObject)
    {
        $statementDocument = $this->transformForInsert($statementObject);
        if (!isset($statementDocument->skipInsert)) {
            $storage = $this->getContainer()->get('storage');
            $storage->insertOne(self::COLLECTION_NAME, $statementDocument);
        } else {
            unset($statementDocument->skipInsert);
        }
        $statementResult = new StatementResult();
        $statementResult->setCursor([$statementDocument]);
        $statementResult->setRemainingCount(1);
        $statementResult->setHasMore(false);

        return $statementResult;
    }

    /**
     * {@inheritDoc}
     */
    public function insertMultiple($statementObjects)
    {
        $statementDocumentsInsert = [];
        $statementDocumentsView = [];
        foreach ($statementObjects as $statementObject) {
            $statementDocument = $this->transformForInsert($statementObject);
            if (!isset($statementDocument->skipInsert)) {
                $statementDocumentsInsert[] = $statementDocument;
            } else {
                unset($statementDocument->skipInsert);
            }
            $statementDocumentsView[] = $statementDocument;
        }

        $storage = $this->getContainer()->get('storage');
        $storage->insertMultiple(self::COLLECTION_NAME, $statementDocumentsInsert);

        $statementResult = new StatementResult();
        $statementResult->setCursor($statementDocumentsView);
        $statementResult->setRemainingCount(count($statementDocumentsView));
        $statementResult->setHasMore(false);

        return $statementResult;
    }

    /**
     * {@inheritDoc}
     */
    public function put($parameters, $statementObject)
    {
        $parameters = new Util\Collection($parameters);

        // Check statementId exists
        if (!$parameters->has('statementId')) {
            throw new AdapterException('The statementId parameter is missing!', Controller::STATUS_BAD_REQUEST);
        }

        $this->validateStatementId($parameters['statementId']);

        // Check statementId
        if (isset($statementObject->id)) {
            // Check for match
            $this->validateStatementIdMatch(Util\xAPI::normalizeUuid($statementObject->id), Util\xAPI::normalizeUuid($parameters['statementId']));
        } else {
            $statementObject->id = Util\xAPI::normalizeUuid($parameters['statementId']);
        }

        $statementDocument = $this->insertOne($statementObject);
        $statementResult = new StatementResult();
        $statementResult->setCursor([$statementDocument]);
        $statementResult->setRemainingCount(1);
        $statementResult->setHasMore(false);

        return $statementResult;
    }

    /**
     * {@inheritDoc}
     */
    public function delete($parameters)
    {
        throw AdapterException('Statements cannot be deleted, only voided!', Controller::STATUS_INTERNAL_SERVER_ERROR);
    }

    /**
     * Gets the Auth to validate for permissions.
     *
     * @return API\Document\Auth\AbstractToken
     */
    private function getAuth()
    {
        return $this->getContainer()->get('auth');
    }

    /**
     * Gets the Access token to validate for permissions.
     *
     * @return API\Document\Auth\AbstractToken
     */
    private function getAccessToken()
    {
        return $this->getContainer()->get('accessToken');
    }

    private function validateStatementMatches($incomingStatement, $existingStatement)
    {
        // Remove exempted attributes
        // https://github.com/adlnet/xAPI-Spec/blob/1.0.3/xAPI-Data.md#231-statement-immutability
        unset($incomingStatement->authority);
        unset($incomingStatement->stored);
        unset($incomingStatement->timestamp);
        unset($incomingStatement->version);
        unset($existingStatement->authority);
        unset($existingStatement->stored);
        unset($existingStatement->timestamp);
        unset($existingStatement->version);
        // Mismatch - return 409 Conflict
        if ($incomingStatement != $existingStatement) {
            throw new AdapterException('An existing statement already exists with the same ID ('.$existingStatement->id.') and is different from the one provided.', Controller::STATUS_CONFLICT);
        }
    }

    // TODO migrate to JsonSchema
    private function validateStatementObjectDefinition($document)
    {
        $definition = $document->getStatementObjectDefinition();
        if (!$definition || !(array)$definition) {
            // empty definitions are allowed
            return;
        }

        $activity = $document->isStatementObjectTypeActivity();
        if (!$activity) {
            return;
        }

        // activities of type cmi.interaction

        if (!empty($definition->correctResponsesPattern)) {
            if (empty($definition->interactionType)) {
                throw new AdapterException('Activity Definition uses correctResponsesPattern without \'interactionType\' property (in statement.object.definition)', Controller::STATUS_BAD_REQUEST);
            }
            return;
        }

        if (empty($definition->interactionType)) {
            return;
        }

        $interactionTypes = ['true-false', 'choice', 'fill-in', 'long-fill-in', 'matching', 'performance', 'sequencing', 'likert', 'numeric', 'other'];

        if (!in_array($definition->interactionType, $interactionTypes)) {
            throw new AdapterException('Property \'interactionType\' values must be one of ' . join(', ', $interactionTypes) . ' (in statement.object.definition)', Controller::STATUS_BAD_REQUEST);
        }

        $interactionComponents = ['choices', 'scale', 'source', 'targe', 'steps'];
        foreach($interactionComponents as $component) {
            if (!empty($definition->{$component})) {
                return;
            }
        }

        throw new AdapterException('Activities of type cmi.interaction require atl east one of \''.join(', ', $interactionComponents).'\' (in statement.object.definition)', Controller::STATUS_BAD_REQUEST);
    }

    private function validateVoidedStatementNotVoiding($referencedDocument)
    {
        if ($referencedDocument->isVoiding()) {
            throw new AdapterException('Voiding statements cannot be voided.', Controller::STATUS_CONFLICT);
        }
    }

    private function validateStatementId($id)
    {
        // Check statementId is acutally valid
        if (!Uuid::isValid($id)) {
            throw new AdapterException('The provided statement ID is invalid!', Controller::STATUS_BAD_REQUEST);
        }
    }

    private function validateStatementIdMatch($statementIdOne, $statementIdTwo)
    {
        if ($statementIdOne !== $statementIdTwo) {
            throw new AdapterException('Statement ID query parameter doesn\'t match the given statement property', Controller::STATUS_BAD_REQUEST);
        }
    }

    private function validateCursorNotEmpty($cursor)
    {
        $cursor = $cursor->toArray();
        if (empty($cursor)) {
            throw new AdapterException('Statement does not exist.', Controller::STATUS_NOT_FOUND);
        }
        return $cursor;
    }
}
