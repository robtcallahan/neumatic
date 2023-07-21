<?php

/**
 * Neumatic Chassis Controller
 *
 * @author Rob Callahan <rob.callahan@neustar.biz>
 */

namespace Neumatic\Controller;

use Neumatic\Model;
use STS\HPSIM;

use Zend\View\Model\JsonModel;
use Zend\Mvc\MvcEvent;

use STS\Util\SSH2;

/**
 * The Chassis Controller Class controls all communication to HP chassis which includes checking the power status,
 * of blades, setting PXE boot and powering blades off and one.
 *
 * @package Neumatic\Controller
 */
class ChassisController extends Base\BaseController
{

    /**
     * Login username for the chassis from config.
     * @var
     */
    protected $chassisUsername;
    /**
     * Login password for the chassis from config.
     * @var
     */
    protected $chassisPassword;
    /**
     * Login username for the chassis switch module from config.
     * @var
     */
    protected $switchUsername;
    /**
     * Login password for the chassis switch module from config.
     * @var
     */
    protected $switchPassword;

    /**
     * @var
     */
    protected $consoleExecDir;
    /**
     * @var
     */
    protected $consoleExecFile;

    /**
     * @var
     */
    protected $logDir;
    /**
     * @var
     */
    protected $consoleLogFile;
    /**
     * @var
     */
    protected $consoleWatcherFile;
    /**
     * @var
     */
    protected $consoleWatcherErrorFile;

    /**
     * SSH2 connection instance.
     * @var SSH2 $ssh
     */
    protected $ssh;
    /**
     * Stream output of the SSH2 connection.
     * @var
     */
    protected $stream;
    /**
     * Timeout value for SSH connection.
     * @var
     */
    protected $sshTimeout;

    /**
     * SSH2 prompt regular expression to test for.
     * @var string
     */
    private $_prompt = '> $';

    /**
     * This method is always called first before any action method and is a good place for reading
     * the config and making some useful assignments that'll be used later on.
     *
     * @param MvcEvent $e
     * @return mixed
     */
    public function onDispatch(\Zend\Mvc\MvcEvent $e)
    {
        $this->chassisUsername = $this->_config['chassisUsername'];
        $this->chassisPassword = $this->_config['chassisPassword'];
        $this->switchUsername = $this->_config['switchUsername'];
        $this->switchPassword = $this->_config['switchPassword'];

        $this->consoleExecDir = __DIR__ . "/../../../bin";
        $this->consoleExecFile = "console_watch.php";

        $this->logDir = "/tmp";
        $this->consoleLogFile = $this->logDir . "/console.log";
        $this->consoleWatcherFile = $this->logDir . "/console_watch.log";
        $this->consoleWatcherErrorFile = $this->logDir . "/console_watch.err";

        $this->sshTimeout = 30;
        $this->ssh = null;
        $this->stream = null;

        return parent::onDispatch($e);
    }

    /**
     * @return JsonModel
     */
    public function indexAction()
    {

        return $this->renderview(array("error" => "This controller has no output from index. Eventually I would like to display the documentation here."));
    }

    /**
     * Connect to the chassis switch and execute a "show profile" command to obtain the MAC address of the blade
     *
     * @return JsonModel
     */
    public function getMacAddressAction()
    {
        $this->_prompt = '->$';

        $serverName = $this->params()->fromRoute('param1');
        $chassisId = $this->params()->fromRoute('param2');

        $serverTable = new Model\NMServerTable($this->_config);
        $server = $serverTable->getByName($serverName);
        if ($server->getId()) {
            $name = $server->getName();
        } else {
            $name = $serverName;
        }

        $switchTable = new HPSIM\HPSIMSwitchTable($this->_config);
        $switch = $switchTable->getActiveByChassisId($chassisId);

        if ($switch->getId() == null) {
            return $this->renderView(array("success" => false, "message" => "No active switch found for chassis"));
        }

        // Since we cannot return a ViewModel using $this->renderView, sshConnect will set the class property, $this->_viewData, if there
        // is an error and then return false. We check the return status and then call
        // $this->renderView($this->_viewData) if an error is found. Not happy about this solution, but it works for now.

        if (!$this->sshConnect($switch->getFullDnsName())) {
            return $this->renderView($this->_viewData);
        }
        $output = "";
        if (!$this->sshCommand("show profile " . $name, $output)) {
            return $this->renderView($this->_viewData);
        }
        $this->sshDisconnect();

        #return $this->renderView($output, "pre");
        $lines = explode("\n", $output);
        foreach ($lines as $line) {
            if (preg_match("/UseBIOS/", $line)) {
                $fields = preg_split("/\s+/", $line);
                $macAddress = preg_replace("/-/", ":", $fields[4]);
                return $this->renderView(array("success" => 1, "macAddress" => $macAddress));
            }
        }
        return $this->renderView(array("success" => false, "message" => "Could not determine MAC address of blade", "output" => $output));
    }

