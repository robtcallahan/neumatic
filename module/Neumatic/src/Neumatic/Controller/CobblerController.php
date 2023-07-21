<?php

namespace Neumatic\Controller;

use Neumatic\Model;

use Zend\View\Model\JsonModel;
use Zend\XmlRpc;
use Zend\Mvc\MvcEvent;
use Zend\Log\Logger;

use STS\Util\SSH2;

class CobblerController extends Base\BaseController {

    private $cobblerConfig;

    private $protocol;
    private $apiPath;

    /** @var XmlRpc\Client $client */
    private $client;
    private $token;
    private $username;
    private $password;

    protected $logDir;
    private $watcherExecDir;
    private $watcherExecFile;
    private $watcherLogFile;

    /**
     * @param MvcEvent $e
     * @return mixed
     */
    public function onDispatch(\Zend\Mvc\MvcEvent $e)
    {
        $this->cobblerConfig = $this->_config['cobblerApi'];

        $this->protocol = $this->cobblerConfig['protocol'];
        $this->apiPath = $this->cobblerConfig['apiPath'];
        $this->username = $this->cobblerConfig['username'];
        $this->password = $this->cobblerConfig['password'];

        $this->watcherExecDir = __DIR__ . "/../../../bin";
        $this->watcherExecFile = "cobbler_watch.php";

        $this->logDir = "/opt/neumatic/watcher_log";
        $this->watcherLogFile = $this->logDir . "/cobbler_watch.log";

        return parent::onDispatch($e);
    }

    /**
     * @return \Zend\View\Model\ViewModel
     */
    public function indexAction() {

        return $this->renderview(array("error"=>"This controller has no output from index. Eventually I would like to display the documentation here."));
    }

    /**
     * Sort an object by its name value
     * Declared as static so that it can be passed in a quoted string to usort
     */
    private static function sortObjByName() {
        return function($a, $b) {
            return strcmp($a['name'], $b['name']);
        };
    }

    /**
     * @return \Zend\View\Model\ViewModel
     */
    public function getServersAction()
    {
        try {
            $servers = $this->getServers();
        } catch (\Exception $e) {
            return $this->renderView(array("false" => true, "message" => $e->getMessage()));
        }
        return $this->renderView(array("success" => true, "servers" => $servers));
    }

    /**
     * @return array
     * @throws \ErrorException
     */
    private function getServers() {
        try {
            $servers = array_keys($this->_config['cobbler']);
        } catch (\Exception $e) {
            throw new \ErrorException($e->getMessage());
        }

        $data = array();
        foreach ($servers as $s) {
            $env = $this->_config['cobbler'][$s]['env'];

            $data[] = array(
                "name" => $s,
                "env"    => $env,
                "displayValue" => "[" . $env . "] " . $s
            );
        }
        return $data;
    }

    /**
     * Get the list of systems from Cobbler
     *
     * @return JsonModel
     */
    public function getSystemsAction() {
        $cobblerServer = $this->params()->fromRoute('param1');
        $this->client = $this->getClient($cobblerServer);

        try {
            $results = $this->client->call("get_systems");
        } catch (\Exception $e) {
            $message = $e->getMessage();
            if (preg_match("/<class 'cobbler.cexceptions.CX'>:'(.*)'/", $message, $m)) {
                $message = $m[1];
            }
            return $this->renderView(array("success" => false, "message" => "Cobbler Error: " . $message));
        }
        return $this->renderview($results);
    }

    /**
     * Get the list of systems and IPs from Cobbler
     *
     * @return JsonModel
     */
    public function getUsedIPsAction() {
        $cobblerServer = $this->params()->fromRoute('param1');
        $this->client = $this->getClient($cobblerServer);

        try {
            $data = $this->getUsedIPs();
        } catch (\ErrorException $e) {
            return $this->renderView(array("success" => false, "message" => "Cobbler Error: " . $e->getMessage()));
        }
        return $this->renderview(array("success" => true, "usedIPs" => $data));
    }

