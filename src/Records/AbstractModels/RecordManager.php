<?php

namespace Nip\Records\AbstractModels;

use Nip\Database\Connection;
use Nip\Database\Query\AbstractQuery as Query;
use Nip\Database\Query\Delete as DeleteQuery;
use Nip\Database\Query\Insert as InsertQuery;
use Nip\Database\Query\Select as SelectQuery;
use Nip\Database\Query\Update as UpdateQuery;
use Nip\Database\Result;
use Nip\HelperBroker;
use Nip\Paginator;
use Nip\Records\Collections\Collection as RecordCollection;
use Nip\Records\Filters\FilterManager;
use Nip\Records\Relations\Relation;
use Nip\Request;

/**
 * Class Table
 * @package Nip\Records\_Abstract
 *
 * @method \Nip_Helper_Url Url()
 */
abstract class RecordManager
{

    /**
     * @var Connection
     */
    protected $db = null;

    protected $_collectionClass = '\Nip\Records\Collections\Collection';

    protected $_helpers = [];

    protected $table = null;
    protected $tableStructure = null;
    protected $primaryKey = null;
    protected $fields = null;
    protected $uniqueFields = null;
    protected $foreignKey = null;

    protected $urlPK = null;

    protected $model = null;
    protected $controller = null;

    protected $registry = null;

    protected $filterManager = null;


    /**
     * The loaded relationships for the model table.
     * @var array
     */
    protected $relations = null;

    protected $_belongsTo = [];
    protected $_hasMany = [];
    protected $_hasAndBelongsToMany = [];

    protected $relationTypes = ['belongsTo', 'hasMany', 'hasAndBelongsToMany'];

    /**
     * @return string
     */
    public function getModelNamespace()
    {
        return $this->getRootNamespace() . $this->getModelNamespacePath();
    }

    /**
     * @return string
     */
    public static function getRootNamespace()
    {
        return 'App\Models\\';
    }

    public function getModelNamespacePath()
    {
        return inflector()->classify($this->getController()) . '\\';
    }

    /**
     * @return string
     */
    public function getController()
    {
        if ($this->controller == null) {
            $this->inflectController();
        }

        return $this->controller;
    }

    protected function inflectController()
    {
        $class = get_class($this);
        if ($this->controller == null) {
            $this->controller = inflector()->unclassify($class);
        }
    }

    /**
     * Overloads findByRecord, findByField, deleteByRecord, deleteByField, countByRecord, countByField
     *
     * @example findByCategory(Category $item)
     * @example deleteByProduct(Product $item)
     * @example findByIdUser(2)
     * @example deleteByTitle(array('Lorem ipsum', 'like'))
     * @example countByIdCategory(1)
     *
     * @param string $name
     * @param array $arguments
     *
     * @return mixed
     */
    public function __call($name, $arguments)
    {
        $return = $this->isCallDatabaseOperation($name, $arguments);
        if ($return !== null) {
            return $return;
        }

        if ($return = $this->isCallUrl($name, $arguments)) {
            return $return;
        }

        if ($name === ucfirst($name)) {
            return $this->getHelper($name);
        }

        trigger_error("Call to undefined method $name", E_USER_ERROR);

        return $this;
    }

    /**
     * @param $name
     * @param $arguments
     * @return RecordCollection|null
     */
    protected function isCallDatabaseOperation($name, $arguments)
    {
        $operations = ["find", "delete", "count"];
        foreach ($operations as $operation) {
            if (strpos($name, $operation . "By") !== false || strpos($name, $operation . "OneBy") !== false) {
                $params = [];
                if (count($arguments) > 1) {
                    $params = end($arguments);
                }

                $match = str_replace([$operation . "By", $operation . "OneBy"], "", $name);
                $field = inflector()->underscore($match);

                if ($field == $this->getPrimaryKey()) {
                    return $this->findByPrimary($arguments[0]);
                }

                $params['where'][] = ["$field " . (is_array($arguments[0]) ? "IN" : "=") . " ?", $arguments[0]];

                $operation = str_replace($match, "", $name) . "Params";

                return $this->$operation($params);
            }
        }

        return null;
    }

