<?php


namespace Neumatic\Controller\Base;


use Zend\Log\Logger;
use Zend\Mvc\Controller\AbstractActionController;
use Zend\Mvc\MvcEvent;
use Zend\View\Model\JsonModel;
use Zend\View\Model\ViewModel;

use Vmwarephp\Vhost;

use Neumatic\Model;

class BaseController extends AbstractActionController
{

    /** @var \Neumatic\Model\NMUser $_user */
    protected $_user;

    /** @var Array $_config */
    protected $_config;

    /** @var  string $_env */
    protected $_env;

    /** @var $_log Logger */
    protected $_log;

    /** @var Array $_route */
    protected $_route;

    /* @var JsonModel $_jsonModel */
    protected $_jsonModel;

    /* @var string $_version */
    protected $_version;

    /* @var int $_timeStart */
    protected $_timeStart;

    protected $_logLevelStrings = array("EMERG", "ALERT", "CRIT", "ERR", "WARN", "NOTICE", "INFO", "DEBUG");

    /** @var Model\NMAuditTable $_auditTable */
    private $_auditTable;

    /**
     * Returned from private method calls. When a return status is non-zero, return this
     * array with $this->renderview().
     * @var array $_viewData
     */
    protected $_viewData;

    // cache properties
    protected $cachePathBase;
    protected $cachePath;
    protected $chefServer;
    protected $defaultCacheLifetime;

    // vSphere Properties
    protected $vSphereConfig;
    protected $vSphereSite;
    protected $vSphereServer;
    protected $vSpherePort;
    protected $vSphereUsername;
    protected $vSpherePassword;

    /**
     *
     */
    protected function attachDefaultListeners() {

        parent::attachDefaultListeners();
        $this->events = $this->getEventManager();
        $this->events->attach('dispatch', array($this, 'preDispatch'), 100);
        $this->events->attach('dispatch', array($this, 'postDispatch'), -100);

    } // attachDefaultListeners()

    /**
     * @param MvcEvent $e
     * @return \Zend\View\Model\JsonModel|\Zend\View\Model\ViewModel
     */
    public function preDispatch(MvcEvent $e) {

        // capture our start time
        $this->_timeStart = microtime(true);

        // get our config
        $this->_config = $this->getServiceLocator()->get('Config');

        // get our logger from the factory
        /** @var \Zend\Log\Logger _log */
        $this->_log = $this->getServiceLocator()->get('Zend\Log');

        // get the version
        $this->_version = $this->getServiceLocator()->get('Version');

        // filter as specified in the config file
        $filter = new \Zend\Log\Filter\Priority($this->_config['logLevel']);

        $writers      = $this->_log->getWriters();
        $writersArray = $writers->toArray();
        for ($i = 0; $i < count($writersArray); $i++) {
            $writersArray[$i]->addFilter($filter);
        }

        // capture our current user
        $uid = null;
        if (array_key_exists('testUsername', $this->_config) && $this->_config['testUsername']) {
            $uid = $this->_config['testUsername'];
        } else if (array_key_exists('PHP_AUTH_USER', $_SERVER)) {
            $uid = $_SERVER['PHP_AUTH_USER'];
        } else {
            $uid = null;
        }
        $pwd = null;
        if (array_key_exists('testPassword', $this->_config) && $this->_config['testPassword']) {
            $pwd = $this->_config['testPassword'];
        } else if (array_key_exists('PHP_AUTH_PW', $_SERVER)) {
            $pwd = $_SERVER['PHP_AUTH_PW'];
        } else {
            $pwd = null;
        }

        if (!$uid) {
            return $this->renderView(array(
                                         "success"   => false,
                                         "error"     => "User not authenticated or not found",
                                         "logLevel"  => Logger::ERR,
                                         "logOutput" => "User not authenticated or not found"
                                     ));
        }


        $uid         = strtolower($uid);
        $userTable   = new Model\NMUserTable($this->_config);
        //$this->uid   = $uid;
        $this->_user = $userTable->getByUserName($uid);
        if (!$this->_user->getId()) {
            $this->_user->setUsername($uid);
            $this->_user = $userTable->create($this->_user);
        }
        $this->_user->set('password', $pwd);

        // switching from teams to ldap user groups
        // $neumatic = new Model\Neumatic($this->_config);
        // $teams = $neumatic->getUserTeams($this->_user->getId());
        $ldapUserGroups = $this->getLdapUserGroups($this->_user->getUsername());
        $this->_user->set('ldapUserGroups', $ldapUserGroups);


        // retrieve our application environment
        $this->_env = getenv('APPLICATION_ENV');

        // retrieve our route
        $this->_route = $this->params()->fromRoute();

        // instantiate our audit table so that we can use its functions
        $this->_auditTable = new Model\NMAuditTable($this->_config);
    } // preDispatch()

