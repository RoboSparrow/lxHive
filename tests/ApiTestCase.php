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

use API\Config;
use API\Bootstrap;

use API\Admin\User as UserAdmin;
use API\Admin\Auth as AuthAdmin;
use API\Admin\AdminException;

use Slim\App;
use Slim\Http\Request;
use Slim\Http\Response;
use Slim\Http\Environment;

use Ramsey\Uuid\Uuid;

use PHPUnit\Framework\TestCase;

abstract class ApiTestCase extends TestCase
{
    /**
     * Process the application given a request method and URI
     *
     * @param string $requestMethod the request method (e.g. GET, POST, etc.)
     * @param string $requestUri the request URI
     * @param array|object|null $requestData the request data
     * @return \Slim\Http\Response
     */

    private $request;
    private $response;

    private $user = null;
    private $token = null;

    private static $version = '1.0.3';

    /**
     * Called before the first test of the test case class is run
     * Loads db config
     */
    public static function setUpBeforeClass(): void
    {
        // Slim 3 deprecation notices
        $level = error_reporting();
        error_reporting(E_ALL ^ E_DEPRECATED);

        Bootstrap::unlock();
        Bootstrap::reset();

        error_reporting($level);
    }


    /**
     * Called after the last test of this test class is run.
     */
    public static function tearDownAfterClass(): void
    {
        Bootstrap::unlock();
        Bootstrap::reset();
    }

    protected function lastRequest()
    {
        return $this->request;
    }

    protected function lastResponse()
    {
        return $this->response;
    }

    protected function xapiVersion()
    {
        return self::$version;
    }

    protected function createUuid() {
        return Uuid::uuid4()->toString();
    }

    protected function createStatement($email, $verb, $object) {
        return [
            'actor' => [
                'mbox' => 'mailto:'.$email,
            ],
            'verb' => [
                'id' => 'http://lxhive.com/xapi/verbs/'.$verb,
            ],
            'object' => [
                'id' => 'http://example.com/xapi/activity/'.$object,
            ],
        ];
    }

    protected function runApp($method, $uri, $headers=[], $body=null)
    {
        // clear last
        $this->request = null;
        $this->response = null;

        // boot app
        Bootstrap::unlock();
        Bootstrap::reset();
        $boot = Bootstrap::factory(Bootstrap::Web);
        $app = $boot->bootWebApp();

        $env = [
            'REQUEST_METHOD' => $method,
            'REQUEST_URI' => $uri
        ];

        /**
        * Special HTTP headers that do not have the "HTTP_" prefix
        * stole nfrom Slim\Http\Headers
        */
        $special = [
            'CONTENT_TYPE' => 1,
            'CONTENT_LENGTH' => 1,
            'PHP_AUTH_USER' => 1,
            'PHP_AUTH_PW' => 1,
            'PHP_AUTH_DIGEST' => 1,
            'AUTH_TYPE' => 1,
        ];

        // mock environment
        $environment = Environment::mock($env);

        // create request and response instances
        $request = Request::createFromEnvironment($environment);
        foreach ($headers as $key => $val) {
            $k = str_replace('-', '_', strtoupper($key));
            if (!in_array($k, array_keys($special))) {
                $k = 'HTTP_'.$k;
            }
            $env[$k] = $val;
        }

        $response = new Response();

        // write body
        if ($body) {
            $request->getBody()->write($body);
        }


        // run app
        ob_start();
        $response = $app->process($request, $response);
        ob_get_clean();

        // handle response
        $this->request = $request;
        $this->response = $response;

        return $response;
    }

    protected function createBasicToken($name, $email, $key, $secret, $permissions) {
        $expiresAt = null;

        Bootstrap::unlock();
        Bootstrap::reset();
        $boot = Bootstrap::factory(Bootstrap::Web);
        $app = $boot->bootWebApp();

        $container = $app->getContainer();
        $user = new UserAdmin($container);
        $auth = new AuthAdmin($container);

        $perms = $user->mergeInheritedPermissions($permissions);

        $this->user = $user->getUserByEmail($email);
        if (!$this->user) {
            $this->user = $user->addUser($name, 'test account', $email, $secret, $perms);
        }

        // (re) create token
        $auth->deleteToken($key);
        $this->token = $auth->addToken($name, 'test account', $expiresAt, $this->user, $perms, $key, $secret);
    }

}
