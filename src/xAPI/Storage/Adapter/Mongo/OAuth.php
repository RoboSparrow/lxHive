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
use API\Storage\Query\OAuthInterface;

use API\Controller;
use API\Util;
use API\Storage\Provider;

use API\Storage\AdapterException;

class OAuth extends Provider implements OAuthInterface, SchemaInterface
{
    const COLLECTION_NAME = 'oAuthTokens';

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
            'name' => 'token.unique',
            'key'  => [
                'token' => 1
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
    public function storeToken($expiresAt, $user, $client, array $permissions = [], $code = null)
    {
        $storage = $this->getContainer()->get('storage');

        $accessTokenDocument = new \API\Document\Generic();

        $expiresDate = new \DateTime();
        $expiresDate->setTimestamp($expiresAt);
        $accessTokenDocument->setExpiresAt(Util\Date::dateTimeToMongoDate($expiresDate));
        $currentDate = new \DateTime();
        $accessTokenDocument->setCreatedAt(Util\Date::dateTimeToMongoDate($currentDate));

        $accessTokenDocument->setUserId($user->_id);
        $accessTokenDocument->setClientId($client->_id);


        $accessTokenDocument->setPermissions($permissions);
        $accessTokenDocument->setToken(Util\OAuth::generateToken());
        if (null !== $code) {
            $accessTokenDocument->setCode($code);
        }

        $storage->insertOne(self::COLLECTION_NAME, $accessTokenDocument);

        return $accessTokenDocument;
    }

    /**
     * {@inheritDoc}
     */
    public function getToken($accessToken)
    {
        $storage = $this->getContainer()->get('storage');
        $expression = $storage->createExpression();

        $expression->where('token', $accessToken);
        $accessTokenDocument = $storage->findOne(self::COLLECTION_NAME, $expression);

        $this->validateAccessTokenNotEmpty($accessTokenDocument);

        $accessTokenDocument = new \API\Document\Generic($accessTokenDocument);

        $this->validateExpiration($accessTokenDocument);

        $accessTokenDocumentTransformed = new \API\Document\OAuthToken($accessTokenDocument);

        // Fetch user for this token - this is done here intentionally for performance reasons
        // We could call $storage->getUserStorage() as well, but it'd be slower
        $accessTokenUser = $storage->findOne(User::COLLECTION_NAME, ['_id' => $accessTokenDocument->userId]);

        $accessTokenDocumentTransformed->setUser($accessTokenUser);

        // Set the host - needed for generation of access token authority
        $uri = $this->getContainer()->get('request')->getUri();
        $host = $uri->getBaseUrl();
        $accessTokenDocumentTransformed->setHost($host);

        return $accessTokenDocumentTransformed;
    }

    /**
     * {@inheritDoc}
     */
    public function deleteToken($accessToken)
    {
        $storage = $this->getContainer()->get('storage');
        $expression = $storage->createExpression();

        $expression->where('token', $accessToken);

        $storage->delete(self::COLLECTION_NAME, $expression);
    }

    /**
     * {@inheritDoc}
     */
    public function expireToken($accessToken)
    {
        $storage = $this->getContainer()->get('storage');
        $expression = $storage->createExpression();

        $expression->where('token', $accessToken);
        $storage->update(self::COLLECTION_NAME, $expression, ['expired' => true]);
    }

    /**
     * {@inheritDoc}
     */
    public function getTokenWithOneTimeCode($params)
    {
        $storage = $this->getContainer()->get('storage');
        $expression = $storage->createExpression();

        $expression->where('code', $params['code']);

        $tokenDocument = $storage->findOne(self::COLLECTION_NAME, $expression);

        $this->validateAccessTokenNotEmpty($tokenDocument);
        $tokenDocument = new \API\Document\AccessToken($tokenDocument);

        // TODO 0.11.x: Add CRUD somewhere method findById to simplify snippets such as this one, or call getClientById on OAuthClients instead...
        $clientExpression = $storage->createExpression();
        $clientExpression->where('_id', $tokenDocument->getClientId());
        $clientDocument = $storage->findOne(OAuthClients::COLLECTION_NAME, $clientExpression);

        $this->validateClientSecret($params, $clientDocument);

        $this->validateRedirectUri($params, $clientDocument);

        // Remove one-time code
        $tokenDocument->setCode(false);

        $storage->update(self::COLLECTION_NAME, $expression, $tokenDocument);

        return $tokenDocument;
    }

    private function validateExpiration($token)
    {
        if (isset($accessTokenDocument->expiresAt) && $accessTokenDocument->expiresAt !== null) {
            if ($expiresAt->sec <= time()) {
                throw new AdapterException('Expired token.', Controller::STATUS_FORBIDDEN);
            }
        }
    }

    private function validateAccessTokenNotEmpty($accessToken)
    {
        if ($accessToken === null) {
            throw new AdapterException('Invalid credentials.', Controller::STATUS_FORBIDDEN);
        }
    }

    private function validateClientSecret($params, $clientDocument)
    {
        if ($clientDocument->clientId !== $params['client_id'] || $clientDocument->secret !== $params['client_secret']) {
            throw new AdapterException('Invalid client_id/client_secret combination!', Controller::STATUS_BAD_REQUEST);
        }
    }

    private function validateRedirectUri($params, $clientDocument)
    {
        if ($params['redirect_uri'] !== $clientDocument->redirectUri) {
            throw new AdapterException('Redirect_uri mismatch!', Controller::STATUS_BAD_REQUEST);
        }
    }
}