    /**
     * @param MvcEvent $e
     */
    public function postDispatch(MvcEvent $e) {
    } // postDispatch()


    protected function arrayOfModelsToObjects($models) {
        $arrayOfObjects = array();
        foreach ($models as $model) {
            $arrayOfObjects[] = $model->toObject();
        }
        return $arrayOfObjects;
    }

    /**
     * @param $uid string
     * @return array
     */
    protected function getLdapUserGroups($uid) {
        if ($cachedUserGroups = $this->checkCache("getLdapUserGroups", true)) {
            return unserialize($cachedUserGroups);
        }

        if ($this->_user->getUserType("Admin")) {
            try {
                // if user is an admin, get a list of all user groups
                $url = "https://" . $this->_config['glass']['server'] . "/larp/user/group/list";

                $response = $this->curlGetUrl($url);
                $groups = $response->output->records;
            } catch (\ErrorException $e) {
                return array();
            }
        } else {
            try {
                // get a list of the users user groups
                $url = "https://" . $this->_config['glass']['server'] . "/larp/user/groups/" . $uid;

                $response = $this->curlGetUrl($url);
                $groups = $response->output->records;

            } catch (\ErrorException $e) {
                return array();
            }
        }

        natcasesort($groups);
        // sort retains the index of array elements so need to reassign
        $sorted = array();
        foreach ($groups as $g) {
            $sorted[] = $g;
        }
        $this->writeCache(serialize($sorted), "getLdapUserGroups", 600);
        return $sorted;
    }

    /**
     * @param $hostName
     * @return array
     */
    protected function getLdapHostHostGroups($hostName) {
        if ($cachedHostHostGroups = $this->checkCache("getLdapHostHostGroups", true)) {
            return unserialize($cachedHostHostGroups);
        }    
        try {
            $url = "https://" . $this->_config['glass']['server'] . "/larp/host/host/groups/" . $hostName;

            $response = $this->curlGetUrl($url);
            $groups = $response->output->records;
        }
        catch (\ErrorException $e) {
            return array();
        }

        natcasesort($groups);
        // sort retains the index of array elements so need to reassign
        $sorted = array();
        foreach ($groups as $g) {
            $sorted[] = $g;
        }
        $this->writeCache(serialize($sorted), "getLdapHostHostGroups", 600);
        return $sorted;
    }

    /**
     * @param $hostName
     * @return array|bool
     */
    protected function getLdapHostUsergroups($hostName) {
        if ($cachedHostUsergroups = $this->checkCache("getLdapHostUsergroups", true)) {
            return unserialize($cachedHostUsergroups);
        }
        try {
            $url = "https://" . $this->_config['glass']['server'] . "/larp/host/user/groups/" . $hostName;

            $response = $this->curlGetUrl($url);
            $groups = $response->output->records;
        }
        catch (\ErrorException $e) {
            return array();
        }

        natcasesort($groups);
        // sort retains the index of array elements so need to reassign
        $sorted = array();
        foreach ($groups as $g) {
            $sorted[] = $g;
        }
        $this->writeCache(serialize($sorted), "getLdapHostUsergroups", 600);
        return $sorted;
    }

    /**
     * @param $serverId int
     * @return Model\NMUsergroup[]
     */
    protected function getUsergroupsByServerId($serverId) {
        $ugTable = new Model\NMUsergroupTable($this->_config);
        return $ugTable->getByServerId($serverId);
    }