    /**
     * Get the list of profiles from Cobbler
     *
     * @return JsonModel
     */
    public function getProfilesAction() {
        $cobblerServer = $this->params()->fromRoute('param1');

        if (!$profiles = $this->getProfiles($cobblerServer)) {
            return $this->renderView($this->_viewData);
        }
        return $this->renderview(array("success" => true, "profiles" => $profiles));
    }

    private function getProfiles($cobblerServer) {
        // get a list of servers from the config file
        $cobblerServers = $this->_config['cobbler'];

        // make sure we have a valid cobbler server
        if (!array_key_exists($cobblerServer, $cobblerServers)) {
            $this->_viewData = array(
                "success" => false,
                "error" => "Unknown Cobbler server: " . $cobblerServer,
                "logLevel" => Logger::ERR,
                "logOutput" => "Unknown Cobbler server: " . $cobblerServer
            );
            return false;
        }

        $this->client = $this->getClient($cobblerServer);

        try {
            $profiles = $this->client->call("get_profiles");
        } catch (\Exception $e) {
            $message = $e->getMessage();
            if (preg_match("/<class 'cobbler.cexceptions.CX'>:'(.*)'/", $message, $m)) {
                $message = $m[1];
            }
            $this->_viewData = array(
                "success" => false,
                "error" => "Cobbler Error: " . $message,
                "logLevel" => Logger::ERR,
                "logOutput" => "Cobbler Error: " . $message
            );
            return false;
        }

        usort($profiles, self::sortObjByName());
        return $profiles;
    }

    /**
     * Get the list of distributions from Cobbler
     *
     * @return JsonModel
     */
    public function getDistributionsAction() {
        $cobblerServer = $this->params()->fromRoute('param1');

        if (!$distros = $this->getDistributions($cobblerServer)) {
            return $this->renderView($this->_viewData);
        }

        if (!$profiles = $this->getProfiles($cobblerServer)) {
            return $this->renderView($this->_viewData);
        }

        $pHash = array();
        foreach($profiles as $p) {
            $pHash[$p['name']] = $p;
        }

        $returnData = array();
        foreach ($distros as $d) {
            if (array_key_exists($d['name'], $pHash)) {
                $returnData[] = $d;
            }
        }
        return $this->renderview(array("success" => true, "distros" => $returnData));
    }

    /**
     * @param $cobblerServer
     * @return array|bool
     */
    private function getDistributions($cobblerServer) {
        // get a list of servers from the config file
        $cobblerServers = $this->_config['cobbler'];

        // make sure we have a valid cobbler server
        if (!array_key_exists($cobblerServer, $cobblerServers)) {
            $this->_viewData = array(
                "success" => false,
                "error" => "Unknown Cobbler server: " . $cobblerServer,
                "logLevel" => Logger::ERR,
                "logOutput" => "Unknown Cobbler server: " . $cobblerServer
            );
            return false;
        }

        // get the distros from the cobbler server
        $this->client = $this->getClient($cobblerServer);

        try {
            $results = $this->client->call("get_distros");
        } catch (\Exception $e) {
            $message = $e->getMessage();
            if (preg_match("/<class 'cobbler.cexceptions.CX'>:'(.*)'/", $message, $m)) {
                $message = $m[1];
            }
            $this->_viewData = array(
                "success" => false,
                "error" => "Cobbler Error: " . $message,
                "logLevel" => Logger::ERR,
                "logOutput" => "Cobbler Error: " . $message
            );
            return false;
        }

        usort($results, self::sortObjByName());

        $distros = array();

        // if this is not the lab cobbler server, only show the list of distros from the config file
        if ($cobblerServers[$cobblerServer]['env'] != 'Lab') {
            $allowedDistros = $this->_config['prodCobblerDistros'];
            $allowedHash = array();
            foreach ($allowedDistros as $distro) {
                $allowedHash[$distro['name']] = 1;
            }

            foreach ($results as $result) {
                if (array_key_exists($result['name'], $allowedHash)) {
                    $distros[] = array("name" => $result['name'], "warn" => false);
                }
            }
        } else {
            $allowedDistros = $this->_config['labCobblerDistros'];
            $allowedHash = array();
            foreach ($allowedDistros as $distro) {
                $allowedHash[$distro['name']] = 1;
            }

            if ($this->_user->getUserType() == 'Admin'
                && array_key_exists('userAdminOn', $_COOKIE)
                && $_COOKIE['userAdminOn'] == "true") {
                $allowAllDistros = true;
            } else {
                $allowAllDistros = false;
            }

            foreach ($results as $result) {
                if (array_key_exists($result['name'], $allowedHash) || $allowAllDistros) {
                    $distros[] = array("name" => $result['name'], "warn" => false);
                }
            }
        }
        return $distros;
    }

