<?php

namespace Neumatic\Controller;


use Zend\View\Model\JsonModel;
use Zend\Mvc\MvcEvent;
use Zend\Log\Logger;

use Neumatic\Model;
use Vmwarephp\Vhost;
use Vmwarephp\Extensions\Task;
use Vmwarephp\Exception\Soap;

class VMWareController extends Base\BaseController
{

    protected $consoleExecDir;
    protected $consoleExecFile;
    protected $consoleLogFile;

    protected $xmlFile;

    /**
     * @param MvcEvent $e
     * @return mixed
     */
    public function onDispatch(MvcEvent $e) {
        $this->defineVSphereServer($this->params()->fromQuery('vSphereSite'));
        $this->vSphereSite = $this->params()->fromQuery('vSphereSite');
        $this->xmlFile = "/tmp/last_vmware_call.xml";
        
        $uid                        = $this->_user->getUsername();
        $this->defaultCacheLifetime = "300";
        $this->cachePathBase        = "/var/www/html/neumatic/data/cache/" . $uid . "/Chef/" . $this->chefServer . "/";
        $this->checkCache();
        
        
        return parent::onDispatch($e);
    }

    /**
     * @return \Zend\View\Model\JsonModel
     */
    public function indexAction() {
        $methods = array();
        foreach (get_class_methods(__CLASS__) as $m) {
            if (preg_match("/Action/", $m)) {
                $methods[] = $m;
            }
        }
        return $this->renderView(array("success" => true, "actions" => implode(", ", $methods)));
    }

    public function startWatcherAction()
    {
        
        $this->watcherExecDir = __DIR__ . "/../../../bin";
        $this->watcherExecFile = "vmtemplate_watch.php";

        $this->logDir = "/opt/neumatic/watcher_log";
        $this->watcherLogFile = $this->logDir . "/vmtemplate_watch.log";
            
        $serverId = $this->params()->fromRoute('param1');

        $nmServerTable = new Model\NMServerTable($this->_config);
        $server = $nmServerTable->getById($serverId);

        $logFile = $this->watcherLogFile . "." . $server->getName();

        // touch the cobbler watcher log file and set its perms wide open so it can be deleted later
        touch($logFile);

        // spawn the vmtemplate_watcher process
        $cmd = "nohup php " . $this->watcherExecDir . "/" . $this->watcherExecFile . " -i " . $server->getId() . " > " . $logFile . " 2>&1 &";
        
        exec($cmd);

        // wait a couple of seconds and then change the perms on the log file
        sleep(1);
        chmod($logFile, 0666);

        return $this->renderView(array("success" => true));
    }

    /**
     * @return JsonModel
     */  
    public function getTemplateListAction(){
        
        try {
            $dcName = $this->params()->fromQuery('dcName');
            $templatesFolder = "Template_Repository";
            $templates = array();
            
            $vServer = new Vhost($this->vSphereServer . ':' . $this->vSpherePort, $this->vSphereUsername, $this->vSpherePassword);
            
            $results = $vServer->findAllManagedObjects('Datacenter', array('name', 'datastore'));
            
            foreach($results AS $dc){
            
                if($dc->name == $dcName){
                    $dataCenter = $dc;
                }    
            }
            $datastores = $dataCenter->datastore;
            
            foreach($datastores AS $ds){
                $ds2 = $vServer->findOneManagedObject('Datastore', $ds->reference->_, array('name', 'vm'));
                if($ds2->name == $templatesFolder){
                    $vms = $ds2->vm;   
                    foreach($vms AS $vm1){
                        if(is_object($vm1)){                    
                            $vm2 = $vServer->findOneManagedObject('VirtualMachine', $vm1->reference->_, array('name'));
                            $template = array('name'=>$vm2->name, 'id'=>$vm2->getReferenceId());
                            $templates[] = $template;
                        }
                        
                    }
                    break;
                }
                
            }
            
        } catch (\Exception $e) {
            $prev = $e->getPrevious();
            return $this->renderView(array(
                "success"   => false,
                #"error"     => $e->getMessage() . ": " . $prev->getMessage(),
                "error" => $e->getMessage(),
                "trace"     => $e->getTraceAsString(),
                "logLevel"  => Logger::ERR,
                "logOutput" => $e->getMessage() . " Trace: " . $e->getTraceAsString()
            ));
        }
        
        return $this->renderView(array(
            "success"   => true,
            "templates"  => $templates,
            "logLevel"  => Logger::INFO,
            "logOutput" => count($templates) . " templates returned",
            //"cache"        => true,
            //"cacheTTL"     => "3600"
            ));

    }

 	private function getTemplateList($dcName){
        
        $templatesFolder = "Template_Repository";
        $templates = array();
        
        $vServer = new Vhost($this->vSphereServer . ':' . $this->vSpherePort, $this->vSphereUsername, $this->vSpherePassword);
        
        $results = $vServer->findAllManagedObjects('Datacenter', array('name', 'datastore'));
        
        foreach($results AS $dc){
        
            if($dc->name == $dcName){
                $dataCenter = $dc;
            }    
        }
        $datastores = $dataCenter->datastore;
        
        foreach($datastores AS $ds){
            $ds2 = $vServer->findOneManagedObject('Datastore', $ds->reference->_, array('name', 'vm'));
            if($ds2->name == $templatesFolder){
                $vms = $ds2->vm;   
                foreach($vms AS $vm1){
                    if(is_object($vm1)){                    
                        $vm2 = $vServer->findOneManagedObject('VirtualMachine', $vm1->reference->_, array('name'));
                        $template = array('name'=>$vm2->name, 'id'=>$vm2->getReferenceId());
                        $templates[] = $template;
                    }
                    
                }
                break;
            }
    
        }
 		return $templates;
    }
    /**
     * @return JsonModel
     */
    public function getDisksByVMNameAction(){
        $vmName = $this->params()->fromRoute('param1');
        try {
            $devices = $this->getDisksByVMName($vmName);
            $output = array();
            foreach($devices AS $devObj){
                $device = array();    
                
                $device['fileName'] = $devObj->backing->fileName;
                $device['capacityInKB'] = $devObj->capacityInKB;
                $device['label'] = $devObj->deviceInfo->label;
                $device['thinProvisioned'] = $devObj->backing->thinProvisioned;
                $device['uuid'] = $devObj->backing->uuid;
                $device['datastore'] = $devObj->backing->datastore;
                $output[] = $device;
            }
            
        } catch (\Exception $e) {
            $prev = $e->getPrevious();
            return $this->renderView(array(
                "success"   => false,
                #"error"     => $e->getMessage() . ": " . $prev->getMessage(),
                "error" => $e->getMessage(),
                "trace"     => $e->getTraceAsString(),
                "logLevel"  => Logger::ERR,
                "logOutput" => $e->getMessage() . " Trace: " . $e->getTraceAsString()
            ));
        }
        // have to return "nodes" as it is part of the tree UI directive
        return $this->renderView(array(
            "success"   => true,
            "disks"  => $output,
            "logLevel"  => $output['errorMessage'] ? Logger::WARN : Logger::INFO,
            "logOutput" => count($output) . " disks returned",
            //"cache"        => true,
            //"cacheTTL"     => "3600"
            ));
    }

    /**
     * @throws \Exception
     * @return mixed|JsonModel
     */
    private function getDisksByVMName($vmName){
        $vServer = new Vhost($this->vSphereServer . ':' . $this->vSpherePort, $this->vSphereUsername, $this->vSpherePassword);
                
        $vm = $vServer->findManagedObjectByName('VirtualMachine', $vmName, array('name', 'config', 'guest'));
         
        $devices = array();
        
        foreach($vm->config->hardware->device AS $devObj){
            $devObjClass = get_class($devObj);
            if($devObjClass == "VirtualDisk"){
              
                $devices[] = $devObj;
             }
             
         }
         return $devices;
    }
    
    public function getNicsByVMNameAction(){
        $vmName = $this->params()->fromRoute('param1');
        
        $devices = $this->getNicsByVMName($vmName);
        print_r($devices);
        die();
    }
    /**
     * @throws \Exception
     * @return mixed|JsonModel
     */
    private function getNicsByVMName($vmName){
        $vServer = new Vhost($this->vSphereServer . ':' . $this->vSpherePort, $this->vSphereUsername, $this->vSpherePassword);
                
        $vm = $vServer->findManagedObjectByName('VirtualMachine', $vmName, array('name', 'config'));
         
        $devices = array();
        //print_r($vm->config->hardware);
        foreach($vm->config->hardware->device AS $devObj){
            $devObjClass = get_class($devObj);
            $label = $devObj->deviceInfo->label;
            if(stristr($label, "Network")){
                
                $devices[] = $devObj;
            }
             
         }
        
         return $devices;
    }

    private function getDatacenterObjectByUid($dataCenterUid) {
        $vServer    = new Vhost($this->vSphereServer . ':' . $this->vSpherePort, $this->vSphereUsername, $this->vSpherePassword);
        $dataCenter = $vServer->findOneManagedObject('Datacenter', $dataCenterUid, array('name', 'hostFolder', 'networkFolder', 'datastoreFolder', 'vmFolder'));
        return $dataCenter;
    }
           
    public function addDiskToVMAction(){
        try {
            $vmName = $this->params()->fromRoute('param1');
            $diskSize = $this->params()->fromRoute('param2');
           
            $result = $this->addDiskToVM($vmName, $diskSize);
            
        } catch (\Exception $e) {
            $prev = $e->getPrevious();
            return $this->renderView(array(
                "success"   => false,
                #"error"     => $e->getMessage() . ": " . $prev->getMessage(),
                "error" => $e->getMessage(),
                "trace"     => $e->getTraceAsString(),
                "logLevel"  => Logger::ERR,
                "logOutput" => $e->getMessage() . " Trace: " . $e->getTraceAsString()
            ));
        }
        
        return $this->renderView(array(
                "success"   => true,
                "message"  => "Drive successfully created",
                "logLevel"  => $result['errorMessage'] ? Logger::WARN : Logger::INFO,
                "logOutput" => "Successfully added a new ".$diskSize."Gb disk to the vm \"$vmName\"",
                //"cache"        => true,
                //"cacheTTL"     => "3600"
                )); 
        
    }

    private function addDiskToVM($vmName, $diskSize){
       
        
        try {
           $site = $this->vSphereSite;
           if($site == "" OR $site == null){
               $site = $this->_config['vSphere']['site'];
           }
           
           $vServer = new Vhost($this->vSphereServer . ':' . $this->vSpherePort, $this->vSphereUsername, $this->vSpherePassword);
            
           $results = $vServer->findAllManagedObjects('Datacenter', array('name', 'vmFolder'));
            
           foreach($results AS $dc){
               
               $vmFolder = $vServer->findOneManagedObject('Folder', $dc->vmFolder->getReferenceId(), array('name', 'childType', 'childEntity'));
                $children = $vmFolder->childEntity;
                foreach($children AS $child){
                    if(get_class($child) == "Vmwarephp\Extensions\VirtualMachine"){
                        $vm = $vServer->findOneManagedObject('VirtualMachine', $child->getReferenceId(), array('name'));
                       
                        if($vmName == $vm->name){
                            $dcName = $dc->name;
                        }
                    }
                }
           }
            
           
           $this->serverConfig = $this->_config['vSphere'][$site];
           
           $this->connectionString =  "-u ".$this->serverConfig['username']." -p ".$this->serverConfig['password']." --vshost ".$this->serverConfig['server']." -D ".$dcName." --vsinsecure";
        
           $command = "/usr/bin/knife vsphere vm vmdk add $vmName $diskSize $this->connectionString";
           $result   = shell_exec($command);
           
                   
        } catch (\Exception $e) {
            $prev = $e->getPrevious();
            return $this->renderView(array(
                "success"   => false,
                #"error"     => $e->getMessage() . ": " . $prev->getMessage(),
                "error" => $e->getMessage(),
                "trace"     => $e->getTraceAsString(),
                "logLevel"  => Logger::ERR,
                "logOutput" => $e->getMessage() . " Trace: " . $e->getTraceAsString()
            ));
        }
        
     
    }

    public function extendVMVirtualDiskAction() {
        $vmName = $this->params()->fromRoute('param1');
        $diskLabel = $this->params()->fromRoute('param2');
        $newSize = $this->params()->fromRoute('param3');
        
        if( empty($vmName) OR empty($diskLabel) OR empty($newSize)){
            return $this->renderView(array(
                                         "success"   => false,
                                         "error"     => "Invalid parameter format. Please provide a VM Name, the label of the disk to be modified and the size in GB : https://neumatic.ops.neustar.biz/vmware/extendVMVirtualDisk/<vmName>/<diskLabel>/<newSize>",
                                         "logLevel"  => Logger::ERR,
                                         "logOutput" => "Invalid parameter format."
                                     ));   
        }
        
        try {
            $vServer = new Vhost($this->vSphereServer . ':' . $this->vSpherePort, $this->vSphereUsername, $this->vSpherePassword);
            
            $datacenters = $vServer->findAllManagedObjects('Datacenter', array('name'));
            $datacenter = $datacenters[0]; 
            
            $vm = $vServer->findManagedObjectByName('VirtualMachine', $vmName, array('name', 'config', 'guest'));
           
            $disks = $this->getDisksByVMName($vmName);
            foreach($disks AS $disk){
                if($disk->deviceInfo->label == $diskLabel){
                    $diskFileName = $disk->backing->fileName;
                }
            }
         
            $service = $vServer->getService();

            $vdm = $service->getServiceContent()->virtualDiskManager;
                       
            // $newSize = 50;
            $sizeKb = $newSize * 1000000;
            $extendDiskTask = $service->makeSoapCall('ExtendVirtualDisk_Task',
                                                             array("_this"       => $vdm->reference,
                                                                   
                                                                   "name" => $diskFileName,
                                                                   "datacenter" => $datacenter->reference,
                                                                   "newCapacityKb" => $sizeKb
                                                                   ));
            if (!$this->watchTask($vServer, $extendDiskTask)) {
                return $this->renderView($this->_viewData);
            }                                                     
            
           /*
           if (!$this->watchTask($vServer, $powerOnTask)) {
                return $this->renderView($this->_viewData);
           }
           */
           
           return $this->renderView(array(
                "success"   => true,
                "message"  => "Drive successfully resized",
                //"logLevel"  => $output['errorMessage'] ? Logger::WARN : Logger::INFO,
                "logOutput" => "Successfully expanded the disk \"$diskFileName\" attached to the vm \"$vmName\"",
                //"cache"        => true,
                //"cacheTTL"     => "3600"
                )); 
            
            
        } catch (\Exception $e) {
            return $this->renderView(array(
                                         "success"   => false,
                                         "error"     => $e->getMessage(),
                                         "trace"     => $e->getTraceAsString(),
                                         "logLevel"  => Logger::ERR,
                                         "logOutput" => $e->getMessage() . " Trace: " . $e->getTraceAsString()
                                     ));
        }
    }

