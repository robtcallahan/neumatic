<?php

use STS\Util\SSH2;

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

// command line opts
$opts = getopt('i:');
if (!array_key_exists('i', $opts)) {
    throw new ErrorException("Server ID not passed");
} else {
    $serverId = $opts['i'];
}

$config = require_once(__DIR__ . "/../config/module.config.php");

$serverTable = new Neumatic\Model\NMServerTable($config);
$server      = $serverTable->getById($serverId);
$chefTable   = new Neumatic\Model\NMChefTable($config);
$chefModel   = $chefTable->getByServerId($serverId);

$logDir = __DIR__ . "/../../../watcher_log";
$cobblerLog = $logDir . "/cobbler_watch.log." . $server->getName();
$consoleLog = $logDir . "/console_watch.log." . $server->getName();

// delete the cobbler watch log file
print "Removing watcher log files...\n";
if (file_exists($cobblerLog)) {
    unlink($cobblerLog);
}
if (file_exists($consoleLog)) {
    unlink($consoleLog);
}

// init variables
$initialRunLogFile = '/var/log/chef-initial-run.log';
$chefLogFile       = '/var/log/chef/client.log';
$timeStart         = time();
$chefTimeout       = $config['chefClientRunTimeoutSecs'];
if ($server->getServerType() == 'standalone' || $server->getServerType() == 'blade') {
    $connectTimeout = 60 * 6;
} else {
    $connectTimeout = 60 * 2;
}
$logFileTimeout = 240;
$secs           = 0;
$interval       = 10;
$prompt         = ']# ';
$ssh            = "";

// attempt to connect to server
print "Attempting to connect to {$server->getName()}...\n";
while ($secs < $connectTimeout) {
    try {
        $ssh = new SSH2($server->getName());
    } catch (\Exception $e) {
        print "\tConnection sleep for " . $interval . "\n";
        sleep($interval);
        $secs += $interval;
        continue;
    }
    break;
}
if (!$ssh) {
    // connection failed after $connectTimeout seconds of trying
    print "Connection to server {$server->getName()} failed\n";
    updateStatus('Built', 'Chef run indeterminate');
    exit;
}

// login as root
print "Logging in...\n";
$secs = 0;
while ($secs < $connectTimeout) {
    try {
        $result = $ssh->loginWithPassword('root', $config['rootPassword']);
    } catch (\ErrorException $e) {
        print "\tLogin sleep for " . $interval . "\n";
        sleep($interval);
        $secs += $interval;
    }
    break;
}
if (!$result) {
    print "Login to server {$server->getName()} server failed\n";
    updateStatus('Built', 'Chef run indeterminate');
    exit;
}

$stream = $ssh->getShell(false, 'vt102', Array(), 4096);
$buffer = '';
$ssh->waitPrompt($prompt, $buffer, 2);

// wait for the existence of /var/log/chef-initial-run.log
print "Waiting for {$initialRunLogFile}...\n";
$secs = 0;
while ($secs < $logFileTimeout) {
    $buffer = '';
    $ssh->writePrompt("ls {$initialRunLogFile}\n");
    $ssh->waitPrompt($prompt, $buffer, 2);

    if (preg_match("/No such file/", $buffer)) {
        print "\tLog file sleep for " . $interval . "\n";
        sleep($interval);
        $secs += $interval;
    } else {
        break;
    }
}
// log file note found. mark chef run as failed
if (preg_match("/No such file/", $buffer)) {
    print "Log file not created. Chef run failed.\n";
    updateStatus('Failed', 'Chef run failed');
    exit;
}

// now we know that chef-client is actually running.
// watch this file for errors
print "Watching Chef log files...\n";
$secs              = 0;
$omnibusChefKiller = false;
while ($secs < $chefTimeout) {
    $buffer = '';
    $ssh->writePrompt("cat {$initialRunLogFile}\n");
    $ssh->waitPrompt($prompt, $buffer, 2);

    if (preg_match("/omnibus chef killer/", $buffer)) {
        print "\tString match for 'omnibus chef killer'\n";
        $omnibusChefKiller = true;

        print "\tNulling {$chefLogFile}...\n";
        $ssh->writePrompt("cat /dev/null > {$chefLogFile}\n");
        $ssh->waitPrompt($prompt, $buffer, 2);

        print "\tMoving to watch {$chefLogFile}\n";
        break;
    }

    if (preg_match("/FATAL/", $buffer) && !$omnibusChefKiller) {
        print "String match for FATAL in {$initialRunLogFile}.\n";
        print "Marking Chef run as failed\n";
        updateStatus('Failed', 'Chef run failed');
        $ssh->closeStream();
        exit;
    }

    $buffer = '';
    $ssh->writePrompt("ls {$chefLogFile}\n");
    $ssh->waitPrompt($prompt, $buffer, 2);

    if (!preg_match("/No such file/", $buffer)) {
        print "\t{$chefLogFile} found.\n";
        print "\tMoving to watch {$chefLogFile}\n";
        break;
    }

    print "\t{$initialRunLogFile} log file sleep for " . $interval . "\n";
    sleep($interval);
    $secs += $interval;
}

while ($secs < $chefTimeout) {
    $buffer = '';
    $ssh->writePrompt("cat {$chefLogFile}\n");
    $ssh->waitPrompt($prompt, $buffer, 2);

    if (preg_match("/FATAL/", $buffer)) {
        print "String match for FATAL in {$chefLogFile}. Marking Chef run as failed\n";
        updateStatus('Failed', 'Chef run failed');
        $ssh->closeStream();
        exit;
    } else if (preg_match("/INFO: Report handlers complete/", $buffer)) {
        print "String match for 'INFO: Report handlers complete' in {$chefLogFile}. Marking Chef run as complete.\n";
        updateStatus('Built', 'Built');
        $ssh->closeStream();

        date_default_timezone_set("America/New_York");
        $dateNow = date('Y-m-d H:i:s');
        $chefModel->setCookEndTime($dateNow);
        $chefTable->update($chefModel);
        exit;
    }
    print "\t{$chefLogFile} log file sleep for " . $interval . "\n";
    sleep($interval);
    $secs += $interval;
}


$ssh->closeStream();
print "Chef run timeout of {$chefTimeout} seconds reached. Marking Chef run as failed.\n";
updateStatus('Failed', 'Chef run timeout');

function updateStatus($status, $text) {
    global $server, $serverTable;

    $server->setStatus($status)->setStatusText($text);
    $server = $serverTable->update($server);
    return $server;
}
