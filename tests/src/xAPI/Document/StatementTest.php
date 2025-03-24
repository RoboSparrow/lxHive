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

    public function testIsStatementObjectTypeActivity()
    {
        $statement = json_decode('{
            "statement": {
                "actor": {
                    "mbox": "mailto:anonymous@lxhive.com"
                },
                "verb": {
                    "id": "http://adlnet.gov/expapi/verbs/completed"
                },
                "object": {
                    "objectType": "Activity",
                    "id": "http://www.example.com/courses/34534"
                }
            }
        }');

        $doc = new StatementDocument($statement);
        $this->assertTrue($doc->isStatementObjectTypeActivity() , 'object: is activity');

        // --

        $statement = json_decode('{
            "statement": {
                "actor": {
                    "mbox": "mailto:anonymous@lxhive.com"
                },
                "verb": {
                    "id": "http://adlnet.gov/expapi/verbs/completed"
                },
                "object": {
                    "objectType": "StatementRef",
                    "id": "6690e6c9-3ef0-4ed3-8b37-7f3964730bee"
                }
            }
        }');

        $doc = new StatementDocument($statement);
        $this->assertFalse($doc->isStatementObjectTypeActivity() , 'object: is activity');
    }

    public function testObjectActivityDefinitions()
    {
        $definition = '{
            "description": {
                "en-US": "Does the xAPI include the concept of statements?"
            },
            "type": "http://adlnet.gov/expapi/activities/cmi.interaction",
            "interactionType": "true-false",
            "correctResponsesPattern": [
                "true"
            ]
        }';

        $statement = json_decode('{
            "statement": {
                "actor": {
                    "mbox": "mailto:anonymous@lxhive.com"
                },
                "verb": {
                    "id": "http://adlnet.gov/expapi/verbs/completed"
                },
                "object": {
                    "objectType": "Activity",
                    "id": "http://www.example.com/courses/34534",
                    "definition": '. $definition .'
                }
            }
        }');

        $doc = new StatementDocument($statement);
        $this->assertTrue($doc->isStatementObjectTypeActivity() , 'object: is activity');
        $this->assertTrue($doc->hasStatementObjectActivityDefinition() , 'object: has activity defintion');

        // --

        $definition = '{
            "description": {
                "en-US": "Does the xAPI include the concept of statements?"
            },
            "type": "http://adlnet.gov/expapi/activities/cmi.interaction",
            "interactionType": "choices",
            "choices": [
                {"id": "yes"}, {"id": "no"}
            ]
        }';

        $statement = json_decode('{
            "statement": {
                "actor": {
                    "mbox": "mailto:anonymous@lxhive.com"
                },
                "verb": {
                    "id": "http://adlnet.gov/expapi/verbs/completed"
                },
                "object": {
                    "objectType": "Activity",
                    "id": "http://www.example.com/courses/34534",
                    "definition": '. $definition .'
                }
            }
        }');

        $doc = new StatementDocument($statement);
        $this->assertTrue($doc->isStatementObjectTypeActivity() , 'object: is activity');
        $this->assertTrue($doc->hasStatementObjectActivityDefinition() , 'object: has activity defintion (choices)');

        // --

        $definition = '{
            "description": {
                "en-US": "Does the xAPI include the concept of statements?"
            },
            "type": "http://not/an/activity/definition"
        }';

        $statement = json_decode('{
            "statement": {
                "actor": {
                    "mbox": "mailto:anonymous@lxhive.com"
                },
                "verb": {
                    "id": "http://adlnet.gov/expapi/verbs/completed"
                },
                "object": {
                    "objectType": "Activity",
                    "id": "http://www.example.com/courses/34534",
                    "definition": '. $definition .'
                }
            }
        }');

        $doc = new StatementDocument($statement);
        $this->assertTrue($doc->isStatementObjectTypeActivity() , 'object: is activity');
        $this->assertFalse($doc->hasStatementObjectActivityDefinition() , 'object: object.definition is not an activity defintion ');

        // --

        $definition = '{
            "description": {
                "en-US": "Does the xAPI include the concept of statements?"
            },
            "type": "http://adlnet.gov/expapi/activities/cmi.interaction",
            "interactionType": "true-false",
            "correctResponsesPattern": [
                "true"
            ]
        }';

        $statement = json_decode('{
            "statement": {
                "actor": {
                    "mbox": "mailto:anonymous@lxhive.com"
                },
                "verb": {
                    "id": "http://adlnet.gov/expapi/verbs/completed"
                },
                "object": {
                    "id": "http://www.example.com/courses/34534",
                }
            }
        }');

        $doc = new StatementDocument($statement);
        $this->assertFalse($doc->isStatementObjectTypeActivity() , 'object: is activity');
        $this->assertFalse($doc->hasStatementObjectActivityDefinition() , 'object: has activity defintion but is not of type "Activity"');
    }

}
