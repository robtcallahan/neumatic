<?php

namespace Neumatic\Controller;

use Zend\View\Model\ViewModel;
use Zend\View\Model\JsonModel;
use Zend\Mvc\MvcEvent;
use Zend\Log\Logger;

use Neumatic\Model;

require_once("vendor/ChefServerApi/ChefServer.php");
require_once("vendor/ChefServerApi/ChefServer12.php");

use ChefServerApi\ChefServer;
use ChefServer12API\ChefServer12;

use STS\Util\SSH2;


class ChefController extends Base\BaseController
{

    protected $config;
    protected $httpCodesIniFile;
    protected $httpCodes;

    protected $chefConfig;
    protected $chefServer;

    protected $debug;
    protected $timeStart;
    /** @var  Model\NMNodeTable $nodeTable */
    protected $nodeTable;
    /** @var  Model\NMUsergroupTable $usergroupTable */
    protected $usergroupTable;
    /** @var  Model\NMNodeToUsergroupTable $nodeToUsergroupTable */
    protected $nodeToUsergroupTable;
    protected $userTable;
    protected $userType;

    /** @var \ChefServerApi\ChefServer $chef */
    protected $chef;

    public function onDispatch(MvcEvent $e) {
        $this->httpCodesIniFile = __DIR__ . "/../../../config/http_codes.ini";
        $this->httpCodes        = parse_ini_file($this->httpCodesIniFile);

        $this->chefServer = $this->params()->fromQuery('chef_server');

        if ($this->chefServer == "" OR $this->chefServer == null) {
            if (isset($_COOKIE['chef_server'])) {
                $this->chefServer = $_COOKIE['chef_server'];
            } else {
                $this->chefServer = $this->_config['chef']['default']['server'];
                if (!isset($_SESSION)) {
                    setcookie('chef_server', $this->chefServer);
                }

            }
        }

        $uid                        = $this->_user->getUsername();
        $this->defaultCacheLifetime = "300";
        $this->cachePathBase        = "/var/www/html/neumatic/data/cache/" . $uid . "/Chef/" . $this->chefServer . "/";
        $this->checkCache();


        $this->chef = $this->defineChefServer();

        $this->debug = $this->params()->fromQuery('debug');

        $this->timeStart = microtime(true);

        $this->nodeTable = new Model\NMNodeTable($this->_config);

        $this->usergroupTable       = new Model\NMUsergroupTable($this->_config);
        $this->nodeToUsergroupTable = new Model\NMNodeToUsergroupTable($this->_config);

        $this->userTable = new Model\NMUserTable($this->_config);
        $this->userType  = $this->_user->getUserType();

        return parent::onDispatch($e);
    }


    /**
     * Separate function so we can instantiate chef servers within actions
     *
     * @return ChefServer
     */
    private function defineChefServer() {
        if (isset($this->chefServer) && $this->chefServer != "") {
            $this->chefConfig = $this->_config['chef'][$this->chefServer];
        } else {
            $this->chefConfig = $this->_config['chef']['default'];
        }
        if (isset($this->chefConfig['enterprise']) AND $this->chefConfig['enterprise'] === true) {
            // Added by Rob on 10/28/2014
            $server = $this->chefConfig['server'];
           
            if (!preg_match("/https:\/\//", $server)) {
                $server = 'https://' . $server;
            }
            $apiConnection = new ChefServer12($server, $this->chefConfig['client'], $this->chefConfig['keyfile'], '11.12.1', true);
        } else {
            $apiConnection = new ChefServer($this->chefConfig['server'], $this->chefConfig['port'], $this->chefConfig['client'], $this->chefConfig['keyfile'], false);
        }
        return $apiConnection;
    }

    public function indexAction() {
        return $this->renderView(array("error" => "This controller has no output from index. Please refer to the API documentation page at https://confluence.nexgen.neustar.biz/display/IS/NeuMatic+API+Documentation"));
    }


    /**
     * Renders either to "JSON" or the standard viewmodel
     *
     * @param mixed $returnvals
     * @param string $model "json|view|pre|xml"
     * @return ViewModel
     *
     */

    /********************************************* Servers *********************************************/

    /**
     * Returns a list of Chef servers listed in the module.config.php file
     * usage: https://{$server}/chef/getServers
     *
     * @return ViewModel json
     */
    public function getServersAction() {
        try {
            $data = $this->getServers();
        } catch (\Exception $e) {
            return $this->renderView(array(
                                         "success"   => false,
                                         "error"   => $e->getMessage(),
                                         "logOutput" => "There was an error attempting to get the list of Chef Servers : " . $e->getMessage(),
                                         "logLevel"  => \Zend\Log\Logger::ERR,
                                         "trace"     => $e->getTraceAsString()));
        }

        return $this->renderView(array(
                                     "success"   => true,
                                     "servers"   => $data,
                                     "logOutput" => "Retrieved the list of chef servers",
                                     "logLevel"  => \Zend\Log\Logger::DEBUG,
                                     "cache"        => true,
                                     "cacheTTL"     => "300"
                                 ));
    }

    private function getServers() {
        $servers = array_keys($this->_config['chef']);
        $data = array();

        if (!isset($this->userGroups)) {
            $this->userGroups = $this->getUserGroups();
        }

        foreach ($servers as $s) {

            $env = $this->_config['chef'][$s]['env'];
            $fqdn = $this->_config['chef'][$s]['server'];
            
            if(isset($this->_config['chef'][$s]['allowBuild']) AND $this->_config['chef'][$s]['allowBuild'] === false){
                $allowBuild = false;
            }else{
                $allowBuild = true;
            }
            
            if(isset($this->_config['chef'][$s]['allowChef']) AND $this->_config['chef'][$s]['allowChef'] === false){
                $allowChef = false;
            }else{
                $allowChef = true;
            }
            
            if (isset($this->_config['chef'][$s]['authorizedGroups']) AND $s != 'targetVersion') {
                $authorizedGroups = $this->_config['chef'][$s]['authorizedGroups'];

                foreach ($this->userGroups AS $ugId) {
                    if(is_numeric($ugId)){
                        $ug      = $this->usergroupTable->getById($ugId);
                        $ugName  = $ug->get('name');
                        $adminOn = false;
                        if (isset($_COOKIE['userAdminOn'])) {
                            if ($_COOKIE['userAdminOn'] == 'true') {
                                $adminOn = true;
                            }
                        }
                        if (in_array($ugName, $authorizedGroups)
                            OR in_array('all', $authorizedGroups)
                            OR ($this->userType == 'Admin' AND $adminOn == true)
                            OR ($this->userType == 'Admin' AND !isset($_COOKIE['userAdminOn']))) {
                            //$this->writeLog(array("logLevel" => \Zend\Log\Logger::DEBUG, "logOutput" => "s=" . $s));
                            // leave out the "default" server
    
                            //if ($s == 'targetVersion' || $env == 'default' || preg_match("/Ent/", $env)) continue;
                            if ($s == 'targetVersion' || $env == 'default') continue;
         
                            $env = $this->_config['chef'][$s]['env'];
    
                            $data[] = array(
                                "name"         => $s,
                                "env"          => $env,
                                "fqdn"         => $fqdn,
                                "displayValue" => "[" . $env . "] " . $s,
                                "allowBuild" => $allowBuild,
                                "allowChef" => $allowChef
                            );
                            continue 2;
                        }
                    }

                }
            } else {

                // $this->writeLog(array("logLevel" => \Zend\Log\Logger::DEBUG, "logOutput" => "s=" . $s));
                // leave out the "default" server
                if ($s == 'targetVersion' || $env == 'default' || preg_match("/Ent/", $env)) continue;


                $env = $this->_config['chef'][$s]['env'];

                $data[] = array(
                    "name"         => $s,
                    "env"          => $env,
                    "fqdn"         => $fqdn,
                    "displayValue" => "[" . $env . "] " . $s,
                    "allowBuild" => $allowBuild
                );
            }

        }

        return $data;
    }


    public function getUserGroupsAction() {
        $uid = $this->params()->fromRoute('param1');

        try {
            if ($uid == null) {
                $uid = $this->_user->getUsername();
            }

            $getUserGroupsURL    = "https://" . $_SERVER['SERVER_NAME'] . "/ldap/getUserGroups/" . $uid;
            $getUserGroupsResult = $this->curlGetUrl($getUserGroupsURL);

            if (is_string($getUserGroupsResult)) {
                $userGroups = json_decode($getUserGroupsResult, JSON_NUMERIC_CHECK);
            } else {
                $userGroups = $getUserGroupsResult;
            }

            $userGroupsArray = $userGroups->groups;

            return $this->renderview(array(
                                         "success"    => true,
                                         "groups"     => $userGroupsArray,
                                         "logOutput"  => "Got the LDAP groups for the user " . $uid,
                                         "logLevel"   => \Zend\Log\Logger::DEBUG,
                                         "parameters" => "[uid: " . $uid . ", chefServer: " . $this->chefServer . "]"
                                     ), "json");

        } catch (\Exception $e) {
            return $this->renderView(array(
                                         "success"    => false,
                                         "error"    => $e->getMessage(),
                                         "logOutput"  => "There was an error getting the groups for the user : " . $e->getMessage(),
                                         "logLevel"   => \Zend\Log\Logger::ERR,
                                         "parameters" => "[uid: " . $uid . ", chefServer: " . $this->chefServer . "]",
                                         "trace"      => $e->getTraceAsString()
                                     ));
        }
    }


    private function getUserGroups($uid = null) {
        if ($cachedGroups = $this->checkCache("getUserGroups", true)) {
            return unserialize($cachedGroups);
        }

        if ($uid == null) {
            $uid = $this->_user->getUsername();
        }
        $getUserGroupsURL = "https://" . $_SERVER['SERVER_NAME'] . "/ldap/getUserGroups/" . $uid;

        //$getUserGroupsURL = "https://localhost/ldap/getUserGroups/".$uid;
        $getUserGroupsResult = $this->curlGetUrl($getUserGroupsURL);

        if (is_string($getUserGroupsResult)) {
            $userGroups = json_decode($getUserGroupsResult, JSON_NUMERIC_CHECK);
        } else {
            $userGroups = $getUserGroupsResult;
        }
        $usergroupsArray = $userGroups->groups;

        $ugIdArray = array();
        foreach ($usergroupsArray AS $usergroupName) {
            $usergroup   = $this->usergroupTable->getByName($usergroupName);
            $usergroupId = $usergroup->getId();
            $ugIdArray[] = $usergroupId;
        }
        $this->writeCache(serialize($ugIdArray), "getUserGroups", 600);
        return $ugIdArray;
    }


    /********************************************* Environments *********************************************/
    /**
     * Check if the currently logged in ldap user is authorized to edit the given environment
     *
     * usage: https://{$server}/chef/checkEnvironmentExists/{$environmentName}
     *
     * @return ViewModel json
     */
    public function checkAuthorizedEnvironmentEditAction() {
        $environmentName = $this->params()->fromRoute('param1');
        try {
            $authorized = $this->checkAuthorizedEnvironmentEdit($environmentName);

        } catch (\Exception $e) {
            return $this->renderView(array(
                                         "success"    => false,
                                         "error"    => $e->getMessage(),
                                         "logOutput"  => "There was an error checking if authorized to modify the environment : " . $e->getMessage(),
                                         "logLevel"   => \Zend\Log\Logger::ERR,
                                         "parameters" => "[environmentName: " . $environmentName . ", chefServer: " . $this->chefServer . "]",
                                         "trace"      => $e->getTraceAsString()));
        }
        return $this->renderview(array(
                                     "success"    => 1,
                                     "authorized" => $authorized,
                                     "logOutput"  => "Checked if user is authorized for the environment " . $environmentName,
                                     "logLevel"   => \Zend\Log\Logger::DEBUG,
                                     "parameters" => "[environmentName: " . $environmentName . ", chefServer: " . $this->chefServer . "]",
                                     "cache"      => true,
                                     "cacheTTL"   => "300"
                                 ), "json");
    }

    private function checkAuthorizedEnvironmentEdit($environmentName) {

        if (isset($_COOKIE['userAdminOn'])) {
            if ($this->userType == 'Admin' AND $_COOKIE['userAdminOn'] == 'true') {
                return true;
            }
        } else {
            if ($this->userType == 'Admin') {
                return true;
            }
        }

        $environment = $this->chef->get('/environments/' . $environmentName);

        if (isset($environment->default_attributes->neustar->ownerGroup)) {
            $ownerGroup = $environment->default_attributes->neustar->ownerGroup;

            if (!isset($this->userGroups)) {
                $this->userGroups = $this->getUserGroups();
            }

            foreach ($this->userGroups AS $ugId) {
                if(is_numeric($ugId)){    
                    $ug     = $this->usergroupTable->getById($ugId);
                    $ugName = $ug->get('name');
                    if ($ugName == $ownerGroup) {
                        return true;
                    }
                }
            }
        }
        /*
        $nodes = $this->getEnvironmentNodes($environmentName);
        $authorizedEdit = false;
        
        foreach($nodes AS $nodeName){
              
            $authorizedEdit = $this->checkAuthorizedNodeEdit($nodeName);
            
            if($authorizedEdit == false){
                return false;
            }
            
        }
        if($authorizedEdit == true){
            return true;
        }
        */
        return false;
    }


    /**
     * Check if an environment with the given name exists on the server
     * usage: https://{$server}/chef/checkEnvironmentExists/{$environmentName}
     *
     * @return ViewModel json
     */
    public function checkEnvironmentExistsAction() {
        $environmentName = $this->params()->fromRoute('param1');

        try {
            $result = $this->checkEnvironmentExists($environmentName);
        } catch (\Exception $e) {
            return $this->renderView(array(
                                         "success"    => false,
                                         "error"    => $e->getMessage(),
                                         "logOutput"  => "There was an error attempting to check if the environment " . $environmentName . " exists on the chef server : " . $e->getMessage(),
                                         "logLevel"   => \Zend\Log\Logger::ERR,
                                         "parameters" => "[environmentName: " . $environmentName . ", chefServer: " . $this->chefServer . "]",
                                         "trace"      => $e->getTraceAsString()));
        }
        if ($result == true OR $result == false) {
            return $this->renderview(array(
                                         "success"    => true,
                                         "exists"     => $result,
                                         "logOutput"  => "Checked if the environment " . $environmentName . " exists",
                                         "logLevel"   => \Zend\Log\Logger::DEBUG,
                                         "parameters" => "[environmentName: " . $environmentName . ", chefServer: " . $this->chefServer . "]"
                                     ), 'json');
        }
        return $this->renderview(array(
                                     "success"    => false,
                                     "error"    => "There was an error checking if the environment " . $environmentName . " exists",
                                     "logOutput"  => "There was an error checking if the environment " . $environmentName . " exists : " . $result,
                                     "parameters" => "[environmentName: " . $environmentName . ", chefServer: " . $this->chefServer . "]",
                                     "logLevel"   => \Zend\Log\Logger::ERR
                                 ), 'json');
    }

    private function checkEnvironmentExists($environmentName) {
        $result = $this->chef->get('/environments/' . $environmentName);

        if (isset($result->error) AND stristr($result->error[0], "Cannot Load")) {
            return false;

        } elseif (isset($result->name)) {
            return true;
        }
        return false;
        
    }


    /**
     * Gets the specified environment and returns in JSON
     * usage: https://{$server}/chef/getEnvironment/{$environmentName}
     *
     * @return ViewModel json
     */
    public function getEnvironmentAction() {
        $environmentName = $this->params()->fromRoute('param1');

        try {
            $environment = $this->chef->get('/environments/' . $environmentName);

            if (empty($environment)) {
                return $this->renderView(array(
                                             "success"    => false,
                                             "error"    => "Error getting environment",
                                             "logOutput"  => "There was an error getting the environment " . $environmentName,
                                             "parameters" => "[environmentName: " . $environmentName . ", chefServer: " . $this->chefServer . "]",
                                             "logLevel"   => \Zend\Log\Logger::ERR
                                         ));
            }

            foreach ($environment->cookbook_versions AS $cbName => $cbVer) {
                $cbVerExp = explode(" ", $cbVer);

                $environment->cookbook_versions->{$cbName} = array("version" => $cbVerExp[1], "operator" => $cbVerExp[0]);

            }

            $environment->authorized = $this->checkAuthorizedEnvironmentEdit($environmentName);

            if (empty($environment)) {
                return $this->renderView(array(
                                             "success"    => false,
                                             "error"    => "Error getting environment",
                                             "logOutput"  => "There was an error getting the environment " . $environmentName,
                                             "parameters" => "[environmentName: " . $environmentName . ", chefServer: " . $this->chefServer . "]",
                                             "logLevel"   => \Zend\Log\Logger::ERR
                                         ));
            }

        } catch (\Exception $e) {
            return $this->renderView(array(
                                         "success"    => false,
                                         "error"    => $e->getMessage(),
                                         "logOutput"  => "There was an error getting the environment " . $environmentName . " : " . $e->getMessage(),
                                         "parameters" => "[environmentName: " . $environmentName . ", chefServer: " . $this->chefServer . "]",
                                         "logLevel"   => \Zend\Log\Logger::ERR,
                                         "trace"      => $e->getTraceAsString()
                                     ));
        }

        return $this->renderView(array(
                                     "success"     => true,
                                     "environment" => $environment,
                                     "logOutput"   => "Successfully got the environment " . $environmentName,
                                     "parameters"  => "[environmentName: " . $environmentName . ", chefServer: " . $this->chefServer . "]",
                                     "logLevel"    => \Zend\Log\Logger::DEBUG,
                                     "cache"       => true,
                                     "cacheTTL"    => "300"
                                 ), "json");
    }