    /**
     * @return JsonModel
     */
    public function getDataCentersAction() {
        try {
            $results = $this->getDataCenters();
    
        } catch (\Exception $e) {
            $prev = $e->getPrevious();
            return $this->renderView(array(
                "success"   => false,
                #"error"     => $e->getMessage() . ": " . $prev->getMessage(),
                "error" => $e->getMessage(),
                "trace"     => $e->getTraceAsString(),
                "logLevel"  => Logger::ERR,
                "logOutput" => $e->getMessage() . " Trace: " . $e->getTraceAsString()
            ));
        }
        // have to return "nodes" as it is part of the tree UI directive
        return $this->renderView(array(
            "success"   => true,
            "error" => $results['errorMessage'],
            "nodes"  => $results['dataCenters'],
            "dataCenters" => $results['dataCenters'],
            "logLevel"  => $results['errorMessage'] ? Logger::WARN : Logger::INFO,
            "logOutput" => count($results['dataCenters']) . " datacenters returned",
            "cache"        => true,
            "cacheTTL"     => "3600"
        ));
    }

    /**
     * @throws \Exception
     * @return mixed|JsonModel
     */
    private function getDataCenters() {
            
        $servers = $this->getEsxServers();

        // errorString to cat any vSphere servers we could not connect to
        $errorString = '';

        $nodes = array();
        $vServer = $server = null;
        $results = null;
        for ($i=0; $i<count($servers); $i++) {
            try {
            	$server = $servers[$i];
            	$vServer = new Vhost($server['server'] . ':' . $server['port'], $server['username'], $server['password']);
                /** @var \Vmwarephp\Extensions\DataCenter[] $results */
                $results = $vServer->findAllManagedObjects('Datacenter', array('name'));
            } catch (\Vmwarephp\Exception\Soap $e) {
                /*	
                if (strpos($e->getMessage(), 'HTTP: Could not connect to host.') !== false) {
                    $errorString .= "Could not connect to vSphere server " . $server['server'] . "\n";
                    $results = array();
                } else {
                    throw new \ErrorException($server['server'] . ": " . $e->getMessage(), $e->getCode());
                }
				 */
            }
            $vServer->disconnect();
 
            // get the hostname
            if (preg_match("/^([\w\d_]+)\.[\w\d_]+", $server['server'], $m)) {
                $vSphereHostName = $m[1];
            } else {
                $vSphereHostName = $server['server'];
            }
            foreach ($results as $dc) {
                if (preg_match("/^\w\wnt/", $vSphereHostName)) {
                    $displayValue = $dc->getName() . " (NT)";
                } else {
                    $displayValue = $dc->getName();
                }
                $nodes[] = array(
                    "vSphereSite"   => $server['site'],
                    "vSphereServer" => $server['server'],
                    "uid"           => $dc->getReferenceId(),
                    "name"          => $dc->getName(),
                    "displayValue"  => $displayValue,
                    "dcName"        => $dc->getName(),
                    "dcUid"         => $dc->getReferenceId(),
                    "hasChildren"   => true,
                    "childrenType"  => "ClusterComputeResources"
                );
            }
        }

        for ($i = 0; $i < count($nodes); $i++) {
            $nodes[$i]['index'] = $i + 1;
        }
        usort($nodes, self::buildSorter('name'));
        
        $output = array('dataCenters' => $nodes, 'errorMessage' => $errorString);
        
        return $output;
    }

    /**
     * @return JsonModel
     */
    public function getClusterComputeResourcesAction() {
        $dataCenterUid = $this->params()->fromRoute('param1');

        $vServer = new Vhost($this->vSphereServer . ':' . $this->vSpherePort, $this->vSphereUsername, $this->vSpherePassword);
        try {
            /** @var \Vmwarephp\Extensions\DataCenter $dc */
            $dataCenter = $vServer->findOneManagedObject('Datacenter', $dataCenterUid, array('name', 'hostFolder', 'networkFolder', 'datastoreFolder', 'vmFolder'));
        } catch (\Exception $e) {
            return $this->renderView(array(
                                         "success"   => false,
                                         "error"     => $e->getMessage(),
                                         "trace"     => $e->getTraceAsString(),
                                         "logLevel"  => Logger::ERR,
                                         "logOutput" => $e->getMessage() . " Trace: " . $e->getTraceAsString()
                                     ));
        }

        try {
            /** @var \Vmwarephp\Extensions\HostFolder $dc */
            $hostFolder = $vServer->findOneManagedObject('Folder', $dataCenter->hostFolder->getReferenceId(), array('name', 'childType', 'childEntity'));
        } catch (\Exception $e) {
            return $this->renderView(array(
                                         "success"   => false,
                                         "error"     => $e->getMessage(),
                                         "trace"     => $e->getTraceAsString(),
                                         "logLevel"  => Logger::ERR,
                                         "logOutput" => $e->getMessage() . " Trace: " . $e->getTraceAsString()
                                     ));
        }

        $nodes    = array();
        $entities = $hostFolder->childEntity;
        for ($i = 0; $i < count($entities); $i++) {
            /** @var \Vmwarephp\Extensions\ClusterComputeResource $entity */
            $entity = $hostFolder->childEntity[$i];
            if ($entity->getReferenceType() == "ClusterComputeResource") {
                try {
                    /** @var \Vmwarephp\Extensions\ClusterComputeResource $dc */
                    $ccr = $vServer->findOneManagedObject('ClusterComputeResource', $entity->getReferenceId(), array('name', 'resourcePool'));
                } catch (\Exception $e) {
                    return $this->renderView(array(
                                                 "success"   => false,
                                                 "error"     => $e->getMessage(),
                                                 "trace"     => $e->getTraceAsString(),
                                                 "logLevel"  => Logger::ERR,
                                                 "logOutput" => $e->getMessage() . " Trace: " . $e->getTraceAsString()
                                             ));
                }
                $nodes[] = array(
                    "vSphereSite"   => $this->vSphereSite,
                    "vSphereServer" => $this->vSphereServer,
                    "dcUid"         => $dataCenterUid,
                    "dcName"        => $dataCenter->getName(),
                    "uid"           => $ccr->getReferenceId(),
                    "ccrUid"        => $ccr->getReferenceId(),
                    "name"          => $ccr->getName(),
                    "ccrName"       => $ccr->getName(),
                    "rpUid"         => $ccr->resourcePool->reference->_,
                    "hasChildren"   => true,
                    "childrenType"  => "ComputeResourceVMs"
                );
            }
        }
        $vServer->disconnect();
        usort($nodes, self::buildSorter('name'));
        for ($i = 0; $i < count($nodes); $i++) {
            $nodes[$i]['index'] = $i + 1;
        }
        return $this->renderView(array(
            "success"   => true,
            "nodes"     => $nodes,
            "clusters"  => $nodes,
            "logLevel"  => Logger::INFO,
            "logOutput" => count($nodes) . " cluster compute resources returned"
        ));
    }

    /**
     * @return JsonModel
     */
    public function getComputeResourceHostsAction() {
        $ccrUid  = $this->params()->fromRoute('param1');
        $vServer = new Vhost($this->vSphereServer . ':' . $this->vSpherePort, $this->vSphereUsername, $this->vSpherePassword);
        try {
            /** @var \Vmwarephp\Extensions\ClusterComputeResource $ccr */
            $ccr = $vServer->findOneManagedObject('ClusterComputeResource', $ccrUid, array('name', 'host'));
        } catch (\Exception $e) {
            return $this->renderView(array(
                                         "success"   => false,
                                         "error"     => $e->getMessage(),
                                         "trace"     => $e->getTraceAsString(),
                                         "logLevel"  => Logger::ERR,
                                         "logOutput" => $e->getMessage() . " Trace: " . $e->getTraceAsString()
                                     ));
        }

        $nodes = array();
        $hosts = $ccr->host;
        foreach ($hosts as $host) {
            try {
                /** @var \Vmwarephp\Extensions\HostSystem $hostSystem */
                $hostSystem = $vServer->findOneManagedObject('HostSystem', $host->getReferenceId(), array('name', 'vm'));
            } catch (\Exception $e) {
                return $this->renderView(array(
                                             "success"   => false,
                                             "error"     => $e->getMessage(),
                                             "trace"     => $e->getTraceAsString(),
                                             "logLevel"  => Logger::ERR,
                                             "logOutput" => $e->getMessage() . " Trace: " . $e->getTraceAsString()
                                         ));
            }
            $nodes[] = array(
                "vSphereSite"   => $this->vSphereSite,
                "vSphereServer" => $this->vSphereServer,
                "uid"           => $hostSystem->getReferenceId(),
                "name"          => $hostSystem->getName(),
                "hasChildren"   => false
            );
        }
        $vServer->disconnect();
        return $this->renderView(array(
                                     "success"   => true,
                                     "nodes"     => $nodes,
                                     "logLevel"  => Logger::INFO,
                                     "logOutput" => count($nodes) . " compute resource hosts returned"
                                 ));
    }

    /**
     * @return JsonModel
     */
    public function getComputeResourceVMsAction() {
        $ccrUid  = $this->params()->fromRoute('param1');
        $vServer = new Vhost($this->vSphereServer . ':' . $this->vSpherePort, $this->vSphereUsername, $this->vSpherePassword);
        try {
            /** @var \Vmwarephp\Extensions\ClusterComputeResource $ccr */
            $ccr = $vServer->findOneManagedObject('ClusterComputeResource', $ccrUid, array('name', 'host'));
        } catch (\Exception $e) {
            return $this->renderView(array(
                                         "success"   => false,
                                         "error"     => $e->getMessage(),
                                         "trace"     => $e->getTraceAsString(),
                                         "logLevel"  => Logger::ERR,
                                         "logOutput" => $e->getMessage() . " Trace: " . $e->getTraceAsString()
                                     ));
        }

        $nodes = array();
        $hosts = $ccr->host;
        if (is_array($hosts) && count($hosts) > 0) {
            /** @var \Vmwarephp\Extensions\HostSystem $host */
            foreach ($hosts as $host) {
                try {
                    /** @var \Vmwarephp\Extensions\HostSystem $hostSystem */
                    $hostSystem = $vServer->findOneManagedObject('HostSystem', $host->getReferenceId(), array('name', 'vm'));
                } catch (\Exception $e) {
                    return $this->renderView(array(
                                                 "success"   => false,
                                                 "error"     => $e->getMessage(),
                                                 "trace"     => $e->getTraceAsString(),
                                                 "logLevel"  => Logger::ERR,
                                                 "logOutput" => $e->getMessage() . " Trace: " . $e->getTraceAsString()
                                             ));
                }
                $vms = $hostSystem->vm;
                if (is_array($vms) && count($vms) > 0) {
                    foreach ($vms as $vm) {
                        if ($vm == "") continue;
                        try {
                            /** @var \Vmwarephp\Extensions\VirtualMachine $virtualMachine */
                            $virtualMachine = $vServer->findOneManagedObject('VirtualMachine', $vm->reference->_, array('name', 'config', 'storage'));
                        } catch (\Exception $e) {
                            return $this->renderView(array(
                                                         "success"   => false,
                                                         "error"     => $e->getMessage(),
                                                         "trace"     => $e->getTraceAsString(),
                                                         "logLevel"  => Logger::ERR,
                                                         "logOutput" => $e->getMessage() . " Trace: " . $e->getTraceAsString()
                                                     ));
                        }
                        $vmConfig = $virtualMachine->getConfig();
                        if ($vmConfig->template || $vmConfig->annotation == "Violin Memory Cluster Management Center") {
                            continue;
                        }

                        $disks   = array();
                        $hw      = $vmConfig->hardware;
                        $devices = $hw->device;
                        foreach ($devices as $device) {
                            if (get_class($device) == "VirtualDisk" && $device->capacityInKB / 1024 / 1024 > 1) {
                                $disks[] = array(
                                    'label'      => $device->deviceInfo->label,
                                    'capacityGB' => round($device->capacityInKB / 1024 / 1024)
                                );
                            }
                        }
                        $nodes[] = array(
                            "vSphereSite"   => $this->vSphereSite,
                            "vSphereServer" => $this->vSphereServer,
                            "ccrUid"        => $ccr->getReferenceId(),
                            "ccrName"       => $ccr->getName(),
                            "uid"           => $virtualMachine->getReferenceId(),
                            "name"          => $virtualMachine->getName(),
                            "numCPU"        => $vmConfig->hardware->numCPU,
                            "memoryGB"      => $vmConfig->hardware->memoryMB / 1024,
                            "disks"         => $disks,
                            "hasChildren"   => false
                        );
                    }
                }
            }
        }
        $vServer->disconnect();

        usort($nodes, self::buildSorter('name'));

        $nmServerTable = new Model\NMServerTable($this->_config);
        for ($i = 0; $i < count($nodes); $i++) {
            $nmServer = $nmServerTable->getByName($nodes[$i]['name']);
            if ($nmServer->getId()) {
                $nodes[$i]['id'] = $nmServer->getId();
            } else {
                $nodes[$i]['id'] = 0;
            }
            $nodes[$i]['index']  = $i + 1;
            $nodes[$i]['length'] = count($nodes);
        }
        return $this->renderView(array(
                                     "success"   => true,
                                     "nodes"     => $nodes,
                                     "logLevel"  => Logger::INFO,
                                     "logOutput" => count($nodes) . " compute resource VMs returned"
                                 ));
    }

    /**
     * @return JsonModel
     */
    public function getClusterComputeResourceNetworksAction() {
        $ccrUid  = $this->params()->fromRoute('param1');
        $vServer = new Vhost($this->vSphereServer . ':' . $this->vSpherePort, $this->vSphereUsername, $this->vSpherePassword);
        try {
            /** @var \Vmwarephp\Extensions\ClusterComputeResource $ccr */
            $ccr = $vServer->findOneManagedObject('ClusterComputeResource', $ccrUid, array('name', 'network'));
        } catch (\Exception $e) {
            return $this->renderView(array(
                                         "success"   => false,
                                         "error"     => $e->getMessage(),
                                         "trace"     => $e->getTraceAsString(),
                                         "logLevel"  => Logger::ERR,
                                         "logOutput" => $e->getMessage() . " Trace: " . $e->getTraceAsString()
                                     ));
        }

        $networks = $ccr->getNetwork();
        $data     = array();
        /** @var \Vmwarephp\ManagedObject $net */
        foreach ($networks as $net) {
            $networkUid = $net->getReferenceId();

            // check for the type. pre 5.5 it'll be "Network" otherwise "DistributedVirtualPortgroup"
            if ($net->getReferenceType() == "Network") {
                $type = "Network";
            } else if ($net->getReferenceType() == "DistributedVirtualPortgroup") {
                $type = "DistributedVirtualPortgroup";
            } else {
                return $this->renderView(array(
                                             "success"   => false,
                                             "error"     => "Unknown object type returned from VMware Cluster Compute Resource Network",
                                             "logLevel"  => Logger::ERR,
                                             "logOutput" => "Unknown object type returned from VMware Cluster Compute Resource Network"
                                         ));
            }

            try {
                /** @var \Vmwarephp\Extensions\Network $network */
                $network = $vServer->findOneManagedObject($type, $networkUid, array('name'));
            } catch (\Exception $e) {
                return $this->renderView(array(
                                             "success"   => false,
                                             "error"     => $e->getMessage(),
                                             "trace"     => $e->getTraceAsString(),
                                             "logLevel"  => Logger::ERR,
                                             "logOutput" => $e->getMessage() . " Trace: " . $e->getTraceAsString()
                                         ));
            }
            if (preg_match("/[Vv][Ll][Aa][Nn](\d+)/", $network->getName(), $m) OR is_numeric(substr($network->getName(), 0, 1))) {
                $data[] = array(
                    "id"           => $network->getReferenceId(),
                    "name"         => $network->getName(),
                    "number"       => $m[1],
                    "vlanId"       => $network->getReferenceId(),
                    "vlanName"     => $network->getName(),
                    "displayValue" => $network->getName()
                );
            }
        }
        usort($data, self::buildSorter('number'));
        return $this->renderView(array(
                                     "success"   => true,
                                     "networks"  => $data,
                                     "logLevel"  => Logger::INFO,
                                     "logOutput" => count($data) . " cluster compute resource networks returned"
                                 ));
    }

