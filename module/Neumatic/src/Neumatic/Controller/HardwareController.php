<?php

/**
 * Neumatic Hardware Controller
 *
 * @author Rob Callahan <rob.callahan@neustar.biz>
 */

namespace Neumatic\Controller;

use Neumatic\Model;

use Zend\View\Model\JsonModel;
use Zend\Json\Server\Exception\ErrorException;
use Zend\Mvc\MvcEvent;
use Zend\Log\Logger;

use STS\Util\SSH2;

require_once("/usr/share/pear/Net/DNS2.php");
use Net_DNS2_Resolver;

/**
 * The Hardware Controller Class controls all communication to standalone hardware which includes checking the power status,
 * setting PXE boot and powering on and off.
 *
 * @package Neumatic\Controller
 */
class HardwareController extends Base\BaseController
{

    /**
     * Login username for the iLO
     * @var
     */
    protected $iLOUsername;
    /**
     * Login password for the iLO
     * @var
     */
    protected $iLOPassword;

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
    private $_prompt;

    private $logDir;

    private $consoleExecDir;
    private $consoleExecFile;
    private $consoleLogFile;
    private $consoleWatcherFile;
    private $consoleWatcherErrorFile;

    /**
     * This method is always called first before any action method and is a good place for reading
     * the config and making some useful assignments that'll be used later on.
     *
     * @param MvcEvent $e
     * @return mixed
     */
    public function onDispatch(\Zend\Mvc\MvcEvent $e)
    {
        $this->iLOUsername = $this->_config['iLOCredentials']['standard']['username'];
        $this->iLOPassword = $this->_config['iLOCredentials']['standard']['password'];

        $this->sshTimeout = 30;
        $this->ssh = null;
        $this->stream = null;
        $this->_prompt = 'hpiLO-> ';

        $this->consoleExecDir = __DIR__ . "/../../../bin";
        $this->consoleExecFile = "console_watch.php";

        $this->logDir = __DIR__ . "/../../../../../watcher_log";
        $this->consoleLogFile = $this->logDir . "/console.log";
        $this->consoleWatcherFile = $this->logDir . "/console_watch.log";
        $this->consoleWatcherErrorFile = $this->logDir . "/console_watch.err";

        return parent::onDispatch($e);
    }

    /**
     * @return JsonModel
     */
    public function indexAction()
    {
        return $this->renderview(array("error" => "This controller has no output from index. Eventually I would like to display the documentation here."));
    }

    public function getMacAddressByNameAction() {
        $serverName = $this->params()->fromRoute('param1');

        $serverTable = new Model\NMServerTable($this->_config);
        $server = $serverTable->getByName($serverName);

        $standaloneTable = new Model\NMStandaloneTable($this->_config);
        $standalone = $standaloneTable->getByServerId($server->getId());

        /*
        $iLOFqdn = $this->getILOFqdn($server->getName());
        if (!$this->testILOFqdn($iLOFqdn)) {
            return $this->renderView($this->_viewData);
        }
        */
        $iLOFqdn = $standalone->getILo();
        if ($iLOFqdn == '') {
            return $this->renderView(array(
                "success" => false,
                "error" => "An iLo FQDN or IP is not defined for {$server->getName()}",
                "logLevel" => Logger::ERR,
                "logOutput" => "An iLo FQDN or IP is not defined for {$server->getName()}"
            ));
        }

        $this->getILOCredentials($server);
        if (!$macAddress = $this->getMacAddress($iLOFqdn)) {
            return $this->renderView(array(
                "success" => false,
                "error" => "Could not determine MAC address of hardware",
                "logLevel" => Logger::ERR,
                "logOutput" => "Cound not determine MAC address of {$server->getName()}"
            ));
        }

        // update the server with this mac address
        $server->setMacAddress($macAddress);
        $serverTable->update($server);

        return $this->renderView(array(
            "success" => true,
            "macAddress" => $macAddress,
            "logLevel" => Logger::DEBUG,
            "logOutput" => "Got MAC address of {$server->getName()}: {$macAddress}"
        ));
    }

