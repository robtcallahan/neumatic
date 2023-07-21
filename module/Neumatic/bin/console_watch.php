<?php

use STS\Util\SSH2;

#use STS\HPSIM\HPSIMMgmtProcessorTable;

$dirName = dirName(__DIR__);
set_include_path(
    implode(':',
            array(
                __DIR__ . "/../src",
                "/opt/sts-lib",
                "/usr/share/pear",
		"/usr/share/php",
                "/usr/share/php/ZF2/library"
            )
    )
);

// register our autoloader that replaces '\' with '/' and '_' with ''
spl_autoload_register(function ($className) {
    $className = (string)str_replace('\\', DIRECTORY_SEPARATOR, $className);
    $className = str_replace('_', DIRECTORY_SEPARATOR, $className);
    require_once($className . ".php");
});

// Require that all errors detected are thrown
set_error_handler(
    create_function(
        '$errLevel, $errString, $errFile, $errLine',
        'throw new ErrorException($errString, 0, $errLevel, $errFile, $errLine);'),
    E_ALL
);

date_default_timezone_set("America/New_York");

// command line opts
$opts = getopt('i:r');
if (!array_key_exists('i', $opts)) {
    throw new ErrorException("Server ID not passed");
} else {
    $serverId = $opts['i'];
}

// config file
$config = require(__DIR__ . "/../config/module.config.php");

// HP license to add if not there
$validLicense = "34QWSN6L7CXWJ6K97YZSLRC9H";

// public DNS server
$dnsServer    = $config['ksNameServer'];

// get the server from the passed id (-i id)
$serverTable = new Neumatic\Model\NMServerTable($config);
$server      = $serverTable->getById($serverId);

$standaloneTable = new Neumatic\Model\NMStandaloneTable($config);
$standalone      = $standaloneTable->getByServerId($server->getId());

$chefTable = new Neumatic\Model\NMChefTable($config);
$chefModel = $chefTable->getByServerId($serverId);

// web server where the kickstart file exists
// get the iso server to copy to which is in the chef struction in config
$isoServer    = $config['chef'][$server->getChefServer()]['isoServer'];
$isoServerUrl = "{$isoServer}/ISOs/{$standalone->getIso()}";
$ksServerUrl  = "http://{$isoServer}";
$ksDir        = "ks";

// kickstart file
$ksFile      = "ks-{$server->getName()}.cfg";

// total console watch timeout when we call it quits
$timeout = 60 * 60; // 60 minutes;
if (strpos($server->getChefServer(), "ap-southeast") !== false) {
    $timeout = 60 * 60 * 1.5;
}
// separate timeout for sending ssh commands
$sshTimeout = 30;


// select the iLO credentials based upon the business service
if ($server->getBusinessServiceName() && stripos($server->getBusinessServiceName(), "ultra") !== false) {
    $username = $config['iLOCredentials']['ultradns']['username'];
    $password = $config['iLOCredentials']['ultradns']['password'];
} else {
    $username = $config['iLOCredentials']['standard']['username'];
    $password = $config['iLOCredentials']['standard']['password'];
}

// log files
$logDir = __DIR__ . "/../../../watcher_log";

// raw output of the iLo
$consoleRawFile = $logDir . "/console_raw.log." . $server->getName();
// processed iLo output; removed escape stsrings
$consoleFile = $logDir . "/console.log." . $server->getName();
// console watcher file
$consoleWatcherFile = $logDir . "/console_watch.log." . $server->getName();

$chefWatcherLogFile = $logDir . "/chef_watch.log." . $server->getName();
$chefWatcherExec    = __DIR__ . "/chef_watch.php";

// delete log files
if (file_exists($consoleRawFile)) {
    unlink($consoleRawFile);
}
if (file_exists($consoleFile)) {
    unlink($consoleFile);
}


