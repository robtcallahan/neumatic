#!/usr/bin/php
<?php

/**
 * lease_reaper.php
 *
 * This is a cron job script that runs once daily to check on the status of VM leases in NeuMatic
 * It uses the neumatic.lease table to check for leases about to expire and will email notifications
 * to users who have VMs about to expire. It will also reap VMs that have expired 1 day after
 * the number of days left = 0.
 *
 */
include __DIR__ . "/../config/global.php";

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

    /*********************************************************************/
    /******************** Log Files & Headers ****************************/
    /*********************************************************************/

    // general definitions
    $title      = "NeuMatic: VM Lease Reaper";
    $scriptName = $argv[0];
    $now        = date("Y-m-d-H-i");
    $startTime  = time();

    $optsNameWidth    = 25;
    $summaryNameWidth = 30;

    // open the log file; also keep a log string to send in email if exception is thrown
    $logString   = "";
    $logFileName = "lease_reaper";
    $logFile     = "{$config['logDir']}/{$logFileName}.{$now}";
    $logFilePtr  = fopen($logFile, "w");

    $logHeader = "{$title} Log\n" .
        "\n" .
        "Host:       " . gethostname() . "\n" .
        "Script:     " . implode(' ', $argv) . "\n" .
        "Start Time: " . date("Y-m-d H:i:s", $startTime) . "\n" .
        "\n" .
        "Options: \n" .
        sprintf("\t %-{$optsNameWidth}s = %s\n", "Send Mail", $options->sendMail ? "true" : "false") .
        sprintf("\t %-{$optsNameWidth}s = %s\n", "Reap", $options->reap ? "true" : "false") .
        sprintf("\t %-{$optsNameWidth}s = %s\n", "Test", $options->test ? "true" : "false") .
        sprintf("\t %-{$optsNameWidth}s = %s\n", "FQDN", $options->fqdn ? $options->fqdn : "") .
        sprintf("\t %-{$optsNameWidth}s = %s\n", "StdOut", $options->stdOut ? "true" : "false") .
        sprintf("\t %-{$optsNameWidth}s = %s\n", "Force Run", $options->forceRun ? "true" : "false") .
        "\n";
    outlog($logHeader);


    // prune old log files
    outlog("Cleaning up old log files...\n");
    $logFiles        = explode("\n", `ls -t {$config['logDir']}/{$logFileName}.*`);
    $todayMinusPrune = $startTime - (60 * 60 * 24 * $config['pruneAfterDays']);
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

    // init summary stats
    $summary = (object)array(
        "numVMs"     => 0,
        "numAlerts"  => 0,
        "numEMails"  => 0,
        "numExpired" => 0,
        "numReaped"  => 0,
    );

    $neumaticBaseUrl = "https://neumatic.ops.neustar.biz";
    #$neumaticBaseUrl   = "https://stlabvsts01.va.neustar.com";
    $neumaticServerUrl = $neumaticBaseUrl . "/#/server";

    $serverTable = new Neumatic\Model\NMServerTable($config);
    $vmTable     = new Neumatic\Model\NMVMWareTable($config);
    $leaseTable  = new Neumatic\Model\NMLeaseTable($config);
    $userTable   = new Neumatic\Model\NMUserTable($config);

    // one day in seconds
    $oneDayInSeconds = 60 * 60 * 24;

    // current time
    $currentTime = time();

    // get a list of all NeuMatic lab VMs
    outlog("Getting NeuMatic Lab VMs...\n");
    $servers         = $serverTable->getAllLabVMs();
    $numVMs          = count($servers);
    $summary->numVMs = $numVMs;
    outlog("\t" . $numVMs . " Lab VMs found\n");

    // create a hash by username
    $serversHash = array();
    foreach ($servers as $server) {
        if (!array_key_exists($server->getUserCreated(), $serversHash)) {
            $vmHash[$server->getUserCreated()] = array();
        }
        $serversHash[$server->getUserCreated()][] = $server;
    }

    // loop over the users and check for expiring leases
    $num = 0;
    foreach ($serversHash as $user => $serversArray) {
        $num++;

        $sendEmail = false;
        outlog(sprintf("[%3d of %3d] %s\n", $num, $numVMs, $user));

        // initialize an email body for a potential list of servers
        $html = "<html><head>" . getCss() . "</head></body>";
        $html .= "<html><body>
        <h2>NeuMatic VM Lease Expiration Notice</h2>
        <p>
            You are receiving this email because you have one or more NeuMatic lab VMs that
            are about to expire or have already expired and have been reaped. If the VM has
            not been reaped yet, you may be able to extend the lease time for these if there
            are any extensions left. At the end of the lease time, the VM will be reaped and
            returned back to the server pool. This process ensures that VMs are not kept around
            indefinitely.
        </p>
        <br>
        ";
        $html .=
            "<table><tr>
                <th>Server</th>
                <th>Days Left</th>
                <th>Lease Start</th>
                <th>Lease End</th>
                <th>Duration</th>
                <th>Exts Remaining</th>
            <tr>";

        /** @var Neumatic\Model\NMServer $server */
        foreach ($serversArray as $server) {
            if ($options->fqdn && $server->getName() != $options->fqdn) continue;

            outlog(sprintf("\t%-30s %5d  ", $server->getName(), $server->getId()));

            // check for existing lease
            $lease = $leaseTable->getByServerId($server->getId());
            if ($lease->getId()) {
                // get the vm
                $vm = $vmTable->getByServerId($server->getId());

                // Check to see if the lease is about to expire. Notify for anything <= 10 days (arbitrary)
                $daysToLeaseEnd = getDaysToLeaseEnd($lease);
                $leaseStartDate = date('Y-m-d', strtotime($lease->getLeaseStart()));
                $leaseEndDate   = date('Y-m-d', time() + ($daysToLeaseEnd * 60 * 60 * 24));

                outlog(sprintf("%-12s %-12s %4d\n", $leaseStartDate, $leaseEndDate, round($daysToLeaseEnd)));

                $extensionsRemaining = $lease->getNumExtensionsAllowed() - $lease->getNumTimesExtended();

                // send an alert email
                if ($lease->getExpired()) {
                    // lease has expired and will be reaped
                    $sendEmail = true;
                    outlog("\tLease expired. Reaping...\n");

                    // delete the vm
                    outlog("\t\tDeleting VMware VM...");
                    if ($options->reap) {
                        try {
                            $url      = "{$neumaticBaseUrl}/vmware/deleteVM/{$server->getId()}?vSphereSite=" . $vm->getVSphereSite();
                            $response = curlGet($url);
                            $json     = json_decode($response);
                            if (property_exists($json, "success") && !$json->success) {
                                if (property_exists($json, "error")) {
                                    outlog($json->error);
                                    outlog("FAILED\n");
                                } else if (property_exists($json, "message") && $json->message == "VM not found") {
                                    outlog("VM not found\n");
                                }
                            } else {
                                outlog("OK\n");
                            }
                        } catch (Exception $e) {
                            outlog("FAILED\n");
                        }
                    }

                    // delete the cobbler profile
                    outlog("\t\tDeleting Cobbler system profile...");
                    if ($options->reap) {
                        try {
                            $url      = "{$neumaticBaseUrl}/cobbler/deleteSystem/{$server->getId()}";
                            $response = curlGet($url);
                            $json     = json_decode($response);
                            if (property_exists($json, "success") && !$json->success) {
                                outlog("FAILED");
                                if (property_exists($json, "error")) {
                                    outlog($json->error);
                                }
                                outlog("\n");
                            } else {
                                outlog("OK\n");
                            }
                        } catch (Exception $e) {
                            outlog("FAILED\n");
                        }
                    }

                    // delete the ldap entry
                    outlog("\t\tDeleting LDAP entry...");
                    if ($options->reap) {
                        try {
                            $url      = "{$neumaticBaseUrl}/ldap/deleteHost/{$server->getName()}";
                            $response = curlGet($url);
                            $json     = json_decode($response);
                            if (property_exists($json, "success") && !$json->success) {
                                outlog("FAILED");
                                if (property_exists($json, "error")) {
                                    outlog($json->error);
                                }
                                outlog("\n");
                            } else {
                                outlog("OK\n");
                            }
                        } catch (Exception $e) {
                            outlog("FAILED\n");
                        }
                    }

                    // delete the Chef node and client entries
                    outlog("\t\tDeleting Chef node...");
                    if ($options->reap) {
                        try {
                            $url      = "{$neumaticBaseUrl}/chef/deleteNode/{$server->getName()}?chef_server={$server->getChefServer()}";
                            $response = curlGet($url);
                            $json     = json_decode($response);
                            if (property_exists($json, "success") && !$json->success) {
                                outlog("FAILED");
                                if (property_exists($json, "error")) {
                                    outlog($json->error);
                                }
                                outlog("\n");
                            } else {
                                outlog("OK\n");
                            }
                        } catch (Exception $e) {
                            outlog("FAILED\n");
                        }
                    }

                    outlog("\t\tDeleting Chef client...");
                    if ($options->reap) {
                        try {
                            $url      = "{$neumaticBaseUrl}/chef/deleteClient/{$server->getName()}?chef_server={$server->getChefServer()}";
                            $response = curlGet($url);
                            $json     = json_decode($response);
                            if (property_exists($json, "success") && !$json->success) {
                                outlog("FAILED");
                                if (property_exists($json, "error")) {
                                    outlog($json->error);
                                }
                                outlog("\n");
                            } else {
                                outlog("OK\n");
                            }
                        } catch (Exception $e) {
                            outlog("FAILED\n");
                        }
                    }

                    // delete the CMDB entry
                    outlog("\t\tDeleting CMDB CI...");
                    if ($options->reap) {
                        try {
                            $url      = "{$neumaticBaseUrl}/cmdb/deleteServer/{$server->getId()}";
                            $response = curlGet($url);
                            $json     = json_decode($response);
                            if (property_exists($json, "success") && !$json->success) {
                                outlog("FAILED");
                                if (property_exists($json, "error")) {
                                    outlog($json->error);
                                }
                                outlog("\n");
                            } else {
                                outlog("OK\n");
                            }
                        } catch (Exception $e) {
                            outlog("FAILED\n");
                        }
                    }

                    // release server back to pool
                    outlog("\t\tReleasing back to pool...");
                    if ($options->reap) {
                        try {
                            $url      = "{$neumaticBaseUrl}/neumatic/releaseBackToPool/{$server->getId()}";
                            $response = curlGet($url);
                            $json     = json_decode($response);
                            if (property_exists($json, "success") && !$json->success) {
                                outlog("FAILED");
                                if (property_exists($json, "error")) {
                                    outlog($json->error);
                                }
                                outlog("\n");
                            } else {
                                outlog("OK\n");
                            }
                        } catch (Exception $e) {
                            outlog("FAILED\n");
                        }
                    }
                    $summary->numReaped++;
                    $html .=
                        "<tr>
                         <td>{$server->getName()}</td>
                         <td class='red' align='center'>0</td>
                         <td>{$leaseStartDate}</td>
                         <td>{$leaseEndDate}</td>
                         <td align='center'>{$lease->getLeaseDuration()}</td>
                         <td align='center'>Reaped</td>
                         </tr>
                        ";
                } else if ($daysToLeaseEnd <= 0) {
                    // last day of lease. Mark as expired and notify the user
                    $sendEmail = true;
                    $summary->numExpired++;
                    $lease->setExpired(1);
                    $leaseTable->update($lease);
                    $html .=
                        "<tr>
                     <td><a href='{$neumaticServerUrl}/{$server->getId()}'>{$server->getName()}</a></td>
                     <td class='red' align='center'>0</td>
                     <td align='center'>{$leaseStartDate}</td>
                     <td align='center'>{$leaseEndDate}</td>
                     <td align='center'>{$lease->getLeaseDuration()}</td>
                     <td align='center'>0 - will be reaped</td>
                     </tr>
                    ";
                } else if ($daysToLeaseEnd <= $config['vmLease']['yellowAlert']) {
                    $summary->numAlerts++;
                    $sendEmail = true;

                    $color = getLeaseAlertColor($daysToLeaseEnd);
                    $html .=
                        "<tr>
                         <td><a href='{$neumaticServerUrl}/{$server->getId()}'>{$server->getName()}</a></td>
                         <td class='{$color}' align='center'>{$daysToLeaseEnd}</td>
                         <td>{$leaseStartDate}</td>
                         <td>{$leaseEndDate}</td>
                         <td align='center'>{$lease->getLeaseDuration()}</td>
                         <td align='center'>{$extensionsRemaining}</td>
                         </tr>
                        ";
                } else {
                    // no action, but log the output
                }
            } else {
                //
            }
        }
        $html .= "</table><br><br>";
        $html .= "<a href='https://neumatic.ops.neustar.biz'><img src='http://images.dev.tools.ops.neustar.biz/NeuMaticLogo.png' alt='NeuMatic Logo' /></a>";
        $html .= "</body></html>";
        if ($sendEmail) {
            $summary->numEMails++;
            if ($options->sendMail || $options->test) {
                $user = $userTable->getByUserName($user);
                if ($options->test) {
                    outlog("\tSending email to rob.callahan@neustar.biz...\n");
                    sendMail('rob.callahan@neustar.biz', $html);
                } else {
                    outlog("\tSending email to " . $user->getEmail() . "...\n");
                    sendMail($user->getEmail(), $html);
                }
            }
        }
    }

    outlog(generateSummary());
    fclose($logFilePtr);
}

    /*********************************************************************/
    /******************** Exception Catcher ******************************/
    /*********************************************************************/

