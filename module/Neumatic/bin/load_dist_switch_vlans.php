#!/usr/bin/php
<?php

include __DIR__ . "/../config/global.php";
use STS\Util\SSH2;

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
    $title      = "NeuMatic: Load Dist Switch VLANs";
    $scriptName = $argv[0];
    $now        = date("Y-m-d-H-i");
    $startTime  = time();

    $optsNameWidth    = 25;
    $summaryNameWidth = 30;

    // open the log file; also keep a log string to send in email if exception is thrown
    $logString   = "";
    $logFileName = "load_dist_switch_vlans";
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
        sprintf("\t %-{$optsNameWidth}s = %s\n", "Use Local Files", $options->useLocal ? "true" : "false") .
        sprintf("\t %-{$optsNameWidth}s = %s\n", "Debug Level", $options->debug ? $options->debug : 0) .
        "\n";
    outlog($logHeader);


    // prune old log files
    outlog("Cleaning up old log files...\n");
    $logFiles        = explode("\n", `ls -t {$config['logDir']}/{$logFileName}.*`);
    $todayMinusPrune = $startTime - (60 * 60 * $config['pruneAfterHours']);
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
        "numConfigs" => 0,
    );

    // current time
    $currentTime = time();

    $networkHost    = "stnetcprveng01.va.neustar.com";
    $configFilePath = "/usr/local/rancid/var/Neustar/configs";
    $dataDir        = "/opt/neumatic/data";
    $thisHost       = rtrim(`hostname`);

    $dsTable  = new Neumatic\Model\NMDistSwitchTable($config);
    $ipv4      = new Net_IPv4();
    $vlanTable = new Neumatic\Model\NMVLANTable($config);
    $dhcpTable = new Neumatic\Model\NMVLANDHCPRelayTable($config);


    outlog("Getting a list of distribution switches...\n");
    $distSwitches = $dsTable->getAllEnabled();
    outlog("\t" . count($distSwitches) . " switches found\n");

    if (!$options->useLocal) {
        outlog("Connecting to Network Team host...\n");
        try {
            $ssh = new SSH2($networkHost);
        } catch (\Exception $e) {
            throw new \ErrorException("Connection to {$networkHost} failed");
        }
        try {
            $ssh->loginWithPassword($config['stsappsUser'], $config['stsappsPassword']);
        } catch (\ErrorException $e) {
            throw new \ErrorException("Login to cobbler server failed");
        }
        $stream = $ssh->getShell(false, 'vt102', Array(), 4096);
        $buffer = '';
        $ssh->waitPrompt(']$ ', $buffer, 2);
        outlog("\tconnected. Sudoing...\n");

        $buffer = '';
        $ssh->writePrompt("sudo bash\n");
        $ssh->waitPrompt(']# ', $buffer, 2);

        $buffer = '';
        $ssh->writePrompt("cd {$configFilePath}\n");
        $ssh->waitPrompt(']# ', $buffer, 2);

        outlog("Copying switch config dump files...\n");
        foreach ($distSwitches as $s) {
            $file = $s->getModel();
            outlog("\t{$file}...\n");

            $scpCmd = "scp " . $file . " " . $config['stsappsUser'] . "@" . $thisHost . ":" . $dataDir;

            $buffer = '';
            $ssh->writePrompt("{$scpCmd}\n");
            $ssh->waitPrompt('password: ', $buffer, 2);

            $ssh->writePrompt("{$config['stsappsPassword']}\n");
            $ssh->waitPrompt(']# ', $buffer, 2);
        }
        $ssh->closeStream();
        outlog("Copy complete\n");
    }

    $switchNum = 0;

    outlog("Parsing switch configs...\n");
    foreach ($distSwitches as $distSwitch) {
        $vlans = array();
        $switchNum++;

        if ($distSwitch->getModel() != 'devxlansw1') continue;

        $swName = $distSwitch->getModel();
        outlog("    {$swName}...\n");

        $swFile = $dataDir . "/" . $swName;

        $contents = file_get_contents($swFile);
        $recs     = explode("\n", $contents);

        $vlans = array();

        for ($i = 0; $i < count($recs); $i++) {
            $r = $recs[$i];
            if ($options->debug == 3) outlog("REC(164)={$r}\n");

            /**
             * vlan 53
             *   name Nex_Gen_Console
             */
            if (preg_match("/^vlan (\d+)/", $r, $m)) {
                $id = $m[1];
                if ($options->debug >= 2) outlog("vlan ## - id={$id} ");

                if (!array_key_exists($id, $vlans)) {
                    $vlans[$id] = (object)array(
                        "distSwitch" => $distSwitch,
                        "id"        => $id,
                        "name"      => "",
                        "cidr"      => "",
                        "ip"        => "",
                        "network"   => "",
                        "netmask"   => "",
                        "gateway"   => "",
                        "dhcpRelay" => array()
                    );
                }

                $m1 = $m2 = array();
                $i++;
                $r = $recs[$i];
                if (preg_match("/\s+(name)\s(.*)/", $r, $m)) {
                    $vlans[$id]->name = $m[2];
                    if ($options->debug >= 2) outlog("name={$vlans[$id]->name}\n");
                }
                $i--;
                continue;
                /*
                if ($options->debug == 3) outlog("\nREC(189)={$r}\n");
                while (!preg_match("/\s+(name)\s(.*)/", $r, $m1) && !preg_match("/^vlan (\d+)/", $r, $m2)) {
                    $i++;
                    $r = $recs[$i];
                    if ($options->debug == 3) outlog("\nREC(193)={$r}\n");
                }

                if (count($m1) == 3) {
                    $vlans[$id]->name = $m1[2];
                    if ($options->debug >= 2) outlog("name={$m1[2]}\n");
                } else {
                    $i--;
                    if ($options->debug >= 2) outlog("\n");
                }
                */
            }

            /**
             * interface Vlan53
             *   ip address 10.31.248.2/24
             *   ip ospf passive-interface
             *   ip router ospf 10 area 0.0.0.3
             *   hsrp 1
             *     preempt
             *     priority 120
             *     ip 10.31.248.1
             *   ip dhcp relay address 10.31.45.61
             *   no shutdown
             * OR
             * interface Vlan81
             *  description shared_vlan
             *  ip address 10.32.81.2 255.255.255.0
             *  ip helper-address 10.31.45.61
             *  no ip redirects
             *  no ip proxy-arp
             *  no ip mroute-cache
             *  standby 81 ip 10.32.81.1
             *  standby 81 priority 90
             *  standby 81 preempt
             *
             */
            if (preg_match("/interface Vlan(\d+)/", $r, $m)) {
                $id = $m[1];
                if ($options->debug >= 2) outlog("interace Vlan## - id={$id} ");

                if (!array_key_exists($id, $vlans)) {
                    $vlans[$id] = (object)array(
                        "distSwitch" => $distSwitch,
                        "id"        => $id,
                        "name"      => "",
                        "cidr"      => "",
                        "ip"        => "",
                        "network"   => "",
                        "netmask"   => "",
                        "gateway"   => "",
                        "dhcpRelay" => array()
                    );
                }

                // read until "ip address"
                $i++;
                $r = $recs[$i];
                if ($options->debug == 3) outlog("\nREC=(250){$r}\n");

                while (!preg_match("/^interface Vlan(\d+)/", $r, $m) && $i+2 <= count($recs)) {
                    if (preg_match("/\s+ip address (\d+\.\d+\.\d+\.\d+)\/(\d+)/", $r, $m)) {
                        $vlans[$id]->cidr = $m[1] . "/" . $m[2];
                        if ($options->debug >= 2) outlog("cidr={$vlans[$id]->cidr} ");
                    }

                    else if (preg_match("/\s+ip address (\d+\.\d+\.\d+\.\d+) (\d+\.\d+\.\d+\.\d+)/", $r, $m)) {
                        $vlans[$id]->ip = $m[1];
                        $vlans[$id]->netmask = $m[2];
                        if ($options->debug >= 2) outlog("ip={$vlans[$id]->ip} ");
                        if ($options->debug >= 2) outlog("netmask={$vlans[$id]->netmask} ");
                    }

                    else if (preg_match("/ip (\d+\.\d+\.\d+\.\d+)/", $r, $m)) {
                        $vlans[$id]->gateway = $m[1];
                        if ($options->debug >= 2) outlog("gateway={$vlans[$id]->gateway} ");
                    }

                    else if (preg_match("/ip dhcp relay address (\d+\.\d+\.\d+\.\d+)/", $r, $m)) {
                        $vlans[$id]->dhcpRelay[] = $m[1];
                        if ($options->debug >= 2) outlog("dhcpRelay={$m[1]} ");
                    }

                    $i++;
                    $r = $recs[$i];
                    if ($options->debug == 3) outlog("\nREC=(255){$r}\n");
                }
                if ($options->debug >= 2) outlog("\n");
                $i--;
            }
        }

        foreach ($vlans as $v) {
            $ok = false;
            // get the pieces and parts of the CIDR network
            if ($v->cidr) {
                if ($net = $ipv4->parseAddress($v->cidr)) {
                    $v->network = $net->network;
                    $v->netmask = $net->netmask;
                    $ok = true;
                }
            } else if ($v->netmask && $v->ip) {
                $ipv4->ip = $v->ip;
                $ipv4->netmask = $v->netmask;
                $error = $ipv4->calculate();
                if (!is_object($error)) {
                    $v->network = $ipv4->network;
                    $ok = true;
                }
            }

            if ($ok) {
                $vlan = $vlanTable->getByDistSwitchIdsAndVlanId($v->distSwitch->getId(), $v->id);
                if ($vlan->getId()) {
                    // TODO: don't want to overwrite data that's been updated manually such as gateway
                    outlog("\tUPDATE: ");
                    $vlan
                        ->setDistSwitchId($v->distSwitch->getId())
                        ->setName($v->name)
                        ->setNetwork($v->network)
                        ->setNetmask($v->netmask)
                        ->setGateway($v->gateway);
                    $vlanTable->update($vlan);
                } else {
                    outlog("\tCREATE: ");
                    $vlan
                        ->setDistSwitchId($v->distSwitch->getId())
                        ->setVlanId($v->id)
                        ->setName($v->name)
                        ->setNetwork($v->network)
                        ->setNetmask($v->netmask)
                        ->setGateway($v->gateway)
                        ->setEnabled(1);
                    $vlanTable->create($vlan);
                }
                outlog(sprintf("ID=%-5s NET=%-20s MASK=%-20s GW=%-20s NAME=%-s\n",
                       $v->id, $v->network, $v->netmask, $v->gateway, $v->name));
                /*
                foreach ($v->dhcpRelay as $d) {
                    outlog("\tDHCP RELAY=" . $d . "\n");
                }
                */
            }
        }
        outlog("\t" . count($vlans) . " VLANs parsed\n");
    }
}

