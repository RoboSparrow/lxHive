<?php

/*
 * This file is part of lxHive LRS - http://lxhive.org/
 *
 * Copyright (C) 2015 Brightcookie Pty Ltd
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

use Monolog\Logger;
use Symfony\Component\Yaml\Parser as YamlParser;
use API\Resource;
use League\Url\Url;
use API\Bootstrap;
use API\Util\Set;
use API\Service\Auth\OAuth as OAuthService;
use API\Service\Auth\Basic as BasicAuthService;
use API\Service\Log as LogService;
use API\Parser\PsrRequest as PsrRequestParser;
use API\Service\Auth\Exception as AuthFailureException;
use API\Util\Versioning;

class Bootstrap
{
    private $id;

    public function __construct($id)
    {
        $this->id = $id;
    }

    public function initWebContainer()
    {
        $yamlParser = new YamlParser();
        $filesystem = new \League\Flysystem\Filesystem(new \League\Flysystem\Adapter\Local(__DIR__.'/../'));

        // 0. Use settings from Config.yml
        $settings = $yamlParser->parse($filesystem->read('app/config/Config.yml'));

        // 1. Load more settings based on mode
        $settings = array_merge($settings, $yamlParser->parse($filesystem->read('app/config/Config.' . $settings['mode'] . '.yml')));

    }
}

// Default config
try {
    Config\Yaml::getInstance()->addFile($appRoot.'/src/xAPI/Config/Config.yml');

    // Only invoked if mode is "production"
    $app->configureMode('production', function () use ($app, $appRoot) {
        // Add config
        Config\Yaml::getInstance()->addFile($appRoot.'/src/xAPI/Config/Config.production.yml');
    });

    // Only invoked if mode is "development"
    $app->configureMode('development', function () use ($app, $appRoot) {
        // Add config
        Config\Yaml::getInstance()->addFile($appRoot.'/src/xAPI/Config/Config.development.yml');
    });
} catch (\Exception $e) {
    if (PHP_SAPI === 'cli' && ((isset($argv[1]) && $argv[1] === 'setup:db') || (isset($argv[0]) && !isset($argv[1])))) {
        // Only invoked if mode is "development"
        $app->configureMode('development', function () use ($app, $appRoot) {
            // Add config
            Config\Yaml::getInstance()->addFile($appRoot.'/src/xAPI/Config/Templates/Config.development.yml');
        });
    } else {
        throw new \Exception('You must run the setup:db command using the X CLI tool!');
    }
}

// RESTful, disable slim's html PrettyException, and deal with legacy lxhive config
$app->config('_debug', $app->config('debug'));
$app->config('debug', false);

if (PHP_SAPI !== 'cli') {
    $app->url = Url::createFromServer($_SERVER);
}

// Logger
$app->configureMode($app->getMode(), function () use ($app, $appRoot) {

    $config = $app->config('log_handlers');
    $debug = $app->config('_debug');

    $handlers = [];
    $stream = $appRoot.'/storage/logs/production.'.date('Y-m-d').'.log';

    if (null === $config) {
        $config = ['ErrorLogHandler'];
    }

    if (PHP_SAPI === 'cli') {
        $config = ['StreamHandler', 'ErrorLogHandler'];
        $stream = 'php://output';
    }

    $formatter = new \Monolog\Formatter\LineFormatter();

    // Set up logging
    if (in_array('FirePHPHandler', $config)) {
        $handler = new \Monolog\Handler\FirePHPHandler();
        $handlers[] = $handler;
    }

    if (in_array('ChromePHPHandler', $config)) {
        $handler = new \Monolog\Handler\ChromePHPHandler();
        $handlers[] = $handler;
    }

    if (in_array('StreamHandler', $config)) {
        $handler = new \Monolog\Handler\StreamHandler($stream);
        $handler->setFormatter($formatter);
        $handlers[] = $handler;
    }

    if (empty($handlers) || in_array('ErrorLogHandler', $config)) {
        $handler = new \Monolog\Handler\ErrorLogHandler();
        $handler->setFormatter($formatter);
        $handlers[] = $handler;
    }

    //@TODO third party dependency should be removed in slim3
    $logger = new \Flynsarmy\SlimMonolog\Log\MonologWriter(array(
        'handlers' => $handlers,
    ));

    $app->config('log.writer', $logger);

    $logLevel = ($debug) ? \Slim\Log::DEBUG : \Slim\Log::ERROR;
    $app->log->setLevel($logLevel);
});

// Error handling
$app->error(function (\Exception $e) {
    $data = null;
    $code = $e->getCode();
    if ($code < 100) {
        $code = 500;
    }
    if (method_exists($e, 'getData')) {
        $data = $e->getData();
    }
    Resource::error($code, $e->getMessage(), $data, $e->getTrace());
});

// Database layer setup
$app->hook('slim.before', function () use ($app) {
    // Temporary database layer setup - will be moved to bootstrap later
    $app->container->singleton('storage', function () use ($app) {
        $storageInUse = $app->config('storage')['in_use'];
        $storageClass = '\\API\\Storage\\Adapter\\'.$storageInUse.'\\'.$storageInUse;
        if (!class_exists($storageClass)) {
            throw new \InvalidArgumentException('Storage type selected in config is invalid!');
        }
        $storageAdapter = new $storageClass($app);

        return $storageAdapter;
    });

    $app->container->singleton('eventDispatcher', function () use ($app) {
        // Instantiate event dispatcher
        $eventDispatcher = new Symfony\Component\EventDispatcher\EventDispatcher();

        return $eventDispatcher;
    });

    // Load any extensions that may exist
    $extensions = $app->config('extensions');

    if ($extensions) {
        foreach ($extensions as $extension) {
            if ($extension['enabled'] === true) {
                // Instantiate the extension class
                $className = $extension['class_name'];
                $extension = new $className($app);

                // Load any xAPI event handlers added by the extension
                $listeners = $extension->getEventListeners();
                foreach ($listeners as $listener) {
                    $app->eventDispatcher->addListener($listener['event'], [$extension, $listener['callable']], (isset($listener['priority']) ? $listener['priority'] : 0));
                }

                // Load any routes added by extension
                $routes = $extension->getRoutes();
                foreach ($routes as $route) {
                    $app->map($route['pattern'], [$extension, $route['callable']])->via($route['methods']);
                }

                // Load any Slim hooks added by extension
                $hooks = $extension->getHooks();
                foreach ($hooks as $hook) {
                    $app->hook($hook['hook'], [$extension, $hook['callable']]);
                }

                // TODO: Load any new data/content validators added by extension
            }
        }
    }
});

// CORS compatibility layer (Internet Explorer)
$app->hook('slim.before.router', function () use ($app) {
    if ($app->request->isPost() && $app->request->get('method')) {
        $method = $app->request->get('method');
        $app->environment()['REQUEST_METHOD'] = strtoupper($method);
        mb_parse_str($app->request->getBody(), $postData);
        $parameters = new Set($postData);
        if ($parameters->has('content')) {
            $content = $parameters->get('content');
            $app->environment()['slim.input'] = $content;
            $parameters->remove('content');
        } else {
            // Content is the only valid body parameter...everything else are either headers or query parameters
            $app->environment()['slim.input'] = '';
        }
        $app->request->headers->replace($parameters->all());
        $app->environment()['slim.request.query_hash'] = $parameters->all();
    }
});

// Parse version
$app->hook('slim.before.dispatch', function () use ($app, $appRoot) {
    // Version
    $app->container->singleton('version', function () use ($app) {
        if ($app->request->isOptions() || $app->request->getPathInfo() === '/about' || strpos(strtolower($app->request->getPathInfo()), '/oauth') === 0) {
            $versionString = $app->config('xAPI')['latest_version'];
        } else {
            $versionString = $app->request->headers('X-Experience-API-Version');
        }

        if ($versionString === null) {
            throw new \Exception('X-Experience-API-Version header missing.', Resource::STATUS_BAD_REQUEST);
        } else {
            try {
                $version = Versioning::fromString($versionString);
            } catch (\InvalidArgumentException $e) {
                throw new \Exception('X-Experience-API-Version header invalid.', Resource::STATUS_BAD_REQUEST);
            }

            if (!in_array($versionString, $app->config('xAPI')['supported_versions'])) {
                throw new \Exception('X-Experience-API-Version is not supported.', Resource::STATUS_BAD_REQUEST);
            }

            return $version;
        }
    });

    // Parser
    $app->container->singleton('parser', function () use ($app) {
        $parser = new PsrRequestParser($app->request);

        return $parser;
    });

    // Request logging
    $app->container->singleton('requestLog', function () use ($app) {
        $logService = new LogService($app);
        $logDocument = $logService->logRequest($app->request);

        return $logDocument;
    });

    // Auth - token
    $app->container->singleton('auth', function () use ($app) {
        if (!$app->request->isOptions() && !($app->request->getPathInfo() === '/about')) {
            $basicAuthService = new BasicAuthService($app);
            $oAuthService = new OAuthService($app);

            $token = null;

            try {
                $token = $oAuthService->extractToken($app->request);
                $app->requestLog->addRelation('oAuthToken', $token)->save();
            } catch (AuthFailureException $e) {
                // Ignore
            }

            try {
                $token = $basicAuthService->extractToken($app->request);
                $app->requestLog->addRelation('basicToken', $token)->save();
            } catch (AuthFailureException $e) {
                // Ignore
            }

            if (null === $token) {
                throw new \Exception('Credentials invalid!', Resource::STATUS_UNAUTHORIZED);
            }

            return $token;
        }
    });

    // Load Twig only if this is a request where we actually need it!
    if (strpos(strtolower($app->request->getPathInfo()), '/oauth') === 0) {
        $twigContainer = new Twig();
        $app->container->singleton('view', function () use ($twigContainer) {
            return $twigContainer;
        });
        $app->view->parserOptions['cache'] = $appRoot.'/storage/.Cache';
    }

    // Content type check
    if (($app->request->isPost() || $app->request->isPut()) && $app->request->getPathInfo() === '/statements' && !in_array($app->request->getMediaType(), ['application/json', 'multipart/mixed', 'application/x-www-form-urlencoded'])) {
        // Bad Content-Type
        throw new \Exception('Bad Content-Type.', Resource::STATUS_BAD_REQUEST);
    }
});

// Start with routing - dynamic for now
// TODO: Change to static routes with Slim3 - huge performance boost!

// ./X @TODO: dedicated identifier for ./X, so phpUnit client passes
// Note: commented out, until this identifier is added (otherwise X is unusable!)
//if (PHP_SAPI === 'cli') {
//    return;
//}

// Get
$app->get('/:resource(/(:action)(/))', function ($resource, $subResource = null) use ($app) {
    $resource = Resource::load($app->version, $resource, $subResource);
    if ($resource === null) {
        Resource::error(Resource::STATUS_NOT_FOUND, 'Cannot find requested resource.');
    } else {
        $resource->get();
    }
});

// Post
$app->post('/:resource(/(:action)(/))', function ($resource, $subResource = null) use ($app) {
    $resource = Resource::load($app->version, $resource, $subResource);
    if ($resource === null) {
        Resource::error(Resource::STATUS_NOT_FOUND, 'Cannot find requested resource.');
    } else {
        $resource->post();
    }
});

// Put
$app->put('/:resource(/(:action)(/))', function ($resource, $subResource = null) use ($app) {
    $resource = Resource::load($app->version, $resource, $subResource);
    if ($resource === null) {
        Resource::error(Resource::STATUS_NOT_FOUND, 'Cannot find requested resource.');
    } else {
        $resource->put();
    }
});

// Delete
$app->delete('/:resource(/(:action)(/))', function ($resource, $subResource = null) use ($app) {
    $resource = Resource::load($app->version, $resource, $subResource);
    if ($resource === null) {
        Resource::error(Resource::STATUS_NOT_FOUND, 'Cannot find requested resource.');
    } else {
        $resource->delete();
    }
});

// Options
$app->options('/:resource(/(:action)(/))', function ($resource, $subResource = null) use ($app) {
    $resource = Resource::load($app->version, $resource, $subResource);
    if ($resource === null) {
        Resource::error(Resource::STATUS_NOT_FOUND, 'Cannot find requested resource.');
    } else {
        $resource->options();
    }
});

// Not found
$app->notFound(function () {
    Resource::error(Resource::STATUS_NOT_FOUND, 'Cannot find requested resource.');

    /**
     * Gets the value of id.
     *
     * @return mixed
     */
    public function getId()
    {
        return $this->id;
    }
});

$app->run();