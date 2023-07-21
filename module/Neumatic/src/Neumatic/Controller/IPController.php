<?php

namespace Neumatic\Controller;

use Neumatic\Model;

use Zend\View\Model\JsonModel;
use Zend\Mvc\MvcEvent;
use Zend\Log\Logger;

use STS\Util\SSH2;

require_once ("/usr/share/pear/Net/DNS2.php");
require_once ("/usr/share/pear/Net/IPv4.php");
require_once ("/usr/share/pear/Net/Ping.php");

use Net_Ping;
use Net_IPv4;
use Net_DNS2_Resolver;

class IPController extends Base\BaseController {

	/** @var  SSH2 $ssh */
	private $_ssh;
	private $_sshTimeout;
	private $_prompt;

	private $_dnsConfig;
	private $_resolver;

	public function onDispatch(\Zend\Mvc\MvcEvent $e) {
		$this -> _dnsConfig = $this -> _config['dns'];

		$this -> _sshTimeout = 30;
		$this -> _ssh = null;
		$this -> _prompt = "com> $";
		$this -> _viewData = array();

		// instantiate DNS2 with an array of nameservers
		$this -> _resolver = new Net_DNS2_Resolver( array('nameservers' => $this -> _dnsConfig['nameservers']));

		return parent::onDispatch($e);
	}

	public function indexAction() {

		return $this -> renderview(array("error" => "This controller has no output from index. Eventually I would like to display the documentation here."));
	}

	/**
	 * Return a list of distributions switches from the dist_switch table for the given location name
	 * eg., Sterling, Charlotte or Denver
	 * @return JsonModel
	 */
	public function getDistSwitchesByLocationAction() {
		$location = $this -> params() -> fromRoute('param1');

		// strip off all but location name
		if (preg_match("/(\w+)-/", $location, $m)) {
			$location = $m[1];
		}
		$dsTable = new Model\NMDistSwitchTable($this -> _config);
		$dsSwitches = $dsTable -> getDistSwitchesByLocation($location);
		return $this -> renderView(array("success" => true, "distSwitches" => $dsSwitches, "logLevel" => Logger::DEBUG, "logOutput" => count($dsSwitches) . " switches returned"));
	}

	/**
	 * Return a list of VLANs from the vlan table for the given dist switch
	 * eg., Sterling Lab, Sterling General Purpose, Sterling NPAC, etc
	 * @return JsonModel
	 */
	public function getSwitchVLansAction() {
		$distSwitch = $this -> params() -> fromRoute('param1');

		$dsTable = new Model\NMDistSwitchTable($this -> _config);
		$dsSwitches = $dsTable -> getDistSwitchesByName($distSwitch);

		$vlanTable = new Model\NMVLANTable($this -> _config);
		$vlans = array();
		foreach ($dsSwitches as $dsSwitch) {
			$results = $vlanTable -> getAllByDistSwitchId($dsSwitch -> getId());
			$vlans = array_merge($vlans, $results);
		}
		$data = array();
		/** @var Model\NMVLAN $vlan */
		foreach ($vlans as $vlan) {
			$data[] = array("id" => $vlan -> getId(), "vlanId" => $vlan -> getVlanId(), "name" => $vlan -> getName(), "ipSubnet" => $vlan -> getNetwork(), "subnetMask" => $vlan -> getNetmask(), "gateway" => $vlan -> getGateway(), "displayValue" => "[" . $vlan -> getVlanId() . "] " . $vlan -> getName());
		}
		return $this -> renderView(array("success" => true, "vlans" => $data, "logLevel" => Logger::DEBUG, "logOutput" => count($vlans) . " vlans returned"));
	}

