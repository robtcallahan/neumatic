<?php
namespace Neumatic\Controller;

use Zend\Mvc\MvcEvent;
use Zend\View\Model\JsonModel;
use Zend\Log\Logger;

use Neumatic\Model;
use STS\LDAP;

// TODO: Actions in this controller should be directed at LDAP only and not the local database
class LDAPController extends Base\BaseController
{
    protected $glassServer;

    /** @var  Model\NMUsergroupTable */
    protected $usergroupTable;
    /** @var  Model\NMNodeTable */
    protected $nodeTable;
    /** @var  Model\NMServerTable  */
    protected $serverTable;
    /** @var  Model\NMHostgroupTable */
    protected $hostgroupTable;
    /** @var  Model\NMNodeToUsergroupTable */
    protected $nodeToUsergroupTable;

    protected $timeStart;

    public function onDispatch(MvcEvent $e) {
        $this->glassServer          = $this->_config['glass']['server'];
        $this->timeStart            = microtime(true);
        $this->defaultCacheLifetime = "300";
        $this->checkCache();

        $this->usergroupTable = new Model\NMUsergroupTable($this->_config);
        $this->nodeTable = new Model\NMNodeTable($this->_config);
        $this->serverTable = new Model\NMServerTable($this->_config);
        $this->hostgroupTable = new Model\NMHostgroupTable($this->_config);
        $this->nodeToUsergroupTable = new Model\NMNodeToUsergroupTable($this->_config);

        return parent::onDispatch($e);
    }

    public function indexAction()
    {
        return $this->renderView(array("message" => "This controller has no output from index."));
    }

    // *****************************************************************************************************************
    // User methods
    // *****************************************************************************************************************

    private function getUser($uid) {
        if (!$response = $this->larpCall("https://" . $this->glassServer . "/larp/user/read/" . $uid)) {
            return false;
        }
        $user = $response->output->records[0];
        return $user;
    }

    /**
     * Look up the user in LDAP
     * @return JsonModel
     */
    public function getUserAction() {
        $uid  = $this->params()->fromRoute('param1');

        if (!$user = $this->getUser($uid)) {
            return $this->renderView($this->_viewData);
        }
        /** @noinspection PhpUndefinedFieldInspection */
        return $this->renderView(array(
            "success" => true,
            "found" => $user->cn ? true : false,
            "user" => $user
        ));
    }

    /**
     * Retrieve a list of LDAP Netgroups that the user is a member of
     * @return JsonModel
     */

    public function getUserGroupsAction() {
        $uid = $this->params()->fromRoute('param1');

        if (!$userGroups = $this->getLdapUserGroups($uid)) {
            return $this->renderView($this->_viewData);
        }
        $objArray = array();
        foreach ($userGroups as $g) {
            $objArray[] = array("name" => $g);
        }
        return $this->renderView(array(
            "success" => true,
            "logOutput" => count($userGroups) . " user groups retrieved",
            "found" => count($userGroups) > 0 ? true : false,
            "userGroups" => $userGroups,
            "groups" => $userGroups,
            "asObject" => $objArray
         ));
    }

    // *****************************************************************************************************************
    // Host methods
    // *****************************************************************************************************************

    /**
     * @param $hostName
     * @return array
     */
    private function getHost($hostName) {
        if (!$response = $this->larpCall("https://" . $this->glassServer . "/larp/host/read/" . $hostName)) {
            return array();
        }
        return $response->output->records;
    }

    /**
     * Lookup a host in LDAP
     * @return JsonModel
     */
    public function getHostAction() {
        $hostName = $this->params()->fromRoute('param1');
        if (!$ldapHost = $this->getHost($hostName)) {
            return $this->renderView($this->_viewData);
        }
        return $this->renderView(array(
            "success" => true,
            "host" => $ldapHost,
            "logOutput" => "LDAP host {$hostName} retrieved.",
            "logLevel" => Logger::INFO
        ));
    }


    /**
     * @param $hostName
     * @return bool
     */
    public function deleteHost($hostName) {
        $host = $this->getHost($hostName);
        if (count($host) > 0) {
            // try first with logged in user then again with stsapps account (member of CoreSA_user)
	        if (!$results = $this->larpCall("https://" . $this->glassServer . "/larp/host/delete/" . $hostName)) {
	            if (!$results = $this->larpCall("https://" . $this->glassServer . "/larp/host/delete/" . $hostName,
	                                            $this->_config['stsappsUser'],
	                                            $this->_config['stsappsPassword'])) {
	                return false;
	            }
	        }
	        return true;
        }
        return true;
    }