    /**
     * @return array
     */
    protected function getLdapHostGroups() {
        if ($cachedHostGroups = $this->checkCache("getLdapHostGroups", true)) {
            return unserialize($cachedHostGroups);
        }    
        try {
            $url = "https://" . $this->_config['glass']['server'] . "/larp/host/group/list";

            $response = $this->curlGetUrl($url);
            $groups = $response->output->records;
        }
        catch (\ErrorException $e) {
            return array();
        }

        natcasesort($groups);
        // sort retains the index of array elements so need to reassign
        $sorted = array();
        foreach ($groups as $g) {
            $sorted[] = $g;
        }
        $this->writeCache(serialize($sorted), "getLdapHostGroups", 3600);
        return $sorted;
    }

    /**
     * @param $serverId int
     * @return Model\NMHostgroup[]
     */
    protected function getHostgroupsByServerId($serverId) {
        $hgTable = new Model\NMHostgroupTable($this->_config);
        return $hgTable->getByServerId($serverId);
    }

    /**
     * @param $vSpherSite
     */
    protected function defineVSphereServer($vSpherSite) {
        $this->vSphereSite = $vSpherSite;
        if (isset($this->vSphereSite) && $this->vSphereSite != "") {
            $this->vSphereConfig = $this->_config['vSphere'][$this->vSphereSite];
        } else {
            $this->vSphereSite   = $this->_config['vSphere']['site'];
            $this->vSphereConfig = $this->_config['vSphere'][$this->vSphereSite];
        }

        $this->vSphereServer   = $this->vSphereConfig['server'];
        $this->vSpherePort     = $this->vSphereConfig['port'];
        $this->vSphereUsername = $this->vSphereConfig['username'];
        $this->vSpherePassword = $this->vSphereConfig['password'];
    }

    private static function sortByMemAvailable() {
        return function($a, $b) {
            $aON = $a['powerState'] == 'poweredOn' && $a['connectionState'] == 'connected' && !$a['inMaintenanceMode'] ? true : false;
            $bON = $b['powerState'] == 'poweredOn' && $b['connectionState'] == 'connected' && !$b['inMaintenanceMode'] ? true : false;

            if ($aON && $bON) {
                if ($a['memoryAvailableGB'] == $b['memoryAvailableGB']) {
                    return 0;
                }
                return $a['memoryAvailableGB'] > $b['memoryAvailableGB'] ? -1 : 1;
            } else if ($aON && !$bON) {
                return -1;
            } else if (!$aON && $bON) {
                return 1;
            } else {
                return 0;
            }
        };
    }

    /**
     * Given the vserver and a host system uid, this method gets the runtime information that includes
     * the connectionState and powerState. This is needed by createVM to specify the first host system
     * that is online and available.
     *
     * @param Vhost $vServer
     * @param $hsUid
     * @return \Vmwarephp\Extensions\HostSystem
     */
    protected function getVMwareHostSystem(Vhost $vServer, $hsUid) {
        /** @var \Vmwarephp\Extensions\HostSystem $system */
        $system = $vServer->findOneManagedObject('HostSystem', $hsUid, array('name', 'summary'));

        $summary = $system->summary;

        $config = $summary->config;
        $hw = $summary->hardware;
        $quickStats = $summary->quickStats;
        $runTime = $summary->runtime;

        $memorySizeGB = round($hw->memorySize / 1024 / 1024 / 1024);
        $overallMemoryUsageGB = round($quickStats->overallMemoryUsage / 1024);
        $memoryAvailableGB = $memorySizeGB - $overallMemoryUsageGB;

        return array(
            'name' => $config->name,
            'uid' => $system->getReferenceId(),
            'version' => $config->product->version,
            'model' => $hw->model,

            'powerState' => $runTime->powerState,
            'connectionState' => $runTime->connectionState,
            'inMaintenanceMode' => $runTime->inMaintenanceMode,

            'cpuMhz' => $hw->cpuMhz,
            'numCpuPkgs' => $hw->numCpuPkgs,
            'numCpuCores' => $hw->numCpuCores,
            'overallCpuUsage' => $quickStats->overallCpuUsage,
            'cpuUsagePercent' => round($quickStats->overallCpuUsage / ($hw->numCpuCores * $hw->cpuMhz) * 100, 2),
            'distributedCpuFairness' => $quickStats->distributedCpuFairness,

            'memorySizeGB' => $memorySizeGB,
            'overallMemoryUsageGB' => $overallMemoryUsageGB,
            'memoryUsagePercent' => round($quickStats->overallMemoryUsage / ($hw->memorySize / (1024 * 1024)) * 100, 2),
            'memoryAvailableGB' => $memoryAvailableGB,
            'distributedMemoryFairness' => $quickStats->distributedMemoryFairness,

            'overallStatus' => $summary->overallStatus,
            //'summary' => $summary
        );
    }

