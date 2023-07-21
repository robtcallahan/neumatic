<?php

namespace Neumatic\Model;

use STS\Database\DBTable;


class NMNodeTable extends DBTable
{
    protected $dbIndex = 'neumatic';
    protected $tableName = 'node';
    protected $idAutoIncremented = true;

    protected $columnNames = array(
        "id",
        "name"
    );

    public function __construct($config = null)
    {
        if ($config) {
            // need to add these to the config since won't be in the config file
            $config['tableName']         = $this->tableName;
            $config['dbIndex']           = $this->dbIndex;
            $config['idAutoIncremented'] = $this->idAutoIncremented;
        }
        parent::__construct($config);
        $this->sysLog->debug();
    }

    /**
     * @param $id
     * @return NMNode
     */
    public function getById($id)
    {
        $this->sysLog->debug("id=" . $id);
        $sql = "select {$this->getQueryColumnsStr()}
                from   {$this->tableName}
		        where  id = {$id};";
        $row = $this->sqlQueryRow($sql);
        return $this->_set($row);
    }

    /**
     * @param string $name
     * @return NMNode
     */
    public function getByName($name)
    {
        $this->sysLog->debug("name=" . $name);
        $sql    = "select {$this->getQueryColumnsStr()}
                from   {$this->tableName}
                where  name = '{$name}'";
        $result = $this->sqlQueryRow($sql);
        return $this->_set($result);
    }



    // *******************************************************************************
    // CRUD methods
    // *******************************************************************************

    /**
     * @param NMNode $o
     * @return NMNode
     */
    public function create($o, $sql = "")
    {
        $this->sysLog->debug();
        $newId = parent::create($o, $sql);
        return $this->getById($newId);
    }

    /**
     * @param NMNode $o
     * @param string $idColumn
     * @param string $sql
     * @return mixed
     */
    public function update($o, $idColumn = "id", $sql = "")
    {
       $this->sysLog->debug();
       return parent::update($o);
    }

    /**
     * @param NMNode $o
     * @param string $idColumn
     * @param string $sql
     * @return mixed
     */
    public function delete($o, $idColumn = "id", $sql = "")
    {
        $this->sysLog->debug();
        return parent::delete($o);
    }

    // *******************************************************************************
    // Getters and Setters
    // *******************************************************************************

    /**
     * @param $logLevel
     * @return void
     */
    public function setLogLevel($logLevel)
    {
        $this->logLevel = $logLevel;
    }

    /**
     * @return mixed
     */
    public function getLogLevel()
    {
        return $this->logLevel;
    }

    /**
     * @param $columnNames
     * @return void
     */
    public function setColumnNames($columnNames)
    {
        $this->columnNames = $columnNames;
    }

    /**
     * @return array
     */
    public function getColumnNames()
    {
        return $this->columnNames;
    }

    /**
     * @param null $dbRowObj
     * @return NMNode
     */
    private function _set($dbRowObj = null)
    {
        $this->sysLog->debug();
        $columns     = $this->getColumns();
        $numberTypes = $this->getNumberTypes();

        $o = new NMNode();
        if ($dbRowObj) {
            foreach ($this->columnNames as $prop) {
                if (array_key_exists($prop, $dbRowObj)) {
                    if (preg_match($numberTypes, $columns[$prop]['type'])) {
                        $o->set($prop, intval($dbRowObj[$prop]));
                    } else {
                        $o->set($prop, $dbRowObj[$prop]);
                    }
                }
            }
        } else {
            foreach ($this->columnNames as $prop) {
                $o->set($prop, null);
            }
        }
        return $o;
    }
}