/*********************************************************************/
/******************** Exception Catcher ******************************/
/*********************************************************************/
catch (\Exception $e) {
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

function sendMail($emailBody) {
    global $config, $title;

    $emailTo   = $config['adminEmail'];
    $emailFrom = $config['adminEmail'];
    $emailSubj = "{$title} Notification";

    $headers = implode("\r\n", array(
        "MIME-Version: 1.0\r\n",
        "Content-type: text/html; charset=us-ascii\r\n",
        "From: {$emailFrom}\r\n",
        "Reply-To: {$emailFrom}\r\n",
        "X-Priority: 1\r\n",
        "X-MSMail-Priority: High\r\n",
        "X-Mailer: PHP/" . phpversion()
    ));

    mail($emailTo, $emailSubj, $emailBody, $headers);
}


function parseOptions() {
    // command line opts
    $opts = getopt('hsrld:');

    // usage if -h
    if ($opts && array_key_exists('h', $opts)) usage();

    // define options
    $options = (object)array(
        "stdOut"   => array_key_exists('s', $opts) ? true : false,
        "forceRun" => array_key_exists('r', $opts) ? true : false,
        "useLocal" => array_key_exists('l', $opts) ? true : false,
        "debug"    => array_key_exists('d', $opts) ? $opts['d'] : 0
    );
    return $options;
}

function usage() {
    print "Usage: load_dist_switch_vlans [-hsr] [-d level]\n";
    print "\n";
    print "       -h         this help\n";
    print "       -s         outlog to STDOUT in real time\n";
    print "       -r         force run even if runCronJobs is false\n";
    print "       -l         use local files, those previously copied\n";
    print "       -d level   debug level = 0-off, 1-some, 2-more\n";
    exit;
}

function generateSummary() {
    global $startTime, $summary, $summaryNameWidth;

    // calc elapsed time
    $endTime       = time();
    $elapsedSecs   = $endTime - $startTime;
    $elapsedFormat = sprintf("%02d:%02d", floor($elapsedSecs / 60), $elapsedSecs % 60);

    return sprintf("\n\nSummary\n%'-60s\n", "") .

    sumOutput("Num Configs Parsed", $summary->numConfigs) .
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