    /**
     * @param $ccrUid
     * @return mixed
     */
    protected function getVMwareHostSystemsByClusterComputeResource($ccrUid) {
        $vServer = new Vhost($this->vSphereServer . ':' . $this->vSpherePort, $this->vSphereUsername, $this->vSpherePassword);

        try {
            /** @var \Vmwarephp\Extensions\ClusterComputeResource $ccr */
            $ccr = $vServer->findOneManagedObject('ClusterComputeResource', $ccrUid, array('name', 'host'));
        } catch (\Exception $e) {
            $this->_viewData = array(
                "success"   => false,
                "error"     => $e->getMessage(),
                "trace"     => $e->getTraceAsString(),
                "logLevel"  => Logger::ERR,
                "logOutput" => "Could not get Datacenter\n" . $e->getTraceAsString()
            );
            return false;
        }

        $hostSystems = array();
        $hosts  = $ccr->host;
        foreach ($hosts as $host) {
            $hostSystems[] = $this->getVMwareHostSystem($vServer, $host->getReferenceId());
        }
        $vServer->disconnect();
        usort($hostSystems, self::sortByMemAvailable());
        return $hostSystems;
    }

    /**
     * @param array $output
     * @return array
     */
    private function _prepOutput($output = Array()) {

        // store our output
        $this->_log->info('================================================================================');
        $this->_log->info('BaseController::_prepOutput()');

        /*
        // set up our output
        $output['env']     = is_object($this->_env) ? "Unknown" : $this->_env;
        $output['route']   = $this->_route;
        $output['version'] = $this->_config['version'];
        */

        // return our output
        return $output;

    } // _prepOutput()

    /**
     * Renders either to "JSON" or the standard viewmodel
     *
     * @param mixed $output
     * @param string $model "json" or "View"
     * @return ViewModel|JsonModel
     *
     * $output should have the following format
     * array("success" => true|false,
     *       "error" => "error message if success is false",
     *       "message" => "message to user when success is true",
     *       "logLevel" => 1|2|3,
     *       "logOutput" => "log message");
     */
    protected function renderView($output, $model = "json") {
        $this->writeLog($output);

        if ($model === "json") {
            $jsonOutput = new JsonModel((array)$output);

            if (isset($output['cache']) AND $output['cache'] = true) {
                if (isset($output['cacheTTL'])) {
                    $lifetime = $output['cacheTTL'];
                } else {
                    $lifetime = $this->defaultCacheLifetime;
                }

                $this->writeCache($jsonOutput->serialize(), null, $lifetime);
            }

            return $jsonOutput;
        } elseif ($model === "view") {
            return new ViewModel((array)$output);
        } else if ($model === "raw") {
            $viewModel = new ViewModel(array("data" => print_r($output, true)));
            $viewModel->setTemplate("neumatic/unformatted.phtml");
            $viewModel->setTerminal(true);
            return $viewModel;
        } else if ($model === "pre") {
            $viewModel = new ViewModel(array("data" => "<pre>" . print_r($output, true) . "</pre>"));
            $viewModel->setTemplate("neumatic/unformatted.phtml");
            $viewModel->setTerminal(true);
            return $viewModel;
        } else if ($model === "xml") {
            $viewModel = new ViewModel(array("data" => $output));
            $viewModel->setTemplate("neumatic/unformatted.phtml");
            $viewModel->setTerminal(true);
            $this->getResponse()->getHeaders()->addHeaders(array('Content-type' => 'text/xml'));
            return $viewModel;
        } else {
            $jsonOutput = new JsonModel((array)$output);

            if (isset($output['cache']) AND $output['cache'] = true) {
                if (isset($output['cacheTTL'])) {
                    $lifetime = $output['cacheTTL'];
                } else {
                    $lifetime = $this->defaultCacheLifetime;
                }

                $this->writeCache($jsonOutput->serialize(), null, $lifetime);
            }
            return $jsonOutput;
        }
    }

