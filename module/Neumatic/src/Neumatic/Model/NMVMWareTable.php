<?php

namespace Neumatic\Model;

use STS\Database\DBTable;


class NMVMWareTable extends DBTable
{
    protected $dbIndex = 'neumatic';
   	protected $tableName = 'vmware';
    protected $idAutoIncremented = true;

	protected $columnNames = array(
        "id",
        "serverId",

        "vSphereSite",
        "vSphereServer",
        "dcName",
        "dcUid",
        "ccrName",
        "ccrUid",
        "rpUid",
        "hsName",

        "instanceUuid",

        "vmSize",
        "numCPUs",
        "memoryGB",

        "vlanName",
        "vlanId",
        "templateName",
        "templateId",
    );

    /**
     * @param null $config
     */
    public function __construct($config = null)
	{
        if ($config) {
            // need to add these to the config since won't be in the config file
            $config['tableName'] = $this->tableName;
            $config['dbIndex'] = $this->dbIndex;
            $config['idAutoIncremented'] = $this->idAutoIncremented;
        }
        parent::__construct($config);
		$this->sysLog->debug();
	}

    /**
     * @param $id
     * @return NMVMWare
     */
	public function getById($id)
	{
        $sql = "select {$this->getQueryColumnsStr()}
                from   {$this->tableName}
		        where  id = " . $id . ";";
		$row = $this->sqlQueryRow($sql);
		return $this->_set($row);
	}

    /**
     * @param $serverId
     * @return NMVMWare
     */
	public function getByServerId($serverId)
	{
        $sql = "select {$this->getQueryColumnsStr()}
                from   {$this->tableName}
		        where  serverId = " . $serverId . ";";
		$row = $this->sqlQueryRow($sql);
		return $this->_set($row);
	}

    /**
     * @param string $vSpherSite
     * @param string $orderBy
     * @param string $dir
     * @return NMVMWare[]
     */
    public function getByVSphereSite($vSpherSite = "lab", $orderBy = "id", $dir = "asc")
	{
        $sql = "select {$this->getQueryColumnsStr()}
                from   {$this->tableName}
                where  vSphereSite = '{$vSpherSite}'
   		        order by {$orderBy} {$dir};";
        $result = $this->sqlQuery($sql);
      		$array  = array();
      		for ($i = 0; $i < count($result); $i++) {
      			$array[] = $this->_set($result[$i]);
      		}
        return $array;
	}

    /**
     * @param $ccrUid
     * @param string $orderBy
     * @param string $dir
     * @return NMVMWare[]
     */
    public function getByCcrUid($ccrUid, $orderBy = "id", $dir = "asc")
	{
        $sql = "select {$this->getQueryColumnsStr()}
                from   {$this->tableName}
                where  ccrUid = '{$ccrUid}'
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
     * @return NMVMWare[]
     */
    public function getAll($orderBy = "id", $dir = "asc")
	{
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
     * @param NMVMWare $o
     * @param string $sql
     * @return NMVMWare
     */
    public function create($o, $sql="")
	{
		$this->sysLog->debug();
        $o->clearChanges();
		$newId = parent::create($o);
		return $this->getById($newId);
	}

    /**
     * @param NMVMWare $o
     * @param string $idColumn
     * @param string $sql
     * @return NMVMWare
     */
    public function update($o, $idColumn = "id", $sql = "")
	{
		$this->sysLog->debug();
		$o = parent::update($o);
        return $this->getById($o->getId());
	}

    /**
     * @param NMVMWare $o
     * @param string $idColumn
     * @param string $sql
     * @return NMVMWare
     */
    public function delete($o, $idColumn = "id", $sql = "")
	{
		$this->sysLog->debug();
        $o->clearChanges();
		return parent::delete($o);
	}

    /**
     * @param NMVMWare $s
     * @return NMVMWare
     */
    public function save(NMVMWare $s)
    {
        $this->sysLog->debug();
        $o = $this->getByServerId($s->getServerId());
        if ($o->getId()) {
            $s->setId($o->getId());
            return $this->update($s);
        } else {
            return $this->create($s);
        }
    }

	// *******************************************************************************
	// * Getters and Setters
	// *****************************************************************************

	/**
	 * @param $logLevel
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
	 * @return NMVMWare
	 */
	private function _set($dbRowObj = null)
	{
		$this->sysLog->debug();
        $columns = $this->getColumns();
        $numberTypes = $this->getNumberTypes();

		$o = new NMVMWare();
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
		}
		else {
			foreach ($this->columnNames as $prop) {
				$o->set($prop, null);
			}
		}
		return $o;
	}
}
