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
    $title      = "NeuMatic: Load Mongo DB";
    $scriptName = $argv[0];
    $now        = date("Y-m-d-H-i");
    $startTime  = time();

    $optsNameWidth    = 25;
    $summaryNameWidth = 30;

    // open the log file; also keep a log string to send in email if exception is thrown
    $logString   = "";
    $logFileName = "load_mongo";
    $logFile     = "{$config['logDir']}/{$logFileName}.{$now}";
    $logFilePtr  = fopen($logFile, "w");

    $logHeader = "{$title} Log\n" .
        "\n" .
        "Host:       " . gethostname() . "\n" .
        "Script:     " . implode(' ', $argv) . "\n" .
        "Start Time: " . date("Y-m-d H:i:s", $startTime) . "\n" .
        "\n" .
        "Options: \n" .
        sprintf("\t %-{$optsNameWidth}s = %s\n", "StdOut", $options->stdOut ? "true" : "false") .
        sprintf("\t %-{$optsNameWidth}s = %s\n", "Force Run", $options->forceRun ? "true" : "false") .
        sprintf("\t %-{$optsNameWidth}s = %s\n", "Start at ", $options->startAt ? $options->startAt : "first Chef server") .
        "\n";
    outlog($logHeader);


    // prune old log files
    outlog("Cleaning up old log files...\n");
    $logFiles        = explode("\n", `ls -t {$config['logDir']}/{$logFileName}.*`);
    $todayMinusPrune = $startTime - (60 * 60 * 24 * $config['pruneAfterHours']);
    for ($i = 0; $i < count($logFiles); $i++) {
        $f = $logFiles[$i];
        if ($f === "") break;

        $stat  = stat($f);
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
    $chefTable   = new \Neumatic\Model\NMChefTable($config);
    $serverTable = new \Neumatic\Model\NMServerTable($config);

    // select a database
    $db         = $mongo->selectDB($config['databases']['mongo']['database']);
    $collection = $db->selectCollection($config['databases']['mongo']['collectionAll']);

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

    // get a list of chef servers
    outlog("Getting a list of Chef servers...\n");
    $chefServers             = getChefServers();
    $summary->numChefServers = count($chefServers);

    // get all chef nodes
    outlog("Getting a list of nodes on all Chef servers...\n");
    $nodesHash = array();
    $chefDir   = "/var/chef";
    $dh = null;

    foreach ($chefServers as $chefServer) {
        if ($options->startAt && $chefServer['name'] != $options->startAt) continue;

        // parse out the organization name
        if (preg_match("/^chef.ops.neustar.biz\/organizations\/(\S+)/", $chefServer['name'], $m)) {
            $entOrg = $m[1];
        } else {
            $entOrg = "NA";
        }
        outlog(sprintf("\t%-20s %-30s\n", "[" . $chefServer['env'] . "]", $chefServer['name']));

        // get the nodes and environments from the Chef server
        $lastLine = system("cd {$chefDir}/{$chefServer['name']}; /usr/bin/knife download nodes; /usr/bin/knife download environments;", $retVal);

        $dirName   = "{$chefDir}/{$chefServer['name']}";

        $fileArray = scandir("{$dirName}/nodes");
        $numNodes  = count($fileArray) - 2;
        $summary->numNodes += $numNodes;
        outlog("\t" . $numNodes . " nodes found\n");

        outlog("Processing servers...\n");

        // open the nodes directory and process each file
        $dh  = opendir("{$dirName}/nodes");
        $num = 0;
        while (($fileName = readdir($dh)) !== false) {
            // skip all but *.json files
            if (strpos($fileName, "json") === false) continue;

            $num++;
            outlog(sprintf("[%4d of %4d] %-50s", $num, $numNodes, $fileName));

            // slurp in the file
            $contents      = file_get_contents("{$dirName}/nodes/{$fileName}");
            $nodesHash[$fileName] = true;

            // loop over all the records in the file and look for '.' or '$' for replacement as
            // mongo does not like '.' or '$' in the key field.
            // also, make note of the environment. We'll need to get the version of build-neu_collection if available
            $json = array();
            $chefEnvironment = "";
            foreach (explode("\n", $contents) as $rec) {
                if (preg_match("/\..*:/", $rec)) {
                    $rec = str_replace('.', "DOT", $rec);
                }
                if (preg_match("/\\$.*:/", $rec)) {
                    $rec = str_replace('$', "DOLLAR", $rec);
                }
                $json[] = $rec;
                if (preg_match('/"chef_environment": "(\w+)",/', $rec, $m)) {
                    $chefEnvironment = $m[1];
                }
            }
            $contents = implode("\n", $json);
            $ohai     = json_decode($contents);

            if (!is_object($ohai)) {
                outlog("Ohai query result is not an object\n");
                continue;
            }

            // determine the hostname be several methods
            if (property_exists($ohai, "automatic") && property_exists($ohai->automatic, "fqdn")) {
                $name = strtolower($ohai->automatic->fqdn);
            } else if (property_exists($ohai, "name")) {
                $name = strtolower($ohai->name);
            } else if (property_exists($ohai, "node") && property_exists($ohai->node, "name")) {
                $name = strtolower($ohai->node->name);
            } else {
                $name = "";
            }
            $ohai->name = $name;

            // before we go and get the version of build-neu_collection, let's see if it's in our run list
            $neuCollectionRecipeFound = false;
            if (property_exists($ohai, 'automatic') && property_exists($ohai->automatic, 'recipes') && is_array($ohai->automatic->recipes)) {
                $recipes = $ohai->automatic->recipes;
                foreach ($recipes as $recipe) {
                    if (strstr($recipe, 'build-neu_collection') !== -1) {
                        $neuCollectionRecipeFound = true;
                        break;
                    }
                }
            }
            // read the associated env file to get the build-neu_collection version if available
            $neuCollectionVer = "NA";
            if ($neuCollectionRecipeFound) {
                if (file_exists("{$dirName}/environments/{$chefEnvironment}.json")) {
                    // slurp in the file
                    $envFile = file_get_contents("{$dirName}/environments/{$chefEnvironment}.json");
                    // loop over the records until something like
                    //   "cookbook_versions": {
                    //     "build-neu_collection": ">= 1.1.3",
                    //     "core_content": "= 1.0.0"
                    //   },
                    foreach(explode("\n", $envFile) as $rec) {
                        if (preg_match('/"build-neu_collection": "(.*)",/', $rec, $m)) {
                            $neuCollectionVer = $m[1];
                            break;
                        }
                    }
                }
            }

            // check to see if we have all the good stuff. Could be that there's a minimum of info being reported
            if ($name != "" && !property_exists($ohai, 'automatic') || !property_exists($ohai->automatic, 'ohai_time')) {
                outlog("Minimum info\n");
                $ohai->neumatic = (object)array(
                    'name'               => $name,
                    'chefServerName'     => $chefServer['name'],
                    'chefServerEnv'      => $chefServer['env'],
                    'chefEntOrg'         => $entOrg,
                    'chefVersion'        => '',
                    'chefVersionStatus'  => 'red',
                    'neuCollectionVersion' => $neuCollectionVer,
                    'ohaiTime'           => 0,
                    'ohaiTimeString'     => '',
                    'ohaiTimeDiff'       => 0,
                    'ohaiTimeDiffString' => 'NA',
                    'ohaiTimeStatus'     => 'red'
                );

                // look for the node document in the collection
                $doc = $collection->findOne(array("name" => $name), array("name" => 1));

                // Now insert or update the document
                if ($doc) {
                    #$collection->remove(array("name" => $name));
                    $collection->update(array("_id" => $doc['_id']), $ohai, array("upsert" => true));
                } else {
                    $collection->insert($ohai);
                }

                if (array_key_exists($name, $nodesHash)) {
                    unset($nodesHash[$name]);
                }
                continue;
            }

            // process the last check in time
            $ohaiTime = $ohai->automatic->ohai_time;
            $checkIn  = calculateLastCheckInTime($currentTime, $ohaiTime);

            // create a new data object in our ohai data for NeuMatic values
            $ohai->neumatic = (object)array(
                'name'               => $name,
                'chefServerName'     => $chefServer['name'],
                'chefServerEnv'      => $chefServer['env'],
                'chefEntOrg'         => $entOrg,
                'chefVersion'        => $ohai->automatic->chef_packages->chef->version,
                'chefVersionStatus'  => version_compare($ohai->automatic->chef_packages->chef->version, $targetVersion, '>=') ? 'green' : 'red',
                'neuCollectionVersion' => $neuCollectionVer,
                'ohaiTime'           => $ohaiTime,                      // decimal value of oahi time (seconds since epoch)
                'ohaiTimeString'     => date('Y-m-d H:i:s', $ohaiTime), // formated ohai time
                'ohaiTimeDiff'       => $checkIn['ohaiTimeDiff'],       // difference in seconds
                'ohaiTimeDiffString' => $checkIn['ohaiTimeDiffString'], // formatted difference in hours, mins, secs
                'ohaiTimeStatus'     => $checkIn['ohaiTimeStatus']      // time color code
            );

            // look for the node document in the collection
            $cursor = $collection->find(array("name" => $name), array("_id" => 1))->limit(1);

            // Now insert or update the document
            if ($doc = $cursor->getNext()) {
                $ohai->_id = $doc['_id'];
                outlog("Update\n");
                $collection->update(array("_id" => $doc['_id']), $ohai, array("upsert" => true));
            } else {
                outlog("Insert\n");
                $collection->insert($ohai);
            }

            if (array_key_exists($name, $nodesHash)) {
                unset($nodesHash[$name]);
            }
        }
        if ($dh) closedir($dh);
    }

    outlog("\n");

    // now remove all mongo entries no longer found in chef servers
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

    $timeDiff            = $currentTime - $ohaiTime;
    $obj['ohaiTimeDiff'] = $timeDiff;
    if ($timeDiff <= 60 * 60) {
        // Ok - less than an hour
        $obj['ohaiTimeDiffString'] = sprintf("%2d min", floor($timeDiff / 60));
        $obj['ohaiTimeStatus']     = "green";
    } else if ($timeDiff <= 60 * 60 * 24) {
        // warning - less than a day
        $hours                     = $timeDiff / 60 / 60;
        $mins                      = ($hours - floor($hours)) * 60;
        $obj['ohaiTimeDiffString'] = sprintf("%d hours %2d min", floor($hours), floor($mins));
        $obj['ohaiTimeStatus']     = "goldenrod";
    } else {
        // error - more than a day
        $days                      = $timeDiff / 60 / 60 / 24;
        $hours                     = ($days - floor($days)) * 24;
        $mins                      = ($hours - floor($hours)) * 60;
        $obj['ohaiTimeDiffString'] = sprintf("%d days %d hours %2d min", floor($days), floor($hours), floor($mins));
        $obj['ohaiTimeStatus']     = "red";
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
    $opts = getopt('hsrc:');

    // usage if -h
    if ($opts && array_key_exists('h', $opts)) usage();

    // define options
    $options = (object)array(
        "stdOut"   => array_key_exists('s', $opts) ? true : false,
        "forceRun" => array_key_exists('r', $opts) ? true : false,
        "startAt" => array_key_exists('c', $opts) ? $opts['c'] : false,
    );
    return $options;
}

function usage() {
    print "Usage: get_chef_status [-hsr]\n";
    print "\n";
    print "       -h                this help\n";
    print "       -s                outlog to STDOUT in real time\n";
    print "       -r                force run even if runCronJobs is false\n";
    print "       -c <chef_server>  start at this chef server\n";
    exit;
}

function getChefServers() {
    global $config;

    $servers = array_keys($config['chef']);
    $data    = array();
    foreach ($servers as $name) {
        if ($name == 'targetVersion') continue;
        if (!isEnterprise($name)) continue;

        $env = $config['chef'][$name]['env'];
        if ($env == 'default') continue;

        outlog(sprintf("\t%-20s %s\n", "[{$env}]", $name));
        $data[] = array(
            "name" => $name,
            "env"  => $env
        );
    }
    return $data;
}

function isEnterprise($name) {
    return preg_match("/^chef.ops.neustar.biz/", $name);
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

