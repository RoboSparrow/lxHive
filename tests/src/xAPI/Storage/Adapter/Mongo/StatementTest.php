<?php
namespace Tests\API\Storage\Adapter\Mongo;

use Tests\MongoTestCase;

use API\Bootstrap;
use API\Storage\Adapter\Mongo\Statement;
use API\Document\Statement as StatementDocument;

class StatementTest extends MongoTestCase
{
    private $modelection;

    public function setUp(): void
    {
        $this->collection = Statement::COLLECTION_NAME;
    }

    public function testGetIndexes()
    {
        $model = new Statement(Bootstrap::getContainer());
        $indexes = $model->getIndexes();

        $this->assertTrue(is_array($indexes));
    }

    /**
     * @depends testGetIndexes
     */
    public function testInstall()
    {
        $this->dropCollection($this->collection);

        $model = new Statement(Bootstrap::getContainer());
        $model->install();
        // has passed without exception

        $indexes = $this->command([
            'listIndexes' => $this->collection
        ])->toArray();

        $configured = array_map(function($i) {
            return $i['name'];
        }, $model->getIndexes());

        $installed = array_map(function($i) {
            return $i->name;
        }, $indexes);

        foreach ($configured as $name) {
            $this->assertContains($name, $installed);
        }
    }
}
