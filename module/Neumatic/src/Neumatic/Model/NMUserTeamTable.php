<?php

namespace Neumatic\Model;

use STS\Database\DBTable;


class NMUserTeamTable extends DBTable
{
    protected $dbIndex = 'neumatic';
    protected $tableName = 'user_team';
    protected $idAutoIncremented = true;

    protected $columnNames = array(
        "userId",
        "teamId"
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
     * @param int $userId
     * @param int $teamId
     * @return NMUserTeam
     */
	public function getByUserIdTeamId($userId, $teamId)
	{
        $sql = "select {$this->getQueryColumnsStr()}
                from   {$this->tableName}
		        where  userId = {$userId}
		          and  teamId = {$teamId};";
		$row = $this->sqlQueryRow($sql);
		return $this->_set($row);
	}

    /**
   	 * @param int $userId
   	 * @return NMUserTeam[]
   	 */
   	public function getByUserId($userId)
   	{
		$sql = "select {$this->getQueryColumnsStr()}
                from   {$this->tableName}
   		        where  userId = {$userId};";
   		$result = $this->sqlQuery($sql);
   		$array  = array();
   		for ($i = 0; $i < count($result); $i++) {
   			$array[] = $this->_set($result[$i]);
   		}
   		return $array;
   	}

    /**
   	 * @param int $teamId
   	 * @return NMUserTeam[]
   	 */
   	public function getByTeamId($teamId)
   	{
		$sql = "select {$this->getQueryColumnsStr()}
                from   {$this->tableName}
   		        where  teamId = {$teamId};";
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
     * @param NMUserTeam $o
     * @param string $sql
     * @return NMUserTeam
     */
    public function create($o, $sql = "")
    {
        $this->sysLog->debug();
        $o->clearChanges();
        parent::create($o);
        return $o;
    }

    /**
     * @param NMUserTeam $o
     * @param string $idColumn
     * @param string $sql
     * @return mixed
     */
    public function update($o, $idColumn = "id", $sql = "")
    {
        $this->sysLog->debug();
        $o->clearChanges();
        return parent::update($o);
    }

    /**
     * @param NMUserTeam $o
     * @param string $idColumn
     * @param string $sql
     * @return mixed
     */
    public function delete($o, $idColumn = "id", $sql = "")
    {
        $this->sysLog->debug();
        $o->clearChanges();
        return parent::delete($o);
    }

    /**
     * @param NMUserTeam $s
     * @return NMUserTeam
     */
    public function save(NMUserTeam $s)
    {
        $this->sysLog->debug();
        $o = $this->getByUserIdTeamId($s->getUserId(), $s->getTeamId());
        if ($o->getUserId()) {
            return $this->update($s);
        } else {
            return $this->create($s);
        }
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
     * @return NMUserTeam
     */
    private function _set($dbRowObj = null)
    {
        $this->sysLog->debug();
        $columns     = $this->getColumns();
        $numberTypes = $this->getNumberTypes();

        $o = new NMUserTeam();
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
