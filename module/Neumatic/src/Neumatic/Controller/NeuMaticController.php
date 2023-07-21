<?php

namespace Neumatic\Controller;

use Zend\Log\Logger;
use Zend\View\Model\JsonModel;
use Zend\Mvc\MvcEvent;

use STS\Util\SSH2;

use Neumatic\Model;


class NeuMaticController extends Base\BaseController {

    protected $logDir;

    protected $cobblerExecFile; // cobbler watcher script
    protected $cobblerWatcherFile; // log output of the cobbler watcher script
    protected $cobblerWatcherErrorFile; // error output of the cobbler watcher script

    protected $chefExecFile; // chef watcher script
    protected $chefWatcherFile; // log output of the chef watcher process
    protected $chefWatcherErrorFile; // error output of the chef watcher process

    protected $consoleExecFile;
    protected $consoleWatcherFile;
    protected $consoleWatcherErrorFile;

    /** @var Model\NMChefTable $chefTable */
    protected $chefTable;
    /** @var Model\NMServerTable $serverTable */
    protected $serverTable;
    /** @var  Model\NMLeaseTable $leaseTable */
    protected $leaseTable;

    protected $neumaticServer;

    /**
     * @param MvcEvent $e
     * @return mixed
     */
    public function onDispatch(MvcEvent $e) {
        date_default_timezone_set('America/New_York');

        $this->logDir = "/opt/neumatic/watcher_log";

        $this->cobblerExecFile         = "cobbler_watch.php";
        $this->cobblerWatcherFile      = $this->logDir . "/cobbler_watch.log";

        $this->chefExecFile            = "chef_watch.php";
        $this->chefWatcherFile         = $this->logDir . "/chef_watch.log";

        $this->consoleExecFile         = "console_watch.php";
        $this->consoleWatcherFile      = $this->logDir . "/console_watch.log";
        $this->consoleWatcherErrorFile = $this->logDir . "/console_watch.err";

        // instantiate our cchef table DAO
        $this->serverTable = new Model\NMServerTable($this->_config);
        $this->chefTable   = new Model\NMChefTable($this->_config);
        $this->leaseTable  = new Model\NMLeaseTable($this->_config);

        $this->neumaticServer = $_SERVER['SERVER_NAME'];

        return parent::onDispatch($e);
    }

    public function writeLogAction($messageObject) {
        return $this->renderView($messageObject);
    }

    /**
     * Quote of the day from http://www.brainyquote.com
     * Parses the JavaScript that is returned to get the quote and the author
     * Saves to the data directory config['dataDir'] for caching each day
     *
     * @return JsonModel|\Zend\View\Model\JsonModel
     */
    public function getQuoteOfTheDayAction() {
        $qotd = array(
            "quote" => "Whever you go, there you are.",
            "author" => "I don't know"
        );

        // different quotes for different folks
        $quoteCategories = array(
            'management',
            'inspire',
            'sports',
            'life',
            'funny',
            'love',
            'art'
        );
        $quoteCategory = $quoteCategories[rand(0, 6)];
        $cacheFile = $this->_config['dataDir'] . "/" . $quoteCategory;

        if (file_exists($cacheFile)) {
            $qotd = unserialize(file_get_contents($cacheFile));
        }
        return $this->renderView(array(
            "success" => true,
            "quote" => $qotd['quote'],
            "author" => $qotd['author']
        ));
    }

    private static function compareVersions() {
        return function($a, $b) {
            return version_compare($a, $b) * -1;
        };
    }

    public function getNeuCollectionCookbookVersionsAction() {
        $versions = array();
        foreach ($this->_config['gitProjectIds'] as $cbName => $projectId) {
            $url = "https://git.nexgen.neustar.biz/api/v3/projects/{$projectId}/repository/tags?private_token=";
            $json = $this->curlGetUrl($url);
            $tags = array();
            foreach ($json as $v) {
                $tags[] = $v->name;
            }
            usort($tags, self::compareVersions());
            $cbName = str_replace("-", "_", $cbName);
            $versions[$cbName] = $tags[0];
        }

        return $this->renderView(array(
            "success" => true,
            "versions" => $versions
        ));
    }

    /**
     * Get a bunch of fun statics on the use of NeuMatic
     * @return JsonModel
     */
    public function getStatsAction() {
        $stats = array();

        $servers             = $this->serverTable->getAll("all");
        $stats['numServers'] = number_format(count($servers));
        $numLab              = 0;
        foreach ($servers as $s) {
            if (preg_match("/stlabvnode/", $s->getName())) {
                $numLab++;
            }
        }
        $stats['numLabServers'] = $numLab;

        $userTable         = new Model\NMUserTable($this->_config);
        $users             = $userTable->getAll();
        $stats['numUsers'] = number_format(count($users));

        $loginsThisMonth             = $userTable->getLoginsThisMonth();
        $stats['numLoginsThisMonth'] = count($loginsThisMonth);

        $loginsThisWeek            = $userTable->getLoginsThisWeek();
        $stats['numLoginsThisWeek'] = count($loginsThisWeek);

        $row                = $userTable->getNumBuilds();
        $stats['numBuilds'] = number_format(intval($row->numServerBuilds));

        $stats['numBuilding'] = $this->serverTable->getNumBuilding();

        $buildTimes = $this->serverTable->getAverageBuildTimes();
        $stats['avgTimeVmware'] = $buildTimes['vmware'];
        $stats['avgTimeRemote'] = $buildTimes['remotes'];
        $stats['avgTimeStandalone'] = $buildTimes['standalones'];
        $stats['avgTimeBlade'] = $buildTimes['blades'];

        return $this->renderView(array(
                                     "success"   => true,
                                     "stats"     => $stats,
                                     "logLevel"  => Logger::INFO,
                                     "logOutput" => "Returning stats"
                                 ));
    }

    public function getTeamsAction() {
        $teamTable = new Model\NMTeamTable($this->_config);
        $teams = $teamTable->getAll();

        $data = array();
        foreach ($teams as $team) {
            $data[] = $team->toObject();
        }
        return $this->renderView(array(
            "success"   => true,
            "teams"   => $data,
            "logLevel"  => Logger::INFO,
            "logOutput" => count($teams) . " teams were returned"
        ));
    }

    /**
     * Get server details
     *
     * @return JsonModel
     */
    public function getServerAction() {
        $serverId    = $this->params()
                            ->fromRoute('param1');
        $serverTable = new Model\NMServerTable($this->_config);
        $server      = $serverTable->getById($serverId);

        if (!$server->getId()) {
            return $this->renderView(array(
                                         "success"   => false,
                                         "error"     => "Unknown server ID: {$serverId}",
                                         "logLevel"  => Logger::ERR,
                                         "logOutput" => "Unknown server ID: {$serverId}"
                                     ));
        }

        switch ($server->getServerType()) {
            case 'standalone':
                $obj        = $server->toObject();
                $standaloneTable = new Model\NMStandaloneTable($this->_config);
                $standalone = $standaloneTable->getByServerId($server->getId());
                foreach ($standaloneTable->getColumnNames() as $prop) {
                    if (property_exists($standalone, $prop)) {
                        if ($prop == 'id') {
                            $obj->standaloneId = $standalone->get('id');
                        } else {
                            $obj->$prop = $standalone->get($prop);
                        }
                    }
                }
                break;
            case 'remote':
                $obj        = $server->toObject();
                $standaloneTable = new Model\NMStandaloneTable($this->_config);
                $standalone = $standaloneTable->getByServerId($server->getId());
                foreach ($standaloneTable->getColumnNames() as $prop) {
                    if (property_exists($standalone, $prop)) {
                        if ($prop == 'id') {
                            $obj->standaloneId = $standalone->get('id');
                        } else {
                            $obj->$prop = $standalone->get($prop);
                        }
                    }
                }
                break;
            case 'blade':
                $obj        = $server->toObject();
                $bladeTable = new Model\NMBladeTable($this->_config);
                $blade      = $bladeTable->getByServerId($server->getId());
                foreach ($bladeTable->getColumnNames() as $prop) {
                    if (property_exists($blade, $prop)) {
                        if ($prop == 'id') {
                            $obj->bladeId = $blade->get('id');
                        } else {
                            $obj->$prop = $blade->get($prop);
                        }
                    }
                }
                break;
            case 'vmware':
		    case 'vmwareCobbler':
                $obj         = $server->toObject();
                $vmwareTable = new Model\NMVMWareTable($this->_config);
                $vmware      = $vmwareTable->getByServerId($server->getId());
                foreach ($vmwareTable->getColumnNames() as $prop) {
                    if (property_exists($vmware, $prop)) {
                        if ($prop == 'id') {
                            $obj->vmwareId = $vmware->get('id');
                        } else {
                            $obj->$prop = $vmware->get($prop);
                        }
                    }
                }
                // provide a shortened date
                $obj->dateCreatedShort = date('Y-m-d', strtotime($server->getDateCreated()));

                // get the disk info
                $luns      = $this->getServerStorage($server->getId());
                $obj->luns = array();
                foreach ($luns as $lun) {
                    $obj->luns[] = $lun->toObject();
                }

                // check if server is from the pool. if so, pass the serverPoolId
                $serverPoolTable = new Model\NMServerPoolTable($this->_config);
                $poolServer      = $serverPoolTable->getByServerId($serverId);
                if ($poolServer->getId()) {
                    $obj->serverPoolId = $poolServer->getId();

                    // get the lease information for this server
                    $nmLeaseTable = new Model\NMLeaseTable($this->_config);
                    $nmLease      = $nmLeaseTable->getByServerId($server->getId());

                    if ($nmLease->getId()) {
                        // lease exists for this serer
                        $obj->isLeased        = 1;
                        $obj->leaseStartDate  = date('Y-m-d', strtotime($nmLease->getLeaseStart()));
                        $obj->daysToLeaseEnd  = $this->getDaysToLeaseEnd($nmLease);
                        $obj->leaseAlertClass = "lease-alert-bg-" . $this->getLeaseAlertColor($obj->daysToLeaseEnd);

                        // add the lease extension info
                        $obj->leaseDuration          = $nmLease->getLeaseDuration();
                        $obj->extensionInDays        = $nmLease->getExtensionInDays();
                        $obj->numExtensionsAllowed   = $nmLease->getNumExtensionsAllowed();
                        $obj->numTimesExtended       = $nmLease->getNumTimesExtended();
                        $obj->numExtensionsRemaining = $nmLease->getNumExtensionsAllowed() - $nmLease->getNumTimesExtended();
                    } else {
                        // no lease found
                        $obj->isLeased = 0;
                    }
                } else {
                    // not a server from the pool
                    $obj->serverPoolId = 0;
                }
                break;
            default:
                return $this->renderView(array(
                                             "success"   => false,
                                             "error"     => "Unknown server type: {$server->getServerType()}",
                                             "logLevel"  => Logger::ERR,
                                             "logOutput" => "Unknown server type: {$server->getServerType()}"
                                         ));
        }

        // since glass can be used directly to make mods, going to grab the current LDAP config user and host
        // groups for this host and merge with what's in NeuMatic's db
        $ldapUserGroups = $this->getLdapHostUsergroups($server->getName());
        $ldapHostGroups = $this->getLdapHostHostGroups($server->getName());

        $ugTable = new Model\NMUsergroupTable($this->_config);
        $results = $this->arrayOfModelsToObjects($ugTable->getByServerId($server->getId()));
        $dbLdapUserGroups = array();
        foreach ($results as $r) {
            $dbLdapUserGroups[] = $r->name;
        }

        $hgTable = new Model\NMHostgroupTable($this->_config);
        $results = $this->arrayOfModelsToObjects($hgTable->getByServerId($server->getId()));
        $dbLdapHostGroups = array();
        foreach ($results as $r) {
            $dbLdapHostGroups[] = $r->name;
        }

        $results = array_diff($ldapUserGroups, $dbLdapUserGroups);
        $obj->ldapUserGroups = array_merge($results, $dbLdapUserGroups);

        $results = array_diff($ldapHostGroups, $dbLdapHostGroups);
        $obj->ldapHostGroups = array_merge($results, $dbLdapHostGroups);

        // if role is comma delimited, then just select the first role
        if (preg_match("/,/", $server->getChefRole())) {
            $roles         = explode(",", $server->getChefRole());
            $obj->chefRole = $roles[0];
        }

        // get owner info
        $userTable = new Model\NMUserTable($this->_config);
        $user      = $userTable->getByUserName($server->getUserCreated());

        return $this->renderView(array(
                                     "success"   => true,
                                     "server"    => $obj,
                                     "owner"     => $user->toObject(),
                                     "logLevel"  => Logger::INFO,
                                     "logOutput" => "Returning server " . $server->getName()
                                 ));
    }

