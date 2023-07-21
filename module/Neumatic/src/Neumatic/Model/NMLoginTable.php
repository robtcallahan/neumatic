<?php

namespace Neumatic\Model;

use STS\Database\DBTable;


class NMLoginTable extends DBTable
{
    protected $dbIndex = 'neumatic';
    protected $tableName = 'login';
    protected $idAutoIncremented = true;


	protected $columnNames = array(
        "id",
		"userId",
        "numLogins",
        "lastLogin",
        "ipAddr",
        "userAgent",
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
	 * @param $userId
	 * @return NMLogin
	 */
	public function getByUserId($userId)
	{
		$this->sysLog->debug("userId=" . $userId);
		$sql    = "select {$this->getQueryColumnsStr()}
                   from   {$this->tableName}
    			   where  userId = " . $userId . ";";
		$result = $this->sqlQueryRow($sql);
		return $this->_set($result);
	}

    /**
   	 * @param string $orderBy
   	 * @param string $dir
   	 * @return NMLogin[]
   	 */
   	public function getAll($orderBy = "userId", $dir = "asc")
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
     * @param int $userId
     * @return \Neumatic\Model\NMLogin
     */
	public function record($userId)
	{
		$this->sysLog->debug("userId=" . $userId);
		date_default_timezone_set("America/New_York");

		$userAgent = array_key_exists("HTTP_USER_AGENT", $_SERVER) ? $_SERVER["HTTP_USER_AGENT"] : "CLI";
		$ipAddr    = array_key_exists("REMOTE_ADDR", $_SERVER) ? $_SERVER["REMOTE_ADDR"] : "localhost";

		$sql    = "select userId, numLogins, lastLogin
		           from   login
		           where  userId = " . $userId . ";";
		$result = $this->sqlQueryRow($sql);
		$now    = date("Y-m-d H:i:s", time());

		if ($result && $result->userId != "") {
            $lastLogin = $result->lastLogin;
			$sql = "update login
			        set
			               lastLogin = '{$now}',
			               numLogins = " . ($result->numLogins + 1) . ",
			               ipAddr    = '{$ipAddr}',
			               userAgent = '{$userAgent}'
			        where
			               userId = {$userId}";
		}
		else {
            $lastLogin = $now;
			$sql = "insert into login (userId, lastLogin, numLogins, ipAddr, userAgent)
			        values ({$userId}, '{$now}', 1, '{$ipAddr}', '{$userAgent}')";
		}
		$this->sql($sql);
        $login = $this->getByUserId($userId);

        // check if we should show the motd. True if last login was yesterday
        $llTime = strtotime($lastLogin);
        if ($llTime >= strtotime("today")) {
            $login->setShowMotd(false);
        } else {
            $login->setShowMotd(true);
        }

        // set the true last login date/time not the current time which was set in the db
        $login->setLastLogin($lastLogin);

        return $login;
	}

    /**
     * @param NMLogin $o
     * @param string $sql
     * @return mixed
     */
    public function create($o, $sql="")
	{
		$this->sysLog->debug();
		return parent::create($o);
	}

    /**
     * @param NMLogin $o
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
     * @param NMLogin $o
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
	 * @return NMLogin
	 */
    private function _set($dbRowObj = null)
   	{
        $this->sysLog->debug();
        $columns = $this->getColumns();
        $numberTypes = $this->getNumberTypes();

   		$o = new NMLogin();
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