    public function getEnvironmentObjectAction() {
        $environmentName = $this->params()->fromRoute('param1');

        try {
            $environment = $this->getEnvironmentObject($environmentName);

            if (empty($environment)) {
                return $this->renderView(array(
                                             "success"    => false,
                                             "error"    => "Error getting environment ",
                                             "logOutput"  => "There was an error getting the environment object " . $environmentName,
                                             "parameters" => "[environmentName: " . $environmentName . ", chefServer: " . $this->chefServer . "]",
                                             "logLevel"   => \Zend\Log\Logger::ERR
                                         ));
            }

            return $this->renderView(array(
                                         "success"     => true,
                                         "environment" => $environment,
                                         "logOutput"   => "Successfully got the object for the environment " . $environmentName,
                                         "parameters"  => "[environmentName: " . $environmentName . ", chefServer: " . $this->chefServer . "]",
                                         "logLevel"    => \Zend\Log\Logger::DEBUG
                                     ), "json");

        } catch (\Exception $e) {
            return $this->renderView(array(
                                         "success"    => false,
                                         "error"    => $e->getMessage(),
                                         "logOutput"  => "There was an error getting the environment object " . $environmentName . " : " . $e->getMessage(),
                                         "parameters" => "[environmentName: " . $environmentName . ", chefServer: " . $this->chefServer . "]",
                                         "logLevel"   => \Zend\Log\Logger::ERR,
                                         "trace"      => $e->getTraceAsString()));
        }

    }

    private function getEnvironmentObject($environmentName) {

        $environment = $this->chef->get('/environments/' . $environmentName);
        return $environment;
    }

    /**
     * Gets an array of the names of all environments currently on the server
     * usage: https://{$server}/chef/getEnvironments/
     *
     * @return ViewModel json
     */
    public function getEnvironmentsAction() {
        try {
            $environments = $this->getEnvironments();

            if (empty($environments)) {
                return $this->renderView(array(
                                             "success"    => false,
                                             "error"    => "Error getting environment list",
                                             "logOutput"  => "There was an error getting the environment list",
                                             "parameters" => "[chefServer: " . $this->chefServer . "]",
                                             "logLevel"   => \Zend\Log\Logger::ERR
                                         ));
            }

            return $this->renderView(array(
                                         "success"      => true,
                                         "environments" => $environments,
                                         "logOutput"    => "Got the list of environments",
                                         "parameters"   => "[chefServer: " . $this->chefServer . "]",
                                         "logLevel"     => \Zend\Log\Logger::DEBUG,
                                         "cache"        => true,
                                         "cacheTTL"     => "300"
                                     ), "json");

        } catch (\Exception $e) {
            return $this->renderView(array(
                                         "success"    => false,
                                         "error"    => $e->getMessage(),
                                         "logOutput"  => "There was an error getting the environment list : " . $e->getMessage(),
                                         "parameters" => "[chefServer: " . $this->chefServer . "]",
                                         "logLevel"   => \Zend\Log\Logger::ERR,
                                         "trace"      => $e->getTraceAsString()
                                     ));
        }

    }

    /**
     * Gets the complete list of environments
     *
     * @return array
     */
    private function getEnvironments() {
        $result       = $this->chef->get('/environments');
        $environments = array();

        foreach ($result AS $k => $v) {
            $env               = array();
            $env['name']       = $k;
            $env['authorized'] = $this->checkAuthorizedEnvironmentEdit($k);
            $environments[]    = $env;
        }
        usort($environments, function (array $a, array $b) {
            return strnatcasecmp($a['name'], $b['name']);
        });
        return $environments;
    }


    /**
     * Gets an array of all environments currently on the server with details
     * usage: https://{$server}/chef/getEnvironmentsDetailed/
     *
     * @return ViewModel json
     */
    public function getEnvironmentsDetailedAction() {
        try {

            $result = $this->chef->get('/environments');
            $environment_list = array();

            foreach ($result AS $k => $v) {
                $environment_list[]['name'] = $k;
            }

            usort($environment_list, function (array $a, array $b) {
                return strnatcasecmp($a['name'], $b['name']);
            });


            $environments = array();


            foreach ($environment_list AS $env) {
                $env_details = $this->chef->get('/environments/' . $env['name']);

                $nodes_tmp = $this->chef->get('/environments/' . $env['name'] . '/nodes');
                $nodes     = array();
                foreach ($nodes_tmp AS $n_k => $n_v) {
                    $nodes[] = $n_k;
                }

                $env_details->nodes      = $nodes;
                $env_details->node_count = count($nodes);


                unset($env_details->json_class);
                unset($env_details->chef_type);


                $env_details->authorized = $this->checkAuthorizedEnvironmentEdit($env['name']);
                //$env_details->authorized = false;

                if (isset($env_details->default_attributes->neustar->ownerGroup)) {
                    $ownerGroup = $env_details->default_attributes->neustar->ownerGroup;
                    if (!isset($this->userGroups)) {
                        $this->userGroups = $this->getUserGroups();
                    }
                    foreach ($this->userGroups AS $ugId) {
                    	if(is_numeric($ugId)){
	                        $ug     = $this->usergroupTable->getById($ugId);
	                        $ugName = $ug->get('name');
	                        if ($ugName == $ownerGroup) {
	                            $env_details->authorized = true;
	                        }
			}
                    }
                }

                if ($env_details->authorized != true) {
                    foreach ($nodes AS $nodeName) {
                        if (!isset($this->userGroups)) {
                            $this->userGroups = $this->getUserGroups();
                        }
                        $env_details->authorized = false;
                        $node                    = $this->nodeTable->getByName($nodeName);
                        $nodeId                  = $node->getId();

                        $nodeUsergroups = $this->nodeToUsergroupTable->getUsergroupsByNodeId($nodeId);


                        if (empty($nodeUsergroups)) {

                            $env_details->authorized = false;
                            break;

                        } else {
                            foreach ($nodeUsergroups AS $nug) {

                                if (in_array($nug['usergroup_id'], $this->userGroups)) {

                                    $env_details->authorized = true;
                                    break;
                                }

                            }
                        }
                        unset($node);
                        if ($env_details->authorized == false) {

                            break;
                        }

                        $env_details->authorized = false;

                    }
                }


                $environments[] = $env_details;

            }
            if (empty($environments)) {
                return $this->renderView(array(
                                             "success"    => false,
                                             "error"    => "Error getting environment list",
                                             "logOutput"  => "There was an error getting the environment list : ",
                                             "parameters" => "[chefServer: " . $this->chefServer . "]",
                                             "logLevel"   => \Zend\Log\Logger::ERR
                                         ));
            }
            return $this->renderView(array(
                                         "success"      => true,
                                         "environments" => $environments,
                                         "logOutput"    => "Got the environment list",
                                         "parameters"   => "[chefServer: " . $this->chefServer . "]",
                                         "logLevel"     => \Zend\Log\Logger::DEBUG,
                                         "cache"        => true,
                                         "cacheTTL"     => "300"
                                     ), "json");

        } catch (\Exception $e) {
            return $this->renderView(array(
                                         "success"    => false,
                                         "error"    => $e->getMessage(),
                                         "logOutput"  => "There was an error getting the environments : " . $e->getMessage(),
                                         "parameters" => "[chefServer: " . $this->chefServer . "]",
                                         "logLevel"   => \Zend\Log\Logger::ERR,
                                         "trace"      => $e->getTraceAsString()
                                     ));
        }
    }


    /**
     * Gets the default attributes for the specified environment and returns in JSON
     * usage: https://{$server}/chef/getEnvironmentDefaultAttributes/{$environmentName}
     *
     * @return ViewModel json
     */
    public function getEnvironmentDefaultAttributesAction() {
        $environmentName = $this->params()->fromRoute('param1');
        try {
            $result = $this->chef->get('/environments/' . $environmentName);

            if (!isset($result->default_attributes)) {
                return $this->renderView(array(
                                             "success"    => false,
                                             "logOutput"  => "Error getting default attributes for environment " . $environmentName,
                                             "parameters" => "[environmentName: " . $environmentName . ", chefServer: " . $this->chefServer . "]",
                                             "logLevel"   => \Zend\Log\Logger::ERR
                                         ), "json");

            }
            return $this->renderView(array(
                                         "success"            => true,
                                         "default_attributes" => $result->default_attributes,
                                         "logOutput"          => "Successfully got the default attributes for environment " . $environmentName,
                                         "parameters"         => "[environmentName: " . $environmentName . ", chefServer: " . $this->chefServer . "]",
                                         "logLevel"           => \Zend\Log\Logger::DEBUG
                                     ), "json");
        } catch (\Exception $e) {
            return $this->renderView(array(
                                         "success"    => false,
                                         "error"    => $e->getMessage(),
                                         "logOutput"  => "There was an error getting the environment " . $environmentName . " : " . $e->getMessage(),
                                         "parameters" => "[environmentName: " . $environmentName . ", chefServer: " . $this->chefServer . "]",
                                         "logLevel"   => \Zend\Log\Logger::ERR,
                                         "trace"      => $e->getTraceAsString()
                                     ));
        }
    }

    /**
     * Gets the override attributes for the specified environment and returns in JSON
     * usage: https://{$server}/chef/getEnvironmentOverrideAttributes/{$environmentName}
     *
     * @return ViewModel JSON
     */

    public function getEnvironmentOverrideAttributesAction() {
        $environmentName = $this->params()->fromRoute('param1');

        try {
            $result          = $this->chef->get('/environments/' . $environmentName);

            if (!isset($result->override_attributes)) {
                return $this->renderView(array(
                                             "success"    => false,
                                             "logOutput"  => "Error getting override attributes for environment " . $environmentName,
                                             "parameters" => "[environmentName: " . $environmentName . ", chefServer: " . $this->chefServer . "]",
                                             "logLevel"   => \Zend\Log\Logger::ERR
                                         ), "json");

            }
            return $this->renderView(array(
                                         "success"             => true,
                                         "override_attributes" => $result->override_attributes,
                                         "logOutput"           => "Successfully got the override attributes for environment " . $environmentName,
                                         "parameters"          => "[environmentName: " . $environmentName . ", chefServer: " . $this->chefServer . "]",
                                         "logLevel"            => \Zend\Log\Logger::DEBUG
                                     ), "json");
        } catch (\Exception $e) {
            return $this->renderView(array(
                                         "success"    => false,
                                         "error"    => $e->getMessage(),
                                         "logOutput"  => "There was an error getting the override attributes for environment " . $environmentName . " : " . $e->getMessage(),
                                         "parameters" => "[environmentName: " . $environmentName . ", chefServer: " . $this->chefServer . "]",
                                         "logLevel"   => \Zend\Log\Logger::ERR,
                                         "trace"      => $e->getTraceAsString()
                                     ));
        }
    }

    /**
     * Gets all cookbooks with version constraints listed in the specified environment
     * usage: https://{$server}/chef/getEnvironmentCookbookVersions/{$environmentName}
     *
     * @return ViewModel json
     */
    public function getEnvironmentCookbookVersionsAction() {
        $environmentName = $this->params()->fromRoute('param1');
        try {
            $env             = $this->chef->get('/environments/' . $environmentName);

            if (!isset($env->cookbook_versions)) {
                return $this->renderView(array(
                                             "success"    => false,
                                             "error"    => "Error getting cookbook versions for environment " . $environmentName,
                                             "logOutput"  => "There was an error getting cookbook versions for environment " . $environmentName,
                                             "parameters" => "[environmentName: " . $environmentName . ", chefServer: " . $this->chefServer . "]",
                                             "logLevel"   => \Zend\Log\Logger::ERR
                                         ), "json");

            }
            $cookbooks = $env->cookbook_versions;
            usort($cookbooks, 'strnatcasecmp');
            return $this->renderView(array(
                                         "success"    => true,
                                         "cookbooks"  => $cookbooks,
                                         "logOutput"  => "Successfully got the cookbook version restrictions for environment " . $environmentName,
                                         "parameters" => "[environmentName: " . $environmentName . ", chefServer: " . $this->chefServer . "]",
                                         "logLevel"   => \Zend\Log\Logger::DEBUG
                                     ), "json");
        } catch (\Exception $e) {
            return $this->renderView(array(
                                         "success"    => false,
                                         "error"    => $e->getMessage(),
                                         "logOutput"  => "There was an error getting cookbook versions for environment " . $environmentName . " : " . $e->getMessage(),
                                         "parameters" => "[environmentName: " . $environmentName . ", chefServer: " . $this->chefServer . "]",
                                         "logLevel"   => \Zend\Log\Logger::ERR,
                                         "trace"      => $e->getTraceAsString()
                                     ));
        }
    }

    /**
     * Gets the current cookbook version constraints for the specified environment and cookbook
     * usage: https://{$server}/chef/getEnvironmentCoobookVersion/{$environmentName}/{$cookbookName}
     *
     * @return string version number or error
     */
    public function getEnvironmentCookbookVersionAction() {
        $environmentName = $this->params()->fromRoute('param1');
        $cookbookName    = $this->params()->fromRoute('param2');

        $envData = $this->chef->get('/environments/' . $environmentName);

        if (isset($envData->error)) {
            exit();
        }
        $versions = $envData->cookbook_versions;

        if (isset($versions->$cookbookName)) {
            $cbVersionExp = explode(" ", $versions->$cookbookName);

            return $this->renderview(array(
                                         "success"  => 1,
                                         "version"  => $cbVersionExp[1],
                                         "operator" => $cbVersionExp[0],
                                         "cache"      => true,
                                         "cacheTTL"   => "300"
                                     ), "json");
        }

        return $this->renderview(array(
                                     "success" => '0',
                                     "error" => "No version restriction set for the cookbook " . $cookbookName . " in the environment " . $environmentName
                                 ), "json");

    }

    /**
     * Gets all nodes assigned to the specified environment
     * Usage: https://{$server}/chef/getEnvironmentNodesAction/{$environmentName}
     *
     * @return ViewModel json
     */
    public function getEnvironmentNodesAction() {
        $environmentName = $this->params()->fromRoute('param1');
        $nodes           = $this->getEnvironmentNodes($environmentName);

        if (!isset($nodes) OR $nodes[0] == 'error') {
            return $this->renderView(array(
                                         "success" => false,
                                         "error" => "Error getting nodes for environment " . $environmentName
                                     ), "json");
        }
        return $this->renderView(array(
                                     "success" => true,
                                     "nodes"   => $nodes,
                                     "count"   => count($nodes)
                                 ), "json");

    }

    /**
     * Gets a list of nodes in the specified environment
     *
     * @param $environmentName
     * @return array
     */
    private function getEnvironmentNodes($environmentName) {
        $result = $this->chef->get('/environments/' . $environmentName . '/nodes');
        $nodes  = array();
        foreach ($result as $k => $nv) {
            $nodes[] = $k;
        }
        sort($nodes);
        return $nodes;
    }

    /**
     * Gets all nodes assigned to the specified environment and some details of each node
     * Usage: https://{$server}/chef/getEnvironmentNodesAndDetailsAction/{$environmentName}
     *
     * @return ViewModel json
     */
    public function getEnvironmentNodesAndDetailsAction() {
        $environmentName = $this->params()->fromRoute('param1');
        $nodeList        = $this->getEnvironmentNodes($environmentName);

        if (!isset($nodeList) OR $nodeList[0] == "error") {
            return $this->renderView(array(
                                         "success" => false,
                                         "error" => "Error getting nodes for environment " . $environmentName
                                     ), "json");
        }

        $nodes = array();
        foreach ($nodeList AS $node) {
            $nodeData = $this->getNodeData($node);
    
            $nodeName = $nodeData->name;
            $nodeData = $nodeData->automatic;

            $currentTime              = time();
            
            if(!isset($nodeData->ohai_time)){
                $nodeData->ohai_time = 0;
            }
            
            $nodeData->ohaiTimeString = date('Y-m-d H:i:s', $nodeData->ohai_time);

            // calculate time since check in
            $timeDiff               = $currentTime - $nodeData->ohai_time;
            $nodeData->ohaiTimeDiff = $timeDiff;
            if ($timeDiff <= 60 * 60) {
                // Ok - less than an hour
                $nodeData->ohaiTimeDelta  = sprintf("%2d min", floor($timeDiff / 60));
                $nodeData->ohaiTimeStatus = "green";
            } else if ($timeDiff <= 60 * 60 * 24) {
                // warning - less than a day
                $hours                    = $timeDiff / 60 / 60;
                $mins                     = ($hours - floor($hours)) * 60;
                $nodeData->ohaiTimeDelta  = sprintf("%d hours %2d min", floor($hours), floor($mins));
                $nodeData->ohaiTimeStatus = "goldenrod";
            } else {
                // error - less than a day
                $days                     = $timeDiff / 60 / 60 / 24;
                $hours                    = ($days - floor($days)) * 24;
                $mins                     = ($hours - floor($hours)) * 60;
                $nodeData->ohaiTimeDelta  = sprintf("%d days %d hours %2d min", floor($days), floor($hours), floor($mins));
                $nodeData->ohaiTimeStatus = "red";
            }

            $nodeData->authorized = $this->checkAuthorizedNodeEdit($nodeName);
            $nodes[$nodeName]     = $nodeData;

        }
        return $this->renderview(array(
                                     "success" => 1,
                                     "nodes"   => $nodes
                                 ), "json");
    }