    /**
     * Returns the number of days remaining until lease end
     * @param Model\NMLease $lease
     * @returns int
     */
    private function getDaysToLeaseEnd(Model\NMLease $lease) {
        $leaseStartTime = strtotime($lease->getLeaseStart());
        $diffInDays     = (time() - $leaseStartTime) / 60 / 60 / 24;
        return floor($lease->getLeaseDuration() - $diffInDays + ($lease->getExtensionInDays() * $lease->getNumTimesExtended()));
    }

    /**
     * Use the number of days to lease end and the params in the config file to return the CSS
     * class for coloring a string, nofication, etc.
     * @param $daysToLeaseEnd
     * @return string
     */
    private function getLeaseAlertColor($daysToLeaseEnd) {
        // set the css to color the alert based on values set in the config file
        if ($daysToLeaseEnd <= $this->_config['vmLease']['redAlert']) {
            return 'red';
        } else if ($daysToLeaseEnd <= $this->_config['vmLease']['yellowAlert']) {
            return 'yellow';
        } else {
            return 'normal';
        }
    }

    /**
     * Get the next server available from the pool
     *
     * @return JsonModel
     */
    public function getNextFreePoolServerAction() {
        // check to see how many servers this user has and if he can have more
        $serverPoolTable = new Model\NMServerPoolTable($this->_config);
        $servers         = $serverPoolTable->getByUserId($this->_user->getId());

        // if he's an admin, he can as many as he wants. yay!
        if (count($servers) >= $this->_user->getMaxPoolServers() && $this->_user->getUserType() != 'Admin') {
            // oops! you've used as many as we've allowed. Have to break it to you
            return $this->renderView(array(
                                         "success"   => false,
                                         "code"      => "Max Servers Reached",
                                         "error"     => "You are already using {$this->_user->getMaxPoolServers()} servers, the max allowed from the pool.",
                                         "logLevel"  => Logger::INFO,
                                         "logOutput" => "User {$this->_user->getUsername()} reached {$this->_user->getMaxPoolServers()}, his max number of pool servers"
                                     ));
        }

        // ok, we're good. give him another
        $server = $serverPoolTable->getNextFree();

        if ($server->getId()) {
            $config             = $this->_config['vmDefaults'];
            $obj                = $server->toObject();
            $obj->id            = 0;
            $obj->serverPoolId  = $server->getId();
            $obj->vSphereSite   = $config['vSphereSite'];
            $obj->vSphereServer = $config['vSphereServer'];

            $obj->dcName  = $config['dcName'];
            $obj->dcUid   = $config['dcUid'];
            $obj->ccrName = $config['ccrName'];
            $obj->ccrUid  = $config['ccrUid'];
            $obj->rpUid   = $config['rpUid'];

            $obj->network       = $config['network'];
            $obj->serverType    = $config['serverType'];
            $obj->location      = $config['location'];
            $obj->vlanName      = $config['vlanName'];
            $obj->vlanId        = $config['vlanId'];
            $obj->cobblerServer = $config['cobblerServer'];
            $obj->cobblerDistro = $config['cobblerDistro'];
            $obj->chefServer    = $config['chefServer'];
            $obj->chefRole      = $config['chefRole'];
            $obj->chefEnv       = $config['chefEnv'];
            $obj->template      = $config['template'];

            $obj->okToBuild = false;
            return $this->renderView(array(
                                         "success"    => true,
                                         "server"     => $obj,
                                         "logLevel"   => Logger::NOTICE,
                                         "logOutput"  => "User " . $this->_user->getUsername() . " was granted " . $server->getName() . " from the lab pool",
                                         "parameters" => "[serverName: {$server->getName()}, userName: {$this->_user->getUsername()}]"
                                     ));
        } else {
            // umm, we seem to be out of servers as we've reached the end of the pool
            return $this->renderView(array(
                                         "success"   => false,
                                         "code"      => "No More Servers",
                                         "error"     => "There are no more free servers available in the pool. Perhaps you'd like to pay for one?",
                                         "logLevel"  => Logger::ERR,
                                         "logOutput" => "There are no more free servers in the lab pool"
                                     ));
        }
    }

    /**
     * Release this lab vm back to the server pool
     *
     * @return JsonModel
     */
    public function releaseBackToPoolAction() {
        $serverId = $this->params()->fromRoute('param1');

        // get the server
        $serverTable = new Model\NMServerTable($this->_config);
        $server      = $serverTable->getById($serverId);

        // delete the lease entry if exists for this VM
        $nmLeaseTable = new Model\NMLeaseTable($this->_config);
        $nmLease      = $nmLeaseTable->getByServerId($server->getId());
        if ($nmLease->getId()) {
            $nmLeaseTable->delete($nmLease);
        }

        // get the pool server
        $serverPoolTable = new Model\NMServerPoolTable($this->_config);
        $serverPool      = $serverPoolTable->getByServerId($serverId);

        if ($serverPool->getId()) {
            // release the server back into the pool
            $serverPool->setServerId(null)
                       ->setState('Free')
                       ->setUserId(null);
            $serverPoolTable->update($serverPool);
        }

        // delete the server
        $serverTable->delete($server);

        return $this->renderView(array(
                                     "success"    => true,
                                     "logLevel"   => Logger::NOTICE,
                                     "logOutput"  => "User " . $this->_user->getUsername() . " released " . $serverPool->getName() . " back to lab pool",
                                     "parameters" => "[serverName: {$serverPool->getName()}, userName: {$this->_user->getUsername()}]"
                                 ));
    }

    /**
     * Extend this servers lease by the alloted about of day
     *
     * @return \Zend\View\Model\JsonModel
     */
    public function extendLeaseAction() {
        $serverId = $this->params()
                         ->fromRoute('param1');

        // server info
        $serverTable = new Model\NMServerTable($this->_config);
        $server      = $serverTable->getById($serverId);

        // get the lease info
        $leaseTable = new Model\NMLeaseTable($this->_config);
        $lease      = $leaseTable->getByServerId($serverId);

        // do we have a lease entry?
        if ($lease->getId()) {
            // are we allowed to extend the lease?
            if ($lease->getNumTimesExtended() < $lease->getNumExtensionsAllowed()) {
                $lease->setNumTimesExtended($lease->getNumTimesExtended() + 1);
                $lease->setExpired(0);
                $lease = $leaseTable->update($lease);
            } else {
                // no lease extensions remain
                return $this->renderView(array(
                                             "success"   => false,
                                             "message"   => "No lease extensions remain for this server",
                                             "logLevel"  => Logger::INFO,
                                             "logOutput" => "User " . $this->_user->getUsername() . " requested a lease extension on " . $server->getName() . " but there are none left"
                                         ));
            }
        } else {
            // no lease entry
            return $this->renderView(array(
                                         "success"   => false,
                                         "message"   => "No lease found for this server",
                                         "logLevel"  => Logger::ERR,
                                         "logOutput" => "No lease found for " . $server->getName()
                                     ));
        }

        $obj = (object)array();

        // get the number of days to lease end
        $obj->daysToLeaseEnd  = $this->getDaysToLeaseEnd($lease);
        $obj->leaseAlertClass = 'lease-alert-bg-' . $this->getLeaseAlertColor($obj->daysToLeaseEnd);

        // add the lease extension info
        $obj->leaseDuration          = $lease->getLeaseDuration();
        $obj->extensionInDays        = $lease->getExtensionInDays();
        $obj->numExtensionsAllowed   = $lease->getNumExtensionsAllowed();
        $obj->numTimesExtended       = $lease->getNumTimesExtended();
        $obj->numExtensionsRemaining = $lease->getNumExtensionsAllowed() - $lease->getNumTimesExtended();

        return $this->renderView(array(
                                     "success"   => true,
                                     "lease"     => $obj,
                                     "logLevel"  => Logger::INFO,
                                     "logOutput" => "Lease extension granted for " . $server->getName()
                                 ));
    }