    /**
     * @return string
     */
    public function getPrimaryKey()
    {
        if ($this->primaryKey === null) {
            $this->initPrimaryKey();
        }

        return $this->primaryKey;
    }

    protected function initPrimaryKey()
    {
        $structure = $this->getTableStructure();
        $this->primaryKey = $structure['indexes']['PRIMARY']['fields'];
        if (count($this->primaryKey) == 1) {
            $this->primaryKey = reset($this->primaryKey);
        }
    }

    /**
     * @return mixed
     */
    protected function getTableStructure()
    {
        if ($this->tableStructure == null) {
            $this->initTableStructure();
        }

        return $this->tableStructure;
    }

    protected function initTableStructure()
    {
        $this->tableStructure = $this->getDB()->getMetadata()->describeTable($this->getTable());
    }

    /**
     * @return Connection
     */
    public function getDB()
    {
        if ($this->db == null) {
            $this->initDB();
        }

        $this->checkDB();

        return $this->db;
    }

    /**
     * @param Connection $db
     * @return $this
     */
    public function setDB($db)
    {
        $this->db = $db;

        return $this;
    }

    protected function initDB()
    {
        $this->setDB($this->newDbConnection());
    }

    /**
     * @return Connection
     */
    protected function newDbConnection()
    {
        return db();
    }

    public function checkDB()
    {
        if (!$this->hasDB()) {
            trigger_error("Database connection missing for [".get_class($this)."]", E_USER_ERROR);
        }
    }

    /**
     * @return bool
     */
    public function hasDB()
    {
        return $this->db instanceof Connection;
    }

    /**
     * @return string
     */
    public function getTable()
    {
        if ($this->table === null) {
            $this->initTable();
        }

        return $this->table;
    }

    /**
     * @param null $table
     */
    public function setTable($table)
    {
        $this->table = $table;
    }

    public function initTable()
    {
        $this->table = $this->getController();
    }

    /**
     * When searching by primary key, look for items in current registry before
     * fetching them from the database
     *
     * @param array $pk_list
     * @return RecordCollection
     */
    public function findByPrimary($pk_list = [])
    {
        $pk = $this->getPrimaryKey();
        $return = $this->newCollection();

        if ($pk_list) {
            $pk_list = array_unique($pk_list);
            foreach ($pk_list as $key => $value) {
                $item = $this->getRegistry()->get($value);
                if ($item) {
                    unset($pk_list[$key]);
                    $return[$item->$pk] = $item;
                }
            }
            if ($pk_list) {
                $query = $this->paramsToQuery();
                $query->where("$pk IN ?", $pk_list);
                $items = $this->findByQuery($query);

                if (count($items)) {
                    foreach ($items as $item) {
                        $this->getRegistry()->set($item->$pk, $item);
                        $return[$item->$pk] = $item;
                    }
                }
            }
        }

        return $return;
    }

    /**
     * @return RecordCollection
     */
    public function newCollection()
    {
        $class = $this->getCollectionClass();
        /** @var RecordCollection $collection */
        $collection = new $class();
        $collection->setManager($this);

        return $collection;
    }

    /**
     * @return string
     */
    public function getCollectionClass()
    {
        return $this->_collectionClass;
    }

    /**
     * @return \Nip_Registry
     */
    public function getRegistry()
    {
        if (!$this->registry) {
            $this->registry = new \Nip_Registry();
        }

        return $this->registry;
    }

    /**
     * @param array $params
     * @return SelectQuery
     */
    public function paramsToQuery($params = [])
    {
        $this->injectParams($params);

        $query = $this->newQuery('select');
        $query->addParams($params);

        return $query;
    }

    /**
     * @param array $params
     */
    protected function injectParams(&$params = [])
    {
    }

    /**
     * Factory
     * @param string $type
     * @return Query
     */
    public function newQuery($type = 'select')
    {
        $query = $this->getDB()->newQuery($type);
        $query->cols("`" . $this->getTable() . "`.*");
        $query->from($this->getFullNameTable());
        $query->table($this->getTable());

        return $query;
    }