    /**
     * Connect to the hardware and obtain the MAC address
     * For HP h/w: show /map1/enetport1/lanendpt1
     *
     * @return JsonModel
     */
    public function getMacAddressAction()
    {
        $serverId = $this->params()->fromRoute('param1');

        $serverTable = new Model\NMServerTable($this->_config);
        $server = $serverTable->getById($serverId);

        $standaloneTable = new Model\NMStandaloneTable($this->_config);
        $standalone = $standaloneTable->getByServerId($server->getId());

        /*
        $iLOFqdn = $this->getILOFqdn($server->getName());
        if (!$this->testILOFqdn($iLOFqdn)) {
            return $this->renderView($this->_viewData);
        }
        */
        $iLOFqdn = $standalone->getILo();
        if ($iLOFqdn == '') {
            return $this->renderView(array(
                "success" => false,
                "error" => "An iLo FQDN or IP is not defined for {$server->getName()}",
                "logLevel" => Logger::ERR,
                "logOutput" => "An iLo FQDN or IP is not defined for {$server->getName()}"
            ));
        }

        $this->getILOCredentials($server);
        if (!$macAddress = $this->getMacAddress($iLOFqdn)) {
            return $this->renderView(array(
                "success" => false,
                "error" => "Could not get MAC address: " . $this->_viewData['error'],
                "logLevel" => Logger::ERR,
                "logOutput" => "Could not determine MAC address of {$server->getName()}: " . $this->_viewData['error']
            ));
        }

        #$this->writeLog(array("logLevel" => Logger::DEBUG, "logOutput" => "macAddress=" . $macAddress));
        // update the server with this mac address
        $server->setMacAddress($macAddress);
        $serverTable->update($server);

        return $this->renderView(array(
            "success" => true,
            "macAddress" => $macAddress,
            "logLevel" => Logger::DEBUG,
            "logOutput" => "Got MAC address of {$server->getName()}: {$macAddress}"
        ));
    }

