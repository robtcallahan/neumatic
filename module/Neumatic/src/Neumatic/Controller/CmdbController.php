<?php

namespace Neumatic\Controller;

use Zend\Mvc\MvcEvent;
use Zend\View\Model\ViewModel;
use Zend\View\Model\JsonModel;
use Zend\Log\Logger;

use STS\CMDB;
use STS\HPSIM;
use STS\SNCache;

use Neumatic\Model;

class CmdbController extends Base\BaseController
{

    protected $defaultCacheLifetime;

    public function onDispatch(MvcEvent $e) {
        
        $this->defaultCacheLifetime = "300";
        $this->checkCache();
        return parent::onDispatch($e);
    }

    public function __construct() {
        // create our new ViewModel and disable the layout
        $this->_viewModel = new ViewModel();
        $this->_viewModel->setTerminal(true);
    }

    /**
     *
     *
     */
    public function indexAction() {

        return $this->renderview(array("error" => "This controller has no output from index."));
    }


    /*  CR  */

    /**
     * Gets a Change Request Object from ServiceNow by the id number.
     * Usage: https://{$neumaticserver}/cmdb/getCRByNumber/{$CRNumber}
     *
     * Returns: json Change Request object or error message if not found.
     *
     * @return JsonModel
     */
    public function getCRByNumberAction() {
        $crNumber = $this->params()->fromRoute('param1');
        $cr       = $this->getCRByNumber($crNumber);
        //validate that it is not null
        $sysId = $cr->get("sysId");

        if ($sysId == null) {
            $output = array("error" => "The Specified Change Request was not found.");
        } else {
            $output = $cr;
        }
        return $this->renderview($output, 'json');
    }

    /**
     *
     * @return ViewModel
     */
    public function getCRNotesAction() {
        $elementId = $this->params()->fromRoute('param1');
        $notes     = $this->getCRNotesByElementId($elementId);
        return $this->renderview($notes, "view");
    }

    /**
     * Checks if the specific Change Request has been approved
     * Usage: https://{$neumaticserver}/cmdb/getCRApproved/{$CRNumber}
     *
     * @return JsonModel
     */
    public function getCRApprovedAction() {
        $CRNumber = $this->params()->fromRoute('param1');
        $CR       = $this->getCRByNumber($CRNumber);
        $approval = $CR->get("approval");

        return $this->renderview(array("approval" => $approval), 'json');
    }

    /**
     *
     * @param $CRNumber
     * @return string
     */
    public function getCRElementId($CRNumber) {
        $CR        = $this->getCRByNumber($CRNumber);
        $elementId = $CR->get("sysId");
        return $elementId;
    }

    /**
     *
     * @return string
     */
    public function createNoteAction() {
        $CRNumber = $this->params()->fromRoute('param1');
        return $this->getCRElementId($CRNumber);
    }

    /**
     *
     * @param $elementId
     * @return string
     */
    private function getCRNotesByElementId($elementId) {
        $cmdbSysJournalFieldListTable = new CMDB\CMDBSysJournalFieldListTable($this->_config);
        $notes                        = $cmdbSysJournalFieldListTable->getNotesByElementId($elementId);
        return $notes;
    }

    /**
     *
     * @param $elementId
     * @param $note
     * @return string
     */
    private function createCRNote($elementId, $note) {
        $cmdbSysJournalFieldListTable = new CMDB\CMDBSysJournalFieldListTable($this->_config);
        $note                         = $cmdbSysJournalFieldListTable->createCRNote($elementId, $note);
        return $note;
    }

    /**
     *
     * @param $CRNumber
     * @return CMDB\CMDBChangeRequest
     */
    private function getCRByNumber($CRNumber) {
        $cmdbChangeRequestTable = new CMDB\CMDBChangeRequestTable($this->_config);
        $cr                     = $cmdbChangeRequestTable->getByNumber($CRNumber);
        return $cr;
    }

    /* Business Services */