    /**
     * @return string
     */
    public function getFullNameTable()
    {
        $database = $this->getDB()->getDatabase();

        return $database ? $database . '.' . $this->getTable() : $this->getTable();
    }

    /**
     * @param $query
     * @param array $params
     * @return RecordCollection
     */
    public function findByQuery($query, $params = [])
    {
        $return = $this->newCollection();

        $results = $this->getDB()->execute($query);
        if ($results->numRows() > 0) {
            $pk = $this->getPrimaryKey();
            while ($row = $results->fetchResult()) {
                $item = $this->getNew($row);
                if (is_string($pk)) {
                    $this->getRegistry()->set($item->$pk, $item);
                }
                if ($params['indexKey']) {
                    $return->add($item, $params['indexKey']);
                } else {
                    $return->add($item);
                }
            }
        }

        return $return;
    }

    /**
     * Factory
     *
     * @return Record
     * @param array $data [optional]
     */
    public function getNew($data = [])
    {

        $pk = $this->getPrimaryKey();
        if (is_string($pk) && $this->getRegistry()->get($data[$pk])) {
            $return = $this->getRegistry()->get($data[$pk]);
            $return->writeData($data);
            $return->writeDBData($data);

            return $return;
        }

        $record = $this->getNewRecordFromDB($data);

        return $record;
    }

    /**
     * @param array $data
     * @return Record
     */
    public function getNewRecordFromDB($data = [])
    {
        $record = $this->getNewRecord($data);
        $record->writeDBData($data);

        return $record;
    }

    /**
     * @param array $data
     * @return Record
     */
    public function getNewRecord($data = [])
    {
        $model = $this->getModel();
        /** @var Record $record */
        $record = new $model();
        $record->setManager($this);
        $record->writeData($data);

        return $record;
    }

    /**
     * @return string
     */
    public function getModel()
    {
        if ($this->model == null) {
            $this->inflectModel();
        }

        return $this->model;
    }

    /**
     * @param null $model
     */
    public function setModel($model)
    {
        $this->model = $model;
    }

    protected function inflectModel()
    {
        $class = get_class($this);
        if ($this->model == null) {
            $this->model = $this->generateModelClass($class);
        }
    }

    /**
     * @param null $class
     * @return string
     */
    public function generateModelClass($class = null)
    {
        $class = $class ? $class : get_class($this);

        if (strpos($class, '\\')) {
            $nsParts = explode('\\', $class);
            $class = array_pop($nsParts);

            if ($class == 'Table') {
                $class = 'Row';
            } else {
                $class = ucfirst(inflector()->singularize($class));
            }

            return implode($nsParts, '\\') . '\\' . $class;
        }

        return ucfirst(inflector()->singularize($class));
    }

    /**
     * @param $name
     * @param $arguments
     * @return bool
     */
    protected function isCallUrl($name, $arguments)
    {
        if (substr($name, 0, 3) == "get" || substr($name, -3) == "URL") {
            $action = substr($name, 3, -3);
            $params = $arguments[0];

            $controller = $this->getController();

            if (substr($action, 0, 5) == 'Async') {
                $controller = 'async-' . $controller;
                $action = substr($action, 5);
            }

            if (substr($action, 0, 5) == 'Modal') {
                $controller = 'modal-' . $controller;
                $action = substr($action, 5);
            }

            $params['action'] = (!empty($action)) ? $action : 'index';
            $params['controller'] = $controller;
            if ($arguments[1]) {
                $params['module'] = $arguments[1];
            }

            return $this->_getURL($params);
        }

        return false;
    }

    /**
     * @param array $params
     * @return mixed
     */
    protected function _getURL($params = [])
    {
        $params['action'] = inflector()->unclassify($params['action']);
        $params['action'] = ($params['action'] == 'index') ? false : $params['action'];
        $params['controller'] = $params['controller'] ? $params['controller'] : $this->getController();
        $params['module'] = $params['module'] ? $params['module'] : Request::instance()->getModuleName();

        $routeName = $params['module'] . '.' . $params['controller'] . '.' . $params['action'];
        if ($this->Url()->getRouter()->hasRoute($routeName)) {
            unset($params['module'], $params['controller'], $params['action']);
        } else {
            $routeName = $params['module'] . '.default';
        }

        return $this->Url()->assemble($routeName, $params);
    }

