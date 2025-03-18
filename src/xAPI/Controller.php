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

namespace API;

use API\Controller\Error as Error;
use API\View\Error as ErrorView;
use Psr\Http\Message\ResponseInterface;
use API\Config;

abstract class Controller
{
    use BaseTrait;

    const STATUS_OK = 200;
    const STATUS_CREATED = 201;
    const STATUS_ACCEPTED = 202;
    const STATUS_NO_CONTENT = 204;

    const STATUS_MULTIPLE_CHOICES = 300;
    const STATUS_MOVED_PERMANENTLY = 301;
    const STATUS_FOUND = 302;
    const STATUS_NOT_MODIFIED = 304;
    const STATUS_USE_PROXY = 305;
    const STATUS_TEMPORARY_REDIRECT = 307;

    const STATUS_BAD_REQUEST = 400;
    const STATUS_UNAUTHORIZED = 401;
    const STATUS_FORBIDDEN = 403;
    const STATUS_NOT_FOUND = 404;
    const STATUS_NOT_FOUND_MESSAGE = 'Cannot find requested resource.';
    const STATUS_METHOD_NOT_ALLOWED = 405;
    const STATUS_METHOD_NOT_ALLOWED_MESSAGE = 'Method %s is not allowed on this resource.';
    const STATUS_NOT_ACCEPTED = 406;
    const STATUS_CONFLICT = 409;
    const STATUS_PRECONDITION_FAILED = 412;
    const STATUS_TOO_MANY_REQUESTS = 429;
    const STATUS_BANDIWDTH_LIMIT_EXCEEDED = 509;

    const STATUS_INTERNAL_SERVER_ERROR = 500;
    const STATUS_NOT_IMPLEMENTED = 501;

    /**
     * Request.
     */
    public $request;

    /**
     * Response.
     */
    public $response;

    /**
     * Construct.
     */
    public function __construct($container, $request, $response)
    {
        $this->setContainer($container);
        $this->setRequest($request);
        $this->setResponse($response);

        $this->init();
    }

    /**
     * Default init, use for overwrite only.
     */
    public function init()
    {
    }

    /**
     * Default (empty) GET handler
     * @return Psr\Http\Message\ResponseInterface A reponse
     */
    public function get()
    {
        return $this->error(self::STATUS_METHOD_NOT_ALLOWED, sprintf(self::STATUS_METHOD_NOT_ALLOWED_MESSAGE, 'GET'));
    }

    /**
     * Default (empty) POST handler
     * @return Psr\Http\Message\ResponseInterface A reponse
     */
    public function post()
    {
        return $this->error(self::STATUS_METHOD_NOT_ALLOWED, sprintf(self::STATUS_METHOD_NOT_ALLOWED_MESSAGE, 'POST'));
    }

    /**
     * Default (empty) PUT handler
     * @return Psr\Http\Message\ResponseInterface A reponse
     */
    public function put()
    {
        return $this->error(self::STATUS_METHOD_NOT_ALLOWED, sprintf(self::STATUS_METHOD_NOT_ALLOWED_MESSAGE, 'PUT'));
    }

    /**
     * Default (empty) DELETE handler
     * @return Psr\Http\Message\ResponseInterface A reponse
     */
    public function delete()
    {
        return $this->error(self::STATUS_METHOD_NOT_ALLOWED, sprintf(self::STATUS_METHOD_NOT_ALLOWED_MESSAGE, 'DELETE'));
    }

    /**
     * Default (empty) OPTIONS handler
     * @return Psr\Http\Message\ResponseInterface A reponse
     */
    public function options()
    {
        return $this->error(self::STATUS_METHOD_NOT_ALLOWED, sprintf(self::STATUS_METHOD_NOT_ALLOWED_MESSAGE, 'OPTIONS'));
    }

    /**
     * @param int   $status HTTP status code
     * @param array $data   The data
     * @param array $allow  Allowed methods
     */
    public function response($status = 200, $data = null, $allow = [])
    {
        if ($data instanceof ResponseInterface) {
            $this->response = $data;
        } else {
            $body = $this->response->getBody();
            $body->write($data);
        }

        if (!empty($allow)) {
            $this->response = $this->response->withHeader('Allow', strtoupper(implode(',', $allow)));
        }

        return $this->response->withStatus($status);
    }

    public function jsonResponse($status = 200, $data = [], $allow = [])
    {
        if ($data instanceof ResponseInterface) {
            $this->response = $data;
        } else {
            $this->response = $this->response->withJson($data, $status);
        }

        if (!empty($allow)) {
            $this->response = $this->response->withHeader('Allow', strtoupper(implode(',', $allow)));
        }

        return $this->response->withStatus($status);
    }

    /**
     * Error handler.
     *
     * @param int    $code    Error code
     * @param string $message Error message
     * @param array|object $data extra data to display
     * @return string
     */
    public function error($code, $message = '', $data = [])
    {
        return $this->jsonResponse($code, ['code' => $code, 'message' => $message, 'data' => $data]);
    }

    /**
     * Dynamically load a routing resource
     * @param string $version xAPI version
     * @param \Psr\Container\ContainerInterface service container
     * @param \Psr\Http\Message\ServerRequestInterface $request Slim request instance
     * @param \Psr\Http\Message\ResponseInterface $response Slim response instance
     * @param string $resource the main resource
     * @param string $subResource An optional subresource
     *
     * @return \API\ControllerInterface
     */
    public static function load($container, $request, $response, $controllerClass)
    {
        if (!class_exists($controllerClass)) {
            $errorResource = new Error($container, $request, $response);
            $errorResource = $errorResource->error(self::STATUS_NOT_FOUND, 'Cannot find requested resource.');

            return $errorResource;
        }

        return new $controllerClass($container, $request, $response);
    }

    /**
     * Gets the Request.
     *
     * @return mixed
     */
    public function getRequest()
    {
        return $this->request;
    }

    /**
     * Sets the Request.
     *
     * @param mixed $request the request
     *
     * @return self
     */
    public function setRequest($request)
    {
        $this->request = $request;

        return $this;
    }

    /**
     * Gets the Response.
     *
     * @return mixed
     */
    public function getResponse()
    {
        return $this->response;
    }

    /**
     * Sets the Response.
     *
     * @param mixed $response the response
     *
     * @return self
     */
    public function setResponse($response)
    {
        $this->response = $response;

        return $this;
    }
}