    public function getHostHostgroupsAction() {
        $hostName = $this->params()->fromRoute('param1');
        if (!$hostgroups = $this->getLdapHostHostgroups($hostName)) {
            return $this->renderView(
                array(
                    "success" => false,
                    "error" => "Could not get hostgroups for " . $hostName,
                    "logLevel" => Logger::ERR,
                    "logOutput" => "Could not get hostgroups for " . $hostName
                )
            );
        }
        return $this->renderView(array(
            "success" => true,
            "hostgroups" => $hostgroups
        ));
    }

    public function getHostUsergroupsAction() {
        $hostName = $this->params()->fromRoute('param1');
        if (!$usergroups = $this->getLdapHostUsergroups($hostName)) {
            return $this->renderView(
                array(
                    "success" => false,
                    "error" => "Could not get usergroups for " . $hostName,
                    "logLevel" => Logger::ERR,
                    "logOutput" => "Could not get usergroups for " . $hostName
                )
            );
        }
        return $this->renderView(array(
            "success" => true,
            "usergroups" => $usergroups
        ));
    }

    /**
     * @param $hostName
     * @param $groupName
     * @return bool
     */
    private function addHostToHostgroup($hostName, $groupName) {
        if (!$results = $this->larpCall("https://" . $this->glassServer . "/larp/host/modify/" . $hostName . "/add/hostgroup/" . $groupName, "stsapps", 'k8btJS$aVrs')) {
            return false;
        }
        return true;
    }

    public function addHostToUsergroupAction() {
        $hostName = $this->params()->fromRoute('param1');
        $groupName = $this->params()->fromRoute('param2');
        $this->addHostToUsergroup($hostName, $groupName);

        return $this->renderView(
            array(
                "success" => true
            )
        );
    }

    /**
     * @param $hostName
     * @param $groupName
     * @return bool
     */
    private function addHostToUsergroup($hostName, $groupName) {
        /*
        if (!$results = $this->larpCall("https://" . $this->glassServer . "/larp/host/modify/" . $hostName . "/add/usergroup/" . $groupName)) {
            return false;
        }
        return true;
        */
        if (!$this->addHostToUsergroupViaDirectLdap($hostName, $groupName)) {
            return $this->renderView(
                array(
                    "success" => false,
                    "logLevel" => Logger::ERR,
                    "error" => "Could not delete host " . $hostName . " from LDAP",
                    "logOutput" => "Could not delete host " . $hostName . " from LDAP"
                )
            );
        }
    }

    /**
     * @param $hostName
     * @param $groupName
     * @return bool
     */
    private function deleteHostFromHostgroup($hostName, $groupName) {
        // try first with logged in user then again with stsapps account (member of CoreSA_user)
        if (!$results = $this->larpCall("https://" . $this->glassServer . "/larp/host/modify/" . $hostName . "/delete/hostgroup/" . $groupName)) {
            if (!$results = $this->larpCall("https://" . $this->glassServer . "/larp/host/modify/" . $hostName . "/delete/hostgroup/" . $groupName,
                                            $this->_config['stsappsUser'],
                                            $this->_config['stsappsPassword'])) {
                return false;
            }
        }
        return true;
    }

    public function deleteHostFromHostgroupAction() {
        $hostName = $this->params()->fromRoute('param1');
        $groupName = $this->params()->fromRoute('param2');

        if (!$this->deleteHostFromHostgroup($hostName, $groupName)) {
            return $this->renderView(
                array(
                    "success" => false,
                    "logLevel" => Logger::ERR,
                    "error" => "Could not delete host " . $hostName . " from hostgroup " . $groupName,
                    "logOutput" => "Could not delete host " . $hostName . " from hostgroup " . $groupName
                )
            );
        }
        return $this->renderView(array(
            "success" => true,
            "logLevel" => Logger::NOTICE,
            "logOutput" => $hostName . " deleted from hostgroup " . $groupName,
            "parameters" => "[serverName: {$hostName}, hostgroup: {$groupName}]"
        ));
    }

    /**
     * @param $hostName
     * @param $groupName
     * @return bool
     */
    private function deleteHostFromUsergroup($hostName, $groupName) {
        #error_log("deleteHostFromUsergroup(" . $hostName . "," . $groupName. ")");
        #error_log("trying user");
        // try first with logged in user then again with stsapps account (member of CoreSA_user)
        if (!$results = $this->larpCall("https://" . $this->glassServer . "/larp/host/modify/" . $hostName . "/delete/usergroup/" . $groupName)) {
            #error_log("user failed. results=" . print_r($results, true));
            #error_log("trying stsapps");
            if (!$results = $this->larpCall("https://" . $this->glassServer . "/larp/host/modify/" . $hostName . "/delete/usergroup/" . $groupName,
                $this->_config['stsappsUser'],
                $this->_config['stsappsPassword'])) {
                #error_log("stsapps failed. results=" . print_r($results, true));
                #error_log("returning false");
                return false;
            }
        }
        #error_log("returning true");
        return true;
    }