    /**
     * @return JsonModel
     */
    public function getBusinessServicesAction() {
        $cmdbBusinessServiceTable = new SNCache\BusinessServiceTable($this->_config);

        try {
            $services = $cmdbBusinessServiceTable->getAll();
        } catch (\ErrorException $e) {
            return $this->renderView(array(
                "success" => false,
                "error" => "Unable to obtain business services",
                "logLevel" => Logger::ERR,
                "logOutput" => "Unable to obtain business services"
            ));
        }
        $data = array();
        foreach ($services as $bs) {
            $data[] = array(
                "sysId" => $bs->getSysId(),
                "name"  => $bs->getName()
            );
        }
        return $this->renderView(array(
            "success" => true,
            "businessServices" => $data,
            "cache" => false
        ));
    }
    /**
     * @return JsonModel
     */
    public function getBusinessServicesBySubstringAction() {
        $nameSubstring = $this->params()->fromRoute('param1');

        $cmdbBusinessServiceTable = new SNCache\BusinessServiceTable($this->_config);

        try {
            if ($nameSubstring == "") {
                $services = $cmdbBusinessServiceTable->getByNameLike($nameSubstring);
            } else {
                $services = $cmdbBusinessServiceTable->getByNameLike($nameSubstring);
            }
        } catch (\ErrorException $e) {
            return $this->renderView(array(
                "success" => false,
                "error" => "Unable to obtain business services with substring " . $nameSubstring . ". " . $e->getMessage(),
                "logLevel" => Logger::ERR,
                "logOutput" => "Unable to obtain business services with substring " . $nameSubstring
            ));
        }

        $data = array();
        foreach ($services as $bs) {
            $data[] = array(
                "sysId" => $bs->getSysId(),
                "name"  => "{$bs->getName()}"
            );
        }
        return $this->renderView(array(
            "success" => true,
            "businessServices" => $data
        ));
    }

    /**
     * @return JsonModel
     */
    public function getBusinessServiceSubsystemsAction() {
        $bsSysId            = $this->params()->fromRoute('param1');
        $cmdbSubsystemTable = new CMDB\CMDBSubsystemTable($this->_config);

        try {
            $subsystems = $cmdbSubsystemTable->getByBusinessServiceId($bsSysId);
        } catch (\ErrorException $e) {
            return $this->renderView(array(
                "success" => false,
                "error" => "Unable to obtain subsystems for this business service",
                "logLevel" => Logger::ERR,
                "logOutput" => "Unable to obtain subsystems for business service sysId = " . $bsSysId
            ));
        }

        $data               = array();
        foreach ($subsystems as $ss) {
            $data[] = array(
                "sysId" => $ss->getSysId(),
                "name"  => $ss->getName()
            );
        }
        return $this->renderView(array(
            "success" => true,
            "subsystems" => $data
        ));
    }

    /**
     * @return JsonModel
     */
    public function getBusinessServicesWithSubsystemsAction() {
        $cmdbBusinessServiceTable = new SNCache\BusinessServiceTable($this->_config);
        $services                 = $cmdbBusinessServiceTable->getAll();
        $data                     = array();
        foreach ($services as $bs) {
            $cmdbSubsystemTable = new CMDB\CMDBSubsystemTable($this->_config);
            $subsystems         = $cmdbSubsystemTable->getByBusinessServiceId($bs->getSysId());
            $ss                 = array();
            foreach ($subsystems as $sub) {
                $ss[] = array(
                    "sysId" => $sub->getSysId(),
                    "name"  => $sub->getName()
                );
            }
            $data[] = array(
                "sysId"      => $bs->getSysId(),
                "name"       => $bs->getName(),
                "subsystems" => $ss
            );
        }
        return $this->renderView($data, "json");
    }


    /**
     * @return JsonModel
     */
    public function getEnvironmentsAction() {
        $cmdbListTable = new CMDB\CMDBListTable('u_environment', 'cmdb_ci', $this->_config);

        try {
            $envs = $cmdbListTable->getArray();
        } catch (\ErrorException $e) {
            return $this->renderView(array(
                "success" => false,
                "error" => "Unable to obtain CMDB environments",
                "logLevel" => Logger::ERR,
                "logOutput" => "Unable to obtain CMDB environments"
            ));
        }

        $data          = array();
        foreach ($envs as $k => $v) {
            $data[] = array(
                "id"   => $k,
                "name" => $v,
            );
        }
        return $this->renderView(array(
            "success" => true,
            "environments" => $data
        ));
    }

