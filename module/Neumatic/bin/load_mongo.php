#!/usr/bin/php
<?php

include __DIR__ . "/../config/global.php";

try {
	// read the config file
    $config = require_once(__DIR__ . "/../config/module.config.php");

	// get the command line options
	$options = parseOptions();

    // check for a lock file. we don't run if another process is currently running
    $lockFile = __DIR__ . "/../../../data/load_mongo.lock";
    if (file_exists($lockFile)) {
        // check to see if there is really a process associated with this id
        $pid     = file_get_contents($lockFile);
        $command = "ps -ef | awk '{print \$2}' | grep {$pid} | grep -v grep";
        $result  = null;
        exec($command, $result);
        if ($result && is_array($result) && $result[0] == $pid) {
            print "Another get_chef_status process is running. Exiting...\n";
            exit;
        }
    }
    // no current process is running
    file_put_contents($lockFile, getmypid());

    /*********************************************************************/
	/******************** Log Files & Headers ****************************/
	/*********************************************************************/

	// general definitions
	$title = "NeuMatic: Load Mongo DB";
	$scriptName = $argv[0];
	$now = date("Y-m-d-H-i");
	$startTime = time();

	$optsNameWidth = 25;
	$summaryNameWidth = 30;

	// open the log file; also keep a log string to send in email if exception is thrown
	$logString = "";
    $logFileName = "load_mongo";
	$logFile = "{$config['logDir']}/{$logFileName}.{$now}";
	$logFilePtr = fopen($logFile, "w");

	$logHeader = "{$title} Log\n" .
		"\n" .
		"Host:       " . gethostname() . "\n" .
		"Script:     " . implode(' ', $argv) . "\n" .
		"Start Time: " . date("Y-m-d H:i:s", $startTime) . "\n" .
		"\n" .
		"Options: \n" .
		sprintf("\t %-{$optsNameWidth}s = %s\n", "StdOut", $options->stdOut ? "true" : "false") .
		sprintf("\t %-{$optsNameWidth}s = %s\n", "Force Run", $options->forceRun ? "true" : "false") .
		"\n";
	outlog($logHeader);


	// prune old log files
	outlog("Cleaning up old log files...\n");
	$logFiles = explode("\n", `ls -t {$config['logDir']}/{$logFileName}.*`);
	$todayMinusPrune = $startTime - (60 * 60 * 24 * $config['pruneAfterHours']);
	for ($i = 0; $i < count($logFiles); $i++) {
		$f = $logFiles[$i];
		if ($f === "") break;

		$stat = stat($f);
		$mTime = $stat[9];
		if ($mTime < $todayMinusPrune) {
			// log file is older than 7 days; delete
			outlog("\tPruning {$f} - " . date("Y-m-d H:i:s", $mTime) . "\n");
			unlink($f);
		}
	}
	outlog("\n");

	/*********************************************************************/
	/************************* Initialization ****************************/
	/*********************************************************************/

    // base api url for NeuMatic web service calls
    $baseApiUrl = "https://neumatic.ops.neustar.biz";

    // current time
    $currentTime = time();

    // current chef client target version
    $targetVersion = $config['chef']['targetVersion'];

    // mongo connect
    $mongo = new MongoClient();

    // NeuMatic models in case we need to update them as in the case of a lab server that was
    // bootstrapped to a different Chef server
    $chefTable = new \Neumatic\Model\NMChefTable($config);
    $serverTable = new \Neumatic\Model\NMServerTable($config);

    // select a database
    $db = $mongo->selectDB($config['databases']['mongo']['database']);
    $collection = $db->selectCollection($config['databases']['mongo']['collection']);

	// init summary stats
	$summary = (object)array(
        "numChefServers" => 0,
		"numServers"     => 0,
        "numNodes"       => 0,
        "numNotFound"    => 0,
        "numBuilt"       => 0,
	);

    // current time
    $currentTime = time();

    // current chef client target version
    $targetVersion = $config['chef']['targetVersion'];

    // get a list of chef servers
    outlog("Getting a list of Chef servers...\n");
    $chefServers = getChefServers();
    $summary->numChefServers = count($chefServers);

    // get all chef nodes
    outlog("Getting a list of nodes on all Chef servers...\n");
    $nodes = array();
    $nodesHash = array();
    foreach ($chefServers as $chefServer) {
        outlog(sprintf("\t%-20s %-30s ", "[" . $chefServer['env'] . "]", $chefServer['name']));
        $url = "{$baseApiUrl}/chef/getNodes?chef_server={$chefServer['name']}";
        $result = curlGet($url);
        $json = json_decode($result);
        if ($json->success && property_exists($json, 'nodes') && is_array($json->nodes)) {
            outlog(sprintf("%5d\n", count($json->nodes)));
            foreach ($json->nodes as $j) {
                $nodes[] = array(
                    "chefServer" => $chefServer['name'],
                    "name" => $j
                );
                $nodesHash[$j] = 1;
            }
        } else {
            outlog(sprintf("%5d\n", 0));
        }
    }
    $numNodes = count($nodes);
    $summary->numNodes = $numNodes;
    outlog("\t" . $numNodes . " nodes found\n");

    #outlog("Getting a list of nodes in the mongo db...\n");



    // get a list of all NeuMatic servers
    outlog("Getting NeuMatic servers...\n");
    $servers     = $serverTable->getAll();
    $numServers = count($servers);
    $summary->numServers = $numServers;
    outlog("\t" . $numServers . " servers found\n");
    $serversHash    = array();
    foreach ($servers as $server) {
        $serversHash[$server->getName()] = $server;
    }


    // now loop thru our Chef nodes, adding a NeuMatic flag and inserting into mongo
    outlog("Processing servers...\n");
    $chefTable = new Neumatic\Model\NMChefTable($config);
    $num = 0;
    foreach ($nodes as $obj) {
        $name = $obj['name'];
        $chefServerFqdn = $obj['chefServer'];

        $num++;
        outlog(sprintf("[%4d of %4d] %-45s", $num, $numNodes, $name));

        // get the ohai details for the node
        $result = curlGet("{$baseApiUrl}/chef/getNode/{$name}?chef_server={$chefServerFqdn}");
        $json = json_decode($result);
        if (!is_object($json) || !property_exists($json, 'success') || !$json->success) {
            outlog("Get node failed\n");
            continue;
        }

        $ohai = $json->node;
        if (!is_object($ohai)) {
            outlog("Ohai query result is not an object\n");
            continue;
        }

        // get the server from the servers hash so we can get additional info for mongo
        if (array_key_exists($name, $serversHash)) {
            $server = $serversHash[$name];
        } else {
            $server = new Neumatic\Model\NMServer();
            $server->setStatus('N/A')->setStatusText('N/A');
        }

        if (preg_match("/(\w+)\.\w+\.neustar.com/", $chefServerFqdn, $m)) {
            $chefServerName = $m[1];
        } else {
            $chefServerName = $chefServerFqdn;
        }

        if (property_exists($ohai, "automatic") && property_exists($ohai->automatic, "fqdn")) {
            $name = $ohai->automatic->fqdn;
        } else if (property_exists($ohai, "name")) {
            $name = $ohai->name;
        } else if (property_exists($ohai, "node") && property_exists($ohai->node, "name")) {
            $name = $ohai->node->name;
        } else {
            $name = "";
        }
        $ohai->name = $name;

        // check to see if we have all the good stuff. Could be that there's a minimum of info being reported
        if ($name != "" && !property_exists($ohai->automatic, 'ohai_time')) {
            outlog("Minimum info\n");
            $ohai->neumatic = (object) array(
                'name'               => $name,
                'chefServerName'     => $chefServerName,
                'chefServerFqdn'     => $chefServerFqdn,
                'chefVersion'        => '',
                'chefVersionStatus'  => 'red',
                'ohaiTime'           => 0,
                'ohaiTimeString'     => '',
                'ohaiTimeDiff'       => 0,
                'ohaiTimeDiffString' => 'n/a',
                'ohaiTimeStatus'     => 'red'
            );

            // look for the node document in the collection
            $doc = $collection->findOne(array("name" => $name), array("name" => 1));

            // Now insert or update the document
            if ($doc) {
                $collection->remove(array("name" => $name));
            }
            $collection->insert($ohai);

            if (array_key_exists($name, $nodesHash)) {
                unset($nodesHash[$name]);
            }
            continue;
        }

        // process the last check in time
        $ohaiTime = $ohai->automatic->ohai_time;
        $checkIn = calculateLastCheckInTime($currentTime, $ohaiTime);

        // create a new data object in our ohai data for NeuMatic values
        $ohai->neumatic = (object) array(
            'name'               => $name,
            'chefServerName'     => $chefServerName,
            'chefServerFqdn'     => $chefServerFqdn,
            'chefVersion'        => $ohai->automatic->chef_packages->chef->version,
            'chefVersionStatus'  => version_compare($ohai->automatic->chef_packages->chef->version, $targetVersion, '>=') ? 'green' : 'red',
            'ohaiTime'           => $ohaiTime,                      // decimal value of oahi time (seconds since epoch)
            'ohaiTimeString'     => date('Y-m-d H:i:s', $ohaiTime), // formated ohai time
            'ohaiTimeDiff'       => $checkIn['ohaiTimeDiff'],       // difference in seconds
            'ohaiTimeDiffString' => $checkIn['ohaiTimeDiffString'], // formatted difference in hours, mins, secs
            'ohaiTimeStatus'     => $checkIn['ohaiTimeStatus']      // time color code
        );

        outlog(sprintf("%-10s %-28s %-16s %s\n", $ohai->neumatic->chefVersion, $ohai->neumatic->ohaiTimeDiffString,
                       $server->getStatus(), $server->getStatusText()));

        // update the chef server in case it was bootstrapped elsewhere
        if ($server->getId() && $server->getChefServer() != $chefServerFqdn) {
            outlog("\tDeleting node & client from Chef server {$server->getChefServer()}\n");
            $result = curlGet("{$baseApiUrl}/chef/deleteNode/{$server->getName()}?chef_server={$server->getChefServer()}");
            $result = curlGet("{$baseApiUrl}/chef/deleteClient/{$server->getName()}?chef_server={$server->getChefServer()}");

            outlog("\tUpdating Chef server in server table\n");
            $server->setChefServer($chefServerFqdn);
            $server = $serverTable->update($server);

            // make sure that the chef server, env and role are correct for this server
            $chef = $chefTable->getByServerId($server->getId());
            if ($chef->getId() && $chef->getServer() != $chefServerFqdn) {
                outlog("\tUpdating Chef server in chef table\n");
                $chef
                    ->setServer($chefServerFqdn)
                    ->setEnvironment($ohai->chef_environment)
                    ->setRole(implode(",", $ohai->automatic->roles));
                $chef = $chefTable->update($chef);
            }
        }

        // look for the node document in the collection
        outlog("\tLooking up node in mongo db\n");
        $cursor = $collection->find(array("name" => $name), array("_id" => 1))->limit(1);
        #$doc = $collection->findOne(array("name" => $name), array("name" => 1));

        #print_r($doc["_id"]); exit;
        // Now insert or update the document
        if ($doc = $cursor->getNext()) {
            outlog("\tRemoving node\n");
            $collection->remove(array("_id" => $doc['_id']));
            outlog("\tInserting node\n");
            $collection->insert($ohai);
            #$ohai->_id = $doc['_id'];
            #outlog(" UPDATE");
            #$collection->update(array("_id" => $doc['_id']), $ohai, array("upsert" => true));
            #outlog(" DONE");
        } else {
            #outlog(" INSERT");
            outlog("\tInserting node\n");
            $collection->insert($ohai);
        }

        if (array_key_exists($name, $nodesHash)) {
            unset($nodesHash[$name]);
        }
    }

    // now remove all mongo entries no longer found in chef servers
    outlog("\n");
    outlog("Removing old mongo entries...\n");
    foreach ($nodesHash as $name => $dummy) {
        $cursor = $collection->find(array("name" => $name), array("_id" => 1))->limit(1);
        if ($doc = $cursor->getNext()) {
            outlog("\tRemoving {$name}\n");
            $collection->remove(array("_id" => $doc['_id']));
        }
    }

    outlog(generateSummary());
   	fclose($logFilePtr);
    unlink($lockFile);
}