    public function getMacAddressesAction()
    {
        $serverId = $this->params()->fromRoute('param1');

        $serverTable = new Model\NMServerTable($this->_config);
        $server = $serverTable->getById($serverId);

        $standaloneTable = new Model\NMStandaloneTable($this->_config);
        $standalone = $standaloneTable->getByServerId($server->getId());

        $iLOFqdn = $standalone->getILo();
        if ($iLOFqdn == '') {
            return $this->renderView(array(
                "success" => false,
                "error" => "An iLo FQDN or IP is not defined for {$server->getName()}",
                "logLevel" => Logger::ERR,
                "logOutput" => "An iLo FQDN or IP is not defined for {$server->getName()}"
            ));
        }

        $this->getILOCredentials($server);
        $macAddresses = $this->getMacAddresses($iLOFqdn);
        if (count($macAddresses) == 0) {
            return $this->renderView(array(
                "success" => false,
                "error" => "Could not get MAC address: " . $this->_viewData['error'],
                "logLevel" => Logger::ERR,
                "logOutput" => "Could not determine MAC address of {$server->getName()}: " . $this->_viewData['error']
            ));
        }

        return $this->renderView(array(
            "success" => true,
            "macAddresses" => $macAddresses,
            "logLevel" => Logger::DEBUG,
            "logOutput" => "Got " . count($macAddresses) . " MAC addresses for {$server->getName()}"
        ));
    }
    /**
     * hpiLO-> cd system1/bootconfig1
     * hpiLO-> help show
     * BootFmCd     : bootsource1
     * BootFmFloppy : bootsource2
     * BootFmDrive  : bootsource3
     * BootFmUSBKey : bootsource4
     * BootFmNetwork: bootsource5
     * To display the bootorder corresponding to the bootsource, run
     * show -all /system1/bootconfig1
     *
     *
     * param1: server id
     *
     * @return JsonModel
     */
    public function resetSystemAction()
    {
        $serverId = $this->params()->fromRoute('param1');

        $serverTable = new Model\NMServerTable($this->_config);
        $server = $serverTable->getById($serverId);

        $standaloneTable = new Model\NMStandaloneTable($this->_config);
        $standalone = $standaloneTable->getByServerId($server->getId());

        $server
            ->setStatus('Building')
            ->setStatusText("Checking server status...");
        $server = $serverTable->update($server);

        /*
        if (!$iLOFqdn = $this->getILOFqdn($server->getName())) {
            return $this->renderView($this->_viewData);
        }
        */
        $iLOFqdn = $standalone->getILo();
        if ($iLOFqdn == '') {
            return $this->renderView(array(
                "success" => false,
                "error" => "An iLo FQDN or IP is not defined for {$server->getName()}",
                "logLevel" => Logger::ERR,
                "logOutput" => "An iLo FQDN or IP is not defined for {$server->getName()}"
            ));
        }

        if (!$this->sshConnect($iLOFqdn, $this->iLOUsername, $this->iLOPassword)) {
            return $this->renderView($this->_viewData);
        }

        // set for network boot. first go to bootconfig1
        $output = "";
        if (!$this->sshCommand("cd /system1/bootconfig1\n", $output)) {
            return $this->renderView($this->_viewData);
        }
        // show the list of bootsources
        $output = "";
        if (!$this->sshCommand("show\n", $output)) {
            return $this->renderView($this->_viewData);
        }
        // if there is a bootsource5 then use it, otherwise use bootsource4
        $bootSource = "bootsource4";
        if (preg_match("/bootsource5/", $output)) {
            $bootSource = "bootsource5";
        }
        $output = "";
        if (!$this->sshCommand("set /system1/bootconfig1/{$bootSource} bootorder=1\n", $output)) {
            return $this->renderView($this->_viewData);
        }

        // check power status. If off, then just power on, if on, then reset
        $output = "";
        $powerStatus = $this->getPowerStatus($iLOFqdn);
        if ($powerStatus == 'On') {
            if (!$this->sshCommand("power reset\n", $output)) {
                return $this->renderView($this->_viewData);
            }
        } else {
            if (!$this->powerOnHardware($iLOFqdn)) {
                return $this->renderView(array(
                    "success" => false,
                    "error" => "Could not determine power status of {$server->getName()}",
                    "logLevel" => Logger::ERR,
                    "logOutput" => "Could not determine power status of {$server->getName()}"
                ));
            }
        }

        $this->sshDisconnect();

        return $this->renderView(array(
            "success" => true,
            "logLevel" => Logger::NOTICE,
            "logOutput" => "Reset system performed on {$server->getName()}"
        ));
    }