    /**
     * @return ViewModel
     */
    public function getSubsystemsByBusinessServiceNameAction() {
        $bsName                   = $this->params()->fromRoute('param1');
        $cmdbBusinessServiceTable = new CMDB\CMDBBusinessServiceTable($this->_config);
        $businessService          = $cmdbBusinessServiceTable->getByName($bsName);

        $cmdbSubsystemTable = new CMDB\CMDBSubsystemTable($this->_config);

        $subsystems = $cmdbSubsystemTable->getByBusinessServiceId($businessService->getSysId());

        $ss = array();
        foreach ($subsystems as $sub) {
            $ss[] = array("sysId" => $sub->getSysId(), "name" => $sub->getName());
        }

        return $this->renderView(array(
            "success" => true,
            "subsystems" => $ss
            ));
    }

    public function getLocationsAction() {
        $sncLocationTable = new SNCache\LocationTable($this->_config);
        $locations         = $sncLocationTable->getAll();

        $loc               = array();
        foreach ($locations as $location) {
            $loc[] = array("sysId" => $location->getSysId(), "name" => $location->getName());
        }
        return $this->renderView(array(
            "success" => true,
            "count" => count($loc),
            "locations" => $loc
            ));
    }

    public function getLocationsBySubstringAction() {
        $nameSubstring = $this->params()->fromRoute('param1');

        $sncLocationTable = new SNCache\LocationTable($this->_config);
        $locations         = $sncLocationTable->getByNameLike($nameSubstring);

        $loc               = array();
        foreach ($locations as $location) {
            $loc[] = array("sysId" => $location->getSysId(), "name" => $location->getName());
        }
        return $this->renderView(array(
            "success" => true,
            "count" => count($loc),
            "locations" => $loc,
            ));
    }

    /* Server */

    /**
     *
     * @param $name
     * @return CMDB\CMDBServer
     */
    private function getServerByName($name) {
        $cmdbServerTable = new CMDB\CMDBServerTable($this->_config);
        $server          = $cmdbServerTable->getByNameLike($name);
        return $server;
    }

    /**
     *
     * @return JSONModel
     */
    public function getServerByNameAction() {
        $name   = $this->params()->fromRoute('param1');
        $server = $this->getServerByName($name);
        return $this->renderView(array(
            "success" => true,
            "server" => $server->toObject(),
            "logLevel" => Logger::DEBUG,
            "logOutput" => "Returned CMDB server name: " . $server->getName()
            ));
    }

    /**
     *
     * @return string
     */
    public function getServerBusinessServiceByNameAction() {
        $name   = $this->params()->fromRoute('param1');
        $server = $this->getServerByName($name);

        $businessService = $server->getBusinessService();
        if ($businessService == "") {
            $businessService = $server->getBusinessServices();
        }
        echo $businessService;
        exit();
    }

    /**
     *
     * @return string
     */
    public function getServerSubsystemByNameAction() {
        $name          = $this->params()->fromRoute('param1');
        $server        = $this->getServerByName($name);
        $subsystemList = $server->getSubsystemList();
        echo $subsystemList;
        exit();
    }

    /**
     *
     * @return string
     */
    public function getServerLocationByNameAction() {
        $name     = $this->params()->fromRoute('param1');
        $server   = $this->getServerByName($name);
        $location = $server->getLocation();
        echo $location;
        exit();
    }

    /**
     *
     * @return string
     */
    public function getServerEnvironmentByNameAction() {
        $name        = $this->params()->fromRoute('param1');
        $server      = $this->getServerByName($name);
        $environment = $server->getEnvironment();
        echo $environment;
        exit();
    }

    /**
     *
     * @return string
     */
    public function getServerClassificationByNameAction() {
        $name           = $this->params()->fromRoute('param1');
        $server         = $this->getServerByName($name);
        $classification = $server->getClassification();
        echo $classification;
        exit();
    }