/*********************************************************************/
/******************** Exception Catcher ******************************/
/*********************************************************************/

catch (Exception $e) {
    global $options, $logString, $config, $title, $lockFile;

    unlink($lockFile);

    $emailTo   = $config['adminEmail'];
    $emailFrom = $config['adminEmail'];
    $emailSubj = "{$title} Error Report";

    $headers = implode("\r\n", array(
        "MIME-Version: 1.0",
        "Content-type: text/html; charset=us-ascii",
        "From: {$emailFrom}",
        "Reply-To: {$emailFrom}",
        "X-Priority: 1",
        "X-MSMail-Priority: High",
        "X-Mailer: PHP/" . phpversion()
    ));

    $traceBack = "returnCode: 1\n" .
        "errorCode:  {$e->getCode()}\n" .
        "errorText:  {$e->getMessage()}\n" .
        "errorFile:  {$e->getFile()}\n" .
        "errorLine:  {$e->getLine()}\n" .
        "errorStack: {$e->getTraceAsString()}\n";

    outlog("{$traceBack}\n");

    if (isset($summary)) {
        outlog(generateSummary());
    }

    if (!$options->stdOut) {
        $emailBody = "<pre style='font-size:6pt;'>\n" .
            "{$logString}\n" .
            "</pre>\n";
        mail($emailTo, $emailSubj, $emailBody, $headers);
    }
    exit;
}