$pid  = pcntl_fork();
if ($pid == -1) {
    throw new \ErrorException("Could not fork process");
} else if ($pid) {
    // parent process
    $rawOut = fopen($consoleRawFile, "a");
    chmod($consoleRawFile, 0666);

    $consoleWatcher = fopen($consoleWatcherFile, "a");
    chmod($consoleWatcherFile, 0666);

    $prompt = 'hpiLO-> ';

    try {
        outlog("Connecting to " . $standalone->getILo() . "\n");
        $ssh = new SSH2($standalone->getILo());
    } catch (\Exception $e) {
        outlog("Connection Failed\n");
        returnError();
    }

    $ok = 1;
    try {
        outlog("Logging in\n");
        $ok = $ssh->loginWithPassword($username, $password);
    } catch (ErrorException $e) {
        outlog("Login Failed: " . $e->getMessage() . "\n");
        returnError();
    }
    if (!$ok) {
        outlog("Login Failed: {$ok}\n");
        returnError();
    }

    outlog("Getting shell\n");
    $stream = $ssh->getShell(false, 'vt102', Array(), 80, 24);
    if (!$stream) {
        outlog("Obtaining shell failed\n");
        returnError();
    }

    // set the socket timeout
    stream_set_timeout($stream, $sshTimeout);

    outlog("Waiting for prompt\n");
    $buffer = '';
    $ok     = $ssh->waitPrompt($prompt, $buffer, $sshTimeout);
    if (!$ok) {
        outlog("Getting iLO prompt failed: {$ok}\n");
        returnError();
    }
    fwrite($rawOut, $buffer);

    outlog("Insuring that we have a valid license\n");
    $buffer = '';
    $ssh->writePrompt("cd /map1\r\n", false);
    $ok = $ssh->waitPrompt($prompt, $buffer, $sshTimeout);
    if (!$ok) {
        outlog("cd /map1 command failed: {$ok}\n");
        returnError();
    }
    fwrite($rawOut, $buffer);

    $buffer = '';
    $ssh->writePrompt("show\r\n", false);
    $ok = $ssh->waitPrompt($prompt, $buffer, $sshTimeout);
    if (!$ok) {
        outlog("show command failed: {$ok}\n");
        returnError();
    }
    fwrite($rawOut, $buffer);

    if (preg_match("/license=([A-Z0-9]+)/", $buffer, $m)) {
        $license = $m[1];
    } else {
        $license = "";
    }
    outlog("license = {$license}\n");

    if ($license != $validLicense) {
        outlog("setting license\n");
        $buffer = '';
        $ssh->writePrompt("set license={$validLicense}\r\n", false);
        $ok = $ssh->waitPrompt($prompt, $buffer, $sshTimeout);
        if (!$ok) {
            outlog("set license={$validLicense} command failed: {$ok}\n");
            returnError();
        }
        fwrite($rawOut, $buffer);
    }


    outlog("sending textcons\n");
    $ssh->writePrompt("textcons\r\n", false);

    $resetting  = true;
    $posting    = false;
    $booting    = false;
    $installing = false;

    $timeStart = time();
    $secs      = 0;
    $interval  = 2;

    stream_set_blocking($stream, false);

    // defining a list of search strings to match and their associated build status values for NeuMatic to display
    $searchStrings = array(
        array(
            "string" => "Early system initialization",
            "status" => "Early system initialization..."
        ),
        /*
        array(
            "string" => "90%",
            "status" => "BIOS boot..."
        ),
        */
        array(
            "string" => "boot: ",
            "status" => "Kickstarting using {$isoServerUrl}..."
        ),
        array(
            "string" => "Loading initrd.img",
            "status" => "Loading initrd.img..."
        ),
        array(
            "string" => "anaconda installer init",
            "status" => "Anaconda installer init..."
        ),
        array(
            "string" => "Waiting for NetworkManager to configure (eth\d)",
            "status" => "Waiting for NetworkManager to configure M1..."
        ),
        array(
            "string" => "Retrieving \/install.img",
            "status" => "Retrieving /install.img..."
        ),
        array(
            "string" => "Running anaconda",
            "status" => "Running anaconda..."
        ),
        array(
            "string" => "Formatting",
            "status" => "Creating and formatting filesystems..."
        ),
        array(
            "string" => "Dependency Check",
            "status" => "Package dependency check..."
        ),
        array(
            "string" => "Installation Starting",
            "status" => "Starting installation process..."
        ),
        array(
            "string" => "Package Installation",
            "status" => "Installing packages..."
        ),
        array(
            "string" => "Packages completed:\s+(\d+)\s+of\s+(\d+)",
            "status" => "Packages completed: M1 of M2",
            "repeat" => true,
            "until"  => "Installing bootloader|terminating|termination"
        ),
        array(
            "string" => "terminating anaconda|sending kill signals",
            "status" => "OS install complete; rebooting system...."
        ),
        array(
            "string" => "Early system initialization",
            "status" => "Post Install: Early system initialization..."
        ),
        /*
        array(
            "string" => "90%",
            "status" => "Post Install: BIOS boot..."
        ),
        */
        array(
            "string" => "CentOS",
            "status" => "Post Install: System starting..."
        ),
        array(
            "string" => "Starting Chef Client",
            "status" => "Starting Chef Client...",
            "action" => "mark as built"
        ),
        array(
            "string" => "Synchronizing Cookbooks",
            "status" => "Synchronizing Cookbooks..."
        ),
        array(
            "string" => "Compiling Cookbooks",
            "status" => "Compiling Cookbooks..."
        ),
        array(
            "string" => "Recipe",
            "status" => "Installing Cookbooks..."
        ),
        array(
            "string" => "Success",
            "status" => "Built..."
        ),
    );

    $step = 0;
    $steps = count($searchStrings);
    $kickstartDownloadError = 0;

    $server = updateStatus("Building", "Waiting for server to come up...", $step+1, $steps);

    /**
     * This first loop will wait for the 'boot:' prompt and then pass the kickstart string"
     */
    $streamData = "";
    while (true) {
        outlog("Reading stream: step " . $step . " - " . $server->getStatusText() . "\n");

        // get the stream output, if error or no data just sleep and continue
        try {
            $newData = $ssh->getAllStreamOutput();
        } catch (\ErrorException $e) {
            sleep($interval);
            $secs += $interval;
            debug("No data");
            continue;
        }

        if (!$newData) {
            sleep($interval);
            $secs += $interval;
            debug("No data");
            continue;
        }

        debug(sprintf("%-11s %6d", "streamData:", strlen($streamData)));
        debug(sprintf("%-11s %6d", "read:", strlen($newData)));

        // write the stream data to the raw output file
        fwrite($rawOut, $newData);

        // concat what might remain in streamData with the new data;
        $streamData .= $newData;
        debug(sprintf("%-11s %6d", "streamData:", strlen($streamData)));

        // process the search string structure
        $moreData = true;
        while ($moreData) {
            debug("while moreData = true");

            if (preg_match("/" . $searchStrings[$step]['string'] . "/", $streamData, $m, PREG_OFFSET_CAPTURE)) {
                debug("match found");
                if (is_array($m) && count($m) > 1) {
                    $status = preg_replace("/M1/", $m[1][0], $searchStrings[$step]['status']);
                    if (count($m) > 2) {
                        $status = preg_replace("/M2/", $m[2][0], $status);
                    }
                } else {
                    $status = $searchStrings[$step]['status'];
                }
                $stringMatchIndex = $m[0][1];
                $stringMatchLength = strlen($m[0][0]);

                outlog("Step {$step}: " . "Matched {$searchStrings[$step]['string']}; setting status = {$status}\n");

                if (array_key_exists("action", $searchStrings[$step]) && $searchStrings[$step]['action'] == "mark as built") {
                    $dateNow = date('Y-m-d h:i:s');
                    $server
                        ->setTimeBuildEnd($dateNow)
                        ->setDateBuilt($dateNow);
                    $serverTable->update($server);
                    $chefModel
                        ->setServerId($serverId)
                        ->setCookStartTime($dateNow);
                    $chefTable->save($chefModel);
                }
                $server = updateStatus("Building", $status, $step+1, $steps);

                // we matched the "boot:" prompt. Send the kickstart string
                if ($searchStrings[$step]['string'] == "boot: ") {
                    $ksCommand = "linux " .
                        "ks={$ksServerUrl}/{$ksDir}/{$ksFile} " .
                        "ksdevice=eth0 " .
                        "ip={$server->getIpAddress()} " .
                        "netmask={$server->getSubnetMask()} " .
                        "gateway={$server->getGateway()} " .
                        "dns={$dnsServer} " .
                        "loglevel=debug " .
                        "text\r\n";
                        #"text nousb noparport noipv6 nofirewire\r\n";

                    // ok, this is really weird, but the only way I could figure out how to get the ks string
                    // to the boot: prompt was to send one character at a time with 0.2 seconds between each.
                    // No idea why this works, but it does.
                    sleep(1);
                    $chars = str_split($ksCommand);
                    foreach ($chars as $c) {
                        $ssh->write($c);
                        usleep(200000);
                        try {
                            $newData = $ssh->getAllStreamOutput();
                        } catch (\ErrorException $e) {
                        }
                        fwrite($rawOut, $newData);
                    }
                    try {
                        $newData = $ssh->getAllStreamOutput();
                    } catch (\ErrorException $e) {
                        sleep($interval);
                        $secs += $interval;
                        break;
                    }
                    fwrite($rawOut, $newData);
                    // we're done with this loop, move on to the build status loop
                }

                // if we specified repeat, eg, for progress of package install, then don't increment step
                if (!array_key_exists("repeat", $searchStrings[$step]) || !$searchStrings[$step]['repeat']) {
                    debug("repeat = false; incrementing step");
                    $step += 1;
                }

                // remove characters from the beginning of the string to the end of the matched string in case
                // there are more matches to be found in streamData
                $prevLen = strlen($streamData);
                $streamData = substr($streamData, $stringMatchIndex + $stringMatchLength);
                $newLen = strlen($streamData);
                debug("substr streamData: " . $prevLen . " - " . ($stringMatchIndex + $stringMatchLength) . " = " . $newLen);
            }
            else if (array_key_exists("repeat", $searchStrings[$step]) && $searchStrings[$step]['repeat'] && array_key_exists("until", $searchStrings[$step])) {
                debug("repeat = true until '" . $searchStrings[$step]['until'] . "'");

                if (preg_match("/" . $searchStrings[$step]['until'] . "/", $streamData, $m, PREG_OFFSET_CAPTURE)) {
                    $stringMatchIndex = $m[0][1];
                    $stringMatchLength = strlen($m[0][0]);

                    debug("'until' string found; incrementing step");
                    $prevLen = strlen($streamData);
                    $streamData = substr($streamData, $stringMatchIndex + $stringMatchLength);
                    $newLen = strlen($streamData);
                    debug("substr streamData: " . $prevLen . " - " . ($stringMatchIndex + $stringMatchLength) . " = " . $newLen);
                    $step += 1;
                } else {
                    debug("'until' string not found; moreData = false");
                    $moreData = false;
                }
            }
            else {
                debug("no match; moreData = false");
                $moreData = false;
            }
        }

        // os install is complete when we match login
        if (preg_match("/Chef Client failed/", $streamData)) {
            outlog("Step {$step}: " . "Matched 'Chef Client failed'; exiting\n");
            returnError("Failed", "Chef Client failed");
        }

        // os install is complete when we match login
        if (preg_match("/[Ll]ogin:/", $streamData)) {
            outlog("Step {$step}: " . "Matched 'login:'; marking as built\n");
            $step++;
            break;
        }

        // check to be sure we were able to connect to the console using 'textcons'
        if (strpos($streamData, "TEXTCONS is already in use by a different client") !== false) {
            outlog("Matched TEXTCONS already in use; stopping console log\n");
            returnError("Failed", "Textcons in use");
        }

        // if we get an unsupported monitor message, send and ESC character to get back to text
        $searchString = "Monitor is in graphics mode or an unsupported text mode";
        if (strpos($streamData, $searchString) !== false) {
            outlog("Step {$step}: Matched supported text mode; sending ESC character\n");
            // Hex 1B is the ESC character
            $ssh->write("\x1b");
            $ssh->write("\n");
            $streamDataLength = strlen($streamData);
            $streamData = substr($streamData, strpos($streamData, $searchString) + strlen($searchString));
            debug("streamData length: " . $streamDataLength . " => " . strlen($streamData) . "");
            continue;
        }

        $downloadKickstartString = "Unable to download the kickstart file";
        if (strpos($streamData, $downloadKickstartString) !== false
            && $kickstartDownloadError < 5) {
            //
            outlog("Step {$step}: Matched 'Unable to download the kickstart file'; sending Tab/Space\n");
            sleep(1);
            $ssh->write("\x9");
            sleep(1);
            $ssh->write("\x20");
            $streamDataLength = strlen($streamData);
            $streamData = substr($streamData, strpos($streamData, $downloadKickstartString) + strlen($downloadKickstartString));
            debug("streamData length: " . $streamDataLength . " => " . strlen($streamData) . "");
            $kickstartDownloadError++;
            continue;
        }

        if (strpos($streamData, "Could not find kernel image: linux") !== false) {
            fwrite($rawOut, $streamData);
            outlog("Kickstart could not find kernel image: linux; stopping console log\n");
            returnError("Failed", "Unable to boot image", $step+1, $steps);
        }

        // check for error string
        if (preg_match("/Error/", $streamData)
            && strpos($streamData, "Error Record Serialization") === false
            && strpos($streamData, "Error log file") === false) {
            // try to get the error string
            if (preg_match("/Error ([\w\d\s]+)/", $streamData, $m)) {
                $errorString = "Error " . $m[1];
            } else {
                $errorString = "Error";
            }
            fwrite($rawOut, $streamData);
            outlog("Error string was detected: {$errorString}\n");
            outlog("Stopping console log\n");
            outlog("Current stream data:\n");
            outlog($streamData);
            returnError("Failed", "Error detected");
        }

        // test to see if we've waited long enough for a response and exit if so
        if ($secs > $timeout) {
            fwrite($rawOut, $streamData);
            outlog("Timed out: {$secs} > {$timeout}; stopping console log\n");
            returnError("Failed", "Timed out", $step+1, $steps);
        }
    }

    // all done. system is built so close up shop
    outlog("Closing stream\n");
    fclose($consoleWatcher);

    $ssh->closeStream();
    fclose($rawOut);

    // update the build time
    $dateNow = date('Y-m-d h:i:s');
    $chefModel
        ->setServerId($serverId)
        ->setCookEndTime($dateNow);
    $chefTable->save($chefModel);
    $server = updateStatus("Built", "Built", $step+1, $steps);

    //remove the raw output and console log files
    unlink($consoleRawFile);
    unlink($consoleFile);
    unlink($consoleWatcherFile);

    // touch the chef watcher log file and set its perms wide open so it can be deleted later
    #touch($chefWatcherLogFile);

    // spawn the chef watch process
    #exec("nohup php " . $chefWatcherExec . " -i " . $server->getId() . " > " . $chefWatcherLogFile . " 2>&1 &");
    #chmod($chefWatcherLogFile, 0666);

    exec("kill {$pid}");

    exit;
}
else {
    // child process
    $rawOut  = fopen($consoleRawFile, "r");
    chmod($consoleRawFile, 0666);
    $filePtr = 0;

    /**
     * Read from the console output log file, one character at a time, checking for escape sequences and replacing
     * each with appropriate HTML elements. CSI reference found here:
     *      http://en.wikipedia.org/wiki/ANSI_escape_code#CSI_codes
     **/
    while (true) {
        #print "read log\n";
        $buffer = "";
        #if ($firstTime) $buffer = "<span>";

        fseek($rawOut, $filePtr);

        $processedOut = fopen($consoleFile, "a");
        chmod($consoleFile, 0666);

        $lastCursor = "";
        while (!feof($rawOut)) {
            #print "\touter while\n";
            $c   = fgetc($rawOut);
            $ord = ord($c);

            // check if escape character (ascii 27)
            if ($ord == 27) {
                $esc = "";
                while (!feof($rawOut)) {
                    #print "\t\tinner while\n";
                    $n = fgetc($rawOut);
                    $esc .= $n;

                    if ($n == 'm' || $n == 'H' || $n == 'J') {
                        if ($esc == $lastCursor) break;
                        if (preg_match("/\d+;\d+;(\d+);(\d+)m/", $esc, $m)) {
                            if ($m[1] == 37 && $m[2] == 44) {
                                $buffer .= "</span><span class='sp-blue'>";
                            } else if ($m[1] == 36 && $m[2] == 40) {
                                $buffer .= "</span><span class='sp-cyan'>";
                            } else {
                                $buffer .= "</span><span class='color-" . $m[1] . " color-" . $m[2] . "'>";
                            }
                        } else if (preg_match("/(\d+)m/", $esc, $m)) {
                            switch ($m[1]) {
                                case 0:
                                    // reset
                                    $buffer .= "</span><span class='color-30 color-40'>";
                                    break;
                                case 1:
                                    // bold
                                    $buffer .= "</span><span class='bold'>";
                                    break;
                                default:
                                    break;
                            }
                        } else if (preg_match("/(\d+)J/", $esc, $m)) {
                            /*
                             * CSI n J	ED – Erase Display	Clears part of the screen.
                             * If n is zero (or missing), clear from cursor to end of screen.
                             * If n is one, clear from cursor to beginning of the screen.
                             * If n is two, clear entire screen (and moves cursor to upper left on DOS ANSI.SYS).
                             *
                             * We'll just add a line break as we don't want to clear the screen
                             */
                            $buffer .= "<br>\n";
                        } else if (preg_match("/(\d+);(\d+)H/", $esc, $m)) {
                            if ($m[2] == 1) {
                                $buffer .= "<br>\n";
                            }
                        } else {
                            $buffer .= $esc;
                        }
                        $lastCursor = $esc;
                        break;
                    }
                }
            } else if ($c == '<') {
                $buffer .= '&lt;';
            } else if ($c == '>') {
                $buffer .= '&gt;';
            } else if ($ord == 10) {
                // ascii character 10 is line feed (\n)
                $buffer .= "<br>\n";
            } else if ($c == 'Ê' || $c == 'Æ' || $c == 'Ç' || $c == 'Ì') {
                // TODO: these characters are not matching
                $buffer .= '-';
            } else {
                $buffer .= $c;
            }
        }
        $filePtr = ftell($rawOut);

        fwrite($processedOut, utf8_encode($buffer));
        fclose($processedOut);
        sleep(5);
    }


}

