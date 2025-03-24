<?php

/*
 * This file is part of lxHive LRS - http://lxhive.org/
 *
 * Copyright (C) 2016 G3 International
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
 * Projected Usage
 *
 *  POST/PUT:
 *  $document = new \API\Document\Statement($parsedJson, \API\DocumentState::UNTRUSTED, '1.0.3');
 *  $statement = $document->validate()->normalize()->document(); // validated and normalized stdClass, ready for storage, changes the state with each chain ['UNTRUSTED->VALIDTED->READY]
 *
 *  REST response
 *  $document = new \API\Document\Statement($mongoDocument, \API\DocumentState::TRUSTED, '1.0.3');
 *  $document->validate()->normalize(); //deals with minor incositencies, will in future also remove meta properties
 *  $json = json_encode($document);
 *
 *  $document will have convenience methods and reveal the convenience methods of subproperties
 *  $document->isReferencing();
 *  $document->actor->isAgent();
 *  $document->object->isSubStatement();
 *
 *  etc..
 */

namespace API\Document;

use Ramsey\Uuid\Uuid;
use Slim\Http\Uri;
use API\Controller;
use API\Document;
use API\DocumentState;
use API\Util;

// TODO 0.11.x: implement normalize, validate, etc. (GraphQL)

class Statement extends Document
{
    public static function fromDatabase($document)
    {
        $documentState = DocumentState::TRUSTED;
        $version = $document->version;
        $statement = new self($document, $documentState, $version);
        return $statement;
    }

    public static function fromApi($document, $version)
    {
        $documentState = DocumentState::UNTRUSTED;
        $data = (object)[];
        $data->statement = $document;
        $statement = new self($data, $documentState, $version);
        return $statement;
    }

    public function validate()
    {
    }

    public function normalize()
    {
        return $this;
    }

    public function getId()
    {
        return $this->data->{'_id'};
    }

    public function setStored($timestamp)
    {
        $this->data->statement->stored = $timestamp;
    }

    public function getStored()
    {
        return $this->data->statement->stored;
    }

    public function setTimestamp($timestamp)
    {
        $this->data->statement->timestamp = $timestamp;
    }

    public function getTimestamp()
    {
        return $this->data->statement->timestamp;
    }

    public function setMongoTimestamp($timestamp)
    {
        $this->data->mongo_timestamp = $timestamp;
    }

    public function getMongoTimestamp()
    {
        return $this->data->mongo_timestamp;
    }

    public function renderExact()
    {
        $this->convertExtensionKeysFromUnicode();

        return $this->data->statement;
    }

    public function renderMeta()
    {
        return $this->data->statement->id;
    }

    /**
     * Sets document property: userId
     * @param string|null $userId
     */
    public function setUserId($userId)
    {
        $this->data->userId = $userId;
    }

    public function renderCanonical()
    {
        throw new \InvalidArgumentException('The \'canonical\' statement format is currently not supported.', Controller::STATUS_NOT_IMPLEMENTED);
    }

    public function setDefaultTimestamp()
    {
        if (!isset($this->data->statement->timestamp) || null ===  $this->data->statement->timestamp) {
            $this->data->statement->timestamp =  $this->data->statement->stored;
        }
    }

    /**
     * Mutate legacy statement.context.contextActivities
     * wraps single activity object (per type) into an array.
     */
    public function legacyContextActivities()
    {
        if (!isset($this->data->statement->context)) {
            return;
        }
        if (!isset($this->data->statement->context->contextActivities)) {
            return;
        }
        foreach ($this->data->statement->context->contextActivities as $type => $value) {
            // We are a bit rat-trapped because statement is an associative array, most efficient way to check if numeric array is here to check for required 'id' property
            if (isset($value->id)) {
                $this->data->statement->context->contextActivities->{$type} = [$value];
            }
        }
    }

    /**
     * Checks whether an statement object is of objectType "Actitivty"
     * @see https://github.com/adlnet/xAPI-Spec/blob/master/xAPI-Data.md#2441-when-the-objecttype-is-activity
     *
     * @return bool
     */
    public function isStatementObjectTypeActivity()
    {
        $object = $this->data->statement->object;
        if (!isset($object->objectType)) {
            return false;
        }
        return ($object->objectType === 'Activity');
    }


    public function getStatementObjectDefinition()
    {
        $object = $this->data->statement->object;
        return (isset($object->definition))? $object->definition : null;
    }

    public function hasVoided()
    {
        $verbId = (isset($this->data->statement->verb->id)) ? $this->data->statement->verb->id : '';
        $verbId = preg_replace('(^https?://)', '', $verbId);

        return ($verbId === 'adlnet.gov/expapi/verbs/voided');
    }

    public function isReferencing()
    {
        if (isset($this->data->statement->object->objectType)
            && ($this->data->statement->object->objectType === 'StatementRef')) {
            return true;
        } else {
            return false;
        }
    }

    public function isVoiding()
    {
        return ($this->hasVoided() && $this->isReferencing());
    }

    public function getReferencedStatementId()
    {
        $referencedId = $this->data->statement->object->id;

        return $referencedId;
    }

    public function fixAttachmentLinks($baseUrl)
    {
        if (isset($this->data->statement->attachments)) {
            if (!is_array($this->data->statement->attachments)) {
                return;
            }
            foreach ($this->data->statement->attachments as &$attachment) {
                if (!isset($attachment->fileUrl)) {
                    $uri = Uri::createFromString($baseUrl);
                    $uri = $uri->withQuery('sha2='.$attachment->sha2);
                    $attachment->fileUrl = (string) $uri;
                }
            }
        }
    }