function calculateDateDiff($dateFrom, $dateTo) {
    $timeDiff = strtotime($dateFrom) - strtotime($dateTo);
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

function calculateLastCheckInTime($currentTime, $ohaiTime) {
    $obj = array();

    $timeDiff          = $currentTime - $ohaiTime;
    $obj['ohaiTimeDiff'] = $timeDiff;
    if ($timeDiff <= 60 * 60) {
        // Ok - less than an hour
        $obj['ohaiTimeDiffString'] = sprintf("%2d min", floor($timeDiff / 60));
        $obj['ohaiTimeStatus']      = "green";
    } else if ($timeDiff <= 60 * 60 * 24) {
        // warning - less than a day
        $hours                    = $timeDiff / 60 / 60;
        $mins                     = ($hours - floor($hours)) * 60;
        $obj['ohaiTimeDiffString'] = sprintf("%d hours %2d min", floor($hours), floor($mins));
        $obj['ohaiTimeStatus']      = "goldenrod";
    } else {
        // error - more than a day
        $days                     = $timeDiff / 60 / 60 / 24;
        $hours                    = ($days - floor($days)) * 24;
        $mins                     = ($hours - floor($hours)) * 60;
        $obj['ohaiTimeDiffString'] = sprintf("%d days %d hours %2d min", floor($days), floor($hours), floor($mins));
        $obj['ohaiTimeStatus']      = "red";
    }
    return $obj;
}

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
    $response = curl_exec($curl);

    curl_close($curl);
    return $response;
}