    /**
     * custom function for collectD configuration
     *
     * @return string
     */
    public function getServerCollectDConfigPrefixAction() {
        $name            = $this->params()->fromRoute('param1');
        $server          = $this->getServerByName($name);
        $businessService = $server->getBusinessService();
        if ($businessService == "") {
            $businessService = $server->getBusinessServices();
        }
        $subsystemList = $server->getSubsystemList();
        $location      = $server->getLocation();
        $environment   = $server->getEnvironment();
        #$classification = $server->getClassification();
        #$sysClassName = $server->getSysClassName();

        $output = $businessService . "." . $subsystemList . "." . $location . "." . $environment . ".Host.";
        $output = str_replace(" - ", "_", $output);
        $output = str_replace(" ", "_", $output);
        echo $output;
        exit();
    }

    public function getServersByBusinessServiceAction() {
        $businessService = $this->params()->fromRoute('param1');
        $param2          = $this->params()->fromRoute('param2');

        $cmdbServerTable = new CMDB\CMDBServerTable($this->_config);
        $servers         = $cmdbServerTable->getByBusinessServicesArray(array($businessService));

        $xml  = "<project>\n";
        $data = array();
        foreach ($servers as $s) {
            $xml .= '<node name="' . $s->getName() . '" description="server node" tags="' . $s->getEnvironment() . '" hostname="' . $s->getName() . '" osArch="amd64" osFamily="unix" osName="Linux" osVersion="" username="root"/>';
            $data[] = $s->getName();
        }
        $xml .= "</project>\n";

        if ($param2 && $param2 == 'rundeck') {
            return $this->renderView($xml, "xml");
        } else {
            return $this->renderView($data);
        }
    }

