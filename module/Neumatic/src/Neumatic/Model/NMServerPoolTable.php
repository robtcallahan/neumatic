<?php

namespace Neumatic\Model;

use STS\Database\DBTable;


class NMServerPoolTable extends DBTable
{
    protected $dbIndex = 'neumatic';
    protected $tableName = 'server_pool';
    protected $idAutoIncremented = true;


	protected $columnNames = array(
        "id",
		"serverId",
        "name",
        "ipAddress",
        "subnetMask",
        "gateway",
        "state",
        "userId",
        "dateCheckedOut"
	);

    /** @var int The number of minutes to hold a pool server before allowing it to be allocated */
    protected $checkOutExpirationMinutes = 15;

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
	 * @return NMServerPool
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
   	 * @param $serverId
   	 * @return NMServerPool
   	 */
   	public function getByServerId($serverId)
   	{
   		$this->sysLog->debug("serverId=" . $serverId);
   		$sql    = "select {$this->getQueryColumnsStr()}
                   from   {$this->tableName}
       			   where  serverId = " . $serverId . ";";
   		$result = $this->sqlQueryRow($sql);
   		return $this->_set($result);
   	}

    /**
   	 * @param $state
   	 * @return NMServerPool[]
   	 */
   	public function getByState($state)
   	{
   		$this->sysLog->debug("state=" . $state);
   		$sql    = "select {$this->getQueryColumnsStr()}
                   from   {$this->tableName}
       			   where  state = '" . $state . "';";
        $result = $this->sqlQuery($sql);
        $array  = array();
        for ($i = 0; $i < count($result); $i++) {
            $array[] = $this->_set($result[$i]);
        }
        return $array;
   	}

    /**
   	 * @param $userId
   	 * @return NMServerPool[]
   	 */
   	public function getByUserId($userId)
   	{
   		$this->sysLog->debug("userId=" . $userId);
   		$sql    = "select {$this->getQueryColumnsStr()}
                   from   {$this->tableName}
       			   where  userId = '" . $userId . "';";
        $result = $this->sqlQuery($sql);
        $array  = array();
        for ($i = 0; $i < count($result); $i++) {
            $array[] = $this->_set($result[$i]);
        }
        return $array;
   	}

    /**
     * We're going to return free servers that have a check out date > 10 minutes
     * The check out date is populated when someone requests a new pool server. However, the pool server
     * is not marked as Used until the first save. Therefore, it is possible that more than on person can
     * request a pool server and get the same one. By adding a check out date, we prevent that from happening
     * at least for 15 minutes which should be plenty of time for a person or process to save.
   	 * @return NMServerPool
   	 */
   	public function getNextFree()
   	{
        $date = date('Y-m-d H:i:s');
   		$this->sysLog->debug();
   		$sql    = "select {$this->getQueryColumnsStr()}
                   from   {$this->tableName}
       			   where  state = 'Free'
       			     and  timestampdiff(MINUTE, dateCheckedOut, '" . $date . "') > " . $this->checkOutExpirationMinutes . "
       			   order by id
       			   limit 1;";
   		$result = $this->sqlQueryRow($sql);
   		$poolServer = $this->_set($result);
        $poolServer->setDateCheckedOut($date);
        $this->update($poolServer);
        return $poolServer;
   	}

    /**
   	 * @param string $orderBy
   	 * @param string $dir
   	 * @return NMServerPool[]
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
     * @param NMServerPool $o
     * @param string $sql
     * @return mixed
     */
    public function create($o, $sql="")
	{
		$this->sysLog->debug();
		return parent::create($o);
	}

    /**
     * @param NMServerPool $o
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
     * @param NMServerPool $o
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
	 * @return NMServerPool
	 */
    private function _set($dbRowObj = null)
   	{
        $this->sysLog->debug();
        $columns = $this->getColumns();
        $numberTypes = $this->getNumberTypes();

   		$o = new NMServerPool();
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
