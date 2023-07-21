#!/usr/bin/php
<?php

include __DIR__ . "/../config/global.php";

// read the config file
$config = require_once(__DIR__ . "/../config/module.config.php");

// one day in seconds
$oneDayInSeconds = 60 * 60 * 24;

// current time
$currentTime = time();

$serverTable = new Neumatic\Model\NMServerTable($config);
$server = $serverTable->getById(1432);

#$chefTable = new Neumatic\Model\NMChefTable($config);

print "date build end={$server->getTimeBuildEnd()}\n";

$timeBuildEnd = strtotime($server->getTimeBuildEnd());
print "time build end={$timeBuildEnd}\n";

print "date=" . date('Y-m-d h:i:s') . "\n";
print "time=" . time() . "\n";

$nDate = date('Y-m-d h:i:s', time());
print "date from time=" . $nDate . "\n";
$nTime =  strtotime($nDate);
print "time from date=" . $nTime . "\n";

$diff = $nTime - $timeBuildEnd;
print "diff={$diff}\n";
print "configTimeout=" . $config['chefClientRunTimeoutSecs'] . "\n";

function calculateDateDiff($dateFrom, $dateTo) {
    $timeDiff = strtotime($dateTo) - strtotime($dateFrom);
    if ($timeDiff <= 60 * 60) {
        // less than an hour
        return sprintf("%2d min", floor($timeDiff / 60));
    } else if ($timeDiff <= 60 * 60 * 24) {
        // less than a day
        $hours = $timeDiff / 60 / 60;
        $mins  = ($hours - floor($hours)) * 60;
        return sprintf("%d hours %2d min", floor($hours), floor($mins));
    } else {
        // more than a day
        $days  = $timeDiff / 60 / 60 / 24;
        $hours = ($days - floor($days)) * 24;
        $mins  = ($hours - floor($hours)) * 60;
        return sprintf("%d days %d hours %2d min", floor($days), floor($hours), floor($mins));
    }
}

