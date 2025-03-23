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
        $this->assertFalse($doc->hasVoided(), 'empty');
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
        $this->assertFalse($doc->hasVoided(), 'verb: not voided');
        $this->assertFalse($doc->isVoiding(), 'verb: not voided');

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
                    "id": "http://lxhive.com/activities/something"
                }
            }
        }');

        $doc = new StatementDocument($statement);
        $this->assertTrue($doc->hasVoided(), 'object is no no StatementRef'); // !!!
        $this->assertFalse($doc->isVoiding(), 'object is no no StatementRef');

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
        $this->assertTrue($doc->hasVoided() ,'verb: http voided');
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
        $this->assertTrue($doc->hasVoided() ,'verb: https voided');
        $this->assertTrue($doc->isVoiding() ,'verb: https voided');
    }

}
