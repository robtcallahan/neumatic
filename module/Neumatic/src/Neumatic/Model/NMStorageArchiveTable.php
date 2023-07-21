<?php

namespace Neumatic\Model;

use STS\Database\DBTable;


class NMStorageArchiveArchiveTable extends DBTable
{
    protected $dbIndex = 'neumatic';
   	protected $tableName = 'storage_archive';
    protected $idAutoIncremented = true;

	protected $columnNames = array(
        "id",
        "serverId",
        "lunSizeGb"
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
     * @return NMStorageArchive
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
     * @return NMStorageArchive[]
     */
	public function getByServerId($serverId)
	{
		$sql = "select {$this->getQueryColumnsStr()}
                from   {$this->tableName}
		        where  serverId = " . $serverId . ";";
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
   	 * @return NMStorageArchive[]
   	 */
   	public function getAll($orderBy = "name", $dir = "asc")
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
     * @param NMStorageArchive $o
     * @param string $sql
     * @return NMStorageArchive
     */
    public function create($o, $sql="")
	{
		$this->sysLog->debug();
        $o->clearChanges();
		$newId = parent::create($o);
		return $this->getById($newId);
	}

    /**
     * @param NMStorageArchive $o
     * @param string $idColumn
     * @param string $sql
     * @return NMStorageArchive
     */
    public function update($o, $idColumn = "id", $sql = "")
	{
		$this->sysLog->debug();
		$o = parent::update($o);
        return $this->getById($o->getId());
	}

    /**
     * @param NMStorageArchive $o
     * @param string $idColumn
     * @param string $sql
     * @return NMStorageArchive
     */
    public function delete($o, $idColumn = "id", $sql = "")
	{
		$this->sysLog->debug();
        $o->clearChanges();
		return parent::delete($o);
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
	 * @return NMStorageArchive
	 */
	private function _set($dbRowObj = null)
	{
		$this->sysLog->debug();
        $columns = $this->getColumns();
        $numberTypes = $this->getNumberTypes();

		$o = new NMStorageArchive();
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
