#!/usr/bin/php
<?php


include __DIR__ . "/../config/global.php";
$config = require_once(__DIR__ . "/../config/module.config.php");

print "Updating Usergroup List\n";
$updateUsergroupListUrl = $config['neumaticWebServiceUrl']."/ldap/updateUsergroupList";
$updateUsergroupListResult = curlGet($updateUsergroupListUrl);
print print_r($updateUsergroupListResult, true) . "\n";

print "Updating Hostgroup List\n";
$updateHostgroupListUrl = $config['neumaticWebServiceUrl']."/ldap/updateHostgroupList";
$updateHostgroupListResult = curlGet($updateHostgroupListUrl);
print print_r($updateHostgroupListResult, true) . "\n";

/*
print "Updating nodes with neuOwner\n";
$configServers = $config['chef'];
foreach($configServers AS $sk=>$sv){
    if($sk == 'targetVersion' OR $sk == 'default'){
        continue;
    }
    $sName = $sv['server'];
    $updateNodesWithNeuOwnerUrl = $config['neumaticWebServiceUrl']."/ldap/updateNodesWithNeuOwner/".$sName;
    //echo $updateNodesWithNeuOwnerUrl;
    $updateNodesWithNeuOwnerResult = curlGet($updateNodesWithNeuOwnerUrl);
    print print_r($updateNodesWithNeuOwnerResult, true) . "\n";
}
*/




function curlGet($url, $post = null) {
    global $config;

    $username = $config['stsappsUser'];
    $password = $config['stsappsPassword'];

    $curl = curl_init($url);

    curl_setopt($curl, CURLOPT_HTTPAUTH, CURLAUTH_BASIC);
    curl_setopt($curl, CURLOPT_USERPWD, "{$username}:{$password}");
    curl_setopt($curl, CURLOPT_HEADER, false);
    curl_setopt($curl, CURLOPT_SSL_VERIFYPEER, false);
    curl_setopt($curl, CURLOPT_SSL_VERIFYHOST, false);
    curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);

    if (is_array($post)) {
        curl_setopt($curl, CURLOPT_POSTFIELDS, $post);
    }
    try {
        $response = curl_exec($curl);
    } catch(Exception $e) {
        print "Curl exception: " . $e->getMessage() . "\n";
        exit;
    }

    curl_close($curl);
    return $response;
}