    /**
     * hpiLO-> vm cdrom insert http://10.31.45.61/mirrors/ISOs/neustar_cent6.5_v13.iso
     * (where 10.31.45.61 is mirrors.neustar.va.com)
     *
     * hpiLO-> vm cdrom get
     * VM Applet = Disconnected
     * Boot Option = NO_BOOT
     * Write Protect = Yes
     * Image Inserted = Connected
     * Image URL = http://10.31.45.61/mirrors/ISOs/neustar_cent6.5_v13.iso
     *
     * hpiLO-> vm cdrom set boot_once
     * hpiLO-> reset
     *
     * param1: server id
     *
     * @return JsonModel
     */
    public function resetRemoteSystemAction()
    {
        $serverId = $this->params()->fromRoute('param1');

        $serverTable = new Model\NMServerTable($this->_config);
        $server = $serverTable->getById($serverId);

        $standaloneTable = new Model\NMStandaloneTable($this->_config);
        $standalone = $standaloneTable->getByServerId($server->getId());

        // select the kickstart file and iLO credentials based upon the business service
        if (stripos($server->getBusinessServiceName(), "ultra") !== false) {
            $ksFileString = $this->updateKickstartFile($server, $this->_config['ksTemplateFiles']['ultradns']);
        } else {
            $ksFileString = $this->updateKickstartFile($server, $this->_config['ksTemplateFiles']['standard']);
        }

        // replacements done. Write to /tmp and copy to ks file server
        $newKsFile = "ks-" . $server->getName() . ".cfg";
        file_put_contents("/tmp/{$newKsFile}", $ksFileString);

        // get the iso server the chef structrure in config. We will copy the kickstart file to it
        $isoServer = $this->getISOServer($server->getChefServer());
        $isoServerKey = $this->_config['chef'][$server->getChefServer()]['isoServerKey'];

        $scpCommand = "scp -o 'Stricthostkeychecking' -i {$isoServerKey} /tmp/{$newKsFile} {$this->_config['ksServerUser']}@{$isoServer}:{$this->_config['ksFileDir']}/{$newKsFile}";
        $output = "";
        $result = exec($scpCommand, $output, $retStatus);
        if ($retStatus != 0) {
            return $this->renderView(array(
                "success" => false,
                "error"   => "Kickstart file could not be copied to cloud server. Message: {$result}, SCP Command: {$scpCommand}",
                "logLevel" => Logger::ERR,
                "logOutput" => "Reset system failed on {$server->getName()}: Kickstart file could not be copied to cloud server: {$result}"
            ));
        }
        // chown so that apache can read it
        $sshCommand = "ssh -i {$isoServerKey} -l {$this->_config['ksServerUser']} {$isoServer} \"chmod 644 {$this->_config['ksFileDir']}/{$newKsFile}\"";
        $output = "";
        $result = exec($sshCommand, $output, $retStatus);
        if ($retStatus != 0) {
            return $this->renderView(array(
                "success" => false,
                "error"   => "Kickstart file could not be copied to cloud server: {$result}",
                "logLevel" => Logger::ERR,
                "logOutput" => "Reset system failed on {$server->getName()}: Kickstart file could not be copied to cloud server: {$result}"
            ));
        }

        // delete tmp file. don't want to leave our trash around
        unlink("/tmp/{$newKsFile}");

        $server
            ->setStatus('Building')
            ->setStatusText("Resetting system...");
        $server = $serverTable->update($server);

        $iLOFqdn = $standalone->getILo();
        if ($iLOFqdn == '') {
            return $this->renderView(array(
                "success" => false,
                "error" => "An iLo FQDN or IP is not defined for {$server->getName()}",
                "logLevel" => Logger::ERR,
                "logOutput" => "An iLo FQDN or IP is not defined for {$server->getName()}"
            ));
        }

        $this->getILOCredentials($server);

        if (!$this->sshConnect($iLOFqdn, $this->iLOUsername, $this->iLOPassword)) {
            return $this->renderView($this->_viewData);
        }

        // disable network boot
        $output = "";
        if (!$this->sshCommand("show /system1/bootconfig1" . PHP_EOL, $output)) {
            return $this->renderView($this->_viewData);
        }
        if (preg_match("/oemhp_uefibootsource/", $output)) {
            // UEFI Bios
            $output = "";
            if (!$this->sshCommand("set /system1/bootconfig1/oemhp_uefibootsource5 bootorder=1" . PHP_EOL, $output)) {
                return $this->renderView($this->_viewData);
            }
        } else {
            $output = "";
            if (!$this->sshCommand("set /system1/bootconfig1/bootsource1 bootorder=1" . PHP_EOL, $output)) {
                return $this->renderView($this->_viewData);
            }
        }

        // increase the iLO idle timeout
        if (!$this->sshCommand("cd /map1/config1" . PHP_EOL, $output)) {
            return $this->renderView($this->_viewData);
        }
        if (!$this->sshCommand("set oemhp_timeout=60" . PHP_EOL, $output)) {
            return $this->renderView($this->_viewData);
        }

        // set ISO URL and boot once
        $isoServer    = "http://" . $this->_config['chef'][$server->getChefServer()]['isoServer'] . "/ISOs";
        $output = "";
        /*
        if (!$this->sshCommand("vm cdrom insert " . $this->_config['isoServerUrl'] . "/" . $standalone->getIso() . PHP_EOL, $output)) {
            return $this->renderView($this->_viewData);
        }
        */
        if (!$this->sshCommand("vm cdrom insert " . $isoServer . "/" . $standalone->getIso() . PHP_EOL, $output)) {
            return $this->renderView($this->_viewData);
        }
        if (!$this->sshCommand("vm cdrom set boot_once" . PHP_EOL, $output)) {
            return $this->renderView($this->_viewData);
        }

        // check power status. If off, then just power on, if on, then reset
        $powerStatus = $this->getPowerStatus($iLOFqdn);
        if ($powerStatus == 'On') {
            if (!$this->powerOffHardware($iLOFqdn)) {
                return $this->renderView(array(
                    "success" => false,
                    "error" => "Could not power off {$server->getName()}",
                    "logLevel" => Logger::ERR,
                    "logOutput" => "Could not power off {$server->getName()}"
                ));
            }
        }
        if (!$this->powerOnHardware($iLOFqdn)) {
            return $this->renderView(array(
                "success" => false,
                "error" => "Could not power on {$server->getName()}",
                "logLevel" => Logger::ERR,
                "logOutput" => "Could not power on {$server->getName()}"
            ));
        }

        $this->sshDisconnect();

        $server
            ->setStatus('Building')
            ->setStatusText("Booting ISO image...");

        $this->consoleWatcherFile .= "." . $server->getName();
        if (file_exists($this->consoleWatcherFile)) {
            unlink($this->consoleWatcherFile);
        }
        $watcher = fopen($this->consoleWatcherFile, "w");
        chmod($this->consoleWatcherFile, 0666);

        // start the console logging process
        fwrite($watcher, "[" . (date("Y-m-d h:i:s")) . "] Console Watcher Log: " . $server->getName() . "\n");
        fwrite($watcher, "[" . (date("Y-m-d h:i:s")) . "] Prompt set to " . $this->_prompt . "\n");
        fwrite($watcher, "[" . (date("Y-m-d h:i:s")) . "] Starting the console watcher...\n");
        fclose($watcher);
        $this->startLogging($server);

        return $this->renderView(array(
            "success" => true,
            "logLevel" => Logger::NOTICE,
            "logOutput" => "Reset system performed on {$server->getName()}"
        ));
    }


