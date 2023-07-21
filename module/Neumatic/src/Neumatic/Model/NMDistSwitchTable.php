<?php

namespace Neumatic\Model;

use STS\Database\DBTable;


class NMDistSwitchTable extends DBTable
{
    protected $dbIndex = 'neumatic';
    protected $tableName = 'dist_switch';
    protected $idAutoIncremented = true;


	protected $columnNames = array(
        "id",
        "model",
        "name",
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
	 * @return NMDistSwitch
	 */
	public function getById($id)
	{
		$this->sysLog->debug("id=" . $id);
		$sql    = "select {$this->getQueryColumnsStr()}
                   from   {$this->tableName}
    			   where  id = " . $id . ";";
		$result = $this->sqlQueryRow($sql);
		return $this->_set($result);
	}

    /**
     * @param string $location
     * @param string $orderBy
     * @param string $dir
     * @return NMDistSwitch[]
     */
   	public function getDistSwitchesByLocation($location, $orderBy = "name", $dir = "asc")
   	{
   		$this->sysLog->debug();
		$sql = "select distinct name
                from   {$this->tableName}
                where  enabled = 1
                  and  name like '%" . $location . "%'
   		        order by {$orderBy} {$dir};";
   		$results = $this->sqlQuery($sql);
   		$array  = array();
        foreach ($results as $result) {
            $array[] = $result['name'];
        }
   		return $array;
   	}

    /**
     * @param string $name
     * @param string $orderBy
     * @param string $dir
     * @return NMDistSwitch[]
     */
   	public function getDistSwitchesByName($name, $orderBy = "model", $dir = "asc")
   	{
   		$this->sysLog->debug();
		$sql = "select {$this->getQueryColumnsStr()}
                from   {$this->tableName}
                where  enabled = 1
                  and  name = '{$name}'
   		        order by {$orderBy} {$dir};";
   		$results = $this->sqlQuery($sql);
   		$array  = array();
        foreach ($results as $result) {
            $array[] = $this->_set($result);
        }
   		return $array;
   	}

    /**
   	 * @param string $orderBy
   	 * @param string $dir
   	 * @return NMDistSwitch[]
   	 */
   	public function getAllEnabled($orderBy = "name", $dir = "asc")
   	{
   		$this->sysLog->debug();
		$sql = "select {$this->getQueryColumnsStr()}
                from   {$this->tableName}
                where  enabled = 1
   		        order by {$orderBy} {$dir};";
   		$result = $this->sqlQuery($sql);
   		$array  = array();
   		for ($i = 0; $i < count($result); $i++) {
   			$array[] = $this->_set($result[$i]);
   		}
   		return $array;
   	}

    /**
   	 * @param string $orderBy
   	 * @param string $dir
   	 * @return NMDistSwitch[]
   	 */
   	public function getAll($orderBy = "name", $dir = "asc")
   	{
   		$this->sysLog->debug();
		$sql = "select {$this->getQueryColumnsStr()}
                from   {$this->tableName}
   		        order by {$orderBy} {$dir};";
   		$result = $this->sqlQuery($sql);
   		$array  = array();
   		for ($i = 0; $i < count($result); $i++) {
   			$array[] = $this->_set($result[$i]);
   		}
   		return $array;
   	}

	// *******************************************************************************
	// CRUD methods
	// *******************************************************************************

    /**
     * @param NMDistSwitch $o
     * @param string $sql
     * @return mixed
     */
    public function create($o, $sql="")
	{
		$this->sysLog->debug();
		return parent::create($o);
	}

    /**
     * @param NMDistSwitch $o
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
     * @param NMDistSwitch $o
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
	 * @return NMDistSwitch
	 */
    private function _set($dbRowObj = null)
   	{
        $this->sysLog->debug();
        $columns = $this->getColumns();
        $numberTypes = $this->getNumberTypes();

   		$o = new NMDistSwitch();
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