    /**
     * Create a new cmdb_ci_server in the SN CMDB
     * Should only be called for VMWare VMs - no bare metal
     * We'll check to see if it already exists and update if so, otherwise create
     *
     * @return JsonModel
     */
    public function createServerAction() {
        $serverId = $this->params()->fromRoute('param1');
        if ($serverId == "") {
            return $this->renderView(array(
                                         "success"   => false,
                                         "error"     => "Server ID not found",
                                         "logLevel"  => Logger::ERR,
                                         "logOutput" => "Server ID was not passed in"
                                     ));
        }

        // get the existing server from the DB
        $serverTable = new Model\NMServerTable($this->_config);
        $server      = $serverTable->getById($serverId);

        // instantiate our cmdb server table which will do all the work
        $cmdbServerTable = new CMDB\CMDBServerTable($this->_config);

        // see if we can find an existing CI
        // note that even if no CI is found, we can still populate the cmdbServer instance with our data
        $cmdbServer = null;
        if ($server->getSysId()) {
            // if server sysId exists, we can lookup with that
            $cmdbServer = $cmdbServerTable->getBySysId($server->getSysId());
        } else {
            $this->writeLog(array("logLevel" => Logger::DEBUG, "logOutput" => "server->getName()=" . $server->getName()));
            // no sysId, let's try with name
            try {
                $cmdbServer = $cmdbServerTable->getByName($server->getName());
            } catch (\Exception $e) {
                if ($e->getCode() == CMDB\CMDBDAO::MULTIPLE_ENTRIES) {
                    return $this->renderView(array(
                        "success" => false,
                        "error"   => "More than one entry found in the CMDB for " . $server->getName(),
                        "logLevel" => Logger::ERR,
                        "logOutput" => "More than one entry found in the CMDB for " . $server->getName()
                    ));
                }
            }
        }

        // get the list of environments
        $cmdbListTable = new CMDB\CMDBListTable('u_environment', 'cmdb_ci', $this->_config);
        $envHash       = $cmdbListTable->getHash();

        // get a list of manufacturers
        $cmdbManTable = new CMDB\CMDBManufacturerTable($this->_config);

        // get a list of host types
        $cmdbListTable = new CMDB\CMDBListTable('u_host_type', 'cmdb_ci_server', $this->_config);
        $hostTypes     = $cmdbListTable->getHash();

        // set defaults
        $hostType  = $hostTypes['Virtual Host'];
        $isVirtual = "true";
        $cmdbMan   = $cmdbManTable->getByName('VMWare');
        $distSwitchName = "Sterling General Purpose";
        $hostedOnServer = null;

        $numCPUs = 0;
        $memoryGB = 0;

        $vmware = new Model\NMVMWare();
        if ($server->getServerType() == 'blade') {
            $hostType  = $hostTypes['Blade Host'];
            $isVirtual = "false";

            $cmdbMan      = $cmdbManTable->getByName('HP');

            $nmBladeTable = new Model\NMBladeTable($this->_config);
            $nmBlade      = $nmBladeTable->getByServerId($server->getId());

            $bladeTable = new HPSIM\HPSIMBladeTable($this->_config);
            $blade      = $bladeTable->getById($nmBlade->getBladeId());

            $chassisTable = new HPSIM\HPSIMChassisTable($this->_config);
            $chassis      = $chassisTable->getById($nmBlade->getChassisId());

            $hostedOnServer = $cmdbServerTable->getByName($chassis->getFullDnsName());

            $distSwitchName = $blade->getDistSwitchName();

            $numCPUs  = $blade->getNumCpus();
            $memoryGB = $blade->getMemorySizeGB();

            // since this is a blade, if we couldn't find the cmdb ci, try with serial number
            if (!$cmdbServer->getSysId() && $blade->getSerialNumber()) {
                $cmdbServer = $cmdbServerTable->getBySerialNumber($blade->getSerialNumber());
            }
        } else if ($server->getServerType() == 'standalone' || $server->getServerType() == 'remote') {
            $hostType  = $hostTypes['Standalone Host'];
            $isVirtual = "false";

            $cmdbMan      = $cmdbManTable->getByName('HP');

            $nmStandaloneTable = new Model\NMStandaloneTable($this->_config);
            $standalone      = $nmStandaloneTable->getByServerId($server->getId());

            $distSwitchName = $standalone->getDistSwitch();
        } else if ($server->getServerType() == 'vmware') {
            $hostType  = $hostTypes['Virtual Host'];
            $isVirtual = "true";

            $cmdbMan      = $cmdbManTable->getByName('VMWare');

            // if this is a vm we should have an entry in the vmware table
            $vmwareTable = new Model\NMVMWareTable($this->_config);
            $vmware      = $vmwareTable->getByServerId($server->getId());

            // lookup the host system so we can use its sys_id for Host On field
            if ($vmware->getHsName()) {
                $hostedOnServer = $cmdbServerTable->getByName($vmware->getHsName());
            } else {
                $hostedOnServer = null;
            }

            // convert dist switch name if necessary. vmware dist switches will be named by their esx cluster
            // so, for example, ST_GenAB would be mapped to "Sterling General Pupose"
            // yes, this is ugly, but we're working on a mapping which should simply this in the future
            $ccrName = $vmware->getCcrName();
            if (preg_match("/(CH|ST)_(\w+)/", $ccrName, $m)) {
                $site   = $m[1] == "CH" ? "Charlotte" : "Sterling";
                $abbrev = $m[2];
                switch ($abbrev) {
                    case "GenAB":
                        $distSwitchName = $site . " General Purpose";
                        break;
                    case "IHN" || "OMS":
                        $distSwitchName = $site . " " . $abbrev;
                        break;
                    case "REG":
                        $distSwitchName = $site . " Registry";
                        break;
                    case "NPAC_DEV":
                        $distSwitchName = $site . " NPAC";
                        break;
                    default:
                        $distSwitchName = $ccrName;
                }
            } else if ($ccrName == 'Lab_Cluster') {
                $distSwitchName = "Sterling Lab";
            } else {
                $distSwitchName = "";
            }

            $numCPUs  = $vmware->getNumCPUs();
            $memoryGB = $vmware->getMemoryGB();
        }

        // get a list of locations
        $cmdbLocationTable = new CMDB\CMDBLocationTable($this->_config);

        // non-VMware servers have their location stored in the server table. both name and sysId
        // VMware servers have their datacenters stored in the VMware table.
        // Therefore, we need to check the server type and "do the needful"
        if ($server->getServerType() == "vmware" && $vmware->getId()) {
            $dcName = $vmware->getDcName();
            if ($dcName == "Sterling" || $dcName == "LAB") {
                $location = $cmdbLocationTable->getByName('Sterling-VA-NSR-B8');
            } else if ($dcName == "Charlotte") {
                $location = $cmdbLocationTable->getByName('Charlotte-NC-CLT-1');
            } else if ($dcName == "Denver") {
                $location = $cmdbLocationTable->getByName('Denver-CO-NSR');
            } else {
                $location = $cmdbLocationTable->getByName('Sterling-VA-NSR-B8');
            }
        } else {
            if ($server->getLocationId()) {
                $location = $cmdbLocationTable->getBySysId($server->getLocationId());
            } else if ($server->getLocation() == "Sterling") {
                $location = $cmdbLocationTable->getByName('Sterling-VA-NSR-B8');
            } else if ($server->getLocation() == "Charlotte") {
                $location = $cmdbLocationTable->getByName('Charlotte-NC-CLT-1');
            } else if ($server->getLocation() == "Denver") {
                $location = $cmdbLocationTable->getByName('Denver-CO-NSR');
            } else {
                $location = $cmdbLocationTable->getByName('Sterling-VA-NSR-B8');
            }
        }

        // get a list of status values
        $cmdbListTable   = new CMDB\CMDBListTable('install_status', 'cmdb_ci_server', $this->_config);
        $installStatuses = $cmdbListTable->getHash();

        // get the total storage
        $storageTable = new Model\NMStorageTable($this->_config);
        $luns         = $storageTable->getByServerId($server->getId());
        $totalGBs     = 0;
        foreach ($luns as $lun) {
            $totalGBs += $lun->getLunSizeGb();
        }

        // parse out the OS and version
        if (preg_match("/(\w+)-(\d.*)$/", $server->getCobblerDistro(), $m)) {
            $cmdbServer
                ->setOs($m[1])
                ->setOsVersion($m[2]);
        }

        // set the timezone so that are date/time updates are local
        date_default_timezone_set('America/New_York');

        // if we done have a BS, Subsystem or Env specified, add it here
        $updatedCmdbInfo = false;
        if (!$server->getBusinessServiceId()) {
            $server->setBusinessServiceId($this->_config['servicenow']['defaultBusinessServiceSysId']);
            $server->setBusinessServiceName($this->_config['servicenow']['defaultBusinessServiceName']);
            $updatedCmdbInfo = true;
        }
        if (!$server->getSubsystemId()) {
            $server->setSubsystemId($this->_config['servicenow']['defaultSubsystemSysId']);
            $server->setSubsystemName($this->_config['servicenow']['defaultSubsystemName']);
            $updatedCmdbInfo = true;
        }
        if (!$server->getCmdbEnvironment()) {
            $server->setCmdbEnvironment($this->_config['servicenow']['defaultEnvironment']);
            $updatedCmdbInfo = true;
        }
        if ($updatedCmdbInfo) {
            $server = $serverTable->update($server);
        }

        // construct our cmdb server instance
        // we can trust that all required fields are present or a build would not be allowed
        $cmdbServer
            ->setName($server->getName())
            ->setBusinessServicesIds($server->getBusinessServiceId())
            ->setSubsystemListId($server->getSubsystemId())
            ->setManufacturerId($cmdbMan->getSysId())
            ->setDeviceTypeId($hostType)
            ->setLocationTypeId('Neustar Data Center')
            ->setIpAddress($server->getIpAddress())
            ->setIsVirtual($isVirtual)
            ->setLocationId($location->getSysId())
            ->setEnvironmentId($envHash[$server->getCmdbEnvironment()])
            ->setInstallStatusId($installStatuses['Live'])
            ->setInstallDate(date('Y-m-d H:i:s'))
            ->setDataSource('Neumatic')
            ->setAttributes('Neumatic');
        if ($server->getServerType() != 'remote') {
            $cmdbServer->setDistributionSwitch($distSwitchName);
        }
        if ($server->getServerType() == 'remote') {
            $cmdbServer->setLocationTypeId('Colocated');
        }

        if ($server->getServerType() == 'vmware') {
            $cmdbServer
                ->setCpuCount($numCPUs)
                ->setRam($memoryGB)
                ->setDiskSpace($totalGBs);
        }

        // set hosted on if we can
        if ($hostedOnServer && $hostedOnServer->getSysId()) {
            $cmdbServer->setHostedOnId($hostedOnServer->getSysId());
        }

        // ready to save
        if ($cmdbServer->getSysId()) {
            // update
            $cmdbServer = $cmdbServerTable->update($cmdbServer);

            $server->setSysId($cmdbServer->getSysId());
            $serverTable->update($server);
        } else {
            // create
            $cmdbServer = $cmdbServerTable->create($cmdbServer);

            // since this is a newly created CI, update the server table with its cmdb sys id
            $server->setSysId($cmdbServer->getSysId());
            $serverTable->update($server);
        }

        if ($cmdbServer->getSysId() == "" || $cmdbServer->getSysId() == null) {
            return $this->renderView(array(
                                         "success"    => false,
                                         "error"  => $cmdbServer->getName() . " could not be created or updated",
                                         "logLevel"   => Logger::ERR,
                                         "logOutput"  => $cmdbServer->getName() . " could not be created or updated"
                                     ));
        }

        return $this->renderView(array(
                                     "success"    => true,
                                     "server"     => $cmdbServer->toObject(),
                                     "logLevel"   => Logger::NOTICE,
                                     "logOutput"  => $cmdbServer->getName() . " CI created",
                                     "parameters" => "[serverName: {$cmdbServer->getName()}]"
                                 ));
    }

