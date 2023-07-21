<?php

namespace Neumatic\Model;

use STS\Database\DBTable;


class NMHostgroupTable extends DBTable
{
    protected $dbIndex = 'neumatic';
    protected $tableName = 'hostgroup';
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
     * @return NMHostgroup
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
     * @return NMHostgroup
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

    /**
     * @param $serverId int
     * @return NMHostgroup[]
     */
    public function getByServerId($serverId) {
        $sql = "select t.id, t.name
                from {$this->tableName} t,
                     hostgroups_to_server hg
                where hg.serverId = {$serverId}
                  and hg.hostgroupId = t.id\n";
        $result = $this->sqlQuery($sql);
        $array  = array();
        foreach ($result as $r) {
            $array[] = $this->_set($r);
        }
        return $array;
    }

    public function isServerInGroup($serverId, $groupId) {
        $sql = "select t.id, t.name
                from {$this->tableName} t,
                     hostgroups_to_server hg
                where hg.serverId = {$serverId}
                  and hg.hostgroupId = t.id
                  and t.id = {$groupId}\n";
        $result = $this->sqlQueryRow($sql);
        return $this->_set($result);
    }

    public function addServerToGroup($serverId, $groupId) {
        // check if already exists
        $group = $this->isServerInGroup($serverId, $groupId);
        if (!$group->getId()) {
            $sql = "insert into hostgroups_to_server
                    set serverId = {$serverId}, hostgroupId = {$groupId};";
            $this->sql($sql);
        }
    }

    public function removeServerAllGroups($serverId) {
        $sql = "delete from hostgroups_to_server
                where serverId = {$serverId}\n";
        $this->sql($sql);
    }

    // *******************************************************************************
    // CRUD methods
    // *******************************************************************************

    /**
     * @param NMHostgroup $o
     * @param string $sql
     * @return NMHostgroup
     */
    public function create($o, $sql = "")
    {
        $this->sysLog->debug();
        $newId = parent::create($o, $sql);
        return $this->getById($newId);
    }

    /**
     * @param NMHostgroup $o
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
     * @param NMHostgroup $o
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
     * @return NMHostgroup
     */
    private function _set($dbRowObj = null)
    {
        $this->sysLog->debug();
        $columns     = $this->getColumns();
        $numberTypes = $this->getNumberTypes();

        $o = new NMHostgroup();
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