    /**
     * @return \Zend\View\Model\JsonModel
     */
    public function deleteServerAction() {
        $serverId = $this->params()
                         ->fromRoute('param1');

        // get the server
        $serverTable = new Model\NMServerTable($this->_config);
        $server      = $serverTable->getById($serverId);

        // delete the lease entry if exists for this VM
        $nmLeaseTable = new Model\NMLeaseTable($this->_config);
        $nmLease      = $nmLeaseTable->getByServerId($server->getId());
        if ($nmLease->getId()) {
            $nmLeaseTable->delete($nmLease);
        }

        // delete the server
        $serverTable->delete($server);

        return $this->renderView(array(
                                     "success"    => true,
                                     "logLevel"   => Logger::NOTICE,
                                     "logOutput"  => "Server " . $server->getName() . " has been deleted",
                                     "parameters" => "[serverName: {$server->getName()}, userName: {$this->_user->getUsername()}]"
                                 ));
    }

    /**
     * @return JsonModel
     */
    public function getVMSizesAction() {
        $vmSizes = $this->_config['vmStandardSizes'];
        return $this->renderView(array(
                                     "success"   => true,
                                     "vmSizes"   => $vmSizes,
                                     "logLevel"  => Logger::INFO,
                                     "logOutput" => "Standard VM sizes was returned"
                                 ));
    }

    /**
     * Moved this function to the chef controller: getNeumaticServersAction()
     * Get the list of servers from the database
     *
     * @return JsonModel
     */
    public function getServersAction() {
        $adminOn = $this->params()
                        ->fromRoute('param1');

        $serverTable = new Model\NMServerTable($this->_config);
        $chefTable   = new Model\NMChefTable($this->_config);

        if ($adminOn == 'true') {
            $servers = $serverTable->getAll();
        } else {
            $servers = $serverTable->getByUsername($this->_user->getUsername());
        }

        $chefData = array();
        $response = null;
        try {
            $response = $this->curlGetUrl("https://{$this->neumaticServer}/chef/getNeumaticServers");
        } catch (\ErrorException $e) {
            // trap the exception, but let's not exit. If we don't get the data, we'll punt
        }
        if ($response) {
            try {
                $chefData = json_decode($response);
            } catch (\ErrorException $e) {
                // still trapping and ignoring
            }
        }
        // if we have the data, build a hash by hostname
        $chefHash = array();
        if ($chefData) {
            foreach ($chefData as $data) {
                if (is_object($data) && property_exists($data, 'hostname')) {
                    $chefHash[$data->hostname] = $data;
                }
            }
        }

        $data = array();
        foreach ($servers as $s) {
            $chef = $chefTable->getByServerId($s->getId());
            $s->setStatusText($this->calculateBuildTime($s, $chef));
            if (preg_match("/^([\w\d_-]+)\./", $s->getName(), $m)) {
                $hostname = $m[1];
            } else {
                $hostname = $s->getName();
            }
            if (preg_match("/^([\w\d_-]+)\./", $s->getChefServer(), $m)) {
                $chefServer = $m[1];
            } else {
                $chefServer = $s->getName();
            }
            $type = ucfirst($s->getServerType());
            if ($type == 'Vmware') $type = 'VMWare';
            $obj                      = $s->toObject();
            $obj->hostname            = $hostname;
            $obj->chefServer          = $chefServer;
            $obj->serverTypeDisplayed = $type;

            // add chef data if available
            if (array_key_exists($hostname, $chefHash)) {
                $chef                   = $chefHash[$hostname];
                $obj->chefServer        = $chef->chefServer;
                $obj->chefVersion       = $chef->chefVersion;
                $obj->chefVersionStatus = $chef->chefVersionStatus;
                $obj->ohaiTimeDelta     = $chef->ohaiTimeDelta;
                $obj->ohaiTimeStatus    = $chef->ohaiTimeStatus;
            }
            // if role is comma delimited, then just select the first role
            if (preg_match("/,/", $obj->chefRole)) {
                $roles         = explode(",", $obj->chefRole);
                $obj->chefRole = $roles[0];
            }
            $data[] = $obj;
        }
        return $this->renderView(array(
                                     "success"   => true,
                                     "servers"   => $data,
                                     "logLevel"  => Logger::INFO,
                                     "logOutput" => count($data) . " servers returned"
                                 ));
    }

    /**
     * Reset the date/time values for this server
     * Called right before a server is built
     *
     * @return JsonModel
     */
    public function resetStartTimeAction() {
        $serverId = $this->params()
                         ->fromRoute('param1');

        $serverTable = new Model\NMServerTable($this->_config);
        $server      = $serverTable->getById($serverId);

        // reset all the time values for this server
        $server
            ->setTimeBuildStart(date('Y-m-d H:i:s', time()))
            ->setTimeBuildEnd('')
            ->setDateBuilt('')
            ->setDateFirstCheckin('')
            ->setBuildStep(0)
            ->setBuildSteps(0);
        $serverTable->update($server);

        // remove the entry in the chef table
        $chefTable = new Model\NMChefTable($this->_config);
        $chef      = $chefTable->getByServerId($server->getId());
        if ($chef->getId()) {
            $chefTable->delete($chef);
        }

        // increment the build count for this user
        $this->incrementBuildCount();

        // reset the lease time if a lease already exists for this VM
        $nmLeaseTable = new Model\NMLeaseTable($this->_config);
        $nmLease      = $nmLeaseTable->getByServerId($server->getId());
        if ($nmLease->getId()) {
            $nmLease->setLeaseStart(date('Y-m-d H:i:s'))
                    ->setLeaseDuration($this->_config['vmLease']['leaseTimeInDays'])
                    ->setExtensionInDays($this->_config['vmLease']['extensionInDays'])
                    ->setNumExtensionsAllowed($this->_config['vmLease']['numExtensionsAllowed'])
                    ->setNumTimesExtended(0)
                    ->setExpired(0);
            $nmLeaseTable->update($nmLease);
        }

        return $this->renderView(array(
                                     "success"    => true,
                                     "logLevel"   => Logger::NOTICE,
                                     "logOutput"  => $server->getName() . " build start time reset",
                                     "parameters" => "[serverName: {$server->getName()}]"
                                 ));
    }

    /**
     *
     */
    private function incrementBuildCount() {
        $userTable = new Model\NMUserTable($this->_config);
        $this->_user->setNumServerBuilds($this->_user->getNumServerBuilds() + 1);
        $userTable->update($this->_user);
    }