    public function deleteHostFromUsergroupAction() {
        $hostName = $this->params()->fromRoute('param1');
        $groupName = $this->params()->fromRoute('param2');

        if (!$this->deleteHostFromUsergroup($hostName, $groupName)) {
            return $this->renderView(
                array(
                    "success" => false,
                    "logLevel" => Logger::ERR,
                    "error" => "Could not delete host " . $hostName . " from hostgroup " . $groupName,
                    "logOutput" => "Could not delete host " . $hostName . " from hostgroup " . $groupName
                )
            );
        }
        return $this->renderView(array(
            "success" => true,
            "logLevel" => Logger::NOTICE,
            "logOutput" => $hostName . " deleted from usergroup " . $groupName,
            "parameters" => "[serverName: {$hostName}, usergroup: {$groupName}]"
        ));
    }

    /**
     * @param $hostName
     * @return bool
     */
    private function deleteHostFromGroups($hostName) {
        // get a list of hostgroups this host is a member of
        $hostgroups = $this->getLdapHostHostgroups($hostName);

        // remove the host from all hostgroups
        foreach($hostgroups as $hostgroup) {
            if (!$this->deleteHostFromHostgroupViaDirectLdap($hostName, $hostgroup)) {
                return false;
            }
        }

        // get a list of usergroups this host is a member of
        $usergroups = $this->getLdapHostUsergroups($hostName);

        // remote the host from all usergroups
        foreach($usergroups as $usergroup) {
            if (!$this->deleteHostFromUsergroupViaDirectLdap($hostName, $usergroup)) {
                return false;
            }
        }

        return true;
    }

    public function getHostNeuOwnerAction() {
        $hostName = $this->params()->fromRoute('param1');
        $neuOwner = $this->getHostNeuOwner($hostName);
        return $this->renderView(array("success" => true, "neuOwner" => $neuOwner));
    }

    // *****************************************************************************************************************
    // Usergroup methods
    // *****************************************************************************************************************

    public function getUsergroupListAction() {
        if (!$usergroups = $this->getUsergroupList()) {
            return $this->renderView($this->_viewData);
        }
        return $this->renderView(array(
            "success" => true, 
            "usergroups" => $usergroups, 
            "logOutput" => "Usergroup list retrieved.",
            "cache"=>true,
            "cacheTTL"=>"3600" 
            ));
    }

    // *****************************************************************************************************************
    // Hostgroup methods
    // *****************************************************************************************************************

    public function getHostGroupsAction() {
        if (!$hostGroups = $this->getLdapHostGroups()) {
            return $this->renderView($this->_viewData);
        }

        $objArray = array();
        foreach ($hostGroups as $g) {
            $objArray[] = array("name" => $g);
        }

        return $this->renderView(array(
            "success" => true,
            "logOutput" => count($hostGroups) . " host groups retrieved",
            "hostGroups" => $hostGroups,
            "asObject" => $objArray
        ));
    }

    // *****************************************************************************************************************
    // Misc methods
    // *****************************************************************************************************************

    public function getPrimaryGroupsAction() {
        $results = $this->getPrimaryGroups();
        $groups  = array();
        foreach ($results as $result) {
            $groups[] = $result->toObject();
        }
        return $this->renderView(array("success" => true, "groups" => $groups));
    }

    public function newGetPrimaryGroupsAction() {
        if (!$getPrimaryGroupsResponse = $this->larpCall("https://" . $this->glassServer . "/larp/glass/primary")) {
            return $this->renderView($this->_viewData);
        }
        foreach ($getPrimaryGroupsResponse->output->records AS $primaryGroup) {
            $getPrimaryGroupDetailsURL      = "https://" . $this->glassServer . "/larp/user/group/read/" . $primaryGroup;
            $this->curlGetUrl($getPrimaryGroupDetailsURL);

            $getSecondaryGroupsURL      = "https://" . $this->glassServer . "/larp/glass/secondary/" . $primaryGroup;
            $getSecondaryGroupsResponse = $this->curlGetUrl($getSecondaryGroupsURL);

            $secondaryGroups = $getSecondaryGroupsResponse->output->records;
            print_r($secondaryGroups);

            //print_r($getPrimaryGroupDetailsResponse->output->records);

        }

        die();

    }

    public function getPrimarySubGroupsAction() {
        $groupName = $this->params()->fromRoute('param1');
        $subGroups = $this->getPrimarySubGroups($groupName);
        return $this->renderView(array("success" => true, "found" => count($subGroups) > 0 ? true : false, "subGroups" => $subGroups));
    }