    /**
     * @param $name
     * @return mixed
     */
    public function getHelper($name)
    {
        return HelperBroker::get($name);
    }

    /**
     * @return SelectQuery
     */
    public function newSelectQuery()
    {
        return $this->newQuery('select');
    }

    /**
     * @param Request|array $request
     * @return mixed
     */
    public function requestFilters($request)
    {
        $this->getFilterManager()->setRequest($request);

        return $this->getFilterManager()->getFiltersArray();
    }

    /**
     * @return FilterManager
     */
    public function getFilterManager()
    {
        if ($this->filterManager === null) {
            $this->initFilterManager();
        }

        return $this->filterManager;
    }

    /**
     * @param FilterManager $filterManager
     */
    public function setFilterManager($filterManager)
    {
        $this->filterManager = $filterManager;
    }

    public function initFilterManager()
    {
        $class = $this->getFilterManagerClass();
        /** @var FilterManager $manager */
        $manager = new $class();
        $manager->setRecordManager($this);
        $this->setFilterManager($manager);
        $this->initFilters();
    }

    /**
     * @return string
     */
    public function getFilterManagerClass()
    {
        return '\Nip\Records\Filters\FilterManager';
    }

    /**
     *
     */
    public function initFilters()
    {
        $this->getFilterManager()->init();
    }

    /**
     * @param $query
     * @return mixed
     * @internal param array $filters
     */
    public function filter($query)
    {
        $query = $this->filterQuery($query);

        return $query;
    }

    /**
     * @param SelectQuery $query
     * @return SelectQuery
     */
    public function filterQuery($query)
    {
        return $this->getFilterManager()->filterQuery($query);
    }

    public function __wakeup()
    {
        $this->initDB();
    }

    /**
     * @param Record $item
     * @return bool|false|Record
     */
    public function exists(Record $item)
    {
        $params = [];
        $params['where'] = [];

        $fields = $this->getUniqueFields();

        if (!$fields) {
            return false;
        }

        foreach ($fields as $field) {
            $params['where'][$field . '-UNQ'] = ["$field = ?", $item->$field];
        }

        $pk = $this->getPrimaryKey();
        if ($item->$pk) {
            $params['where'][] = ["$pk != ?", $item->$pk];
        }

        return $this->findOneByParams($params);
    }

    /**
     * @return null
     */
    public function getUniqueFields()
    {
        if ($this->uniqueFields === null) {
            $this->initUniqueFields();
        }

        return $this->uniqueFields;
    }

    /**
     * @return array|null
     */
    public function initUniqueFields()
    {
        $this->uniqueFields = [];
        $structure = $this->getTableStructure();
        foreach ($structure['indexes'] as $name => $index) {
            if ($index['unique']) {
                foreach ($index['fields'] as $field) {
                    if ($field != $this->getPrimaryKey()) {
                        $this->uniqueFields[] = $field;
                    }
                }
            }
        }

        return $this->uniqueFields;
    }

    /**
     * Finds one Record using params array
     *
     * @param array $params
     * @return Record|false
     */
    public function findOneByParams(array $params = [])
    {
        $params['limit'] = 1;
        $records = $this->findByParams($params);
        if (count($records) > 0) {
            return $records->rewind();
        }

        return false;
    }

    /**
     * Finds Records using params array
     *
     * @param array $params
     * @return mixed
     */
    public function findByParams($params = [])
    {
        $query = $this->paramsToQuery($params);

        return $this->findByQuery($query, $params);
    }

    /**
     * @return RecordCollection
     */
    public function getAll()
    {
        if (!$this->getRegistry()->exists("all")) {
            $this->getRegistry()->set("all", $this->findAll());
        }

        return $this->getRegistry()->get("all");
    }

    /**
     * @return RecordCollection
     */
    public function findAll()
    {
        return $this->findByParams();
    }

