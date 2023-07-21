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
spl_autoload_register(function ($className)
{
	$className = (string) str_replace('\\', DIRECTORY_SEPARATOR, $className);
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
$opts = getopt('i:c:');
if (!array_key_exists('i', $opts)) {
    throw new ErrorException("Server ID not passed");
} else {
    $serverId = $opts['i'];
}
if (!array_key_exists('c', $opts)) {
    throw new ErrorException("Cobbler server not passed");
} else {
    $cobblerServer = $opts['c'];
}

$config = require_once(__DIR__ . "/../config/module.config.php");

$serverTable = new Neumatic\Model\NMServerTable($config);
$server = $serverTable->getById($serverId);
$chefTable = new Neumatic\Model\NMChefTable($config);
$chefModel = $chefTable->getByServerId($serverId);

try {
    $ssh = new SSH2($server->getCobblerServer());
} catch (\Exception $e) {
    throw new \ErrorException("Connection to cobbler server failed");
}

$authConfig = $config['cobbler'][$server->getCobblerServer()];
try {
    #$ssh->loginWithPassword($authConfig['username'], $authConfig['password']);
    if (!$ssh->loginWithKey($authConfig['username'], $authConfig['publicKeyFile'], $authConfig['privateKeyFile'])) {
        throw new \ErrorException("Login to cobbler server failed");
    }
} catch (\ErrorException $e) {
    throw new \ErrorException("Login to cobbler server failed");
}
$stream = $ssh->getShell(false, 'vt102', Array(), 4096);

#date_default_timezone_set("America/New_York");
date_default_timezone_set("GMT");
$startTime = time();
$startDate = date('Y-m-d H:i:s', $startTime);


function sortTimes($a, $b) {
    return strcmp($a['time'], $b['time']);
}

$timeout = $config['osInstallTimeoutSecs'];
$secs = 0;
$interval = 10;
$prompt = ']# ';

$command = "/root/neumatic.py | grep " . $server->getName();
$buffer = '';
$ssh->waitPrompt('> ', $buffer, 2);

// loop while time < timeout
while($secs < $timeout) {
    print "Secs: {$secs}, Timeout: {$timeout}\n";
    $buffer = '';
    $ssh->writePrompt($command . "\n");
    $ssh->waitPrompt($prompt, $buffer, 2);

    // example output record:
    // 172.30.32.132  |system:stneumatic.va.neustar.com|Sat Jan 18 15:09:29 2014|installing (0m 3s)

    // maybe not reporting in yet
    if ($buffer == "") {
        print "\tServer not found in cobbler log\n";
        continue;
    }

    $lines = explode("\r\n", $buffer);

    $times = array();
    foreach ($lines as $line) {
        chop($line);
        if (preg_match("/\d+\.\d+\.\d+\.\d+/", $line)) {
            $fields = explode('|', $line);
            $times[] = array(
                "time" => strtotime($fields[2] . " GMT"),
                "status" => $fields[3]
            );
        }
    }
    if (count($times) < 1) {
        print "\tInvalid record found. Sleeping for " . $interval . "\n";
        sleep($interval);
        $secs += $interval;
        continue;
    }

    usort($times, 'sortTimes');
    $rec = $times[count($times)-1];

    $logDate = date('Y-m-d H:i:s', $rec['time']);
    $logTime = $rec['time'];
    $status = $rec['status'];

    print "\tDate Start: {$startDate}\n";
    print "\tDate Log:   {$logDate}\n";
    print "\tTime Start: {$startTime}\n";
    print "\tTime Log:   {$logTime}\n";

    // check to see if the VM starting reporting in yet in the Cobbler log. It could be an old record.
    if ($logTime < $startTime) {
        print "\tlogTime < timeStart (Kickstarting...)\n";
        updateStatus('Building', 'Kickstarting...');
        print "\tsleeping for " . $interval . "\n";
        sleep($interval);
        $secs += $interval;
        continue;
    }
    print "\tlog has been updated\n";

    // update the status of the server
    $status = preg_replace('/installing/', 'Installing OS ', $status);
    print "\tstatus = " . $status . "\n";
    updateStatus('Building', $status);

    // if the status value is finished, then update status one more time and we can exit
    if (preg_match("/finished/", $status)) {
        print "\tbuild is finished\n";
        date_default_timezone_set("America/New_York");
        $dateNow = date('Y-m-d H:i:s');
        $server->setDateBuilt($dateNow)->setTimeBuildEnd($dateNow);
        $serverTable->update($server);

        // update server status
        updateStatus('Building', 'Cooking...');

        // update the chef table indicating that we're starting the bootstrap
        $chefModel
            ->setServerId($serverId)
            ->setCookStartTime($dateNow);
        $chefTable->save($chefModel);
        break;
    }

    print "\tsleeping for " . $interval . "\n";
    sleep($interval);
    $secs += $interval;
}
$ssh->closeStream();

if ($secs >= $timeout) {
    // build timed out. update the status
    print "OS install timed out\n";
    updateStatus('Failed', 'OS install timed out');
} else {
    // touch the chef watcher log file and set its perms wide open so it can be deleted later
    $chefLog = "/opt/neumatic/watcher_log/chef_watch.log." . $server->getName();
    touch($chefLog);

    // spawn the chef watch process
    exec("nohup php /opt/neumatic/module/Neumatic/bin/chef_watch.php -i " . $server->getId() . " > " . $chefLog . " 2>&1 &");
    chmod($chefLog, 0666);
}

// delete the cobbler system profile
print "Deleting Cobbler server profile...\n";
curlGet("https://neumatic.ops.neustar.biz/cobbler/deleteSystem/" . $server->getId());


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


function updateStatus($status, $text) {
    global $server, $serverTable;

    $server->setStatus($status)->setStatusText($text);
    $server = $serverTable->update($server);
    return $server;
}