function outlog($string) {
    global $consoleWatcher;
    fwrite($consoleWatcher, "[" . (date("Y-m-d h:i:s")) . "] " . $string);
}

function debug($string) {
    global $consoleWatcher;
    fwrite($consoleWatcher, sprintf("%22s%s\n", " ", $string));
}

function curlGetUrl($url, $username, $password) {
    $curl = curl_init($url);

    curl_setopt($curl, CURLOPT_HTTPAUTH, CURLAUTH_BASIC);
    curl_setopt($curl, CURLOPT_USERPWD, "{$username}:{$password}");
    //curl_setopt($curl, CURLOPT_VERBOSE, true);
    curl_setopt($curl, CURLOPT_HEADER, false);
    curl_setopt($curl, CURLOPT_SSL_VERIFYPEER, false);
    curl_setopt($curl, CURLOPT_SSL_VERIFYHOST, false);
    curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);

    $response = curl_exec($curl);

    curl_close($curl);
    return $response;
}

function updateStatus($status, $text, $step, $steps) {
    global $server, $serverTable;

    $server
        ->setStatus($status)
        ->setStatusText($text)
        ->setBuildStep($step)
        ->setBuildSteps($steps);
    $server = $serverTable->update($server);
    return $server;
}

function returnError($status="Failed", $statusText="Failed") {
    global $ssh, $pid, $server, $rawOut, $consoleWatcher, $consoleRawFile, $consoleFile, $step, $steps;

    $ssh->closeStream();
    fclose($rawOut);

    outlog("Error occurred, Exiting...");
    fclose($consoleWatcher);

    //remove the raw output and console log files
    #unlink($consoleRawFile);
    unlink($consoleFile);

    exec("kill {$pid}");

    $dateNow = date('Y-m-d h:i:s');
    $server->setTimeBuildEnd($dateNow)
           ->setDateBuilt($dateNow);
    $server = updateStatus($status, $statusText, $step+1, $steps);
    exit;
}
