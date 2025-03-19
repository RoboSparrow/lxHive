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

namespace API\Middleware;

use Slim\Http\Stream;
use Slim\Http\StatusCode;

use Psr\Http\Message\ServerRequestInterface as Request;
use Psr\Http\Message\ResponseInterface as Response;

use API\Config;
use API\Util\Date as DateUtils;

class XapiLegacyRequestMiddleware
{
    private $container;

    public function __construct($container) {
        $this->container = $container;
    }

    /**
     * Adds Xapi headers to response (XAPI section 1.3)
     * @see https://github.com/adlnet/xAPI-Spec/blob/master/xAPI-Communication.md#13-alternate-request-syntax
     * @see https://github.com/adlnet/xAPI-Spec/blob/master/xAPI-Communication.md#appendix-c-cross-domain-request-example
     *
     * @param  \Psr\Http\Message\ServerRequestInterface $request  PSR7 request
     * @param  \Psr\Http\Message\ResponseInterface      $response PSR7 response
     * @param  callable                                 $next     Next middleware
     *
     * @return \Psr\Http\Message\ResponseInterface
     */
    public function __invoke(Request $request, Response $response, $next)
    {
        $allowedHeaders = ['content-type', 'authorization', 'x-experience-api-version', 'content-length', 'if-match', 'if-none-match'];

        $method = strtoupper($request->getMethod());
        $params = $request->getQueryParams();

        // #313 special case: lxHive `more` URL for paginated statement requests (i.e. POST /statements?method=GET&until_id=59b8cdd0a097fa60676f2295)
        $until_id = null;
        if (isset($params['until_id'])) {
            $until_id = $params['until_id'];
            unset($params['until_id']);
        }

        // All xAPI requests issued MUST be POST
        if ($method !== 'POST') {
            $response = $next($request, $response);
            return $response;
        }

        // The intended xAPI method MUST be included as the value of the "method" query string parameter.
        if (!isset($params['method'])) {
            $response = $next($request, $response);
            return $response;
        }

        // The Learning Record Provider MUST NOT include any other query string parameters on the request.
        if (count($params) > 1) {
            $code = StatusCode::HTTP_BAD_REQUEST;
            return $response->withJson([
                    'code' => $code,
                    'message' => 'Alternate request syntax: Request MUST NOT include any other query string parameters than \'method\''
                ],
                $code);
        }

        // transform method
        $request = $request->withMethod($params['method']);

        // parse formdata body
        $body = (string)$request->getBody();
        mb_parse_str($body, $data);

        $content = (!empty($data['content'])) ? $data['content'] : null;

        // transform query and headers
        $query = [];
        foreach ($data as $key => $value) {
            if (in_array($key, ['method', 'content'])) {
                continue;
            }

            // @see https://github.com/adlnet/xAPI-Spec/blob/master/xAPI-Communication.md#requirements
            // The Learning Record Provider MUST include other header parameters not listed above in the HTTP header as normal.
            // we do not know what is a header and what is a param (this is a flaw in ste specs), so we treat everything else as a param

            if (in_array(strtolower($key), $allowedHeaders)) {
                $request = $request->withHeader($key, explode(',', $value));
                continue;
            }

            $query[$key] = $value;
        }

        // set new query
        if ($until_id) {
            $query['until_id'] = $until_id;
        }

        $uri = $request->getUri();
        $uri = $uri->withQuery(http_build_query($query));
        $request = $request->withUri($uri);

        // set new body
        $string = (is_string($content)) ? $content : json_encode($content);

        $stream = fopen('php://memory', 'r+');
        fwrite($stream, $string);
        rewind($stream);

        $body = new Stream($stream);
        $request = $request->withBody($body)->reparseBody();

        // re-assign new request to slim app container
        $this->container->offsetUnset('request');
        $this->container->offsetSet('request', $request);

        $response = $next($request, $response);

        return $response;
    }
}