    /**
     * @param int $count
     * @return mixed
     */
    public function findLast($count = 9)
    {
        return $this->findByParams([
            'limit' => $count,
        ]);
    }

    /**
     * Inserts a Record into the database
     * @param Record $model
     * @param array|bool $onDuplicate
     * @return mixed
     */
    public function insert($model, $onDuplicate = false)
    {
        $query = $this->insertQuery($model, $onDuplicate);
        $query->execute();

        return $this->getDB()->lastInsertID();
    }

    /**
     * @param $model
     * @param $onDuplicate
     * @return InsertQuery
     */
    public function insertQuery($model, $onDuplicate)
    {
        $inserts = $this->getQueryModelData($model);

        $query = $this->newInsertQuery();
        $query->data($inserts);

        if ($onDuplicate !== false) {
            $query->onDuplicate($onDuplicate);
        }

        return $query;
    }

    /**
     * @param Record $model
     * @return array
     */
    public function getQueryModelData($model)
    {
        $data = [];

        $fields = $this->getFields();
        foreach ($fields as $field) {
            if (isset($model->$field)) {
                $data[$field] = $model->$field;
            }
        }

        return $data;
    }

    /**
     * @return null
     */
    public function getFields()
    {
        if ($this->fields === null) {
            $this->initFields();
        }

        return $this->fields;
    }

    public function initFields()
    {
        $structure = $this->getTableStructure();
        $this->fields = array_keys($structure['fields']);
    }

    /**
     * @return InsertQuery
     */
    public function newInsertQuery()
    {
        return $this->newQuery('insert');
    }

    /**
     * Updates a Record's database entry
     * @param Record $model
     * @return bool|Result
     */
    public function update(Record $model)
    {
        $query = $this->updateQuery($model);

        if ($query) {
            return $query->execute();
        }

        return false;
    }

    /**
     * @param Record $model
     * @return bool|UpdateQuery
     */
    public function updateQuery(Record $model)
    {
        $pk = $this->getPrimaryKey();
        if (!is_array($pk)) {
            $pk = [$pk];
        }

        $data = $this->getQueryModelData($model);

        if ($data) {
            $query = $this->newUpdateQuery();
            $query->data($data);

            foreach ($pk as $key) {
                $query->where("$key = ?", $model->$key);
            }

            return $query;
        }

        return false;
    }

    /**
     * @return UpdateQuery
     */
    public function newUpdateQuery()
    {
        return $this->newQuery('update');
    }

    /**
     * Saves a Record's database entry
     * @param Record $model
     */
    public function save(Record $model)
    {
        $pk = $this->getPrimaryKey();

        if (isset($model->$pk)) {
            $model->update();

            return $model->$pk;
        } else {
            /** @var Record $previous */
            $previous = $model->exists();

            if ($previous) {
                $data = $model->toArray();

                if ($data) {
                    $previous->writeData($model->toArray());
                }
                $previous->update();

                $model->writeData($previous->toArray());

                return $model->$pk;
            }
        }

        $model->insert();

        return $model->$pk;
    }

    /**
     * Delete a Record's database entry
     *
     * @param mixed $input
     */
    public function delete($input)
    {
        $pk = $this->getPrimaryKey();

        if ($input instanceof $this->model) {
            $primary = $input->$pk;
        } else {
            $primary = $input;
        }

        $query = $this->newDeleteQuery();
        $query->where("$pk = ?", $primary);
        $query->limit(1);

        $this->getDB()->execute($query);
    }

    /**
     * @return DeleteQuery
     */
    public function newDeleteQuery()
    {
        return $this->newQuery('delete');
    }

    /**
     * Delete a Record's database entry
     * @param array $params
     * @return $this
     */
    public function deleteByParams($params = [])
    {
        extract($params);

        $query = $this->newDeleteQuery();

        if (isset($where)) {
            if (is_array($where)) {
                foreach ($where as $condition) {
                    $condition = (array)$condition;
                    $query->where($condition[0], $condition[1]);
                }
            } else {
                call_user_func_array([$query, 'where'], $where);
            }
        }

        if (isset($order)) {
            call_user_func_array([$query, 'order'], $order);
        }

        if (isset($limit)) {
            call_user_func_array([$query, 'limit'], $limit);
        }

        $this->getDB()->execute($query);

        return $this;
    }