catch (Exception $e) {
    global $options, $logString, $config, $title;

    $emailTo   = $config['adminEmail'];
    $emailFrom = $config['adminEmail'];
    $emailSubj = "{$title} Error Report";

    $headers = implode("\r\n", array(
        "MIME-Version: 1.0\r\n",
        "Content-type: text/html; charset=us-ascii\r\n",
        "From: {$emailFrom}\r\n",
        "Reply-To: {$emailFrom}\r\n",
        "X-Priority: 1\r\n",
        "X-MSMail-Priority: High\r\n",
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

function getDaysToLeaseEnd(Neumatic\Model\NMLease $lease) {
    $leaseStartTime = strtotime($lease->getLeaseStart());
    $diffInDays = (time() - $leaseStartTime) / 60 / 60 / 24;
    $daysToLeaseEnd = floor($lease->getLeaseDuration() - $diffInDays + ($lease->getExtensionInDays() * $lease->getNumTimesExtended()));
    return $daysToLeaseEnd;
}

function getLeaseAlertColor($daysToLeaseEnd) {
    global $config;

    // set the css to color the alert based on values set in the config file
    if ($daysToLeaseEnd <= $config['vmLease']['redAlert']) {
        return 'red';
    } else if ($daysToLeaseEnd <= $config['vmLease']['yellowAlert']) {
        return 'yellow';
    } else {
        return 'normal';
    }
}

function sendMail($to, $body) {
    $subject = "NeuMatic VM Lease Expiration Notice";

    $headers = implode("\r\n", array(
        "MIME-Version: 1.0",
        "Content-type: text/html; charset=ISO-8859-1",
        "From: NeuMatic <messages-noreply@bounce.neustar.biz>",
        "X-Priority: 1",
        "X-MSMail-Priority: High",
        "X-Mailer: PHP/" . phpversion()
    ));

    mail($to, $subject, $body, $headers);
}

function getCSS() {
    return '
        <style type="text/css">
        body {
          font-family: arial;
          font-size: 11pt;
          color: #296496;
        }
        a { text-decoration: none; }
        h2 {
          font-weight: bold;
          font-size: 14pt;
        }
        h3 {
          font-weight: bold;
          font-size: 9pt;
        }
        h4 {
          font-weight: bold;
          font-size: 9pt;
          margin: 5px 10px;
        }
        p {
          margin-left: 5px;
          width: 550px;
        }
        table {
            width: 98%;
            font-size: 90%;
            margin: 5pt 0pt 10pt 0pt;
            /*border: 1px solid #C6D5E1;*/
        }
        th { color: #296496; }
        td {
            border-bottom: 1px solid #dddddd;
            padding: 1pt;
            vertical-align: bottom;
        }
        .indent { margin-left: 40px; }
        .red { color: red; }
        .yellow { color: #ED9C28; }
        .normal { color: #296496 }
        </style>
        ';
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
    $opts = getopt('hmptsri:');

    // usage if -h
    if ($opts && array_key_exists('h', $opts)) usage();

    // define options
    $options = (object)array(
        "sendMail" => array_key_exists('m', $opts) ? false : true,
        "reap"     => array_key_exists('p', $opts) ? false : true,
        "test"     => array_key_exists('t', $opts) ? true : false,
        "fqdn"     => array_key_exists('i', $opts) ? $opts['i'] : false,
        "stdOut"   => array_key_exists('s', $opts) ? true : false,
        "forceRun" => array_key_exists('r', $opts) ? true : false
    );
    return $options;
}

function usage() {
    print "Usage: get_chef_status [-hmpsr][-i fqdn]\n";
    print "\n";
    print "       -h         this help\n";
    print "       -m         do not send any email\n";
    print "       -p         do not reap but show me what you'll do\n";
    print "       -t         test; send email to Rob Callahan only\n";
    print "       -s         outlog to STDOUT in real time\n";
    print "       -r         force run even if runCronJobs is false\n";
    print "       -i <fqdn>  only process this fqdn\n";
    exit;
}

function generateSummary() {
    global $startTime, $summary, $summaryNameWidth;

    // calc elapsed time
    $endTime       = time();
    $elapsedSecs   = $endTime - $startTime;
    $elapsedFormat = sprintf("%02d:%02d", floor($elapsedSecs / 60), $elapsedSecs % 60);

    return sprintf("\n\nSummary\n%'-60s\n", "") .

    sumOutput("Num VMs", $summary->numVMs) .
    sumOutput("Num Alerts", $summary->numAlerts, $summary->numVMs) .
    sumOutput("Num EMails", $summary->numEMails, $summary->numVMs) .
    sumOutput("Num Expired", $summary->numExpired, $summary->numVMs) .
    sumOutput("Num Reaped", $summary->numReaped, $summary->numVMs) .
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
    fwrite($logFilePtr, $logMsg);
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

