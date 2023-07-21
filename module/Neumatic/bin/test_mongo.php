#!/usr/bin/php
<?php

include __DIR__ . "/../config/global.php";

// read the config file
$config = require_once(__DIR__ . "/../config/module.config.php");

// one day in seconds
$oneDayInSeconds = 60 * 60 * 24;

// current time
$currentTime = time();

$mongo = new \MongoClient();

// get our database and collection
$database = $mongo->selectDB($config['databases']['mongo']['database']);
$collection = $database->selectCollection($config['databases']['mongo']['collection']);

#$doc = $collection->findOne(array("name" => "stlabvnode37.va.neustar.com"));
#print_r($doc);

/*
$cursor = $collection->find(
    array("automatic.fqdn" => "stlabvnode37.va.neustar.com"),
    array(
        "automatic.hostname" => 1,
        "automatic.fqdn" => 1,
        "neumatic.chefServerName" => 1,
        "neumatic.chefServerFqdn" => 1,
        "neumatic.chefVersion" => 1,
        "neumatic.chefVersionStatus" => 1,
        "neumatic.ohaiTime" => 1,
        "neumatic.ohaiTimeString" => 1,
        "neumatic.ohaiTimeDiff" => 1,
        "neumatic.ohaiTimeDiffString" => 1,
        "neumatic.ohaiTimeStatus" => 1,
        "automatic.roles" => 1,
        "automatic.cpu.total" => 1,
        "automatic.memory.total" => 1,
        "automatic.dmi.system.manufacturer" => 1,
        "automatic.dmi.system.product_name" => 1,
        "automatic.platform" => 1,
        "automatic.platform_version" => 1,
        "chef_environment" => 1,
    )
);
*/
#$cursor = $collection->find(array(), array("_id" => 1, "node.name" => 1));
$cursor = $collection->find();

$count = 0;
$hash = array();
foreach ($cursor as $doc) {
    $count++;
    /*
    if ($count == 20) {
        print_r($doc);
        exit;
    }
    continue;
    */

    if (array_key_exists("automatic", $doc) && array_key_exists("fqdn", $doc['automatic'])) {
        $hash[$doc['automatic']['fqdn']] = 1;
        print "automatic.fqdn: " . $doc['automatic']['fqdn'] . "\n";
    }
    else if (array_key_exists("name", $doc)) {
        $hash[$doc['name']] = 1;
        print "name: " . $doc['name'] . "\n";
    } else if (array_key_exists("node", $doc) && array_key_exists("name", $doc['node'])) {
        print "node.name: " . $doc['node']['name'] . "\n";
    } else {
        if ($doc['success']) {
            print_r($doc);
        } else if (array_key_exists("message", $doc)) {
            print "success=" . $doc['success'] . ", message=" . $doc['message'] . "\n";
        } else {
            print_r($doc);
        }
    }
}
print count($hash) . "\n";