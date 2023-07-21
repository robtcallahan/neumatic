<?php

namespace Neumatic\Model;

use STS\Database\DBTable;


class NMServerTable extends DBTable
{
    protected $dbIndex = 'neumatic';
   	protected $tableName = 'server';
    protected $idAutoIncremented = true;

	protected $columnNames = array(
        "id",
        "name",
        "oldName",
        "serverType",

        "ownerId",
        "teamId",
        "ldapUserGroup",
        "ldapHostGroup",

        "sysId",
        "businessServiceName",
        "businessServiceId",
        "subsystemName",
        "subsystemId",
        "cmdbEnvironment",

        "description",

        "location",
        "locationId",

        "network",
        "subnetMask",
        "gateway",
        "macAddress",
        "ipAddress",

        "cobblerServer",
        "cobblerDistro",
        "cobblerKickstart",
        "cobblerMetadata",

        "remoteServer",

        "chefServer",
        "chefRole",
        "chefEnv",

        "dateCreated",
        "userCreated",
        "dateUpdated",
        "userUpdated",

        "okToBuild",
        "buildStep",
        "buildSteps",
        "status",
        "statusText",

        "timeBuildStart",
        "timeBuildEnd",

        "dateBuilt",
        "userBuilt",
        "dateFirstCheckin",

        "archived"
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
     * @return NMServer
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
     * @return NMServer
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
     * @param string $username
     * @param string $listType
     * @return NMServer[]
     */
	public function getByUsername($username, $listType = 'current')
	{
        if ($listType == 'current') {
            $sqlListType = "and archived = 0\n";
        } else if ($listType == 'archived') {
            $sqlListType = "and archived = 1\n";
        } else if ($listType == 'building') {
            $sqlListType = "and status = 'Building'\n";
        } else {
            $sqlListType = "\n";
        }

        // convert the comma-separate query columns string to an array then add "t." in front of each
        // so we can perform a join in the query
        $propsArray = array();
        $stringArray = explode(",", $this->getQueryColumnsStr());
        foreach ($stringArray as $prop) {
            $propsArray[] = "t.{$prop}";
        }
        $columns = implode(",", $propsArray);

        $sql = "select {$columns},
                       case when u.id is not null then u.username
                            else t.userCreated
                       end as owner
                from   {$this->tableName} t
                left outer join user u on u.id = t.ownerId
		        where  ((t.userCreated = '" . $username . "' and u.id is null)
		           or  u.username = '" . $username . "')\n";
        $sql .= $sqlListType;
		$sql .= "order by name;";
        $result = $this->sqlQuery($sql);
        $array  = array();
        for ($i = 0; $i < count($result); $i++) {
            $array[] = $this->_set($result[$i]);
        }
        return $array;
	}

    /**
     * @param int $userId
     * @param string $listType
     * @internal param bool $includeArchived
     * @return NMServer[]
     */
	public function getByUserId($userId, $listType = 'current')
	{
        if ($listType == 'current') {
            $sqlListType = "and archived = 0\n";
        } else if ($listType == 'archived') {
            $sqlListType = "and archived = 1\n";
        } else if ($listType == 'building') {
            $sqlListType = "and status = 'Building'\n";
        } else {
            $sqlListType = "\n";
        }

        $sql = "select {$this->getQueryColumnsStr()}
                from   {$this->tableName}
		        where  userId = {$userId}\n";
        $sql .= $sqlListType;
		$sql .= "order by name;";
        $result = $this->sqlQuery($sql);
        $array  = array();
        for ($i = 0; $i < count($result); $i++) {
            $array[] = $this->_set($result[$i]);
        }
        return $array;
	}

    /**
     * @param int $teamId
     * @param string $listType
     * @internal param bool $includeArchived
     * @return NMServer[]
     */
	public function getByTeamId($teamId, $listType = 'current')
	{
        if ($listType == 'current') {
            $sqlListType = "and archived = 0\n";
        } else if ($listType == 'archived') {
            $sqlListType = "and archived = 1\n";
        } else if ($listType == 'building') {
            $sqlListType = "and status = 'Building'\n";
        } else {
            $sqlListType = "\n";
        }

        $sql = "select {$this->getQueryColumnsStr()}
                from   {$this->tableName}
		        where  teamId = {$teamId}\n";
        $sql .= $sqlListType;
		$sql .= "order by name;";
        $result = $this->sqlQuery($sql);
        $array  = array();
        for ($i = 0; $i < count($result); $i++) {
            $array[] = $this->_set($result[$i]);
        }
        return $array;
	}

    /**
     * @param string $ldapUserGroup
     * @param string $listType
     * @return NMServer[]
     */
	public function getByLdapUserGroup($ldapUserGroup, $listType = 'current')
	{
        if ($listType == 'current') {
            $sqlListType = "and archived = 0\n";
        } else if ($listType == 'archived') {
            $sqlListType = "and archived = 1\n";
        } else if ($listType == 'building') {
            $sqlListType = "and status = 'Building'\n";
        } else {
            $sqlListType = "\n";
        }

        $sql = "select {$this->getQueryColumnsStr()}
                from   {$this->tableName}
		        where  ldapUserGroup = '{$ldapUserGroup}'\n";
        $sql .= $sqlListType;
		$sql .= "order by name;";
        $result = $this->sqlQuery($sql);
        $array  = array();
        for ($i = 0; $i < count($result); $i++) {
            $array[] = $this->_set($result[$i]);
        }
        return $array;
	}



    /**
     * @return NMServer[]
     */
   	public function getAllLabVMs()
   	{
   		$this->sysLog->debug();
		$sql = "select {$this->getQueryColumnsStr()}
                from   {$this->tableName}
                where  name like 'stlabvnode%';";
   		$result = $this->sqlQuery($sql);
   		$array  = array();
   		for ($i = 0; $i < count($result); $i++) {
   			$array[] = $this->_set($result[$i]);
   		}
   		return $array;
   	}

    /**
     * @param string $listType
     * @return NMServer[]
     */
   	public function getAll($listType = 'current')
   	{
        $orderBy = "name";
        $dir = "asc";

        if ($listType == 'current') {
            $sqlListType = "where archived = 0\n";
        } else if ($listType == 'archived') {
            $sqlListType = "where archived = 1\n";
        } else if ($listType == 'building') {
            $sqlListType = "where status = 'Building'\n";
        } else {
            $sqlListType = "\n";
        }

   		$this->sysLog->debug();
		$sql = "select {$this->getQueryColumnsStr()}
                from   {$this->tableName}\n";
        $sql .= $sqlListType;
   		$sql .= "order by {$orderBy} {$dir};";
   		$result = $this->sqlQuery($sql);
   		$array  = array();
   		for ($i = 0; $i < count($result); $i++) {
   			$array[] = $this->_set($result[$i]);
   		}
   		return $array;
   	}

    public function getNumBuilding() {
        $sql = "select count(*) as numServers
                from   {$this->tableName}
                where  status = 'Building'";
        $row = $this->sqlQueryRow($sql);
        return $row->numServers;
    }

    public function getServerCountByUsername() {
        $sql = "select userCreated, count(name) as numServers
                from   {$this->tableName}
                where  userCreated is not null
                group by userCreated";
        return $this->sqlQuery($sql);
    }

    public function getBuildMetrics()
   	{
        // exclude any build times over 1 hour
		$sql = "select s.name as serverName,
		               case when s.dateBuilt is not null
		                    then s.dateBuilt
		                    else s.timeBuildEnd
		               end as dateBuilt,
		               s.serverType,
		               s.timeBuildStart,
		               s.timeBuildEnd,
		               timediff(s.timeBuildEnd, s.timeBuildStart) as buildTime,
                       timestampdiff(SECOND, s.timeBuildStart, s.timeBuildEnd) as buildTimeSecs
       		    from server s
		        where s.timeBuildStart is not null
		          and s.timeBuildEnd is not null
		          and timediff(s.timeBuildEnd, s.timeBuildStart) < '01:00:00'
		        order by dateBuilt desc";
   		$results = $this->sqlQuery($sql);
   		return $results;
   	}


    public function getAverageBuildTimes()
   	{
        // exclude any build times over 1 hour
		$sql = "select avg(timestampdiff(SECOND, s.timeBuildStart, s.timeBuildEnd)) as avgBuildTimeSecs
       		    from server s
		        where s.timeBuildStart is not null
		          and s.timeBuildEnd is not null
		          and s.serverType = 'vmware'
		          and timediff(s.timeBuildEnd, s.timeBuildStart) < '01:00:00'
		        order by dateBuilt desc";
   		$results1 = $this->sqlQueryRow($sql);

        $sql = "select avg(timestampdiff(SECOND, s.timeBuildStart, s.timeBuildEnd)) as avgBuildTimeSecs
             		    from server s
      		        where s.timeBuildStart is not null
      		          and s.timeBuildEnd is not null
      		          and s.serverType = 'blade'
      		          and timediff(s.timeBuildEnd, s.timeBuildStart) < '01:00:00'
      		        order by dateBuilt desc";
        $results2 = $this->sqlQueryRow($sql);

        $sql      = "select avg(timestampdiff(SECOND, s.timeBuildStart, s.timeBuildEnd)) as avgBuildTimeSecs
             		    from server s
      		        where s.timeBuildStart is not null
      		          and s.timeBuildEnd is not null
      		          and s.serverType = 'remote'
      		          and timediff(s.timeBuildEnd, s.timeBuildStart) < '01:00:00'
      		        order by dateBuilt desc";
        $results3 = $this->sqlQueryRow($sql);

        $sql      = "select avg(timestampdiff(SECOND, s.timeBuildStart, s.timeBuildEnd)) as avgBuildTimeSecs
             		    from server s
      		        where s.timeBuildStart is not null
      		          and s.timeBuildEnd is not null
      		          and s.serverType = 'standalone'
      		          and timediff(s.timeBuildEnd, s.timeBuildStart) < '01:00:00'
      		        order by dateBuilt desc";
        $results4 = $this->sqlQueryRow($sql);

        return array(
            "vmware" => sprintf("%02d:%02d", floor($results1->avgBuildTimeSecs / 60), $results1->avgBuildTimeSecs % 60),
            "blades" => sprintf("%02d:%02d", floor($results2->avgBuildTimeSecs / 60), $results2->avgBuildTimeSecs % 60),
            "remotes" => sprintf("%02d:%02d", floor($results3->avgBuildTimeSecs / 60), $results3->avgBuildTimeSecs % 60),
            "standalones" => sprintf("%02d:%02d", floor($results4->avgBuildTimeSecs / 60), $results4->avgBuildTimeSecs % 60)
        );
   	}


	// *******************************************************************************
	// CRUD methods
	// *******************************************************************************

    /**
     * @param NMServer $o
     * @param string $sql
     * @return NMServer
     */
    public function create($o, $sql="")
	{
		$this->sysLog->debug();
        $o->clearChanges();
		$newId = parent::create($o);
		return $this->getById($newId);
	}

    /**
     * @param NMServer $o
     * @param string $idColumn
     * @param string $sql
     * @return NMServer
     */
    public function update($o, $idColumn = "id", $sql = "")
	{
		$this->sysLog->debug();
		$o = parent::update($o);
        return $this->getById($o->getId());
	}

    /**
     * @param NMServer $o
     * @param string $idColumn
     * @param string $sql
     * @return NMServer
     */
    public function delete($o, $idColumn = "id", $sql = "")
	{
		$this->sysLog->debug();
        $o->clearChanges();
		return parent::delete($o);
	}

    /**
     * @param NMServer $s
     * @return NMServer
     */
    public function save(NMServer $s)
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
	 * @return NMServer
	 */
	private function _set($dbRowObj = null)
	{
		$this->sysLog->debug();
        $columns = $this->getColumns();
        $numberTypes = $this->getNumberTypes();

		$o = new NMServer();
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
