<?php

namespace Neumatic\Model;

use STS\Database\DBTable;
use Zend\Db\Adapter\Adapter;


class Neumatic extends DBTable
{
    protected $dbIndex = 'neumatic';
   	protected $tableName = 'server';
    protected $idAutoIncremented = true;

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
	}

    /**
     * @return array()
     */
	public function getLeases()
	{
        $sql = "select s.id as serverId, s.name as serverName, s.userCreated as owner,
                       l.id as leaseId, l.leaseStart, l.leaseDuration, l.expired, l.extensionInDays,
                       l.numExtensionsAllowed, l.numTimesExtended
                from   server s, lease l
		        where  s.id = l.serverId
		        order by s.name;";
        $results = $this->adapter->query($sql, Adapter::QUERY_MODE_EXECUTE);
   		return $results->toArray();
	}

    /**
     * @param $userId
     * @return NMTeam[]
     */
    public function getUserTeams($userId) {
        $sql = "select t.id, t.name, t.ownerId
                from   team t, user_team ut
                where  t.id = ut.teamId
                  and  ut.userId = {$userId};";
        $results = $this->adapter->query($sql, Adapter::QUERY_MODE_EXECUTE);
   		$resultsArray = $results->toArray();

        $teams = array();
        foreach ($resultsArray as $r) {
            $team = new NMTeam();
            foreach (array('id', 'name', 'ownerId') as $prop) {
                if (array_key_exists($prop, $r)) {
                    $team->set($prop, $r[$prop]);
                }
            }
            $teams[] = $team;
        }
        return $teams;
    }

	// *******************************************************************************
	// * Getters and Setters
	// *****************************************************************************

}