    /**
     * param1: management module fqdn
     * param2: slot number
     *
     * @return JsonModel
     */
    public function restartSystemAction()
    {
        $serverId = $this->params()->fromRoute('param1');

        $serverTable = new Model\NMServerTable($this->_config);
        $server = $serverTable->getById($serverId);

        $bladeTable = new Model\NMBladeTable($this->_config);
        $blade = $bladeTable->getByServerId($server->getId());

        $this->consoleWatcherFile .= "." . $server->getName();
        if (file_exists($this->consoleWatcherFile)) {
            unlink($this->consoleWatcherFile);
        }
        $watcher = fopen($this->consoleWatcherFile, "w");

        $server
            ->setStatus('Building')
            ->setStatusText("Checking blade status...");
        $server = $serverTable->update($server);

        fwrite($watcher, "[" . (date("Y-m-d H:i:s")) . "] Console Watcher Log: " . $server->getName() . " (Slot " . $blade->getBladeSlot() . ")\n");
        fwrite($watcher, "[" . (date("Y-m-d H:i:s")) . "] Getting active management module...\n");

        $mmTable = new HPSIM\HPSIMMgmtProcessorTable($this->_config);
        $mm = $mmTable->getActiveByChassisId($blade->getChassisId());

        if ($mm->getId() == null) {
            fwrite($watcher, "[" . (date("Y-m-d H:i:s")) . "] No active management module found for chassis\n");
            return $this->renderView(array("success" => false, "message" => "No active MM found for chassis. Exiting..."));
        }


        if (preg_match("/^([\w\d]+)\..*/", $mm->getFullDnsName(), $m)) {
            $this->_prompt = $m[1] . '> $';
        } else {
            $this->_prompt = '> $';
        }
        fwrite($watcher, "[" . (date("Y-m-d H:i:s")) . "] Prompt set to " . $this->_prompt . "\n");

        // connect to the chassis
        fwrite($watcher, "[" . (date("Y-m-d H:i:s")) . "] Connecting to chassis " . $mm->getFullDnsName() . "...\n");
        if (!$this->sshConnect($mm->getFullDnsName())) {
            return $this->renderView($this->_viewData);
        }

        // first check if powered on
        fwrite($watcher, "[" . (date("Y-m-d H:i:s")) . "] Checking power status...\n");
        if (!$powerStatus = $this->getBladePowerStatus($blade->getBladeSlot())) {
            fwrite($watcher, "[" . (date("Y-m-d H:i:s")) . "] Could not determine power status. Exiting...\n");
            return $this->renderView($this->_viewData);
        }

        // if blade is power on, turn it off
        if ($powerStatus == "On") {
            fwrite($watcher, "[" . (date("Y-m-d H:i:s")) . "] Blade is ON. Powering OFF blade...\n");
            $server->setStatus('Building')->setStatusText("Powering OFF blade...");
            $server = $serverTable->update($server);

            if (!$this->powerOffBlade($blade)) {
                return $this->renderView($this->_viewData);
            }
        }
        fwrite($watcher, "[" . (date("Y-m-d H:i:s")) . "] Blade is OFF.\n");

        // blade has been powered off. Set the blade to PXE boot on next power on
        fwrite($watcher, "[" . (date("Y-m-d H:i:s")) . "] Setting PXE boot...\n");
        $server->setStatus('Building')->setStatusText("Setting PXE boot...");
        $server = $serverTable->update($server);

        $output = '';
        if (!$this->sshCommand("set server boot once pxe " . $blade->getBladeSlot() . "\n\r", $output)) {
            fwrite($watcher, "[" . (date("Y-m-d H:i:s")) . "] Could not set PXE boot. Exiting...\n");
            return $this->renderView($this->_viewData);
        }

        // wait before we power on
        fwrite($watcher, "[" . (date("Y-m-d H:i:s")) . "] Sleeping 10 seconds...\n");
        sleep(10);

        // we're ready to go. Power on the server
        fwrite($watcher, "[" . (date("Y-m-d H:i:s")) . "] Powering ON blade...\n");
        $server->setStatus('Building')->setStatusText("Powering ON blade...");
        $serverTable->update($server);
        if (!$this->powerOnBlade($blade)) {
            return $this->renderView($this->_viewData);
        }

        fwrite($watcher, "[" . (date("Y-m-d H:i:s")) . "] Disconnecting from chassis...\n");
        $this->sshDisconnect();
        fclose($watcher);

        return $this->renderView(array("success" => 1, "message" => "Success"));
    }

