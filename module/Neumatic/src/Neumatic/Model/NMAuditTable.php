<?php

namespace Neumatic\Model;

use STS\Database\DBTable;


class NMAuditTable extends DBTable
{
    protected $dbIndex = 'neumatic';
   	protected $tableName = 'audit';
    protected $idAutoIncremented = true;

	protected $columnNames = array(
    	"id",
        "userId",
        "userName",
        "dateTime",
        "ipAddress",
        "method",
        "uri",
        "controller",
        "function",
        "parameters",
        "descr"
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
     * @return NMAudit
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
     * @param $hostName
     * @return NMAudit[]
     */
	public function getByHostName($hostName)
	{
        $sql = "select {$this->getQueryColumnsStr()}
                from   {$this->tableName}
		        where  parameters like '%" . $hostName . "%'
		        order by dateTime desc;";
		$rows = $this->sqlQuery($sql);
        $entries = array();
        foreach ($rows as $row) {
            $entries[] = $this->_set($row);
        }
		return $entries;
	}


	// *******************************************************************************
	// CRUD methods
	// *******************************************************************************

    /**
     * @param NMAudit $o
     * @param string $sql
     * @return NMAudit
     */
    public function create($o, $sql="")
	{
		$this->sysLog->debug();
        $o->clearChanges();
		$newId = parent::create($o);
		return $this->getById($newId);
	}

    /**
     * @param NMAudit $o
     * @param string $idColumn
     * @param string $sql
     * @return NMAudit
     */
    public function update($o, $idColumn = "id", $sql = "")
	{
		$this->sysLog->debug();
		$o = parent::update($o);
        return $this->getById($o->getId());
	}

    /**
     * @param NMAudit $o
     * @param string $idColumn
     * @param string $sql
     * @return NMAudit
     */
    public function delete($o, $idColumn = "id", $sql = "")
	{
		$this->sysLog->debug();
        $o->clearChanges();
		return parent::delete($o);
	}

    /**
     * @param NMAudit $s
     * @return NMAudit
     */
    public function save(NMAudit $s)
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
	 * @return NMAudit
	 */
	private function _set($dbRowObj = null)
	{
		$this->sysLog->debug();
        $columns = $this->getColumns();
        $numberTypes = $this->getNumberTypes();

		$o = new NMAudit();
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