    /**
     * Get the list of kickstart templates from Cobbler
     *
     * @return JsonModel
     */
    public function getKickstartTemplatesAction() {
        $cobblerServer = $this->params()->fromRoute('param1');
        $this->client = $this->getClient($cobblerServer);

        try {
            $results = $this->client->call("get_kickstart_templates");
        } catch (\Exception $e) {
            $message = $e->getMessage();
            if (preg_match("/<class 'cobbler.cexceptions.CX'>:'(.*)'/", $message, $m)) {
                $message = $m[1];
            }
            return $this->renderView(array("success" => false, "message" => "Cobbler Error: " . $message));
        }

        $kickstarts = array();
        foreach ($results as $ks) {
            // TODO: this needs to be changed to a 'superuser' user type and not a specific user
            if ($this->_user->getUserType() == 'Admin') {
                $kickstarts[] = $ks;
            }
            else if (preg_match("/\/baseline.ks|\/baseline_6.ks|\/2013-REL5-database.ks|\/baseline_uek/", $ks)) {
                $kickstarts[] = $ks;
            }
        }
        //usort($kickstarts, self::sortObjByName());
        sort($kickstarts);
        return $this->renderview(array("success" => true, "kickstarts" => $kickstarts));
    }


    /**
     * @return \Zend\View\Model\JsonModel
     */
    public function startWatcherAction()
    {
        $serverId = $this->params()->fromRoute('param1');

        $nmServerTable = new Model\NMServerTable($this->_config);
        $server = $nmServerTable->getById($serverId);

        $logFile = $this->watcherLogFile . "." . $server->getName();

        // touch the cobbler watcher log file and set its perms wide open so it can be deleted later
        touch($logFile);

        // spawn the cobbler_watcher process
        $cmd = "nohup php " . $this->watcherExecDir . "/" . $this->watcherExecFile . " -i " . $server->getId() . " -c " . $server->getCobblerServer() . " > " . $logFile . " 2>&1 &";
        exec($cmd);

        // wait a couple of seconds and then change the perms on the log file
        sleep(1);
        chmod($logFile, 0666);

        return $this->renderView(array("success" => true));
    }

    /**
     * @return \Zend\View\Model\JsonModel
     */
    public function checkCobblerLogAction()
    {
        $serverId = $this->params()->fromRoute('param1');

        $nmServerTable = new Model\NMServerTable($this->_config);
        $nmServer = $nmServerTable->getById($serverId);

        try {
            $ssh = new SSH2($nmServer->getCobblerServer());
        } catch (\Exception $e) {
            return $this->renderView(array("success" => false, "message" => "Connection Failed"));
        }

        $authConfig = $this->_config['cobbler'][$nmServer->getCobblerServer()];
        try {
            #$ssh->loginWithPassword($authConfig['username'], $authConfig['password']);
            if (!$ssh->loginWithKey($authConfig['username'], $authConfig['publicKeyFile'], $authConfig['privateKeyFile'])) {
                return $this->renderView(array("success" => false, "message" => "Login with key failed"));
            }
        } catch (\ErrorException $e) {
            return $this->renderView(array("success" => false, "message" => "Login Failed"));
        }
        $ssh->getShell(false, 'vt102', Array(), 4096);

        $prompt = ']# ';
        $buffer = '';
        $ssh->waitPrompt($prompt, $buffer, 2);
        $ssh->writePrompt("/root/neumatic.py | grep " . $nmServer->getName() . " | tail -1");
        $buffer = '';
        $ssh->waitPrompt($prompt, $buffer, 2);
        $ssh->closeStream();

        $lines = explode("\r\n", $buffer);
        $rec = chop($lines[1]);
        $fields = explode('|', $rec);
        return $this->renderView(array("success" => true, "time" => $fields[2], "status" => $fields[3]));
    }