    // *****************************************************************************************************************
    // NeuMatic methods where a server id is passed rather than a name
    // *****************************************************************************************************************

    public function deleteHostFromLdapAction() {
        $hostName = $this->params()->fromRoute('param1');

        if (!$this->deleteHostFromLdap($hostName)) {
            return $this->renderView(
                array(
                    "success" => false,
                )
            );
        }

        return $this->renderView(array(
            "success" => true,
        ));
    }

    /**
     * @param $hostName
     * @return bool
     */
    private function deleteHostFromLdap($hostName) {
        // first delete from host and user groups
        if (!$this->deleteHostFromGroups($hostName)) {
            return false;
        }

        // delete the host
        if (!$this->deleteHostViaDirectLdap($hostName)) {
            return false;
        }
        return true;
    }

    public function deleteHostAction() {
        $serverId = $this->params()->fromRoute('param1');
        $server = $this->serverTable->getById($serverId);

        if (!$this->deleteHostFromLdap($server->getName())) {
            return $this->renderView(
                array(
                    "success" => false,
                    "logLevel" => Logger::ERR,
                    "error" => "Could not delete host " . $server->getName() . " from LDAP",
                    "logOutput" => "Could not delete host " . $server->getName() . " from LDAP"
                )
            );
        }

        return $this->renderView(array(
            "success" => true,
            "logLevel" => Logger::NOTICE,
            "logOutput" => "{$server->getName()} deleted from LDAP",
            "parameters" => "[serverName: {$server->getName()}]"
        ));
    }

    public function createHostAction() {
        $serverId = $this->params()->fromRoute('param1');

        $server = $this->serverTable->getById($serverId);
        $userGroups = $this->usergroupTable->getByServerId($serverId);
        $hostGroups = $this->hostgroupTable->getByServerId($serverId);

        $userGroupNames = array();
        foreach ($userGroups as $g) {
            $userGroupNames[] = $g->getName();
        }

        $hostGroupNames = array();
        foreach ($hostGroups as $g) {
            $hostGroupNames[] = $g->getName();
        }


        // delete the host first
        if (!$this->deleteHost($server->getName())) {
            return $this->renderView(
                array(
                    "success" => false,
                    "logLevel" => Logger::ERR,
                    "error" => "Could not delete host " . $server->getName() . " from LDAP",
                    "logOutput" => "Could not delete host " . $server->getName() . " from LDAP"
                )
            );
        }

        // determine the primary user group that will be assigned to neuOwner. this will be the
        // first group in the list that is not one of requiredUserGroups in the config file.
        // if none is found then requiredUserGroups[0] (CoreSA_user) will be used.

        // what groups are not in requireUserGroups?
        #error_log("userGroupNames=" . implode(",", $userGroupNames));
        #error_log("requiredUserGroups=" . implode(",", $this->_config['ldap']['requiredUserGroups']));
        $arrayDiff = array_diff($userGroupNames, $this->_config['ldap']['requiredUserGroups']);
        $nonRequiredGroups = array();
        foreach($arrayDiff as $e) {
            $nonRequiredGroups[] = $e;
        }
        #error_log("nonRequiredGroups=" . implode(",", $nonRequiredGroups));
        #error_log("nonRequiredGroups=" . print_r($nonRequiredGroups, true));
        #error_log("type=" . gettype($nonRequiredGroups));
        #error_log("num items=" . count($nonRequiredGroups));

        /*
         * Going to change to just making CoreSA_user neuOwner at all times
        if (count($nonRequiredGroups) == 0) {
            $neuOwner = $this->_config['ldap']['requiredUserGroups'][0];
        } else {
            // choosing the first element. Not ideal, but otherwise we'll have to make UI changes
            $neuOwner = $nonRequiredGroups[0];
        }
        #error_log("neuOwner=" . $neuOwner);
         */
        $neuOwner = "CoreSA_user";

        // create the host, but pause for a sec
        sleep(1);
        if (!$this->larpCall("https://" . $this->glassServer . "/larp/host/create/" .
                             $server->getName() . "/" .
                             $this->_config["ldap"]["requiredHostGroups"][0] . "/" .
                             $neuOwner)) {
            return $this->renderView($this->_viewData);
        }

        // add the user groups
        foreach ($userGroups as $userGroup) {
            // skip the primary user group (neuOwner) since it gets added as a usergroup automatically
            if ($userGroup->getName() == $neuOwner) continue;

            if (!$this->addHostToUsergroupViaDirectLdap($server->getName(), $userGroup->getName())) {
                return $this->renderView(
                    array(
                        "success" => false,
                        "logLevel" => Logger::ERR,
                        "error" => "Could not add usergroup " . $userGroup->getName() . " to host " . $server->getName(),
                        "logOutput" => "Could not add usergroup " . $userGroup->getName() . " to host " . $server->getName()
                    )
                );
            }
        }

        // add the host groups
        foreach ($hostGroups as $hostGroup) {
            if (!$this->addHostToHostgroupViaDirectLdap($server->getName(), $hostGroup->getName())) {
                return $this->renderView(
                    array(
                        "success" => false,
                        "logLevel" => Logger::ERR,
                        "error" => "Could not add host " . $server->getName() . " to hostgroup " . $hostGroup->getName(),
                        "logOutput" => "Could not add host " . $server->getName() . " to hostgroup " . $hostGroup->getName()
                    )
                );
            }
        }

        return $this->renderView(array(
            "success"   => true,
            "logLevel"  => Logger::NOTICE,
            "logOutput" => "{$server->getName()} added to LDAP",
            "parameters" => "[serverName: {$server->getName()}]"
        ));
    }