    /**
     * Returns paginated results
     * @param Paginator $paginator
     * @param array $params
     * @return mixed
     */
    public function paginate(Paginator $paginator, $params = [])
    {
        $query = $this->paramsToQuery($params);

        $countQuery = $this->getDB()->newSelect();
        $countQuery->count(['*', 'count']);
        $countQuery->from([$query, 'tbl']);
        $results = $countQuery->execute()->fetchResults();
        $count = $results[0]['count'];

        $paginator->setCount($count);

        $params['limit'] = $paginator->getLimits();

        return $this->findByParams($params);
    }

    /**
     * Checks the registry before fetching from the database
     * @param mixed $primary
     * @return Record
     */
    public function findOne($primary)
    {
        $item = $this->getRegistry()->get($primary);
        if (!$item) {
            $all = $this->getRegistry()->get("all");
            if ($all) {
                $item = $all[$primary];
            }
            if (!$item) {
                $params['where'][] = ["`{$this->getTable()}`.`{$this->getPrimaryKey()}` = ?", $primary];
                $item = $this->findOneByParams($params);
                if ($item) {
                    $this->getRegistry()->set($primary, $item);
                }

                return $item;
            }
        }

        return $item;
    }

    /**
     * @param Query $query
     * @param array $params
     * @return bool
     */
    public function findOneByQuery($query, $params = [])
    {
        $query->limit(1);
        $return = $this->findByQuery($query, $params);
        if (count($return) > 0) {
            return $return->rewind();
        }

        return false;
    }

    /**
     * @param bool|array $where
     * @return int
     */
    public function count($where = false)
    {
        return $this->countByParams(["where" => $where]);
    }

    /**
     * Counts all the Record entries in the database
     * @param array $params
     * @return int
     */
    public function countByParams($params = [])
    {
        $this->injectParams($params);
        $query = $this->newQuery('select');
        $query->addParams($params);

        return $this->countByQuery($query);
    }

    /**
     * Counts all the Record entries in the database
     * @param Query $query
     * @return int
     */
    public function countByQuery($query)
    {
        $query->setCols('count(*) as count');
        $result = $this->getDB()->execute($query);

        if ($result->numRows()) {
            $row = $result->fetchResult();

            return (int)$row['count'];
        }

        return false;
    }

    /**
     * @param $data
     * @return mixed
     */
    public function cleanData($data)
    {
        return $this->getDB()->getAdapter()->cleanData($data);
    }

    /**
     * The name of the field used as a foreign key in other tables
     * @return string
     */
    public function getPrimaryFK()
    {
        if (!$this->foreignKey) {
            $this->initPrimaryFK();
        }

        return $this->foreignKey;
    }

    public function initPrimaryFK()
    {
        $this->foreignKey = $this->getPrimaryKey() . "_" . inflector()->underscore($this->getModel());
    }

    /**
     * @param $fk
     */
    public function setPrimaryFK($fk)
    {
        $this->foreignKey = $fk;
    }

    /**
     * The name of the field used as a foreign key in other tables
     * @return string
     */
    public function getUrlPK()
    {
        if ($this->urlPK == null) {
            $this->urlPK = $this->getPrimaryKey();
        }

        return $this->urlPK;
    }

    /**
     * @param $name
     * @return bool
     */
    public function hasField($name)
    {
        $fields = $this->getFields();
        if (is_array($fields) && in_array($name, $fields)) {
            return true;
        }

        return false;
    }

    /**
     * @return array
     */
    public function getFullTextFields()
    {
        $return = [];
        $structure = $this->getTableStructure();
        foreach ($structure['indexes'] as $name => $index) {
            if ($index['fulltext']) {
                $return[$name] = $index['fields'];
            }
        }

        return $return;
    }

