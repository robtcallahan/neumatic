<?php

namespace Neumatic\Model;

use STS\Database\DBTable;


class NMRatingTable extends DBTable
{
    protected $dbIndex = 'neumatic';
    protected $tableName = 'rating';
    protected $idAutoIncremented = true;


	protected $columnNames = array(
        "id",
		"userId",
        "rating",
        "dateRated",
        "comments",
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
	 * @return NMRating
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
   	 * @return array
   	 */
   	public function getUserRatings()
   	{
   		$this->sysLog->debug();
		$sql = "select r.id, r.userId, r.rating, r.dateRated, r.comments,
                       u.username, u.firstName, u.lastName, u.dateCreated as userSince
                from   rating r,
                       user u
                where  u.id = r.userId
   		        order by r.dateRated desc;";
   		$result = $this->sqlQuery($sql);
   		return $result;
   	}

	// *******************************************************************************
	// CRUD methods
	// *******************************************************************************

   /**
     * @param NMRating $o
     * @param string $sql
     * @return mixed
     */
    public function create($o, $sql="")
	{
		$this->sysLog->debug();
		return parent::create($o);
	}

    /**
     * @param NMRating $o
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
     * @param NMRating $o
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
	 * @return NMRating
	 */
    private function _set($dbRowObj = null)
   	{
        $this->sysLog->debug();
        $columns = $this->getColumns();
        $numberTypes = $this->getNumberTypes();

   		$o = new NMRating();
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