function parseOptions() {
    // command line opts
    $opts = getopt('hsr');

    // usage if -h
    if ($opts && array_key_exists('h', $opts)) usage();

    // define options
    $options = (object)array(
        "stdOut"       => array_key_exists('s', $opts) ? true : false,
        "forceRun"     => array_key_exists('r', $opts) ? true : false
    );
    return $options;
}

function usage() {
    print "Usage: get_chef_status [-hsr]\n";
    print "\n";
    print "       -h         this help\n";
    print "       -s         outlog to STDOUT in real time\n";
    print "       -r         force run even if runCronJobs is false\n";
    exit;
}

function getChefServers() {
    global $config;

    $servers = array_keys($config['chef']);
    $data    = array();
    foreach ($servers as $name) {
        if ($name == 'targetVersion') continue;

        $env = $config['chef'][$name]['env'];
        if ($env == 'default' ) continue;

        outlog(sprintf("\t%-20s %s\n", "[{$env}]", $name));
        $data[] = array(
            "name" => $name,
            "env"  => $env
        );
    }
    return $data;
}

function generateSummary() {
    global $startTime, $summary, $summaryNameWidth;

    // calc elapsed time
    $endTime       = time();
    $elapsedSecs   = $endTime - $startTime;
    $elapsedFormat = sprintf("%02d:%02d", floor($elapsedSecs / 60), $elapsedSecs % 60);

    return sprintf("\n\nSummary\n%'-60s\n", "") .

    sumOutput("Num Chef Servers", $summary->numChefServers) .
    sumOutput("Num Chef Nodes", $summary->numNodes) .
    sumOutput("Num Servers", $summary->numServers) .
    sumOutput("Num Transitioned", $summary->numBuilt, $summary->numServers) .
    sumOutput("Num Not Found", $summary->numNotFound, $summary->numServers) .
    "\n" .

    sprintf("%-{$summaryNameWidth}s: %s\n", "Start Time", date("Y-m-d H:i:s", $startTime)) .
    sprintf("%-{$summaryNameWidth}s: %s\n", "End Time", date("Y-m-d H:i:s", $endTime)) .
    sprintf("%-{$summaryNameWidth}s: %s\n", "Elapsed Time", $elapsedFormat) .
    "Synchronization Complete\n";
}

function sumOutput($title, $count, $total = null) {
    global $summaryNameWidth;

    if ($total) {
        return sprintf("%-{$summaryNameWidth}s: %5d (%4.1f%%)\n", $title, $count, round($count / $total * 100, 1));
    } else {
        return sprintf("%-{$summaryNameWidth}s: %5d\n", $title, $count);
    }
}

function outlog($logMsg) {
    global $options, $logFilePtr, $logString;

    if ($options->stdOut) {
        print $logMsg;
    }
    if ($logFilePtr) {
        fwrite($logFilePtr, $logMsg);
    }
    $logString .= $logMsg;
}

function printException(\ErrorException $e) {
    outlog("\n");
    outlog(sprintf("%-12s => %s\n", "returnCode", 1));
    outlog(sprintf("%-12s => %s\n", "errorCode", $e->getCode()));
    outlog(sprintf("%-12s => %s\n", "errorText", $e->getMessage()));
    outlog(sprintf("%-12s => %s\n", "errorFile", $e->getFile()));
    outlog(sprintf("%-12s => %s\n", "errorLine", $e->getLine()));
    outlog(sprintf("%-12s => \n%s\n", "errorStack", $e->getTraceAsString()));
    exit;
}

