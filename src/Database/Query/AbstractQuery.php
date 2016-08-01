<?php

namespace Nip\Database\Query;

use Nip\Database\Connection;
use Nip\Database\Query\Condition\Condition;
use Nip\Database\Query\Condition\AndCondition;
use Nip\Database\Query\Condition\OrCondition;
use Nip\Database\Result;

/**
 * Class AbstractQuery
 * @package Nip\Database\Query
 *
 * @method AbstractQuery setCols() setCols(array|string $cols = null)
 * @method AbstractQuery cols() cols(array|string $cols)
 * @method AbstractQuery count() count(array|string $cols)
 * @method AbstractQuery sum() sum(array|string $cols)
 * @method AbstractQuery from() from(array|string $from)
 * @method AbstractQuery data() data(array $data)
 * @method AbstractQuery table() table(array|string $table)
 * @method AbstractQuery order() order(array|string $order)
 */
abstract class AbstractQuery
{

    /**
     * @var Connection
     */
    protected $_db;

    protected $_parts = array(
        'where' => null,
    );

    public function setManager(Connection $manager)
    {
        $this->_db = $manager;
        return $this;
    }

    /**
     * @return Connection
     */
    public function getManager()
    {
        return $this->_db;
    }

    public function __call($name, $arguments)
    {
        if (strpos($name, 'set') === 0) {
            $name = str_replace('set', '', $name);
            $name[0] = strtolower($name[0]);
            $this->initPart($name);
        }

        foreach ($arguments as $argument) {
            $this->addPart($name, $argument);
        }

        return $this;
    }

    public function addParams($params)
    {
        $this->checkParamSelect($params);
        $this->checkParamFrom($params);
        $this->checkParamWhere($params);
        $this->checkParamOrder($params);
        $this->checkParamGroup($params);
        $this->checkParamHaving($params);
        $this->checkParamLimit($params);
    }

    protected function checkParamSelect($params)
    {
        if (isset($params['select']) && is_array($params['select'])) {
            call_user_func_array(array($this, 'cols'), $params['select']);
        }
    }

    protected function checkParamFrom($params)
    {
        if (isset($params['from']) && !empty($params['from'])) {
            $this->from($params['from']);
        }
    }

    protected function checkParamWhere($params)
    {
        if (isset($params['where']) && is_array($params['where'])) {
            foreach ($params['where'] as $condition) {
                $condition = (array)$condition;
                $this->where($condition[0], $condition[1]);
            }
        }
    }

    protected function checkParamOrder($params)
    {
        if (isset($params['order']) && !empty($params['order'])) {
            call_user_func_array(array($this, 'order'), $params['order']);
        }
    }

    protected function checkParamGroup($params)
    {
        if (isset($params['group']) && !empty($params['group'])) {
            call_user_func_array(array($this, 'group'), array($params['group']));
        }
    }

    protected function checkParamHaving($params)
    {
        if (isset($params['having']) && !empty($params['having'])) {
            call_user_func_array(array($this, 'having'), array($params['having']));
        }
    }

    protected function checkParamLimit($params)
    {
        if (isset($params['limit']) && !empty($params['limit'])) {
            call_user_func_array(array($this, 'limit'), array($params['limit']));
        }
    }

    protected function addPart($name, $value)
    {
        if (!isset($this->_parts[$name])) {
            $this->initPart($name);
        }
        $this->_parts[$name][] = $value;
        return $this;
    }

    protected function setPart($name, $value)
    {
        $this->initPart($name);
        $this->addPart($name, $value);
        return $this;
    }

    protected function initPart($name)
    {
        $this->_parts[$name] = array();
        return $this;
    }

    protected function getPart($name)
    {
        return $this->hasPart($name) ? $this->_parts[$name] : null;
    }

    protected function hasPart($name)
    {
        return isset($this->_parts[$name]) && count($this->_parts[$name]);
    }

    public function limit($start, $offset = false)
    {
        $this->_parts['limit'] = $start;
        if ($offset) {
            $this->_parts['limit'] .= ',' . $offset;
        }
        return $this;
    }