    // *****************************************************************************************************************
    // Private methods
    // *****************************************************************************************************************

    private function getILOCredentials(Model\NMServer $server) {
        // select the iLO credentials based upon the business service
        if ($server->getBusinessServiceName() && stripos($server->getBusinessServiceName(), "ultra") !== false) {
            $this->iLOUsername = $this->_config['iLOCredentials']['ultradns']['username'];
            $this->iLOPassword = $this->_config['iLOCredentials']['ultradns']['password'];
        } else {
            $this->iLOUsername = $this->_config['iLOCredentials']['standard']['username'];
            $this->iLOPassword = $this->_config['iLOCredentials']['standard']['password'];
        }
    }

    private function updateKickstartFile(Model\NMServer $server, $ksTemplateFile) {
        // first, use the ks template file and update with this server's info
        $tmplFile = file_get_contents($ksTemplateFile);
        $recs = explode("\n", $tmplFile);

        $outArray = array();
        foreach ($recs as $rec) {
            $rec = rtrim($rec);

            if (preg_match("/(.*)__HOSTNAME__(.*)/", $rec, $m)) {
                $rec = $m[1] . $server->getName() . $m[2];
            }
            if (preg_match("/(.*)__IPADDRESS__(.*)/", $rec, $m)) {
                $rec = $m[1] . $server->getIpAddress() . $m[2];
            }
            if (preg_match("/(.*)__NETMASK__(.*)/", $rec, $m)) {
                $rec = $m[1] . $server->getSubnetMask() . $m[2];
            }
            if (preg_match("/(.*)__GATEWAY__(.*)/", $rec, $m)) {
                $rec = $m[1] . $server->getGateway() . $m[2];
            }
            if (preg_match("/(.*)__NAMESERVER__(.*)/", $rec, $m)) {
                $rec = $m[1] . $this->_config['ksNameServer'] . $m[2];
            }
            if (preg_match("/(.*)__ISO_URL__(.*)/", $rec, $m)) {
                // if there is a CenOS ISO URL defined in the chef server config, use that.
                // otherwise use the default
                if (array_key_exists('centOSIsoUrl', $this->_config['chef'][$server->getChefServer()])) {
                    $rec = $m[1] . $this->_config['chef'][$server->getChefServer()]['centOSIsoUrl'] . $m[2];
                } else {
                    $rec = $m[1] . $this->_config['centOSIsoUrl'] . $m[2];
                }
            }
            if (preg_match("/(.*)__REPO_URL__(.*)/", $rec, $m)) {
                // if there is a REPO URL defined in the chef server config, use that.
                // otherwise use the default
                if (array_key_exists('repoUrl', $this->_config['chef'][$server->getChefServer()])) {
                    $rec = $m[1] . $this->_config['chef'][$server->getChefServer()]['repoUrl'] . $m[2];
                } else {
                    $rec = $m[1] . $this->_config['centOSIsoUrl'] . $m[2];
                }
            }
            if (preg_match("/(.*)__CHEF_ENVIRONMENT__(.*)/", $rec, $m)) {
                $rec = $m[1] . $server->getChefEnv() . $m[2];
            }
            if (preg_match("/(.*)__CHEF_ROLE__(.*)/", $rec, $m)) {
                $rec = $m[1] . $server->getChefRole() . $m[2];
            }

            if (strpos($rec, "__VALIDATION_PEM__") !== false) {
                $clientConfigDir = __DIR__ . "/../../../../../public/clientconfig/" . $server->getChefServer();
                $fileString = file_get_contents($clientConfigDir . "/validation.pem");
                $fileArray = explode(PHP_EOL, $fileString);
                foreach ($fileArray as $l) {
                    $outArray[] = $l;
                }
            }
            else if (strpos($rec, "__CLIENT_RB__") !== false) {
                $clientConfigDir = __DIR__ . "/../../../../../public/clientconfig/" . $server->getChefServer();
                $fileString = file_get_contents($clientConfigDir . "/client.rb");
                $fileArray = explode(PHP_EOL, $fileString);
                foreach ($fileArray as $l) {
                    $outArray[] = $l;
                }
            }
            else if (strpos($rec, "__SERVER_CRT__") !== false) {
                $clientConfigDir = __DIR__ . "/../../../../../public/clientconfig/" . $server->getChefServer();
                $fileString = file_get_contents($clientConfigDir . "/server.crt");
                $fileArray = explode(PHP_EOL, $fileString);
                foreach ($fileArray as $l) {
                    $outArray[] = $l;
                }
            }
            else {
                $outArray[] = $rec;
            }
        }
        return implode("\n", $outArray);
    }