    /**
     * @return JsonModel
     */
    public function getResourcePoolsAction() {
        $vServer = new Vhost($this->vSphereServer . ':' . $this->vSpherePort, $this->vSphereUsername, $this->vSpherePassword);
        try {
            /** @var \Vmwarephp\Extensions\ResourcePool[] $pools */
            $pools = $vServer->findAllManagedObjects('ResourcePool', array('name'));
        } catch (\Exception $e) {
            return $this->renderView(array(
                                         "success"   => false,
                                         "error"     => $e->getMessage(),
                                         "trace"     => $e->getTraceAsString(),
                                         "logLevel"  => Logger::ERR,
                                         "logOutput" => $e->getMessage() . " Trace: " . $e->getTraceAsString()
                                     ));
        }

        $data = array();
        foreach ($pools as $p) {
            $data[] = array(
                "uid"  => $p->getReferenceId(),
                "name" => $p->getName()
            );
        }

        $vServer->disconnect();
        return $this->renderView(array(
                                     "success"   => true,
                                     "pools"     => $data,
                                     "logLevel"  => Logger::INFO,
                                     "logOutput" => count($data) . " resource pools returned"
                                 ));
    }

    /**
     * @return JsonModel
     */
    public function getStoragePodsAction() {
        $vServer = new Vhost($this->vSphereServer . ':' . $this->vSpherePort, $this->vSphereUsername, $this->vSpherePassword);
        try {
            /** @var \Vmwarephp\Extensions\StoragePod[] $storagePods */
            $storagePods = $vServer->findAllManagedObjects('StoragePod', array('name'));
        } catch (\Exception $e) {
            return $this->renderView(array(
                                         "success"   => false,
                                         "error"     => $e->getMessage(),
                                         "trace"     => $e->getTraceAsString(),
                                         "logLevel"  => Logger::ERR,
                                         "logOutput" => $e->getMessage() . " Trace: " . $e->getTraceAsString()
                                     ));
        }

        $data = array();
        foreach ($storagePods as $p) {
            $data[] = array(
                "uid"  => $p->getReferenceId(),
                "name" => $p->getName()
            );
        }

        $vServer->disconnect();
        return $this->renderView(array(
                                     "success"     => true,
                                     "storagePods" => $data,
                                     "logLevel"    => Logger::INFO,
                                     "logOutput"   => count($data) . " storage pods returned"
                                 ));
    }

    /**
     * @return JsonModel
     */
    public function getStoragePodByClusterComputeResourceAction() {
        $ccrUid = $this->params()->fromRoute('param1');
        $sp     = $this->getStoragePodByClusterComputeResource($ccrUid);

        if (!$sp) {
            return $this->renderView($this->_viewData);
        }
        return $this->renderView(array(
                                     "success"    => true,
                                     "storagePod" => $sp,
                                     "logLevel"   => Logger::INFO
                                 ));
    }

    /**
     * @param $ccrUid
     * @return array|JsonModel
     */
    private function getStoragePodByClusterComputeResource($ccrUid) {
        $vServer = new Vhost($this->vSphereServer . ':' . $this->vSpherePort, $this->vSphereUsername, $this->vSpherePassword);

        // lookup the CCR
        try {
            /** @var \Vmwarephp\Extensions\ClusterComputeResource $ccr */
            $ccr = $vServer->findOneManagedObject('ClusterComputeResource', $ccrUid, array('name'));
        } catch (\Exception $e) {
            $this->_viewData = array(
                "success"   => false,
                "error"     => $e->getMessage(),
                "trace"     => $e->getTraceAsString(),
                "logLevel"  => Logger::ERR,
                "logOutput" => $e->getMessage() . " Trace: " . $e->getTraceAsString()
            );
            return false;
        }

        // get all the storage pods for this vSphere server
        try {
            /** @var \Vmwarephp\Extensions\StoragePod[] $storagePods */
            $storagePods = $vServer->findAllManagedObjects('StoragePod', array('name'));
        } catch (\Exception $e) {
            $this->_viewData = array(
                "success"   => false,
                "error"     => $e->getMessage(),
                "trace"     => $e->getTraceAsString(),
                "logLevel"  => Logger::ERR,
                "logOutput" => $e->getMessage() . " Trace: " . $e->getTraceAsString()
            );
            return false;
        }

        // now try to match the storage pod and the CCR using their names
        $sp = array();
        foreach ($storagePods as $p) {
            if ($ccr->getName() . "_DS" == $p->getName()) {
                $sp = array(
                    "uid"  => $p->getReferenceId(),
                    "name" => $p->getName()
                );
            }
        }

        $vServer->disconnect();
        return $sp;
    }

    /**
     * @return JsonModel
     */
    public function getDataStoresAction() {
        $vServer = new Vhost($this->vSphereServer . ':' . $this->vSpherePort, $this->vSphereUsername, $this->vSpherePassword);
        try {
            /** @var \Vmwarephp\Extensions\Datastore[] $dataStores */
            $dataStores = $vServer->findAllManagedObjects('Datastore', array('name', 'info'));
        } catch (\Exception $e) {
            return $this->renderView(array(
                                         "success"   => false,
                                         "error"     => $e->getMessage(),
                                         "trace"     => $e->getTraceAsString(),
                                         "logLevel"  => Logger::ERR,
                                         "logOutput" => $e->getMessage() . " Trace: " . $e->getTraceAsString()
                                     ));
        }

        $data = array();
        foreach ($dataStores as $p) {
            $info   = $p->getInfo();
            $size   = property_exists($info, 'vmfs') ? round($info->vmfs->capacity / 1024 / 1024 / 1024) : 'N/A';
            $free   = round($info->freeSpace / 1024 / 1024 / 1024);
            $data[] = array(
                "uid"          => $p->getReferenceId(),
                "name"         => $p->getName(),
                "size"         => $size,
                "free"         => $free,
                "displayValue" => "{$p->getName()} ({$free} GB of {$size} GB free)"
            );
        }

        $vServer->disconnect();
        return $this->renderView(array(
                                     "success"    => true,
                                     "dataStores" => $data,
                                     "logLevel"   => Logger::INFO,
                                     "logOutput"  => count($data) . " data stores returned"
                                 ));
    }

    /**
     * @return JsonModel
     */
    public function getHostSystemsAction() {
        $vServer = new Vhost($this->vSphereServer . ':' . $this->vSpherePort, $this->vSphereUsername, $this->vSpherePassword);

        try {
            /** @var \Vmwarephp\Extensions\HostSystem[] $systems */
            $systems = $vServer->findAllManagedObjects('HostSystem', array('name', 'runtime'));
        } catch (\Exception $e) {
            return $this->renderView(array(
                                         "success"   => false,
                                         "error"     => $e->getMessage(),
                                         "trace"     => $e->getTraceAsString(),
                                         "logLevel"  => Logger::ERR,
                                         "logOutput" => $e->getMessage() . " Trace: " . $e->getTraceAsString()
                                     ));
        }

        $data = array();
        foreach ($systems as $s) {
            $data[] = array(
                "uid"  => $s->getReferenceId(),
                "name" => $s->getName(),
                "connectionState" => $s->runtime->connectionState,
                "powerState" => $s->runtime->powerState
            );
        }

        $vServer->disconnect();
        return $this->renderView(array(
                                     "success"     => true,
                                     "hostSystems" => $data,
                                     "logLevel"    => Logger::INFO,
                                     "logOutput"   => count($data) . " host systems returned"
                                 ));
    }

    public function getHostSystemAction() {
        $hostName = $this->params()->fromRoute('param1');

        $vServer = new Vhost($this->vSphereServer . ':' . $this->vSpherePort, $this->vSphereUsername, $this->vSpherePassword);
        /** @var \Vmwarephp\Extensions\HostSystem $system */
        $system = $vServer->findManagedObjectByName('HostSystem', $hostName, array('name'));
        $hsConfig = $this->getVMwareHostSystem($vServer, $system->getReferenceId());

        return $this->renderView($hsConfig, 'pre');
    }

    public function getHostSystemByUidAction() {
        $hostUid = $this->params()->fromRoute('param1');

        $vServer = new Vhost($this->vSphereServer . ':' . $this->vSpherePort, $this->vSphereUsername, $this->vSpherePassword);
        $hsConfig = $this->getVMwareHostSystem($vServer, $hostUid);

        return $this->renderView($hsConfig, 'pre');
    }

    /**
     * @return JsonModel
     */
    public function getHostSystemsByDataCenterAction() {
        $dcUid       = $this->params()->fromRoute('param1');
        $hostSystems = $this->getHostSystemsByDataCenter($dcUid);

        if (!$hostSystems) {
            return $this->renderView($this->_viewData);
        }
        return $this->renderView(array(
                                     "success"   => true,
                                     "nodes"     => $hostSystems,
                                     "logLevel"  => Logger::INFO,
                                     "logOutput" => count($hostSystems) . " host systems returned"
                                 ));
    }

