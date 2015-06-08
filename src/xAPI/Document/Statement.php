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

namespace API\Document;

use Sokil\Mongo\Document;
use JsonSerializable;
use Rhumsaa\Uuid\Uuid;
use League\Url\Url;
use API\Resource;

class Statement extends Document implements JsonSerializable
{
    protected $_data = [
        'statement' => [
            'authority' => null,
            'id'        => null,
            'actor'     => null,
            'verb'      => null,
            'object'    => null,
            'timestamp' => null,
            'stored'    => null,
        ],
        'mongo_timestamp' => null,
        'voided'          => false,
    ];

    public function setStatement($statement)
    {
        $this->_data['statement'] = $statement;
    }

    public function getStatement()
    {
        return $this->_data['statement'];
    }

    public function setStored($timestamp)
    {
        $this->_data['statement']['stored'] = $timestamp;
    }

    public function getStored()
    {
        return $this->_data['statement']['stored'];
    }

    public function setTimestamp($timestamp)
    {
        $this->_data['statement']['timestamp'] = $timestamp;
    }

    public function getTimestamp()
    {
        return $this->_data['statement']['timestamp'];
    }

    public function setMongoTimestamp($timestamp)
    {
        $this->_data['mongo_timestamp'] = $timestamp;
    }

    public function getMongoTimestamp()
    {
        return $this->_data['mongo_timestamp'];
    }

    public function setDefaultTimestamp()
    {
        if (!isset($this->_data['statement']['timestamp']) || null === $this->_data['statement']['timestamp']) {
            $this->_data['statement']['timestamp'] = $this->_data['statement']['stored'];
        }
    }

    public function isVoiding()
    {
        if (isset($this->_data['statement']['verb']['id'])
            && ($this->_data['statement']['verb']['id'] === 'http://adlnet.gov/expapi/verbs/voided')
            && isset($this->_data['statement']['object']['objectType'])
            && ($this->_data['statement']['object']['objectType'] = 'StatementRef')
        ) {
            return true;
        } else {
            return false;
        }
    }

    public function getReferencedStatement()
    {
        $referencedId = $this->_data['statement']['object']['id'];

        $referencedStatement = $this->getCollection()->find()->where('statement.id', $referencedId)->current();

        if (null === $referencedStatement) {
            throw new \InvalidArgumentException('Referenced statement does not exist!', Resource::STATUS_BAD_REQUEST);
        }

        return $referencedStatement;
    }

    public function fixAttachmentLinks($baseUrl)
    {
        if (isset($this->_data['statement']['attachments'])) {
            foreach ($this->_data['statement']['attachments'] as &$attachment) {
                if (!isset($attachment['fileUrl'])) {
                    $url = Url::createFromUrl($baseUrl);
                    $url->getQuery()->modify(['sha2' => $attachment['sha2']]);
                    $attachment['fileUrl'] =  $url->__toString();
                }
            }
        }
    }

    public function extractActivities()
    {
        $activities = [];
        // Main activity
        if ((isset($this->_data['statement']['object']['objectType']) && $this->_data['statement']['object']['objectType'] === 'Activity') || !isset($this->_data['statement']['object']['objectType'])) {
            $activities[] = $this->_data['statement']['object'];
        }

        /* Commented out for now due to performance reasons
        // Context activities
        if (isset($this->_data['statement']['context']['contextActivities'])) {
            if (isset($this->_data['statement']['context']['contextActivities']['parent'])) {
                foreach ($this->_data['statement']['context']['contextActivities']['parent'] as $singleActivity) {
                    $activities[] = $singleActivity;
                }
            }
            if (isset($this->_data['statement']['context']['contextActivities']['category'])) {
                foreach ($this->_data['statement']['context']['contextActivities']['category'] as $singleActivity) {
                    $activities[] = $singleActivity;
                }
            }
            if (isset($this->_data['statement']['context']['contextActivities']['grouping'])) {
                foreach ($this->_data['statement']['context']['contextActivities']['grouping'] as $singleActivity) {
                    $activities[] = $singleActivity;
                }
            }
            if (isset($this->_data['statement']['context']['contextActivities']['other'])) {
                foreach ($this->_data['statement']['context']['contextActivities']['other'] as $singleActivity) {
                    $activities[] = $singleActivity;
                }
            }
        }
        // SubStatement activity check
        if (isset($this->_data['statement']['object']['objectType']) && $this->_data['statement']['object']['objectType'] === 'SubStatement') {
            if ((isset($this->_data['statement']['object']['object']['objectType']) && $this->_data['statement']['object']['object']['objectType'] === 'Activity') || !isset($this->_data['statement']['object']['object']['objectType']) {
                $activities[] = $this->_data['statement']['object']['object'];
            }
        }*/

        return $activities;
    }

    public function jsonSerialize()
    {
        return $this->getStatement();
    }

    public function setDefaultId()
    {
        // If no ID has been set, set it
        if (empty($this->_data['statement']['id']) || $this->_data['statement']['id'] === null) {
            $this->_data['statement'] = ['id' => Uuid::uuid4()->toString()] + $this->_data['statement'];
        }
    }

    public function renderExact()
    {
        return $this->getStatement();
    }

    public function renderMeta()
    {
        return $this->getStatement()['id'];
    }
}