    /**
     * Create a new Cobbler system profile
     *
     * @return JsonModel
     */
    public function createSystemAction()
    {
        $attempt = 1;

        $serverId = $this->params()->fromRoute('param1');

        $serverTable = new Model\NMServerTable($this->_config);
        $server = $serverTable->getById($serverId);

        // log into cobbler
        $this->client = $this->getClient($server->getCobblerServer());
        try {
            $token = $this->cobblerLogin();
        } catch (\Exception $e) {
            return $this->renderView(array(
                                         "success"   => false,
                                         "error"     => $e->getMessage(),
                                         "trace"     => $e->getTraceAsString(),
                                         "logLevel"  => Logger::ERR,
                                         "logOutput" => $e->getMessage() . " Trace: " . $e->getTraceAsString()
                                     ));
        }

        // check for existing system & delete if found
        try {
            $this->deleteSystem($server->getName());
        } catch (\ErrorException $e) {
            return $this->renderView(array(
                                         "success"   => false,
                                         "error"     => $e->getMessage(),
                                         "trace"     => $e->getTraceAsString(),
                                         "logLevel"  => Logger::ERR,
                                         "logOutput" => $e->getMessage() . " Trace: " . $e->getTraceAsString()
                                     ));
        }

        // check for existing IP
        $results = $this->getUsedIPs($this->client);
        foreach ($results as $ip => $o) {
            if ($ip == $server->getIpAddress()) {
                return $this->renderView(array(
                                             "success"   => false,
                                             "error"     => "Cobbler: IP {$ip} already used by system {$o['hostname']}",
                                             "logLevel"  => Logger::ERR,
                                             "logOutput" => "Cobbler: IP {$ip} already used by system {$o['hostname']}"
                                         ));
            }
        }

        try {
            // create a new system
            $systemId = $this->client->call("new_system", array($token));

            // modify the system with all our params
            // General
            $this->client->call("modify_system", array($systemId, 'name', $server->getName(), $token));
            $this->client->call("modify_system", array($systemId, 'profile', $server->getCobblerDistro(), $token));

            $this->client->call("modify_system", array($systemId, 'status', 'production', $token));
            $this->client->call("modify_system", array($systemId, 'kickstart', $server->getCobblerKickstart(), $token));
    
            // Networking (Global)
            $this->client->call("modify_system", array($systemId, 'hostname', $server->getName(), $token));
            $this->client->call("modify_system", array($systemId, 'gateway', $server->getGateway(), $token));
            $this->client->call("modify_system", array($systemId, 'name_servers', $this->_config['defaultNameServers'], $token));
            $this->client->call("modify_system", array($systemId, 'name_servers_search', $this->_config['defaultNameServersSearchPath'], $token));

            // Networking
            $ethX = $this->_config['defaultNetworkInterface'];
            $this->client->call("modify_system",
                array($systemId,
                    'modify_interface',
                    array("macaddress-{$ethX}" => $server->getMacAddress(),
                          "ipaddress-{$ethX}"  => $server->getIpAddress(),
                          "netmask-{$ethX}"    => $server->getSubnetMask(),
                          "static-{$ethX}"     => $this->_config['defaultIPStatic'],
                          "dnsname-{$ethX}"    => $server->getName()),
                    $token));

            // kickstart meta data which holds the chef keys (server, role and env)
            $ksMeta = "CHEFSERVER=" . $server->getChefServer() .
                " CHEFROLE=" . $server->getChefRole() .
                " CHEFENV=" . $server->getChefEnv() .
                " " . $server->getCobblerMetadata();
            $this->client->call("modify_system", array($systemId, 'ks_meta', $ksMeta, $token));

            $this->client->call("save_system", array($systemId, $token));
        } catch (\Exception $e) {
            $message = $e->getMessage();
            // example error: <class 'cobbler.cexceptions.CX'>:'invalid profile name: CentOS-6.5-x86_64'
            if (preg_match("/<class 'cobbler.cexceptions.CX'>:'(.*)'/", $message, $m)) {
                $message = $m[1];
            }
            return $this->renderView(array(
                                         "success"   => false,
                                         "error"     => "Cobbler Error: " . $message,
                                         "logLevel"  => Logger::ERR,
                                         "logOutput" => "Cobbler Error: " . $message
                                     ));
        }

        // call the sync from the cobbler server command line
        try {
            $this->waitForSync($server->getCobblerServer());
        } catch(\ErrorException $e) {
            if ($attempt == 1) {
                $attempt += 1;
                $this->createSystemAction();
            }
            return $this->renderView(array(
                                         "success"   => false,
                                         "error"     => $e->getMessage(),
                                         "trace"     => $e->getTraceAsString(),
                                         "logLevel"  => Logger::ERR,
                                         "logOutput" => $e->getMessage() . " Trace: " . $e->getTraceAsString()
                                     ));
        }

        return $this->renderView(array(
                                     "success"    => true,
                                     "systemId" => $systemId,
                                     "logLevel"   => Logger::NOTICE,
                                     "logOutput"  => $server->getName() . " profile created",
                                     "parameters" => "[serverName: {$server->getName()}]"
                                 ));
    }