	/**
	 * @return \Zend\View\Model\JsonModel
	 */
	public function getVlanDetailsByVlanIdAndDistSwitchNameAction() {
		$vlanId = $this -> params() -> fromRoute('param1');
		$distSwitchParam = $this -> params() -> fromRoute('param2');

		// if vlanId in the form of vlan32, then parse the number value at the end and drop vlan string
		if (preg_match("/[Vv][Ll][Aa][Nn](\d+)/", $vlanId, $m)) {
			$vlanId = $m[1];
		}
		// convert dist switch name if necessary. vmware dist switches will be named by their esx cluster
		// so, for example, ST_GenAB would be mapped to "Sterling General Purpose"
		// yes, this is ugly, but we're working on a mapping which should simplify this in the future
		// we are? hmm I need to make a note of this somewhere
		if (preg_match("/(CH|ST|DE|AT)_([\w_]+)$/", $distSwitchParam, $m)) {
			$site = $m[1];
			switch ($site) {
				case "CH" :
					$site = "Charlotte";
					break;
				case "DE" :
					$site = "Denver";
					break;
				case "ST" :
					$site = "Sterling";
					break;
				case "AT" :
					$site = "Amsterdam";
					break;
				default :
					$site = "Sterling";
			}
			$abbrev = $m[2];
			switch ($abbrev) {
				case "GenAB" :
					$distSwitchName = $site . " General Purpose";
					break;
				case "IHN" :
					$distSwitchName = $site . " " . $abbrev;
					break;
				case "LP" :
					$distSwitchName = $site . " LEAP";
					break;
				case "OMS" :
					$distSwitchName = $site . " " . $abbrev;
					break;
				case "REG" :
					$distSwitchName = $site . " Registry";
					break;
				case "Lab_Cluster" :
					$distSwitchName = "Sterling Lab";
					break;
				case "NPAC_DEV" :
					$distSwitchName = $site . " NPAC";
					break;
				default :
					$distSwitchName = $site;
			}
		} else if ($distSwitchParam == "LAB_Cluster") {
			$distSwitchName = "Sterling Lab";
		} else {
			$distSwitchName = $distSwitchParam;
		}

		$dsTable = new Model\NMDistSwitchTable($this -> _config);
		$dsSwitches = $dsTable -> getDistSwitchesByName($distSwitchName);
		$dsIds = array();
		foreach ($dsSwitches as $dsSwitch) {
			$dsIds[] = $dsSwitch -> getId();
		}
		$vlanTable = new Model\NMVLANTable($this -> _config);

		
		$vlan = $vlanTable -> getByDistSwitchIdsAndVlanId($dsIds, $vlanId);

		#return $this->renderView(array("ds name" => $distSwitchName, "vlanId" => $vlanId, "vlan" => $vlan->toObject()));

		if ($vlan -> getId()) {
			$data = array("distSwitch" => $distSwitchName, "vlanId" => $vlan -> getVlanId(), "ipSubnet" => $vlan -> getNetwork(), "subnetMask" => $vlan -> getNetmask(), "gateway" => $vlan -> getGateway());
			return $this -> renderView(array("success" => true, "vlanData" => $data));
		} else {
			return $this -> renderView(array("success" => false, "error" => "Could not obtain VLAN details for " . $distSwitchName . " and VLAN Id " . $vlanId, "logLevel" => Logger::ERR, "logOutput" => "Could not obtain VLAN details for " . $distSwitchName . " and VLAN Id " . $vlanId));
		}
	

	}