    /**
     * Writes to the NeuMatic log file and write actions to the neumatic.audit table
     *
     * Uses Common Log Format: http://en.wikipedia.org/wiki/Common_Log_Format
     * Example:
     * 127.0.0.1 user-identifier frank [10/Oct/2000:13:55:36 -0700] "GET /apache_pb.gif HTTP/1.0" 200 2326
     *      user-identifier will always be "-" since we don't have that info
     *      size in bytes (last field) will always be "-" since we don't have that either
     *
     * Adds application-specific at the end of the CLF fields, in the following order
     * Attribute        Type        Values                    Use        Default         Comments
     * -----------        ------        ----------------------    -------    --------------- ---------------
     * logLevel            integer        [0-7]                    LOG        6 (info)        Output as string representation
     * controller       string      controller name         LOG     none;referenced
     * function         string      calling function name   LOG     none;referenced
     * execTime         int         execution time in secs  LOG     none;calculated
     * success            boolean        [01]  ||  [true|false]    UI        1
     * logOutput        string        log message                LOG        "-"
     *
     * UI ONLY:
     * error            string        str returned on error    UI        "-"
     * message          string      str returned on success UI      "-"
     *
     * Log Levels are standard:
     * EMERG   = 0;  // Emergency: system is unusable
     * ALERT   = 1;  // Alert: action must be taken immediately
     * CRIT    = 2;  // Critical: critical conditions
     * ERR     = 3;  // Error: error conditions
     * WARN    = 4;  // Warning: warning conditions
     * NOTICE  = 5;  // Notice: normal but significant condition
     *               // Using this for all audit activity (writes)
     *               // Message will be written to neumatic.audit table
     * INFO    = 6;  // Informational: informational messages
     * DEBUG   = 7;  // Debug: debug messages
     *
     * @param $messageObject
     */
    protected function writeLog($messageObject) {
        // parse out the attributes
        $success = array_key_exists("success", $messageObject) ? $messageObject["success"] : 1;
        if ($success == false) $success = 0;

        // logLevel and logOutput
        $logLevel       = array_key_exists("logLevel", $messageObject) ? $messageObject["logLevel"] : Logger::INFO;
        $logLevelString = $this->_logLevelStrings[$logLevel];
        $logOutput      = array_key_exists("logOutput", $messageObject) ? $messageObject["logOutput"] : "-";

        // controller
        $pathArray  = explode('\\', $this->params('controller'));
        $controller = strtolower($pathArray[2]);

        // calculate execution time. _timeStart was set in preDispatch
        $execTime = round(microtime(true) - $this->_timeStart, 2);

        // get the function that called renderView() or the function directly
        $trace = debug_backtrace();
        array_shift($trace);
        $caller = array_shift($trace);
        if ($caller['function'] == "renderView") {
            $caller = array_shift($trace);
        }

        $request  = $this->getRequest();
        $response = $this->getResponse();

        // 10.33.204.154 - rcallaha [06/Aug/2014:16:31:50 -0400] "GET https://stlabvsts01.va.neustar.com/users/getAndLogUser 1.1"
        if (isset($_SERVER['REMOTE_ADDR'])) {
            $ipAddress = $_SERVER['REMOTE_ADDR'];
        } else {
            $ipAddress = '0.0.0.0';
        }
        $logMessage =
            // CLF - Common Log Format here
            $ipAddress . " " .
            "- " .
            ($this->_user ? $this->_user->getUserName() : " ") . " " .
            "[" . date('d/M/Y:H:i:s O') . "] " .
            "\"" . $request->getMethod() . " " . $request->getUri()->getPath() . " HTTP/" . $request->getVersion() . "\" " .
            $response->getStatusCode() . " " .
            "- " .
            // custom data additions here
            $logLevelString . " " .
            $controller . " " .
            $caller['function'] . " " .
            $execTime . " " .
            $success . " " .
            $logOutput . " ";

        $this->_log->debug($logMessage);

        // process the audit info now, but only if logLevel = NOTICE
        if ($logLevel == Logger::NOTICE) {
            // parameters; used for auditing
            $parameters = array_key_exists("parameters", $messageObject) ? $messageObject["parameters"] : "";

            $auditRec = new Model\NMAudit();
            $auditRec
                ->setUserId($this->_user->getId())
                ->setUserName($this->_user->getUsername())
                ->setDateTime(date('Y-m-d H:i:s'))
                ->setIpAddress($_SERVER['REMOTE_ADDR'])
                ->setMethod($request->getMethod())
                ->setUri($request->getUri()->getPath())
                ->setController($controller)
                ->setFunction($caller['function'])
                ->setParameters($parameters)
                ->setDescr($logOutput);
            $this->_auditTable->create($auditRec);
        }
    }

