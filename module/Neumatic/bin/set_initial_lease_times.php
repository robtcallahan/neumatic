#!/usr/bin/php
<?php

/**
 * The purpose is to create lease table entries for all lab VMs and then
 * set the lease duration to the number of days between vm create date and today
 * + 7 days if the time diff is more than 30 days.
 * This gives the user 7 days plus 2, 7 day extensions if they still want the VM around.
 * The script should only be needed to run once.
 */
include __DIR__ . "/../config/global.php";
$config = require_once(__DIR__ . "/../config/module.config.php");

$oneDay = 60 * 60 * 24;

try {

    $serverTable = new Neumatic\Model\NMServerTable($config);
    $vmTable     = new Neumatic\Model\NMVMWareTable($config);
    $leaseTable  = new Neumatic\Model\NMLeaseTable($config);

    $now = time();

    // get all the lab VMs
    $vms = $vmTable->getByVSphereSite("lab");
    foreach ($vms as $vm) {
        $server = $serverTable->getById($vm->getServerId());

        $lease = $leaseTable->getByServerId($vm->getServerId());
        $dateCreatedTime = strtotime($lease->getLeaseStart());

        print $server->getName() . "[" . $lease->getLeaseStart() . "]: ";

        // diff of days between create date and today
        $daysDiff = floor(($now - $dateCreatedTime) / $oneDay);

        $update = false;
        if ($lease->getExpired()) {
            $update = true;
            $newLeaseDurationDays = $daysDiff + 6;
            $newLeaseDurationDate = date('Y-m-d', $dateCreatedTime + ($daysDiff * $oneDay) + (7 * $oneDay));
            $lease
                ->setExpired(0)
                ->setLeaseDuration($newLeaseDurationDays);
            print "UPDATE: " . $server->getDateCreated() . ", Days: " . $daysDiff . ", New Days: " . $newLeaseDurationDays . ", Date: " . $newLeaseDurationDate . "\n";
            $leaseTable->update($lease);
        } else {
            print "OK\n";
        }

        // if less than 30, leave it alone
        // if greater than 30 days, then set the lease duration to the number of days
        // between the create date of vm and today + 7. That'll give the user 7 days
        // plus 2 extensions of 7 days each.
        /*
        if ($daysDiff >= 30) {
            $newLeaseDurationDays = $daysDiff + 10;
            $newLeaseDurationDate = date('Y-m-d', $dateCreatedTime + ($daysDiff * $oneDay) + (7 * $oneDay));
            $lease->setLeaseDuration($newLeaseDurationDays);
        } else {
            $newLeaseDurationDays = (30 - $daysDiff) + 14;
            $newLeaseDurationDate = date('Y-m-d', $dateCreatedTime + ($daysDiff * $oneDay) + (7 * $oneDay));
            $lease->setLeaseDuration($newLeaseDurationDays);
            $lease->setLeaseDuration(30);
        }
        */
    }


} catch (Exception $e) {
    print("\n");
    printf("%-12s => %s\n", "returnCode", 1);
    printf("%-12s => %s\n", "errorCode", $e->getCode());
    printf("%-12s => %s\n", "errorText", $e->getMessage());
    printf("%-12s => %s\n", "errorFile", $e->getFile());
    printf("%-12s => %s\n", "errorLine", $e->getLine());
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