	/**
	 * Takes a network and netmask as input, gets all the IPs in the network, loops over each checking DNS and pinging
	 * returning the first IP that is not in DNS and does not ping.
	 *
	 * @return JsonModel
	 */
	public function getNextAvailableIPAction() {
		$networkIP = $this -> params() -> fromRoute('param1');
		$netmask = $this -> params() -> fromRoute('param2');

		if ($networkIP == "" || $netmask == "") {
			return $this -> renderView(array("success" => false, "error" => "Network or netmask is missing"));
		}

		// assign the net ip and netmask and calculate the bitmask
		$ipv4 = new Net_IPv4();
		$ipv4 -> ip = $networkIP;
		$ipv4 -> netmask = $netmask;
		$ipv4 -> calculate();
		$network = $ipv4 -> network;
		$bitmask = $ipv4 -> bitmask;

		// using the network and bitmask, get the number if address and their min and max values
		if (($min = ip2long($network)) !== false) {
			$max = ($min | (1<<(32 - $bitmask)) - 1);
			$numAddresses = $max - $min;
		} else {
			return $this -> renderView(array("success" => false, "error" => 'Could not obtain an IP address. ip2long(' . $network . ') failed', "logLevel" => Logger::ERR, "logOutput" => 'Could not obtain an IP address. ip2long(' . $network . ') failed'));
		}

		// define the starting address depending on the size of the network
		/*
		 if ($numAddresses < 50) {
		 $ipStartOffset = 11;
		 } else if ($numAddresses < 100) {
		 $ipStartOffset = 21;
		 } else {
		 $ipStartOffset = 51;
		 }
		 */
		$ipStartOffset = 21;

		// instantiate ping and set arguments of 1 count and time of 1 second (makes it a bit faster)
		$ping = Net_Ping::factory();
		$ping -> setArgs(array('count' => 1, 'timeout' => 1));

		// create an array of IP addresses for this network from the starting IP address
		$addresses = array();
		if (($min = ip2long($network)) !== false) {
			$max = ($min | (1<<(32 - $bitmask)) - 1);
			for ($i = $min + $ipStartOffset; $i < $max; $i++)
				$addresses[] = long2ip($i);
		}

		foreach ($addresses as $addr) {
			try {
				$this -> _resolver -> query($addr, 'PTR');
			} catch (\Net_DNS2_Exception $e) {
				if ($e -> getMessage() == "DNS request failed: The domain name referenced in the query does not exist.") {
					$result = $ping -> ping($addr);
					if ($result -> getReceived() == 0) {
						return $this -> renderView(array("success" => true, "ipAddress" => $addr, "logLevel" => Logger::INFO, "logOutput" => "IP address found: " . $addr));
					}
				} else {
					return $this -> renderView(array("success" => false, "error" => $e -> getMessage(), "trace" => $e -> getTraceAsString(), "logLevel" => Logger::ERR, "logOutput" => $e -> getMessage() . " Trace: " . $e -> getTraceAsString()));
				}
			}
		}
		return $this -> renderview(array("success" => false, "error" => "No IP address found", "logLevel" => Logger::ERR, "logOutput" => "No IP address found for network " . $networkIP));
	}

	/**
	 * Takes and FQDN and IP address and adds the A and PTR records to DNS
	 *
	 * @return JsonModel
	 */
	public function addToDNSAction() {
		// server id passed as param
		$serverId = $this -> params() -> fromRoute('param1');

		// get the server from our local DB
		$serverTable = new Model\NMServerTable($this -> _config);
		$server = $serverTable -> getById($serverId);

		// get fqdn and IP
		$fqdn = $server -> getName();
		$ipAddress = $server -> getIpAddress();

		if ($fqdn == "" || $ipAddress == "") {
			return $this -> renderview(array("success" => false, "error" => "FQDN or IP address is missing", "logLevel" => Logger::ERR, "logOutput" => "FQDN or IP address is missing"));
		}

		// first check to see if either the fqdn or ip address is already in dns. If so,
		// check if name and address is the same. If so, that's ok. If not, return an error
		if ($result = $this -> hostInDNS($fqdn)) {
			if ($result) {
				$answer = $result -> answer[0];
				if ($answer -> name == $fqdn && $answer -> address == $ipAddress) {
					return $this -> renderView(array("success" => true, "message" => "Host {$fqdn} and IP {$ipAddress} already exists in DNS", "logLevel" => Logger::INFO, "logOutput" => "Host {$fqdn} and IP {$ipAddress} already exists in DNS"));
				} else {
					return $this -> renderView(array("success" => false, "error" => "Host {$fqdn} and IP {$ipAddress} already exists in DNS and are not tied to each other", "logLevel" => Logger::ERR, "logOutput" => "Host {$fqdn} and IP {$ipAddress} already exists in DNS"));
				}
			}
		} else {
			if ($this -> ipInDNS($ipAddress)) {
				return $this -> renderView(array("success" => false, "error" => "IP address {$ipAddress} already exists in DNS", "logLevel" => Logger::ERR, "logOutput" => "IP address {$ipAddress} already exists in DNS"));
			}
		}

		// if old server name exists, delete it first
		if ($server -> getOldName() && $server -> getName() != $server -> getOldName()) {
			if (!$this -> deleteFromDNS($server -> getOldName(), $server -> getIpAddress())) {
				return $this -> renderView($this -> _viewData);
			}
		}

		if (!$this -> sshConnect($this -> _dnsConfig['server'], $this -> _dnsConfig['username'], $this -> _dnsConfig['password'])) {
			return $this -> renderView($this -> _viewData);
		}
		$output = "";
		if (!$this -> sshCommand("/opt/dnscli/bin/add-arec-i " . $fqdn . " " . $ipAddress, $output)) {
			return $this -> renderView($this -> _viewData);
		}
		$this -> sshDisconnect();

		// if the output of add-arec-i is not empty, there was an error and we must report it.
		// normally this command will not produce output if it is successful
		/*
		 if ($output != '') {
		 return $this->renderView(array(
		 "success"   => false,
		 "error"     => "DNS A Rec was not added for " . $fqdn . " " . $ipAddress . ". Return message: " . $output,
		 "logLevel"  => Logger::ERR,
		 "logOutput" => "DNS A Rec was not added for " . $fqdn . " " . $ipAddress . ". Return message: " . $output,
		 "parameters" => "[serverName: {$fqdn}]"
		 ));
		 }
		 */

		// sleep for 1 second to insure that it got propagated
		sleep(1);
		// now check to be sure that the host got added to DNS
		$result = $this -> hostInDNS($fqdn);
		if (!$result) {
			return $this -> renderView(array("success" => false, "error" => "DNS A Rec was not added for " . $fqdn . " " . $ipAddress, "output" => $output, "logLevel" => Logger::ERR, "logOutput" => "DNS A Rec was not added for " . $fqdn . " " . $ipAddress, "parameters" => "[serverName: {$fqdn}]"));
		}

		return $this -> renderView(array("success" => true, "output" => $output, "logLevel" => Logger::NOTICE, "logOutput" => "DNS A Rec added for " . $fqdn . " " . $ipAddress, "parameters" => "[serverName: {$fqdn}]"));
	}