    /**
     * @return JsonModel
     */
    public function stopSystemAction()
    {
        $serverId = $this->params()->fromRoute('param1');
        #$powerOff = $this->params()->fromRoute('param2');

        $serverTable = new Model\NMServerTable($this->_config);
        $server = $serverTable->getById($serverId);

        $consoleTable = new Model\NMConsoleTable($this->_config);
        $console = $consoleTable->getByServerId($server->getId());

        $consoleData = "";
        $consoleLogFile = $this->consoleLogFile . "." . $server->getName();
        if (file_exists($consoleLogFile)) {
            $consoleData = file_get_contents($consoleLogFile);
        }
        $consoleLog = "";
        $consoleWatcherFile = $this->consoleWatcherFile . "." . $server->getName();
        if (file_exists($consoleWatcherFile)) {
            $consoleLog = file_get_contents($consoleWatcherFile);
        }
        $console
            ->setServerId($server->getId())
            ->setConsoleRunning(false)
            ->setConsoleLog($consoleData)
            ->setConsoleWatcherLog($consoleLog);
        if ($console->getId()) {
            $consoleTable->update($console);
        } else {
            $consoleTable->create($console);
        }

        // null the dateBuilt value of the server
        $server->setDateBuilt(null);
        $serverTable->update($server);

        /*
        if ($powerOff) {
            // get the active mgmt module for this chassis
            if (!$chassMMFqdn = $this->getChassisMM($server->getChassisId())) {
                return $this->renderView($this->_viewData);
            }

            // connect to the chassis
            if (!$this->sshConnect($chassMMFqdn)) {
                return $this->renderView($this->_viewData);
            }

            // Power off the server
            if (!$this->powerOffBlade($blade)) {
                return $this->renderView($this->_viewData);
            }

            $this->sshDisconnect();
        }
        */

        $this->stopLogging($server, $console);
        return $this->renderView(array("success" => true, "message" => "Success"));
    }

    /**
     * @return JsonModel
     */
    public function pingAction()
    {
        $host = $this->params()->fromRoute('param1');
        $timeout = 1;

        /* ICMP ping packet with a pre-calculated checksum */
        $package = "\x08\x00\x7d\x4b\x00\x00\x00\x00PingHost";
        $socket = socket_create(AF_INET, SOCK_RAW, 1);
        socket_set_option($socket, SOL_SOCKET, SO_RCVTIMEO, array('sec' => $timeout, 'usec' => 0));
        socket_connect($socket, $host, null);

        $ts = microtime(true);
        socket_send($socket, $package, strLen($package), 0);
        if (socket_read($socket, 255)) {
            $result = microtime(true) - $ts;
        } else {
            $result = false;
        }
        socket_close($socket);

        return $this->renderView(array("success" => $result));
    }

    /**
     * @return JsonModel
     */
    public function restartConsoleAction()
    {
        $serverId = $this->params()->fromRoute('param1');

        $serverTable = new Model\NMServerTable($this->_config);
        $server = $serverTable->getById($serverId);

        $consoleTable = new Model\NMConsoleTable($this->_config);
        $console = $consoleTable->getByServerId($server->getId());

        $this->startLogging($server, $console, true);
        return $this->renderView(array("success" => 1));
    }

    /**
     * @return JsonModel
     */
    public function stopConsoleAction()
    {
        $serverId = $this->params()->fromRoute('param1');

        $serverTable = new Model\NMServerTable($this->_config);
        $server = $serverTable->getById($serverId);

        $consoleTable = new Model\NMConsoleTable($this->_config);
        $console = $consoleTable->getByServerId($server->getId());

        $this->stopLogging($server, $console);
        return $this->renderView(array("success" => 1, "message" => "Success"));
    }