    /**
     * Searches for a cookbook version constraint in the specified environment with the CB name matching the specified search string
     * usage: https://{$server}/chef/searchEnvironmentCookbooks/{$environmentName}/{$searchString}
     *
     *
     */
    /*
    public function searchEnvironmentCookbooksAction()
    {
        $environmentName = $this->params()->fromRoute('param1');
        $search          = $this->params()->fromRoute('param2');

        $envData   = $this->chef->get('/environments/' . $environmentName);
        $cookbooks = $envData->cookbook_versions;

        foreach ($cookbooks AS $k => $v) {
            if (stristr($k, $search)) {
                echo $v;
                exit();
            }
        }
        exit();
    }
*/

    /**
     * Edits/Adds cookbook version constraints to the specified environment
     * usage: https://{$server}/chef/editEnvironmentCookbookVersion/{$environmentName}/{$cookbookName}/{$operator}/{$version}
     * The operators are:
     * "eq" - equals
     * "gt" - greater than
     * "lt" - less than
     * "lteq" - less than or equal to
     * "gteq" - greater than or equal to
     *
     * @return ViewModel json
     */
    public function editEnvironmentCookbookVersionAction() {

        //needs permissions checks
        $environmentName = $this->params()->fromRoute('param1');
        $cookbookName    = $this->params()->fromRoute('param2');
        $operator        = $this->params()->fromRoute('param3');
        $version         = $this->params()->fromRoute('param4');

        if (!$this->checkAuthorizedEnvironmentEdit($environmentName)) {
            return $this->renderview(array(
                                         "success"   => false,
                                         "error"   => "User is not authorized to modify this environment",
                                         "logOutput" => "User attempted to modify version restriction " . $cookbookName . " " . $operator . " " . $version . "  on environment " . $environmentName . " but is not authorized"
                                     ), "json");

        }

        if ($operator == 'eq') {
            $operator = "=";
        } elseif ($operator == 'gt') {
            $operator = ">";
        } elseif ($operator == 'lt') {
            $operator = "<";
        } elseif ($operator == 'lteq') {
            $operator = "<=";
        } elseif ($operator == 'gteq') {
            $operator = ">=";
        }

        $envData = $this->chef->get('/environments/' . $environmentName);

        $envData->cookbook_versions->{$cookbookName} = $operator . " " . $version;

        $result = $this->chef->put('/environments', $environmentName, $envData);


        if ($result->name == $environmentName AND $result->cookbook_versions->$cookbookName == $operator . " " . $version) {
            $this->clearCache("getEnvironment", array($environmentName));
            return $this->renderview(array(
                                         "success"   => true,
                                         "logOutput" => "added/modified version restriction " . $cookbookName . " " . $operator . " " . $version . "  on environment " . $environmentName
                                     ), "json");
        }

        return $this->renderview(array(
                                     "success"   => false,
                                     "error"   => "Error setting cookbook version",
                                     "logOutput" => "Attempted to modify version restriction " . $cookbookName . " " . $operator . " " . $version . "  on environment " . $environmentName . "but encountered an error"
                                 ), "json");

    }

    /*
     * Deletes the version restriction for the specified cookbook in the specified environment
     * Usage: https://neumatic.ops.neustar.biz/chef/deleteEnvironmentCookbookVersion/{$environmentName}/{$cookbookName}?chef_server={$chefServer}
     * 
     * @return ViewModel json
     */
    public function deleteEnvironmentCookbookVersionAction() {
        $environmentName = $this->params()->fromRoute('param1');
        $cookbookName    = $this->params()->fromRoute('param2');

        if (!$this->checkAuthorizedEnvironmentEdit($environmentName)) {
            return $this->renderview(array(
                                         "success"   => 0,
                                         "error"   => "User is not authorized to modify this environment",
                                         "logOutput" => "User attempted to delete version restrictions for cookbook " . $cookbookName . "  on environment " . $environmentName . " but is not authorized"
                                     ), "json");

        }

        $envData = $this->chef->get('/environments/' . $environmentName);
        unset($envData->cookbook_versions->{$cookbookName});

        $result = $this->chef->put('/environments', $environmentName, $envData);

        if ($result->name == $environmentName AND !isset($result->cookbook_versions->{$cookbookName})) {
            $this->clearCache("getEnvironment", array($environmentName));
            return $this->renderview(array(
                                         "success"   => 1,
                                         "logOutput" => "Version restrictions for cookbook " . $cookbookName . " deleted from environment " . $environmentName
                                     ), "json");
        }

        return $this->renderview(array(
                                     "success"   => 0,
                                     "error"   => "Error deleting cookbook version restriction",
                                     "logOutput" => "Error deleting cookbook version restrictions for cookbook " . $cookbookName . " for environment " . $environmentName
                                 ), "json");
    }

    public function saveEnvironmentObjectAction() {

        $environmentName = $this->params()->fromRoute('param1');
        $environmentObj  = $this->params()->fromPost('environmentObj');

        if (!$this->checkAuthorizedEnvironmentEdit($environmentName)) {
            return $this->renderview(array(
                                         "success"   => 0,
                                         "error"   => "User is not authorized to modify this environment",
                                         "logOutput" => "User attempted to save modifications to the environment " . $environmentName . " but is not authorized"
                                     ), "json");

        }

        $result = $this->saveEnvironmentObject($environmentName, $environmentObj);

        $this->clearCache("getEnvironments");
        $this->clearCache("getEnvironmentsDetailed");

        if (is_object($result) AND isset($result->name) AND $result->name == $environmentName) {
            return $this->renderview(array(
                                         "success"     => 1,
                                         "environment" => $result,
                                         "logOutput"   => "Saved modifications to the environment " . $environmentName
                                     ), "json");
        }

        return $this->renderview(array(
                                     "success"   => 0,
                                     "error"   => $result,
                                     "logOutput" => "User attempted to save modifications to the environment " . $environmentName . " but encountered an error"
                                 ), "json");
    }

    private function saveEnvironmentObject($environmentName, $environmentObj) {
        //we should first check over the object to see if it is valid.

        $envExists = $this->checkEnvironmentExists($environmentName);
        if ($envExists) {
           
           $result = $this->chef->put('/environments', $environmentName, json_decode($environmentObj, JSON_NUMERIC_CHECK));
        } else {
               
           $result = $this->chef->post('/environments', json_decode($environmentObj, JSON_NUMERIC_CHECK));
        }
        return $result;
    }

    public function saveEnvironmentAction() {


        $environmentName = $this->params()->fromPost('environmentName');

        $newEnvironment = $this->params()->fromPost('newEnvironment');

        if ($newEnvironment != true) {
            if (!$this->checkAuthorizedEnvironmentEdit($environmentName)) {
                return $this->renderview(array(
                                             "success"   => 0,
                                             "error"   => "User is not authorized to modify this environment",
                                             "logOutput" => "User attempted to save modifications to the environment " . $environmentName . " but is not authorized"
                                         ), "json");

            }
        }

        $this->clearCache("getEnvironments");
        $this->clearCache("getEnvironmentsDetailed");
        $this->clearCache("getEnvironment", array($environmentName));
        $this->clearCache("getEnvironmentNodesAndDetails", array($environmentName));

        $environmentDescription = $this->params()->fromPost('environmentDescription');

        $environmentOwnerGroup = $this->params()->fromPost('environmentOwnerGroup');

        $environmentBusinessServiceArray = $this->params()->fromPost('environmentBusinessService');
        $environmentBusinessService      = $environmentBusinessServiceArray['name'];

        if ($environmentSubsystemArray = $this->params()->fromPost('environmentSubsystem')) {

            $environmentSubsystem = $environmentSubsystemArray['name'];
        } else {
            $environmentSubsystem = "";
        }

        $environmentLocationArray = $this->params()->fromPost('environmentLocation');
        $environmentLocation      = $environmentLocationArray['name'];

        $environmentTimezone = $this->params()->fromPost('environmentTimezone');

        $newEnvironment = $this->params()->fromPost('newEnvironment');

        $default_attributes = json_decode(json_encode($this->params()->fromPost('default_attributes'), JSON_NUMERIC_CHECK), JSON_NUMERIC_CHECK);

        $override_attributes = json_decode(json_encode($this->params()->fromPost('override_attributes'), JSON_NUMERIC_CHECK), JSON_NUMERIC_CHECK);

        if ($newEnvironment == 'false') {

            $environmentObj = $this->getEnvironmentObject($environmentName);

        } else {

            $environmentObj       = new \stdClass();
            $environmentObj->name = $environmentName;

            $cookbook_versions = json_decode(json_encode($this->params()->fromPost('cookbook_versions'), JSON_NUMERIC_CHECK), JSON_NUMERIC_CHECK);

        }
        $environmentObj->description = $environmentDescription;
        if (empty($default_attributes)) {
            if (isset($environmentObj->default_attributes)) {
                $default_attributes = $environmentObj->default_attributes;
            } else {
                $default_attributes = new \stdClass();
            }

        }
        if (empty($override_attributes) AND isset($environmentObj->override_attributes)) {
            if (isset($environmentObj->override_attributes)) {
                $override_attributes = $environmentObj->override_attributes;
            } else {
                $override_attributes = new \stdClass();
            }
        }

        if (!isset($default_attributes->neustar)) {
            $default_attributes->neustar = array();    
        }
        
        if(is_object($default_attributes)){
            $default_attributes = $this->object_to_array($default_attributes);
        }
        
        if(isset($environmentOwnerGroup) AND $environmentOwnerGroup != null AND $environmentOwnerGroup != "" AND $environmentOwnerGroup != '0'){
            if(is_object($default_attributes)){
                $default_attributes->neustar['ownerGroup'] = $environmentOwnerGroup;
                
            }
            if(is_array($default_attributes)){
                $default_attributes['neustar']['ownerGroup'] = $environmentOwnerGroup;
            
            }
        }
        
        if(isset($environmentBusinessService) AND $environmentBusinessService != null AND $environmentBusinessService != "" AND $environmentBusinessService != '0' ){
            if(is_object($default_attributes)){
                $default_attributes->neustar['business_service'] = $environmentBusinessService;
                
            }
            if(is_array($default_attributes)){
                $default_attributes['neustar']['business_service'] = $environmentBusinessService;
            
            }
        }
        
        if(isset($environmentSubsystem) AND $environmentSubsystem != null AND $environmentSubsystem != "" AND $environmentSubsystem != '0'){
            if(is_object($default_attributes)){
            $default_attributes->neustar['subsystem'] = $environmentSubsystem;
                
            }
            if(is_array($default_attributes)){
                $default_attributes['neustar']['subsystem'] = $environmentSubsystem;
                
            }
        }
        
        if(isset($environmentLocation) AND $environmentLocation != null AND $environmentLocation != "" AND $environmentLocation != '0'){
            if(is_object($default_attributes)){
                $default_attributes->neustar['location'] = $environmentLocation;
            }
            if(is_array($default_attributes)){
                $default_attributes['neustar']['location'] = $environmentLocation;
            }
        }
        
        if(isset($environmentTimezone) AND $environmentTimezone != null AND $environmentTimezone != "" AND $environmentTimezone != '0'){
            if(is_object($default_attributes)){
                $default_attributes->neustar['timezone'] = $environmentTimezone;
                
            }
            if(is_array($default_attributes)){
                $default_attributes['neustar']['timezone'] = $environmentTimezone;
                
            }
        }

        if ($newEnvironment == 'false') {
            $environmentObj->override_attributes = $override_attributes;
            $environmentObj->default_attributes  = $default_attributes;
            
            if (is_object($environmentObj->override_attributes) && (count(get_object_vars($environmentObj->override_attributes)) < 1)) {

                unset($environmentObj->override_attributes);
            }
            if (is_object($environmentObj->cookbook_versions) && (count(get_object_vars($environmentObj->cookbook_versions)) < 1)) {

                unset($environmentObj->cookbook_versions);
            }

            $saveEnvironmentResult = $this->saveEnvironmentObject($environmentName, json_encode($environmentObj, JSON_NUMERIC_CHECK));

        } else {
            $environmentObj->default_attributes = $default_attributes;
            if (isset($cookbook_versions) AND !empty($cookbook_versions)) {
                $cbv = new \stdClass();
                foreach ($cookbook_versions AS $k => $v) {
                    $cbv->$k = $v['operator'] . " " . $v['version'];
                }
                $environmentObj->cookbook_versions = $cbv;
            } 
            //$environmentObj->override_attributes = (object)NULL;
            $environmentObj->json_class = "Chef::Environment";
            $environmentObj->chef_type  = "environment";
            //unset($environmentObj->cookbook_versions);

            $saveEnvironmentResult = $this->saveEnvironmentObject($environmentName, json_encode($environmentObj, JSON_NUMERIC_CHECK));

        }

        if (isset($saveEnvironmentResult->uri) OR isset($saveEnvironmentResult->name)) {
            $this->clearCache("getEnvironments");
            $this->clearCache("getEnvironmentsDetailed");
            return $this->renderview(array(
                                         "success"     => 1,
                                         "environment" => $saveEnvironmentResult,
                                         "logOutput"   => "Saved modifications to the environment " . $environmentName
                                     ), "json");
        } else {

            return $this->renderview(array(
                                         "success"   => 0,
                                         "error"   => $saveEnvironmentResult,
                                         "logOutput" => "User attempted to save modifications to the environment " . $environmentName . " but encountered an error"
                                     ), "json");
        }

    }

    public function saveNewEnvironmentObjectAction() {

        $environmentName = $this->params()->fromRoute('param1');
        $environmentObj  = $this->params()->fromPost('environmentObj');

        $result = $this->chef->post('/environments', json_decode($environmentObj, JSON_NUMERIC_CHECK));


        if (is_object($result) AND isset($result->uri)) {

            $this->clearCache("getEnvironments");
            $this->clearCache("getEnvironmentsDetailed");
            $this->clearCache("getEnvironment", array($environmentName));
            $this->clearCache("getEnvironmentNodesAndDetails", array($environmentName));

            return $this->renderview(array(
                                         "success"     => 1,
                                         "environment" => $result,
                                         "logOutput"   => "New environment " . $environmentName . " created",
                                     ), "json");
        }

        return $this->renderview(array(
                                     "success"   => 0,
                                     "error"   => $result,
                                     "logOutput" => "Error creating new environment " . $environmentName
                                 ), "json");
    }

    /*
     * Deletes the specified environment from the chef server
     * Usage: https://neumatic.ops.neustar.biz/chef/deleteEnvironment/{$environmentName}?chef_server={$chefServer}
     * 
     * @return ViewModel json
     */
    public function deleteEnvironmentAction() {

        $environmentName = $this->params()->fromRoute('param1');

        if (!$this->checkAuthorizedEnvironmentEdit($environmentName)) {
            return $this->renderview(array(
                                         "success"   => 0,
                                         "error"   => "User is not authorized to modify this environment",
                                         "logOutput" => "User attempted to delete the environment " . $environmentName . " but is not authorized",
                                         "logLevel"  => \Zend\Log\Logger::INFO
                                     ), "json");

        }

        $result = $this->chef->delete('/environments', $environmentName);

        if (is_object($result) AND isset($result->name)) {

            $this->clearCache("getEnvironments");
            $this->clearCache("getEnvironmentsDetailed");
            $this->clearCache("getEnvironment", array($environmentName));
            $this->clearCache("getEnvironmentNodesAndDetails", array($environmentName));

            return $this->renderview(array(
                                         "success"   => 1,
                                         "result"    => $result,
                                         "logOutput" => "Deleted environment " . $environmentName,
                                         "logLevel"  => \Zend\Log\Logger::NOTICE
                                     ), "json");
        }

        if (isset($result->error)) {
            $error = $result->error;
        } else {
            $error = $result;
        }

        return $this->renderview(array(
                                     "success"   => 0,
                                     "error"   => $error,
                                     "logOutput" => "Error while attempting to delete the environment " . $environmentName,
                                     "logLevel"  => \Zend\Log\Logger::ERR
                                 ), "json");

    }

    /********************************************* Clients *********************************************/
    /**
     * Checks if the currently logged in user has permission to modify the given client.
     * usage: https://neumatic.ops.neustar.biz/chef/checkAuthorizedClientEdit/{$clientName}
     *
     * @return ViewModel
     *
     */
    public function checkAuthorizedClientEditAction() {
        $clientName = $this->params()->fromRoute('param1');
        try {
            $authorized = $this->checkAuthorizedClientEdit($clientName);
        } catch (\Exception $e) {
            return $this->renderView(array(
                                         "success"    => false,
                                         "error"    => $e->getMessage(),
                                         "logOutput"  => "There was an error checking if authorized to modify the client : " . $e->getMessage(),
                                         "parameters" => "[clientName: " . $clientName . ", chefServer: " . $this->chefServer . "]",
                                         "logLevel"   => \Zend\Log\Logger::ERR,
                                         "trace"      => $e->getTraceAsString()
                                     ));
        }

        return $this->renderview(array(
                                     "success"    => 1,
                                     "authorized" => $authorized,
                                     "parameters" => "[clientName: " . $clientName . ", chefServer: " . $this->chefServer . "]",
                                     "logOutput"  => "Checked if user is authorized for the client " . $clientName,
                                     "logLevel"   => \Zend\Log\Logger::DEBUG,
                                     "cache"      => true,
                                     "cacheTTL"   => "300"
                                 ), "json");
    }