	/**
	 * Takes and FQDN and IP address and remove the A and PTR records from DNS
	 *
	 * @return JsonModel
	 */
	public function deleteFromDNSAction() {
		// server id passed as param
		$serverId = $this -> params() -> fromRoute('param1');

		// get the server from our local DB
		$serverTable = new Model\NMServerTable($this -> _config);
		$server = $serverTable -> getById($serverId);

		// get fqdn and IP
		$fqdn = $server -> getName();
		$ipAddress = $server -> getIpAddress();

		// if this is a pool server, we don't want to remove it from DNS, so just return successful
		$poolTable = new Model\NMServerPoolTable($this -> _config);
		$poolServer = $poolTable -> getByServerId($server -> getId());
		if ($poolServer -> getId()) {
			return $this -> renderView(array("success" => true, "message" => "This is a pool server. DNS removal prevented", "logLevel" => Logger::INFO, "logOutput" => "This is a pool server. DNS removal prevented"));
		}

		if (!$this -> deleteFromDNS($fqdn, $ipAddress)) {
			return $this -> renderView($this -> _viewData);
		}

		return $this -> renderView(array("success" => true, "logLevel" => Logger::NOTICE, "logOutput" => "DNS A Rec deleted for " . $server -> getName() . " " . $server -> getIpAddress(), "parameters" => "[serverName: {$fqdn}]"));
	}

	public function deleteFromDNSByFqdnAction() {
		$fqdn = $this -> params() -> fromRoute('param1');
		$ipAddress = gethostbyname($fqdn);

		if (!$this -> deleteFromDNS($fqdn, $ipAddress)) {
			return $this -> renderView($this -> _viewData);
		}

		return $this -> renderView(array("success" => true, "logLevel" => Logger::NOTICE, "logOutput" => "DNS A Rec deleted for $fqdn $ipAddress", "parameters" => "[serverName: {$fqdn}]"));

	}

	private function deleteFromDNS($fqdn, $ipAddress) {
		// eh, if we don't have the details then we can't delete. Just ignore
		if ($fqdn == "" || $ipAddress == "") {
			$this -> _viewData = array("success" => true, "message" => "FQDN or IP address is missing", "logLevel" => Logger::INFO, "logOutput" => "FQDN or IP address is missing");
			return true;
		}

		// first check to see if either the fqdn or ip address is in dns. if not we don't need to do anything
		if (!$this -> hostInDNS($fqdn) && !$this -> ipInDNS($ipAddress)) {
			$this -> _viewData = array("success" => true, "message" => "Host and IP already removed from DNS", "logLevel" => Logger::INFO, "logOutput" => "Host and IP already removed from DNS");
			return true;
		}

		if (!$this -> sshConnect($this -> _dnsConfig['server'], $this -> _dnsConfig['username'], $this -> _dnsConfig['password'])) {
			return false;
		}
		$output = "";
		if (!$this -> sshCommand("/opt/dnscli/bin/del-arec-i " . $fqdn . " " . $ipAddress, $output)) {
			return false;
		}
		$this -> sshDisconnect();
		return true;
	}

