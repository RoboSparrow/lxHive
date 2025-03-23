<?php
namespace Tests\API\Service;

use Tests\TestCase;
use Ramsey\Uuid\Uuid;

use API\Document\Statement as StatementDocument;

class StatementTest extends TestCase
{

    public function testIsVoiding()
    {
        $doc = new StatementDocument(null);
        $this->assertFalse($doc->isVoiding(), 'empty');

        // --

        $vid = 'http://adlnet.gov/expapi/verbs/something';
        $sid = Uuid::uuid4()->toString();
        $statement = json_decode('{
            "statement": {
                "actor": {
                    "mbox": "mailto:anonymous@lxhive.com"
                },
                "verb": {
                    "id": "'.$vid.'"
                },
                "object": {
                    "objectType": "StatementRef",
                    "id": "'.$sid.'"
                }
            }
        }');

        $doc = new StatementDocument($statement);
        $this->assertFalse($doc->isVoiding() ,'verb: not voided');

        // --

        $vid = 'https://adlnet.gov/expapi/verbs/voided';
        $statement = json_decode('{
            "statement": {
                "actor": {
                    "mbox": "mailto:anonymous@lxhive.com"
                },
                "verb": {
                    "id": "'.$vid.'"
                },
                "object": {
                    "id": "https://adlnet.gov/expapi/objects/something",
                }
            }
        }');

        $doc = new StatementDocument($statement);
        $this->assertFalse($doc->isVoiding() ,'object: not StatementRef');

        // --

        $vid = 'http://adlnet.gov/expapi/verbs/voided';
        $sid = Uuid::uuid4()->toString();
        $statement = json_decode('{
            "statement": {
                "actor": {
                    "mbox": "mailto:anonymous@lxhive.com"
                },
                "verb": {
                    "id": "'.$vid.'"
                },
                "object": {
                    "objectType": "StatementRef",
                    "id": "'.$sid.'"
                }
            }
        }');

        $doc = new StatementDocument($statement);
        $this->assertTrue($doc->isVoiding() ,'verb: http voided');

        // --

        $vid = 'https://adlnet.gov/expapi/verbs/voided';
        $sid = Uuid::uuid4()->toString();
        $statement = json_decode('{
            "statement": {
                "actor": {
                    "mbox": "mailto:anonymous@lxhive.com"
                },
                "verb": {
                    "id": "'.$vid.'"
                },
                "object": {
                    "objectType": "StatementRef",
                    "id": "'.$sid.'"
                }
            }
        }');

        $doc = new StatementDocument($statement);
        $this->assertTrue($doc->isVoiding() ,'verb: https voided');
    }

}
