#!/usr/bin/php
<?php

include __DIR__ . "/../config/global.php";

try {
    $config = require(__DIR__ . "/../config/module.config.php");

    $vServerName = 'stopvcenter02.va.neustar.com:443';
    $vServer = new \Vmwarephp\Vhost($vServerName, $config['vSphere']['prod']['username'], $config['vSphere']['prod']['password']);

    $service = $vServer->getService();
    $soapClient = $service->getSoapClient();

    $hostSystem = $vServer->findManagedObjectByName('HostSystem', 'stopcpresx11.va.neustar.com', array('name', 'vm'));

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
                "vmUid"    => $virtualMachine->getReferenceId(),
                "vmName"   => $virtualMachine->getName(),
                "numCPUs"  => $virtualMachine->getConfig()->hardware->numCPU,
                "memoryGB" => round($virtualMachine->getConfig()->hardware->memoryMB / 1024),
                "guestMemUsageMB" => $virtualMachine->summary->quickStats->guestMemoryUsage,
                "overallCpuUsage" => $virtualMachine->summary->quickStats->overallCpuUsage,
            );
        }
    }
    print_r($nodes);

    $vServer->disconnect();
}

catch (SoapFault $e) {
    global $service;
    $xml = $service->getLastRequest();

    print "message = " . $e->getMessage() . "\n";
    print "trace = " . $e->getTraceAsString() . "\n";
    print "xml = " . $service->getLastRequest() . "\n";
    file_put_contents($xmlFile, $xml);
}
