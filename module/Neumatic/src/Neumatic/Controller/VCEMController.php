<?php

/**
 * Neumatic VCEM Controller
 *
 * @author Rob Callahan <rob.callahan@neustar.biz>
 */

namespace Neumatic\Controller;

use Neumatic\Model;
use STS\HPSIM;

use Zend\Json\Server\Exception\ErrorException;
use Zend\View\Model\JsonModel;
use Zend\Mvc\MvcEvent;

use STS\Util\SSH2;

/**
 * This controller runs commands on the HP SIM Windows host to configure blade profiles
 * It needs to ssh to the host to run the vcemcli.exe command with various options
 *
 * @package Neumatic\Controller
 */
class VCEMController extends Base\BaseController
{

    /**
     * Login username for the chassis from config.
     * @var
     */
    protected $vcemUsername;
    /**
     * Login password for the chassis from config.
     * @var
     */
    protected $vcemPassword;
    /**
     * HP SIM Server Name
     * @var
     */
    protected $vcemServerName;

    /**
     * @var Model\NMServer
     */
    protected $nmServer;
    /**
     * @var Model\NMBlade
     */
    protected $nmBlade;
    /**
     * @var HPSIM\HPSIMBlade
     */
    protected $hpsimBlade;
    /**
     * @var HPSIM\HPSIMChassis
     */
    protected $hpsimChassis;

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

    /**
     * This method is always called first before any action method and is a good place for reading
     * the config and making some useful assignments that'll be used later on.
     *
     * @param MvcEvent $e
     * @return mixed
     */
    public function onDispatch(MvcEvent $e)
    {
        $this->vcemServerName = $this->_config['vcemServerName'];
        $this->vcemUsername = $this->_config['vcemUsername'];
        $this->vcemPassword = $this->_config['vcemPassword'];
        $this->_prompt = 'simadmin>$';

        $this->sshTimeout = 30;
        $this->ssh = null;
        $this->stream = null;

        $serverId = $this->params()->fromRoute('param1');

        $nmServerTable = new Model\NMServerTable($this->_config);
        $this->nmServer = $nmServerTable->getById($serverId);

        $nmBladeTable = new Model\NMBladeTable($this->_config);
        $this->nmBlade = $nmBladeTable->getByServerId($this->nmServer->getId());

        $hpsimBladeTable = new HPSIM\HPSIMBladeTable($this->_config);
        $this->hpsimBlade = $hpsimBladeTable->getById($this->nmBlade->getBladeId());

        $hpsimChassisTable = new HPSIM\HPSIMChassisTable($this->_config);
        $this->hpsimChassis = $hpsimChassisTable->getById($this->hpsimBlade->getChassisId());

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
     * Get the power status of a blade given a chassis and slot(bay)
     *
     * @return JsonModel
     */
    public function getBladePowerStatusAction()
    {
        try {
            $this->vcemConnect();
        } catch (\ErrorException $e) {
            return $this->renderView(array("success" => false, "message" => $e->getMessage()));
        }

        $output = "";
        try {
            $this->sshCommand("vcemcli.exe -show power-status -enclosurename {$this->hpsimChassis->getDeviceName()} -bayname {$this->hpsimBlade->getSlotNumber()}", $output);
        } catch (\ErrorException $e) {
            return $this->renderView(array("success" => false, "message" => $e->getMessage()));
        }

        $this->sshDisconnect();

        if (preg_match("/Power Status: (\w+)/", $output, $m)) {
            return $this->renderView(array("success" => true, "powerStatus" => $m[1]));
        } else {
            return $this->renderView(array("success" => false, "message" => "Unable to determine power status", "output" => $output));
        }
    }


    // *****************************************************************************************************************
    // Private methods
    // *****************************************************************************************************************

    /**
     * @throws \Exception
     */
    private function vcemConnect()
    {
        try {
            $this->ssh = new SSH2($this->vcemServerName);
        } catch (\Exception $e) {
            throw new \Exception("ssh connection to {$this->vcemServerName} failed: " . $e->getMessage());
        }

        try {
            $ok = $this->ssh->loginWithPassword($this->vcemUsername, $this->vcemPassword);
        } catch (\Exception $e) {
            throw new \Exception("Login to {$this->vcemServerName} failed: " . $e->getMessage());
        }
        if (!$ok) {
            throw new \Exception("Login to {$this->vcemServerName} failed");
        }

        $stream = $this->ssh->getShell(false, 'vt102', Array(), 4096);
        if (!$stream) {
            throw new \Exception("Obtaining shell on {$this->vcemServerName} failed");
        }

        // set the socket timeout to 30 seconds
        stream_set_timeout($stream, $this->sshTimeout);

        $buffer = '';
        $ok = $this->ssh->waitPrompt($this->_prompt, $buffer, $this->sshTimeout);
        if (!$ok) {
            throw new \Exception("Getting shell prompt on {$this->vcemServerName} failed");
        }
    }

    /**
     * @param $command
     * @param $output
     * @throws \Exception
     */
    private function sshCommand($command, &$output)
    {
        $buffer = '';
        $this->ssh->writePrompt($command);
        $ok = $this->ssh->waitPrompt($this->_prompt, $buffer, $this->sshTimeout);
        if (!$ok) {
            throw new \Exception("'" . $command . "' command failed");
        }
        $output = $buffer;
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