    /**
     * @return JsonModel
     */
    public function deleteServerAction() {
        $serverId = $this->params()->fromRoute('param1');
        if ($serverId == "") {
            return $this->renderView(array("success" => false, "message" => "Server ID not found"));
        }

        // get the existing server from the DB
        $serverTable = new Model\NMServerTable($this->_config);
        $server      = $serverTable->getById($serverId);

        // instantiate our cmdb server table which will do all the work
        $cmdbServerTable = new CMDB\CMDBServerTable($this->_config);

        // get a list of status values
        $cmdbListTable   = new CMDB\CMDBListTable('install_status', 'cmdb_ci_server', $this->_config);
        $installStatuses = $cmdbListTable->getHash();

        $this->writeLog(array("logLevel" => Logger::DEBUG, "logOutput" => "installStatuses['Disposed']=" . $installStatuses['Disposed']));


        // see if we can find an existing CI
        if ($server->getSysId()) {
            // if server sysId exists, we can lookup with that
            $cmdbServer = $cmdbServerTable->getBySysId($server->getSysId());
        } else {
            // no sysId, let's try with name
            $cmdbServer = $cmdbServerTable->getByName($server->getName());
        }

        $this->writeLog(array("logLevel" => Logger::DEBUG, "logOutput" => "cmdbServer name=" . $cmdbServer->getName()));

        // ready to save
        if ($cmdbServer->getSysId()) {
            // Changing to delete for all stlabvnode* hosts
            if (preg_match("/stlabvnode/", $server->getName())) {
                $cmdbServerTable->delete($cmdbServer);
                return $this->renderView(array(
                    "success"    => true,
                    "logLevel"   => Logger::NOTICE,
                    "logOutput"  => $cmdbServer->getName() . " deleted",
                    "parameters" => "[serverName: {$cmdbServer->getName()}]"
                ));
            } else if ($server->getServerType() == "blade") {
                $cmdbServer->setInstallStatusId($installStatuses['Inventory']);
                $cmdbServer = $cmdbServerTable->update($cmdbServer);
                return $this->renderView(array(
                    "success"    => true,
                    "logLevel"   => Logger::NOTICE,
                    "logOutput"  => $cmdbServer->getName() . " CI status has been changed to " . $cmdbServer->getInstallStatus(),
                    "parameters" => "[serverName: {$cmdbServer->getName()}]"
                ));
            } else {
                $cmdbServer->setInstallStatusId($installStatuses['Disposed']);
                $cmdbServer = $cmdbServerTable->update($cmdbServer);
                return $this->renderView(array(
                    "success"    => true,
                    "logLevel"   => Logger::NOTICE,
                    "logOutput"  => $cmdbServer->getName() . " CI status has been changed to " . $cmdbServer->getInstallStatus(),
                    "parameters" => "[serverName: {$cmdbServer->getName()}]"
                ));
            }
        } else {
            return $this->renderView(array(
                                         "success"   => true,
                                         "error"     => "CMDB entry not found",
                                         "logLevel"  => Logger::ERR,
                                         "logOutput" => "CMDB entry for " . $server->getName() . " not found"
                                     ));
        }

    }

    // *****************************************************************************************************************
    // Private methods
    // *****************************************************************************************************************

}
