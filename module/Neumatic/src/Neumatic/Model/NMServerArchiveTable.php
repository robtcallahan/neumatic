<?php

namespace Neumatic\Model;

use STS\Database\DBTable;


class NMServerArchiveTable extends DBTable
{
    protected $dbIndex = 'neumatic';
   	protected $tableName = 'server_archive';
    protected $idAutoIncremented = true;

	protected $columnNames = array(
        "id",
        "name",
        "serverType",

        "sysId",
        "businessServiceName",
        "businessServiceId",
        "subsystemName",
        "subsystemId",
        "cmdbEnvironment",

        "location",

        "network",
        "subnetMask",
        "gateway",
        "macAddress",
        "ipAddress",

        "cobblerServer",
        "cobblerDistro",
        "cobblerKickstart",

        "chefServer",
        "chefRole",
        "chefEnv",

        "dateCreated",
        "userCreated",
        "dateUpdated",
        "userUpdated",

        "okToBuild",
        "status",
        "statusText",

        "timeBuildStart",
        "timeBuildEnd",

        "dateBuilt",
        "userBuilt",
        "dateFirstCheckin"
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
     * @return NMServerArchive
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
     * @param $name
     * @return NMServerArchive
     */
	public function getByName($name)
	{
        $sql = "select {$this->getQueryColumnsStr()}
                from   {$this->tableName}
		        where  name = '" . $name . "';";
		$row = $this->sqlQueryRow($sql);
		return $this->_set($row);
	}

    /**
     * @param $username
     * @return NMServerArchive[]
     */
	public function getByUsername($username)
	{
        $sql = "select {$this->getQueryColumnsStr()}
                from   {$this->tableName}
		        where  userCreated = '" . $username . "'
		        order by name;";
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
   	 * @return NMServerArchive[]
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
     * @param NMServerArchive $o
     * @param string $sql
     * @return NMServerArchive
     */
    public function create($o, $sql="")
	{
		$this->sysLog->debug();
        $o->clearChanges();
		$newId = parent::create($o);
		return $this->getById($newId);
	}

    /**
     * @param NMServerArchive $o
     * @param string $idColumn
     * @param string $sql
     * @return NMServerArchive
     */
    public function update($o, $idColumn = "id", $sql = "")
	{
		$this->sysLog->debug();
		$o = parent::update($o);
        return $this->getById($o->getId());
	}

    /**
     * @param NMServerArchive $o
     * @param string $idColumn
     * @param string $sql
     * @return NMServerArchive
     */
    public function delete($o, $idColumn = "id", $sql = "")
	{
		$this->sysLog->debug();
        $o->clearChanges();
		return parent::delete($o);
	}

    /**
     * @param NMServerArchive $s
     * @return NMServerArchive
     */
    public function save(NMServerArchive $s)
    {
        $this->sysLog->debug();
        $o = $this->getByName($s->getName());
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
	 * @return NMServerArchive
	 */
	private function _set($dbRowObj = null)
	{
		$this->sysLog->debug();
        $columns = $this->getColumns();
        $numberTypes = $this->getNumberTypes();

		$o = new NMServerArchive();
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
