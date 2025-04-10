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

namespace API\Service;

use API\Service;
use API\Util\Collection;

class AgentProfile extends Service
{
    /**
     * Fetches agent profiles according to the given parameters.
     *
     * @return array An array of agentProfile objects.
     */
    public function agentProfileGet()
    {
        $request = $this->getContainer()->get('parser')->getData();
        $params = new Collection($request->getQueryParams());

        $documentResult = $this->getStorage()->getAgentProfileStorage()->getFiltered($params);

        return $documentResult;
    }

    /**
     * Tries to save (merge) an agentProfile.
     */
    public function agentProfilePost()
    {
        $request = $this->getContainer()->get('parser')->getData();
        $params = new Collection($request->getQueryParams());

        // Validation has been completed already - everything is assumed to be valid
        $rawBody = $request->getRawPayload();

        $params->set('headers', $request->getHeaders());

        $documentResult = $this->getStorage()->getAgentProfileStorage()->post($params, $rawBody);

        return $documentResult;
    }

    /**
     * Tries to PUT (replace) an agentProfile.
     *
     * @return
     */
    public function agentProfilePut()
    {
        $request = $this->getContainer()->get('parser')->getData();
        $params = new Collection($request->getQueryParams());

        $params->set('headers', $request->getHeaders());

        $rawBody = $request->getRawPayload();

        $documentResult = $this->getStorage()->getAgentProfileStorage()->put($params, $rawBody);

        return $documentResult;
    }

    /**
     * Fetches activity states according to the given parameters.
     *
     * @return self Nothing.
     */
    public function agentProfileDelete()
    {
        $request = $this->getContainer()->get('parser')->getData();
        $params = new Collection($request->getQueryParams());

        $params->set('headers', $request->getHeaders());

        $deletionResult = $this->getStorage()->getAgentProfileStorage()->delete($params);

        return $deletionResult;
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
