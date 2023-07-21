<?php

namespace Neumatic\Controller;

use Zend\Mvc\MvcEvent;
use Zend\View\Model\JsonModel;
use Zend\Log\Logger;

use STS\HPSIM;

class HPSIMController extends Base\BaseController
{

    /**
     * @param MvcEvent $e
     * @return mixed
     */
    public function onDispatch(MvcEvent $e)
    {
        return parent::onDispatch($e);
    }

    /**
     * @return JsonModel
     */
    public function indexAction()
    {
        return $this->renderview(array("error" => "This controller has no output from index."));
    }

    /**
     * @return JsonModel
     */
    public function getChassisAction()
    {
        $chassisTable = new HPSIM\HPSIMChassisTable($this->_config);
        $chassis      = $chassisTable->getAll();
        $data         = array();
        foreach ($chassis as $chassi) {
            $data[] = array(
                "id"   => $chassi->getId(),
                "name" => $chassi->getDeviceName()
            );
        }
        return $this->renderView($data);
    }

    /**
     * @return JsonModel
     */
    public function getChassisByDistributionSwitchAction()
    {
        $distSwName = $this->params()->fromRoute('param1');
        $chassisTable = new HPSIM\HPSIMChassisTable($this->_config);
        $chassis      = $chassisTable->getBySwitchName($distSwName);
        $data         = array();
        foreach ($chassis as $chassi) {
            $data[] = array(
                "id"   => $chassi->getId(),
                "name" => $chassi->getDeviceName()
            );
        }
        return $this->renderView(array(
            "success" => true,
            "logLevel" => Logger::INFO,
            "logOutput" => count($data) . " chassis returned for " . $distSwName,
            "chassis" => $data
        ));
    }

    private static function sortBySlotNumber()
    {
        return function(HPSIM\HPSIMBlade $a, HPSIM\HPSIMBlade $b) {
            if ($a->getSlotNumber() > $b->getSlotNumber()) {
                return 1;
            } else if ($a->getSlotNumber() < $b->getSlotNumber()) {
                return -1;
            } else {
                return 0;
            }
        };
    }

    /**
     * @return JsonModel
     */
    public function getChassisBladesAction()
    {
        $chassisId = $this->params()->fromRoute('param1');
        $bladeTable = new HPSIM\HPSIMBladeTable($this->_config);
        $blades     = $bladeTable->getByChassisId($chassisId);

        usort($blades, self::sortBySlotNumber());
        $data       = array();
        foreach ($blades as $blade) {
            $data[] = array(
                "id"   => $blade->getId(),
                "name" => $blade->getDeviceName(),
                "slot" => $blade->getSlotNumber(),
                "fqdn" => $blade->getFullDnsName(),
                "isInventory" => $blade->getIsInventory(),
                "displayValue" => "[Slot " . $blade->getSlotNumber() . "] " . $blade->getDeviceName()
            );
        }
        return $this->renderView(array(
            "success" => true,
            "logLevel" => Logger::INFO,
            "logOutput" => count($data) . " blades returned",
            "blades" => $data
        ));
    }

    /**
     * Returns the list of distribution switches for a given location (Sterling or Charlotte)
     *
     * @return JsonModel
     */
    public function getDistSwitchesByLocationAction()
    {
        $location = $this->params()->fromRoute('param1');

        // strip off all but location name
        if (preg_match("/(\w+)-/", $location, $m)) {
            $location = $m[1];
        }

        $this->_config['dbIndex'] = 'hpsim';
        $hpsim = new HPSIM\HPSIM($this->_config);
        $distSwitches = $hpsim->getDistSwitches($location);
        $data       = array();
        foreach ($distSwitches as $sw) {
            $data[] = $sw->distSwitchName;
        }
        return $this->renderView(array(
            "success" => true,
            "distSwitches" => $data,
            "logLevel" => Logger::INFO,
            "logOutput" => count($data) . " switches returned"
        ));
    }

    /**
     * Returns a list of VLANs given a chassis id
     *
     * @return JsonModel
     */
    public function getSwitchVLansAction()
    {
        $switchName = $this->params()->fromRoute('param1');
        $vlanTable = new HPSIM\HPSIMVLANTable($this->_config);
        $vlans = $vlanTable->getByDistSwitchName($switchName);

        $vlanDetailsTable = new HPSIM\HPSIMVLANDetailTable($this->_config);
        $data = array();
        foreach ($vlans as $vlan) {
            $details = $vlanDetailsTable->getByVlanIdAndDistSwitchName($vlan->vlanId, $switchName);
            $data[] = array(
                "id" => 0,
                "vlanId" => $vlan->vlanId,
                "name" => $vlan->name,
                "ipSubnet" => $details->getIpSubnet(),
                "subnetMask" => $details->getSubnetMask(),
                "gateway" => $details->getGateway(),
                "displayValue" => "[" . $vlan->vlanId . "] " . $vlan->name
            );
        }
        usort($data, self::buildSorter('vlanId'));
        return $this->renderView(array(
            "success" => true,
            "logLevel" => Logger::INFO,
            "logOutput" => count($data) . " vlans returned",
            "vlans" => $data
        ));
    }

