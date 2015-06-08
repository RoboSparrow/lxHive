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

namespace API\Service;

use API\Service;
use API\Resource;
use Slim\Helper\Set;
use Sokil\Mongo\Cursor;

class Activity extends Service
{
    /**
     * Activities.
     *
     * @var array
     */
    protected $activities;

    /**
     * Cursor.
     *
     * @var cursor
     */
    protected $cursor;

    /**
     * Is this a single activity state fetch?
     *
     * @var bool
     */
    protected $single = false;

    /**
     * Fetches activity profiles according to the given parameters.
     *
     * @param array $request The incoming HTTP request
     *
     * @return array An array of activityProfile objects.
     */
    public function activityGet($request)
    {
        $params = new Set($request->get());

        $collection  = $this->getDocumentManager()->getCollection('activities');
        $cursor      = $collection->find();

        $cursor->where('id', $params->get('activityId'));

        if ($cursor->count() === 0) {
            throw new Exception('Activity does not exist.', Resource::STATUS_NOT_FOUND);
        }

        $this->cursor = $cursor;
        $this->single = true;

        return $this;
    }

    /**
     * Gets the Activities.
     *
     * @return array
     */
    public function getActivities()
    {
        return $this->activityProfiles;
    }

    /**
     * Sets the Activities.
     *
     * @param array $activities the activities
     *
     * @return self
     */
    public function setActivities(array $activities)
    {
        $this->activities = $activities;

        return $this;
    }

    /**
     * Gets the Cursor.
     *
     * @return cursor
     */
    public function getCursor()
    {
        return $this->cursor;
    }

    /**
     * Sets the Cursor.
     *
     * @param cursor $cursor the cursor
     *
     * @return self
     */
    public function setCursor(Cursor $cursor)
    {
        $this->cursor = $cursor;

        return $this;
    }

    /**
     * Gets the Is this a single activity fetch?.
     *
     * @return bool
     */
    public function getSingle()
    {
        return $this->single;
    }

    /**
     * Sets the Is this a single activity fetch?.
     *
     * @param bool $single the is single
     *
     * @return self
     */
    public function setSingle($single)
    {
        $this->single = $single;

        return $this;
    }
}
