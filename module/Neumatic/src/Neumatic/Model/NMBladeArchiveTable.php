<?php

namespace Neumatic\Model;

use STS\Database\DBTable;


class NMBladeArchiveArchiveTable extends DBTable
{
    protected $dbIndex = 'neumatic';
   	protected $tableName = 'blade_archive';
    protected $idAutoIncremented = true;

	protected $columnNames = array(
        "id",
        "serverId",

        "distSwitch",

        "vlanName",
        "vlanId",

        "chassisName",
        "chassisId",
        "bladeName",
        "bladeId",
        "bladeSlot",
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
     * @return NMBladeArchive
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
     * @return NMBladeArchive
     */
	public function getByServerId($serverId)
	{
        $sql = "select {$this->getQueryColumnsStr()}
                from   {$this->tableName}
		        where  serverId = " . $serverId . ";";
		$row = $this->sqlQueryRow($sql);
		return $this->_set($row);
	}


	// *******************************************************************************
	// CRUD methods
	// *******************************************************************************

    /**
     * @param NMBladeArchive $o
     * @param string $sql
     * @return NMBladeArchive
     */
    public function create($o, $sql="")
	{
		$this->sysLog->debug();
        $o->clearChanges();
		$newId = parent::create($o);
		return $this->getById($newId);
	}

    /**
     * @param NMBladeArchive $o
     * @param string $idColumn
     * @param string $sql
     * @return NMBladeArchive
     */
    public function update($o, $idColumn = "id", $sql = "")
	{
		$this->sysLog->debug();
		$o = parent::update($o);
        return $this->getById($o->getId());
	}

    /**
     * @param NMBladeArchive $o
     * @param string $idColumn
     * @param string $sql
     * @return NMBladeArchive
     */
    public function delete($o, $idColumn = "id", $sql = "")
	{
		$this->sysLog->debug();
        $o->clearChanges();
		return parent::delete($o);
	}

    /**
     * @param NMBladeArchive $s
     * @return NMBladeArchive
     */
    public function save(NMBladeArchive $s)
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
	 * @return NMBladeArchive
	 */
	private function _set($dbRowObj = null)
	{
		$this->sysLog->debug();
        $columns = $this->getColumns();
        $numberTypes = $this->getNumberTypes();

		$o = new NMBladeArchive();
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
