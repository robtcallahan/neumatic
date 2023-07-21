<?php

namespace Neumatic\Model;

use STS\Database\DBTable;


class NMUserTable extends DBTable
{
    protected $dbIndex = 'neumatic';
    protected $tableName = 'user';
    protected $idAutoIncremented = true;

    protected $columnNames = array(
        "id",
        "firstName",
        "lastName",
        "username",
        "empId",
        "title",
        "dept",
        "office",
        "email",
        "officePhone",
        "mobilePhone",
        "userType",
        "numServerBuilds",
        "maxPoolServers",
        "dateCreated",
        "userCreated",
        "dateUpdated",
        "userUpdated"
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
     * @param $id
     * @return NMUser
     */
    public function getById($id)
    {
        $this->sysLog->debug("id=" . $id);
        $sql = "select {$this->getQueryColumnsStr()}
                from   {$this->tableName}
		        where  id = {$id};";
        $row = $this->sqlQueryRow($sql);
        return $this->_set($row);
    }

    /**
     * @param string $userName
     * @return NMUser
     */
    public function getByUserName($userName)
    {
        $this->sysLog->debug("userName=" . $userName);
        $sql    = "select {$this->getQueryColumnsStr()}
                from   {$this->tableName}
                where  userName = '{$userName}'";
        $result = $this->sqlQueryRow($sql);
        return $this->_set($result);
    }

    /**
     * @param string $lastName
     * @return NMUser
     */
    public function getByLastName($lastName)
    {
        $this->sysLog->debug("lastName=" . $lastName);

        $sql    = "select {$this->getQueryColumnsStr()}
                from   {$this->tableName}
                where  lastName = '{$lastName}'";
        $result = $this->sqlQueryRow($sql);
        return $this->_set($result);
    }

    /**
   	 * @param string $orderBy
   	 * @param string $dir
   	 * @return NMUser[]
   	 */
   	public function getAll($orderBy = "lastName", $dir = "asc")
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

    /**
   	 * @param string $orderBy
   	 * @param string $dir
   	 * @return mixed
   	 */
   	public function getLogins($orderBy = "lastName", $dir = "asc")
   	{
		$sql = "select u.id, u.firstName, u.lastName, u.username, u.title,
		               u.dept, u.email, u.userType, u.numServerBuilds,
		               l.numLogins, l.lastLogin
                from   user u, login l
                where  l.userId = u.id
   		        order by {$orderBy} {$dir};";
   		return $this->sqlQuery($sql);
   	}

    /**
   	 * @param string $orderBy
   	 * @param string $dir
   	 * @return mixed
   	 */
   	public function getLoginsThisMonth($orderBy = "lastName", $dir = "asc")
   	{
		$sql = "select u.id, u.firstName, u.lastName, u.username, u.title, u.dept, u.email, u.userType, u.numServerBuilds,
		               l.numLogins, l.lastLogin, l.ipAddr, l.userAgent
                from   user u, login l
                where  l.userId = u.id
                  and  DATE_SUB(CURDATE(),INTERVAL 30 DAY) <= l.lastLogin
   		        order by {$orderBy} {$dir};";
   		return $this->sqlQuery($sql);
   	}

    /**
   	 * @param string $orderBy
   	 * @param string $dir
   	 * @return mixed
   	 */
   	public function getLoginsThisWeek($orderBy = "lastName", $dir = "asc")
   	{
		$sql = "select u.id, u.firstName, u.lastName, u.username, u.title, u.dept, u.email, u.userType, u.numServerBuilds,
		               l.numLogins, l.lastLogin, l.ipAddr, l.userAgent
                from   user u, login l
                where  l.userId = u.id
                  and  DATE_SUB(CURDATE(),INTERVAL 7 DAY) <= l.lastLogin
   		        order by {$orderBy} {$dir};";
   		return $this->sqlQuery($sql);
   	}

    public function getNumBuilds() {
        $sql = "select sum(numServerBuilds) as numServerBuilds
                from {$this->tableName};";
        return $this->sqlQueryRow($sql);
    }

    /**
     * @param string $query
     * @return NMUser[]
     */
    public function getByNameLike($query = "")
    {
        $this->sysLog->debug();

        $sql = "select {$this->getQueryColumnsStr()}
                from   {$this->tableName}\n";

        if ($query !== "") {
            $sql .= "WHERE lastName like '%{$query}%' OR firstName like '%{$query}%' OR userName like '%{$query}%'\n";
        }
        $sql .= "ORDER  BY lastName;";
        $rows = $this->sqlQuery($sql);

        $users = array();
        for ($i = 0; $i < count($rows); $i++) {
            $users[] = $this->_set($rows[$i]);
        }
        return $users;
    }

    /**
     * @return NMUser[]
     */
    public function getIdHash()
    {
        $this->sysLog->debug();

        $sql  = "select {$this->getQueryColumnsStr()}
                from   {$this->tableName};";
        $rows = $this->sqlQuery($sql);

        $userHash = array();
        for ($i = 0; $i < count($rows); $i++) {
            $u                     = $this->_set($rows[$i]);
            $userHash[$u->getId()] = $u;
        }
        return $userHash;
    }

    /**
     * @return NMUser[]
     */
    public function getUserNameHash()
    {
        $this->sysLog->debug();

        $sql  = "select {$this->getQueryColumnsStr()}
                from   {$this->tableName};";
        $rows = $this->sqlQuery($sql);

        $userHash = array();
        for ($i = 0; $i < count($rows); $i++) {
            $u                           = $this->_set($rows[$i]);
            $userHash[$u->getUserName()] = $rows[$i];
        }
        return $userHash;
    }


    // *******************************************************************************
    // CRUD methods
    // *******************************************************************************

    /**
     * @param NMUser $o
     * @param string $sql
     * @return NMUser
     */
    public function create($o, $sql = "")
    {
        $this->sysLog->debug();
        $newId = parent::create($o);
        return $this->getById($newId);
    }

    /**
     * @param NMUser $o
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
     * @param NMUser $o
     * @param string $idColumn
     * @param string $sql
     * @return mixed
     */
    public function delete($o, $idColumn = "id", $sql = "")
    {
        $this->sysLog->debug();
        return parent::delete($o);
    }

    /**
     * @param NMUser $s
     * @return NMUser
     */
    public function save(NMUser $s)
    {
        $this->sysLog->debug();
        $o = $this->getByUserName($s->getUsername());
        if ($o->getId()) {
            $s->setId($o->getId());
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
     * @return NMUser
     */
    private function _set($dbRowObj = null)
    {
        $this->sysLog->debug();
        $columns     = $this->getColumns();
        $numberTypes = $this->getNumberTypes();

        $o = new NMUser();
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