    /**
     * Get a list of host system given a data center uid
     *
     * @param $dcUid
     * @return array|JsonModel
     */
    private function getHostSystemsByDataCenter($dcUid) {
        $vServer = new Vhost($this->vSphereServer . ':' . $this->vSpherePort, $this->vSphereUsername, $this->vSpherePassword);
        try {
            /** @var \Vmwarephp\Extensions\DataCenter $dc */
            $dataCenter = $vServer->findOneManagedObject('Datacenter', $dcUid, array('name', 'hostFolder'));
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

        try {
            $hostFolder = $vServer->findOneManagedObject('Folder', $dataCenter->getHostFolder()->getReferenceId(), array('name', 'childType', 'childEntity'));
        } catch (\Exception $e) {
            $this->_viewData = array(
                "success"   => false,
                "error"     => $e->getMessage(),
                "trace"     => $e->getTraceAsString(),
                "logLevel"  => Logger::ERR,
                "logOutput" => $e->getMessage() . " Trace: " . $e->getTraceAsString()
            );
            return false;
        }

        $nodes    = array();
        $entities = $hostFolder->childEntity;
        for ($i = 0; $i < count($entities); $i++) {
            /** @var \Vmwarephp\Extensions\ClusterComputeResource $entity */
            $entity = $hostFolder->childEntity[$i];
            if ($entity->getReferenceType() == "ClusterComputeResource") {
                try {
                    /** @var \Vmwarephp\Extensions\ClusterComputeResource $ccr */
                    $ccr = $vServer->findOneManagedObject('ClusterComputeResource', $entity->getReferenceId(), array('host'));
                } catch (\Exception $e) {
                    $this->_viewData = array(
                        "success"   => false,
                        "error"     => $e->getMessage(),
                        "trace"     => $e->getTraceAsString(),
                        "logLevel"  => Logger::ERR,
                        "logOutput" => $e->getMessage() . " Trace: " . $e->getTraceAsString()
                    );
                    return false;
                }

                foreach ($ccr->host as $host) {
                    if (!is_object($host)) continue;

                    try {
                        /** @var \Vmwarephp\Extensions\HostSystem $hostSystem */
                        $hostSystem = $vServer->findOneManagedObject('HostSystem', $host->getReferenceId(), array('name'));
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
                    $nodes[] = array(
                        "uid"  => $hostSystem->getReferenceId(),
                        "name" => $hostSystem->getName()
                    );
                }
            }
        }
        $vServer->disconnect();
        return $nodes;
    }


    /**
     * @return JsonModel
     */
    public function getHostSystemsByClusterComputeResourceAction() {
        $ccrUid = $this->params()->fromRoute('param1');
        $hostSystems = $this->getVMwareHostSystemsByClusterComputeResource($ccrUid);

        if (!$hostSystems) {
            return $this->renderView($this->_viewData);
        }

        return $this->renderView(array(
            "success"   => true,
            "hostSystems" => $hostSystems,
            "logLevel"  => Logger::INFO,
            "logOutput" => count($hostSystems) . " host systems returned"
        ));
    }

    /**
     * @return JsonModel
     */
    public function getNetworksAction() {
        try {
            $networks = $this->getNetworks();
        } catch (\Exception $e) {
            #$prev = $e->getPrevious();
            return $this->renderView(array(
                                         "success"   => false,
                                         "error"     => $e->getMessage(),
                                         "trace"     => $e->getTraceAsString(),
                                         "logLevel"  => Logger::ERR,
                                         "logOutput" => $e->getMessage() . " Trace: " . $e->getTraceAsString()
                                     ));
        }
        return $this->renderView(array(
                                     "success"   => true,
                                     "networks"  => $networks,
                                     "logLevel"  => Logger::INFO,
                                     "logOutput" => count($networks) . " networks returned"
                                 ));
    }

    /**
     * @throws \Exception
     * @return array
     */
    private function getNetworks() {
        $vServer = new Vhost($this->vSphereServer . ':' . $this->vSpherePort, $this->vSphereUsername, $this->vSpherePassword);
        try {
            /** @var \Vmwarephp\Extensions\Network[] $networks */
            $networks = $vServer->findAllManagedObjects('Network', array('name'));
        } catch (\Vmwarephp\Exception\Soap $e) {
            throw new \Exception(null, null, $e);
        }

        $data = array();
        foreach ($networks as $n) {
            $data[] = array(
                "id"           => $n->getReferenceId(),
                "vlanId"       => $n->getReferenceId(),
                "name"         => $n->getName(),
                "displayValue" => $n->getName()
            );
        }
        $vServer->disconnect();
        return $data;
    }

    /**
     * @return JsonModel
     */
    public function getVMByIdAction() {
        $serverId = $this->params()->fromRoute('param1');

        $nmServerTable = new Model\NMServerTable($this->_config);
        $nmServer      = $nmServerTable->getById($serverId);

        $vServer = new Vhost($this->vSphereServer . ':' . $this->vSpherePort, $this->vSphereUsername, $this->vSpherePassword);
        try {
            /** @var \Vmwarephp\Extensions\VirtualMachine $vm */
            $vm = $vServer->findManagedObjectByName('VirtualMachine', $nmServer->getName(), array('name', 'config', 'guest'));
        } catch (\Exception $e) {
            return $this->renderView(array(
                                         "success"   => false,
                                         "error"     => $e->getMessage(),
                                         "trace"     => $e->getTraceAsString(),
                                         "logLevel"  => Logger::ERR,
                                         "logOutput" => $e->getMessage() . " Trace: " . $e->getTraceAsString()
                                     ));
        }
        $data = array(
            "id"     => $vm->getReferenceId(),
            "name"   => $vm->getName(),
            "extra"  => $vm->getConfig()->extraConfig,
            "config" => $vm->getConfig(),
            "guest"  => $vm->getGuest()
        );

        $vServer->disconnect();
        return $this->renderView(array(
                                     "success"   => true,
                                     "data"      => $data,
                                     "logLevel"  => Logger::INFO,
                                     "logOutput" => "VM " . $vm->getName() . " returned"
                                 ));
    }

    /**
     * @return JsonModel
     */
    public function getVMByNameAction() {
        $vmName = $this->params()->fromRoute('param1');

        $vServer = new Vhost($this->vSphereServer . ':' . $this->vSpherePort, $this->vSphereUsername, $this->vSpherePassword);
        try {
            /** @var \Vmwarephp\Extensions\VirtualMachine $vm */
            $vm = $vServer->findManagedObjectByName('VirtualMachine', $vmName, array('name', 'config', 'guest'));

        } catch (\Exception $e) {
            return $this->renderView(array(
                                         "success"   => false,
                                         "error"     => $e->getMessage(),
                                         "trace"     => $e->getTraceAsString(),
                                         "logLevel"  => Logger::ERR,
                                         "logOutput" => $e->getMessage() . " Trace: " . $e->getTraceAsString()
                                     ));
        }
        $data = array(
            "id"     => $vm->getReferenceId(),
            "name"   => $vm->getName(),
            "config" => $vm->getConfig(),
            "guest"  => $vm->getGuest()
        );

        $vServer->disconnect();
        return $this->renderView(array(
                                     "success"   => true,
                                     "data"      => $data,
                                     "logLevel"  => Logger::INFO,
                                     "logOutput" => "VM " . $vmName . " returned"
                                 ));
    }

    /**
     * @return JsonModel
     */
    public function getVMsAction() {
        $vServer = new Vhost($this->vSphereServer . ':' . $this->vSpherePort, $this->vSphereUsername, $this->vSpherePassword);
        try {
            /** @var \Vmwarephp\Extensions\VirtualMachine[] $vms */
            $vms = $vServer->findAllManagedObjects('VirtualMachine', array('name'));
        } catch (\Exception $e) {
            return $this->renderView(array(
                                         "success"   => false,
                                         "error"     => $e->getMessage(),
                                         "trace"     => $e->getTraceAsString(),
                                         "logLevel"  => Logger::ERR,
                                         "logOutput" => $e->getMessage() . " Trace: " . $e->getTraceAsString()
                                     ));
        }

        $data = array();
        foreach ($vms as $vm) {
            $data[] = array(
                "id"   => $vm->getReferenceId(),
                "name" => $vm->getName(),
            );
        }

        $vServer->disconnect();
        return $this->renderView(array(
                                     "success"   => true,
                                     "vms"       => $data,
                                     "logLevel"  => Logger::INFO,
                                     "logOutput" => count($data) . " VMs returned"
                                 ));
    }

    /**
     * @return JsonModel
     */
    public function getAllVMsAction() {
        $nodes = array();

        // get all the datacenters
        try {
            $results = $this->getDataCenters();
        } catch (\Exception $e) {
            $prev = $e->getPrevious();
            return $this->renderView(array(
                "success"   => false,
                "error"     => $e->getMessage() . ": " . ($prev ? $prev->getMessage() : ""),
                "trace"     => $e->getTraceAsString(),
                "logLevel"  => Logger::ERR,
                "logOutput" => $e->getMessage() . " Trace: " . $e->getTraceAsString()
            ));
        }

        $dataCenters = $results['dataCenters'];
        // loop thru each datacenter
        foreach ($dataCenters as $dc) {
            // define the vsphere site from the datacenter
            $this->defineVSphereServer($dc['vSphereSite']);

            // connect to vsphere
            $vServer = new Vhost($this->vSphereServer . ':' . $this->vSpherePort, $this->vSphereUsername, $this->vSpherePassword);

            // get the datacenter hostFolder
            try {
                /** @var \Vmwarephp\Extensions\DataCenter $dc */
                $dataCenter = $vServer->findOneManagedObject('Datacenter', $dc['dcUid'], array('name', 'hostFolder'));
            } catch (\Vmwarephp\Exception\Soap $e) {
                    return $this->renderView(array(
                                                 "success"   => false,
                                                 "error"     => $e->getMessage(),
                                                 "trace"     => $e->getTraceAsString(),
                                                 "logLevel"  => Logger::ERR,
                                                 "logOutput" => $e->getMessage() . " Trace: " . $e->getTraceAsString()
                                             ));
            }

            // get hostFolder info
            try {
                /** @var \Vmwarephp\Extensions\HostFolder $hostFolder */
                $hostFolder = $vServer->findOneManagedObject('Folder', $dataCenter->getHostFolder()->getReferenceId(), array('name', 'childType', 'childEntity'));
            } catch (\Exception $e) {
                return $this->renderView(array(
                                             "success"   => false,
                                             "error"     => $e->getMessage(),
                                             "trace"     => $e->getTraceAsString(),
                                             "logLevel"  => Logger::ERR,
                                             "logOutput" => $e->getMessage() . " Trace: " . $e->getTraceAsString()
                                         ));
            }

            // loop thru the folder entities
            foreach ($hostFolder->getChildren() as $entity) {
                // if ClusterComputeResource, then get the ccr, host and its vms
                if (property_exists($entity, 'reference') &&
                    property_exists($entity->reference, 'type') &&
                    $entity->reference->type == "ClusterComputeResource") {
                    // get the ClusterComputeResource data
                    try {
                        /** @var \Vmwarephp\Extensions\ClusterComputeResource $ccr */
                        $ccr = $vServer->findOneManagedObject('ClusterComputeResource', $entity->reference->_, array('name', 'host'));
                    } catch (\Exception $e) {
                        return $this->renderView(array(
                                                     "success"   => false,
                                                     "error"     => $e->getMessage(),
                                                     "trace"     => $e->getTraceAsString(),
                                                     "logLevel"  => Logger::ERR,
                                                     "logOutput" => $e->getMessage() . " Trace: " . $e->getTraceAsString()
                                                 ));
                    }

                    // loop thru each host in the CCR
                    foreach ($ccr->host as $host) {
                        if (!is_object($host)) continue;
                        // get the HostSystem and its VMs
                        try {
                            /** @var \Vmwarephp\Extensions\HostSystem $hostSystem */
                            $hostSystem = $vServer->findOneManagedObject('HostSystem', $host->getReferenceId(), array('name', 'vm'));
                        } catch (\Exception $e) {
                            return $this->renderView(array(
                                                         "success"   => false,
                                                         "error"     => $e->getMessage(),
                                                         "trace"     => $e->getTraceAsString(),
                                                         "logLevel"  => Logger::ERR,
                                                         "logOutput" => $e->getMessage() . " Trace: " . $e->getTraceAsString()
                                                     ));
                        }

                        // loop thru each VM
                        foreach ($hostSystem->vm as $vmEntity) {
                            // get the VM name
                            if (is_object($vmEntity)) {
                                try {
                                    $virtualMachine = $vServer->findOneManagedObject('VirtualMachine', $vmEntity->reference->_, array('name', 'config', 'summary'));
                                } catch (\Exception $e) {
                                    return $this->renderView(array(
                                                                 "success"   => false,
                                                                 "error"     => $e->getMessage(),
                                                                 "trace"     => $e->getTraceAsString(),
                                                                 "logLevel"  => Logger::ERR,
                                                                 "logOutput" => $e->getMessage() . " Trace: " . $e->getTraceAsString()
                                                             ));
                                }
                                // add to our return array
                                $nodes[] = array(
                                    "hsUid"    => $hostSystem->getReferenceId(),
                                    "hsName"   => $hostSystem->getName(),
                                    "ccrName"  => $ccr->getName(),
                                    "vmUid"    => $virtualMachine->getReferenceId(),
                                    "vmName"   => $virtualMachine->getName(),
                                    "numCPUs"  => $virtualMachine->getConfig()->hardware->numCPU,
                                    "memoryGB" => $virtualMachine->getConfig()->hardware->memoryMB / 1024,
                                    "guestMemUsageMB" => $virtualMachine->summary->quickStats->guestMemoryUsage,
                                    "overallCpuUsageMHz" => $virtualMachine->summary->quickStats->overallCpuUsage,
                                );
                            }
                        }
                    }
                }
            }
            $vServer->disconnect();
        }

        /**
         * now loop thru the nodes and construct a json object like that returned for the hypervisor query
         * {
         *   "hypervisors": {
         *       "stomcprkvm01.va.neustar.com": {
         *           "status": "OK",
         *           "vms": {
         *               "stomcqavfe3.va.neustar.com": {
         *                   "status": "running"
         *               },
         *               "stomcqafmw1.va.neustar.com": {
         *                   "status": "running"
         *               }
         *           }
         *       },
         *   }
         * }
         *
         */
        $hypers = array();
        foreach ($nodes as $node) {
            if (!array_key_exists($node['hsName'], $hypers)) {
                $hypers[$node['hsName']] = array(
                    "status"  => "OK",
                    "id"      => $node['hsUid'],
                    "name"    => $node['hsName'],
                    "ccrName" => $node['ccrName'],
                    "vms"     => array()
                );
            }
            $hypers[$node['hsName']]['vms'][$node['vmName']] = array(
                "status"             => "OK",
                "id"                 => $node['vmUid'],
                "numCPUs"            => $node['numCPUs'],
                "memoryGB"           => $node['memoryGB'],
                "guestMemUsageMB"    => $node['guestMemUsageMB'],
                "overallCpuUsageMHz" => $node['overallCpuUsageMHz'],
            );
        }
        return $this->renderView(array(
                                     "success"     => true,
                                     "hypervisors" => $hypers,
                                     "logLevel"    => Logger::INFO,
                                     "logOutput"   => count($hypers) . " hypervisors returned"
                                 ));
    }


    /**
     * @return JsonModel
     */
    public function getVMsByHostSystemAction() {
        $hsName  = $this->params()->fromRoute('param1');
        $vServer = new Vhost($this->vSphereServer . ':' . $this->vSpherePort, $this->vSphereUsername, $this->vSpherePassword);
        try {
            /** @var \Vmwarephp\Extensions\HostSystem $hostSystem */
            $hostSystem = $vServer->findManagedObjectByName('HostSystem', $hsName, array('name', 'vm'));
        } catch (\Exception $e) {
            return $this->renderView(array(
                                         "success"   => false,
                                         "error"     => $e->getMessage(),
                                         "trace"     => $e->getTraceAsString(),
                                         "logLevel"  => Logger::ERR,
                                         "logOutput" => $e->getMessage() . " Trace: " . $e->getTraceAsString()
                                     ));
        }

        $nodes = array();
        foreach ($hostSystem->vm as $vm) {
            try {
                /** @var \Vmwarephp\Extensions\VirtualMachine $virtual */
                $virtual = $vServer->findOneManagedObject('VirtualMachine', $vm->reference->_, array('name', 'config', 'summary'));
            } catch (\Exception $e) {
                return $this->renderView(array(
                                             "success"   => false,
                                             "error"     => $e->getMessage(),
                                             "trace"     => $e->getTraceAsString(),
                                             "logLevel"  => Logger::ERR,
                                             "logOutput" => $e->getMessage() . " Trace: " . $e->getTraceAsString()
                                         ));
            }
            $nodes[] = array(
                "id"   => $virtual->getReferenceId(),
                "name" => $virtual->getName(),
                "config" => $virtual->getConfig(),
                "quickStats" => $virtual->summary->quickStats
            );
        }

        $vServer->disconnect();
        return $this->renderView(array(
                                     "success"   => true,
                                     "vms"       => $nodes,
                                     "logLevel"  => Logger::INFO,
                                     "logOutput" => count($nodes) . " VMs returned"
                                 ));
    }

    /**
     * @return JsonModel
     */
    public function getVMsByHostSystemUidAction() {
        $hsUid = $this->params()->fromRoute('param1');
        try {
            $vms = $this->getVMsByHostSystemUid($hsUid);
        } catch (\Exception $e) {
            return $this->renderView(array(
                                         "success"   => false,
                                         "error"     => $e->getMessage(),
                                         "trace"     => $e->getTraceAsString(),
                                         "logLevel"  => Logger::ERR,
                                         "logOutput" => $e->getMessage() . " Trace: " . $e->getTraceAsString()
                                     ));
        }
        return $this->renderView(array(
                                     "success"   => true,
                                     "vms"       => $vms,
                                     "logLevel"  => Logger::INFO,
                                     "logOutput" => count($vms) . " VMs returned"
                                 ));
    }

    /**
     * @param $hsUid
     * @return array|JsonModel
     */
    public function getVMsByHostSystemUid($hsUid) {
        $vServer = new Vhost($this->vSphereServer . ':' . $this->vSpherePort, $this->vSphereUsername, $this->vSpherePassword);
        try {
            /** @var \Vmwarephp\Extensions\HostSystem $hostSystem */
            $hostSystem = $vServer->findOneManagedObject('HostSystem', $hsUid, array('name', 'vm'));
        } catch (\Exception $e) {
            return $this->renderView(array(
                                         "success"   => false,
                                         "error"     => $e->getMessage(),
                                         "trace"     => $e->getTraceAsString(),
                                         "logLevel"  => Logger::ERR,
                                         "logOutput" => $e->getMessage() . " Trace: " . $e->getTraceAsString()
                                     ));
        }

        $nodes = array();
        foreach ($hostSystem->vm as $vm) {
            try {
                /** @var \Vmwarephp\Extensions\VirtualMachine $virtualMachine */
                $virtualMachine = $vServer->findOneManagedObject('VirtualMachine', $vm->reference->_, array('name', 'config', 'summary'));
            } catch (\Exception $e) {
                return $this->renderView(array(
                                             "success"   => false,
                                             "error"     => $e->getMessage(),
                                             "trace"     => $e->getTraceAsString(),
                                             "logLevel"  => Logger::ERR,
                                             "logOutput" => $e->getMessage() . " Trace: " . $e->getTraceAsString()
                                         ));
            }
            $nodes[] = array(
                "id"   => $virtualMachine->getReferenceId(),
                "name" => $virtualMachine->getName(),
                "config" => $virtualMachine->getConfig(),
                "quickStats" => $virtualMachine->summary->quickStats
            );
        }
        $vServer->disconnect();
        return $nodes;
    }

    /**
     * @return JsonModel
     */
    public function checkIfVMExistsAction() {
        $serverId = $this->params()->fromRoute('param1');

        $nmServerTable = new Model\NMServerTable($this->_config);
        $nmServer      = $nmServerTable->getById($serverId);

        $nmVmwareTable = new Model\NMVMWareTable($this->_config);
        $nmVmware      = $nmVmwareTable->getByServerId($serverId);

        foreach($this->_config['vSphere'] AS $vSphereSiteName=>$vSphereSiteConfig){
            if($vSphereSiteName != 'site'){
                
                
        

                try {
                	$vServer = new Vhost($vSphereSiteConfig['server'] . ':' . $vSphereSiteConfig['port'], $vSphereSiteConfig['username'], $vSphereSiteConfig['password']);
                	$this->defineVSphereServer($vSphereSiteName);
                    /** @var \Vmwarephp\Extensions\VirtualMachine $vm */
                    $vm = $vServer->findManagedObjectByName('VirtualMachine', $nmServer->getName());
                } catch (\Exception $e) {
                	/*
                    return $this->renderView(array(
                                                 "success"   => false,
                                                 "error"     => $e->getMessage(),
                                                 "trace"     => $e->getTraceAsString(),
                                                 "logLevel"  => Logger::ERR,
                                                 "logOutput" => $e->getMessage() . " Trace: " . $e->getTraceAsString()
                                             ));
					 */
					 
                }
                $vServer->disconnect();
        
                if ($vm && $vm->getReferenceId()) {
                    return $this->renderView(array(
                                                 "success"   => true,
                                                 "vmExists"  => true,
                                                 "logLevel"  => Logger::DEBUG,
                                                 "logOutput" => "VM " . $nmServer->getName() . " exists"
                                             ));
                }
            }
        }
        return $this->renderView(array(
                                             "success"   => true,
                                             "vmExists"  => false,
                                             "logLevel"  => Logger::DEBUG,
                                             "logOutput" => "VM " . $nmServer->getName() . " does not exist"
                                         ));
        
    }
    /**
     * @return JsonModel
     */
    public function getLastSoapRequestAction() {
        return $this->renderView(file_get_contents($this->xmlFile), "xml");
    }

    /**
     * @return JsonModel
     */
    public function deleteVMAction() {
        $serverId = $this->params()->fromRoute('param1');

        $nmServerTable = new Model\NMServerTable($this->_config);
        $nmServer      = $nmServerTable->getById($serverId);

        $nmVmwareTable = new Model\NMVMWareTable($this->_config);
        $nmVmware      = $nmVmwareTable->getByServerId($serverId);

        foreach($this->_config['vSphere'] AS $vSphereSiteName=>$vSphereSiteConfig){
            if($vSphereSiteName != 'site'){
                $vServer = new Vhost($vSphereSiteConfig['server'] . ':' . $vSphereSiteConfig['port'], $vSphereSiteConfig['username'], $vSphereSiteConfig['password']);
                $this->defineVSphereServer($vSphereSiteName);
                
                try {
                    /** @var \Vmwarephp\Extensions\VirtualMachine $vm */
                    $vm = $vServer->findManagedObjectByName('VirtualMachine', $nmServer->getName(), array('name', 'runtime'));
                } catch (\Exception $e) {
                    $vServer->disconnect();
					continue;
					/*
                    return $this->renderView(
                                array(
                                    "success"   => false,
                                    "error"     => $e->getMessage(),
                                    "trace"     => $e->getTraceAsString(),
                                    "logLevel"  => Logger::ERR,
                                    "logOutput" => $e->getMessage() . " Trace: " . $e->getTraceAsString()
                                )
                    );
					 */ 
                }
        
                if (!$vm || !property_exists($vm, 'runtime')) {
                    $vServer->disconnect();
                    continue;
                }
        
                // check if powered on first
                if ($vm->runtime->powerState == 'poweredOn') {
                    $task = $vServer->powerOffVMTask($vm);
                    if (!$this->watchTask($vServer, $task)) {
                        return $this->renderView($this->_viewData);
                    }
                }
        
                /** @var Task $task */
                $task = $vServer->destoryTask($vm);
                if (!$task = $this->watchTask($vServer, $task)) {
                    return $this->renderView($this->_viewData);
                }
        
                $vServer->disconnect();
                
                $returnArray = array(
                                "success"    => true,
                                "logLevel"   => Logger::NOTICE,
                                "logOutput"  => "VM " . $nmServer->getName() . " has been deleted from " . $nmVmware->getVSphereSite(),
                                "parameters" => "[serverName: {$nmServer->getName()}]"
                            );
                
                
                
            }
        }

        if(isset($returnArray) AND isset($returnArray['success'])){
            return $this->renderView($returnArray);
        }else{
            return $this->renderView(
                array(
                    "success"   => true,
                    "logLevel"  => Logger::INFO,
                    "logOutput" => "VM " . $nmServer->getName() . " not found in any vSphere server to delete"
                )
            );    
        }
        


    }
    /**
     * @return JsonModel
     */
    public function deleteVMByNameAction() {
        $vmName = $this->params()->fromRoute('param1');
        $vServer = new Vhost($this->vSphereServer . ':' . $this->vSpherePort, $this->vSphereUsername, $this->vSpherePassword);
        try {
            /** @var \Vmwarephp\Extensions\VirtualMachine $vm */
            $vm = $vServer->findManagedObjectByName('VirtualMachine', $vmName, array('name', 'runtime'));
        } catch (\Exception $e) {
            $vServer->disconnect();
            return $this->renderView(
                        array(
                            "success"   => false,
                            "error"     => $e->getMessage(),
                            "trace"     => $e->getTraceAsString(),
                            "logLevel"  => Logger::ERR,
                            "logOutput" => $e->getMessage() . " Trace: " . $e->getTraceAsString()
                        )
            );
        }

        // ensure that we found the vm first
        if (!$vm || !property_exists($vm, 'runtime')) {
            $vServer->disconnect();
            return $this->renderView(
                        array(
                            "success"   => true,
                            "logLevel"  => Logger::INFO,
                            "logOutput" => "VM " . $vmName . " not found "
                        )
            );
        }

        // check if powered on first
        if ($vm->runtime->powerState == 'poweredOn') {
            $task = $vServer->powerOffVMTask($vm);
            if (!$this->watchTask($vServer, $task)) {
                return $this->renderView($this->_viewData);
            }
        }

        /** @var Task $task */
        $task = $vServer->destoryTask($vm);
        if (!$task = $this->watchTask($vServer, $task)) {
            return $this->renderView($this->_viewData);
        }

        $vServer->disconnect();
        return $this->renderView(
                    array(
                        "success"    => true,
                        "logLevel"   => Logger::NOTICE,
                        "logOutput"  => "VM " . $vmName . " has been deleted ",
                        "parameters" => "[vmName: {$vmName}]"
                    )
        );
    }
    /**
     * @return JsonModel
     */
    public function powerOffVMAction() {
        $serverId = $this->params()->fromRoute('param1');

        $nmServerTable = new Model\NMServerTable($this->_config);
        $nmServer      = $nmServerTable->getById($serverId);

        $nmVmwareTable = new Model\NMVMWareTable($this->_config);
        $nmVmware      = $nmVmwareTable->getByServerId($serverId);

        $this->defineVSphereServer($nmVmware->getVSphereSite());

        try {
            $vServer = new Vhost($this->vSphereServer . ':' . $this->vSpherePort, $this->vSphereUsername, $this->vSpherePassword);
        } catch (\Exception $e) {
            return $this->renderView(array(
                                         "success"   => false,
                                         "error"     => $e->getMessage(),
                                         "trace"     => $e->getTraceAsString(),
                                         "logLevel"  => Logger::ERR,
                                         "logOutput" => $e->getMessage() . " Trace: " . $e->getTraceAsString()
                                     ));
        }

        try {
            /** @var \Vmwarephp\Extensions\VirtualMachine $vm */
            $vm = $vServer->findManagedObjectByName('VirtualMachine', $nmServer->getName(), array('name'));
        } catch (\Exception $e) {
            return $this->renderView(array(
                                         "success"   => false,
                                         "error"     => $e->getMessage(),
                                         "trace"     => $e->getTraceAsString(),
                                         "logLevel"  => Logger::ERR,
                                         "logOutput" => $e->getMessage() . " Trace: " . $e->getTraceAsString()
                                     ));
        }

        // check if powered on first
        if ($vm->runtime->powerState == 'poweredOn') {
            try {
                /** @var Task $task */
                $task = $vServer->powerOffVMTask($vm);
            } catch (\Exception $e) {
                return $this->renderView(array(
                                             "success"   => false,
                                             "error"     => $e->getMessage(),
                                             "trace"     => $e->getTraceAsString(),
                                             "logLevel"  => Logger::ERR,
                                             "logOutput" => $e->getMessage() . " Trace: " . $e->getTraceAsString()
                                         ));
            }
            if (!$this->watchTask($vServer, $task)) {
                return $this->renderView($this->_viewData);
            }
        }

        $vServer->disconnect();
        return $this->renderView(array(
                                     "success"    => true,
                                     "logLevel"   => Logger::NOTICE,
                                     "logOutput"  => "VM " . $nmServer->getName() . " in " . $nmVmware->getVSphereSite() . " has been powered off",
                                     "parameters" => "[serverName: {$nmServer->getName()}]"
                                 ));
    }

    /**
     * @return JsonModel
     */
    public function powerOnVMAction() {
        $serverId = $this->params()->fromRoute('param1');

        $nmServerTable = new Model\NMServerTable($this->_config);
        $nmServer      = $nmServerTable->getById($serverId);

        $nmVmwareTable = new Model\NMVMWareTable($this->_config);
        $nmVmware      = $nmVmwareTable->getByServerId($serverId);

        $this->defineVSphereServer($nmVmware->getVSphereSite());

        try {
            $vServer = new Vhost($this->vSphereServer . ':' . $this->vSpherePort, $this->vSphereUsername, $this->vSpherePassword);
        } catch (\Exception $e) {
            return $this->renderView(array(
                                         "success"   => false,
                                         "error"     => $e->getMessage(),
                                         "trace"     => $e->getTraceAsString(),
                                         "logLevel"  => Logger::ERR,
                                         "logOutput" => $e->getMessage() . " Trace: " . $e->getTraceAsString()
                                     ));
        }

        try {
            /** @var \Vmwarephp\Extensions\VirtualMachine $vm */
            $vm = $vServer->findManagedObjectByName('VirtualMachine', $nmServer->getName(), array('name'));
        } catch (\Exception $e) {
            return $this->renderView(array(
                                         "success"   => false,
                                         "error"     => $e->getMessage(),
                                         "trace"     => $e->getTraceAsString(),
                                         "logLevel"  => Logger::ERR,
                                         "logOutput" => $e->getMessage() . " Trace: " . $e->getTraceAsString()
                                     ));
        }

        try {
            /** @var Task $task */
            $task = $vServer->powerOnVMTask($vm);
        } catch (\Exception $e) {
            return $this->renderView(array(
                                         "success"   => false,
                                         "error"     => $e->getMessage(),
                                         "trace"     => $e->getTraceAsString(),
                                         "logLevel"  => Logger::ERR,
                                         "logOutput" => $e->getMessage() . " Trace: " . $e->getTraceAsString()
                                     ));
        }

        if (!$this->watchTask($vServer, $task)) {
            return $this->renderView($this->_viewData);
        }

        $vServer->disconnect();
        return $this->renderView(array(
                                     "success"    => true,
                                     "logLevel"   => Logger::NOTICE,
                                     "logOutput"  => "VM " . $nmServer->getName() . " in " . $nmVmware->getVSphereSite() . " has been powered on",
                                     "parameters" => "[serverName: {$nmServer->getName()}]"
                                 ));
    }

    /**
     * @return JsonModel
     */
    public function setNetworkBootAction() {
        $serverId = $this->params()->fromRoute('param1');

        $nmServerTable = new Model\NMServerTable($this->_config);
        $nmServer      = $nmServerTable->getById($serverId);

        $nmVmwareTable = new Model\NMVMWareTable($this->_config);
        $nmVmware      = $nmVmwareTable->getByServerId($serverId);

        $this->defineVSphereServer($nmVmware->getVSphereSite());

        try {
            $vServer = new Vhost($this->vSphereServer . ':' . $this->vSpherePort, $this->vSphereUsername, $this->vSpherePassword);
        } catch (\Exception $e) {
            return $this->renderView(array(
                                         "success"   => false,
                                         "error"     => $e->getMessage(),
                                         "trace"     => $e->getTraceAsString(),
                                         "logLevel"  => Logger::ERR,
                                         "logOutput" => $e->getMessage() . " Trace: " . $e->getTraceAsString()
                                     ));
        }
        $service = $vServer->getService();

        try {
            /** @var \Vmwarephp\Extensions\VirtualMachine $vm */
            $vm = $vServer->findManagedObjectByName('VirtualMachine', $nmServer->getName(), array('name', 'config'));
        } catch (\Exception $e) {
            return $this->renderView(array(
                                         "success"   => false,
                                         "error"     => $e->getMessage(),
                                         "trace"     => $e->getTraceAsString(),
                                         "logLevel"  => Logger::ERR,
                                         "logOutput" => $e->getMessage() . " Trace: " . $e->getTraceAsString()
                                     ));
        }

        // get the device keys for the first hard drive and first network device for use in the boot order
        $hd1Key = $net1Key = "";
        $devices = $vm->getConfig()->hardware->device;
        foreach ($devices as $d) {
            if ($d->deviceInfo->label == 'Hard disk 1') {
                $hd1Key = $d->key;
            } else if ($d->deviceInfo->label == 'Network adapter 1') {
                $net1Key = $d->key;
            }
        }

        $vmConfig = new \VirtualMachineConfigSpec();
        $vmConfig->name = $nmServer->getName();

        $vmBootOptions = new \VirtualMachineBootOptions();
        $vmBootOptions->bootOrder = array(
            new \VirtualMachineBootOptionsBootableEthernetDevice($net1Key),
            new \VirtualMachineBootOptionsBootableDiskDevice($hd1Key)
        );
        $vmConfig->bootOptions = $vmBootOptions;

        /*
        $optionValue = new \OptionValue(
            new \SoapVar('bios.bootDeviceClasses', XSD_STRING, 'string', null, 'element', null),
            new \SoapVar('allow:net,hd', XSD_STRING, 'string', null, 'element', null)
        );
        $vmConfig->extraConfig = array(new \SoapVar($optionValue, SOAP_ENC_OBJECT, 'OptionValue'));
        */

        $vmConfigSoap = new \SoapVar($vmConfig, SOAP_ENC_OBJECT, 'VirtualMachineConfigSpec');

        try {
            /** @var \Vmwarephp\Extensions\Task $task */
            $task = $service->makeSoapCall('ReconfigVM_Task',
                                           array("_this"  => $vm->toReference(),
                                                 "spec" => $vmConfigSoap
                                           ));
        } catch (\Exception $e) {
            $xml = $service->getLastRequest();
            $this->xmlFile = "/tmp/reconfig_task.xml";
            file_put_contents($this->xmlFile, $xml);
            return $this->renderView(array(
                                         "success"   => false,
                                         "error"     => "Error calling ReconfigVM_Task for this VM",
                                         "logLevel"  => Logger::ERR,
                                         "logOutput" => $e->getMessage() . " Trace: " . $e->getTraceAsString()
                                     ));
        }

        $xml = $service->getLastRequest();
        file_put_contents("/tmp/reconfig_task.xml", $xml);

        if (!$this->watchTask($vServer, $task)) {
            return $this->renderView($this->_viewData);
        }

        $vServer->disconnect();
        return $this->renderView(array(
                                     "success"    => true,
                                     "logLevel"   => Logger::NOTICE,
                                     "logOutput"  => "VM " . $nmServer->getName() . " in " . $nmVmware->getVSphereSite() . " has been reconfigured for network boot",
                                     "parameters" => "[serverName: {$nmServer->getName()}]"
                                 ));
    }

    /**
     * @param \Vmwarephp\Vhost $vServer
     * @param $networkDevice
     * @param $vlanId
     * @throws \Exception
     * @return \VirtualDeviceConfigSpec
     */
    public function getDVSConfigSpec(\Vmwarephp\Vhost $vServer, $networkDevice, $vlanId) {
        // Find dvportgroup
        try {
            $dvPortGroup = $vServer->findOneManagedObject("DistributedVirtualPortgroup", $vlanId, array('name', 'config'));
        } catch (\Exception $e) {
            throw new \Exception(null, null, $e);
        }

        // Retrieve dvportgroup key
        $dvPortGroupKey = $dvPortGroup->getReferenceId();
        $dvsEntityKey   = $dvPortGroup->config->distributedVirtualSwitch->getReferenceId();

        // Retrieve DVS uuid
        try {
            $dvsEntity = $vServer->findOneManagedObject("VmwareDistributedVirtualSwitch", $dvsEntityKey, array('config'));
        } catch (\Exception $e) {
            throw new \Exception(null, null, $e);
        }
        $dvsUuid = $dvsEntity->config->uuid;

        // New object which represents a connection or association between a
        // DistributedVirtualPortgroup or a DistributedVirtualPort and a Virtual machine virtual NIC
        $dvsPortConnection               = new \DistributedVirtualSwitchPortConnection();
        $dvsPortConnection->portgroupKey = $dvPortGroupKey;
        $dvsPortConnection->switchUuid   = $dvsUuid;

        // New object which defines backing for a virtual Ethernet card that connects to a
        // distributed virtual switch port or portgroup
        $virtualDeviceBackingInfo       = new \VirtualEthernetCardDistributedVirtualPortBackingInfo();
        $virtualDeviceBackingInfo->port = $dvsPortConnection;

        // New object which contains information about connectable virtual devices
        $virtualDeviceConnInfo                    = new \VirtualDeviceConnectInfo();
        $virtualDeviceConnInfo->startConnected    = 1;
        $virtualDeviceConnInfo->allowGuestControl = 0;
        $virtualDeviceConnInfo->connected         = 1;

        // New object which define virtual device
        $networkDevice = new \VirtualVmxnet3();
        $networkDevice->key         = 4;
        $networkDevice->backing     = $virtualDeviceBackingInfo;
        $networkDevice->connectable = $virtualDeviceConnInfo;

        // New object which encapsulates change specifications for an individual virtual device
        $deviceConfigSpec            = new \VirtualDeviceConfigSpec();
        $deviceConfigSpec->operation = "add";
        $deviceConfigSpec->device    = $networkDevice;

        return $deviceConfigSpec;
    }

    /**
     * @param \Vmwarephp\Vhost $vServer
     * @param $networkDevice
     * @param $vlanId
     * @throws \Exception
     * @return \VirtualDeviceConfigSpec
     */
    public function getNetworkConfigSpec(\Vmwarephp\Vhost $vServer, $networkDevice, $vlanId) {
        // get the network from the vlan id, we need both the name and uid
        try {
            /** @var \Vmwarephp\Extensions\Network $network */
            $network = $vServer->findOneManagedObject('Network', $vlanId, array('name'));
        } catch (Soap $e) {
            throw new \Exception(null, null, $e);
        }

        $nicBacking                 = new \VirtualEthernetCardNetworkBackingInfo();
        $nicBacking->deviceName     = $network->getName();
        $networkDevice->key         = 4;
        $networkDevice->backing     = $nicBacking;
        $networkDevice->addressType = "generated";

        // Add a network device
        $deviceConfigSpec            = new \VirtualDeviceConfigSpec();
        $deviceConfigSpec->operation = "add";
        $deviceConfigSpec->device    = $networkDevice;

        return $deviceConfigSpec;
    }

    /**
     * @return JsonModel
     */
    public function createVMAction() {
        $this->writeLog(array("logLevel" => Logger::DEBUG, "logOutput" => "createVMAction()"));

        ini_set('default_socket_timeout', 120);

        // server id passed as param
        $serverId = $this->params()->fromRoute('param1');

        // get the server from our local DB
        $nmServerTable = new Model\NMServerTable($this->_config);
        $nmServer      = $nmServerTable->getById($serverId);

        // get the vm info from the vm table
        $nmVmwareTable = new Model\NMVMWareTable($this->_config);
        $nmVmware      = $nmVmwareTable->getByServerId($serverId);

        $templateId = $nmVmware->getTemplateId();
       	$templateName = $nmVmware->getTemplateName();
       
        if($templateId == null OR $templateId == ""){
            $templateName = $this->_config['vmDefaults']['template'];
			$dcName = $nmVmware->getDcName();
        	$templateList = $this->getTemplateList($dcName);
        	
			foreach($templateList AS $template){
				
				if($template['name'] == $templateName){
					
					$templateId = $template['id'];
					
					$nmVmware->setTemplateId($templateId);
					$nmVmware->setTemplateName($templateName);
					
					$tnexp = explode('-', $templateName); 
					$distro = $tnexp[0]."-".$tnexp[1];
					$nmServer->setCobblerDistro($distro);
				}
			}

		}
   
        //-----------------
        /* stuff that needs to go in config somewhere */
        $dnsServerList = $this->_config['dns']['nameservers'];
        $dnsSuffixList = '"'.$this->_config['dns']['search'].'"';
  
            
        //-----------------    
            
        // use the vSphereSite value in vmware table row to connect to the appropriate vSphere server
        $this->defineVSphereServer($nmVmware->getVSphereSite());
        $vServer = new Vhost($this->vSphereServer . ':' . $this->vSpherePort, $this->vSphereUsername, $this->vSpherePassword);
        $service = $vServer->getService();
        
        // define the base parameters retrieved from the DB
        $vmName   = $nmServer->getName();
        $vmHostName = current(explode(".", $vmName));
        $ipAddress = $nmServer->getIpAddress();
        $gateway = $nmServer->getGateway();
        $subnetMask = $nmServer->getSubnetMask();
        
        $dataCenterId = $nmVmware->getDcUid();
        $poolUid      = $nmVmware->getRpUid();
        $numCPUs  = $nmVmware->getNumCPUs();
        $memoryMB = $nmVmware->getMemoryGB() * 1024;
        $vlanId   = $nmVmware->getVlanId();
        
        $vm = $vServer->findOneManagedObject('VirtualMachine', $templateId, array('name', 'config', 'guest'));
        
        $templateName = $vm->getName();
        $dc = $vServer->findOneManagedObject('Datacenter', $dataCenterId, array('name', 'vmFolder'));
        
        $vmFolder = $dc->getVmFolder();
        
        $rp = $vServer->findOneManagedObject('ResourcePool', $poolUid, array());
     
        $annotation = "A NeuMatic VM\n" .
            "Created By: " . $this->_user->getLastName() . ", " . $this->_user->getFirstName() . "\n" .
            "Created On: " . date('Y-m-d H:i:s') . "\n" .
            "Business Service: " . $nmServer->getBusinessServiceName() . "\n" .
            "Subsystem: " . $nmServer->getSubsystemName() . "\n" .
            "Environment: " . $nmServer->getCmdbEnvironment();
        
              
        /****** spec object *******/
        $cloneSpec = new \VirtualMachineCloneSpec();
        $cloneSpec->powerOn = false;
        $cloneSpec->template = false;
        
        /******* config object *******/   
        $vmConfig = new \VirtualMachineConfigSpec();  
        $vmConfig->name         = $vmName;
        $vmConfig->annotation   = $annotation;
        $vmConfig->numCPUs      = $numCPUs;
        $vmConfig->memoryMB     = $memoryMB;
        $cloneSpec->config = $vmConfig;
        
        
        /******* customization object ********/
        $cloneSpec->customization = new \CustomizationSpec();
        $cloneSpec->customization->options = new \CustomizationLinuxOptions;
        $cloneSpec->customization->globalIPSettings = new \CustomizationGlobalIPSettings;
        $cloneSpec->customization->globalIPSettings->dnsServerList = $dnsServerList;
        $cloneSpec->customization->globalIPSettings->dnsSuffixList = $dnsSuffixList;
        
        /****** adapter object ******/  
        $adapter = new \CustomizationIPSettings;
        $adapter->gateway = $gateway; 
        $adapter->ip = new \CustomizationFixedIp;
        $adapter->ip->ipAddress = $ipAddress;
        $adapter->subnetMask = $subnetMask;
        $cloneSpec->customization->nicSettingMap->adapter = $adapter;
       
        /****** identity object ******/ 
        $identity = new \CustomizationLinuxPrep;
        
        //$identity->domain = $vmDomainName;
        $hostname = new \CustomizationFixedName;
        $hostname->name = $vmHostName;
        $identity->hostName = $hostname;
        $identity->hwClockUTC = true;
        $identity->timeZone = "America/New_York";
        $cloneSpec->customization->identity = $identity;


        /***** Get storage recommendation *****/
        $storagePod    = $this->getStoragePodByClusterComputeResource($nmVmware->getCcrUid());
        
		/***** If there is no storagepod then it only has local storage and we need the alternate recommendation method *****/ 
        if(is_array($storagePod) AND !empty($storagePod)){
        
	        $storagePodUid = $storagePod['uid'];
	        $vmConfigSoap = new \SoapVar($vmConfig, SOAP_ENC_OBJECT, 'VirtualMachineConfigSpec');
	
	        $storageResourceManager = $service->getServiceContent()->storageResourceManager;
	
	        $storageDrsPodSelectionSpec             = new \StorageDrsPodSelectionSpec();
	        $storageDrsPodSelectionSpec->storagePod = $storagePodUid;
	        $storageDrsPodSelectionSpecSoap         = new \SoapVar($storageDrsPodSelectionSpec, SOAP_ENC_OBJECT, 'StorageDrsPodSelectionSpec');
	
	        $storagePlacementSpec                   = new \StoragePlacementSpec();
	        $storagePlacementSpec->configSpec       = $vmConfigSoap;
	        $storagePlacementSpec->type             = 'create';
	        $storagePlacementSpec->resourcePool     = $poolUid;
	        $storagePlacementSpec->folder           = $vmFolder->toReference();
            // TODO: undefined variable $hostSystem
	        $storagePlacementSpec->host             = $hostSystem['uid'];
	        $storagePlacementSpec->podSelectionSpec = $storageDrsPodSelectionSpecSoap;
	        $storagePlacementSpecSoap               = new \SoapVar($storagePlacementSpec, SOAP_ENC_OBJECT, 'StoragePlacementSpec');


	        $this->writeLog(array("logLevel" => Logger::DEBUG, "logOutput" => "createVMAction() - recommend datastores"));
	        
	        try {
	            $storagePlacementResult = $service->makeSoapCall('RecommendDatastores',
	                                                             array("_this"       => $storageResourceManager->reference,
	                                                                   "storageSpec" => $storagePlacementSpecSoap));
	        } catch (\Exception $e) {
	            $xml = $service->getLastRequest();
	            file_put_contents($this->xmlFile, $xml);
	            return $this->renderView(array(
	                                         "success"   => false,
	                                         "error"     => "Error getting storage placement for this VM",
	                                         "logLevel"  => Logger::ERR,
	                                         "logOutput" => $e->getMessage() . " Trace: " . $e->getTraceAsString()
	                                     ));
	        }
	        file_put_contents($this->xmlFile, $service->getLastRequest());
        	// get the recommendations, select the first of them and obtain the datastore
        	$recommendations = $storagePlacementResult->recommendations;            
 			/** @var \ClusterRecommendation $recommend */
	        $recommend = $recommendations[0];
	        /** @var \StoragePlacementAction $action */
	        $action = $recommend->action[0];
	        /** @var \VirtualMachineRelocateSpec $relocationSpec */
	        $relocationSpec = $action->relocateSpec;
	        /** @var \Vmwarephp\Extensions\Datastore $recommendedDatastore */
	        $recommendedDatastore = $relocationSpec->datastore;
	
	        $this->writeLog(array("logLevel" => Logger::DEBUG, "logOutput" => "createVMAction() - get datastore"));
	        // now lookup the datastore so we can get the name
	        try {
	            /** @var \Vmwarephp\Extensions\Datastore $datastore */
	            $datastore = $vServer->findOneManagedObject('Datastore', $recommendedDatastore->getReferenceId(), array('name'));
	        } catch (\Exception $e) {
	            $xml = $service->getLastRequest();
	            file_put_contents($this->xmlFile, $xml);
	            return $this->renderView(array(
	                                         "success"   => false,
	                                         "error"     => "Error getting datastore for this VM",
	                                         "logLevel"  => Logger::ERR,
	                                         "logOutput" => $e->getMessage() . " Trace: " . $e->getTraceAsString()
	                                     ));
	        }
		}else{
			// the regular method no worky on local storage so we do this crap...
			$ccr = $vServer->findOneManagedObject('ClusterComputeResource', $nmVmware->getCcrUid(), array('name', 'resourcePool', 'configurationEx', 'host'));

			$rp = $ccr->resourcePool;
			$hosts = $ccr->host;
			
			$storageObjects = array();
			foreach($hosts AS $host){
				$hostref = $host->getReferenceId();
				$hostobj = $vServer->findOneManagedObject('HostSystem', $hostref, array('name', 'datastore'));

				foreach($hostobj->datastore AS $ds){
					$o = array();
					$ds_ref	= $ds->getReferenceId();
					$ds_obj = $vServer->findOneManagedObject('Datastore', $ds_ref, array('name', 'info', 'summary', 'capability'));
					
					$ds_obj_summary = $ds_obj->summary;
					if(stristr($ds_obj->name, "esx")){
							
						$o['name'] = $ds_obj->name;
						$o['reference'] = $ds_ref;
						$o['freespace'] = $ds_obj_summary->freeSpace;
					
						$storageObjects[] = $o;
					}
				}
			
			}
	
			$currentDS = array();
			foreach($storageObjects AS $SO){
				if(empty($currentDS)){
					$currentDS = $SO;
				}else{
					if($SO['freespace'] > $currentDS['freespace']){
						$currentDS = $SO;
					}
				}
			}
			
			$datastore = $vServer->findOneManagedObject('Datastore', $currentDS['reference'], array('name'));
		}

        /***** location object ******/  
        $location = new \VirtualMachineRelocateSpec();
        $location->datastore = $datastore->toReference();
        $cloneSpec->location = $location;
        $cloneSpec->location->pool = $rp->toReference();
    
        if (preg_match("/network/", $vlanId)) {
            // Network
            try {
                // TODO: undefined variable $nic
                $nicConfigSpec = $this->getNetworkConfigSpec($vServer, $nic, $vlanId);
            } catch (\Exception $e) {
                #$prev = $e->getPrevious();
                return $this->renderView(array(
                                             "success"   => false,
                                             "error"     => "Error getting network config spec for this VM",
                                             "logLevel"  => Logger::ERR,
                                             "logOutput" => $e->getMessage() . " Trace: " . $e->getTraceAsString()
                                         ));
            }
        } else if (preg_match("/dvportgroup/", $vlanId)) {
            // DistributedVirtualPortgroup
            try {
                // TODO: undefined variable $nic
                $nicConfigSpec = $this->getDVSConfigSpec($vServer, $nic, $vlanId);
            } catch (\Exception $e) {
                #$prev = $e->getPrevious();
                return $this->renderView(array(
                                             "success"   => false,
                                             "error"     => "Error getting DVS config spec for this VM",
                                             "logLevel"  => Logger::ERR,
                                             "logOutput" => $e->getMessage() . " Trace: " . $e->getTraceAsString()
                                         ));
            }
        } else {
            return $this->renderView(array(
                                         "success"   => false,
                                         "error"     => "Unrecogized VLAN ID: " . $vlanId,
                                         "logLevel"  => Logger::ERR,
                                         "logOutput" => "Unrecogized VLAN ID: " . $vlanId
                                         ));
            }
            
            $templateNics = $this->getNicsByVMName($templateName);
            $templateNic = $templateNics[0];
         
        $backing = $nicConfigSpec->device->backing;
        
        $nicConfigSpec->device = $templateNic;
        $nicConfigSpec->device->backing = $backing;
        $nicConfigSpec->operation = "edit";
        $nicConfigSpec->device->connectable->startConnected = 1;
        unset($nicConfigSpec->device->macAddress);
        
        
        $cloneSpec->config->deviceChange = array(
            $nicConfigSpec
        );
    

       
        $this->writeLog(array("logLevel" => Logger::DEBUG, "logOutput" => "createVMAction() - create VM Task"));
     
        /****** make the call ******/
        try {
            /** @var \Vmwarephp\Extensions\Task $task */
         
            $task = $service->makeSoapCall('CloneVM_Task',
                                           array("_this"  => $vm->toReference(),
                                                 "folder" => $vmFolder->toReference(),
                                                 "name"   => $vmName,
                                                 "spec"   => $cloneSpec
                                           ));
        } catch (\Exception $e) {
            $xml = $service->getLastRequest();
            file_put_contents("/tmp/create_vm.xml", $xml);
            return $this->renderView(array(
                                         "success"   => false,
                                         "error"     => "Error calling CreateVM_Task for this VM",
                                         "logLevel"  => Logger::ERR,
                                         "logOutput" => $e->getMessage() . " Trace: " . $e->getTraceAsString()
                                     ));
        }

        // expecting a task to be returned with info. fail if not
        if (!$info = $task->getInfo()) {
            return $this->renderView($this->_viewData);
        }


        $this->writeLog(array("logLevel" => Logger::DEBUG, "logOutput" => "createVMAction() - check task for error"));
        
        // check the info container to see if there is an error
        if (property_exists($info, 'error') && is_object($info->error) && property_exists($info->error, 'localizedMessage')) {
            $vServer->disconnect();
            $msg = "Error: VMWare returned an error on VM create";
            if (property_exists($info, 'error') && is_object($info->error) && property_exists($info->error, 'localizedMessage')) {
                $msg = "Error: " . $info->error->localizedMessage;
            }
            return $this->renderView(array(
                                         "success"   => false,
                                         "error"     => $msg,
                                         "message"   => $msg,
                                         "logLevel"  => Logger::ERR,
                                         "logOutput" => $msg
                                     ));
        }

        $this->writeLog(array("logLevel" => Logger::DEBUG, "logOutput" => "createVMAction() - watch task"));
        // now watch the task until it's complete creating the VM
        if (!$task = $this->watchTask($vServer, $task)) {
            return $this->renderView($this->_viewData);
        }

        // vm created. obtain the mac address and return it
        /** @var \Vmwarephp\Extensions\VirtualMachine $newVM */
        $newVM = $task->getInfo()->result;

        $this->writeLog(array("logLevel" => Logger::DEBUG, "logOutput" => "createVMAction() - get new VM"));
        try {
            /** @var \Vmwarephp\Extensions\VirtualMachine $vm */
            $vm = $vServer->findOneManagedObject('VirtualMachine', $newVM->getReferenceId(), array('name', 'config'));
        } catch (\Exception $e) {
            return $this->renderView(array(
                                         "success"   => false,
                                         "error"     => "Error getting newly created VM",
                                         "logLevel"  => Logger::ERR,
                                         "logOutput" => $e->getMessage() . " Trace: " . $e->getTraceAsString()
                                     ));
        }

        $this->writeLog(array("logLevel" => Logger::DEBUG, "logOutput" => "createVMAction() - get MAC address"));
        if (!$macAddress = $this->getVMMacAddress($vm)) {
            return $this->renderView(array(
                                         "success"   => false,
                                         "error"     => "Could not obtain MAC address",
                                         "logLevel"  => Logger::ERR,
                                         "logOutput" => "Could not obtain MAC address for " . $nmServer->getName()
                                     ));
        }
        

        // update the mac address in the server table
        if ($nmServer->getId()) {
            if ($macAddress) {
                $nmServer->setMacAddress($macAddress);
            }
            // update the instance uid and host system name in the vmware table
            if (property_exists($vm->getConfig(), 'instanceUuid')) {
                $nmVmware->setInstanceUuid($vm->getConfig()->instanceUuid);
            }
            if (isset($hostSystem['name'])) {
                $nmVmware->setHsName($hostSystem['name']);
                $nmVmwareTable->update($nmVmware);
            }
            $nmVmwareTable->update($nmVmware);
            $nmServerTable->update($nmServer);
        }
        
        
         // get the storage (luns) info from the storage table
        $nmStorageTable = new Model\NMStorageTable($this->_config);
        $nmLuns         = $nmStorageTable->getByServerId($nmServer->getId());
        //remove the first Lun...that one is already created.
        $primaryLun = array_shift($nmLuns);
        foreach($nmLuns AS $lun){
               
            $size = $lun->getLunSizeGb();
            $this->addDiskToVM($vmName, $size);
        }
        //power this sucker up
        try {
            /** @var Task $powerontask */
            $powerontask = $vServer->powerOnVMTask($vm);
        } catch (\Exception $e) {
            return $this->renderView(array(
                                         "success"   => false,
                                         "error"     => $e->getMessage(),
                                         "trace"     => $e->getTraceAsString(),
                                         "logLevel"  => Logger::ERR,
                                         "logOutput" => $e->getMessage() . " Trace: " . $e->getTraceAsString()
                                     ));
        }

        if (!$this->watchTask($vServer, $powerontask)) {
            return $this->renderView($this->_viewData);
        }
		
        $vServer->disconnect();
		
        return $this->renderView(array(
                                     "success"    => true,
                                     "vmId"       => $vm->getReferenceId(),
                                     "macAddress" => $macAddress,
                                     "logLevel"   => Logger::NOTICE,
                                     "logOutput"  => "VMware VM " . $nmServer->getName() . " created on " . $nmVmware->getVSphereSite() . " from template $templateName",
                                     "parameters" => "[serverName: {$nmServer->getName()}]"
                                 ));

    }




    /**
     * @return JsonModel
     */
    public function createCobblerVMAction() {
        $this->writeLog(array("logLevel" => Logger::DEBUG, "logOutput" => "createVMAction()"));

        ini_set('default_socket_timeout', 120);

        // server id passed as param
        $serverId = $this->params()->fromRoute('param1');

        // get the server from our local DB
        $nmServerTable = new Model\NMServerTable($this->_config);
        $nmServer      = $nmServerTable->getById($serverId);

        // get the vm info from the vm table
        $nmVmwareTable = new Model\NMVMWareTable($this->_config);
        $nmVmware      = $nmVmwareTable->getByServerId($serverId);

        // get the storage (luns) info from the storage table
        $nmStorageTable = new Model\NMStorageTable($this->_config);
        $nmLuns         = $nmStorageTable->getByServerId($nmServer->getId());

        // use the vSphereSite value in vmware table row to connect to the appropriate vSphere server
        $this->defineVSphereServer($nmVmware->getVSphereSite());
        $vServer = new Vhost($this->vSphereServer . ':' . $this->vSpherePort, $this->vSphereUsername, $this->vSpherePassword);
        $service = $vServer->getService();
        $this->writeLog(array("logLevel" => Logger::DEBUG, "logOutput" => "createVMAction() - vSphereSite=" . $nmVmware->getVSphereSite()));

        // define the base parameters retrieved from the DB
        $vmName   = $nmServer->getName();
        $numCPUs  = $nmVmware->getNumCPUs();
        $memoryMB = $nmVmware->getMemoryGB() * 1024;
        $vlanId   = $nmVmware->getVlanId();
        $this->writeLog(array("logLevel" => Logger::DEBUG, "logOutput" => "createVMAction() - vmName=" . $vmName));

        $dataCenterId = $nmVmware->getDcUid();
        $poolUid      = $nmVmware->getRpUid();
        $guestId      = "centos64Guest";

        $storagePod    = $this->getStoragePodByClusterComputeResource($nmVmware->getCcrUid());
        $storagePodUid = $storagePod['uid'];

        $this->writeLog(array("logLevel" => Logger::DEBUG, "logOutput" => "createVMAction() - find datacenter"));
        // get the vmFolder in the dataCenter
        try {
            /** @var \Vmwarephp\Extensions\Datastore $dc */
            $dc = $vServer->findOneManagedObject('Datacenter', $dataCenterId, array('name', 'vmFolder'));
        } catch (\Exception $e) {
            $xml = $service->getLastRequest();
            file_put_contents($this->xmlFile, $xml);
            return $this->renderView(array(
                                         "success"   => false,
                                         "error"     => "Error getting datacenter for this VM",
                                         "logLevel"  => Logger::ERR,
                                         "logOutput" => $e->getMessage() . " Trace: " . $e->getTraceAsString()
                                     ));
        }
        /** @var \Vmwarephp\Extensions\Folder $vmFolder */
        $vmFolder = $dc->getVmFolder();

        $this->writeLog(array("logLevel" => Logger::DEBUG, "logOutput" => "createVMAction() - get host system"));

        // get the host systems for this compute resource
        $hostSystems = $this->getVMwareHostSystemsByClusterComputeResource($nmVmware->getCcrUid());

        // sort by available memory then use the first element off the array
        // this will insure that the host system is powered on, connected and has the most memory available

        // Moved the sort into the getHostSystemsByClusterComputeResource. no need for an extra method here
        // $sortedHostSystems = $this->sortHostSystemsByMemAvailable($hostSystems);
        $hostSystem = $hostSystems[0];

        if ($hostSystem['powerState'] != 'poweredOn' || $hostSystem['connectionState'] != 'connected') {
            return $this->renderView(array(
                "success"   => false,
                "error"     => "Could not find an available host system for Cluster Compute Resource " . $nmVmware->getCcrName(),
                "logLevel"  => Logger::ERR,
                "logOutput" => "Could not find an available host system for Cluster Compute Resource " . $nmVmware->getCcrName()
            ));
        }

        // check the OS and choose the NIC appropriately
        if (preg_match("/6\.\d/", $nmServer->getCobblerDistro())) {
            $nic = new \VirtualVmxnet3();
        } else {
            $nic = new \VirtualE1000();
        }

        // get the network config spec, we need both the name and uid
        // check for the type. pre 5.5 it'll be "Network" otherwise "DistributedVirtualPortgroup"
        $this->writeLog(array("logLevel" => Logger::DEBUG, "logOutput" => "createVMAction() - get network"));
        if (preg_match("/network/", $vlanId)) {
            // Network
            try {
                $nicConfigSpec = $this->getNetworkConfigSpec($vServer, $nic, $vlanId);
            } catch (\Exception $e) {
                #$prev = $e->getPrevious();
                return $this->renderView(array(
                                             "success"   => false,
                                             "error"     => "Error getting network config spec for this VM",
                                             "logLevel"  => Logger::ERR,
                                             "logOutput" => $e->getMessage() . " Trace: " . $e->getTraceAsString()
                                         ));
            }
        } else if (preg_match("/dvportgroup/", $vlanId)) {
            // DistributedVirtualPortgroup
            try {
                $nicConfigSpec = $this->getDVSConfigSpec($vServer, $nic, $vlanId);
            } catch (\Exception $e) {
                #$prev = $e->getPrevious();
                return $this->renderView(array(
                                             "success"   => false,
                                             "error"     => "Error getting DVS config spec for this VM",
                                             "logLevel"  => Logger::ERR,
                                             "logOutput" => $e->getMessage() . " Trace: " . $e->getTraceAsString()
                                         ));
            }
        } else {
            return $this->renderView(array(
                                         "success"   => false,
                                         "error"     => "Unrecogized VLAN ID: " . $vlanId,
                                         "logLevel"  => Logger::ERR,
                                         "logOutput" => "Unrecogized VLAN ID: " . $vlanId
                                     ));
        }
        // Add a scsi device
        $scsiCtrlSpec            = new \VirtualDeviceConfigSpec();
        $scsiCtrlSpec->operation = "add";
        $scsiCtrl                = new \VirtualLsiLogicController();
        $scsiCtrl->busNumber     = 0;
        $scsiCtrlSpec->device    = $scsiCtrl;
        $scsiCtrl->key           = 1;
        $scsiCtrl->sharedBus     = "noSharing";

        // Add disk storage
        $diskSpecs = array();
        $lunNumber = 0;
        /** @var $lun Model\NMStorage */
        foreach ($nmLuns as $lun) {
            #$vmfi->vmPathName = "[" . $lun->getLunName() . "]";

            $diskfileBacking = new \VirtualDiskFlatVer2BackingInfo();
            #$diskfileBacking->fileName = $vmfi->vmPathName;
            $diskfileBacking->diskMode        = "persistent";
            $diskfileBacking->thinProvisioned = 1;

            $disk                = new \VirtualDisk();
            $disk->capacityInKB  = $lun->getLunSizeGb() * 1024 * 1024;
            $disk->key           = $lunNumber;
            $disk->controllerKey = 1;
            $disk->unitNumber    = $lunNumber;
            $disk->backing       = $diskfileBacking;

            $diskSpec                = new \VirtualDeviceConfigSpec();
            $diskSpec->fileOperation = "create";
            $diskSpec->operation     = "add";
            $diskSpec->device        = $disk;

            $diskSpecs[] = $diskSpec;
            $lunNumber++;
        }

        $annotation = "A NeuMatic VM\n" .
            "Created By: " . $this->_user->getLastName() . ", " . $this->_user->getFirstName() . "\n" .
            "Created On: " . date('Y-m-d H:i:s') . "\n" .
            "Business Service: " . $nmServer->getBusinessServiceName() . "\n" .
            "Subsystem: " . $nmServer->getSubsystemName() . "\n" .
            "Environment: " . $nmServer->getCmdbEnvironment() . "\n" .
            "Cobbler Server: " . $nmServer->getCobblerServer() . "\n";
        $vmConfig               = new \VirtualMachineConfigSpec();
        $vmConfig->name         = $vmName;
        $vmConfig->annotation   = $annotation;
        $vmConfig->numCPUs      = $numCPUs;
        $vmConfig->memoryMB     = $memoryMB;
        $vmConfig->guestId      = $guestId;
        $vmConfig->deviceChange = array(
            new \SoapVar($scsiCtrlSpec, SOAP_ENC_OBJECT, 'VirtualDeviceConfigSpec'),
            new \SoapVar($nicConfigSpec, SOAP_ENC_OBJECT, 'VirtualDeviceConfigSpec')
        );
        foreach ($diskSpecs as $diskSpec) {
            $vmConfig->deviceChange[] = new \SoapVar($diskSpec, SOAP_ENC_OBJECT, 'VirtualDeviceConfigSpec');
        }

        $vmConfigSoap = new \SoapVar($vmConfig, SOAP_ENC_OBJECT, 'VirtualMachineConfigSpec');

        $storageResourceManager = $service->getServiceContent()->storageResourceManager;

        $storageDrsPodSelectionSpec             = new \StorageDrsPodSelectionSpec();
        $storageDrsPodSelectionSpec->storagePod = $storagePodUid;
        $storageDrsPodSelectionSpecSoap         = new \SoapVar($storageDrsPodSelectionSpec, SOAP_ENC_OBJECT, 'StorageDrsPodSelectionSpec');

        $storagePlacementSpec                   = new \StoragePlacementSpec();
        $storagePlacementSpec->configSpec       = $vmConfigSoap;
        $storagePlacementSpec->type             = 'create';
        $storagePlacementSpec->resourcePool     = $poolUid;
        $storagePlacementSpec->folder           = $vmFolder->toReference();
        $storagePlacementSpec->host             = $hostSystem['uid'];
        $storagePlacementSpec->podSelectionSpec = $storageDrsPodSelectionSpecSoap;
        $storagePlacementSpecSoap               = new \SoapVar($storagePlacementSpec, SOAP_ENC_OBJECT, 'StoragePlacementSpec');


        $this->writeLog(array("logLevel" => Logger::DEBUG, "logOutput" => "createVMAction() - recommend datastores"));
        try {
            $storagePlacementResult = $service->makeSoapCall('RecommendDatastores',
                                                             array("_this"       => $storageResourceManager->reference,
                                                                   "storageSpec" => $storagePlacementSpecSoap));
        } catch (\Exception $e) {
            $xml = $service->getLastRequest();
            file_put_contents($this->xmlFile, $xml);
            return $this->renderView(array(
                                         "success"   => false,
                                         "error"     => "Error getting storage placement for this VM",
                                         "logLevel"  => Logger::ERR,
                                         "logOutput" => $e->getMessage() . " Trace: " . $e->getTraceAsString()
                                     ));
        }
        file_put_contents($this->xmlFile, $service->getLastRequest());

        // TODO: need to get this working
        /*
        if (property_exists($storagePlacementResult, 'drsFault') && property_exists($storagePlacementResult->drsFault, 'reason')) {
            return $this->renderView(array("success" => false, "message" => $storagePlacementResult->drsFault->reason));
        }
        */

        // get the recommendations, select the first of them and obtain the datastore
        $recommendations = $storagePlacementResult->recommendations;
        /** @var \ClusterRecommendation $recommend */
        $recommend = $recommendations[0];
        /** @var \StoragePlacementAction $action */
        $action = $recommend->action[0];
        /** @var \VirtualMachineRelocateSpec $relocationSpec */
        $relocationSpec = $action->relocateSpec;
        /** @var \Vmwarephp\Extensions\Datastore $recommendedDatastore */
        $recommendedDatastore = $relocationSpec->datastore;

        $this->writeLog(array("logLevel" => Logger::DEBUG, "logOutput" => "createVMAction() - get datastore"));
        // now lookup the datastore so we can get the name
        try {
            /** @var \Vmwarephp\Extensions\Datastore $datastore */
            $datastore = $vServer->findOneManagedObject('Datastore', $recommendedDatastore->getReferenceId(), array('name'));
        } catch (\Exception $e) {
            $xml = $service->getLastRequest();
            file_put_contents($this->xmlFile, $xml);
            return $this->renderView(array(
                                         "success"   => false,
                                         "error"     => "Error getting datastore for this VM",
                                         "logLevel"  => Logger::ERR,
                                         "logOutput" => $e->getMessage() . " Trace: " . $e->getTraceAsString()
                                     ));
        }

        // add this datastore to all the disk specs
        $diskSpecs = array();
        $vmfi      = new \VirtualMachineFileInfo();
        $lunNumber = 0;
        foreach ($nmLuns as $lun) {
            $vmfi->vmPathName = "[" . $datastore->getName() . "]";

            $diskfileBacking                  = new \VirtualDiskFlatVer2BackingInfo();
            $diskfileBacking->fileName        = $vmfi->vmPathName;
            $diskfileBacking->diskMode        = "persistent";
            $diskfileBacking->thinProvisioned = 1;

            $disk                = new \VirtualDisk();
            $disk->capacityInKB  = $lun->getLunSizeGb() * 1024 * 1024;
            $disk->key           = $lunNumber;
            $disk->controllerKey = 1;
            $disk->unitNumber    = $lunNumber;
            $disk->backing       = $diskfileBacking;

            $diskSpec                = new \VirtualDeviceConfigSpec();
            $diskSpec->fileOperation = "create";
            $diskSpec->operation     = "add";
            $diskSpec->device        = $disk;

            $diskSpecs[] = $diskSpec;
            $lunNumber++;
        }

        // update the vmconfig instance so we can call creatVM_task
        $vmConfig->files        = new \SoapVar($vmfi, SOAP_ENC_OBJECT, 'VirtualMachineFileInfo');
        $vmConfig->deviceChange = array(
            new \SoapVar($scsiCtrlSpec, SOAP_ENC_OBJECT, 'VirtualDeviceConfigSpec'),
            new \SoapVar($nicConfigSpec, SOAP_ENC_OBJECT, 'VirtualDeviceConfigSpec')
        );
        foreach ($diskSpecs as $diskSpec) {
            $vmConfig->deviceChange[] = new \SoapVar($diskSpec, SOAP_ENC_OBJECT, 'VirtualDeviceConfigSpec');
        }
        $vmConfigSoap = new \SoapVar($vmConfig, SOAP_ENC_OBJECT, 'VirtualMachineConfigSpec');

        /*
         * Couldn't get this ApplyStorageDrsRecommendation_Task to work
         * Using CreateVM_Task below instead
         *
        $firstRecommendationKey = $recommendations[0]->key;
        $keySoap = new \SoapVar($firstRecommendationKey, XSD_STRING, 'string');
        $vServer = new Vhost($this->vSphereServer . ':' . $this->vSpherePort, $this->vSphereUsername, $this->vSpherePassword);
        $service = $vServer->getService();
        try
        {
            $task = $service->makeSoapCall('ApplyStorageDrsRecommendation_Task',
                array("_this" => $storageResourceManager->reference,
                      "key" => $keySoap));
        } catch (\Exception $e) {
            $xml = $service->getLastRequest();
            file_put_contents($this->xmlFile, $xml);
                    return $this->renderView(array(
                                                 "success"   => false,
                                                 "error"     => $e->getMessage(),
                                                 "trace"     => $e->getTraceAsString(),
                                                 "logLevel"  => Logger::ERR,
                                                 "logOutput" => $e->getMessage() . " Trace: " . $e->getTraceAsString()
                                             ));
        }
        $xml = $service->getLastRequest();
        file_put_contents($this->xmlFile, $xml);
        */

        $this->writeLog(array("logLevel" => Logger::DEBUG, "logOutput" => "createVMAction() - create VM Task"));
        // create the VM
        try {
            /** @var \Vmwarephp\Extensions\Task $task */
            $task = $service->makeSoapCall('CreateVM_Task',
                                           array("_this"  => $vmFolder->toReference(),
                                                 "config" => $vmConfigSoap,
                                                 "pool"   => $poolUid,
                                                 "host"   => $hostSystem['uid']
                                           ));
        } catch (\Exception $e) {
            $xml = $service->getLastRequest();
            file_put_contents("/tmp/create_vm.xml", $xml);
            return $this->renderView(array(
                                         "success"   => false,
                                         "error"     => "Error calling CreateVM_Task for this VM",
                                         "logLevel"  => Logger::ERR,
                                         "logOutput" => $e->getMessage() . " Trace: " . $e->getTraceAsString()
                                     ));
        }

        // expecting a task to be returned with info. fail if not
        if (!$info = $task->getInfo()) {
            return $this->renderView($this->_viewData);
        }

        $this->writeLog(array("logLevel" => Logger::DEBUG, "logOutput" => "createVMAction() - check task for error"));
        // check the info container to see if there is an error
        if (property_exists($info, 'error') && is_object($info->error) && property_exists($info->error, 'localizedMessage')) {
            $vServer->disconnect();
            $msg = "Error: VMWare returned an error on VM create";
            if (property_exists($info, 'error') && is_object($info->error) && property_exists($info->error, 'localizedMessage')) {
                $msg = "Error: " . $info->error->localizedMessage;
            }
            return $this->renderView(array(
                                         "success"   => false,
                                         "error"     => $msg,
                                         "message"   => $msg,
                                         "logLevel"  => Logger::ERR,
                                         "logOutput" => $msg
                                     ));
        }

        $this->writeLog(array("logLevel" => Logger::DEBUG, "logOutput" => "createVMAction() - watch task"));
        // now watch the task until it's complete creating the VM
        if (!$task = $this->watchTask($vServer, $task)) {
            return $this->renderView($this->_viewData);
        }

        // vm created. obtain the mac address and return it
        /** @var \Vmwarephp\Extensions\VirtualMachine $newVM */
        $newVM = $task->getInfo()->result;

        $this->writeLog(array("logLevel" => Logger::DEBUG, "logOutput" => "createVMAction() - get new VM"));
        try {
            /** @var \Vmwarephp\Extensions\VirtualMachine $vm */
            $vm = $vServer->findOneManagedObject('VirtualMachine', $newVM->getReferenceId(), array('name', 'config'));
        } catch (\Exception $e) {
            return $this->renderView(array(
                                         "success"   => false,
                                         "error"     => "Error getting newly created VM",
                                         "logLevel"  => Logger::ERR,
                                         "logOutput" => $e->getMessage() . " Trace: " . $e->getTraceAsString()
                                     ));
        }

        $this->writeLog(array("logLevel" => Logger::DEBUG, "logOutput" => "createVMAction() - get MAC address"));
        if (!$macAddress = $this->getVMMacAddress($vm)) {
            return $this->renderView(array(
                                         "success"   => false,
                                         "error"     => "Could not obtain MAC address",
                                         "logLevel"  => Logger::ERR,
                                         "logOutput" => "Could not obtain MAC address for " . $nmServer->getName()
                                     ));
        }
        $vServer->disconnect();

        // update the mac address in the server table
        if ($nmServer->getId()) {
            if ($macAddress) {
                $nmServer->setMacAddress($macAddress);
            }
            // update the instance uid and host system name in the vmware table
            if (property_exists($vm->getConfig(), 'instanceUuid')) {
                $nmVmware->setInstanceUuid($vm->getConfig()->instanceUuid);
            }
            if ($hostSystem['name']) {
                $nmVmware->setHsName($hostSystem['name']);
                $nmVmwareTable->update($nmVmware);
            }
            $nmVmwareTable->update($nmVmware);
            $nmServerTable->update($nmServer);
        }
        return $this->renderView(array(
                                     "success"    => true,
                                     "vmId"       => $vm->getReferenceId(),
                                     "macAddress" => $macAddress,
                                     "logLevel"   => Logger::NOTICE,
                                     "logOutput"  => "VMware VM " . $nmServer->getName() . " created on " . $nmVmware->getVSphereSite(),
                                     "parameters" => "[serverName: {$nmServer->getName()}]"
                                 ));
    }






    // *****************************************************************************************************************
    // Private methods
    // *****************************************************************************************************************

    /**
     * @return array
     */
    private function getEsxServers() {
        $config  = $this->_config['vSphere'];
        $servers = array();
        foreach ($config as $k => $v) {
            if ($k == 'site') continue;
            $v['site'] = $k;
            $servers[] = $v;
        }
        return $servers;
    }

    /**
     * @param \Vmwarephp\Extensions\VirtualMachine $vm
     * @return bool
     */
    private function getVMMacAddress(\Vmwarephp\Extensions\VirtualMachine $vm) {
        if (!property_exists($vm->getConfig(), 'hardware') || !property_exists($vm->getConfig()->hardware, 'device')) {
            return false;
        }

        $devices = $vm->getConfig()->hardware->device;
        foreach ($devices as $d) {
            /* this used to work but not anymore and not sure why
            if ($d instanceof \VirtualE1000 || $d instanceof \VirtualVmxnet3) {
                return $d->macAddress;
            }
            */
            if (preg_match("/Network adapter/", $d->deviceInfo->label)) {
                return $d->macAddress;
            }
        }
        return false;
    }

    /**
     * @param Vhost $vServer
     * @param $task
     * @return bool|Task
     */
    private function watchTask(vHost $vServer, $task) {
        $this->writeLog(array("logLevel" => Logger::DEBUG, "logOutput" => "watchTask()"));
        $done = false;
        while (!$done) {
            /** @var Task $task */
            $this->writeLog(array("logLevel" => Logger::DEBUG, "logOutput" => "watchTask() - find Task"));
            $task = $vServer->findOneManagedObject('Task', $task->getReferenceId(), array('info'));
            $this->writeLog(array("logLevel" => Logger::DEBUG, "logOutput" => "watchTask() - Task found: " . $task->getReferenceId()));
            $info = $task->getInfo();
            $this->writeLog(array("logLevel" => Logger::DEBUG, "logOutput" => "watchTask() - Task info->state: " . $info->state));
            if ($info->state == "error") {
                $msg = "Error: VMWare returned an error";
                if (property_exists($info, 'error') && is_object($info->error) && property_exists($info->error, 'localizedMessage')) {
                    $msg = "Error: " . $info->error->localizedMessage;
                }
                $vServer->disconnect();

                $this->_viewData = array(
                    "success"   => false,
                    "error"     => $msg,
                    "logLevel"  => Logger::ERR,
                    "logOutput" => $msg,
                    "details"   => $info
                );
                return false;
            } else if ($info->state == "success") {
                $done = true;
            } else {
                sleep(1);
            }
        }
        return $task;
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