    /**
     * @param $url
     * @param null $post
     * @param bool $decodeJson
     * @param string $username
     * @param string $password
     * @return mixed
     */
    protected function curlGetUrl($url, $post = null, $decodeJson = true, $username="", $password="") {
        $username = $username ? $username : $this->_user->getUsername();
        $password = $password ? $password : $this->_user->get('password');

        $curl = curl_init($url);

        curl_setopt($curl, CURLOPT_HTTPAUTH, CURLAUTH_BASIC);
        if (strstr($url, "https") !== -1) {
            curl_setopt($curl, CURLOPT_USERPWD, "{$username}:{$password}");
        }
        curl_setopt($curl, CURLOPT_VERBOSE, true);
        curl_setopt($curl, CURLOPT_HEADER, false);
        curl_setopt($curl, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($curl, CURLOPT_SSL_VERIFYHOST, false);
        curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);

        if (is_array($post)) {
            curl_setopt($curl, CURLOPT_POSTFIELDS, $post);
        }

        $response = curl_exec($curl);
        curl_close($curl);

        if ($decodeJson) {
            try {
                $json = json_decode($response);
            } catch (\ErrorException $e) {
                return $this->renderView(array("success" => false, "message" => "Could not decode JSON from {$url}"));
            }
            return $json;
        } else {
            return $response;
        }
    }


    protected function writeCache($output, $path = null, $lifetime = null) {

        $uid = $this->_user->getUsername();

        if ($lifetime == null AND isset($this->defaultCacheLifetime)) {
            $lifetime = $this->defaultCacheLifetime;
        }

        if (isset($this->cachePathBase)) {
            $cachePathBase = $this->cachePathBase;
        } else {
            $contExp    = explode("Controller\\", $this->params()->fromRoute('controller'));
            $controller = end($contExp);
            if (isset($this->chefServer)) {
                $cachePathBase = "/var/www/html/neumatic/data/cache/" . $uid . "/" . $controller . "/" . $this->chefServer . "/";
            } else {
                $cachePathBase = "/var/www/html/neumatic/data/cache/" . $uid . "/" . $controller . "/";
            }

        }

        if ($path == null) {

            $cachePath = $cachePathBase;

            $action = $this->params()->fromRoute('action');
            $params = $this->params()->fromRoute();

            unset($params['controller']);
            unset($params['action']);

            $cachePath .= $action;

            if (!empty($params)) {
                foreach ($params AS $param) {
                    $cachePath .= "/" . $param;
                }
            }
        } else {
            $cachePath = $cachePathBase . $path;
        }
        if (!file_exists($cachePath)) {
            mkdir($cachePath, 0777, true);
        }
        if (file_exists($cachePath . "/cacheFile")) {
            unlink($cachePath . "/cacheFile");
        }
        if ($fh = fopen($cachePath . "/cacheFile", 'w')) {

            fwrite($fh, $output);
            fclose($fh);
        }
        if ($lifetime != null) {
            if ($fh = fopen($cachePath . "/ttl", 'w')) {

                fwrite($fh, $lifetime);
                fclose($fh);
            }
        }
    }