    /**
     * Get a specified relationship.
     * @param  string $relation
     * @return null|Relation
     */
    public function getRelation($relation)
    {
        $this->checkInitRelations();

        return $this->relations[$relation];
    }

    /**
     * Check if the model needs to initRelations
     * @return void
     */
    protected function checkInitRelations()
    {
        if ($this->relations === null) {
            $this->initRelations();
        }
    }

    protected function initRelations()
    {
        $this->relations = [];
        foreach ($this->relationTypes as $type) {
            $this->initRelationsType($type);
        }
    }

    /**
     * @param string $type
     */
    protected function initRelationsType($type)
    {
        $array = $this->{'_' . $type};
        $this->initRelationsFromArray($type, $array);
    }

    /**
     * @param $type
     * @param $array
     */
    public function initRelationsFromArray($type, $array)
    {
        foreach ($array as $key => $item) {
            $name = is_array($item) ? $key : $item;
            $params = is_array($item) ? $item : [];
            $this->initRelation($type, $name, $params);
        }
    }

    /**
     * @param string $type
     * @param string $name
     * @param array $params
     * @return void
     */
    protected function initRelation($type, $name, $params)
    {
        $this->relations[$name] = $this->newRelation($type);
        $this->relations[$name]->setName($name);
        $this->relations[$name]->addParams($params);
    }

    /**
     * @param string $type
     * @return \Nip\Records\Relations\Relation
     */
    public function newRelation($type)
    {
        $class = $this->getRelationClass($type);
        /** @var \Nip\Records\Relations\Relation $relation */
        $relation = new $class();
        $relation->setManager($this);

        return $relation;
    }

    /**
     * @param string $type
     * @return string
     */
    public function getRelationClass($type)
    {
        $class = 'Nip\Records\Relations\\' . ucfirst($type);

        return $class;
    }

    /**
     * @param $name
     * @param array $params
     */
    public function belongsTo($name, $params = [])
    {
        $this->initRelation('belongsTo', $name, $params);
    }

    /**
     * @param $name
     * @param array $params
     */
    public function hasMany($name, $params = [])
    {
        $this->initRelation('hasMany', $name, $params);
    }

    /**
     * @param $name
     * @param array $params
     */
    public function HABTM($name, $params = [])
    {
        $this->initRelation('hasAndBelongsToMany', $name, $params);
    }

    /**
     * Determine if the given relation is loaded.
     * @param  string $key
     * @return bool
     */
    public function hasRelation($key)
    {
        $this->checkInitRelations();

        return array_key_exists($key, $this->relations);
    }

    /**
     * Set the specific relationship in the model.
     * @param  string $relation
     * @param  mixed $value
     * @return $this
     */
    public function setRelation($relation, $value)
    {
        $this->checkInitRelations();
        $this->relations[$relation] = $value;

        return $this;
    }

    /**
     * @param Record $from
     * @param Record $to
     * @return Record
     */
    public function cloneRelations($from, $to)
    {
        $relations = $from->getManager()->getRelations();
        foreach ($relations as $name => $relation) {
            /** @var \Nip\Records\Relations\HasMany $relation */
            if ($relation->getType() != 'belongsTo') {
                /** @var Record[] $associatedOld */
                $associatedOld = $from->{'get' . $name}();
                if (count($associatedOld)) {
                    $associatedNew = $to->getRelation($name)->newCollection();
                    foreach ($associatedOld as $associated) {
                        $aItem = $associated->getCloneWithRelations();
                        $associatedNew[] = $aItem;
                    }
                    $to->getRelation($name)->setResults($associatedNew);
                }
            }
        }

        return $to;
    }

    /**
     * Get all the loaded relations for the instance.
     * @return array
     */
    public function getRelations()
    {
        $this->checkInitRelations();

        return $this->relations;
    }

    /**
     * Set the entire relations array on the model.
     * @param  array $relations
     * @return $this
     */
    public function setRelations(array $relations)
    {
        $this->relations = $relations;

        return $this;
    }

    /**
     * Sets model and database table from the class name
     */
    protected function inflect()
    {
        $this->inflectController();
    }
}