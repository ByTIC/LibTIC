<?php

namespace Nip\Tests\Records;

use Mockery as m;
use Nip\Database\Connection;
use Nip\Records\RecordManager as Records;
use Nip\Request;

class RecordsTest extends \Codeception\TestCase\Test
{
    /**
     * @var \UnitTester
     */
    protected $tester;

    /**
     * @var Records
     */
    protected $_object;

    public function testSetModel()
    {
        $this->_object->setModel('Row');
        self::assertEquals($this->_object->getModel(), 'Row');

        $this->_object->setModel('Row2');
        self::assertEquals($this->_object->getModel(), 'Row2');
    }

    public function testGetFullNameTable()
    {
        self::assertEquals('pages', $this->_object->getFullNameTable());

        $this->_object->getDB()->setDatabase('database_name');
        self::assertEquals('database_name.pages', $this->_object->getFullNameTable());
    }

    // tests

    public function testGenerateModelClass()
    {
        self::assertEquals($this->_object->generateModelClass('Notifications\Table'), 'Notifications\Row');
        self::assertEquals($this->_object->generateModelClass('Notifications_Tables'), 'Notifications_Table');
        self::assertEquals($this->_object->generateModelClass('Notifications'), 'Notification');
        self::assertEquals($this->_object->generateModelClass('Persons'), 'Person');
    }

    /**
     * @return array
     */
    public function providerGetController()
    {
        return [
            ["notifications-tables", "Notifications_Tables"],
            ["notifications-tables", "Notifications\\Tables\\Tables"],
            ["notifications-tables", "App\\Models\\Notifications\\Tables\\Tables"],
        ];
    }

    /**
     * @dataProvider providerGetController
     * @param $controller
     * @param $class
     */
    public function testGetController($controller, $class)
    {
        /** @var Records $records */
        $records = new Records();
        $records->setClassName($class);

        self::assertEquals($controller, $records->getController());
    }

    public function testGetRelationClass()
    {
        self::assertEquals('Nip\Records\Relations\BelongsTo', $this->_object->getRelationClass('BelongsTo'));
        self::assertEquals('Nip\Records\Relations\BelongsTo', $this->_object->getRelationClass('belongsTo'));

        self::assertEquals('Nip\Records\Relations\HasMany', $this->_object->getRelationClass('HasMany'));
        self::assertEquals('Nip\Records\Relations\HasMany', $this->_object->getRelationClass('hasMany'));

        self::assertEquals('Nip\Records\Relations\HasAndBelongsToMany',
            $this->_object->getRelationClass('HasAndBelongsToMany'));
        self::assertEquals('Nip\Records\Relations\HasAndBelongsToMany',
            $this->_object->getRelationClass('hasAndBelongsToMany'));
    }

    public function testNewRelation()
    {
        self::assertInstanceOf('Nip\Records\Relations\BelongsTo', $this->_object->newRelation('BelongsTo'));
        self::assertInstanceOf('Nip\Records\Relations\BelongsTo', $this->_object->newRelation('belongsTo'));

        self::assertInstanceOf('Nip\Records\Relations\HasMany', $this->_object->newRelation('HasMany'));
        self::assertInstanceOf('Nip\Records\Relations\HasMany', $this->_object->newRelation('hasMany'));

        self::assertInstanceOf('Nip\Records\Relations\HasAndBelongsToMany',
            $this->_object->newRelation('HasAndBelongsToMany'));
        self::assertInstanceOf('Nip\Records\Relations\HasAndBelongsToMany',
            $this->_object->newRelation('hasAndBelongsToMany'));
    }

    public function testInitRelationsFromArrayBelongsToSimple()
    {
        $users = m::namedMock('Users', 'Records')->shouldDeferMissing()
            ->shouldReceive('instance')->andReturnSelf()
            ->getMock();
        $users->setPrimaryFK('id_user');

        m::namedMock('User', 'Record');
        m::namedMock('Articles', 'Records');

        $this->_object->setPrimaryFK('id_object');

        $this->_object->initRelationsFromArray('belongsTo', ['User']);
        $this->_testInitRelationsFromArrayBelongsToUser('User');

        $this->_object->initRelationsFromArray('belongsTo', [
            'UserName' => ['with' => $users],
        ]);
        $this->_testInitRelationsFromArrayBelongsToUser('UserName');

        self::assertSame($users, $this->_object->getRelation('User')->getWith());
    }

    protected function _testInitRelationsFromArrayBelongsToUser($name)
    {
        self::assertTrue($this->_object->hasRelation($name));
        self::assertInstanceOf('Nip\Records\Relations\BelongsTo', $this->_object->getRelation($name));
        self::assertInstanceOf('Nip\Records\RecordManager', $this->_object->getRelation($name)->getWith());
        self::assertEquals($this->_object->getRelation($name)->getWith()->getPrimaryFK(),
            $this->_object->getRelation($name)->getFK());
    }

    public function testNewCollection()
    {
        $collection = $this->_object->newCollection();
        self::assertInstanceOf('Nip\Records\Collections\Collection', $collection);
        self::assertSame($this->_object, $collection->getManager());
    }

    public function testRequestFilters()
    {
        $request = new Request();
        $params = [
            'title' => 'Test title',
            'name' => 'Test name',
        ];
        $request->query->add($params);

        $this->_object->getFilterManager()->addFilter(
            $this->_object->getFilterManager()->newFilter('Column\BasicFilter')
                ->setField('title')
        );

        $this->_object->getFilterManager()->addFilter(
            $this->_object->getFilterManager()->newFilter('Column\BasicFilter')
                ->setField('name')
        );

        $filtersArray = $this->_object->requestFilters($request);
        self::assertSame($filtersArray, $params);
    }

    protected function _before()
    {
        $wrapper = new Connection();

        $this->_object = new Records();
        $this->_object->setDB($wrapper);
        $this->_object->setTable('pages');
    }

    protected function _after()
    {
    }
}
