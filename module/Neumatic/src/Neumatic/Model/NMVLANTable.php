<?php

namespace Neumatic\Model;

use STS\Database\DBTable;


class NMVLANTable extends DBTable
{
    protected $dbIndex = 'neumatic';
    protected $tableName = 'vlan';
    protected $idAutoIncremented = true;


	protected $columnNames = array(
        "id",
		"distSwitchId",
        "vlanId",
        "name",
        "network",
        "netmask",
        "gateway",
        "enabled",
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
	 * @return NMVLAN
	 */
	public function getById($id)
	{
		$sql    = "select {$this->getQueryColumnsStr()}
                   from   {$this->tableName}
    			   where  id = " . $id . ";";
		$result = $this->sqlQueryRow($sql);
		return $this->_set($result);
	}

    /**
   	 * @param array $distSwitchIds
     * @param $vlanId
   	 * @return NMVLAN
   	 */
   	public function getByDistSwitchIdsAndVlanId($distSwitchIds, $vlanId)
   	{
   		if (filter_var($vlanId, FILTER_VALIDATE_IP)) {
			$sql    = "select {$this->getQueryColumnsStr()}
                   from   {$this->tableName}
       			   where  distSwitchId in (" . implode(',', $distSwitchIds) . ")
       			     and  network LIKE '".$vlanId."%'
       			     and  enabled = 1;";
		}else{
			$sql    = "select {$this->getQueryColumnsStr()}
                   from   {$this->tableName}
       			   where  distSwitchId in (" . implode(',', $distSwitchIds) . ")
       			     and  vlanId = " . $vlanId . "
       			     and  enabled = 1;";
		}	
   		
   		$result = $this->sqlQueryRow($sql);
   		return $this->_set($result);
   	}

    /**
   	 * @param string $orderBy
   	 * @param string $dir
   	 * @return NMVLAN[]
   	 */
   	public function getAll($orderBy = "name", $dir = "asc")
   	{
		$sql = "select {$this->getQueryColumnsStr()}
                from   {$this->tableName}
   		        order by {$orderBy} {$dir};";
   		$results = $this->sqlQuery($sql);
   		$array  = array();
        foreach ($results as $result) {
            $array[] = $this->_set($result);
        }
   		return $array;
   	}

    /**
     * @param int $distSwitchId
     * @param string $orderBy
     * @param string $dir
     * @return NMVLAN[]
     */
   	public function getAllByDistSwitchId($distSwitchId, $orderBy = "vlanId", $dir = "asc")
   	{
		$sql = "select {$this->getQueryColumnsStr()}
                from   {$this->tableName}
                where  distSwitchId = {$distSwitchId}
   		        order by {$orderBy} {$dir};";
   		$results = $this->sqlQuery($sql);
   		$array  = array();
        foreach ($results as $result) {
            $array[] = $this->_set($result);
        }
   		return $array;
   	}


	// *******************************************************************************
	// CRUD methods
	// *******************************************************************************

    /**
     * @param NMVLAN $o
     * @param string $sql
     * @return mixed
     */
    public function create($o, $sql="")
	{
		$this->sysLog->debug();
		return parent::create($o);
	}

    /**
     * @param NMVLAN $o
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
     * @param NMVLAN $o
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
	 * @return NMVLAN
	 */
    private function _set($dbRowObj = null)
   	{
        $this->sysLog->debug();
        $columns = $this->getColumns();
        $numberTypes = $this->getNumberTypes();

   		$o = new NMVLAN();
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