    private function getMacAddress($iLOFqdn) {
        // Since we cannot return a ViewModel using $this->renderView, sshConnect will set the class property, $this->_viewData, if there
        // is an error and then return false. We check the return status and then call
        // $this->renderView($this->_viewData) if an error is found. Not happy about this solution, but it works for now.

        if (!$this->sshConnect($iLOFqdn, $this->iLOUsername, $this->iLOPassword)) {
            return false;
        }

        $output = "";
        if (!$this->sshCommand("show /system1/network1/Integrated_NICs" . PHP_EOL, $output)) {
            return false;
        }
        $this->sshDisconnect();

        // loop the the lines of the output and parse out the mac address if it can be found
        $lines = explode("\n", $output);
        foreach ($lines as $line) {
            if (preg_match("/Port1NIC_MACAddress=([0-9a-f:]+)/", $line, $m)) {
                return $m[1];
            }
        }
        return 0;
    }

    private function getMacAddresses($iLOFqdn) {
        // Since we cannot return a ViewModel using $this->renderView, sshConnect will set the class property, $this->_viewData, if there
        // is an error and then return false. We check the return status and then call
        // $this->renderView($this->_viewData) if an error is found. Not happy about this solution, but it works for now.

        if (!$this->sshConnect($iLOFqdn, $this->iLOUsername, $this->iLOPassword)) {
            return false;
        }

        $output = "";
        if (!$this->sshCommand("show /system1/network1/Integrated_NICs" . PHP_EOL, $output)) {
            return false;
        }
        $this->sshDisconnect();

        // loop the the lines of the output and parse out the mac address if it can be found
        $lines = explode("\n", $output);
        $macs = array();
        foreach ($lines as $line) {
            if (preg_match("/Port(\d+)NIC_MACAddress=([0-9a-f:]+)/", $line, $m)) {
                $macs[] = array(
                    "port" => $m[1],
                    "address" => $m[2],
                    "display" => "[Port " . $m[1] . "] " . $m[2]
                );
            }
        }
        return $macs;
    }
    /**
     * @param $iLO
     * @return bool
     */
    private function powerOnHardware($iLO) {
        if (!$this->sshCommand("power on" . PHP_EOL, $output)) {
            return false;
        }

        // check the status of the hardware until it has successfully powered on
        $timeout = 30;
        $secs = 0;
        while ($secs < $timeout) {
            sleep(2);
            if (!$powerStatus = $this->getPowerStatus($iLO)) {
                return false;
            }
            if ($powerStatus == "On") {
                return true;
            }
            $secs += 2;
        }
        return false;
    }
    