    /**
     * @param $string
     * @param array $values
     * @return $this
     */
    public function where($string, $values = array())
    {
        /** @var Condition $this ->_parts[] */
        if ($string) {
            if (isset($this->_parts['where']) && $this->_parts['where'] instanceOf Condition) {
                $this->_parts['where'] = $this->_parts['where']->and_($this->getCondition($string, $values));
            } else {
                $this->_parts['where'] = $this->getCondition($string, $values);
            }
        }

        return $this;
    }

    public function orWhere($string, $values = array())
    {
        if ($string) {
            if ($this->_parts['where'] instanceOf Condition) {
                $this->_parts['where'] = $this->_parts['where']->or_($this->getCondition($string, $values));
            } else {
                $this->_parts['where'] = $this->getCondition($string, $values);
            }
        }

        return $this;
    }

    public function having($string, $values = array())
    {
        if ($string) {
            if ($this->_parts['having'] instanceOf Condition) {
                $this->_parts['having'] = $this->_parts['having']->and_($this->getCondition($string, $values));
            } else {
                $this->_parts['having'] = $this->getCondition($string, $values);
            }
        }

        return $this;
    }

    /**
     * @param string $string
     * @param array $values
     * @return Condition
     */
    public function getCondition($string, $values = array())
    {
        if (!is_object($string)) {
            $condition = new Condition($string, $values);
            $condition->setQuery($this);
        } else {
            $condition = $string;
        }
        return $condition;
    }

    /**
     * Escapes data for safe use in SQL queries
     *
     * @param string $data
     * @return string
     */
    public function cleanData($data)
    {
        return $this->getManager()->getAdapter()->cleanData($data);
    }

    /**
     * @return Result
     */
    public function execute()
    {
        return $this->getManager()->execute($this);
    }

    public function assemble()
    {
        return null;
    }

    /**
     * Implements magic method.
     *
     * @return string This object as a Query string.
     */
    public function __toString()
    {
        return (string)$this->assemble();
    }

    protected function getTable()
    {
        if (!is_array($this->_parts['table']) && count($this->_parts['table']) < 1) {
            trigger_error("No Table defined", E_USER_WARNING);
        }

        return reset($this->_parts['table']);
    }

    protected function parseWhere()
    {
        return is_object($this->_parts['where']) ? (string)$this->_parts['where'] : '';
    }

    protected function parseHaving()
    {
        if (isset($this->_parts['having'])) {
            return (string)$this->_parts['having'];
        }
        return '';
    }

    /**
     * Parses ORDER BY entries
     *
     * @return string
     */
    protected function parseOrder()
    {
        if (!isset($this->_parts['order']) || !is_array($this->_parts['order']) || count($this->_parts['order']) < 1) {
            return false;
        }

        $orderParts = array();

        foreach ($this->_parts['order'] as $itemOrder) {
            if ($itemOrder) {
                if (!is_array($itemOrder)) {
                    $itemOrder = array($itemOrder);
                }

                $column = isset($itemOrder[0]) ? $itemOrder[0] : false;
                $type = isset($itemOrder[1]) ? $itemOrder[1] : '';
                $protected = isset($itemOrder[2]) ? $itemOrder[2] : true;

                $column = ($protected ? $this->protect($column) : $column) . ' ' . strtoupper($type);

                $orderParts[] = trim($column);
            }
        }

        return implode(', ', $orderParts);
    }

    /**
     * Prefixes table names
     *
     * @param string $table
     * @return string
     */
    protected function tableName($table = '')
    {
        return $this->getManager()->tableName($table);
    }

    /**
     * Adds backticks to input
     *
     * @param string $input
     * @return string
     */
    protected function protect($input)
    {
        return strpos($input, '(') !== false ? $input : str_replace("`*`", "*",
            '`' . str_replace('.', '`.`', $input) . '`');
    }

    /**
     * Removes backticks from input
     *
     * @param string $input
     * @return string
     */
    protected function cleanProtected($input)
    {
        return str_replace('`', '', $input);
    }

}