	/**
	 * @return JsonModel
	 */
	public function deletePtrRecordAction() {
		$ipAddress = $this -> params() -> fromRoute('param1');
		if (!$this -> sshConnect($this -> _dnsConfig['server'], $this -> _dnsConfig['username'], $this -> _dnsConfig['password'])) {
			return $this -> renderView($this -> _viewData);
		}
		$output = "";
		if (!$this -> sshCommand("/opt/dnscli/bin/del-arec-i " . $ipAddress . " " . $ipAddress, $output)) {
			return $this -> renderView($this -> _viewData);
		}
		$this -> sshDisconnect();
		return $this -> renderView(array("success" => true, "output" => $output, "logLevel" => Logger::NOTICE, "logOutput" => "DNS Ptr Rec deleted for " . $ipAddress));
	}

	/**
	 * @return JsonModel
	 */
	public function hostInDNSAction() {
		$hostname = $this -> params() -> fromRoute('param1');
		$result = $this -> hostInDNS($hostname);
		if (!$result) {
			return $this -> renderView(array("success" => true, "ipAddress" => ''));
		} else {
			return $this -> renderView(array("success" => true, "ipAddress" => $result -> answer[0] -> address));
		}
	}

	/**
	 * @return JsonModel
	 */
	public function ipInDNSAction() {
		$ipAddress = $this -> params() -> fromRoute('param1');
		$result = $this -> ipInDNS($ipAddress);
		return $this -> renderView(array("success" => true, "result" => $result));
	}

	/**
	 * @return JsonModel
	 */
	public function addCNameAction() {
		$cname = $this -> params() -> fromRoute('param1');
		$fqdn = $this -> params() -> fromRoute('param2');

		// insure we have both required params
		if ($fqdn == "" || $cname == "") {
			return $this -> renderView(array("success" => false, "error" => "CNAME or FQDN is missing", "logLevel" => Logger::ERR, "logOutput" => "CNAME or FQDN is missing"));
		}

		// check if cname already exists
		if ($result = $this -> cNameInDNS($cname)) {
			if ($result) {
				$answer = $result -> answer[0];
				if ($answer -> name == $cname) {
					return $this -> renderView(array("success" => true, "message" => "CNAME {$cname} already exists in DNS", "logLevel" => Logger::INFO, "logOutput" => "CNAME {$cname} already exists in DNS"));
				}
			}
		}

		if (!$this -> sshConnect($this -> _dnsConfig['server'], $this -> _dnsConfig['username'], $this -> _dnsConfig['password'])) {
			return $this -> renderView($this -> _viewData);
		}
		$output = "";
		if (!$this -> sshCommand("/opt/dnscli/bin/add-cname-i " . $cname . " " . $fqdn, $output)) {
			return $this -> renderView($this -> _viewData);
		}
		$this -> sshDisconnect();
		return $this -> renderView(array("success" => true, "output" => $output, "logLevel" => Logger::NOTICE, "logOutput" => "DNS CName Rec added for " . $cname . " " . $fqdn, "parameters" => "[serverName: {$fqdn}]"));
	}

	/**
	 * @return JsonModel
	 */
	public function deleteCNameAction() {
		$cname = $this -> params() -> fromRoute('param1');
		$fqdn = $this -> params() -> fromRoute('param2');

		// insure we have both required params
		if ($fqdn == "" || $cname == "") {
			return $this -> renderView(array("success" => false, "error" => "CNAME or FQDN is missing", "logLevel" => Logger::ERR, "logOutput" => "CNAME or FQDN is missing"));
		}

		// check if cname exists. return if not
		if ($result = $this -> cNameInDNS($cname)) {
			if ($result) {
				$answer = $result -> answer[0];
				if ($answer -> name != $cname && $answer -> cname != $fqdn) {
					return $this -> renderView(array("success" => true, "message" => "CNAME {$cname} already exists in DNS", "logLevel" => Logger::INFO, "logOutput" => "CNAME {$cname} already exists in DNS"));
				}
			}
		}

		if (!$this -> sshConnect($this -> _dnsConfig['server'], $this -> _dnsConfig['username'], $this -> _dnsConfig['password'])) {
			return $this -> renderView($this -> _viewData);
		}
		$output = "";
		if (!$this -> sshCommand("/opt/dnscli/bin/del-cname-i " . $cname . " " . $fqdn, $output)) {
			return $this -> renderView($this -> _viewData);
		}
		$this -> sshDisconnect();
		return $this -> renderView(array("success" => true, "output" => $output, "logLevel" => Logger::NOTICE, "logOutput" => "DNS CName Rec delete for " . $cname . " " . $fqdn, "parameters" => "[serverName: {$fqdn}]"));
	}