    public function convertExtensionKeysToUnicode()
    {
        if (isset($this->data->statement->context->extensions)) {
            $this->extensionKeysToUnicode($this->data->statement->context->extensions);
        }

        if (isset($this->data->statement->result->extensions)) {
            $this->extensionKeysToUnicode($this->data->statement->result->extensions);
        }

        if (isset($this->data->statement->object->definition->extensions)) {
            $this->extensionKeysToUnicode($this->data->statement->object->definition->extensions);
        }

        if (isset($this->data->statement->context->contextActivities)) {
            $ca = $this->data->statement->context->contextActivities;
            foreach($ca as $section) {
                foreach($section as $activity) {
                    if(isset($activity->definition->extensions)) {
                        $this->extensionKeysToUnicode($activity->definition->extensions);
                    }
                }
            }
        }
    }

    public function convertExtensionKeysFromUnicode()
    {
        if (isset($this->data->statement->context->extensions)) {
            $this->extensionKeysFromUnicode($this->data->statement->context->extensions);
        }

        if (isset($this->data->statement->result->extensions)) {
            $this->extensionKeysFromUnicode($this->data->statement->result->extensions);
        }

        if (isset($this->data->statement->object->definition->extensions)) {
            $this->extensionKeysFromUnicode($this->data->statement->object->definition->extensions);
        }

        if (isset($this->data->statement->context->contextActivities)) {
            $ca = $this->data->statement->context->contextActivities;
            foreach($ca as $section) {
                foreach($section as $activity) {
                    if(isset($activity->definition->extensions)) {
                        $this->extensionKeysFromUnicode($activity->definition->extensions);
                    }
                }
            }
        }
    }

    private function extensionKeysFromUnicode($obj) {
        foreach ($obj as $key => $val) {
            $new = str_replace('[dot]', '.', $key);
            $new = str_replace('\uFF0E', '.', $new); // legacy
            if($new != $key) {
                $obj->{$new} = $val;
                unset($obj->{$key});
            }
        }
    }

    private function extensionKeysToUnicode($obj) {
        foreach ($obj as $key => $val) {
            $new = str_replace('.', '[dot]', $key);
            $new = str_replace('.', '\uFF0E', $new); // legacy
            if($new != $key) {
                $obj->{$new} = $val;
                unset($obj->{$key});
            }
        }
    }

    public function normalizeExistingIds()
    {
        if (!empty($this->data->statement->id) && $this->data->statement->id !== null) {
            $this->data->statement->id = Util\xAPI::normalizeUuid($this->data->statement->id);
        }

        if ($this->isReferencing()) {
            $this->data->statement->object->id = Util\xAPI::normalizeUuid($this->data->statement->object->id);
        }

        if (!empty($this->data->statement->context->registration) && $this->data->statement->context->registration !== null) {
            $this->data->statement->context->registration = Util\xAPI::normalizeUuid($this->data->statement->context->registration);
        }
    }

    public function setDefaultId()
    {
        // If no ID has been set, set it
        if (empty($this->data->statement->id) || $this->data->statement->id === null) {
            $this->data->statement->id = Uuid::uuid4()->toString();
        }
    }

    public function renderIds()
    {
        $this->convertExtensionKeysFromUnicode();
        $statement = $this->data->statement;

        if (isset($statement->actor->objectType) && $statement->actor->objectType === 'Group') {
            $statement->actor->member = array_map(function ($singleMember) {
                return $this->simplifyObject($singleMember);
            }, $statement->actor->member);
        } else {
            $statement->actor = $this->simplifyObject($statement->actor);
        }

        if (isset($statement->object->objectType) && $statement->object->objectType === 'SubStatement') {
            if ($statement->object->actor->objectType === 'Group') {
                $statement->object->actor->member = array_map(function ($singleMember) {
                    return $this->simplifyObject($singleMember);
                }, $statement->object->actor->member);
            } else {
                $statement->object->actor = $this->simplifyObject($statement->object->actor);
            }
            $statement->object->object = $this->simplifyObject($statement->object->object);
        } else {
            $statement->object = $this->simplifyObject($statement->object);
        }

        return $statement;
    }

    private function simplifyObject($object)
    {
        if (isset($object->mbox)) {
            $uniqueIdentifier = 'mbox';
        } elseif (isset($object->mbox_sha1sum)) {
            $uniqueIdentifier = 'mbox_sha1sum';
        } elseif (isset($object->openid)) {
            $uniqueIdentifier = 'openid';
        } elseif (isset($object->account)) {
            $uniqueIdentifier = 'account';
        } elseif (isset($object->id)) {
            $uniqueIdentifier = 'id';
        }
        $object = [
            'objectType' => $object->objectType,
            $uniqueIdentifier => $object->{$uniqueIdentifier}
        ];
        return $object;
    }

    public function extractActivities()
    {
        $activities = [];
        // Main activity
        if ((isset($this->data->statement->object->objectType) && $this->data->statement->object->objectType === 'Activity') || !isset($this->data->statement->object->objectType)) {
            $activity = $this->data->statement->object;
            $activities[] = $activity;
        }
        return $activities;
    }
}