    /**
     * @return \Zend\View\Model\JsonModel
     */
    public function deleteSystemAction()
    {
        $serverId = $this->params()->fromRoute('param1');

        $serverTable = new Model\NMServerTable($this->_config);
        $server = $serverTable->getById($serverId);

        // log into cobbler
        $this->client = $this->getClient($server->getCobblerServer());
        try {
            $this->token = $this->cobblerLogin();
        } catch (\Exception $e) {
            return $this->renderView(array(
                                         "success"   => false,
                                         "error"     => $e->getMessage(),
                                         "trace"     => $e->getTraceAsString(),
                                         "logLevel"  => Logger::ERR,
                                         "logOutput" => $e->getMessage() . " Trace: " . $e->getTraceAsString()
                                     ));
        }

        // delete the system
        try {
            $this->deleteSystem($server->getName());
        } catch (\ErrorException $e) {
            return $this->renderView(array(
                                         "success"   => false,
                                         "error"     => $e->getMessage(),
                                         "trace"     => $e->getTraceAsString(),
                                         "logLevel"  => Logger::ERR,
                                         "logOutput" => $e->getMessage() . " Trace: " . $e->getTraceAsString()
                                     ));
        }

        return $this->renderView(array(
                                     "success"    => true,
                                     "logLevel"   => Logger::NOTICE,
                                     "logOutput"  => $server->getName() . " profile deleted",
                                     "parameters" => "[serverName: {$server->getName()}]"
                                 ));
    }


    // *****************************************************************************************************************
    // Private methods
    // *****************************************************************************************************************

    /**
     * @param $serverName
     * @throws \ErrorException
     */
    private function deleteSystem($serverName) {
        try {
            $results = $this->client->call("get_systems");
            $existingSystemProfile = "";
            foreach ($results as $sys) {
                if ($sys['name'] == $serverName) {
                    $existingSystemProfile = $serverName;
                    break;
                }
            }
            if ($existingSystemProfile) {
                // system profile exists. Delete and continue on
                $this->client->call('remove_system', array($serverName, $this->token, false));
            }
        } catch (\Exception $e) {
            $message = $e->getMessage();
            if (preg_match("/<class 'cobbler.cexceptions.CX'>:'(.*)'/", $message, $m)) {
                $message = $m[1];
            }
            throw new \ErrorException($message);
        }
    }

