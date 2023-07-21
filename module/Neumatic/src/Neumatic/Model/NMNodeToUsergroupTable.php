<?php

namespace Neumatic\Model;

use STS\Database\DBTable;


class NMNodeToUsergroupTable extends DBTable
{
    protected $dbIndex = 'neumatic';
    protected $tableName = 'node_to_usergroup';
   
    protected $idAutoIncremented = false;
    protected $columnNames = array(
        "node_id",
        "usergroup_id"
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
     * @param $node_id
     * @return NMNodeToUsergroup
     */
    public function getUsergroupsByNodeId($node_id)
    {
        $this->sysLog->debug("node_id=" . $node_id);
        if(is_numeric($node_id)){
            $sql = "select usergroup_id
                    from   {$this->tableName}
                    where  node_id = {$node_id};";
                    
            $result = $this->sqlQuery($sql);
            return $result;
        }
        return array();
    }
   
    /**
     * @param $usergroup_id
     * @return NMNodeToUsergroup
     */
    public function getByUsergroupId($usergroup_id)
    {
        $this->sysLog->debug("usergroup_id=" . $usergroup_id);
        $sql = "select {$this->getQueryColumnsStr()}
                from   {$this->tableName}
                where  usergroup_id = {$usergroup_id};";
        $result = $this->sqlQuery($sql);
        return $result;
    }
   
    public function getByNodeAndUsergroupIds($node_id, $usergroup_id){
        $this->sysLog->debug("usergroup_id=" . $usergroup_id);
        $sql = "select {$this->getQueryColumnsStr()}
                from   {$this->tableName}
                where  node_id = '{$node_id}' AND usergroup_id = '{$usergroup_id}';";
            $row = $this->sqlQueryRow($sql);
        return $row;
    }

    // *******************************************************************************
    // CRUD methods
    // *******************************************************************************

    /**
     * @param int $node_id
     * @param int $usergroup_id
     * @return boolean
     */
    public function createUserGroupEntry($node_id, $usergroup_id)
    {
        $this->sysLog->debug("node_id=".$node_id." usergroup_id=" . $usergroup_id);
        
        $checkExists = $this->getByNodeAndUsergroupIds($node_id, $usergroup_id);
        if(!is_object($checkExists)){
            $sql = "INSERT INTO {$this->tableName}
                    ({$this->getQueryColumnsStr()})
                    VALUES ({$node_id}, {$usergroup_id});";
                    
            $this->sql($sql);
            return true;    
        }
        return false;
    }

   

    /**
     * @param int $node_id
     * @param int $usergroup_id
     * @return boolean
     */
    public function deleteEntry($node_id, $usergroup_id)
    {
        $this->sysLog->debug("node_id=".$node_id." usergroup_id=" . $usergroup_id);
        $sql = "DELETE FROM {$this->tableName}
                WHERE 
                node_id='{$node_id}' AND usergroup_id='{$usergroup_id}'";
        $row = $this->sqlQuery($sql);
        return $row;
        
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
}