    /**
     * @return JsonModel
     */
    public function powerOffBladeAction()
    {
        $serverId = $this->params()->fromRoute('param1');

        $serverTable = new Model\NMServerTable($this->_config);
        $server = $serverTable->getById($serverId);

        $bladeTable = new Model\NMBladeTable($this->_config);
        $blade = $bladeTable->getByServerId($server->getId());

        $mmTable = new HPSIM\HPSIMMgmtProcessorTable($this->_config);
        $mm = $mmTable->getActiveByChassisId($blade->getChassisId());

        if ($mm->getId() == null) {
            return $this->renderView(array("success" => false, "message" => "No active MM found for chassis. Exiting..."));
        }

        if (preg_match("/^([\w\d]+)\..*/", $mm->getFullDnsName(), $m)) {
            $this->_prompt = $m[1] . '> $';
        } else {
            $this->_prompt = '> $';
        }

        // connect to the chassis
        if (!$this->sshConnect($mm->getFullDnsName())) {
            return $this->renderView($this->_viewData);
        }

        if (!$this->powerOffBlade($blade)) {
            $this->sshDisconnect();
            return $this->renderView($this->_viewData);
        }
        $this->sshDisconnect();
        return $this->renderView(array("success" => true));
    }


    // *****************************************************************************************************************
    // Private methods
    // *****************************************************************************************************************

    /**
     * @param $chassisId
     * @return bool|JsonModel
     */
    private function getChassisMM($chassisId)
    {
        $mmTable = new HPSIM\HPSIMMgmtProcessorTable($this->_config);
        $mm = $mmTable->getActiveByChassisId($chassisId);

        if ($mm->getId() == null) {
            return $this->renderView(array("success" => false, "message" => "No active MM found for chassis"));
        }

        if (preg_match("/^([\w\d]+)\..*/", $mm->getFullDnsName(), $m)) {
            $this->_prompt = $m[1] . '> $';
        } else {
            $this->_prompt = '> $';
        }
        return true;
    }

    /**
     * @param Model\NMBlade $blade
     * @return bool
     */
    private function powerOnBlade(Model\NMBlade $blade)
    {
        if (!$this->sshCommand("poweron server " . $blade->getBladeSlot() . "\r\n", $output)) {
            return false;
        }

        // check the status of the blade until it has successfully powered on
        while (1) {
            sleep(5);
            if (!$powerStatus = $this->getBladePowerStatus($blade->getBladeSlot())) {
                return false;
            }
            if ($powerStatus == "On") {
                return true;
            }
        }
        return true;
    }
    
    /**
     * @param Model\NMBlade $blade
     * @return bool
     */
    private function powerOffBlade(Model\NMBlade $blade)
    {
        $output = '';
        if (!$this->sshCommand("poweroff server " . $blade->getBladeSlot() . " FORCE\r\n", $output)) {
            return false;
        }

        // check the status of the blade until it has successfully powered down
        while (1) {
            sleep(5);
            if (!$powerStatus = $this->getBladePowerStatus($blade->getBladeSlot())) {
                return false;
            }
            if ($powerStatus == "Off") {
                return true;
            }
        }
        return true;
    }

    /**
     * @param $slot
     * @return bool
     */
    private function getBladePowerStatus($slot)
    {
        $output = '';
        if (!$this->sshCommand("show server status " . $slot . "\r\n", $output)) {
            return false;
        }

        if (preg_match("/Power: (On|Off)/", $output, $m)) {
            $powerStatus = $m[1];
        } else {
            $this->sshDisconnect();
            $this->_viewData = array("success" => false, "message" => "Could not determine power status of blade");
            return false;
        }
        return $powerStatus;
    }