    // TODO: needs to go in NeuMatic controller
    public function getUsergroupsByServerIdAction() {
        $serverId = $this->params()->fromRoute('param1');

        // method in BaseController for visibility in other controllers
        $groups = $this->getUsergroupsByServerId($serverId);
        $usergroups = array();
        foreach ($groups as $g) {
            $usergroups[] = $g->toObject();
        }
        return $this->renderView(
            array(
                "success" => true,
                "usergroups" => $usergroups,
                "logLevel" => Logger::DEBUG,
                "logOutput" => count($usergroups) . " usergroups returned for serverId " . $serverId
            )
        );
    }

    // TODO: needs to go in NeuMatic controller
    public function getHostgroupsByServerIdAction() {
        $serverId = $this->params()->fromRoute('param1');

        // method in BaseController for visibility in other controllers
        $groups = $this->getHostgroupsByServerId($serverId);
        $hostgroups = array();
        foreach ($groups as $g) {
            $hostgroups[] = $g->toObject();
        }
        return $this->renderView(
            array(
                "success" => true,
                "hostgroups" => $hostgroups,
                "logLevel" => Logger::DEBUG,
                "logOutput" => count($hostgroups) . " hostgroups returned for serverId " . $serverId
            )
        );
    }

    /*
    public function addServerToHostgroupAction() {
        $serverId = $this->params()->fromRoute('param1');
        $hostgroupId = $this->params()->fromRoute('param1');

        $hgTable = new Model\NMHostgroupTable($this->_config);
        $hostgroup = $hgTable->addServerToGroup($serverId, $hostgroupId);

        return $this->renderView(
            array(
                "success" => true,
                "logLevel" => Logger::DEBUG,
                "hostgroup" => $hostgroup,
                "logOutput" => "Server ID" . $serverId . " has been added to HostGroup ID " . $hostgroupId
            )
        );
    }
    */

    /*
    public function addServerToHostgroupAction() {
        $serverId = $this->params()->fromRoute('param1');
        $hostgroupId = $this->params()->fromRoute('param1');

        $hgTable = new Model\NMHostgroupTable($this->_config);
        $hostgroup = $hgTable->addServerToGroup($serverId, $hostgroupId);

        return $this->renderView(
            array(
                "success" => true,
                "logLevel" => Logger::DEBUG,
                "hostgroup" => $hostgroup,
                "logOutput" => "Server ID" . $serverId . " has been added to HostGroup ID " . $hostgroupId
            )
        );
    }
    */

    // *****************************************************************************************************************
    // Direct LDAP methods
    // *****************************************************************************************************************

    private function addHostToUsergroupViaDirectLdap($hostName, $groupName) {
        // insure the host exists
        $ngTable = new LDAP\LDAPNetgroupTable($this->_config);
        $host    = $ngTable->getByHostName($hostName);
        if ($host->getCn()) {
            $ngTable->addHostToUserGroup($hostName, $groupName);
            $this->_viewData = array(
                "success"   => true,
                "logLevel"  => Logger::NOTICE,
                "logOutput" => $hostName . " added to " . $groupName,
                "parameters" => "[serverName: {$hostName},userGroup: {$hostName}]"
            );
            return true;
        } else {
            $this->_viewData = array(
                "success"   => true,
                "logLevel"  => Logger::INFO,
                "logOutput" => $groupName . " attempted to be added to " . $hostName . " LDAP but the host was not found"
            );
            return true;
        }
    }

