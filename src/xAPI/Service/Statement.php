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

use API\Util;
use API\Config;
use API\Service;
use API\Controller;
use API\HttpException as Exception;

class Statement extends Service
{
    /**
     * Fetches statements according to the given parameters.
     *
     * @return array An array of statement objects.
     */
    public function statementGet()
    {
        $parameters = $this->getContainer()->get('parser')->getData()->getQueryParams();

        $statementResult = $this->getStorage()->getStatementStorage()->get($parameters);

        return $statementResult;
    }

    /**
     * Tries to  a statement with a specified statementId.
     *
     * @return array An array of statement documents or a single statement document.
     */
    public function statementPost()
    {
        $this->validateJsonMediaType($this->getContainer()->get('parser')->getData());

        if (count($this->getContainer()->get('parser')->getAttachments()) > 0) {
            $fsAdapter = \API\Util\Filesystem::generateAdapter(Config::get('filesystem'));

            foreach ($this->getContainer()->get('parser')->getAttachments() as $attachment) {
                $attachmentBody = $attachment->getRawPayload();

                $detectedEncoding = mb_detect_encoding($attachmentBody);
                $contentEncoding = isset($attachment->getHeaders()['content-transfer-encoding']) ? $attachment->getHeaders()['content-transfer-encoding'][0] : null;

                if ($detectedEncoding === 'UTF-8' && ($contentEncoding === null || $contentEncoding === 'binary')) {
                    try {
                        $attachmentBody = iconv('UTF-8', 'ISO-8859-1//IGNORE', $attachmentBody);
                    } catch (\Exception $e) {
                        //Use raw file on failed conversion (do nothing!)
                    }
                }

                $this->validateAttachmentRequest($attachment);
                $hash = $attachment->getHeaders()['x-experience-api-hash'][0];
                $contentType = $attachment->getHeaders()['content-type'][0];

                $this->getStorage()->getAttachmentStorage()->store($hash, $contentType);

                $fsAdapter->write($hash, $attachmentBody);
            }
        }

        $body = $this->getContainer()->get('parser')->getData()->getPayload();

        // Multiple statements
        if ($this->areMultipleStatements($body)) {
            $statementResult = $this->getStorage()->getStatementStorage()->insertMultiple($body);
        } else {
            // Single statement
            $statementResult = $this->getStorage()->getStatementStorage()->insertOne($body);
        }

        return $statementResult;
    }

    /**
     * Tries to PUT a statement with a specified statementId.
     *
     * @return
     */
    public function statementPut()
    {
        $this->validateJsonMediaType($this->getContainer()->get('parser')->getData());

        if (count($this->getContainer()->get('parser')->getAttachments()) > 0) {
            $fsAdapter = \API\Util\Filesystem::generateAdapter(Config::get('filesystem'));

            foreach ($this->getContainer()->get('parser')->getAttachments() as $attachment) {
                $attachmentBody = $attachment->getRawPayload();

                $detectedEncoding = mb_detect_encoding($attachmentBody);
                $contentEncoding = isset($attachment->getHeaders()['content-transfer-encoding']) ? $attachment->getHeaders()['content-transfer-encoding'][0] : null;

                if ($detectedEncoding === 'UTF-8' && ($contentEncoding === null || $contentEncoding === 'binary')) {
                    try {
                        $attachmentBody = iconv('UTF-8', 'ISO-8859-1//IGNORE', $attachmentBody);
                    } catch (\Exception $e) {
                        // Use raw file on failed conversion (do nothing!)
                    }
                }

                $hash = $attachment->getHeaders()['X-Experience-API-Hash'];
                $contentType = $part->getHeaders()['Content-Type'];

                $this->getStorage()->getAttachmentStorage()->store($hash, $contentType);

                $fsAdapter->write($hash, $attachmentBody);
            }
        }

        // Single
        $parameters = $this->getContainer()->get('parser')->getData()->getQueryParams();
        $body = $this->getContainer()->get('parser')->getData()->getPayload();

        $statementResult = $this->getStorage()->getStatementStorage()->put($parameters, $body);

        return $statementResult;
    }

    // Quickest solution for validateing 1D vs 2D assoc arrays
    private function areMultipleStatements(&$array)
    {
        // Is this an array of objects or a single object?
        return is_array($array);
    }

    private function validateJsonMediaType($jsonRequest)
    {
        // TODO 0.11.x: Possibly validate this using GraphQL
        $ctype = $jsonRequest->getHeaders()['content-type'][0];
        if (! Util\Parser::isApplicationJson($ctype)) {
            throw new Exception('Media type specified in Content-Type header must be \'application/json\'!', Controller::STATUS_BAD_REQUEST);
        }
    }

    private function validateAttachmentRequest($attachmentRequest)
    {
        // TODO 0.11.x: Possibly validate this using GraphQL
        if (!isset($attachmentRequest->getHeaders()['x-experience-api-hash']) || (empty($attachmentRequest->getHeaders()['x-experience-api-hash']))) {
            throw new Exception('Missing X-Experience-API-Hash on attachment!', Controller::STATUS_BAD_REQUEST);
        }

        if (!isset($attachmentRequest->getHeaders()['content-type']) || (empty($attachmentRequest->getHeaders()['content-type']))) {
            throw new Exception('Missing Content-Type on attachment!', Controller::STATUS_BAD_REQUEST);
        }
    }
}