	// *****************************************************************************************************************
	// Private methods
	// *****************************************************************************************************************

	/**
	 * DNS A record lookup for a host
	 *
	 * @param $host
	 * @return bool
	 */
	private function hostInDNS($host) {
		try {
			$result = $this -> _resolver -> query($host, 'A');
		} catch (\Net_DNS2_Exception $e) {
			return false;
		}
		return $result;
	}

	/**
	 * DNS CNAME record lookup for a host
	 *
	 * @param $host
	 * @return bool
	 */
	private function cNameInDNS($host) {
		try {
			$result = $this -> _resolver -> query($host, 'CNAME');
		} catch (\Net_DNS2_Exception $e) {
			return false;
		}
		return $result;
	}

	/**
	 * DNS PTR record lookup for an IP address
	 *
	 * @param $ipAddress
	 * @return bool
	 */
	private function ipInDNS($ipAddress) {
		try {
			$result = $this -> _resolver -> query($ipAddress, 'PTR');
		} catch (\Net_DNS2_Exception $e) {
			return false;
		}
		return $result;
	}

	/**
	 * Since we cannot return a JsonModel using $this->renderView, we'll set the class property, $this->_viewData, if there
	 * is an error and then return false. The caller is responsible for checking the return status and then calling
	 * $this->renderView($this->_viewData) if an error is found. Not happy about this solution, but it works for now.
	 *
	 * @param $host
	 * @param $username
	 * @param $password
	 * @return bool
	 */
	private function sshConnect($host, $username, $password) {
		try {
			$this -> _ssh = new SSH2($host);
		} catch (\Exception $e) {
			$this -> _viewData = array("success" => false, "error" => "ssh connection to {$host} failed", "logLevel" => Logger::ERR, "logOutput" => "ssh connection to {$host} failed");
			return false;
		}

		try {
			$ok = $this -> _ssh -> loginWithPassword($username, $password);
		} catch (\ErrorException $e) {
			$this -> _viewData = array("success" => false, "error" => "Login to {$host} failed", "logLevel" => Logger::ERR, "logOutput" => "Login to {$host} failed");
			return false;
		}
		if (!$ok) {
			$this -> _viewData = array("success" => false, "error" => "Login to {$host} failed", "logLevel" => Logger::ERR, "logOutput" => "Login to {$host} failed");
			return false;
		}

		$stream = $this -> _ssh -> getShell(false, 'vt102', Array(), 4096);
		if (!$stream) {
			$this -> _viewData = array("success" => false, "error" => "Obtaining shell on {$host} failed", "logLevel" => Logger::ERR, "logOutput" => "Obtaining shell on {$host} failed");
			return false;
		}

		// set the socket timeout to 30 seconds
		stream_set_timeout($stream, $this -> _sshTimeout);

		$buffer = '';
		$ok = $this -> _ssh -> waitPrompt($this -> _prompt, $buffer, $this -> _sshTimeout);
		if (!$ok) {
			$this -> _viewData = array("success" => false, "error" => "Getting shell prompt on {$host} failed", "logLevel" => Logger::ERR, "logOutput" => "Getting shell prompt on {$host} failed");
			return false;
		}
		return true;
	}

	/**
	 * @param $command
	 * @param $output
	 * @return bool
	 */
	private function sshCommand($command, &$output) {
		$buffer = '';
		$this -> _ssh -> writePrompt($command);
		$ok = $this -> _ssh -> waitPrompt($this -> _prompt, $output, $this -> _sshTimeout);
		if (!$ok) {
			$this -> _viewData = array("success" => false, "error" => "'" . $command . "' command failed: " . $buffer, "logLevel" => Logger::ERR, "logOutput" => "'" . $command . "' command failed: " . $buffer);
			return false;
		}
		return true;
	}

	/**
	 *
	 */
	private function sshDisconnect() {
		if ($this -> _ssh) {
			$this -> _ssh -> closeStream();
		}
	}

}