    private function deleteHostFromUsergroupViaDirectLdap($hostName, $groupName) {
        // insure the host exists
        $ngTable = new LDAP\LDAPNetgroupTable($this->_config);
        $host    = $ngTable->getByHostName($hostName);
        if ($host->getCn()) {
            $ngTable->deleteHostFromUserGroup($hostName, $groupName);
            $this->_viewData = array(
                "success"   => true,
                "logLevel"  => Logger::NOTICE,
                "logOutput" => $hostName . " added to " . $groupName,
                "parameters" => "[serverName: {$hostName},userGroup: {$hostName}]"
            );
            return true;
        } else {
            $this->_viewData = array(
                "success"   => true,
                "logLevel"  => Logger::INFO,
                "logOutput" => $hostName . " attempted to be added to " . $groupName . " LDAP but the host was not found"
            );
            return true;
        }
    }

    private function deleteHostFromHostgroupViaDirectLdap($hostName, $groupName) {
        // insure the host exists
        $ngTable = new LDAP\LDAPNetgroupTable($this->_config);
        $host    = $ngTable->getByHostName($hostName);
        if ($host->getCn()) {
            $ngTable->deleteHostFromHostGroup($hostName, $groupName);
            $this->_viewData = array(
                "success"   => true,
                "logLevel"  => Logger::NOTICE,
                "logOutput" => $hostName . " added to " . $groupName,
                "parameters" => "[serverName: {$hostName},hostGroup: {$hostName}]"
            );
            return true;
        } else {
            $this->_viewData = array(
                "success"   => true,
                "logLevel"  => Logger::INFO,
                "logOutput" => $hostName . " attempted to be added to " . $groupName . " LDAP but the host was not found"
            );
            return true;
        }
    }

    private function addHostToHostgroupViaDirectLdap($hostName, $groupName) {
        // insure the host exists
        $ngTable = new LDAP\LDAPNetgroupTable($this->_config);
        $host    = $ngTable->getByHostName($hostName);
        if ($host->getCn()) {
            $ngTable->addHostToHostGroup($hostName, $groupName);
            $this->_viewData = array(
                "success"   => true,
                "logLevel"  => Logger::NOTICE,
                "logOutput" => $hostName . " added to " . $groupName,
                "parameters" => "[serverName: {$hostName},hostGroup: {$hostName}]"
            );
            return true;
        } else {
            $this->_viewData = array(
                "success"   => true,
                "logLevel"  => Logger::INFO,
                "logOutput" => $hostName . " attempted to be added to " . $groupName . " LDAP but the host was not found"
            );
            return true;
        }
    }

    public function deleteHostViaDirectLdapAction() {
        $hostName = $this->params()->fromRoute('param1');

        if (!$this->deleteHostViaDirectLdap($hostName)) {
            return $this->renderView(
                array(
                    "success"   => false,
                    "error"     => "Could not delete host " . $hostName . " from LDAP",
                    "logLevel"  => Logger::NOTICE,
                    "logOutput" => "Host " . $hostName . " deleted from LDAP"
                )
            );
        }
        return $this->renderView(
            array(
                "success"   => true,
                "logLevel"  => Logger::NOTICE,
                "logOutput" => "Host " . $hostName . " deleted from LDAP"
            )
        );
    }


    private function deleteHostViaDirectLdap($hostName) {
        $ngTable = new LDAP\LDAPNetgroupTable($this->_config);
        $host    = $ngTable->getByHostName($hostName);
        if ($host->getCn()) {
            $dn = "cn={$hostName},ou=Netgroup,o=Neustar";
            $ngTable->delete($dn);
            $this->_viewData = array(
                "success"   => true,
                "logLevel"  => Logger::NOTICE,
                "logOutput" => $hostName . " deleted from LDAP",
                "parameters" => "[serverName: {$hostName}]"
            );
            return true;
        } else {
            $this->_viewData = array(
                "success"   => true,
                "logLevel"  => Logger::INFO,
                "logOutput" => $hostName . " attempted to be deleted from LDAP but was not found"
            );
            return true;
        }
        /*
        if (isset($getHostNeuOwnerResponse->output->records[0])) {
            $neuOwner = $getHostNeuOwnerResponse->output->records[0]->neuOwner;
            return $neuOwner;
        }
        return false;
        */
    }

    // *****************************************************************************************************************
    // Misc private methods
    // *****************************************************************************************************************