    private function checkAuthorizedClientEdit($clientName) {

        if (isset($_COOKIE['userAdminOn'])) {
            if ($this->userType == 'Admin' AND $_COOKIE['userAdminOn'] == 'true') {
                return true;
            }
        } else {
            if ($this->userType == 'Admin') {
                return true;
            }
        }
        //userCreated
        $serverTable = new Model\NMServerTable($this->_config);
        $server      = $serverTable->getByName($clientName);
        $userCreated = $server->get('userCreated');
        if ($userCreated == $this->_user->getUsername()) {
            return true;
        }
        return false;
    }

    /**
     * Checks if a client with the given name exists on the server.
     * usage: https://{$server}/chef/checkClientExists/{$clientName}
     *
     * @return ViewModel
     *
     */
    public function checkClientExistsAction() {
        $client = $this->params()->fromRoute('param1');
        $result = $this->chef->get('/clients/' . $client);

        if (isset($result->error) AND stristr($result->error[0], "Cannot load")) {
            echo "0";
            exit();
        } elseif (isset($result->name)) {
            echo "1";
            exit();
        }
        echo "0";
        exit();
       
    }
    private function checkClientExists($clientName){
        $client     = $this->chef->get('/clients/' . $clientName);
        if (isset($client->name)) {
            return true;
        }elseif (isset($client->error) AND stristr($client->error[0], "not found")) {
            return false;
        }
        return false;
    }
    /**
     * Gets a specific client from the Chef Server.
     * Usage: https://neumatic.ops.neustar.biz/chef/getClient/{$clientName}
     *
     * @return ViewModel
     *
     */
    public function getClientAction() {
        $clientName = $this->params()->fromRoute('param1');
        $client     = $this->chef->get('/clients/' . $clientName);

        if (is_object($client) AND isset($client->name)) {
            return $this->renderview(array(
                                         "success" => 1,
                                         'client'  => $client
                                     ), "json");
        }

        return $this->renderview(array(
                                     "success"   => 0,
                                     "error"   => "Error getting client " . $clientName,
                                     "logOutput" => "There was an error getting the client " . $clientName . " : " . $client
                                 ), "json");

    }

    /**
     * Gets all Clients registered on the Chef server.
     * usage: https://{$server}/chef/getClients
     *
     * @return ViewModel
     *
     */
    public function getClientsAction() {
        $result = $this->chef->get('/clients');


        if (is_object($result) AND !empty($result)) {

            $clients = array();
            foreach ($result AS $k => $v) {
                $clients[] = $k;
            }

            return $this->renderview(array(
                                         "success" => '1',
                                         "clients" => $clients
                                     ), "json");
        }

        return $this->renderview(array(
                                     "success"   => '0',
                                     "error"   => "Error getting client list",
                                     "logOutput" => "There was an error getting the client list : " . $result
                                 ), "json");
    }

    /**
     * Deletes a specific client from the server.
     * usage: https://{$server}/chef/deleteClient/{$clientName}
     *
     * @return ViewModel
     *
     */
    public function deleteClientAction() {

        $clientName = $this->params()->fromRoute('param1');

        if(!$this->checkClientExists($clientName)){
             return $this->renderview(array(
                                         "success"   => 1,
                                         "error"   => "Client " . $clientName . " does not exist on the server, so no need to delete it.",
                                         "logOutput" => "User attempted to delete the client " . $clientName . " but it does not exist so nothing to do.",
                                         "logLevel"  => 3
                                     ), "json");
        }

        if (!$this->checkAuthorizedClientEdit($clientName)) {
            return $this->renderview(array(
                                         "success"   => 0,
                                         "error"   => "User is not authorized to modify this client",
                                         "logOutput" => "User attempted to delete the client " . $clientName . " but is not authorized",
                                         "logLevel"  => \Zend\Log\Logger::INFO
                                     ), "json");

        }

        $result = $this->chef->delete('/clients', $clientName);

        if (isset($result->name)) {
            return $this->renderview(array(
                                         "success"    => 1,
                                         "result"     => $result,
                                         "logOutput"  => "Client " . $clientName . " deleted.",
                                         "logLevel"   => \Zend\Log\Logger::NOTICE,
                                         "parameters" => "[clientName: " . $clientName . ", chefServer: " . $this->chefServer . "]"
                                     ), "json");
        } elseif (isset($result->error) AND stristr($result->error[0], "Cannot load")) {
            return $this->renderview(array(
                                         "success"   => 1,
                                         "logOutput" => "Attempted to delete client '" . $clientName . "' but it doesn't exist on the server, so no problem.",
                                         "logLevel"  => \Zend\Log\Logger::INFO
                                     ), "json");
        }
        return $this->renderview(array(
                                     "success"   => 0,
                                     "error"   => "Error deleting client " . $clientName,
                                     "result"    => $result,
                                     "logOutput" => "There was an error while attempting to delete the client " . $clientName,
                                     "logLevel"  => 1
                                 ), "json");

    }

    /********************************************* Cookbooks *********************************************/

    /*
    * Check if the currently logged in ldap user is authorized to edit the given cookbook.
    * 
    * Usage: https://neumatic.ops.neustar.biz/chef/checkAuthorizedCookbookEdit/{$cookbookName}?chef_server={$chefServer} 
    * 
    */
    public function checkAuthorizedCookbookEditAction() {
        $cookbookName = $this->params()->fromRoute('param1');
        try {
            $authorized = $this->checkAuthorizedCookbookEdit($cookbookName);

        } catch (\Exception $e) {
            return $this->renderView(array(
                                         "success"    => false,
                                         "error"    => $e->getMessage(),
                                         "logOutput"  => "There was an error checking if authorized to modify the cookbook : " . $e->getMessage(),
                                         "logLevel"   => \Zend\Log\Logger::ERR,
                                         "parameters" => "[cookbookName: " . $cookbookName . ", chefServer: " . $this->chefServer . "]",
                                         "trace"      => $e->getTraceAsString()
                                     ));
        }

        return $this->renderview(array(
                                     "success"    => 1,
                                     "authorized" => $authorized,
                                     "logOutput"  => "Checked if user is authorized for the cookbook " . $cookbookName,
                                     "logLevel"   => \Zend\Log\Logger::DEBUG,
                                     "parameters" => "[cookbookName: " . $cookbookName . ", chefServer: " . $this->chefServer . "]",
                                     "cache"      => true,
                                     "cacheTTL"   => "300"
                                 ), "json");
    }

    private function checkAuthorizedCookbookEdit(/** @noinspection PhpUnusedParameterInspection */
        $cookbookName) {
        return true;

        /*
        if (isset($_COOKIE['userAdminOn'])) {
            if ($this->userType == 'Admin' AND $_COOKIE['userAdminOn'] == 'true') {
                return true;
            }
        } else {
            if ($this->userType == 'Admin') {
                return true;
            }
        }

        if (!isset($this->userGroups)) {
            $this->userGroups = $this->getUserGroups();
        }

        $cookbook = $this->getCookbook($cookbookName);

        if (isset($cookbook->metadata->attributes->ownerGroup->value)) {
            $ownerGroup = $cookbook->metadata->attributes->ownerGroup->value;


            foreach ($this->userGroups AS $ugId) {
                $ug     = $this->usergroupTable->getById($ugId);
                $ugName = $ug->get('name');
                if ($ugName == $ownerGroup) {
                    return true;
                }
            }
        }
        return false;
        */
    }


    /**
     * Checks if a cookbook with the given name exists on the server
     * usage: https://{$server}/chef/checkCookbookExists/{$cookbookName}
     *
     * @return ViewModel
     *
     */
    public function checkCookbookExistsAction() {
        $cookbookName = $this->params()->fromRoute('param1');
        $result       = $this->chef->get('/cookbooks/' . $cookbookName);

        if (isset($result->error) AND stristr($result->error[0], "Cannot find")) {
            echo "0";
            exit();
        } elseif (isset($result->{$cookbookName})) {
            echo "1";
            exit();
        }
        return $this->renderview($result, "json");
    }

    /**
     * Gets a specific cookbook with the given name
     * usage: https://{$server}/chef/getCookbook/{$cookbookName}
     *
     * @return ViewModel
     *
     */
    public function getCookbookAction() {
        $cookbookName    = $this->params()->fromRoute('param1');
        try {
            $cookbookDetails = $this->getCookbook($cookbookName);

            if (is_object($cookbookDetails) AND isset($cookbookDetails->cookbook_name)) {

                return $this->renderview(array(
                                             "success"  => 1,
                                             "cookbook" => $cookbookDetails
                                         ), "json");
            }
            return $this->renderview(array(
                                         "success" => 0,
                                         "error" => "Error getting cookbook " . $cookbookName,
                                         "result"  => $cookbookDetails
                                     ), "json");

        } catch (\Exception $e) {
            return $this->renderView(array(
                                         "success"    => false,
                                         "error"    => $e->getMessage(),
                                         "logOutput"  => "There was an error getting the cookbook " . $cookbookName . " : " . $e->getMessage(),
                                         "parameters" => "[cookbookName: " . $cookbookName . ", chefServer: " . $this->chefServer . "]",
                                         "logLevel"   => \Zend\Log\Logger::ERR,
                                         "trace"      => $e->getTraceAsString()
                                     ));
        }
    }

    private function getCookbook($cookbookName) {

        $getVersionsResult = $this->chef->get('/cookbooks/' . $cookbookName);


        if (is_object($getVersionsResult) AND isset($getVersionsResult->$cookbookName)) {
            $versions                  = $getVersionsResult->$cookbookName->versions;
            $cookbookDetails           = $this->chef->get('/cookbooks/' . $cookbookName . '/_latest');
            $cookbookDetails->versions = $versions;
            return $cookbookDetails;
        }
        return $getVersionsResult;
    }

    public function getCookbookVersionDetailsAction(){
        $cookbookName    = $this->params()->fromRoute('param1');
        $cookbookVersion    = $this->params()->fromRoute('param2');
        $cookbookVersionDetails = $this->getCookbookVersionDetails($cookbookName, $cookbookVersion);
        if (is_object($cookbookVersionDetails) AND isset($cookbookVersionDetails->cookbook_name)) {

            return $this->renderview(array(
                                        "success"  => 1,
                                        "cookbook" => $cookbookVersionDetails
                                     ), "json");
        }
    }

    private function getCookbookVersionDetails($cookbookName, $cookbookVersion) {

        $getVersionDetailsResult = $this->chef->get('/cookbooks/' . $cookbookName . '/' . $cookbookVersion);


        return $getVersionDetailsResult;

    }


    /**
     * Gets a json list of all versions of the specified cookbook that are installed on the server.
     * usage: https://{$server}/chef/getCookbookVersions/{$cookbookName}
     *
     * @return ViewModel
     *
     */

    public function getCookbookVersionsAction() {
        $cookbookName = $this->params()->fromRoute('param1');
        try {
            $versions = $this->getCookbookVersions($cookbookName);
            if (count($versions) >= 1) {
                return $this->renderview(array(
                                             "success"  => true,
                                             "versions" => $versions
                                         ), "json");
            }

            return $this->renderview(array(
                                         "success"  => true,
                                         "error"  => "No versions of this cookbook are installed on the server.",
                                         "versions" => ""
                                     ), "json");

        } catch (\Exception $e) {
            return $this->renderView(array(
                                         "success"    => false,
                                         "error"    => $e->getMessage(),
                                         "logOutput"  => "There was an error getting installed versions of cookbook " . $cookbookName . " : " . $e->getMessage(),
                                         "parameters" => "[cookbookName: " . $cookbookName . ", chefServer: " . $this->chefServer . "]",
                                         "logLevel"   => \Zend\Log\Logger::ERR,
                                         "trace"      => $e->getTraceAsString()
                                     ));
        }


    }

    private function getCookbookVersions($cookbookName) {

        $cbObj = $this->chef->get('/cookbooks/' . $cookbookName);
        if (is_object($cbObj) AND is_object($cbObj->$cookbookName) AND isset($cbObj->$cookbookName->versions)) {
            $versionsObj = $cbObj->$cookbookName->versions;

            $versions = array();
            foreach ($versionsObj AS $v) {
                $versions[] = $v->version;
            }
            return $versions;
        }
        return array();
    }


    /**
     * Gets a list of all cookbooks on the server
     * usage: https://{$server}/chef/getCookbooks
     *
     * @return ViewModel
     *
     */
    public function getCookbooksAction() {

        $cookbooks = $this->getAllCookbooks();

        if (is_array($cookbooks)) {
            return $this->renderview(array(
                                         "success"   => true,
                                         "cookbooks" => $cookbooks,
                                         "cache"     => true
                                     ), "json");
        }

        return $this->renderview(array(
                                     "success" => false,
                                     "error" => "Error getting cookbooks"
                                 ), "json");

    }

    public function getAllRecipesAction() {
        $cookbooks = $this->getAllCookbooks();
        $recipes   = array();
        /** @noinspection PhpUnusedLocalVariableInspection */
        foreach ($cookbooks AS $k => $v) {
            $recipeList = $v['recipes'];
            foreach ($recipeList as $recipe) {
                $recipes[] = $recipe;
            }
        }
        if (is_array($recipes)) {
            return $this->renderview(array(
                                         "success"  => 1,
                                         "recipes"  => $recipes,
                                         "cache"    => true,
                                         "cacheTTL" => "300"
                                     ), "json");
        }

        return $this->renderview(array(
                                     "success" => 0, "error" => "Error getting recipes"
                                 ), "json");

    }

    /**
     * Gets all cookbooks currently installed the server
     * Usage: https://{$server}/chef/getAllCookbooks
     *
     */

    private function getAllCookbooks() {
        $result    = $this->chef->get('/cookbooks');
        $cookbooks = array();

        foreach ($result AS $k => $v) {
            $cb         = array();
            $cb['name'] = $k;

            $cb['versions'] = $this->getCookbookVersions($k);

            $latestVersion = $cb['versions'][0];

            $cb['recipes'] = $this->getCookbookVersionRecipes($k, $latestVersion);

            $cb['authorized'] = false;

            $cbAuth = $this->checkAuthorizedCookbookEdit($k);

            if ($cbAuth == true) {
                $cb['authorized'] = true;
            }
            $cookbooks[] = $cb;
        }
        usort($cookbooks, function (array $a, array $b) {
            return strnatcasecmp($a['name'], $b['name']);
        });

        return $cookbooks;
    }


    /**
     * Search for cookbooks with a name that contains the search string
     * usage: https://{$server}/chef/searchCookbooks/{$searchString}
     *
     * @return ViewModel json
     */
    public function searchCookbooksAction() {
        $search    = $this->params()->fromRoute('param1');
        $cookbooks = $this->getAllCookbooks();
        $results   = array();
        foreach ($cookbooks AS $CB) {
            if (stristr($CB['name'], $search)) {
                $results[] = $CB['name'];
            }
        }

        if (is_array($results)) {
            return $this->renderview(array(
                                         "success"   => 1,
                                         "cookbooks" => $results
                                     ), "json");
        }

        return $this->renderview(array(
                                     "success" => 0,
                                     "error" => "No match found in cookbooks for " . $search
                                 ), "json");
    }

    /**
     * Gets details on the specified cookbook version
     * Usage: https://{$server}/chef/getCookbookVersion/{$cookbookName}/{$cookbookVersion}
     *
     * @return ViewModel json
     */
    public function getCookbookVersionAction() {
        $cookbook = $this->params()->fromRoute('param1');
        $version  = $this->params()->fromRoute('param2');
        $result   = $this->chef->get('/cookbooks/' . $cookbook . '/' . $version);

        if (is_object($result) AND $result->cookbook_name == $cookbook) {
            return $this->renderview(array(
                                         "success"  => 1,
                                         "cookbook" => $result
                                     ), "json");
        }
        return $this->renderview(array(
                                     "success" => 0,
                                     "error" => "Error getting cookbook '" . $cookbook . "' version '" . $version . "'"
                                 ), "json");
    }


    private function getCookbookVersionRecipes($cookbookName, $cookbookVersion = 'latest') {
        $cookbookVersionDetails = $this->chef->get('/cookbooks/' . $cookbookName . '/' . $cookbookVersion);
        $recipes                = $cookbookVersionDetails->metadata->recipes;
        $recipeList             = $this->object_to_array($recipes);
        $recipes                = array();
        foreach ($recipeList AS $k => $v) {
            $recipes[] = $k;
        }

        return $recipes;

    }