    /**
     * @param Model\NMServer $server
     * @param Model\NMConsole $console
     * @param bool $restart
     */
    private function startLogging(Model\NMServer $server, Model\NMConsole $console, $restart=false)
    {
        if (!preg_match("/{$server->getName()}/", $this->consoleWatcherFile)) {
            $this->consoleWatcherFile .= "." . $server->getName();
        }
        $watcher = fopen($this->consoleWatcherFile, "a");

        $pid = $this->getConsoleProcess($server->getId());
        if (!$pid) {
            $consoleTable = new Model\NMConsoleTable($this->_config);
            $console
                ->setConsoleWatcherLog(" ")
                ->setConsoleLog(" ")
                ->setConsoleRunning(true);
            $consoleTable->update($console);

            // spawn the console_watcher process
            if ($restart) {
                fwrite($watcher, "[" . (date("Y-m-d H:i:s")) . "] Watcher restarted\n");
                fclose($watcher);
                $command = "nohup php " . $this->consoleExecDir . "/" . $this->consoleExecFile . " -i " . $server->getId() . " -r > " . $this->consoleWatcherErrorFile . " 2>&1 &";
            } else {
                fwrite($watcher, "[" . (date("Y-m-d H:i:s")) . "] Watcher started\n");
                fclose($watcher);
                $command = "nohup php " . $this->consoleExecDir . "/" . $this->consoleExecFile . " -i " . $server->getId() . " > " . $this->consoleWatcherErrorFile . " 2>&1 &";
            }
            exec($command);
        } else {
            fwrite($watcher, "[" . (date("Y-m-d H:i:s")) . "] Watcher already running\n");
            fclose($watcher);
        }
    }

    /**
     * @param Model\NMServer $server
     * @param \Neumatic\Model\NMConsole $console
     */
    private function stopLogging(Model\NMServer $server, Model\NMConsole $console)
    {
        $consoleTable = new Model\NMConsoleTable($this->_config);
        $console->setConsoleRunning(false);
        $consoleTable->update($console);

        $pid = $this->getConsoleProcess($server->getId());
        if ($pid) {
            exec("kill {$pid}");
        }
    }

    /**
     * @param $logFile
     */
    private function deleteConsoleLog($logFile)
    {
        if (file_exists($logFile)) {
            unlink($logFile);
        }
    }

    /**
     * @param int $serverId
     * @return bool|string
     */
    private function getConsoleProcess($serverId) {
        $found = exec("ps -ef | grep '" . $this->consoleExecFile . " -i " . $serverId . "' | grep -v grep | awk '{print \$2}'", $pidArray);
        if ($found) {
            return implode(" ", $pidArray);
        }
        return false;
    }

    /**
     * Since we cannot return a ViewModel using $this->renderView, we'll set the class property, $this->_viewData, if there
     * is an error and then return false. The caller is responsible for checking the return status and then calling
     * $this->renderView($this->_viewData) if an error is found. Not happy about this solution, but it works for now.
     *
     * @param $host
     * @return bool
     */
    private function sshConnect($host)
    {
        $this->_prompt = '.*mm\d> $';

        try {
            $this->ssh = new SSH2($host);
        } catch (\Exception $e) {
            $this->_viewData = array("success" => false, "message" => "ssh connection to {$host} failed");
            return false;
        }

        try {
            $ok = $this->ssh->loginWithPassword($this->switchUsername, $this->switchPassword);
        } catch (\ErrorException $e) {
            $this->_viewData = array("success" => false, "message" => "Login to {$host} failed");
            return false;
        }
        if (!$ok) {
            $this->_viewData = array("success" => false, "message" => "Login to {$host} failed");
            return false;
        }

        $stream = $this->ssh->getShell(false, 'vt102', Array(), 4096);
        if (!$stream) {
            $this->_viewData = array("success" => false, "message" => "Obtaining shell on {$host} failed");
            return false;
        }

        // set the socket timeout to 30 seconds
        stream_set_timeout($stream, $this->sshTimeout);

        $buffer = '';
        $ok = $this->ssh->waitPrompt($this->_prompt, $buffer, $this->sshTimeout);
        if (!$ok) {
            $this->_viewData = array("success" => false, "message" => "Getting shell prompt on {$host} failed");
            return false;
        }
        return true;
    }

    /**
     * @param $command
     * @param $output
     * @return bool
     */
    private function sshCommand($command, &$output)
    {
        $buffer = '';
        $this->ssh->writePrompt($command);
        $ok = $this->ssh->waitPrompt($this->_prompt, $buffer, $this->sshTimeout);
        if (!$ok) {
            $this->_viewData = array("success" => false, "message" => "'" . $command . "' command failed");
            return false;
        }
        $output = $buffer;
        return true;
    }

    /**
     *
     */
    private function sshDisconnect()
    {
        if ($this->ssh) {
            $this->ssh->closeStream();
        }
    }

}