    private function larpCall($url, $username="", $password="") {
        $username = $username ? $username : "";
        $password = $password ? $password : "";

        try {
            $response = $this->curlGetUrl($url, null, true, $username, $password);
        } catch (\ErrorException $e) {
            $this->_viewData = array(
                "success"   => false,
                "error"     => "Exception caught. Unable to perform LDAP function: " . $e->getMessage() . " API call: " . $url,
                "larpUrl"   => $url,
                "trace"     => $e->getTraceAsString(),
                "logLevel"  => Logger::ERR,
                "logOutput" => $e->getMessage() . " Trace: " . $e->getTraceAsString()
            );
            return false;
        }

        if ($response->output->success != true) {
            #$this->writeLog(array("logOutput" => print_r($response, true)));
            $this->_viewData = array(
                "success"   => false,
                "error"     => "LARP returned false. Unable to peform LDAP function: " . $response->output->message . " API call: " . $url,
                "larpUrl"   => $url,
                "logLevel"  => Logger::ERR,
                "logOutput" => "LARP returned false. Unable to perform LDAP function: " . $response->output->message
            );
            return false;
        }
        return $response;
    }

    private function getUsergroupList() {
        if (!$response = $this->larpCall("https://" . $this->glassServer . "/larp/user/group/list")) {
            return false;
        }
        $usergroups = $response->output->records;
        natcasesort($usergroups);
        // sort retains the index of array elements so need to reassign
        $sorted = array();
        foreach ($usergroups as $g) {
            $sorted[] = $g;
        }
        return $sorted;
    }

    private function getHostgroupList() {
        if (!$response = $this->larpCall("https://" . $this->glassServer . "/larp/host/group/list")) {
            return false;
        }

        $hostgroups = $response->output->records;
        natcasesort($hostgroups);
        // sort retains the index of array elements so need to reassign
        $sorted = array();
        foreach ($hostgroups as $g) {
            $sorted[] = $g;
        }
        return $sorted;
    }

    private function getPrimaryGroups() {
        $ngTable = new LDAP\LDAPNetgroupTable($this->_config);
        return $ngTable->getPrimaryGroups();
    }

    private function getPrimarySubGroups($groupName) {
        $ngTable = new LDAP\LDAPNetgroupTable($this->_config);
        return $ngTable->getPrimarySubGroups($groupName);
    }

    private function getHostNeuOwner($hostName) {
        if (!$getHostNeuOwnerResponse = $this->larpCall("https://" . $this->glassServer . "/larp/host/read/" . $hostName)) {
            return false;
        }
        if (isset($getHostNeuOwnerResponse->output->records[0])) {
            $neuOwner = $getHostNeuOwnerResponse->output->records[0]->neuOwner;
            return $neuOwner;
        }
        return false;
    }


    // *****************************************************************************************************************
    // usergroup, hostgroup and node cache methods
    // *****************************************************************************************************************

    public function insertHostgroupAction() {
        $hostgroupName = $this->params()->fromRoute('param1');

        $hostgroup = $this->hostgroupTable->getByName($hostgroupName);
        $id        = $hostgroup->getId();

        if (is_numeric($id)) {
            return $this->renderView(array("success" => false, "logOutput" => "A hostgroup with the name " . $hostgroupName . " is already in the database"));
        } else {

            $hostgroup->setName($hostgroupName);
            if ($hostgroup = $this->hostgroupTable->create($hostgroup)) {
                return $this->renderView(array(
                                             "success" => true,
                                             "logLevel"  => Logger::INFO,
                                             "logOutput" => "Hostgroup " . $hostgroupName . " was inserted successfully"
                                         ));
            }

        }
        return $this->renderView(array("success" => false, "logOutput" => "Something went wrong inserting " . $hostgroupName . " into the database"));

    }

    private function insertHostgroup($hostgroupName) {
        $hostgroup = $this->hostgroupTable->getByName($hostgroupName);
        $id        = $hostgroup->getId();
        if (is_numeric($id)) {
            return false;
        }
        $hostgroup->setName($hostgroupName);
        $this->hostgroupTable->create($hostgroup);
        return true;
    }


    public function insertNodeToUsergroupAction() {
        $node_id      = $this->params()->fromRoute('param1');
        $usergroup_id = $this->params()->fromRoute('param2');
        if ($insertResult = $this->insertNodeToUsergroup($node_id, $usergroup_id)) {
            return $this->renderView(array(
                                         "success" => true,
                                         "logLevel"  => Logger::INFO,
                                         "logOutput" => "Inserted node_id=" . $node_id . " and usergroup_id=" . $usergroup_id . " into node_to_usergroup table"
                                     ));

        }
        return $this->renderView(array("success" => false, "logOutput" => "Attempted to insert node_id=" . $node_id . " and usergroup_id=" . $usergroup_id . " into node_to_usergroup table but it was already there."));

    }

    private function insertNodeToUsergroup($node_id, $hostgroup_id) {
        $insertResult = $this->nodeToUsergroupTable->createUserGroupEntry($node_id, $hostgroup_id);

        return $insertResult;
    }


