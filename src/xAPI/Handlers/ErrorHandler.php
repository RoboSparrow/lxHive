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
 *
 * This file was adapted from slim.
 * License information is available at https://github.com/slimphp/Slim/blob/3.x/LICENSE.md
 *
 */

namespace API\Handlers;

use Exception;
use Psr\Container\ContainerInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;

use Slim\Http\Body;

use API\Config;
use API\Util\Date as DateUtils;

class ErrorHandler
{
    private $logger = null;
    private $displayErrorDetails = false;

    /**
     * {@inheritdoc}
     */
    public function __construct(ContainerInterface $container) {
        if ($container->has('settings')) {
            $this->displayErrorDetails = $container->get('settings')['displayErrorDetails'];
        }

        if ($container->has('logger')) {
            $this->logger = $container->get('logger');
        }
    }

    /**
     * {@inheritdoc}
     */
    public function __invoke(ServerRequestInterface $request, ResponseInterface $response, Exception $exception)
    {
        $data = null;
        $message = $exception->getMessage();
        $statusCode = 500;

        // handle API\HttpException
        if (method_exists($exception, 'getStatusCode')) {
            $statusCode = $exception->getStatusCode();
        }

        if (method_exists($exception, 'getData')) {
            $data = $exception->getData();
        }

        // catch MongoDB exceptions, adjust codes AND prevent exception messages giving away connection details
        if (is_subclass_of($exception, '\MongoDB\Driver\Exception\Exception')) {
            $statusCode = 500;
            if (!$this->displayErrorDetails) {
                $message = 'Database error: ['.$exception->getCode().'], '.get_class($exception);
            }
        }

        $log = $this->renderLogError($statusCode, $message, $exception, $data);
        if ($this->logger) {
            $this->logger->error($log);
        } else {
            error_log($log);
        }

        $out = $this->renderJsonError($statusCode, $message, $exception, $data);
        $body = new Body(fopen('php://temp', 'r+'));
        $body->write($out);


        $date = DateUtils::dateTimeToISO8601(DateUtils::dateTimeExact());
        $version = Config::get(['xAPI', 'latest_version']);

        // TODO text/html for oauth
        return $response
            ->withStatus(($statusCode > 99 && $statusCode < 600) ? $statusCode : 500)
            ->withHeader('Content-Type', 'application/json')
            ->withHeader('X-Experience-API-Consistent-Through', 'application/json')
            ->withHeader('X-Experience-API-Version', $version)
            ->withHeader('X-Experience-API-Consistent-Through', $date)
            ->withBody($body);
    }

    /**
     * Render JSON error. Exeption message and code  are submitted separately because they might have been overwritten
     *
     * @param Exception $exception
     *
     * @return string
     */
    protected function renderJsonError($statusCode, $message, $exception, $data = null)
    {
        $error = [
            'code' => $statusCode,
            'message' => $message,
            'exception' => [],
        ];

        if ($this->displayErrorDetails)  {
            if ($data) {
                $error['data'] = json_encode($data);
            }

            do {
                $error['exception'][] = [
                    'type' => get_class($exception),
                    'code' => $exception->getCode(),
                    'message' => $exception->getMessage(),
                    'file' => $exception->getFile(),
                    'line' => $exception->getLine(),
                    'trace' => explode("\n", $exception->getTraceAsString()),
                ];
            } while ($exception = $exception->getPrevious());
        }

        return json_encode($error, JSON_PRETTY_PRINT);
    }

    /**
     * Write to the error log
     *
     * @param Exception $exception
     *
     * @return void
     */
    protected function renderLogError($statusCode, $message, $exception, $data = null)
    {
        $msg = 'Error: ['.$statusCode.']: ' . $message . PHP_EOL;
        if ($data) {
            $msg .= ' - data:\'' . json_encode($data) . '\'' . PHP_EOL;
        }
        $msg .= $this->renderThrowableAsText($exception);

        if ($this->displayErrorDetails)  {
            while ($exception = $exception->getPrevious()) {
                $msg .= PHP_EOL . 'Previous error:' . PHP_EOL;
                $msg .= $this->renderThrowableAsText($exception);
            }
        }

        return $msg;
    }

    /**
     * Render error as Text.
     *
     * @param Exception|Throwable $throwable
     *
     * @return string
     */
    protected function renderThrowableAsText($throwable)
    {
        $text = sprintf('Type: %s' . PHP_EOL, get_class($throwable));

        if ($code = $throwable->getCode()) {
            $text .= sprintf('Code: %s' . PHP_EOL, $code);
        }
        if ($message = $throwable->getMessage()) {
            $text .= sprintf('Message: %s' . PHP_EOL, htmlentities($message));
        }
        if ($file = $throwable->getFile()) {
            $text .= sprintf('File: %s' . PHP_EOL, $file);
        }
        if ($line = $throwable->getLine()) {
            $text .= sprintf('Line: %s' . PHP_EOL, $line);
        }
        if ($trace = $throwable->getTraceAsString()) {
            $text .= sprintf('Trace: %s', $trace);
        }

        return $text;
    }
}