    /**
     * Connect to the Cobbler API via XMLRPC
     * @param $server
     * @return XmlRpc\Client
     */
    private function getClient($server) {
        $url = $this->protocol . "://" . $server . "/" . $this->apiPath;
        $client = new XmlRpc\Client($url);

        // ignore ssl cert validation and increase timeout to 60 secs
        $client->getHttpClient()->setOptions(
            array(
                'sslverifypeer' => false,
                'timeout'       => 90,
                'sslcapath'     => '/etc/ssl/certs'
            ));
        $this->client = $client;
        return $client;
    }

    /**
     * Logs into cobbler using auth info and gets a token that can be used to make changes
     * @throws \ErrorException
     * @return mixed
     */
    private function cobblerLogin(){
        // send the login request
        try {
            $this->token = $this->client->call("login", array($this->username, $this->password));
        } catch (\Exception $e) {
            $message = $e->getMessage();
            if (preg_match("/<class 'cobbler.cexceptions.CX'>:'(.*)'/", $message, $m)) {
                $message = $m[1];
            }
            throw new \ErrorException($message);
        }
        return $this->token;
    }

    /**
     * @return array
     * @throws \ErrorException
     */
    private function getUsedIPs() {
        try {
            $results = $this->client->call("get_systems");
        } catch (\Exception $e) {
            $message = $e->getMessage();
            if (preg_match("/<class 'cobbler.cexceptions.CX'>:'(.*)'/", $message, $m)) {
                $message = $m[1];
            }
            throw new \ErrorException($message);
        }

        $data = array();
        foreach ($results as $system) {
            $hostname = $system['hostname'];
            $interfaces = $system['interfaces'];
            foreach(array_keys($interfaces) as $ifName) {
                $if = $interfaces[$ifName];
                $ip = $if['ip_address'];
                $data[$ip] = array(
                    "hostname" => $hostname,
                    "interface" => $ifName
                );
            }
        }
        return $data;
    }

    /**
     * @param string $cobblerServer
     * @return bool
     * @throws \ErrorException
     */
    public function waitForSync($cobblerServer = "stlabvmcblr01.va.neustar.com") {
        // initiate an ssh connection to the Cobbler server
        try {
            $ssh = new SSH2($cobblerServer);
        } catch (\Exception $e) {
            throw new \ErrorException("Connection to cobbler server failed");
        }

        // login
        $authConfig = $this->_config['cobbler'][$cobblerServer];
        try {
            #$ssh->loginWithPassword($authConfig['username'], $authConfig['password']);
            if (!$ssh->loginWithKey($authConfig['username'], $authConfig['publicKeyFile'], $authConfig['privateKeyFile'])) {
                return $this->renderView(array("success" => false, "message" => "Login with key failed"));
            }
        } catch (\ErrorException $e) {
            throw new \ErrorException("Login to cobbler server failed");
        }
        // set the shell params
        $ssh->getShell(false, 'vt102', Array(), 4096);

        // set the root prompt
        $prompt = '# ';

        // Cobbler sync command to run
        $command = "/usr/bin/cobbler sync";

        // output will contain the entire session
        $output = '';
        // buffer will be cleared and written to in each writePrompt call
        $buffer = '';

        // wait for the command line prompt
        $ssh->waitPrompt($prompt, $buffer, 2);
        $output .= $buffer;

        // send the sync command
        $ssh->writePrompt($command . "\n");
        $buffer = '';

        // wait 60 seconds for the prompt to return
        $ssh->waitPrompt($prompt, $buffer, $this->_config['cobblerSyncTimeout']);
        $output .= $buffer;
        $ssh->closeStream();

        // insure that we've received a Task Complete for the sync
        if (!strstr($output, '*** TASK COMPLETE ***')) {
            throw new \ErrorException("Cobbler sync command failed or did not complete");
        }
        return true;
    }
}
