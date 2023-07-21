#!/usr/bin/php
<?php

include __DIR__ . "/../config/global.php";

require_once(__DIR__ . "/../../../vendor/ChefServerApi/ChefServer.php");
require_once(__DIR__ . "/../../../vendor/ChefServerApi/ChefServer12.php");

use ChefServerApi\ChefServer;
use ChefServer12API\ChefServer12;

try {
	// read the config file
    $config = require_once(__DIR__ . "/../config/module.config.php");

	// get the command line options
	$options = parseOptions();

	// check to see if we should run
	if (!$config['runCronJobs'] && !$options->forceRun) {
		print "runCronJobs is set to false in the config file. Exiting...\n";
		exit;
	}

    // check for a lock file. we don't run if another process is currently running
    $lockFile = __DIR__ . "/../../../data/get_chef_status.lock";
    if (file_exists($lockFile)) {
        // check to see if there is really a process associated with this id
        $pid = file_get_contents($lockFile);
        $command = "ps -ef | awk '{print \$2}' | grep {$pid} | grep -v grep";
        $result = null;
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
	$title = "NeuMatic: Get Servers Chef Status";
	$scriptName = $argv[0];
	$now = date("Y-m-d-H-i");
	$startTime = time();

	$optsNameWidth = 25;
	$summaryNameWidth = 30;

	// open the log file; also keep a log string to send in email if exception is thrown
	$logString = "";
    $logFileName = "get_chef_status";
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
    // overriding config file since this runs frequently
	$todayMinusPrune = $startTime - (60 * 60 * 24 * 1);
	for ($i = 0; $i < count($logFiles); $i++) {
		$f = $logFiles[$i];
		if ($f === "") break;

		$stat = stat($f);
		$mTime = $stat[9];
		if ($mTime < $todayMinusPrune) {
			// log file is older than 5 days; delete
			outlog("\tPruning {$f} - " . date("Y-m-d H:i:s", $mTime) . "\n");
			unlink($f);
		}
	}
	outlog("\n");

	/*********************************************************************/
	/************************* Initialization ****************************/
	/*********************************************************************/

	// init summary stats
	$summary = (object)array(
        "numChefServers" => 0,
		"numServers"     => 0,
        "numNodes"       => 0,
        "numNotFound"    => 0,
        "numBuilt"       => 0,
	);

    // one day in seconds
    $oneDayInSeconds = 60 * 60 * 24;

    // current time
    $currentTime = time();

    // current chef client target version
    $targetVersion = $config['chef']['targetVersion'];

    // get a list of chef servers
    outlog("Getting a list of Chef servers...\n");
    $chefServers = getChefServers();
    $summary->numChefServers = count($chefServers);
    outlog("\t" . count($chefServers) . " servers found\n");

    // get a list of all NeuMatic servers
    outlog("Getting NeuMatic servers...\n");
    $serverTable = new Neumatic\Model\NMServerTable($config);
    $servers     = $serverTable->getAll();
    $numServers = count($servers);
    $summary->numServers = $numServers;
    outlog("\t" . $numServers . " servers found\n");

    // query each to get a total list of nodes. the hash will be nodeName => chefServer
    outlog("Getting a list of nodes on all Chef servers...\n");
    $nodesHash    = array();
    $chefInstHash = array();
    foreach ($chefServers as $chefServer) {
        $chefConfig = $config['chef'][$chefServer['name']];

        outlog(sprintf("\t%-28s %-60s", "[{$chefConfig['env']}]", $chefConfig['server']));

        if (isset($chefConfig['enterprise']) AND $chefConfig['enterprise'] === true) {
            $server = $chefConfig['server'];

            if (!preg_match("/https:\/\//", $server)) {
                $server = 'https://' . $server;
            }
            $chefInst = new ChefServer12($server, $chefConfig['client'], $chefConfig['keyfile'], '11.12.1', true);
        } else {
            $chefInst = new ChefServer($chefConfig['server'], $chefConfig['port'], $chefConfig['client'], $chefConfig['keyfile'], false);
        }


        # = new \ChefServerApi\ChefServer($chefConfig['server'], $chefConfig['port'], $chefConfig['client'], $chefConfig['keyfile']);
        $chefInstHash[$chefConfig['server']] = $chefInst;

        if (preg_match("/ent - /", $chefConfig['env'])) {
            $result = $chefInst->get('/nodes');
        } else {
            $result = $chefInst->get('/nodes');
        }
        $i = 0;
		$result = (array) $result;
        if ($result && is_array($result)) {
            foreach ($result AS $k => $v) {
                $i++;
                $nodesHash[$k] = $chefConfig['server'];
            }
        }
        outlog(sprintf("%5d\n", $i));
    }
    $summary->numNodes = count($nodesHash);
    outlog("\t" . count($nodesHash) . " nodes found\n");

    $emailBody = '';
    $sendEmail = false;

    // now loop thru our NeuMatic servers and get the chef details for each
    outlog("Processing servers...\n");
    $chefTable = new Neumatic\Model\NMChefTable($config);
    $num = 0;
    foreach ($servers as $server) {
        $num++;
        outlog(sprintf("[%4d of %4d] %-45s", $num, $numServers, $server->getName()));

        // lookup the server in the nodes hash to obtain the correct/current chef server it is bootstrapped to
        if (!array_key_exists($server->getName(), $nodesHash)) {
            $summary->numNotFound++;
            outlog("Node not found\n");
            continue;
        }
        $chefServer = $nodesHash[$server->getName()];

        // get the row from the chef table
        $chefModel = $chefTable->getByServerId($server->getId());

        // in case it was not found...
        $chefModel->setServerId($server->getId());
        $chefModel->setServer($chefServer);

        // reuse our chef instance
        /** @var \ChefServerApi\ChefServer $chefInst */
        $chefInst = $chefInstHash[$chefServer];

        if (get_class($chefInst) == 'ChefServer12API\ChefServer12') {
            $ent = true;
        } else {
            $ent = false;
        }
        outlog(sprintf("%-5s", $ent ? "ENT" : ""));

        try {
            // get the props for this node
            $results = $chefInst->postWithQueryParams(
                                '/search/node',
                                '?q=name:' . $server->getName(),
                                '{"chefServerUrl":["chef_client","config","chef_server_url"],' .
                                '"chefVersion":["chef_packages","chef","version"],' .
                                '"ohaiTime":["ohai_time"],' .
                                '"environment":["chef_environment"],' .
                                '"roles":["roles"]}',
                                true);
        } catch(Exception $e) {
            outlog("ERROR " . $e->getCode() . ": " . $e->getMessage() . "\n");
            if ($e->getCode() == "401") {
                exit;
            }
            continue;
        }

        // if results were returned from the chef query, process the data
        if (property_exists($results, 'total') && $results->total > 0 && property_exists($results, 'rows') && count($results->rows) > 0) {
            $data = $results->rows[0]->data;

            // check chef version against target
            if (property_exists($data, 'chefVersion')) {
                // assign the chef version
                $chefModel->setVersion($data->chefVersion);

                if (version_compare($data->chefVersion, $targetVersion, '>=')) {
                    $chefModel->setVersionStatus('green');
                } else {
                    $chefModel->setVersionStatus('red');
                }
            } else {
                $chefModel->setVersion('');
                $chefModel->setVersionStatus('green');
            }

            // get the ohai checkin time and calulate the diff from now
            if (property_exists($data, 'ohaiTime')) {
                // calculate time since check in
                $checkIn = calculateLastCheckInTime($currentTime, $data->ohaiTime);

                // update our model
                $chefModel
                    ->setOhaiTimeInt($data->ohaiTime) // decimal value of oahi time (seconds since epoch)
                    ->setOhaiTime(date('Y-m-d H:i:s', $data->ohaiTime)) // formated ohai time
                    ->setOhaiTimeDiff($checkIn->ohaiTimeDiff) // difference in seconds
                    ->setOhaiTimeDiffString($checkIn->ohaiTimeDeltaString) // formatted difference in hours, mins, secs
                    ->setOhaiTimeStatus($checkIn->ohaiTimeStatus); // time color code
            } else {
                $chefModel
                    ->setOhaiTimeInt(null)
                    ->setOhaiTime(null)
                    ->setOhaiTimeDiff(null)
                    ->setOhaiTimeDiffString(null)
                    ->setOhaiTimeStatus(null);
            }

            // get the environment
            if (property_exists($data, 'environment')) {
                $chefModel->setEnvironment($data->environment);
            } else {
                $chefModel->setEnvironment(null);
            }

            // get the role
            if (property_exists($data, 'roles') && is_array($data->roles)) {
                $chefModel->setRole(implode(",", $data->roles));
            } else {
                $chefModel->setRole(null);
            }
        }

        outlog(sprintf("%-10s %-28s Role: %-40s %-16s %s\n", $chefModel->getVersion(), $chefModel->getOhaiTimeDiffString(), $chefModel->getRole(), $server->getStatus(), $server->getStatusText()));

        if ($server->getStatus() == 'Building' && $server->getStatusText() == 'Cooking...') {
            // extra debug logs
            outlog("\tIdentified Cooking...\tRole={$chefModel->getRole()}\tTimeDiff={$chefModel->getOhaiTimeDiff()}\n");
            //$sendEmail = true;
        }

        // now we need to check if the build status should be changed to complete.
        // if the current status is 'Building' or 'Aborted' and we have a non-empty role value, then mark as Built
        if (($server->getStatus() == 'Building' || $server->getStatus() == 'Aborted' || $server->getStatus() == 'Failed')
            && $chefModel->getRole() != null
            && $chefModel->getRole() != ""
            && $chefModel->getOhaiTimeDiff() > 0) {

            //$sendEmail = true;

            // mark the server as built if ohai time < one day and any of the following are true
            if ((($server->getStatus() == 'Building'
                        && ($server->getStatusText() == 'Cooking...' || $server->getStatusText() == "Build stopping..."))
                || ($server->getStatus() == 'Aborted')
                || ($server->getStatus() == 'Failed'))
                && $chefModel->getOhaiTimeDiff() > 0
                && $chefModel->getOhaiTimeDiff() < 60 * 60)
            {
                outlog("\tMarking as Built, statusText=" . $server->getStatusText() . "\n");
                // mark server build as complete
                $server
                    ->setStatus('Built')
                    ->setStatusText('Built');
                $server    = $serverTable->update($server);

                $chefModel->setCookEndTime(date('Y-m-d H:i:s', $currentTime));
                $chefModel->setCookTimeString(calculateDateDiff($chefModel->getCookStartTime(),
                                                                $chefModel->getCookEndTime()));
                $chefTable->update($chefModel);

                // delete the cobbler system profile
                outlog("\tDeleting Cobbler server profile...\n");
                curlGet("https://neumatic.ops.neustar.biz/cobbler/deleteSystem/" . $server->getId());

            }

            // delete the cobbler watcher log file if exists
            outlog("\tChecking for cobbler_watch.log.{$server->getName()}\n");
            if (file_exists("/opt/neumatic/watcher_log/cobbler_watch.log.{$server->getName()}")) {
                outlog("\tDeleting cobbler_watch.log.{$server->getName()}\n");
                unlink("/opt/neumatic/watcher_log/cobbler_watch.log.{$server->getName()}");
            }

            // delete the chef watcher log file if exists
            outlog("\tChecking for chef_watch.log.{$server->getName()}\n");
            if (file_exists("/opt/neumatic/watcher_log/chef_watch.log.{$server->getName()}")) {
                outlog("\tDeleting chef_watch.log.{$server->getName()}\n");
                unlink("/opt/neumatic/watcher_log/chef_watch.log.{$server->getName()}");
            }

            $summary->numBuilt++;
        }

        // if we're still Cooking past the timeout, then set server build status to Failed
        if ($server->getStatus() == 'Building' && $server->getStatusText() == 'Cooking...') {
            //$sendEmail = true;

            if ($chefModel->getCookStartTime()) {
                $cookingStart = strtotime($chefModel->getCookStartTime());
                $diff = time() - $cookingStart;

                outlog("\tdate cooking start={$server->getTimeBuildEnd()}\n");
                outlog("\ttime cooking start={$cookingStart}\n");

                $dateNow = date('Y-m-d H:i:s', time());
                outlog("\tdate now=" . $dateNow . "\n");

                $timeNow = strtotime($dateNow);
                outlog("\ttime now=" . $timeNow . "\n");

                $diff = $timeNow - $cookingStart;
                outlog("\ttime diff={$diff}\n");

                outlog("\tconfigTimeout=" . $config['chefClientRunTimeoutSecs'] . "\n");

                if ($diff > $config['chefClientRunTimeoutSecs']) {
                    outlog("\ttime:" . time() . " - cookingStart:{$cookingStart} = {$diff} > " . $config['chefClientRunTimeoutSecs']);

                    $server->setStatus('Failed')
                        ->setStatusText('Chef run timeout');
                    $serverTable->update($server);
                }
            }
        }

        // save the chef model and server
        if (count($chefModel->getChanges()) > 0) {
            $chefModel = $chefTable->save($chefModel);
        }

        #outlog("\n");
    }

    outlog(generateSummary());
   	fclose($logFilePtr);

    if ($sendEmail) {
        sendMail($emailBody);
    }

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

function sendMail($emailBody) {
    global $config, $title;

    $emailTo   = $config['adminEmail'];
    $emailFrom = $config['adminEmail'];
    $emailSubj = "{$title} Notification";

    $headers = implode("\r\n", array(
        "MIME-Version: 1.0",
        "Content-type: text/html; charset=us-ascii",
        "From: {$emailFrom}",
        "Reply-To: {$emailFrom}",
        "X-Priority: 1",
        "X-MSMail-Priority: High",
        "X-Mailer: PHP/" . phpversion()
    ));

    mail($emailTo, $emailSubj, $emailBody, $headers);
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

function calculateLastCheckInTime($currentTime, $ohaiTime) {
    $obj = (object)array();

    $timeDiff          = $currentTime - $ohaiTime;
    $obj->ohaiTimeDiff = $timeDiff;
    if ($timeDiff <= 60 * 60) {
        // Ok - less than an hour
        $obj->ohaiTimeDeltaString = sprintf("%2d min", floor($timeDiff / 60));
        $obj->ohaiTimeStatus      = "ok";
    } else if ($timeDiff <= 60 * 60 * 24) {
        // warning - less than a day
        $hours                    = $timeDiff / 60 / 60;
        $mins                     = ($hours - floor($hours)) * 60;
        $obj->ohaiTimeDeltaString = sprintf("%d hours %2d min", floor($hours), floor($mins));
        $obj->ohaiTimeStatus      = "warning";
    } else {
        // error - more than a day
        $days                     = $timeDiff / 60 / 60 / 24;
        $hours                    = ($days - floor($days)) * 24;
        $mins                     = ($hours - floor($hours)) * 60;
        $obj->ohaiTimeDeltaString = sprintf("%d days %d hours %2d min", floor($days), floor($hours), floor($mins));
        $obj->ohaiTimeStatus      = "error";
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
    global $options, $logFilePtr, $logString, $emailBody;

    if ($options->stdOut) {
        print $logMsg;
    }
    fwrite($logFilePtr, $logMsg);
    $logString .= $logMsg;
    $emailBody .= $logMsg;
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

