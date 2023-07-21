<?php

namespace Neumatic\Model;

use STS\Database\DBTable;


class NMVLANDHCPRelayTable extends DBTable
{
    protected $dbIndex = 'neumatic';
    protected $tableName = 'vlan_dhcp_relay';
    protected $idAutoIncremented = true;


	protected $columnNames = array(
        "id",
        "vlanId",
        "address",
        "enabled",
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
	 * @return NMVLANDHCPRelay
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
   	 * @param string $orderBy
   	 * @param string $dir
   	 * @return NMVLANDHCPRelay[]
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
     * @param NMVLANDHCPRelay $o
     * @param string $sql
     * @return mixed
     */
    public function create($o, $sql="")
	{
		$this->sysLog->debug();
		return parent::create($o);
	}

    /**
     * @param NMVLANDHCPRelay $o
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
     * @param NMVLANDHCPRelay $o
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
	 * @return NMVLANDHCPRelay
	 */
    private function _set($dbRowObj = null)
   	{
        $this->sysLog->debug();
        $columns = $this->getColumns();
        $numberTypes = $this->getNumberTypes();

   		$o = new NMVLANDHCPRelay();
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
