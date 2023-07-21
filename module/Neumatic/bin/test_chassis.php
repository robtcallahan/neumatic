<?php

$dirName = dirName(__DIR__);
set_include_path(
    implode(':',
        array(
            __DIR__ . "/../src",
            __DIR__ . "/../../../vendor/vmwarephp/library",
            "/Users/rcallaha/workspace/sts-lib/trunk",
            "/opt/sts-lib",
            "/usr/share/pear",
	    "/usr/share/php",
            "/usr/share/php/ZF2/library"
        )
    )
);

// Require that all errors detected are thrown
set_error_handler(
    create_function(
        '$errLevel, $errString, $errFile, $errLine',
        'throw new ErrorException($errString, 0, $errLevel, $errFile, $errLine);'),
    E_ALL
);

$config = require(__DIR__ . "/../config/module.config.php");

use STS\Util\SSH2;

$sshTimeout = 30;

try {
    $prompt = '> $';

    $ssh = new SSH2("stnphpbcpr5mm1.va.neustar.com");
    print "logging in ";
    $ok = $ssh->loginWithPassword("Administrator", "0nly4sas");
    print "ok = {$ok}\n";

    print "getting shell ";
    $ok = $ssh->getShell(false, 'vt102', Array(), 4096);
    print "ok = {$ok}\n";

    print "wait for prompt ";
    $buffer = '';
    $ok = $ssh->waitPrompt($prompt, $buffer, $sshTimeout);
    print "ok = {$ok}\n";
    print "buffer = {$buffer}\n";

    $buffer = '';
    print "show server status ";
    $numBytes = $ssh->writePrompt("show server status 1");
    print "numBytes = {$numBytes} ";
    $ok = $ssh->waitPrompt($prompt, $buffer, $sshTimeout);
    print "ok = {$ok}\n";
    print "buffer = {$buffer}\n";

    print "Connecting to blade\n";
    $prompt = "iLO-> $";
    $buffer = '';
    $ssh->writePrompt("connect server 1");
    $ok = $ssh->waitPrompt($prompt, $buffer, $sshTimeout);
    print "ok = {$ok}\n";
    print "buffer = {$buffer}\n";

    print "Insuring that we have a valid license\n";
    $buffer = '';
    $ssh->writePrompt("cd /map1\n\r");
    $ok = $ssh->waitPrompt($prompt, $buffer, $sshTimeout);
    print "ok = {$ok}\n";
    print "buffer = {$buffer}\n";

    $buffer = '';
    $ssh->writePrompt("show\n\r");
    $ok = $ssh->waitPrompt($prompt, $buffer, $sshTimeout);
    print "ok = {$ok}\n";
    print "buffer = {$buffer}\n";

    if (preg_match("/license=([\w\d]+)/", $buffer, $m)) {
        print "license = {$m[1]}\n";
    }
    $ssh->closeStream();

    /*
    $vServerName = 'stlabvcenter02.cis.neustar.com:443';
    $vServer = new \Vmwarephp\Vhost($vServerName, 'neumatic', 't00l3t3@m');

    $service = $vServer->getService();
    $soapClient = $service->getSoapClient();

    #$vm = $vServer->findOneManagedObject('Datacenter', 'datacenter-2', array('name', 'vmFolder'));

    #$folders = $vServer->findOneManagedObject('Folder', 'group-v3', array('name'));
    #foreach ($folders as $f) {
    #    print $f->name . "\n";
    #}
    #exit;

    $vm = $vServer->findManagedObjectByName('VirtualMachine', 'stneumatic.va.neustar.com', array('name', 'runtime', 'guest'));

    #if ($vm) print $vm->getName() . ", " . $vm->runtime->powerState . ", " . $vm->runtime->bootTime . ", " . $vm->guest->guestState . "\n";

    $vServer->destoryTask($vm);
    $vServer->disconnect();
    */
}

catch (SoapFault $e) {
    print "message = " . $e->getMessage() . "\n";
    print "trace = " . $e->getTraceAsString() . "\n";
    print "request = " . $soapClient->__getLastRequest() . "\n";
    file_put_contents($xmlFile, $soapClient->__getLastRequest());
}
