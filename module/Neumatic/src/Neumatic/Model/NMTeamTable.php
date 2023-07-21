<?php

namespace Neumatic\Model;

use STS\Database\DBTable;


class NMTeamTable extends DBTable
{
    protected $dbIndex = 'neumatic';
    protected $tableName = 'team';
    protected $idAutoIncremented = true;
    protected $tableAlias = "t";


    protected $columnNames = array(
        "id",
        "name",
        "ownerId",
    );

    protected static $joinTables = array(
        array(
            'table'    => "user",
            'alias'    => "u1",
            'joinType' => "left",
            'joinTo'   => "id",
            'joinFrom' => "ownerId",
            'columns'  => array(
                "firstName"  => "ownerFirstName",
                "lastName"   => "ownerLastName",
                "username"   => "ownerUsername",
                "email"      => "ownerEmail",
            )
        ),
    );

    protected $select;
    protected $from;
    protected $join;
    protected $where;


    public function __construct($config = null)
    {
        if ($config) {
            // need to add these to the config since won't be in the config file
            $config['tableName']         = $this->tableName;
            $config['dbIndex']           = $this->dbIndex;
            $config['idAutoIncremented'] = $this->idAutoIncremented;
        }

        parent::__construct($config);

        $this->select   = "";
        $tmpArray = array();
        foreach ($this->columnNames as $prop) {
            $tmpArray[] = "{$this->tableAlias}.{$prop}";
        }

        $this->select = "select " . implode(",\n\t", $tmpArray);
        if (isset(self::$joinTables) && count(self::$joinTables) > 0) {
            $this->select .= ",";
        }
        $this->select .= "\n";

        $this->from = "from {$this->tableName} as {$this->tableAlias}\n";
        $this->join = "";
        $this->where = "where 1 = 1\n";
        for ($i=0; $i<count(self::$joinTables); $i++) {
            $jt = self::$joinTables[$i];
            if ($i > 0) {
                $this->select .= ",\n";
            }

            $tmpArray = array();
            foreach ($jt['columns'] as $name => $alias) {
                $tmpArray[] = "{$jt['alias']}.{$name} as \"{$alias}\"";
            }
            $this->select .= implode(",\n\t", $tmpArray);

            $this->join .= "{$jt['joinType']} join {$jt['table']} {$jt['alias']} on {$jt['alias']}.{$jt['joinTo']} = {$this->tableAlias}.{$jt['joinFrom']}";
            if (array_key_exists('joinAnd', $jt)) {
                $this->join .= " and {$jt['joinAnd']}";
            };
            $this->join .= "\n";

            if (array_key_exists('where', $jt) && $jt['where']) {
                $this->where .= " and {$jt['where']}\n";
            }
        }
        $this->select .= "\n";


        $this->query = $this->select . $this->from . $this->join . $this->where;
    }

    /**
     * @param $id
     * @return NMTeam
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
     * @param string $name
     * @return NMTeam
     */
    public function getByName($name)
    {
        $this->sysLog->debug("name=" . $name);
        $sql    = "select {$this->getQueryColumnsStr()}
                   from   {$this->tableName}
                   where  name = '{$name}'";
        $result = $this->sqlQueryRow($sql);
        return $this->_set($result);
    }

    /**
   	 * @return NMTeam[]
   	 */
   	public function getIdHash()
   	{
		$sql = "select {$this->getQueryColumnsStr()}
                from   {$this->tableName};";
   		$result = $this->sqlQuery($sql);
   		$hash  = array();
   		for ($i = 0; $i < count($result); $i++) {
   			$t = $this->_set($result[$i]);
            $hash[$t->getId()] = $t;
   		}
   		return $hash;
   	}

    /**
   	 * @param string $orderBy
   	 * @param string $dir
   	 * @return NMTeam[]
   	 */
   	public function getAll($orderBy = "name", $dir = "asc")
   	{
        $sql = $this->query . "\n order by {$orderBy} {$dir};";
   		$result = $this->sqlQuery($sql);
   		$array  = array();
        foreach ($result as $r) {
   			$array[] = $this->_set($r);
   		}
   		return $array;
   	}


    // *******************************************************************************
    // CRUD methods
    // *******************************************************************************

    /**
     * @param NMTeam $o
     * @param string $sql
     * @return NMTeam
     */
    public function create($o, $sql = "")
    {
        $newId = parent::create($o);
        return $this->getById($newId);
    }

    /**
     * @param NMTeam $o
     * @param string $idColumn
     * @param string $sql
     * @return mixed
     */
    public function update($o, $idColumn = "id", $sql = "")
    {
        return parent::update($o);
    }

    /**
     * @param NMTeam $o
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
     * @param NMTeam $s
     * @return NMTeam
     */
    public function save(NMTeam $s)
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
     * @return NMTeam
     */
    private function _set($dbRowObj = null)
    {
        $this->sysLog->debug();
        $columns     = $this->getColumns();
        $numberTypes = $this->getNumberTypes();

        $o = new NMTeam();
        foreach ($this->columnNames as $prop) {
            if ($dbRowObj && array_key_exists($prop, $dbRowObj)) {
                if (preg_match($numberTypes, $columns[$prop]['type'])) {
                    $o->set($prop, intval($dbRowObj[$prop]));
                } else {
                    $o->set($prop, $dbRowObj[$prop]);
                }
            } else {
                $o->set($prop, null);
            }
        }
        if (isset(self::$joinTables) && count(self::$joinTables)) {
            foreach (self::$joinTables as $jt) {
                foreach ($jt['columns'] as $prop) {
                    if ($dbRowObj && array_key_exists($prop, $dbRowObj)) {
                        $o->set($prop, $dbRowObj[$prop]);
                    } else {
                        $o->set($prop, null);
                    }
                }
            }
        }
        return $o;
    }
}