    /**
     * @param $iLO
     * @return bool
     */
    private function powerOffHardware($iLO)
    {
        $output = '';
        if (!$this->sshCommand("power off hard" . PHP_EOL, $output)) {
            return false;
        }

        // check the status of the blade until it has successfully powered down
        $timeout = 30;
        $secs = 0;
        while ($secs < $timeout) {
            sleep(2);
            if (!$powerStatus = $this->getPowerStatus($iLO)) {
                return false;
            }
            if ($powerStatus == "Off") {
                return true;
            }
            $secs += 2;
        }
        return false;
    }

    /**
     * @param $iLO
     * @return bool
     */
    private function getPowerStatus($iLO)
    {
        $output = '';
        if (!$this->sshCommand("power" . PHP_EOL, $output)) {
            return false;
        }

        if (preg_match("/power: server power is currently: (On|Off)/", $output, $m)) {
            $powerStatus = $m[1];
        } else {
            $this->sshDisconnect();
            $this->_viewData = array(
                "success" => false,
                "error" => "Could not determine power status of {$iLO}",
                "logLevel" => Logger::ERR,
                "logOutput" => "Could not determine power status of {$iLO}"
            );
            return false;
        }
        return $powerStatus;
    }

    private function getISOServer($chefServerName) {
        $isoServer = $this->_config['chef'][$chefServerName]['isoServer'];
        // if iso server is an IP then return
        if (preg_match("/\d+\/.\d+\/.\d+\/.\d+/", $isoServer)) {
            return $isoServer;
        }

        // otherwise, convert to IP Address
        $resolver = new Net_DNS2_Resolver();
        $response = $resolver->query($isoServer);

        if (!property_exists($response, 'answer')) {
            // just return the name
            return $isoServer;
        }

        $found = false;
        /** @noinspection PhpUndefinedFieldInspection */
        $answers = $response->answer;
        for ($i = 0; $i < count($answers); $i++) {
            if (get_class($answers[$i]) == "Net_DNS2_RR_A") {
                $found = true;
                break;
            }
        }
        if (!$found) {
            // just return the name
            return $isoServer;
        }
        $answer = $answers[$i];

        if (!property_exists($answer, "address")) {
            // just return the name
            return $isoServer;
        }
        $ipAddress = $answer->address;
        return $ipAddress;
    }


    /*
    private function getILOFqdn($fqdn) {
        // construct the iLO console DNS name by adding "-con"
        $fields = explode('.', $fqdn);
        $iLOFqdn = $fields[0] . "-con.";
        array_shift($fields);
        $iLOFqdn = $iLOFqdn . implode('.', $fields);
        return $iLOFqdn;
    }

    private function testILOFqdn($iLOFqdn) {
        // check to be sure we have the iLO console in DNS
        $resolver = new Net_DNS2_Resolver(array('nameservers' => $this->_config['dns']['nameservers']));
        try {
            $result = $resolver->query($iLOFqdn, 'A');
        } catch (\Net_DNS2_Exception $e) {
            $this->_viewData = array(
                "success" => false,
                "error" => "Could not find {$iLOFqdn} in DNS",
                "macAddress" => "",
                "logLevel" => Logger::ERR,
                "logOutput" => "Could not find {$iLOFqdn} in DNS"
            );
            return false;
        }
        return $result;
    }
    */