    public function insertUsergroupAction() {
        $usergroupName = $this->params()->fromRoute('param1');

        $usergroup = $this->usergroupTable->getByName($usergroupName);
        $id        = $usergroup->getId();

        if (is_numeric($id)) {
            return $this->renderView(array(
                                         "success"   => false,
                                         "error"     => "A usergroup with the name " . $usergroupName . " is already in the database",
                                         "logLevel"  => Logger::ERR,
                                         "logOutput" => "A usergroup with the name " . $usergroupName . " is already in the database"
                                     ));
        } else {

            $usergroup->setName($usergroupName);
            if ($usergroup = $this->usergroupTable->create($usergroup)) {
                return $this->renderView(array(
                                             "success" => true,
                                             "logLevel"  => Logger::INFO,
                                             "logOutput" => "Usergroup " . $usergroupName . " was inserted successfully"
                                         ));

            }

        }
        return $this->renderView(array(
                                     "success"   => false,
                                     "error"     => "Something went wrong inserting " . $usergroupName . " into the database",
                                     "logLevel"  => Logger::ERR,
                                     "logOutput" => "Something went wrong inserting " . $usergroupName . " into the database"
                                 ));
    }

    private function insertUsergroup($usergroupName) {
        $usergroup = $this->usergroupTable->getByName($usergroupName);
        $id        = $usergroup->getId();
        if (is_numeric($id)) {
            return false;
        }
        $usergroup->setName($usergroupName);
        $this->usergroupTable->create($usergroup);
        return true;
    }


    public function updateUsergroupListAction() {
        #$getUsergroupsResult = $this->curlGetUrl("https://".$_SERVER['SERVER_NAME']."/ldap/getUsergroupList");
        #$usergroups          = $getUsergroupsResult->usergroups;
        $usergroups = $this->getUsergroupList();

        foreach ($usergroups AS $group) {
            $this->insertUsergroup($group);
        }
        return $this->renderView(array("success" => true, "logOutput" => count($usergroups) . " usergroups found and updated."));
    }


    public function updateHostgroupListAction() {
        $hostgroups = $this->getHostgroupList();

        foreach ($hostgroups AS $group) {
            $this->insertHostgroup($group);
        }
        return $this->renderView(array("success" => true, "logOutput" => count($hostgroups) . " hostgroups found and updated."));

    }


    public function insertNodeAction() {
        $nodeName = $this->params()->fromRoute('param1');
        if ($this->insertNode($nodeName)) {
            return $this->renderView(array("success" => true, "logOutput" => "Node " . $nodeName . " was inserted successfully"));
        }
        return $this->renderView(array("success" => false, "logOutput" => "Error inserting Node " . $nodeName));

    }

    private function insertNode($nodeName) {
        $node = $this->nodeTable->getByName($nodeName);
        $id   = $node->getId();
        if (is_numeric($id)) {
            return $id;
        }
        $node->setName($nodeName);
        $node = $this->nodeTable->create($node);
        $id   = $node->get('id');
        return $id;
    }


    public function updateNodesWithNeuOwnerAction() {
        $chefServer = $this->params()->fromRoute('param1');

        $getNodeListURL    = "https://".$_SERVER['SERVER_NAME']."/chef/getNodes?chef_server=" . $chefServer;
        $getNodeListResult = $this->curlGetUrl($getNodeListURL);

        $nodeCount = 0;
        if ($getNodeListResult->success == 1) {
            $nodeList  = $getNodeListResult->nodes;
            $nodeCount = count($nodeList);

            foreach ($nodeList AS $nodeName) {

                #$getHostNeuOwnerURL    = "https://".$_SERVER['SERVER_NAME']."/ldap/getHostNeuOwner/" . $nodeName . "?chef_server=" . $chefServer;
                #$getHostNeuOwnerResult = $this->curlGetUrl($getHostNeuOwnerURL);
                #$neuOwner = $getHostNeuOwnerResult->neuOwner;
                $neuOwner = $this->getHostNeuOwner($nodeName);

                $nodeId = $this->insertNode($nodeName);

                if (is_array($neuOwner)) {

                    foreach ($neuOwner AS $usergroupName) {
                        $usergroup   = $this->usergroupTable->getByName($usergroupName);
                        $usergroupId = $usergroup->get("id");
                        if (is_numeric($usergroupId)) {
                            $this->insertNodeToUsergroup($nodeId, $usergroupId);
                        }

                    }
                }
            }
        }
        return $this->renderView(array(
                                     "success"   => true,
                                     "nodeCount" => $nodeCount,
                                     "logOutput" => "Node to usergroup permissions updated for all nodes on chef server " . $chefServer,
                                     "logLevel"  => Logger::NOTICE
                                 ));
    }

}
