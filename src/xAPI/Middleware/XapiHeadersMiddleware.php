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

use Psr\Http\Message\ServerRequestInterface as Request;
use Psr\Http\Message\ResponseInterface as Response;

use API\Config;
use API\Util\Date as DateUtils;

class XapiHeadersMiddleware
{
    /**
     * Adds Xapi headers to response
     * @see https://github.com/adlnet/xAPI-Spec/blob/master/xAPI-Communication.md#12-headers
     *
     * @param  \Psr\Http\Message\ServerRequestInterface $request  PSR7 request
     * @param  \Psr\Http\Message\ResponseInterface      $response PSR7 response
     * @param  callable                                 $next     Next middleware
     *
     * @return \Psr\Http\Message\ResponseInterface
     */
    public function __invoke(Request $request, Response $response, $next)
    {
        $date = DateUtils::dateTimeToISO8601(DateUtils::dateTimeExact());
        $version = Config::get(['xAPI', 'latest_version']);

        $response = $next($request, $response);

        return $response->withHeader('X-Experience-API-Version', $version)
                        ->withHeader('X-Experience-API-Consistent-Through', $date)
                         ;
    }
}
