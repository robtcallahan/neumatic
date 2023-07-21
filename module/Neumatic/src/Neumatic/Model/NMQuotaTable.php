<?php

namespace Neumatic\Model;

use STS\Database\DBTable;


class NMQuotaTable extends DBTable
{
    protected $dbIndex = 'neumatic';
   	protected $tableName = 'quota';
    protected $idAutoIncremented = true;

	protected $columnNames = array(
    	"id",
        "dcUid",
        "ccrUid",
        "businessServiceName",
   		"businessServiceId",
        "cpus",
        "memoryGB",
        "storageGB"
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
     * @return NMQuota
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
     * @param $bsId
     * @return NMQuota
     */
	public function getByBusinessServiceId($bsId)
	{
        $sql = "select {$this->getQueryColumnsStr()}
                from   {$this->tableName}
		        where  businessServiceId = '" . $bsId . "';";
		$row = $this->sqlQueryRow($sql);
		return $this->_set($row);
	}

    public function getByDcUidCcrUidAndBusinessServiceId($dcUid, $ccrUid, $bsId)
   	{
           $sql = "select {$this->getQueryColumnsStr()}
                   from   {$this->tableName}
   		           where  dcUid = '" . $dcUid . "'
   		             and  ccrUid = '" . $ccrUid . "'
   		             and  businessServiceId = '" . $bsId . "';";
   		$row = $this->sqlQueryRow($sql);
   		return $this->_set($row);
   	}

    /**
     * @param bsName
     * @return NMQuota
     */
	public function getByBusinessServiceName($bsName)
	{
        $sql = "select {$this->getQueryColumnsStr()}
                from   {$this->tableName}
		        where  businessServiceName = '" . $bsName . "';";
		$row = $this->sqlQueryRow($sql);
		return $this->_set($row);
	}


	// *******************************************************************************
	// CRUD methods
	// *******************************************************************************

    /**
     * @param NMQuota $o
     * @param string $sql
     * @return NMQuota
     */
    public function create($o, $sql="")
	{
		$this->sysLog->debug();
        $o->clearChanges();
		$newId = parent::create($o);
		return $this->getById($newId);
	}

    /**
     * @param NMQuota $o
     * @param string $idColumn
     * @param string $sql
     * @return NMQuota
     */
    public function update($o, $idColumn = "id", $sql = "")
	{
		$this->sysLog->debug();
		$o = parent::update($o);
        return $this->getById($o->getId());
	}

    /**
     * @param NMQuota $o
     * @param string $idColumn
     * @param string $sql
     * @return NMQuota
     */
    public function delete($o, $idColumn = "id", $sql = "")
	{
		$this->sysLog->debug();
        $o->clearChanges();
		return parent::delete($o);
	}

    /**
     * @param NMQuota $s
     * @return NMQuota
     */
    public function save(NMQuota $s)
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
	 * @return NMQuota
	 */
	private function _set($dbRowObj = null)
	{
		$this->sysLog->debug();
        $columns = $this->getColumns();
        $numberTypes = $this->getNumberTypes();

		$o = new NMQuota();
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
