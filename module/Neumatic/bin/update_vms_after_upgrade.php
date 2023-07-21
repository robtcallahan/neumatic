#!/usr/bin/php
<?php

/**
 * This script had to be run after the lab esx cluster was upgraded to VMware 5.5
 * as all the object keys (ids) changed. Since there is only one lab cluster, all
 * that had to be done was to update the keys for all the objects.
 *
 * It should be noted that the instance uid is probably incorrect as we updated it
 * as a key and not the uuid. However, after looking over the code, instance uuid
 * doesn't seem to be used anywhere and is just updated as a reference. It would
 * be good to go back and put in the correct info.
 *
 */
include __DIR__ . "/../config/global.php";
$config = require_once(__DIR__ . "/../config/module.config.php");


try {

    $json = json_decode(file_get_contents(__DIR__ . "/../../../data/vcenter_lab03_vms.json"));
    $nodes = $json->nodes;

    $serverTable = new Neumatic\Model\NMServerTable($config);
    $vmTable = new Neumatic\Model\NMVMWareTable($config);

    foreach ($nodes as $node) {
        print $node->name . ": ";
        $server = $serverTable->getByName($node->name);

        if ($server->getId()) {
            $vm = $vmTable->getByServerId($server->getId());
            print $vm->getInstanceUuid() . " -> " . $node->uid . "\n";

            $vm->setInstanceUuid($node->uid)
                ->setVSphereServer("stlabvcenter03.va.neustar.com")
                ->setDcUid("datacenter-401")
                ->setCcrName("LAB_Cluster")
                ->setCcrUid("domain-c406")
                ->setVlanId("dvportgroup-417")
                ->setRpUid("resgroup-407");
            $vmTable->update($vm);
        } else {
            print "NOT FOUND\n";
        }
    }


} catch (Exception $e) {
    print("\n");
   	printf("%-12s => %s\n",   "returnCode", 1);
   	printf("%-12s => %s\n",   "errorCode",  $e->getCode());
   	printf("%-12s => %s\n",   "errorText",  $e->getMessage());
   	printf("%-12s => %s\n",   "errorFile",  $e->getFile());
   	printf("%-12s => %s\n",   "errorLine",  $e->getLine());
   	printf("%-12s => \n%s\n", "errorStack", $e->getTraceAsString());
   	exit;
}

function curlGetUrl($url, $post = null) {
    $curl = curl_init($url);

    curl_setopt($curl, CURLOPT_HTTPAUTH, CURLAUTH_BASIC);
    curl_setopt($curl, CURLOPT_USERPWD, "rcallaha:D43m0n01");
    //curl_setopt($curl, CURLOPT_VERBOSE, true);
    curl_setopt($curl, CURLOPT_HEADER, false);
    curl_setopt($curl, CURLOPT_SSL_VERIFYPEER, false);
    curl_setopt($curl, CURLOPT_SSL_VERIFYHOST, false);
    curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);

    if (is_array($post)) {
        curl_setopt($curl, CURLOPT_POSTFIELDS, $post);
    }

    $response = curl_exec($curl);

    curl_close($curl);
    return $response;
}