    protected function checkCache($path = null, $returnOutput = null) {
        $uid = $this->_user->getUsername();
        if (isset($this->cachePathBase)) {
            $cachePathBase = $this->cachePathBase;
        } else {
            $contExp       = explode("Controller\\", $this->params()->fromRoute('controller'));
            $controller    = end($contExp);
            $cachePathBase = "/var/www/html/neumatic/data/cache/" . $uid . "/" . $controller . "/" . $this->chefServer . "/";
        }


        if ($path == null) {
            $action = $this->params()->fromRoute('action');
            $params = $this->params()->fromRoute();
            unset($params['controller']);
            unset($params['action']);
            $this->cachePath = $cachePathBase . $action;
            if (!empty($params)) {
                foreach ($params AS $param) {
                    $this->cachePath .= "/" . $param;
                }
            }
            if (file_exists($this->cachePath . "/cacheFile")) {
                if (file_exists($this->cachePath . "/ttl")) {

                    $cacheLifetime = file_get_contents($this->cachePath . "/ttl");

                } else {
                    $cacheLifetime = $this->defaultCacheLifetime;
                }

                if (filemtime($this->cachePath . "/cacheFile") > (time() - $cacheLifetime)) {
                    $cacheOutput = file_get_contents($this->cachePath . "/cacheFile");
                    if ($this->isJson($cacheOutput)) {

                        $cacheOutput = json_encode(json_decode($cacheOutput));

                    }
                    echo $cacheOutput;
                    die();
                } else {
                    unlink($this->cachePath . "/cacheFile");
                }

            }
        } else {
            $tempCachePath = $cachePathBase . $path;

            if (file_exists($tempCachePath . "/cacheFile")) {

                if (file_exists($tempCachePath . "/ttl")) {

                    $cacheLifetime = file_get_contents($tempCachePath . "/ttl");

                } else {
                    $cacheLifetime = $this->defaultCacheLifetime;
                }
                if (filemtime($tempCachePath . "/cacheFile") > (time() - $cacheLifetime)) {
                    $cacheOutput = file_get_contents($tempCachePath . "/cacheFile");
                    if ($returnOutput === true) {
                        return $cacheOutput;
                    } else {
                        echo $cacheOutput;
                        die();
                    }
                } else {
                    unlink($tempCachePath . "/cacheFile");
                }
            }
            return false;
        }
    }


    protected function clearCache($action, $params = null, $path = null) {
        if ($path == null) {
            $cachePath = $this->cachePathBase . $action;
            if ($action == "all") {
                $uid       = $this->_user->getUsername();
                $cachePath = "/var/www/html/neumatic/data/cache/" . $uid;

            }
            if ($params != null) {
                if (isset($params['controller'])) {
                    unset($params['controller']);
                }
                if (isset($params['action'])) {
                    unset($params['action']);
                }

                foreach ($params AS $param) {
                    $cachePath .= "/" . $param;
                }
            }

            if (is_dir($cachePath)) {
                $objects = scandir($cachePath);

                foreach ($objects as $object) {

                    if ($object != "." && $object != "..") {
                        if (filetype($cachePath . "/" . $object) == "dir") {
                            $this->rrmdir($cachePath . "/" . $object);

                        } else {
                            unlink($cachePath . "/" . $object);
                        }
                    }
                }
            }
        } else {
            $tempCachePath = $this->cachePathBase . $path;
            if (is_dir($tempCachePath)) {
                $objects = scandir($tempCachePath);

                foreach ($objects as $object) {
                    if ($object != "." && $object != "..") {
                        if (filetype($tempCachePath . "/" . $object) == "dir") {

                            $this->rrmdir($tempCachePath . "/" . $object);
                        } else {
                            unlink($tempCachePath . "/" . $object);
                        }
                    }
                }
            }
        }

    }

    protected function rrmdir($dir) {
        if (is_dir($dir)) {
            $objects = scandir($dir);

            foreach ($objects as $object) {
                if ($object != "." && $object != "..") {
                    if (filetype($dir . "/" . $object) == "dir") $this->rrmdir($dir . "/" . $object); else unlink($dir . "/" . $object);
                }
            }
            reset($objects);
            rmdir($dir);
        }
    }

    function isJson($string) {
        json_decode($string);
        return (json_last_error() == JSON_ERROR_NONE);
    }

}