    /**
     * Adds the specific cookbook to the given node's run list
     * usage: https://{$server}/chef/addCookbookToNode/{$cookbookName}/{$nodeName}
     *
     * @return ViewModel json
     */
    public function addCookbookToNodeAction() {
        $cookbookName = $this->params()->fromRoute('param1');
        $nodeName     = $this->params()->fromRoute('param2');

        if (!$this->checkAuthorizedNodeEdit($nodeName)) {
            return $this->renderview(array(
                                         "success"   => 0,
                                         "error"   => "User is not authorized to modify this node",
                                         "logOutput" => "User attempted to add the cookbook " . $cookbookName . " to the node " . $nodeName . " but is not authorized"
                                     ), "json");

        }

        // check if cookbook exists
        $cookbook = $this->chef->get('/cookbooks/' . $cookbookName);

        if (isset($cookbook->error) AND stristr($cookbook->error[0], "Cannot find")) {
            /** @noinspection PhpUnusedLocalVariableInspection */
            $cookbookExists = false;
            return $this->renderview(array(
                                         "success" => 0,
                                         "error" => "Cannot find cookbook " . $cookbookName
                                     ), "json");

        } elseif (isset($cookbook->{$cookbookName})) {
            /** @noinspection PhpUnusedLocalVariableInspection */
            $cookbookExists = true;
        } else {
            return $this->renderview(array(
                                         "success" => 0,
                                         "error" => "Error checking if cookbook " . $cookbookName . " exists."
                                     ), "json");

        }

        // check if node exists
        $node = $this->chef->get('/nodes/' . $nodeName);

        if (isset($node->error) AND stristr($node->error[0], "not found")) {
            /** @noinspection PhpUnusedLocalVariableInspection */
            $nodeExists = false;
            return $this->renderview(array(
                                         "success" => 0,
                                         "error" => "Cannot find node " . $nodeName
                                     ), "json");

        } elseif (isset($node->name)) {
            /** @noinspection PhpUnusedLocalVariableInspection */
            $nodeExists = true;
        } else {
            return $this->renderview(array(
                                         "success" => 0,
                                         "error" => "Error checking if node " . $nodeName . " exists"
                                     ), "json");

        }

//need cookbook dependency checks here!

        //check if cookbook is already applied
        $nodeRunList = $node->run_list;

        /** @noinspection PhpUnusedLocalVariableInspection */
        $cookbookApplied = false;
        foreach ($nodeRunList AS $nr) {
            if (stristr($nr, "recipe[")) {
                $nr = str_replace('recipe[', "", $nr);
                $nr = str_replace(']', "", $nr);
            }

            if ($nr == $cookbookName) {
                /** @noinspection PhpUnusedLocalVariableInspection */
                $cookbookApplied = true;
                return $this->renderview(array(
                                             "success" => 1,
                                             'error' => 'The cookbook "' . $cookbookName . '" is already applied to the node "' . $nodeName . '"'
                                         ), "json");

                break;
            }
        }

        // add cookbook to node
        $nodeRoles[] = $cookbookName;

        //$node->automatic->roles = $nodeRoles;
        $node->run_list[] = "recipe[" . $cookbookName . "]";
        // apply
        $update = $this->chef->put('/nodes', $nodeName, $node);

        if (is_object($update) AND $update->name == $nodeName) {
            return $this->renderview(array(
                                         "success" => 1,
                                         "node"    => $update
                                     ), "json");
        }
        return $this->renderview(array(
                                     "success" => 0,
                                     "error" => "Error adding " . $cookbookName . " to node " . $nodeName
                                 ), "json");

    }

    /*
    * Removes the specified version of the specified cookbook from the server.
    * 
    * Usage: https://neumatic.ops.neustar.biz/chef/deleteCookbookVersion/{$cookbookName}/{$cookbookVersion}?chef_server={$chefServer} 
    * 
    */

    public function deleteCookbookVersionAction() {

        $cookbookName    = $this->params()->fromRoute('param1');
        $cookbookVersion = $this->params()->fromRoute('param2');

        if (!$this->checkAuthorizedCookbookEdit($cookbookName)) {
            return $this->renderview(array(
                                         "success"   => 0,
                                         "error"   => "User is not authorized to modify this cookbook",
                                         "logOutput" => "User attempted to delete the version " . $cookbookVersion . " of the cookbook " . $cookbookName . " but is not authorized",
                                         "logLevel"  => '2'
                                     ), "json");
        }

        $result = $this->chef->delete('/cookbooks', $cookbookName, $cookbookVersion);

        if (is_object($result) AND isset($result->recipes)) {
            $this->clearCache("getCookbooks");
            return $this->renderview(array(
                                         "success"   => 1,
                                         "result"    => $result,
                                         "logOutput" => "Deleted version " . $cookbookVersion . " of cookbook " . $cookbookName . " from the server",

                                     ), "json");
        }

        return $this->renderview(array(
                                     "success"   => 0,
                                     "error"   => "Error deleting cookbook.",
                                     "result"    => $result,
                                     "logOutput" => "There was an error deleting the version" . $cookbookVersion . " of cookbook " . $cookbookName . " from the server"
                                 ), "json");

    }


    /********************************************* DataBags *********************************************/


    /********************************************* Nodes *********************************************/
    public function checkAuthorizedNodeEditAction() {
        $nodeName = $this->params()->fromRoute('param1');

        try {
            if ($nodeName == "" OR $nodeName == null) {
                return $this->renderview(array(
                                             "success"    => 0,
                                             "authorized" => 0,
                                             "logOutput"  => "nodeName is empty",
                                             "logLevel"   => \Zend\Log\Logger::DEBUG,
                                             "parameters" => "[nodeName: " . $nodeName . ", chefServer: " . $this->chefServer . "]"
                                         ), "json");
            }

            $authorized = $this->checkAuthorizedNodeEdit($nodeName);
            return $this->renderview(array(
                                         "success"    => 1,
                                         "authorized" => $authorized,
                                         "logOutput"  => "Checked if user is authorized for the node " . $nodeName,
                                         "logLevel"   => \Zend\Log\Logger::DEBUG,
                                         "parameters" => "[nodeName: " . $nodeName . ", chefServer: " . $this->chefServer . "]",
                                         "cache"      => true,
                                         "cacheTTL"   => "300"
                                     ), "json");
        } catch (\Exception $e) {
            return $this->renderView(array(
                                         "success"    => false,
                                         "error"    => $e->getMessage(),
                                         "logOutput"  => "There was an error checking if authorized to modify the node : " . $e->getMessage(),
                                         "logLevel"   => \Zend\Log\Logger::ERR,
                                         "parameters" => "[nodeName: " . $nodeName . ", chefServer: " . $this->chefServer . "]",
                                         "trace"      => $e->getTraceAsString()
                                     ));
        }

    }

    private function checkAuthorizedNodeEdit($nodeName) {
        if (isset($_COOKIE['userAdminOn'])) {
            if ($this->userType == 'Admin' AND $_COOKIE['userAdminOn'] == 'true') {
                return true;
            }
        } else {
            if ($this->userType == 'Admin') {
                return true;
            }
        }


        if (!isset($this->userGroups)) {
            $this->userGroups = $this->getUserGroups();
        }

        //userCreated
        $serverTable = new Model\NMServerTable($this->_config);
        $server      = $serverTable->getByName($nodeName);
        $userCreated = $server->get('userCreated');
        if ($userCreated == $this->_user->getUsername()) {
            return true;
        }

        $node   = $this->nodeTable->getByName($nodeName);
        $nodeId = $node->getId();

        $nodeUsergroups = $this->nodeToUsergroupTable->getUsergroupsByNodeId($nodeId);

        if (empty($nodeUsergroups)) {
            unset($node);
            return false;

        } else {
            foreach ($nodeUsergroups AS $nug) {
                if (in_array($nug['usergroup_id'], $this->userGroups)) {
                    unset($node);
                    return true;

                }
            }
        }


        unset($node);
        return false;
    }

    /**
     * Check if a node exists on the server and return '1' if so, '0' if not.
     * Usage: https://{$server}/chef/checkNodeExists/{$nodeName}
     *
     */
    public function checkNodeExistsAction() {
        $nodeName = $this->params()->fromRoute('param1');
        $node     = $this->chef->get('/nodes/' . $nodeName);

        if (isset($node->name)) {
            echo "1";
            exit();
        }elseif (isset($node->error) AND stristr($node->error[0], "not found")) {
            echo "0";
            exit();
        }else{
            echo "0";
            exit();
        }
    }
    private function checkNodeExists($nodeName){
        $node     = $this->chef->get('/nodes/' . $nodeName);
        if (isset($node->name)) {
            return true;
        }elseif (isset($node->error) AND stristr($node->error[0], "not found")) {
            return false;
        }
        return false;
    }
    /**
     * Gets details of the specified node
     * usage: https://{$server}/chef/getNode/{$nodeName}
     * @return ViewModel json
     */
    public function getNodeAction() {
        $nodeName = $this->params()->fromRoute('param1');
        $node     = $this->getNode($nodeName);


        if (is_object($node) AND isset($node->name) AND $node->name == $nodeName) {
            return $this->renderview(array(
                                         "success" => 1,
                                         "node"    => $node
                                     ), "json");
        }

        return $this->renderview(array(
                                     "success" => 0,
                                     "error" => "Error getting node " . $nodeName
                                 ), "json");

    }

    private function getNode($nodeName) {
        $node             = $this->chef->get('/nodes/' . $nodeName);
        $node->authorized = $this->checkAuthorizedNodeEdit($nodeName);
        return $node;

    }

    /**
     * Gets a list of all nodes on the server
     * usage: https://{$server}/chef/getNodes
     *
     * @return ViewModel json
     */
    public function getNodesAction() {
        try {    
            $result = $this->chef->get('/nodes');
            $nodes  = array();
            foreach ($result AS $k => $v) {
                $nodes[] = $k;
            }
            sort($nodes);

                 
            if (is_array($nodes)) {   
                return $this->renderview(array(
                                         "success"    => true,
                                         "nodes" => $nodes,
                                         "logOutput"  => "Got the list of nodes for the server " . $this->chefServer,
                                         "logLevel"   => \Zend\Log\Logger::DEBUG,
                                         "parameters" => "[chefServer: " . $this->chefServer . "]",
                                         "cache"      => true,
                                         "cacheTTL"   => "300"
                                     ), "json");
            }else{
                return $this->renderview(array(
                                         "success"    => false,
                                         "logOutput"  => "Error getting the list of nodes for the server " . $this->chefServer,
                                         "logLevel"   => \Zend\Log\Logger::DEBUG,
                                         "parameters" => "[chefServer: " . $this->chefServer . "]"
                                     ), "json");
            }                          
        } catch (\Exception $e) {
            return $this->renderView(array(
                                         "success"    => false,
                                         "error"    => $e->getMessage(),
                                         "logOutput"  => "Error getting the list of nodes for the server " . $this->chefServer . " : " . $e->getMessage(),
                                         "logLevel"   => \Zend\Log\Logger::ERR,
                                         "parameters" => "[chefServer: " . $this->chefServer . "]",
                                         "trace"      => $e->getTraceAsString()
                                     ));
        }                         
                                 
    }

    /**
     * Gets a list of all nodes for all chef servers
     * usage: https://{$server}/chef/getNodesAllServers
     *
     * @return ViewModel json
     */
    public function getNodesAllServersAction() {
        $chefServers = $this->getServers();
        $nodes       = array();
        foreach ($chefServers as $chefServer) {
            if (preg_match("/Lab|Prod|NPAC/", $chefServer['env'])) {
                $this->chef = $this->defineChefServer($chefServer['name']);
                $result     = $this->chef->get('/nodes');
                foreach ($result AS $k => $v) {
                    $nodes[] = $k;
                }
            }
        }
        sort($nodes);

        if (is_array($nodes) AND !empty($nodes)) {
            return $this->renderview(array(
                                         "success" => 1,
                                         "nodes"   => $nodes
                                     ), "json");
        }

        return $this->renderview(array(
                                     "success" => 0,
                                     "error" => "Error getting node list"
                                 ), "json");

    }

    /**
     * Check all Chef servers for the existence of the node
     */
    public function checkNodeExistsAllServersAction() {
        $nodeName = $this->params()->fromRoute('param1');

        // get an array of Chef servers
        $servers = $this->getServers();

        // loop over each
        $data = array();
        foreach ($servers as $server) {
            $this->chefServer = $server['name'];
            // define the chef api handle
            $this->chef = $this->defineChefServer();

            // check for this node
            $node = null;
            try {
                $node = $this->chef->get('/nodes/' . $nodeName);
            } catch(\Exception $e) {
                $data[] = array(
                    "name" => $this->chefServer,
                    "fqdn" => $server['fqdn'],
                    "nodeExists" => "Connection error"
                );
                continue;
            }

            if (is_object($node) AND isset($node->name) AND $node->name == $nodeName) {
                $data[] = array(
                    "name" => $this->chefServer,
                    "fqdn" => $server['fqdn'],
                    "nodeExists" => true
                );
            } else {
                // check for this client
                $client = null;
                try {
                    $client = $this->chef->get('/clients/' . $nodeName);
                } catch(\Exception $e) {
                    $data[] = array(
                        "name" => $this->chefServer,
                        "fqdn" => $server['fqdn'],
                        "nodeExists" => "Connection error"
                    );
                    continue;
                }

                if (is_object($client) AND isset($client->name) AND $client->name == $nodeName) {
                    $data[] = array(
                        "name" => $this->chefServer,
                        "fqdn" => $server['fqdn'],
                        "nodeExists" => true
                    );
                } else {
                    $data[] = array(
                        "name" => $this->chefServer,
                        "fqdn" => $server['fqdn'],
                        "nodeExists" => false
                    );
                }
            }
        }
        return $this->renderView(array(
            "success" => true,
            "servers" => $data,
            "logLevel" => Logger::DEBUG,
            "logOutput" => count($data) . " chef servers checked for node " . $nodeName
        ));
    }

    /** Gets all the nodes and a subset of interesting properties
     * usage: https://{$server}/chef/getNodesAndDetails
     *
     * @return JsonModel
     */
    public function getNodesAndDetailsAction() {
        $nodes = $this->getNodesAndDetails();

        if (is_array($nodes) AND !empty($nodes)) {
            return $this->renderview(array(
                                         "success" => 1,
                                         "nodes"   => $nodes
                                     ), "json");
        }

        return $this->renderview(array(
                                     "success" => 0,
                                     "error" => "Error getting nodes."
                                 ), "json");

    }

    /* 
     * Gets all the nodes and a subset of interesting properties for all chef servers
     * usage: https://{$server}/chef/getNodesAndDetailsAllServers
     *
     * @return JsonModel
     */
    public function getNodesAndDetailsAllServersAction() {
        $chefServers = $this->getServers();
        $nodes       = array();
        foreach ($chefServers as $chefServer) {
            if (preg_match("/Lab|Prod|NPAC/", $chefServer['env'])) {
                $this->chef = $this->defineChefServer($chefServer['name']);
                $theseNodes = $this->getNodesAndDetails();
                $nodes      = array_merge($nodes, $theseNodes);
            }
        }
        sort($nodes);

        if (is_array($nodes) AND !empty($nodes)) {
            return $this->renderview(array(
                                         "success" => 1,
                                         "nodes"   => $nodes
                                     ), "json");
        }

        return $this->renderview(array(
                                     "success" => 0,
                                     "error" => "Error getting nodes"
                                 ), "json");

    }

    /**
     * Get a list of servers from the neumatic db servers table, either all or by username depending on adminOn flag
     * Process all the servers and get Chef details for each and return
     *
     * @return JsonModel
     */
    public function getNeumaticServersAction() {
        $adminOn = $this->params()->fromRoute('param1');


        $targetVersion = $this->_config['chef']['targetVersion'];
        $currentTime   = time();

        // get a list of servers; either all or those owner by username
        $serverTable = new Model\NMServerTable($this->_config);
        if ($adminOn == 'true') {
            $servers = $serverTable->getAll();
        } else {
            $servers = $serverTable->getByUsername($this->_user->getUsername());
        }
        // get environments from all chef servers
        $chefServers = $this->getServers();
        $hostEnvs    = array();
        foreach ($chefServers as $chefServer) {
            $this->chef = $this->defineChefServer($chefServer['name']);
            $envs       = $this->getEnvironments();
            foreach ($envs as $env) {
                $nodes = $this->getEnvironmentNodes($env['name']);
                foreach ($nodes as $node) {
                    $hostEnvs[$node] = $env['name'];
                }
            }
        }

        $serverPoolTable = new Model\NMServerPoolTable($this->_config);

        // get chef details for each node
        $nodes = array();
        $i     = -1;
        foreach ($servers as $server) {
            $server->setStatusText($this->calculateBuildTime($server));

            // convert server fqdn to hostname
            if (preg_match("/^([\w\d_-]+)\./", $server->getName(), $m)) {
                $hostname = $m[1];
            } else {
                $hostname = $server->getName();
            }

            // convert chef server fqdn to hostname
            if (preg_match("/^([\w\d_-]+)\./", $server->getChefServer(), $m)) {
                $chefServer = $m[1];
            } else {
                $chefServer = $server->getName();
            }

            // make nice the server type
            $type = ucfirst($server->getServerType());
            if ($type == 'Vmware') $type = 'VMWare';

            // convert server to object and then add the result of the properties to it
            $obj                      = $server->toObject();
            $obj->hostname            = $hostname;
            $obj->chefServer          = $chefServer;
            $obj->serverTypeDisplayed = $type;

            // check if this is a pool server. We need to let Angular know so that if a build is
            // executed, DNS will not be called
            $pServer = $serverPoolTable->getByServerId($server->getId());
            if ($pServer->getId()) {
                $obj->serverPoolId = $pServer->getId();
            } else {
                $obj->serverPoolId = false;
            }

            // define the chef server from the server config
            $this->chef = $this->defineChefServer($server->getChefServer());

            // get the chef details for this server (node)
            $results = $this->chef->postWithQueryParams(
                                  '/search/node',
                                  '?q=name:' . $server->getName(),
                                  '{"fqdn": ["fqdn"],
                "hostname": ["hostname"],
                "chefServerUrl": ["chef_client", "config", "chef_server_url"],
                "chefVersion": ["chef_packages", "chef", "version"],
                "ohaiTime": ["ohai_time"],
                "memory": ["memory","total"],
                "manufacturer": ["dmi", "system", "manufacturer"],
                "model": ["dmi", "system", "product_name"],
                "platform": ["platform"],
                "platformVersion": ["platform_version"]}',
                                  true);

            // if results were returned from the chef query, process the data
            if (property_exists($results, 'total') && $results->total == 1) {
                $i++;
                $data = $results->rows[0]->data;
                // get the actual Chef server and convert to hostname
                if ($data->chefServerUrl) {
                    if (preg_match("/^https:\/\/([\w\d_-]+)\./", $data->chefServerUrl, $m)) {
                        $obj->chefServer = $m[1];
                    } else {
                        $obj->chefServer = preg_replace("/https:\/\//", "", $data->chefServerUrl);
                    }
                }
                // assign the chef version
                $obj->chefVersion = $data->chefVersion;

                // assign the environment
                if (array_key_exists($data->fqdn, $hostEnvs)) {
                    $obj->chefEnvironment = $hostEnvs[$data->fqdn];
                } else {
                    $obj->chefEnvironment = "";
                }

                // check chef version against target
                if (version_compare($data->chefVersion, $targetVersion, '>=')) {
                    $obj->chefVersionStatus = "green";
                } else {
                    $obj->chefVersionStatus = "red";
                }

                // format ohai time
                $obj->ohaiTime       = $data->ohaiTime;
                $obj->ohaiTimeString = date('Y-m-d H:i:s', $data->ohaiTime);

                // calculate time since check in
                $checkIn = $this->calculateLastCheckInTime($currentTime, $data->ohaiTime);
                // difference in seconds
                $obj->ohaiTimeDiff = $checkIn->ohaiTimeDiff;
                // formatted difference in hours, mins, secs
                $obj->ohaiTimeDelta = $checkIn->ohaiTimeDelta;
                // color code
                $obj->ohaiTimeStatus = $checkIn->ohaiTimeStatus;
            }
            $nodes[] = $obj;
        }