    /**
     * @param Model\NMServer $server
     */
    private function startLogging(Model\NMServer $server)
    {
        if (!preg_match("/{$server->getName()}/", $this->consoleWatcherFile)) {
            $this->consoleWatcherFile .= "." . $server->getName();
        }
        $watcher = fopen($this->consoleWatcherFile, "a");

        $pid = $this->getConsoleProcess($server->getId());
        if (!$pid) {
            fwrite($watcher, "[" . (date("Y-m-d h:i:s")) . "] Watcher started\n");
            fclose($watcher);
            chmod($this->consoleWatcherFile, 0666);
            $command = "nohup php " . $this->consoleExecDir . "/" . $this->consoleExecFile . " -i " . $server->getId() . " > " . $this->consoleWatcherErrorFile . " 2>&1 &";
            exec($command);
        } else {
            fwrite($watcher, "[" . (date("Y-m-d h:i:s")) . "] Watcher already running; killing\n");
            $this->stopLogging($server);
            fwrite($watcher, "[" . (date("Y-m-d h:i:s")) . "] Watcher started\n");
            fclose($watcher);
            chmod($this->consoleWatcherFile, 0666);
            $command = "nohup php " . $this->consoleExecDir . "/" . $this->consoleExecFile . " -i " . $server->getId() . " > " . $this->consoleWatcherErrorFile . " 2>&1 &";
            exec($command);
        }
    }

    /**
     * @param Model\NMServer $server
     */
    private function stopLogging(Model\NMServer $server)
    {
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
     * @param string $fqdn
     * @param string $username
     * @param string $password
     * @return bool
     */
    private function sshConnect($fqdn, $username, $password)
    {
        try {
            $this->ssh = new SSH2($fqdn);
        } catch (\Exception $e) {
            $this->_viewData = array(
                "success" => false,
                "error" => "ssh connection to {$fqdn} failed",
                "logLevel" => Logger::ERR,
                "logOutput" => "ssh connection to {$fqdn} failed"
            );
            return false;
        }

        try {
            $ok = $this->ssh->loginWithPassword($username, $password);
        } catch (\Exception $e) {
            $this->_viewData = array(
                "success" => false,
                "error" => "Login to {$fqdn} failed: " . $e->getMessage(),
                "logLevel" => Logger::ERR,
                "logOutput" => "Login to {$fqdn} failed"
            );
            return false;
        }
        if (!$ok) {
            $this->_viewData = array(
                "success" => false,
                "error" => "Login to {$fqdn} failed",
                "logLevel" => Logger::ERR,
                "logOutput" => "Login to {$fqdn} failed"
            );
            return false;
        }

        $stream = $this->ssh->getShell(false, 'vt102', Array(), 4096);
        if (!$stream) {
            $this->_viewData = array(
                "success" => false,
                "error" => "Obtaining shell on {$fqdn} failed",
                "logLevel" => Logger::ERR,
                "logOutput" => "Obtaining shell on {$fqdn} failed"
            );
            return false;
        }

        // set the socket timeout to 30 seconds
        stream_set_timeout($stream, $this->sshTimeout);

        $buffer = '';
        $ok = $this->ssh->waitPrompt($this->_prompt, $buffer, $this->sshTimeout);
        if (!$ok) {
            $this->_viewData = array(
                "success" => false,
                "error" => "Obtaining shell on {$fqdn} failed",
                "logLevel" => Logger::ERR,
                "logOutput" => "Obtaining shell on {$fqdn} failed"
            );
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
            $this->_viewData = array(
                "success" => false,
                "error" => "'" . $command . "' command failed",
                "logLevel" => Logger::ERR,
                "logOutput" => "'" . $command . "' command failed"
            );
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