    /**
     * @return \Zend\View\Model\JsonModel
     */
    public function getVlanDetailsByVlanIdAndDistSwitchNameAction()
    {
        $vlanId = $this->params()->fromRoute('param1');
        $distSwitchParam = $this->params()->fromRoute('param2');

        // if vlanId in the form of vlan32, then parse the number value at the end and drop vlan string
        if (preg_match("/[Vv][Ll][Aa][Nn](\d+)/", $vlanId, $m)) {
            $vlanId = $m[1];
        }

        // convert dist switch name if necessary. vmware dist switches will be named by their esx cluster
        // so, for example, ST_GenAB would be mapped to "Sterling General Pupose"
        // yes, this is ugly, but we're working on a mapping which should simplify this in the future
        if (preg_match("/(CH|ST|DE)_([\w_]+)$/", $distSwitchParam, $m)) {
            $site = $m[1];
            switch ($site) {
                case "CH":
                    $site = "Charlotte";
                    break;
                case "DE":
                    $site = "Denver";
                    break;
                case "ST":
                    $site = "Sterling";
                    break;
                default:
                    $site = "Sterling";
            }
            $abbrev = $m[2];
            switch ($abbrev) {
                case "GenAB":
                    $distSwitchName = $site . " General Purpose";
                    break;
                case "IHN":
                    $distSwitchName = $site . " " . $abbrev;
                    break;
                case "LP":
                    $distSwitchName = $site . " LEAP";
                    break;
                case "OMS":
                    $distSwitchName = $site . " " . $abbrev;
                    break;
                case "REG":
                    $distSwitchName = $site . " Registry";
                    break;
                case "Lab_Cluster":
                    $distSwitchName = "Sterling Lab";
                    break;
                case "NPAC_DEV":
                    $distSwitchName = $site . " NPAC";
                    break;
                default:
                    $distSwitchName = $site;
            }
        } else if ($distSwitchParam == "LAB_Cluster") {
            $distSwitchName = "Sterling Lab";
        } else {
            $distSwitchName = $distSwitchParam;
        }

        $vlanDetailsTable = new HPSIM\HPSIMVLANDetailTable($this->_config);
        $vlanDetails = $vlanDetailsTable->getByVlanIdAndDistSwitchName($vlanId, $distSwitchName);
        if ($vlanDetails->getIpSubnet()) {
            $data = array(
                "distSwitch" => $vlanDetails->getDistSwitchName(),
                "vlanId" => $vlanDetails->getVlanId(),
                "ipSubnet" => $vlanDetails->getIpSubnet(),
                "subnetMask" => $vlanDetails->getSubnetMask(),
                "gateway" => $vlanDetails->getGateway()
            );
            return $this->renderView(array("success" => true, "vlanData" => $data));
        } else {
            return $this->renderView(array("success" => false, "message" => "Could not obtain VLAN details for " . $distSwitchName . " and " . $vlanId));
        }
    }

    /**
     * @return \Zend\View\Model\JsonModel
     */
    public function getVlansAction()
    {
        $chassisTable = new HPSIM\HPSIMChassisTable($this->_config);
        $switchTable = new HPSIM\HPSIMSwitchTable($this->_config);
        $vlanTable = new HPSIM\HPSIMVLANTable($this->_config);
        $vlanDetailsTable = new HPSIM\HPSIMVLANDetailTable($this->_config);

        $data = array();
        $switches = $switchTable->getAllActive();
        foreach ($switches as $switch) {
            $chassis = $chassisTable->getById($switch->getChassisId());
            $vlans = $vlanTable->getBySwitchId($switch->getId());
            foreach ($vlans as $vlan) {
                $vlanDetails = $vlanDetailsTable->getByVlanIdAndDistSwitchName($vlan->getVlanId(), $chassis->getDistSwitchName());
                $data[] = array(
                    "distSwitch" => $chassis->getDistSwitchName(),
                    "chassisId" => $chassis->getId(),
                    "chassisName" => $chassis->getDeviceName(),
                    "switchId" => $switch->getId(),
                    "switchName" => $switch->getDeviceName(),
                    "vlanTableId" => $vlan->getId(),
                    "vlanId" => $vlan->getVlanId(),
                    "vlanName" => $vlan->getName(),
                    "ipSubnet" => $vlanDetails->getIpSubnet(),
                    "subnetMask" => $vlanDetails->getSubnetMask(),
                    "gateway" => $vlanDetails->getGateway()
                );
            }
        }
        return $this->renderView($data);
    }

    /**
     * @return \Zend\View\Model\JsonModel
     */
    public function getChassisMMAction()
    {
        try {
            $chassisId = $this->params()->fromRoute('param1');
            $mmTable = new HPSIM\HPSIMMgmtProcessorTable($this->_config);
            $mm = $mmTable->getActiveByChassisId($chassisId);
        } catch (\ErrorException $e) {
            return $this->renderView(array("success" => 0, "message" => "Could not obtain the chassis' active management module", "errorText" => $e->getMessage(), "traceback" => $e->getTraceAsString()));
        }
        return $this->renderView(array("success" => 1, "name" => $mm->getFullDnsName(), "ipAddress" => $mm->getDeviceAddress()));
    }

    /**
     * @param $key
     * @return callable
     */
    private static function buildSorter($key) {
        return function($a, $b) use ($key) {
            return strnatcmp($a[$key], $b[$key]);
        };
    }

}