    /**
     * @param Model\NMServer $server
     * @param Model\NMChef $chef
     * @return mixed|string
     */
    public function calculateBuildTime(Model\NMServer $server, Model\NMChef $chef) {
        if ($server->getStatus() == 'Built' && $server->getTimeBuildStart() && $server->getTimeBuildEnd()) {
            $diff = strtotime($server->getTimeBuildEnd()) - strtotime($server->getTimeBuildStart());
            $buildString = $server->getStatusText() . sprintf(" (%02d:%02d)", floor($diff / 60), $diff % 60);
            $cookTime = $this->calculateCookTime($server, $chef);
            if ($cookTime) {
                $buildString .= " (" . $cookTime . ")";
            }
            return $buildString;
        } else if ($server->getStatus() == 'Building' && $server->getTimeBuildStart()) {
            $timezone = 'America/New_York';
            date_default_timezone_set("America/New_York");

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

    /**
     * @param \Neumatic\Model\NMServer $server
     * @param \Neumatic\Model\NMChef $nmChef
     * @return mixed|string
     */
    public function calculateCookTime(Model\NMServer $server, Model\NMChef $nmChef) {
        if ($server->getStatus() == 'Built' && $nmChef->getCookStartTime() && $nmChef->getCookEndTime()) {
            $diff = strtotime($nmChef->getCookEndTime()) - strtotime($nmChef->getCookStartTime());
            //return sprintf("Cooked (%02d:%02d)", floor($diff / 60), $diff % 60);
            return sprintf("%02d:%02d", floor($diff / 60), $diff % 60);
        } else {
            return "";
        }
    }

    /**
     * @return JsonModel
     */
    public function getServerStatusAction() {
        $serverId    = $this->params()->fromRoute('param1');
        $serverTable = new Model\NMServerTable($this->_config);
        $server      = $serverTable->getById($serverId);
        $chefTable   = new Model\NMChefTable($this->_config);
        $chef        = $chefTable->getByServerId($server->getId());
        return $this->renderView(array(
                                     "success"    => true,
                                     "id"         => $server->getId(),
                                     "status"     => $server->getStatus(),
                                     "statusText" => $this->calculateBuildTime($server, $chef),
                                     "logLevel"   => Logger::INFO,
                                     "logOutput"  => $server->getName() . " has status of " . $server->getStatusText()
                                 ));
    }

    /**
     * @return \Zend\View\Model\JsonModel
     */
    public function getLeasesAction() {
        $neumatic = new Model\Neumatic($this->_config);
        $leases   = $neumatic->getLeases();
        $data     = array();
        foreach ($leases as $lease) {
            $leaseStartTime          = strtotime($lease['leaseStart']);
            $diffInDays              = (time() - $leaseStartTime) / 60 / 60 / 24;
            $lease['daysToLeaseEnd'] = floor($lease['leaseDuration'] - $diffInDays + ($lease['extensionInDays'] * $lease['numTimesExtended']));
            $lease['leaseEnd']       = date('Y-m-d H:i:s', $leaseStartTime + ($lease['daysToLeaseEnd'] * 60 * 60 * 24));
            $data[]                  = $lease;
        }
        return $this->renderView(array(
                                     "success"   => true,
                                     "leases"    => $data,
                                     "logLevel"  => Logger::INFO,
                                     "logOutput" => count($data) . " leased servers returned"
                                 ));
    }

    public function getLeaseAction() {
        $serverId    = $this->params()->fromRoute('param1');

        // get the server
        $serverTable = new Model\NMServerTable($this->_config);
        $server      = $serverTable->getById($serverId);

        // get the lease info
        $leaseTable = new Model\NMLeaseTable($this->_config);
        $lease      = $leaseTable->getByServerId($server->getId());

        $daysToLeaseEnd  = $this->getDaysToLeaseEnd($lease);

        $leaseObj = $lease->toObject();
        $leaseObj->daysToLeaseEnd = $daysToLeaseEnd;
        $leaseObj->alertClass = "lease-alert-bg-" . $this->getLeaseAlertColor($daysToLeaseEnd);
        $leaseObj->leaseEnd = date('Y-m-d H:i:s', strtotime($lease->getLeaseStart()) + ($daysToLeaseEnd * 60 * 60 * 24));


        return $this->renderView(array(
            "success"   => true,
            "server"    => $server->toObject(),
            "lease"     => $leaseObj,
            "logLevel"  => Logger::DEBUG,
            "logOutput" => "Returned lease info for " . $server->getName()
        ));
    }

    public function saveLeaseAction() {
        $json = $this->params()->fromPost('lease');

        // get the server
        $serverTable = new Model\NMServerTable($this->_config);
        $server      = $serverTable->getById($json['serverId']);

        $leaseTable = new Model\NMLeaseTable($this->_config);
        $lease      = $leaseTable->getById($json['id']);

        $lease
            ->setLeaseStart($json['leaseStart'])
            ->setLeaseDuration($json['leaseDuration'])
            ->setExpired($json['expired'])
            ->setExtensionInDays($json['extensionInDays'])
            ->setNumExtensionsAllowed($json['numExtensionsAllowed'])
            ->setNumTimesExtended($json['numTimesExtended']);
        $leaseTable->update($lease);

        return $this->renderView(array(
            "success"   => true,
            "logLevel"  => Logger::NOTICE,
            "logOutput" => "Lease params update for " . $server->getName()
        ));
    }

    /**
     * Queries the server and chef tables and returns the server details and status
     * Used by the Systems view which runs as interval to update the server values on the page
     *
     * @return JsonModel
     */
    public function getAllServerStatusAction() {
        $adminOn  = $this->params()->fromRoute('param1');
        $listType = $this->params()->fromRoute('param2');
        $ldapUserGroup = $this->params()->fromRoute('param3');

        $poolTable = new Model\NMServerPoolTable($this->_config);

        $userTable = new Model\NMUserTable($this->_config);
        $usersHash = $userTable->getIdHash();

        #$teamTable = new Model\NMTeamTable($this->_config);
        #$teamsHash = $teamTable->getIdHash();

        // get the list of NeuMatic servers: either those owned by the user or all if the case of ad Admin user
        if ($adminOn == 'true') {
            $servers = $this->serverTable->getAll($listType);
        } else if ($ldapUserGroup == "My Systems") {
            // TODO: Needs to be by owerId and then fall back to userCreated
            $servers = $this->serverTable->getByUsername($this->_user->getUsername(), $listType);
        } else {
            $servers = $this->serverTable->getByLdapUserGroup($ldapUserGroup, $listType);
        }

        // loop over the servers and gather the data needed to send back to the UI
        $data = array();
        foreach ($servers as $server) {
            // get the chef table data for this server
            $chef = $this->chefTable->getByServerId($server->getId());
            $pool = $poolTable->getByServerId($server->getId());

            // shorten the server name
            if (preg_match("/^([\w\d_-]+)\./", $server->getName(), $m)) {
                $hostname = $m[1];
            } else {
                $hostname = $server->getName();
            }

            // shorten the chef server name
            if (preg_match("/^([\w\d_-]+)\./", $chef->getServer(), $m)) {
                $chefHostname = $m[1];
            } else {
                $chefHostname = $chef->getServer();
            }

            // make nice the server type
            $serverTypeDisplayed = ucfirst($server->getServerType());
            if ($serverTypeDisplayed == 'Vmware') $serverTypeDisplayed = 'VMWare';

            // if role is comma delimited, then just select the first role
            if (preg_match("/,/", $server->getChefRole())) {
                $roles    = explode(",", $server->getChefRole());
                $chefRole = $roles[0];
            } else {
                $chefRole = $server->getChefRole();
            }

            // get the lease information if applicable
            $lease = $this->leaseTable->getByServerId($server->getId());
            if ($lease->getId()) {
                $isLeased        = 1;
                $daysToLeaseEnd  = $this->getDaysToLeaseEnd($lease);
                $leaseAlertClass = "lease-alert-" . $this->getLeaseAlertColor($daysToLeaseEnd);
            } else {
                $isLeased        = 0;
                $daysToLeaseEnd  = "-";
                $leaseAlertClass = "lease-alert-normal";
            }

            if ($server->getStatus() == "Building") {
                $timezone = 'America/New_York';
                date_default_timezone_set("America/New_York");

                $startStr  = $server->getTimeBuildStart();
                $startTime = strtotime($startStr);

                // get the time now, then convert to str and back to Epoch time in the current timezone
                $nowTime    = time();
                $nowStr     = date("Y-m-d H:i:s", $nowTime);
                $nowTimeNew = strtotime($nowStr . " " . $timezone);

                $diff = $nowTimeNew - $startTime;

                $statusText = $server->getStatusText();
                $elapsedTime = sprintf("%02d:%02d", floor($diff / 60), $diff % 60);
            } else {
                $statusText = $this->calculateBuildTime($server, $chef);
                $elapsedTime = "";
            }

            $data[] = array(
                "id"                  => $server->getId(),
                "poolServer"          => $pool->getId() ? 1 : 0,
                "serverPoolId"        => $pool->getId(),

                "hostname"            => $hostname,
                "fqdn"                => $server->getName(),
                "name"                => $server->getName(),

                "owner"               => array_key_exists($server->getOwnerId(), $usersHash) ? $usersHash[$server->getOwnerId()]->getUsername() : $server->getUserCreated(),
                "ldapUserGroup"       => $server->getLdapUserGroup() ? $server->getLdapUserGroup() : 'N/A',

                "serverType"          => $server->getServerType(),
                "serverTypeDisplayed" => $serverTypeDisplayed,
                "chefServerFqdn"      => $server->getChefServer(),
                "chefServer"          => $server->getChefServer(),
                "chefServerHostName"  => $chefHostname,
                "description"         => $server->getDescription(),
                "chefVersion"         => $chef->getVersion(),
                "chefVersionStatus"   => $chef->getVersionStatus(),
                "ohaiTime"            => $chef->getOhaiTimeInt() ? $chef->getOhaiTimeInt() : '',
                "ohaiTimeString"      => $chef->getOhaiTime(),
                "ohaiTimeDiff"        => $chef->getOhaiTimeDiff(),
                "ohaiTimeDiffString"  => $chef->getOhaiTimeDiffString(),
                "ohaiTimeStatus"      => $chef->getOhaiTimeStatus(),
                "cobblerDistro"       => $server->getCobblerDistro(),

                "chefRole"            => $chefRole,
                "chefEnv"             => $server->getChefEnv(),

                "status"              => $server->getStatus(),
                "statusText"          => $statusText,
                "elapsedTime"         => $elapsedTime,
                "buildStep"           => $server->getBuildStep(),
                "buildSteps"          => $server->getBuildSteps(),

                //"chefStatusText"      => $this->calculateCookTime($server, $chef),
                "userCreated"         => $server->getUserCreated(),
                "dateBuilt"           => $server->getDateBuilt(),
                "archived"            => $server->getArchived(),

                "isLeased"            => $isLeased,
                "daysToLeaseEnd"      => $daysToLeaseEnd,
                "leaseAlertClass"     => $leaseAlertClass
            );
        }
        return $this->renderView(array(
                                     "success"   => true,
                                     "servers"   => $data,
                                     "logLevel"  => Logger::INFO,
                                     "logOutput" => count($data) . " servers returned"
                                 ));
    }

    /**
     * @return JsonModel
     */
    public function updateStatusAction() {
        
        $status = $this->params()->fromPost('status');

        $serverTable = new Model\NMServerTable($this->_config);

        $server      = $serverTable->getById($this->params()
                                                  ->fromPost('serverId'));

        $server
            ->setStatus($status)
            ->setStatusText($this->params()->fromPost('statusText'));

        if ($status == 'Built') {
            // update the user built and timestamp
            $timestamp = date('Y-m-d H:i:s');
            $server
                ->setUserBuilt($this->_user->getUsername())
                ->setDateBuilt($timestamp);
        }
        $server = $serverTable->update($server);


        return $this->renderView(array(
                                     "success"    => true,
                                     "status"     => $server->getStatus(),
                                     "statusText" => $server->getStatusText(),
                                     "logLevel"   => Logger::INFO,
                                     "logOutput"  => $server->getName() . " status updated to " . $server->getStatusText()
                                 ));
    }

    /**
     * @return JsonModel
     */
    public function updateServerAction() {
        $serverId = $this->params()->fromRoute('param1');
        $params = json_decode($this->getRequest()->getContent());

        $serverTable = new Model\NMServerTable($this->_config);
        $server      = $serverTable->getById($serverId);

        $server->set($params->property, $params->value);
        $server = $serverTable->update($server);
        return $this->renderView(array(
            "success"    => true,
            "message"    => "Server updated successfully",
            "logLevel"   => Logger::NOTICE,
            "logOutput"  => $server->getName() . " " . $params->property . " changed to " . $server->get($params->property),
            "parameters" => "[serverName: {$server->getName()}]"
        ));
    }

    /**
     * @return JsonModel
     */
    public function serverBuiltAction() {
        $serverTable = new Model\NMServerTable($this->_config);
        $server      = $serverTable->getById($this->params()->fromRoute('param1'));

        $server
            ->setStatus('Built')
            ->setStatusText('Built')
            ->setDateBuilt(date("Y-m-d H:i:s"))
            ->setUserBuilt($this->_user->getUsername());
        $server = $serverTable->update($server);
        return $this->renderView(array(
                                     "success"    => true,
                                     "status"     => $server->getStatus(),
                                     "statusText" => $server->getStatusText(),
                                     "logLevel"   => Logger::NOTICE,
                                     "logOutput"  => $server->getName() . " has been built",
                                     "parameters" => "[serverName: {$server->getName()}]"
                                 ));
    }

    /**
     * @return JsonModel
     */
    public function stopBuildAction() {
        $serverId    = $this->params()
                            ->fromRoute('param1');
        $serverTable = new Model\NMServerTable($this->_config);
        $server      = $serverTable->getById($serverId);

        // delete the cobbler profile
        $json = $this->curlGetUrl("https://{$this->neumaticServer}/cobbler/deleteSystem/" . $serverId);
        if ($json && is_object($json) && property_exists($json, 'success') && !$json->success) {
            $this->writeLog(array(
                "logLevel" => Logger::ERR,
                "logOutput" => "Unable to delete cobbler profile for " . $server->getName()
            ));
        }

        // null the dateBuilt value of the server
        $server->setDateBuilt(null);
        $serverTable->update($server);

        if ($this->stopLogging($server)) {
            return $this->renderView(array(
                                         "success"    => true,
                                         "logLevel"   => Logger::NOTICE,
                                         "logOutput"  => $server->getName() . " build has been aborted",
                                         "parameters" => "[serverName: {$server->getName()}]"
                                     ));
        } else {
            return $this->renderView(array(
                                         "success"   => true,
                                         "logLevel"  => Logger::ERR,
                                         "logOutput" => "Could not find either Cobbler or Chef watcher process " . $server->getName()
                                     ));
        }
    }

    /**
     * @return JsonModel
     */
    /*
    public function consoleReadAction() {
        $serverId = $this->params()->fromRoute('param1');

        $serverTable = new Model\NMServerTable($this->_config);
        $server      = $serverTable->getById($serverId);

        $consoleTable = new Model\NMConsoleTable($this->_config);
        $console      = $consoleTable->getByServerId($server->getId());

        if ($console->getConsoleRunning()) {
            if ($server->getServerType() == 'blade') {
                if (file_exists($this->consoleWatcherFile . "." . $server->getName())) {
                    $consoleWatcher = file_get_contents($this->consoleWatcherFile . "." . $server->getName());
                } else {
                    $consoleWatcher = "N/A";
                }
                if (file_exists($this->consoleLogFile . "." . $server->getName())) {
                    $consoleFile = file_get_contents($this->consoleLogFile . "." . $server->getName());
                    $consoleData = utf8_encode($consoleFile);
                } else {
                    $consoleData = "N/A";
                }
            } else {
                $consoleWatcher = file_get_contents($this->consoleWatcherFile . "." . $server->getName());
                $consoleData    = 'Not available for VMs';
            }
        } else {
            $consoleWatcher = $console->getConsoleWatcherLog();
            $consoleData    = $console->getConsoleLog();
        }
        return $this->renderView(array(
                                     "success"        => true,
                                     "consoleWatcher" => $consoleWatcher,
                                     "consoleData"    => $consoleData,
                                     "logLevel"       => Logger::INFO,
                                     "logOutput"      => ""
                                 ));
    }
    */

    /**
     * Save this server
     *
     * @return JsonModel
     */
    public function saveServerAction() {
        $serverType = $this->params()->fromPost('serverType');

        if ($serverType == "") {
            return $this->renderView(array(
                                         "success"   => false,
                                         "error"     => "Server type is not defined",
                                         "logLevel"  => Logger::ERR,
                                         "logOutput" => "Server type is not defined"
                                     ));
        } else if (!preg_match("/vmware|blade|standalone|remote|vmwareCobbler/", $serverType)) {
            return $this->renderView(array(
                                         "success"   => false,
                                         "error"     => "Unknown server type: {$serverType}",
                                         "logLevel"  => Logger::ERR,
                                         "logOutput" => "Unknown server type: {$serverType}"
                                     ));
        }

        $timestamp = date('Y-m-d H:i:s');

        // get the existing server from the DB
        $serverTable = new Model\NMServerTable($this->_config);
        if ($this->params()->fromPost('id')) {
            $server = $serverTable->getById($this->params()->fromPost('id'));
        } else {
            $server = $serverTable->getByName($this->params()->fromPost('name'));
        }

        // define the set of required fields for all servers, blades and vmware vms.
        $requiredFieldsAll = array(
            'name',
            'serverType',

            'businessServiceId',
            'businessServiceName',
            'subsystemId',
            'subsystemName',
            'cmdbEnvironment',

            'subnetMask',
            'gateway',
            'ipAddress',

            'chefServer',
            'chefRole',
            'chefEnv'
        );

        $requiredFieldsBlade = array(
            'location',
            'locationId',
            'distSwitch',
            'vlanName',
            'vlanId',
            'chassisName',
            'chassisId',
            'bladeName',
            'bladeId',
            'bladeSlot',
            'macAddress',

            'cobblerServer',
            'cobblerDistro',
            'cobblerKickstart',
        );

        $requiredFieldsStandalone = array(
            'location',
            'locationId',
            'distSwitch',
            'iLo',

            'cobblerServer',
            'cobblerDistro',
            'cobblerKickstart',
        );

        $requiredFieldsRemote = array(
            'location',
            'locationId',
            'iLo',
        );

        $requiredFieldsVMWare = array(
            'vlanName',
            'vlanId',
            'vSphereSite',
            'vSphereServer',
            'dcName',
            'dcUid',
            'ccrName',
            'ccrUid',
            'rpUid',

            'vmSize',
            'numCPUs',
            'memoryGB',
            'luns',

            'cobblerServer',
            'cobblerDistro',
            'cobblerKickstart',
        );

        // not sure we're going to use oldName but we have to come up with a way to check for
        // changes and update data sources appropriately, eg, DNS, LDAP, etc
        if (strtolower($this->params()->fromPost('name')) != $server->getName() && $server->getName()) {
            $server->setOldName($server->getName());
        }

        $server
            ->setName(strtolower($this->params()->fromPost('name')))
            ->setServerType($serverType)

            ->setBusinessServiceId($this->params()->fromPost('businessServiceId'))
            ->setBusinessServiceName($this->params()->fromPost('businessServiceName'))
            ->setSubsystemId($this->params()->fromPost('subsystemId'))
            ->setSubsystemName($this->params()->fromPost('subsystemName'))
            ->setCmdbEnvironment($this->params()->fromPost('cmdbEnvironment'))

            ->setDescription($this->params()->fromPost('description'))

            ->setLocation($this->params()->fromPost('location'))
            ->setLocationId($this->params()->fromPost('locationId'))

            ->setNetwork($this->params()->fromPost('network'))
            ->setSubnetMask($this->params()->fromPost('subnetMask'))
            ->setGateway($this->params()->fromPost('gateway'))
            ->setMacAddress($this->params()->fromPost('macAddress'))
            ->setIpAddress($this->params()->fromPost('ipAddress'))

            ->setCobblerServer($this->params()->fromPost('cobblerServer'))
            ->setCobblerDistro($this->params()->fromPost('cobblerDistro'))
            ->setCobblerKickstart($this->params()->fromPost('cobblerKickstart'))
            ->setCobblerMetadata($this->params()->fromPost('cobblerMetadata'))

            ->setRemoteServer($this->params()->fromPost('remoteServer'))

            ->setChefServer($this->params()->fromPost('chefServer'))
            ->setChefRole($this->params()->fromPost('chefRole'))
            ->setChefEnv($this->params()->fromPost('chefEnv'));

		

        if ($server->getId()) {
            // update
            $server
                ->setDateUpdated($timestamp)
                ->setUserUpdated($this->_user->getUsername());
            $server = $serverTable->update($server);
        } else {
            // create
            $server
                ->setOwnerId($this->_user->getId())
                ->setDateCreated($timestamp)
                ->setUserCreated($this->_user->getUsername())
                ->setDateUpdated($timestamp)
                ->setUserUpdated($this->_user->getUsername());
            $server = $serverTable->create($server);

            // this is a new server and from the server pool, so start the lease time running
            // if we don't already have a lease
            if ($this->params()->fromPost('serverPoolId')) {
                $nmLeaseTable = new Model\NMLeaseTable($this->_config);
                $nmLease      = $nmLeaseTable->getByServerId($server->getId());
                if (!$nmLease->getId()) {
                    $nmLease->setServerId($server->getId())
                            ->setLeaseStart($timestamp)
                            ->setLeaseDuration($this->_config['vmLease']['leaseTimeInDays'])
                            ->setExtensionInDays($this->_config['vmLease']['extensionInDays'])
                            ->setNumExtensionsAllowed($this->_config['vmLease']['numExtensionsAllowed'])
                            ->setNumTimesExtended(0)
                            ->setExpired(0);
                    $nmLeaseTable->create($nmLease);
                }
            }
        }

        switch ($server->getServerType()) {
            case 'standalone':
                $standaloneTable = new Model\NMStandaloneTable($this->_config);
                $standalone      = $standaloneTable->getByServerId($server->getId());
                $standalone
                    ->setServerId($server->getId())
                    ->setRemote(0)
                    ->setILo($this->params()->fromPost('iLo'))
                    ->setIso($this->params()->fromPost('iso'))
                    ->setDistSwitch($this->params()->fromPost('distSwitch'))
                    ->setVlanName($this->params()->fromPost('vlanName'))
                    ->setVlanId($this->params()->fromPost('vlanId'));
                $standaloneTable->save($standalone);
                break;
            case 'remote':
                $standaloneTable = new Model\NMStandaloneTable($this->_config);
                $standalone      = $standaloneTable->getByServerId($server->getId());
                $standalone
                    ->setServerId($server->getId())
                    ->setRemote(1)
                    ->setILo($this->params()->fromPost('iLo'))
                    ->setIso($this->params()->fromPost('iso'));
                $standaloneTable->save($standalone);
                break;
            case 'blade':
                $bladeTable = new Model\NMBladeTable($this->_config);
                $blade      = $bladeTable->getByServerId($server->getId());
                $blade
                    ->setServerId($server->getId())
                    ->setDistSwitch($this->params()->fromPost('distSwitch'))
                    ->setChassisName($this->params()->fromPost('chassisName'))
                    ->setChassisId($this->params()->fromPost('chassisId'))
                    ->setBladeName($this->params()->fromPost('bladeName'))
                    ->setBladeId($this->params()->fromPost('bladeId'))
                    ->setBladeSlot($this->params()->fromPost('bladeSlot'))

                    ->setVlanName($this->params()->fromPost('vlanName'))
                    ->setVlanId($this->params()->fromPost('vlanId'));
                $bladeTable->save($blade);
                break;
            
            case 'vmwareCobbler':
                $vmwareTable = new Model\NMVMWareTable($this->_config);
                $vmware      = $vmwareTable->getByServerId($server->getId());
                $vmware
                    ->setServerId($server->getId())
                    ->setVSphereSite($this->params()->fromPost('vSphereSite'))
                    ->setVSphereServer($this->params()->fromPost('vSphereServer'))
                    ->setDcName($this->params()->fromPost('dcName'))
                    ->setDcUid($this->params()->fromPost('dcUid'))
                    ->setCcrName($this->params()->fromPost('ccrName'))
                    ->setCcrUid($this->params()->fromPost('ccrUid'))
                    ->setRpUid($this->params()->fromPost('rpUid'))
                    ->setVmSize($this->params()->fromPost('vmSize'))
                    ->setNumCPUs($this->params()->fromPost('numCPUs'))
                    ->setMemoryGB($this->params()->fromPost('memoryGB'))
                    ->setVlanName($this->params()->fromPost('vlanName'))
                    ->setVlanId($this->params()->fromPost('vlanId'));
                $vmwareTable->save($vmware);

                $storageTable = new Model\NMStorageTable($this->_config);

                // get an array of existing luns and then create a hash by id
                $dbLuns     = $storageTable->getByServerId($server->getId());
                $dbLunsHash = array();
                foreach ($dbLuns as $dbLun) {
                    $dbLunsHash[$dbLun->getId()] = $dbLun;
                }

                // here are the luns from the POST
                $luns = json_decode($this->params()->fromPost('luns'));
                // loop over each
                foreach ($luns as $lun) {
                    if (property_exists($lun, 'id') && $lun->id) {
                        //update
                        $existing = $storageTable->getById($lun->id);
                        if ($existing->getId()) {
                            $existing->setLunSizeGb($lun->lunSizeGb);
                            $storageTable->update($existing);
                        }
                        // remove from our hash so that we can delete any no longer used
                        if (array_key_exists($lun->id, $dbLunsHash)) {
                            unset($dbLunsHash[$lun->id]);
                        }
                    } else {
                        // create
                        $new = new Model\NMStorage();
                        $new->setServerId($server->getId())
                            ->setLunSizeGb($lun->lunSizeGb);
                        $storageTable->create($new);
                    }
                }
                // now, delete any luns remaining in our hash as they are no longer used
                /** @var $lun Model\NMStorage */
                /** @noinspection PhpUnusedLocalVariableInspection */
                foreach ($dbLunsHash as $id => $lun) {
                    $storageTable->delete($lun);
                }
                break;
           
            case 'vmware':
                $vmwareTable = new Model\NMVMWareTable($this->_config);
                $vmware      = $vmwareTable->getByServerId($server->getId());
				
				$templateName = $this->params()->fromPost('templateName');
				$templateId = $this->params()->fromPost('templateId');
				
				
				
				if($templateName == null || $templateName == "" || $templateId == null || $templateId == ""){
						
						$dcName = $this->params()->fromPost('dcName');
						$vSphereSite = $this->params()->fromPost('vSphereSite');
						$getTemplateListResult = json_decode($this->curlGetUrl("https://{$this->neumaticServer}/vmware/getTemplateList?vSphereSite=".$vSphereSite."&dcName=".$dcName));
						
						$templateList = $getTemplateListResult['templates'];
						
						$defaultTemplate = $this->_config['vmDefaults']['template'];
						if($templateId != null AND $templateId != ""){
							//set the template name from the ID
							
							foreach($templateList AS $template){
								if ($template['id'] == $templateId){
									$templateName = $template['name'];
								}
							}	
						}else{
							$templateName == $defaultTemplate;
							foreach($templateList AS $template){
								if ($template['name'] == $templateName){
									$templateId = $template['id'];
								}
							}
						}
				}
				
				
				
                $vmware
                    ->setServerId($server->getId())
                    ->setVSphereSite($this->params()->fromPost('vSphereSite'))
                    ->setVSphereServer($this->params()->fromPost('vSphereServer'))
                    ->setDcName($this->params()->fromPost('dcName'))
                    ->setDcUid($this->params()->fromPost('dcUid'))
                    ->setCcrName($this->params()->fromPost('ccrName'))
                    ->setCcrUid($this->params()->fromPost('ccrUid'))
                    ->setRpUid($this->params()->fromPost('rpUid'))
                    ->setVmSize($this->params()->fromPost('vmSize'))
                    ->setNumCPUs($this->params()->fromPost('numCPUs'))
                    ->setMemoryGB($this->params()->fromPost('memoryGB'))
                    ->setVlanName($this->params()->fromPost('vlanName'))
                    ->setVlanId($this->params()->fromPost('vlanId'))
                    ->setTemplateId($this->params()->fromPost('templateId'))
					->setTemplateName($this->params()->fromPost('templateName'));
                $vmwareTable->save($vmware);
				
				if($templateName = $this->params()->fromPost('templateName')){
					$tnexp = explode('-', $templateName); 
					$distro = $tnexp[0]."-".$tnexp[1];
					$server->setCobblerDistro($distro);
				
				}
				
                $storageTable = new Model\NMStorageTable($this->_config);

                // get an array of existing luns and then create a hash by id
                $dbLuns     = $storageTable->getByServerId($server->getId());
                $dbLunsHash = array();
                foreach ($dbLuns as $dbLun) {
                    $dbLunsHash[$dbLun->getId()] = $dbLun;
                }

                // here are the luns from the POST
                $luns = json_decode($this->params()->fromPost('luns'));
                // loop over each
                foreach ($luns as $lun) {
                    if (property_exists($lun, 'id') && $lun->id) {
                        //update
                        $existing = $storageTable->getById($lun->id);
                        if ($existing->getId()) {
                            $existing->setLunSizeGb($lun->lunSizeGb);
                            $storageTable->update($existing);
                        }
                        // remove from our hash so that we can delete any no longer used
                        if (array_key_exists($lun->id, $dbLunsHash)) {
                            unset($dbLunsHash[$lun->id]);
                        }
                    } else {
                        // create
                        $new = new Model\NMStorage();
                        $new->setServerId($server->getId())
                            ->setLunSizeGb($lun->lunSizeGb);
                        $storageTable->create($new);
                    }
                }
                // now, delete any luns remaining in our hash as they are no longer used
                /** @var $lun Model\NMStorage */
                /** @noinspection PhpUnusedLocalVariableInspection */
                foreach ($dbLunsHash as $id => $lun) {
                    $storageTable->delete($lun);
                }
                break;
            default:
                return $this->renderView(array(
                                             "success"   => 0,
                                             "error"     => "Unknown server type: {$server->getServerType()}",
                                             "logLevel"  => Logger::ERR,
                                             "logOutput" => "Unknown server type: {$server->getServerType()}"
                                         ));
        }

        // this is from the server pool, so update the the server_pool table record
        if ($this->params()->fromPost('serverPoolId')) {
            $serverPoolTable = new Model\NMServerPoolTable($this->_config);
            $serverPool      = $serverPoolTable->getById($this->params()->fromPost('serverPoolId'));
            $serverPool
                ->setServerId($server->getId())
                ->setUserId($this->_user->getId())
                ->setState('Used');
            $serverPoolTable->update($serverPool);
        }

        // check to see if all required fields are present. If so, mark okToBuild as true
        // this will enable the Build button on the systems view
        $requiredFields = array();
        if ($serverType == "vmware" || $serverType == "vmwareCobbler") {
            $requiredFields = array_merge($requiredFieldsAll, $requiredFieldsVMWare);
        } else if ($serverType == "standalone") {
            $requiredFields = array_merge($requiredFieldsAll, $requiredFieldsStandalone);
        } else if ($serverType == "blade") {
            $requiredFields = array_merge($requiredFieldsAll, $requiredFieldsBlade);
        } else if ($serverType == "remote") {
            $requiredFields = array_merge($requiredFieldsAll, $requiredFieldsRemote);
        }
        $server->setOkToBuild(true);
        $missingFields = "";
        foreach ($requiredFields as $field) {
            if (!$this->params()->fromPost($field)) {
                $server->setOkToBuild(false);
                $missingFields .= $field . ", ";
            }
        }
        $serverTable->update($server);

        // update the usergroup and hostgroup tables

        // add all user groups to the server
        $ugTable = new Model\NMUsergroupTable($this->_config);
        // start with a clean slate. Easier than adding logic here
        $ugTable->removeServerAllGroups($server->getId());
        // insure we have the default user groups on this host
        $userGroupNames = $this->params()->fromPost('ldapUserGroups');
        $groupsDiff = array_diff($this->_config['ldap']['requiredUserGroups'], $userGroupNames);
        $userGroupNames = array_merge($userGroupNames, $groupsDiff);

        // add them to the server
        foreach ($userGroupNames as $name) {
            $userGroup = $ugTable->getByName($name);
            $ugTable->addServerToGroup($server->getId(), $userGroup->getId());
        }

        // add all host groups to server
        $hgTable = new Model\NMHostgroupTable($this->_config);
        // start with a clean slate
        $hgTable->removeServerAllGroups($server->getId());
        // insure we have the default host groups included
        $hostGroupNames = $this->params()->fromPost('ldapHostGroups');
        if (!$hostGroupNames) $hostGroupNames = array();
        $groupsDiff = array_diff($this->_config['ldap']['requiredHostGroups'], $hostGroupNames);
        $hostGroupNames = array_merge($hostGroupNames, $groupsDiff);

        // add them to the server
        foreach ($hostGroupNames as $name) {
            $hostGroup = $hgTable->getByName($name);
            $hgTable->addServerToGroup($server->getId(), $hostGroup->getId());
        }


        return $this->renderView(array(
                                     "success"    => true,
                                     "server"     => $server->toObject(),
                                     "missingFields" => $missingFields,
                                     "logLevel"   => Logger::NOTICE,
                                     "logOutput"  => "{$server->getName()} has been saved.",
                                     "parameters" => "[serverName: {$server->getName()}]"
                                 ));
    }


    /**
     * Archive server by setting the archive column value to 1
     *
     * @return JsonModel
     */
    public function archiveServerAction() {
        $serverId    = $this->params()->fromRoute('param1');
        $serverTable = new Model\NMServerTable($this->_config);
        $server      = $serverTable->getById($serverId);
        $server->setArchived(1);
        $serverTable->update($server);
        return $this->renderView(array(
                                     "success"    => true,
                                     "logLevel"   => Logger::NOTICE,
                                     "logOutput"  => $server->getName() . " has been archived",
                                     "parameters" => "[serverName: {$server->getName()}]"
                                 ));
    }

    /**
     * Unarchive server by setting the archive column value to 0
     *
     * @return JsonModel
     */
    public function unarchiveServerAction() {
        $serverId    = $this->params()->fromRoute('param1');
        $serverTable = new Model\NMServerTable($this->_config);
        $server      = $serverTable->getById($serverId);
        $server->setArchived(0);
        $serverTable->update($server);
        return $this->renderView(array(
                                     "success"    => true,
                                     "logLevel"   => Logger::NOTICE,
                                     "logOutput"  => $server->getName() . " has been unarchived",
                                     "parameters" => "[serverName: {$server->getName()}]"
                                 ));
    }

    /**
     * Get the list of current ratings.
     *
     * @return JsonModel
     */
    public function getRatingsAction() {
        $ratingTable = new Model\NMRatingTable($this->_config);
        $ratings     = $ratingTable->getUserRatings();
        for ($i = 0; $i < count($ratings); $i++) {
            $ratings[$i]['dateRated'] = date('F j, Y', strtotime($ratings[$i]['dateRated']));
        }
        return $this->renderView(array(
                                     "success"   => true,
                                     "ratings"   => $ratings,
                                     "logLevel"  => Logger::INFO,
                                     "logOutput" => "User ratings returned"
                                 ));
    }

    /**
     * Save the user's rating and comments
     *
     * @return JsonModel
     */
    public function saveRatingAction() {
        $userId   = $this->params()->fromPost('userId');
        $rating   = $this->params()->fromPost('rating');
        $comments = $this->params()->fromPost('comments');

        $ratingTable = new Model\NMRatingTable($this->_config);
        $nmRating    = $ratingTable->getByUserId($userId);
        $nmRating
            ->setUserId($userId)
            ->setRating($rating)
            ->setComments($comments)
            ->setDateRated(date('Y-m-d H:i:s'));
        if ($nmRating->getId()) {
            $ratingTable->update($nmRating);
        } else {
            $ratingTable->create($nmRating);
        }
        return $this->renderView(array(
                                     "success"   => true,
                                     "logLevel"  => Logger::INFO,
                                     "logOutput" => "User " . $this->_user->getUsername() . "'s ratings have been saved"
                                 ));
    }

    /**
     * @return \Zend\View\Model\JsonModel
     */
    public function getAllowedChefServersAction() {
        // username is currently not being used since the /chef/getUser does not support it...yet
        // $userName = $this->params()->fromRoute('param1');

        // get the user to see if he has admin rights
        // get a list of Chef servers
        $results     = $this->curlGetUrl("https://{$this->neumaticServer}/chef/getServers");
        $chefServers = $results->servers;
        $this->writeLog(array("logLevel" => Logger::DEBUG, "logOutput" => "got " . count($chefServers) . " Chef servers"));

        // loop thru all servers and check for this user's account exists
        for ($i = 0; $i < count($chefServers); $i++) {
            $json = $this->curlGetUrl("https://{$this->neumaticServer}/chef/getUser/" . $this->_user->getUsername() . "?chef_server=" . $chefServers[$i]->name);
            if ($json->success) {
                $chefServers[$i]->isUser = true;
            } else {
                $chefServers[$i]->isUser = false;
            }
        }
        return $this->renderView(array(
                                     "success"     => true,
                                     "chefServers" => $chefServers,
                                     "logLevel"    => Logger::INFO,
                                     "logOutput"   => count($chefServers) . " have been returned"
                                 ));

    }

    public function getConsoleLogAction() {
        $serverId    = $this->params()->fromRoute('param1');
        $serverTable = new Model\NMServerTable($this->_config);
        $server      = $serverTable->getById($serverId);

        $logDir = __DIR__ . "/../../../../../watcher_log";
        $consoleLogFile = $logDir . "/console.log." . $server->getName();
        $log = file_get_contents($consoleLogFile);

        return $this->renderView(array("success" => true, "log" => $log));
    }
    /**
     * @return \Zend\View\Model\JsonModel
     */
    public function getChefLogAction() {
        $serverId    = $this->params()->fromRoute('param1');
        $serverTable = new Model\NMServerTable($this->_config);
        $server      = $serverTable->getById($serverId);

        $data = array(
            "initialRun" => array('date' => 'File Not Found', 'log' => ''),
            "clientLog"  => array('date' => 'File Not Found', 'log' => ''),
            "stackTrace" => array('date' => 'File Not Found', 'log' => ''),
        );

        $initialRunLogFile = '/var/log/chef-initial-run.log';
        $clientLogFile = '/var/log/chef/client.log';
        $stackTraceLogFile = '/var/chef/cache/chef-stacktrace.out';

        $ssh = null;
        try {
            $ssh = new SSH2($server->getName());
        } catch (\Exception $e) {
            return $this->renderView(array(
                "success" => false,
                "error" => "Unable to connect to {$server->getName()}",
                "logLevel" => Logger::ERR,
                "logOutput" => "Unable to connect to {$server->getName()}"
            ));
        }
        try {
            $ssh->loginWithPassword('root', $this->_config['rootPassword']);
        } catch (\ErrorException $e) {
            return $this->renderView(array(
                "success" => false,
                "error" => "Unable to login to {$server->getName()}",
                "logLevel" => Logger::ERR,
                "logOutput" => "Unable to login to {$server->getName()}"
            ));
        }

        $ssh2 = null;
        try {
            $ssh2 = new SSH2($server->getName());
        } catch (\Exception $e) {
            return $this->renderView(array(
                "success" => false,
                "error" => "Unable to connect to {$server->getName()}",
                "logLevel" => Logger::ERR,
                "logOutput" => "Unable to connect to {$server->getName()}"
            ));
        }
        try {
            $ssh2->loginWithPassword('root', $this->_config['rootPassword']);
        } catch (\ErrorException $e) {
            return $this->renderView(array(
                "success" => false,
                "error" => "Unable to login to {$server->getName()}",
                "logLevel" => Logger::ERR,
                "logOutput" => "Unable to login to {$server->getName()}"
            ));
        }

        // get the shell and wait for the prompt
        $ssh->getShell(false, 'vt102', Array(), 4096);
        $prompt = ']# ';
        $buffer = '';
        $ssh->waitPrompt($prompt, $buffer, 2);

        // chef for the existence of initial run log file
        $buffer = '';
        $ssh->writePrompt("stat -c '%Z' {$initialRunLogFile}\n");
        $ssh->waitPrompt($prompt, $buffer, 2);
        if (!preg_match("/No such file/", $buffer)) {
            // need to get just the output of the command and not the command itself
            $a = explode("\n", $buffer);
            $date = $a[1];
            $data['initialRun']['date'] = date('Y-m-d h:i', rtrim($date));

            // get the contents of the log file
            // waitPrompt was not working. not providing the entire file so changed to sftp
            $sftp = ssh2_sftp($ssh2->getResource());
            $data['initialRun']['log'] = file_get_contents("ssh2.sftp://" . $sftp . $initialRunLogFile);
            $ssh2->closeStream();
        }

        // check for client log
        $buffer = '';
        $ssh->writePrompt("stat -c '%Z' {$clientLogFile}\n");
        $ssh->waitPrompt($prompt, $buffer, 2);
        if (!preg_match("/No such file/", $buffer)) {
            // need to get just the output of the command and not the command itself
            $a = explode("\n", $buffer);
            $date = $a[1];
            $data['clientLog']['date'] = date('Y-m-d h:i', rtrim($date));

            // get the contents of the log file
            // waitPrompt was not working. not providing the entire file so changed to sftp
            $sftp = ssh2_sftp($ssh2->getResource());
            $data['clientLog']['log'] = file_get_contents("ssh2.sftp://" . $sftp . $clientLogFile);
            $ssh2->closeStream();
        }

        // check the stack trace log
        $buffer = '';
        $ssh->writePrompt("stat -c '%Z' {$stackTraceLogFile}\n");
        $ssh->waitPrompt($prompt, $buffer, 2);
        if (!preg_match("/No such file/", $buffer)) {
            // need to get just the output of the command and not the command itself
            $a = explode("\n", $buffer);
            $date = $a[1];
            $data['stackTrace']['date'] = date('Y-m-d h:i', rtrim($date));

            // get the contents of the log file
            // waitPrompt was not working. not providing the entire file so changed to sftp
            $sftp = ssh2_sftp($ssh2->getResource());
            $data['stackTrace']['log'] = file_get_contents("ssh2.sftp://" . $sftp . $stackTraceLogFile);
            $ssh2->closeStream();
        }

        $ssh->closeStream();
        $ssh2->closeStream();
        return $this->renderView(array(
            "success" => true,
            "data" => $data
        ));
    }

    /**
     * @return \Zend\View\Model\JsonModel
     */
    public function getAuditLogEntriesAction() {
        $serverId    = $this->params()->fromRoute('param1');
        $serverTable = new Model\NMServerTable($this->_config);
        $server      = $serverTable->getById($serverId);

        $auditTable = new Model\NMAuditTable($this->_config);
        $results = $auditTable->getByHostName($server->getName());

        $logEntries = array();
        foreach ($results as $r) {
            $logEntries[] = $r->toObject();
        }
        return $this->renderView(array(
            "success" => true,
            "logLevel" => Logger::DEBUG,
            "logOutput" => "Audit log entries retrieved for " . $server->getName(),
            "serverName" => $server->getName(),
            "logEntries" => $logEntries
        ));
    }

    /**
     * @return \Zend\View\Model\JsonModel
     */
    public function getBuildMetricsAction() {
        $serverTable  = new Model\NMServerTable($this->_config);
        $buildMetrics = $serverTable->getBuildMetrics();
        $avgBuildTimes = $serverTable->getAverageBuildTimes();

        return $this->renderView(array(
            "success" => true,
            "logLevel" => Logger::DEBUG,
            "logOutput" => "",
            "buildMetrics" => $buildMetrics,
            "avgBuildTimes" => $avgBuildTimes
        ));
    }

    public function getDistSwitchesAction() {
        $distSwitchTable = new Model\NMDistSwitchTable($this->_config);
        $results = $distSwitchTable->getAllEnabled();
        $distSwitches = array();
        foreach ($results as $r) {
            $distSwitches[] = $r->toObject();
        }
        return $this->renderView(array(
            "success" => true,
            "logLevel" => Logger::DEBUG,
            "logOutput" => count($distSwitches) . " distribution switches returned",
            "distSwitches" => $distSwitches
        ));
    }

    private function getBusinessServicesByDistSwitchIdAndVlanId($distSwitchId, $vlanId) {
        $vlanBSTable = new Model\NMVLANBusinessServiceTable($this->_config);
        $results = $vlanBSTable->getByDistSwitchIdAndVlanId($distSwitchId, $vlanId);
        $vlanBusinessServices = array();
        foreach ($results as $r) {
            $vlanBusinessServices[] = $r->toObject();
        }
        return $vlanBusinessServices;
    }

    public function getVlansByDistSwitchIdAction() {
        $distSwitchId = $this->params()->fromRoute('param1');

        $vlanTable = new Model\NMVLANTable($this->_config);
        $results = $vlanTable->getAllByDistSwitchId($distSwitchId);
        $vlans = array();
        foreach ($results as $r) {
            $businessServices = $this->getBusinessServicesByDistSwitchIdAndVlanId($distSwitchId, $r->getVlanId());
            $vlan = $r->toObject();
            $vlan->businessServices = $businessServices;
            $vlans[] = $vlan;
        }
        return $this->renderView(array(
            "success" => true,
            "logLevel" => Logger::DEBUG,
            "logOutput" => count($vlans) . " VLANs returned",
            "vlans" => $vlans
        ));
    }

    public function saveVlanAction() {
        $vlanTable = new Model\NMVLANTable($this->_config);
        $distSwitchTable = new Model\NMDistSwitchTable($this->_config);
        $bsTable = new Model\NMVLANBusinessServiceTable($this->_config);
        $vlan = new Model\NMVLAN();

        $distSwitchId = $this->params()->fromPost('distSwitchId');
        $vlan
            ->setId($this->params()->fromPost('id'))
            ->setDistSwitchId($distSwitchId)
            ->setVlanId($this->params()->fromPost('vlanId'))
            ->setName($this->params()->fromPost('name'))
            ->setNetwork($this->params()->fromPost('network'))
            ->setNetmask($this->params()->fromPost('netmask'))
            ->setGateway($this->params()->fromPost('gateway'))
            ->setEnabled($this->params()->fromPost('enabled'));
        $vlanTable->update($vlan);

        $results     = $bsTable->getByDistSwitchIdAndVlanId($distSwitchId, $vlan->getVlanId());
        $bsHash = array();
        foreach ($results as $r) {
            $bsHash[$r->getId()] = $r;
        }

        $bss = json_decode($this->params()->fromPost('businessServices'));
        foreach ($bss as $bs) {
            if (property_exists($bs, 'id') && $bs->id) {
                // update
                $existing = $bsTable->getById($bs->id);
                if ($existing->getId()) {
                    $existing
                        ->setName($bs->name)
                        ->setEnvironment($bs->environment)
                        ->setSysId($bs->sysId);
                    $bsTable->update($existing);
                }
                if (array_key_exists($bs->id, $bsHash)) {
                    unset($bsHash[$bs->id]);
                }
            } else {
                // create
                $new = new Model\NMVLANBusinessService();
                $new
                    ->setDistSwitchId($distSwitchId)
                    ->setVlanId($vlan->getVlanId())
                    ->setName($bs->name)
                    ->setEnvironment($bs->environment)
                    ->setSysId($bs->sysId);
                $bsTable->create($new);
            }
        }
        // now, delete any luns remaining in our hash as they are no longer used
        /** @noinspection PhpUnusedLocalVariableInspection */
        foreach ($bsHash as $id => $bs) {
            $bsTable->delete($bs);
        }

        $distSwitch = $distSwitchTable->getById($vlan->getDistSwitchId());
        return $this->renderView(array(
            "success" => true,
            "logLevel" => Logger::NOTICE,
            "logOutput" => "VLAN " . $vlan->getId() . "(" . $distSwitch->getModel() . ") updated. " . $vlan . "\n",
            "vlan" => $vlan->toObject()
        ));
    }

    public function saveDescriptionAction() {
        $serverTable = new Model\NMServerTable($this->_config);
        $server = $serverTable->getById($this->params()->fromPost('id'));
        $server->setDescription($this->params()->fromPost('description'));
        $serverTable->update($server);

        return $this->renderView(array(
            "success" => true,
            "logLevel" => Logger::NOTICE,
            "logOutput" => "Server " . $server->getName() . " description updated to " . $server->getDescription() . "\n"
        ));
    }

    public function getBusinessServicesByVMClusterAction() {
        $dcUid = $this->params()->fromRoute('param1');
        if (!$dcUid) $dcUid = 'datacenter-449';
        $ccrUid = $this->params()->fromRoute('param2');
        if (!$ccrUid) $ccrUid = 'domain-c458';
        $vSphereSite = 'prod';

        // get the host systems for this cluster
        $this->defineVSphereServer($vSphereSite);
        $hostSystems = $this->getVMwareHostSystemsByClusterComputeResource($ccrUid);

        // instantiate vm, server and storage tables
        $vmwareTable = new Model\NMVMWareTable($this->_config);
        $serverTable = new Model\NMServerTable($this->_config);
        $storageTable = new Model\NMStorageTable($this->_config);
        $quotaTable = new Model\NMQuotaTable($this->_config);

        // get all the VMs in this cluster
        $nmVms = $vmwareTable->getByCcrUid($ccrUid);

        // total resources
        $totals = array(
            "cpusTotal" => 0,
            "memoryTotal" => 0,
            "storageTotal" => 0
        );

        // sum up the total cpu and mem for this cluster
        // TODO: Will need to get storage as well
        foreach ($hostSystems as $hs) {
            $totals['cpusTotal'] += $hs['numCpuCores'];
            $totals['memoryTotal'] += $hs['memorySizeGB'];
        }

        // resources by business service
        $businessServices = array();

        foreach ($nmVms as $nmVm) {
            $server = $serverTable->getById($nmVm->getServerId());
            $quota = $quotaTable->getByDcUidCcrUidAndBusinessServiceId($dcUid, $ccrUid, $server->getBusinessServiceId());

            $luns = $storageTable->getByServerId($server->getId());

            $totalGBs = 0;
            foreach ($luns as $lun) {
                $totalGBs += $lun->getLunSizeGb();
            }

            if (!array_key_exists($server->getBusinessServiceName(), $businessServices)) {
                $businessServices[$server->getBusinessServiceName()] = array(
                    "name" => $server->getBusinessServiceName(),
                    "sysId" => $server->getBusinessServiceId(),
                    "quotaId" => $quota->getId(),
                    "cpusUsed" => 0,
                    "cpusQuota" => $quota->getCpus(),
                    "memoryUsed" => 0,
                    "memoryQuota" => $quota->getMemoryGB(),
                    "storageUsed" => 0,
                    "storageQuota" => $quota->getStorageGB()
                );
            }
            $businessServices[$server->getBusinessServiceName()]['cpusUsed'] += $nmVm->getNumCPUs();
            $businessServices[$server->getBusinessServiceName()]['memoryUsed'] += $nmVm->getMemoryGB();
            $businessServices[$server->getBusinessServiceName()]['storageUsed'] += $totalGBs;
        }

        $bsArray = array();
        foreach ($businessServices as $bs) {
            $bsArray[] = $bs;
        }

        return $this->renderView(array(
            "success"   => true,
            "vSphereSite" => $vSphereSite,
            "dcUid" => $dcUid,
            "ccrUid" => $ccrUid,
            "totals" => $totals,
            "businessServices" => $bsArray,
            "hostSystems" => $hostSystems,
            "logLevel"  => Logger::DEBUG,
            "logOutput" => count($hostSystems) . " host systems returned"
        ));

    }

    public function saveQuotaAction() {
        $quotaId = $this->params()->fromPost('quotaId');

        // get the quota table entry, if exists
        $quotaTable = new Model\NMQuotaTable($this->_config);

        if ($quotaId) {
            $quota = $quotaTable->getById($quotaId);
        } else {
            $quota = new Model\NMQuota();
        }

        $quota
            ->setDcUid($this->params()->fromPost('dcUid'))
            ->setCcrUid($this->params()->fromPost('ccrUid'))
            ->setBusinessServiceId($this->params()->fromPost('bsSysId'))
            ->setBusinessServiceName($this->params()->fromPost('bsName'))
            ->setCpus(intval($this->params()->fromPost('cpusQuota')))
            ->setMemoryGB(intval($this->params()->fromPost('memoryQuota')))
            ->setStorageGB(intval($this->params()->fromPost('storageQuota')));

        if ($quota->getId()) {
            $quotaTable->update($quota);
        } else {
            $quotaTable->create($quota);
        }

        return $this->renderView(array(
            "success"   => true,
            "logLevel"  => Logger::INFO,
            "logOutput" => "Quota updated for " . $quota->getBusinessServiceName()
        ));
    }

    public function getISOsAction() {
        $html = file_get_html('http://mirrors.va.neustar.com/ISOs');

        $isos = array();
        foreach ($html->find('tr') as $tr) {
            $img = $tr->find('img');
            if (is_array($img) && count($img) > 0 && is_object($img[0]) && $img[0]->getAttribute('src') != "/icons/unknown.gif") {
                continue;
            }
            foreach($tr->find('a') as $a) {
                if (strpos($a->plaintext, "6.5") !== false || $this->_user->getUserType() == "Admin") {
                    $isos[] = $a->plaintext;
                };
            }
        }
        return $this->renderView(array(
            "success" => true,
            "logLevel" => Logger::DEBUG,
            "logOutput" => count($isos) . " ISOs returned",
            "isos" => $isos
        ));
    }

    public function getSelfServiceBusinessServicesAction() {
        $businessServices = $this->_config['selfService']['allowedBusinessServices'];
        return $this->renderView(array(
            "success" => true,
            "logLevel" => Logger::DEBUG,
            "logOutput" => count($businessServices) . " businessServices returned",
            "businessServices" => $businessServices
        ));
    }

    // *****************************************************************************************************************
    // Private methods
    // *****************************************************************************************************************
    /**
     * @param Model\NMServer $server
     * @return bool
     */
    private function stopLogging(Model\NMServer $server) {
        $ok = true;

        $pid = $this->getProcess($server->getId(), $this->cobblerExecFile);
        if ($pid) {
            exec("kill {$pid}");
        } else {
            $ok = false;
        }
        $this->writeLog(array("logLevel" => Logger::DEBUG, "logOutput" => "Cobbler log file is " . $this->cobblerWatcherFile . "." . $server->getName()));
        if (file_exists($this->cobblerWatcherFile . "." . $server->getName())) {
            $this->writeLog(array("logLevel" => Logger::DEBUG, "logOutput" => "Deleting cobbler log file is " . $this->cobblerWatcherFile . "." . $server->getName()));
            unlink($this->cobblerWatcherFile . "." . $server->getName());
        }

        $pid = $this->getProcess($server->getId(), $this->chefExecFile);
        if ($pid) {
            exec("kill {$pid}");
        } else {
            $ok = false;
        }
        if (file_exists($this->chefWatcherFile . "." . $server->getName())) {
            unlink($this->chefWatcherFile . "." . $server->getName());
        }

        $pid = $this->getProcess($server->getId(), $this->consoleExecFile);
        if ($pid) {
            exec("kill {$pid}");
        } else {
            $ok = false;
        }
        if (file_exists($this->consoleWatcherFile . "." . $server->getName())) {
            unlink($this->consoleWatcherFile . "." . $server->getName());
        }

        return $ok;
    }

    /**
     * @param int $serverId
     * @param $execFile
     * @return bool|string
     */
    private function getProcess($serverId, $execFile) {
        $found = exec("ps -ef | grep '" . $execFile . " -i " . $serverId . "' | grep -v grep | awk '{print \$2}'",
                      $pidArray);
        if ($found) {
            return implode(" ", $pidArray);
        }
        return false;
    }

    /**
     * @param $serverId
     * @return Model\NMStorage[]
     */
    private function getServerStorage($serverId) {
        $storageTable = new Model\NMStorageTable($this->_config);
        return $storageTable->getByServerId($serverId);
    }

}