        if (is_array($nodes)) {
            return $this->renderview(array(
                                         "success" => 1,
                                         "nodes"   => $nodes
                                     ), "json");
        }

        return $this->renderview(array(
                                     "success" => 0,
                                     "error" => "Error getting nodes."
                                 ), "json");

    }

    public function getNeumaticServersStatusAction() {

        $currentTime   = time();
        $targetVersion = $this->_config['chef']['targetVersion'];

        // get a list of servers; either all or those owner by username
        $serverTable = new Model\NMServerTable($this->_config);
        if ($this->_user->getUserType() == 'Admin') {
            $servers = $serverTable->getAll();
        } else {
            $servers = $serverTable->getByUsername($this->_user->getUsername());
        }

        // get chef details for each node
        $nodes = array();
        $i     = -1;
        foreach ($servers as $server) {
            // convert chef server fqdn to hostname
            if (preg_match("/^([\w\d_-]+)\./", $server->getChefServer(), $m)) {
                $chefServer = $m[1];
            } else {
                $chefServer = $server->getName();
            }

            // convert server to object and then add the result of the properties to it
            $obj = (object)array(
                'id'         => $server->getId(),
                'chefServer' => $chefServer
            );

            // define the chef server from the server config
            $this->chef = $this->defineChefServer($server->getChefServer());

            // get the chef details for this server (node)
            $results = $this->chef->postWithQueryParams(
                                  '/search/node',
                                  '?q=name:' . $server->getName(),
                                  '{"chefServerUrl": ["chef_client", "config", "chef_server_url"],
                "chefVersion": ["chef_packages", "chef", "version"],
                "ohaiTime": ["ohai_time"],
                "roles": ["roles"]}',
                                  true);

            // if results were returned from the chef query, process the data
            if (property_exists($results, 'total') && $results->total == 1) {
                $i++;
                $data = (object) array();
                try {
                    $data = $results->rows[0]->data;
                } catch (\ErrorException $e) {
                    error_log("Could not get to results->rows[0]->data. results=" . print_r($results, true));
                }
                // get the actual Chef server and convert to hostname
                if ($data->chefServerUrl) {
                    if (preg_match("/^https:\/\/([\w\d_-]+)\./", $data->chefServerUrl, $m)) {
                        $obj->chefServer = $m[1];
                    } else {
                        $obj->chefServer = preg_replace("/https:\/\//", "", $data->chefServerUrl);
                    }
                }
                // check chef version against target
                if (property_exists($data, 'chefVersion')) {
                    // assign the chef version
                    $obj->chefVersion = $data->chefVersion;

                    if (version_compare($data->chefVersion, $targetVersion, '>=')) {
                        $obj->chefVersionStatus = "green";
                    } else {
                        $obj->chefVersionStatus = "red";
                    }
                } else {
                    $obj->chefVersion       = '';
                    $obj->chefVersionStatus = 'green';
                }

                if (property_exists($data, 'ohaiTime')) {
                    // format ohai time
                    $obj->ohaiTimeString = date('Y-m-d H:i:s', $data->ohaiTime);

                    // calculate time since check in
                    $checkIn = $this->calculateLastCheckInTime($currentTime, $data->ohaiTime);
                    // difference in seconds
                    $obj->ohaiTimeDiff = $checkIn->ohaiTimeDiff;
                    // formatted difference in hours, mins, secs
                    $obj->ohaiTimeDelta = $checkIn->ohaiTimeDelta;
                    // color code
                    $obj->ohaiTimeStatus = $checkIn->ohaiTimeStatus;
                } else {
                    $obj->ohaiTime       = '';
                    $obj->ohaiTimeString = '';
                    $obj->ohaiTimeDiff   = '';
                    $obj->ohaiTimeDelta  = '';
                    $obj->ohaiTimeStatus = '';
                }

                if (property_exists($data, 'roles') && is_array($data->roles)) {
                    $obj->roles = implode(",", $data->roles);
                } else {
                    $obj->roles = "";
                }
            }
            $nodes[] = $obj;
        }

        if (is_array($nodes)) {
            return $this->renderview(array(
                                         "success" => 1,
                                         "nodes"   => $nodes
                                     ), "json");
        }

        return $this->renderview(array(
                                     "success" => 0,
                                     "error" => "Error getting nodes"
                                 ), "json");

    }

    /** private function of getNodesAndDetailsAction
     *
     * @return mixed
     */
    private function getNodesAndDetails() {
        $targetVersion = $this->_config['chef']['targetVersion'];
        $currentTime   = time();

        if (preg_match("/^([\w\d_-]+)\./", $this->chefConfig['server'], $m)) {
            $chefServer = $m[1];
        } else {
            $chefServer = $this->chefConfig['server'];
        }

        $hostEnvs = array();
        $envs     = $this->getEnvironments();
        foreach ($envs as $env) {
            $nodes = $this->getEnvironmentNodes($env['name']);
            foreach ($nodes as $node) {
                $hostEnvs[$node] = $env['name'];
            }
        }

        // get the properties of all the nodes
        $results = $this->chef->post('/search/node',
                                     '{"fqdn": ["fqdn"],
            "hostname": ["hostname"],
            "chefVersion": ["chef_packages", "chef", "version"],
            "ohaiTime": ["ohai_time"],
            "recipes": ["recipes"],
            "memory": ["memory","total"],
            "manufacturer": ["dmi", "system", "manufacturer"],
            "model": ["dmi", "system", "product_name"],
            "platform": ["platform"],
            "platformVersion": ["platform_version"]}',
                                     true);

        $nodes = array();
        if (property_exists($results, 'rows')) {
            for ($i = 0; $i < count($results->rows); $i++) {
                $data = $results->rows[$i]->data;
                // try to fix fqdn. Seen cases where it is blank
                $fqdn = "";
                if ($data->fqdn == "") {
                    if (preg_match("/^st/", $data->hostname)) {
                        $fqdn = $data->hostname . ".va.neustar.com";
                    } else if (preg_match("/^ch/", $data->hostname)) {
                        $fqdn = $data->hostname . ".nc.neustar.com";
                    }
                } else {
                    $fqdn = $data->fqdn;
                }
                $nodes[$i] = array(
                    "hostname"        => $data->hostname,
                    "fqdn"            => $fqdn,
                    "chefServer"      => $chefServer,
                    "chefServerFqdn"  => $this->chefConfig['server'],
                    "chefVersion"     => $data->chefVersion,
                    "ohaiTime"        => $data->ohaiTime,
                    "recipes"         => $data->recipes,
                    "memory"          => $data->memory,
                    "manufacturer"    => $data->manufacturer,
                    "model"           => $data->model,
                    "platform"        => $data->platform,
                    "platformVersion" => $data->platformVersion
                );

                // assign the environment
                if (array_key_exists($data->fqdn, $hostEnvs)) {
                    $nodes[$i]['chefEnvironment'] = $hostEnvs[$data->fqdn];
                } else {
                    $nodes[$i]['chefEnvironment'] = "";
                }
                // check chef version against target
                if (version_compare($data->chefVersion, $targetVersion, '>=')) {
                    $nodes[$i]['chefVersionStatus'] = "green";
                } else {
                    $nodes[$i]['chefVersionStatus'] = "red";
                }

                // format ohai time
                $nodes[$i]['ohaiTimeString'] = date('Y-m-d H:i:s', $data->ohaiTime);

                // calculate time since check in
                $checkIn                     = $this->calculateLastCheckInTime($currentTime, $data->ohaiTime);
                $nodes[$i]['ohaiTimeDiff']   = $checkIn->ohaiTimeDiff;
                $nodes[$i]['ohaiTimeDelta']  = $checkIn->ohaiTimeDelta;
                $nodes[$i]['ohaiTimeStatus'] = $checkIn->ohaiTimeStatus;
            }
        }
        return $nodes;
    }

    /**
     * Calculate the time difference of current time and last check in time (ohai time) and
     * return difference in seconds, formatted in hours, mins, secs and a color coding
     *
     * @param $currentTime
     * @param $ohaiTime
     * @return object
     */
    private function calculateLastCheckInTime($currentTime, $ohaiTime) {
        $obj = (object)array();

        $timeDiff          = $currentTime - $ohaiTime;
        $obj->ohaiTimeDiff = $timeDiff;
        if ($timeDiff <= 60 * 60) {
            // Ok - less than an hour
            $obj->ohaiTimeDelta  = sprintf("%2d min", floor($timeDiff / 60));
            $obj->ohaiTimeStatus = "green";
        } else if ($timeDiff <= 60 * 60 * 24) {
            // warning - less than a day
            $hours               = $timeDiff / 60 / 60;
            $mins                = ($hours - floor($hours)) * 60;
            $obj->ohaiTimeDelta  = sprintf("%d hours %2d min", floor($hours), floor($mins));
            $obj->ohaiTimeStatus = "goldenrod";
        } else {
            // error - less than a day
            $days                = $timeDiff / 60 / 60 / 24;
            $hours               = ($days - floor($days)) * 24;
            $mins                = ($hours - floor($hours)) * 60;
            $obj->ohaiTimeDelta  = sprintf("%d days %d hours %2d min", floor($days), floor($hours), floor($mins));
            $obj->ohaiTimeStatus = "red";
        }
        return $obj;
    }

    /**
     * Get a specific bit of Node information such as uptime or platform. For deeper data, use parameters to specify the array path.
     * Usage: https://{$server}/chef/getNodeData/{$nodeName}/{$param1}/{$param2}
     *
     * Examples: https://stopvprcw01.va.neustar.com/chef/getNodeData/stopvprcw01.va.neustar.com/kernel/release will return "2.6.32-279.el6.x86_64"
     * https://stopvprcw01.va.neustar.com/chef/getNodeData/stopvprcw01.va.neustar.com/platform returns "centos"
     */
    public function getNodeDataAction() {
        $nodeName = $this->params()->fromRoute('param1');
        $nodeData = $this->getNodeData($nodeName);

        if ($nodeData != "") {
            return $this->renderview(array(
                                         "success"  => 1,
                                         "nodeData" => $nodeData
                                     ), "json");
        }

        return $this->renderview(array(
                                     "success" => 0,
                                     "error" => "Error getting node " . $nodeName
                                 ), "json");

    }


    private function getNodeData($node) {
        $result = $this->chef->get('/nodes/' . $node);

        $params = $this->params()->fromRoute();

        $paramcount = 0;
        $levels     = array();

        foreach ($params AS $param_k => $param_v) {
            if ($param_k != "controller" AND $param_k != "action" AND $param_k != "param1") {
                $paramcount++;
                $levels[] = $param_v;
            }
        }

        if ($paramcount == 0) {
            return $result;
        }

        $nodeData = $this->getObjectValueRecurse($levels, $result->automatic);

        if (is_object($nodeData) OR is_array($nodeData)) {
            return $nodeData;
        } else {
            #echo $nodeData;
            return $nodeData;
        }
    }

    public function getNodeLastUpdatedAction() {
        $nodeName  = $this->params()->fromRoute('param1');
        $node      = $this->chef->get('/nodes/' . $nodeName);
        $timestamp = $node->automatic->ohai_time;

        if (isset($node->automatic->ohai_time) AND $node->automatic->ohai_time != "") {
            return $this->renderview(array(
                                         "success" => 1,
                                         "updated" => date('m-d-Y H:i:s', $timestamp)
                                     ), "json");
        }

        return $this->renderview(array(
                                     "success" => 0,
                                     "error" => "Error getting updated timestamp for node " . $nodeName
                                 ), "json");

    }


    /**
     * Delete the specified node from the Chef server.
     * Usage: https://{$server}/chef/deleteNode/{$nodeName}
     *
     * @return mixed | JsonModel
     */
    public function deleteNodeAction() {
        $nodeName = $this->params()->fromRoute('param1');

        if(!$this->checkNodeExists($nodeName)){
             return $this->renderview(array(
                                         "success"   => 1,
                                         "error"   => "Node " . $nodeName . " does not exist on the server, so no need to delete it.",
                                         "logOutput" => "User attempted to delete the node " . $nodeName . " but it does not exist so nothing to do.",
                                         "logLevel"  => 3
                                     ), "json");
        }
        if (!$this->checkAuthorizedNodeEdit($nodeName)) {
            return $this->renderview(array(
                                         "success"   => 0,
                                         "error"   => "User is not authorized to modify this node",
                                         "logOutput" => "User attempted to delete the node " . $nodeName . "but is not authorized",
                                         "logLevel"  => \Zend\Log\Logger::INFO
                                     ), "json");
        }

        $result = $this->chef->delete('/nodes', $nodeName);

        if (isset($result->error) AND stristr($result->error[0], "Not Found")) {
            return $this->renderview(array(
                                         "success"   => 1,
                                         "error"   => "Node " . $nodeName . " does not exist on the server, so no need to delete it.",
                                         "logOutput" => "User attempted to delete the node " . $nodeName . " but it does not exist so nothing to do.",
                                         "logLevel"  => 3
                                     ), "json");

        } elseif (isset($result->name)) {

            return $this->renderview(array(
                                         "success"   => 1,
                                         "error"   => "Node " . $nodeName . " deleted.",
                                         "logOutput" => "Node " . $nodeName . " deleted.",
                                         "logLevel"  => \Zend\Log\Logger::NOTICE
                                     ), "json");
        }

        return $this->renderview(array(
                                     "success"   => 0,
                                     "error"   => "Error deleting node " . $nodeName,
                                     "logOutput" => "There was an error attempting to delete the node " . $nodeName,
                                     "logLevel"  => \Zend\Log\Logger::ERR
                                 ), "json");

    }


    /**
     * Rename a node. There was no function in Chef to do this directly so what this does is create
     * a new node that is a copy of the old one with the new name and then delete the old one.
     *
     * Usage: https://{$server}/chef/renameNode/{$nodeName}/{$newNodeName}
     *
     */
    public function renameNodeAction() {
        $nodeName    = $this->params()->fromRoute('param1');
        $newNodeName = $this->params()->fromRoute('param2');

        if (!$this->checkAuthorizedNodeEdit($nodeName)) {
            return $this->renderview(array(
                                         "success"   => 0,
                                         "error"   => "User is not authorized to modify this node",
                                         "logOutput" => "User attempted to rename the node " . $nodeName . " but is not authorized",
                                         "logLevel"  => \Zend\Log\Logger::INFO
                                     ), "json");
        }

        // get it if it exists first
        $nodeObj = $this->chef->get('/nodes/' . $nodeName);

        if (isset($nodeObj->name)) {
            $nodeObj->name = $newNodeName;
        } else {
            return $this->renderview($nodeObj, "json");
        }

        //create a new node with this information
        $result = $this->chef->post('/nodes', $nodeObj);

        if (isset($result->uri)) {
            $result = $this->chef->delete('/nodes', $nodeName);
            if (isset($result->error) AND stristr($result->error[0], "Not Found")) {
                echo "1";
                exit();

            } elseif (isset($result->name)) {
                echo "1";
                exit();
            }
        }

        // if it returns a uri, it was successfully created. Now we delete the old one.
        if (isset($result->uri)) {
            $result = $this->chef->delete('/nodes', $nodeName);
            if (isset($result->error) AND stristr($result->error[0], "Not Found")) {
                return $this->renderview(array(
                                             "success" => 1,
                                             "error" => "Node renamed."
                                         ), "json");

            } elseif (isset($result->name)) {
                return $this->renderview(array(
                                             "success" => 1,
                                             "error" => "Node renamed."
                                         ), "json");
            }
        }
        return $this->renderview(array(
                                     "success" => 0,
                                     "error" => "Error renaming node."
                                 ), "json");

    }

    /**
     * Grabs the information from the source node and creates a new one with the new name but the same information as the source.
     * Usage: https://{$server}/chef/copyNode/{$sourceNodeName}/{$newNodeName}
     *
     */
    public function copyNodeAction() {
        $sourceNodeName = $this->params()->fromRoute('param1');
        $newNodeName    = $this->params()->fromRoute('param2');

        // get it if it exists first
        $nodeObj = $this->chef->get('/nodes/' . $sourceNodeName);

        if (isset($nodeObj->name)) {
            $nodeObj->name = $newNodeName;
        } else {
            //return the error
            return $this->renderview($nodeObj, "json");
        }

        //create a new node with this information
        $result = $this->chef->post('/nodes', $nodeObj);

        // if it returns a uri, it was successfully created. 
        if (isset($result->uri)) {
            return $this->renderview(array(
                                         "success"   => 1,
                                         "error"   => "Node successfully copied",
                                         "node"      => $result,
                                         "logOutput" => "Copied the node " . $sourceNodeName . " to create the new node " . $newNodeName,
                                         "logLevel"  => \Zend\Log\Logger::NOTICE
                                     ), "json");
        }

        return $this->renderview(array(
                                     "success"   => 0,
                                     "error"   => "Error copying node.",
                                     "logOutput" => "There was an error attempting to copy the node " . $sourceNodeName . " to create the new node " . $newNodeName,
                                     "logLevel"  => \Zend\Log\Logger::ERR
                                 ), "json");

    }

    /**
     * Gets all roles assigned to the node
     * Usage: https://{$server}/chef/getNodeRoles/{$nodeName}
     *
     */
    public function getNodeRolesAction() {
        $nodeName = $this->params()->fromRoute('param1');
        $nodeData = $this->chef->get('/nodes/' . $nodeName);
        if (isset($nodeData->error)) {
            return $this->renderview(array(
                                         "success" => 0,
                                         "error" => "Error getting Roles for node " . $nodeName
                                     ), "json");
        }

        $runList = $nodeData->run_list;
        $roles   = array();
        foreach ($runList AS $runItem) {
            if (stristr($runItem, "role[")) {
                $runItem = str_replace("role[", "", $runItem);
                $runItem = str_replace("]", "", $runItem);
                $roles[] = $runItem;
            }
        }


        if (isset($roles)) {
            return $this->renderview(array(
                                         "success" => 1,
                                         "roles"   => $roles
                                     ), "json");
        }

        return $this->renderview(array(
                                     "success" => 0,
                                     "error" => "Error getting Roles."
                                 ), "json");

    }

    /**
     * Gets all recipes assigned to the node
     * Usage: https://{$server}/chef/getNodeRecipes/{$nodeName}
     *
     */
    public function getNodeRecipesAction() {

        $nodeName = $this->params()->fromRoute('param1');
        $nodeData = $this->chef->get('/nodes/' . $nodeName);
        if (isset($nodeData->error)) {
            return $this->renderview(array("success" => 0,
                                           "error" => "Error getting recipes for node " . $nodeName
                                     ), "json");
        }

        $runList = $nodeData->run_list;
        $recipes = array();
        foreach ($runList AS $runItem) {
            if (stristr($runItem, "recipe[")) {
                $runItem   = str_replace("recipe[", "", $runItem);
                $runItem   = str_replace("]", "", $runItem);
                $recipes[] = $runItem;
            }
        }

        if (isset($recipes)) {
            return $this->renderview(array(
                                         "success" => 1,
                                         "recipes" => $recipes
                                     ), "json");
        }

        return $this->renderview(array(
                                     "success" => 0,
                                     "error" => "Error getting Recipes"
                                 ), "json");
    }

    /**
     * Adds the specified role to the specified node. Checks first to ensure both exist.
     * Usage: https://{$server}/chef/addRoleToNode/{$roleName}/{$nodeName}
     *
     */
    public function addRoleToNodeAction() {
        $roleName = $this->params()->fromRoute('param1');
        $nodeName = $this->params()->fromRoute('param2');

        // check if role exists
        $role = $this->chef->get('/roles/' . $roleName);

        if (isset($role->error) AND stristr($role->error[0], "Cannot load")) {
            /** @noinspection PhpUnusedLocalVariableInspection */
            $roleExists = false;
        } elseif (isset($role->name)) {
            /** @noinspection PhpUnusedLocalVariableInspection */
            $roleExists = true;
        } else {
            return $this->renderview($role, "json");
        }

        // check if node exists
        $node = $this->chef->get('/nodes/' . $nodeName);

        if (isset($node->error) AND stristr($node->error[0], "not found")) {
            /** @noinspection PhpUnusedLocalVariableInspection */
            $nodeExists = false;
        } elseif (isset($node->name)) {
            /** @noinspection PhpUnusedLocalVariableInspection */
            $nodeExists = true;
        } else {
            return $this->renderview($node, "json");
        }

        //check if role is already applied
        $nodeRunList = $node->run_list;

        /** @noinspection PhpUnusedLocalVariableInspection */
        $roleApplied = false;
        foreach ($nodeRunList AS $nr) {
            if (stristr($nr, "role[")) {
                $nr = str_replace('role[', "", $nr);
                $nr = str_replace(']', "", $nr);
            }

            if ($nr == $roleName) {
                /** @noinspection PhpUnusedLocalVariableInspection */
                $roleApplied = true;
                return $this->renderview(array(
                                             "success" => 1,
                                             "error" => "The role " . $roleName . " is already applied to the node " . $nodeName
                                         ), "json");
            }
        }

        // add role to node
        $nodeRoles[] = $roleName;

        //$node->automatic->roles = $nodeRoles;
        $node->run_list[] = "role[" . $roleName . "]";
        // apply
        $update = $this->chef->put('/nodes', $nodeName, $node);

        if (isset($update->name)) {
            return $this->renderview(array(
                                         "success" => 1,
                                         "node"    => $update
                                     ), "json");
        }
        return $this->renderview(array(
                                     "success" => 0,
                                     "error" => "Something went wrong while applying the role " . $roleName . " to the node " . $nodeName
                                 ), "json");


    }

    /*
     * Sets the environment for the specified node to the specified environment
     * Usage:  https://neumatic.ops.neustar.biz/chef/setNodeEnvironment/{$nodeName}/{$environmentName}?chef_server={$chefServer}
     * 
     */

    public function setNodeEnvironmentAction() {
        $nodeName        = $this->params()->fromRoute('param1');
        $environmentName = $this->params()->fromRoute('param2');

        try {
            if (!$this->checkAuthorizedNodeEdit($nodeName)) {
                return $this->renderview(array(
                                             "success"   => 0,
                                             "error"   => "User is not authorized to modify this node",
                                             "logOutput" => "User attempted to modify the environment of the node " . $nodeName . "but is not authorized",
                                             "logLevel"  => \Zend\Log\Logger::INFO
                                         ), "json");
            }
            $nodeData                   = $this->chef->get('/nodes/' . $nodeName);
            $nodeData->chef_environment = $environmentName;

            $nodeUpdate = $this->chef->put('/nodes', $nodeName, $nodeData);

            if (isset($nodeUpdate->chef_environment) AND $nodeUpdate->chef_environment == $environmentName) {
                return $this->renderView(array(
                                             "success"    => true,
                                             "logOutput"  => "Node Environment Updated Successfully",
                                             "node"       => $nodeUpdate,
                                             "parameters" => "[nodeName: " . $nodeName . ", environmentName: " . $environmentName . ", chefServer: " . $this->chefServer . "]",
                                             "logLevel"   => \Zend\Log\Logger::DEBUG
                                         ), "json");
            }
        } catch (\Exception $e) {
            return $this->renderView(array(
                                         "success"    => false,
                                         "logOutput"  => "There was an error attempting to set the environment of the node : " . $e->getMessage(),
                                         "logLevel"   => \Zend\Log\Logger::ERR,
                                         "parameters" => "[nodeName: " . $nodeName . ", environmentName: " . $environmentName . ", chefServer: " . $this->chefServer . "]",
                                         "trace"      => $e->getTraceAsString()));
        }

        return $this->renderView(array(
                                     "success"    => false,
                                     "logOutput"  => "Error updating the environment for the node. " . $nodeUpdate,
                                     "parameters" => "[nodeName: " . $nodeName . ", environmentName: " . $environmentName . ", chefServer: " . $this->chefServer . "]",
                                     "logLevel"   => \Zend\Log\Logger::ERR,
                                 ), "json");
    }
    
    private function setNodeEnvironment($nodeName, $environment){
         try {
            
            $nodeData                   = $this->chef->get('/nodes/' . $nodeName);
            $nodeData->chef_environment = $environment;

            $nodeUpdate = $this->chef->put('/nodes', $nodeName, $nodeData);
            if (isset($nodeUpdate->chef_environment) AND $nodeUpdate->chef_environment == $environment) {
                return true;
            }else{
  
                return $nodeUpdate;
            }
        } catch (\Exception $e) {
            return $e;
        }


    }
    
    /*
     * 
     * 
     */

    public function setNodeRunListAction() {
        $nodeName = $this->params()->fromRoute('param1');
        $nodeData = $this->chef->get('/nodes/' . $nodeName);
        unset($nodeData->automatic);
        $runList = $this->params()->fromPost('runList');

        if (empty($runList)) {
            $runList = array();
        }

        $nodeData->run_list = $runList;
        $nodeData = json_encode($nodeData);

        $nodeUpdate         = $this->chef->put('/nodes', $nodeName, $nodeData);

        if (isset($nodeUpdate->name) AND $nodeUpdate->name == $nodeName) {
            return $this->renderView(array(
                                         "success" => true,
                                         "error" => "Node Run List Updated Successfully",
                                         "node"    => $nodeUpdate
                                     ), "json");
        }

        return $this->renderView(array(
                                     "success" => false,
                                     "error" => "Error updating the Run List"
                                 ), "json");
    }






    private function setNodeRole($nodeName, $role){
    
        $nodeName = $this->params()->fromRoute('param1');
        $nodeData = $this->chef->get('/nodes/' . $nodeName);
        
        unset($nodeData->automatic);
        $runList = array();
        $runList[] = "role[$role]";
        
        if (empty($runList)) {
            $runList = array();
        }

        $nodeData->run_list = $runList;
        $nodeData = json_encode($nodeData);

        $nodeUpdate         = $this->chef->put('/nodes', $nodeName, $nodeData);

        if (isset($nodeUpdate->name) AND $nodeUpdate->name == $nodeName) {
            return true;
        }

        return $nodeUpdate;
    
}








    /********************************************* Roles *********************************************/

    /*
     * Checks if the current user is authorized to edit the given role.
     * Usage:  https://neumatic.ops.neustar.biz/chef/checkAuthorizedRoleEdit/{$roleName}?chef_server={$chefServer}
     * 
     */

    public function checkAuthorizedRoleEditAction() {
        $roleName = $this->params()->fromRoute('param1');
        try {
            $authorized = $this->checkAuthorizedRoleEdit($roleName);
        } catch (\Exception $e) {
            return $this->renderView(array(
                                         "success"    => false,
                                         "error"    => $e->getMessage(),
                                         "logOutput"  => "There was an error checking if authorized to modify the role : " . $e->getMessage(),
                                         "parameters" => "[roleName: " . $roleName . ", chefServer: " . $this->chefServer . "]",
                                         "logLevel"   => \Zend\Log\Logger::ERR,
                                         "trace"      => $e->getTraceAsString()
                                     ));
        }

        return $this->renderview(array(
                                     "success"    => 1,
                                     "authorized" => $authorized,
                                     "parameters" => "[roleName: " . $roleName . ", chefServer: " . $this->chefServer . "]",
                                     "logOutput"  => "Checked if user is authorized for the role " . $roleName,
                                     "logLevel"   => \Zend\Log\Logger::DEBUG,
                                     "cache"      => true,
                                     "cacheTTL"   => "300"
                                 ), "json");
    }

    private function checkAuthorizedRoleEdit($roleName) {

        if (isset($_COOKIE['userAdminOn'])) {
            if ($this->userType == 'Admin' AND $_COOKIE['userAdminOn'] == 'true') {
                return true;
            }
        } else {
            if ($this->userType == 'Admin') {
                return true;
            }
        }

        $default_attributes = $this->getRoleDefaultAttributes($roleName);

        if (isset($default_attributes->ownerGroup)) {
            if (!isset($this->userGroups)) {
                $this->userGroups = $this->getUserGroups();
            }

            foreach ($this->userGroups AS $ugId) {
                $ug     = $this->usergroupTable->getById($ugId);
                $ugName = $ug->get('name');
                if ($ugName == $default_attributes->ownerGroup) {
                    return true;
                }
            }

        }

        return false;
    }


    /**
     * Check if the specified role is defined on the server.
     * Usage: https://{$server}/chef/checkRoleExists/{$roleName}
     *
     */
    public function checkRoleExistsAction() {
        $roleName = $this->params()->fromRoute('param1');
        $result   = $this->chef->get('/roles/' . $roleName);

        if (isset($result->error) AND stristr($result->error[0], "Cannot load")) {
            echo "0";
            exit();
        } elseif (isset($result->name)) {
            echo "1";
            exit();
        }
        /*
        if (isset($result->name)) {
            return $this->renderView(array("success" => true, "result"=>"1", "error" => "Role". $roleName ."Exists"), "json");
        }
        return $this->renderView(array("success" => false, "error" => ""), "json");
        */
    }

    /**
     * Gets list of all roles defined on the server.
     * Usage: https://{$server}/chef/getRoles
     *
     */
    public function getRolesAction() {
        $result = $this->chef->get('/roles');

        $roles = array();
        foreach ($result AS $k => $v) {

            $roles[] = $k;

        }

        if (is_array($roles)) {
            natcasesort($roles);
            $roles = array_values($roles);
            return $this->renderView(array(
                                         "success" => true,
                                         "error" => "",
                                         "roles"   => $roles
                                     ), "json");
        }

        return $this->renderView(array(
                                     "success" => false,
                                     "error" => "Error getting roles"
                                 ), "json");

    }

    public function getRolesWithPermissionsAction() {
        $result = $this->chef->get('/roles');
        $authorizedEdit = false;
        $roles = array();
        foreach ($result AS $k => $v) {
            $role = array();

            $role['name'] = $k;

            $nodes = $this->search("node", "roles", $k);

            foreach ($nodes->rows AS $node) {
                $nodeName = $node->name;

                $authorizedEdit = $this->checkAuthorizedNodeEdit($nodeName);

                if ($authorizedEdit == false) {
                    $role['authorized'] = false;
                    $roles[]            = $role;
                    continue 2;
                }
            }

            if ($authorizedEdit == true) {
                $role['authorized'] = true;
            } else {
                $role['authorized'] = false;
            }

        }

        if (is_array($roles)) {
            natcasesort($roles);
            $roles = array_values($roles);
            return $this->renderView(array(
                                         "success" => true,
                                         "error" => "",
                                         "roles"   => $roles
                                     ), "json");
        }

        return $this->renderView(array(
                                     "success" => false,
                                     "error" => "Error getting roles"
                                 ), "json");

    }

    /**
     * Gets all roles defined on the server with details.
     * Usage: https://{$server}/chef/getRolesWithDetails
     *
     */
    public function getRolesWithDetailsAction() {
        $result = $this->chef->get('/roles');

        $roles = array();

        foreach ($result AS $k => $v) {
            $roleName = $k;

            $roleObj = $this->chef->get('/roles/' . $k);

            $authorizedEdit = $this->checkAuthorizedRoleEdit($roleName);
/*
            $nodes = $this->chef->postWithQueryParams(
                                '/search/node',
                                '?q=roles:' . $roleName,
                                '{"name": ["name"]}',
                                true);

            $roleObj->nodeCount = $nodes->total;
*/
            if ($authorizedEdit == true) {
                $roleObj->authorized = true;
            } else {
                $roleObj->authorized = false;
            }

            $roles[] = $roleObj;

            unset($roleObj);

        }

        if (is_array($roles)) {
            return $this->renderView(array(
                                         "success"  => true,
                                         "roles"    => $roles,
                                         "cache"    => true,
                                         "cacheTTL" => "300"
                                     ), "json");
        }

        return $this->renderView(array(
                                     "success" => false,
                                     "error" => "Error getting roles"
                                 ), "json");

    }

    /*
     * Gets the details of the specified Role
     * Usage: https://{$server}/chef/getRole/{$roleName}
     * 
     */
    public function getRoleAction() {
        $roleName = $this->params()->fromRoute('param1');
        $role     = $this->chef->get('/roles/' . $roleName);

/*
        $nodes    = $this->chef->postWithQueryParams(
                               '/search/node',
                               '?q=roles:' . $roleName,
                               '{"name": ["name"], "ohai_time": ["ohai_time"], "chef_packages": ["chef_packages"]}',
                               true);
        $nodeList = array();
        foreach ($nodes->rows AS $node) {
            $nodeData = $node->data;

            $nodeName                 = $nodeData->name;
            $nodeData->ohaiTimeString = date('Y-m-d H:i:s', $nodeData->ohai_time);

            // calculate time since check in
            $currentTime            = time();
            $timeDiff               = $currentTime - $nodeData->ohai_time;
            $nodeData->ohaiTimeDiff = $timeDiff;
            if ($timeDiff <= 60 * 60) {
                // Ok - less than an hour
                $nodeData->ohaiTimeDelta  = sprintf("%2d min", floor($timeDiff / 60));
                $nodeData->ohaiTimeStatus = "green";
            } else if ($timeDiff <= 60 * 60 * 24) {
                // warning - less than a day
                $hours                    = $timeDiff / 60 / 60;
                $mins                     = ($hours - floor($hours)) * 60;
                $nodeData->ohaiTimeDelta  = sprintf("%d hours %2d min", floor($hours), floor($mins));
                $nodeData->ohaiTimeStatus = "goldenrod";
            } else {
                // error - less than a day
                $days                     = $timeDiff / 60 / 60 / 24;
                $hours                    = ($days - floor($days)) * 24;
                $mins                     = ($hours - floor($hours)) * 60;
                $nodeData->ohaiTimeDelta  = sprintf("%d days %d hours %2d min", floor($days), floor($hours), floor($mins));
                $nodeData->ohaiTimeStatus = "red";
            }

            $nodeAuthorized       = $this->checkAuthorizedNodeEdit($nodeName);
            $nodeData->authorized = $nodeAuthorized;

            $nodeList[] = $nodeData;
        }


        $role->nodeList  = $nodeList;
        $role->nodeCount = count($nodeList);
*/
        $role->authorized = $this->checkAuthorizedRoleEdit($roleName);

        if ($role->authorized == false AND isset($role->default_attributes->ownerGroup)) {
            if (!isset($this->userGroups)) {
                $this->userGroups = $this->getUserGroups();
            }
            $ownerGroup = $role->default_attributes->ownerGroup;

            foreach ($this->userGroups AS $ugId) {

                $ug     = $this->usergroupTable->getById($ugId);
                $ugName = $ug->get('name');
                if ($ugName == $ownerGroup) {
                    $role->authorized = true;

                }
            }

        }


        if (isset($role->name) AND $role->name == $roleName) {
            return $this->renderView(array(
                                         "success" => true,
                                         "role"    => $role
                                     ), "json");
        }

        return $this->renderView(array(
                                     "success" => false,
                                     "error" => "Error getting role"
                                 ), "json");

    }


    public function saveRoleAction() {

        $roleName        = $this->params()->fromPost('roleName');
        $roleDescription = $this->params()->fromPost('roleDescription');
        $ownerGroup      = $this->params()->fromPost('ownerGroup');
        $newRole         = $this->params()->fromPost('newRole');

        $default_attributes = json_decode(json_encode($this->params()->fromPost('default_attributes'), JSON_NUMERIC_CHECK), JSON_NUMERIC_CHECK);

        $override_attributes = json_decode(json_encode($this->params()->fromPost('override_attributes'), JSON_NUMERIC_CHECK), JSON_NUMERIC_CHECK);


        if (empty($default_attributes)) {
            $default_attributes = new \stdClass();
        }

        if (empty($override_attributes)) {
            $override_attributes = new \stdClass();
        }


        $roleObj              = new \stdClass();
        $roleObj->name        = $roleName;
        $roleObj->description = $roleDescription;


        if (empty($default_attributes)) {
            $roleObj->default_attributes = new \stdClass();
        } else {
            $roleObj->default_attributes = $default_attributes;
        }
        if (empty($override_attributes)) {
            $roleObj->override_attributes = new \stdClass();
        } else {
            $roleObj->override_attributes = $override_attributes;
        }

        $roleObj->json_class = "Chef::Role";
        $roleObj->chef_type  = "role";

        $runList = $this->params()->fromPost('run_list');
        if (empty($runList)) {
            $runList = array();
        }
        $roleObj->run_list = $runList;

        $roleObj->default_attributes->ownerGroup = $ownerGroup;

        if ($newRole == 'true') {
            $result = $this->chef->post('/roles', $roleObj);
        } else {
            $result = $this->chef->put('/roles', $roleName, $roleObj);
        }

        if (is_object($result) AND isset($result->name)) {

            $this->clearCache("getRolesWithDetails");

            return $this->renderview(array(
                                         "success" => 1,
                                         "role"    => $result
                                     ), "json");
        }

        return $this->renderview(array(
                                     "success" => 0,
                                     "error" => "Error saving role."
                                 ), "json");
    }


    /**
     * Gets the default attributes for the specified Role
     * usage: https://{$server}/chef/getRoleDefaultAttributes/{$roleName}
     *
     */
    public function getRoleDefaultAttributesAction() {
        $roleName = $this->params()->fromRoute('param1');

        try {
            if ($default_attributes = $this->getRoleDefaultAttributes($roleName)) {

                return $this->renderview(array(
                                             "success"            => 1,
                                             "default_attributes" => $default_attributes,
                                             "logOutput"          => "Successfully got default_attributes for role " . $roleName,
                                             "parameters"         => "[roleName: " . $roleName . ", chefServer: " . $this->chefServer . "]",
                                             "logLevel"           => \Zend\Log\Logger::DEBUG
                                         ), "json");
            }
            return $this->renderview(array(
                                         "success"    => 0,
                                         "error"    => "Error getting default attributes.",
                                         "logOutput"  => "There was an error getting default_attributes for role " . $roleName,
                                         "parameters" => "[roleName: " . $roleName . ", chefServer: " . $this->chefServer . "]",
                                         "logLevel"   => \Zend\Log\Logger::DEBUG
                                     ), "json");
        } catch (\Exception $e) {
            return $this->renderView(array(
                                         "success"    => false,
                                         "logOutput"  => "There was an error getting default_attributes for role " . $roleName . " : " . $e->getMessage(),
                                         "parameters" => "[roleName: " . $roleName . ", chefServer: " . $this->chefServer . "]",
                                         "logLevel"   => \Zend\Log\Logger::ERR,
                                         "trace"      => $e->getTraceAsString()
                                     ));
        }
    }

    private function getRoleDefaultAttributes($roleName) {

        $role               = $this->chef->get('/roles/' . $roleName);
        $default_attributes = $role->default_attributes;
        return $default_attributes;
    }

    /**
     * Gets the override attributes for the specified Role
     * usage: https://{$server}/chef/getRoleOverrideAttributes/{$roleName}
     *
     */
    public function getRoleOverrideAttributesAction() {
        $roleName = $this->params()->fromRoute('param1');
        $role     = $this->chef->get('/roles/' . $roleName);

        if (is_object($role)) {
            return $this->renderview(array(
                                         "success"             => 1,
                                         "override_attributes" => $role->override_attributes
                                     ), "json");
        }
        return $this->renderview(array(
                                     "success" => 0,
                                     "error" => "Error getting default attributes."
                                 ), "json");
    }

    /**
     * Adds the specified cookbook to the specified role
     * usage: https://{$server}/chef/addCookbookToRole/{$cookbookName}/{$roleName}
     *
     */
    public function addCookbookToRoleAction() {
        /** @noinspection PhpUnusedLocalVariableInspection */
        $cookbookName = $this->params()->fromRoute('param1');
        /** @noinspection PhpUnusedLocalVariableInspection */
        $roleName     = $this->params()->fromRoute('param2');

    }

    public function deleteRoleAction() {

        $roleName = $this->params()->fromRoute('param1');
        $result   = $this->chef->delete('/roles', $roleName);

        if (is_object($result) AND isset($result->name)) {
            $this->clearCache("getRolesWithDetails");
            return $this->renderview(array(
                                         "success" => 1,
                                         "role"    => $result
                                     ), "json");
        }
        if (isset($result->error)) {

            $error = $result->error;
            if (is_array($error)) {
                $error = $error[0];
            }
        } else {
            $error = $result;
        }

        return $this->renderview(array(
                                     "success"   => 0,
                                     "logOutput" => "There was an error while attempting to delete the role " . $roleName . " : " . $error
                                 ), "json");
    }


    /********************************************* Sandboxes *********************************************/

    /********************************************* Search *********************************************/

    /**
     *
     *
     */
    public function searchNodeAction() {
        $searchKey = $this->params()->fromRoute('param1');
        $searchVal = $this->params()->fromRoute('param2');
        $result    = $this->search("node", $searchKey, $searchVal);
        $count     = $result->total;

        return $this->renderview(array(
                                     "success" => 1,
                                     "count"   => $count,
                                     "nodes"   => $result->rows
                                 ), "json");
    }

    /**
     *
     *
     */
    public function searchRoleAction() {
        $searchKey = $this->params()->fromRoute('param1');
        $searchVal = $this->params()->fromRoute('param2');
        $result    = $this->search("role", $searchKey, $searchVal);

        return $this->renderview(array(
                                     "success" => 1,
                                     "roles"   => $result->rows
                                 ), "json");
    }

    /**
     *
     *
     */
    public function searchClientAction() {
        $searchKey = $this->params()->fromRoute('param1');
        $searchVal = $this->params()->fromRoute('param2');
        $result    = $this->search("client", $searchKey, $searchVal);

        return $this->renderview(array(
                                     "success" => 1,
                                     "clients" => $result->rows
                                 ), "json");
    }

    /**
     *
     *
     */
    public function searchUsersAction() {
        $searchKey = $this->params()->fromRoute('param1');
        $searchVal = $this->params()->fromRoute('param2');
        $result    = $this->search("users", $searchKey, $searchVal);

        return $this->renderview(array(
                                     "success" => 1,
                                     "users"   => $result->rows
                                 ), "json");
    }

    /**
     *
     *
     */
    private function search($index, $searchKey, $searchVal) {

        $searchParams['q'] = $searchKey . ":*" . $searchVal . "*";

        $result = $this->chef->get('/search/' . $index, $searchParams);

        return $result;
    }


    /********************************************* User *********************************************/

    /**
     * Gets a list of all users on the server
     * usage: https://{$server}/chef/getUsers
     *
     */
    public function getUsersAction() {
        $result = $this->chef->get('/users');

        if (is_object($result)) {
            return $this->renderview(array(
                                         "success" => 1,
                                         "users"   => $result
                                     ), "json");
        }
        return $this->renderview(array(
                                     "success" => 0,
                                     "error" => $result
                                 ), "json");
    }

    /**
     * Gets the details of the specified user
     * Usage: https://{$server}/chef/getUser/{$userName}
     *
     */
    public function getUserAction() {
        $userName = $this->params()->fromRoute('param1');
        try {
            $result = $this->chef->get('/users/' . $userName);

        } catch (\Exception $e) {
            return $this->renderView(array("success" => false, "error" => $e->getMessage(), "trace" => $e->getTraceAsString()));
        }

        $httpCode = $this->chef->getHttpCode();
        if ($httpCode == 404) {
            return $this->renderView(array("success" => false, "error" => "User not found"));
        } else if (!preg_match("/^2/", $httpCode)) {
            if (is_array($this->httpCodes)) {
                return $this->renderView(array("success" => false,
                                               "error"   => "Chef server returned HTTP code {$httpCode}: " . $this->httpCodes[$httpCode]));
            }
            return $this->renderView(array("success" => false,
                                           "error"   => "Chef server returned HTTP code {$httpCode}: "));
        }
        if (is_object($result) AND $result->name == $userName) {
            return $this->renderView(array(
                                         "success"   => true,
                                         "logLevel"  => Logger::DEBUG,
                                         "logOutput" => "User {$userName} exists on {$this->chefServer}",
                                         "user"      => $result
                                     ));
        }
        return $this->renderView(array("success" => false, "error" => "Unspecified error getting user"));
    }

    public function createUserAction() {
        $userName = $this->params()->fromRoute('param1');
        /** @noinspection PhpUnusedLocalVariableInspection */
        $admin    = $this->params()->fromRoute('param2') ? $this->params()->fromRoute('param2') : false;

        try {
            $passwordChars = "abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789";
            $password      = substr(str_shuffle($passwordChars), 0, 10);

            $result = $this->chef->post('/users',
                                        array(
                                            "name"     => $userName,
                                            "password" => $password,
                                            "admin"    => true
                                        )
            );
        } catch (\Exception $e) {
            return $this->renderView(array("success" => false, "error" => $e->getMessage(), "trace" => $e->getTraceAsString()));
        }

        $httpCode = $this->chef->getHttpCode();
        if (!preg_match("/^2/", $httpCode)) {
            return $this->renderView(array("success" => false,
                                           "error" => "Chef server returned HTTP code {$httpCode}: " . $this->httpCodes[$httpCode]));
        }

        if (property_exists($result, 'private_key')) {
            return $this->renderView(array("success" => true, "privateKey" => $result->private_key));
        } else {
            return $this->renderView(array("false" => false, "error" => "Private key not returned"));
        }
    }

    public function deleteUserAction() {
        $userName = $this->params()->fromRoute('param1');

        try {
            $this->chef->delete('/users', $userName);
        } catch (\Exception $e) {
            return $this->renderView(array("success" => false, "error" => $e->getMessage(), "trace" => $e->getTraceAsString()));
        }

        $httpCode = $this->chef->getHttpCode();
        if (!preg_match("/^2/", $httpCode)) {
            return $this->renderView(array("success" => false,
                                           "error" => "Chef server returned HTTP code {$httpCode}: " . $this->httpCodes[$httpCode]));
        }
        return $this->renderView(array("success" => true));
    }

    /**
     * Follows an object down several layers (specified by the array) to get a certain value
     *
     */
    private function getObjectValueRecurse($array, $object) {
        $val = array_shift($array);

        if (is_string($object->$val) OR is_int($object->$val)) {
            $value = $object->$val;

            return $value;
        }
        if (isset($object->$val)) {
            if (is_object($object->$val)) {

                return $this->getObjectValueRecurse($array, $object->$val);
            } else {
                return null;

            }
        }
        return null;
    }

    /***************************************************Reports**********************************************************/
    public function nodeAndEnvironmentReportAction() {

        $result = $this->chef->get('/nodes');
        $nodes  = array();
        foreach ($result AS $k => $v) {
            $nodes[] = $k;
        }
        sort($nodes);

        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename=node_report.csv');

        // create a file pointer connected to the output stream
        $output = fopen('php://output', 'w');
        fputcsv($output, array('Name', 'Environment', 'Client Version', 'Last Run'));
        foreach ($nodes AS $nodeName) {

            $node  = $this->chef->get('/nodes/' . $nodeName);
            $out   = array();
            $out[] = $nodeName;
            $out[] = $node->chef_environment;
            $out[] = $node->automatic->chef_packages->chef->version;
            $out[] = date('Y-m-d H:i:s', $node->automatic->ohai_time);

            fputcsv($output, $out);

        }
        exit();
    }

    /**
     * Runs Knife and uploads the version of the cookbook currently on the system.
     * The cookbook must already exist at /var/chef/cookbooks.
     *
     */
    public function uploadCookbookAction() {
        $cookbook = $this->params()->fromRoute('param1');
        $command  = "sh /var/www/html//bin/uploadCookbook.sh " . $cookbook;
        $output   = shell_exec($command);
        return $this->renderview($output, "json");
    }

    /**
     * Forces the chef client to run on the specified node by using the "knife ssh" command
     * Usage: https://{$server}/chef/runChefClient/{$nodeName}
     *
     */
    public function runChefClientAction() {
        $name    = $this->params()->fromRoute('param1');
        $command = "sh /var/www/html/neumatic/bin/runChefClient.sh " . $name . " " . $this->chefServer;

        exec($command, $result);

        $last = end($result);

        if (stristr($last, "Chef Client finished")) {
            $output = array("result" => "1", "details" => $result);
        } else {
            //
            $output = array("result" => "0", "details" => $result);
        }

        return $this->renderview($output, "json");
    }

    /**
     * Given a server with a start and end build times, return the time in min:secs
     *
     * @param Model\NMServer $server
     * @return mixed|string
     */
    public function calculateBuildTime(Model\NMServer $server) {
        if ($server->getStatus() == 'Built' && $server->getTimeBuildStart() && $server->getTimeBuildEnd()) {
            $diff = strtotime($server->getTimeBuildEnd()) - strtotime($server->getTimeBuildStart());
            return $server->getStatusText() . sprintf(" (%02d:%02d)", floor($diff / 60), $diff % 60);
        } else if ($server->getStatus() == 'Building' && $server->getTimeBuildStart()) {
            $timezone = 'America/New_York';

            $startStr  = $server->getTimeBuildStart();
            $startTime = strtotime($startStr);

            // get the time now, then convert to str and back to Epoch time in the current timezone
            $nowTime    = time();
            $nowStr     = date("Y-m-d H:i:s", $nowTime);
            $nowTimeNew = strtotime($nowStr . " " . $timezone);

            $diff = $nowTimeNew - $startTime;
            return sprintf("%02d:%02d", floor($diff / 60), $diff % 60) . "  " . $server->getStatusText();
        } else {
            return $server->getStatusText();
        }
    }

    private function object_to_array($obj) {

        $arr    = array();
        $arrObj = is_object($obj) ? get_object_vars($obj) : $obj;
        foreach ($arrObj as $key => $val) {
            $val       = (is_array($val) || is_object($val)) ? $this->object_to_array($val) : $val;
            $arr[$key] = $val;
        }
        return $arr;
    }

    public function clearCacheAction() {

        try {
            $this->clearCache('all');
        } catch (\Exception $e) {
            return $this->renderView(array(
                                         "success"   => false,
                                         "error"   => $e->getMessage(),
                                         "logOutput" => "There was an error attempting to clear the user's cache : " . $e->getMessage(),
                                         "logLevel"  => \Zend\Log\Logger::ERR,
                                         "trace"     => $e->getTraceAsString()));
        }

        return $this->renderView(array(
                                     "success"   => true,
                                     "logOutput" => "Cleared the cache for the user",
                                     "logLevel"  => \Zend\Log\Logger::DEBUG
                                 ));
    }
}

/* this is a test */
