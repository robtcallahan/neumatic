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
$opts = getopt('i:');

if (!array_key_exists('i', $opts)) {
    throw new ErrorException("Server ID not passed");
} else {
    $serverId = $opts['i'];
}


$config = require_once("/var/www/html/neumatic/module/Neumatic/config/module.config.php");

$serverTable = new Neumatic\Model\NMServerTable($config);
$server = $serverTable->getById($serverId);
$chefTable = new Neumatic\Model\NMChefTable($config);
$chefModel = $chefTable->getByServerId($serverId);
$vmwareTable = new Neumatic\Model\NMVMWareTable($config);
$vmwareModel = $vmwareTable->getByServerId($serverId);

$serverName = $server->getName();

$templateName = $vmwareModel->getTemplateName();

$timeout = $config['osInstallTimeoutSecs'];
$secs = 0;
$interval = 10;
$prompt = ']# ';

date_default_timezone_set("GMT");
$startTime = time();
$startDate = date('Y-m-d H:i:s', $startTime);

// loop while time < timeout
while($secs < $timeout) {
     
    if(ping($serverName)){
        
        updateStatus('Building', 'Attempting Connection...');
        break;
        
    }else{
        
        updateStatus('Building', 'Booting VM...');
        sleep($interval);
        $secs += $interval;
    }
}

while($secs < $timeout) {
    try {
        $ssh = new SSH2($serverName);
        if($ssh instanceof SSH2){
            //appears to be ready...
            break;
        }
        
    } catch (\Exception $e) {
        sleep($interval);
        $secs += $interval;
        continue;
    }
    
}
if ($secs >= $timeout) {
    // build timed out. update the status
    print "OS install timed out\n";
    updateStatus('Failed', 'OS install timed out');
    exit;
}

$dateNow = date('Y-m-d H:i:s');
$server->setDateBuilt($dateNow)->setTimeBuildEnd($dateNow);
$serverTable->update($server);


try {
    if (!$ssh->loginWithPassword($config['rootUser'], $config['rootPassword'])){
        throw new \ErrorException("Login to VM failed");
    }
   
} catch (\ErrorException $e) {
    throw new \ErrorException("Login to VM failed");
}


updateStatus('Building', 'Configuring network...');

$chefServer=$server->getChefServer();
$environment = $server->getChefEnv();

$stream = $ssh->getShell(false, 'vt102', Array(), 4096);
$prompt = ']$ ';
$ssh->waitPrompt($prompt, $buffer, 2);

$sftpConnect = "ssh2.sftp://".$config['rootUser'].":".$config['rootPassword']."@".$serverName .":22";

$command = "hostname $serverName";
$buffer = '';
$ssh->writePrompt($command . "\n");
$ssh->waitPrompt($prompt, $buffer, 2);

/*** Having an issue with gateway and DNS being set during clonevm_task properly so we are going to set it here. Need to get back to figuring out wth is happening ***/
$gateway = $server->getGateway();

$command = "sed -i '/GATEWAY/c\GATEWAY=".$gateway."' /etc/sysconfig/network";
$buffer = '';
$ssh->writePrompt($command . "\n");
$ssh->waitPrompt($prompt, $buffer, 2);

/* Oops need to set the hostname to the full fqdn in /etc/sysconfig/network */
$command = "sed -i '/HOSTNAME/c\HOSTNAME=".$serverName."' /etc/sysconfig/network";
$buffer = '';
$ssh->writePrompt($command . "\n");
$ssh->waitPrompt($prompt, $buffer, 2);


$dnsSearch = $config['dns']['search']; 

$command = 'echo "search '.$dnsSearch.'" >> /etc/resolv.conf';    
$buffer = '';
$ssh->writePrompt($command . "\n");
$ssh->waitPrompt($prompt, $buffer, 2); 
 
$dnsArray = $config['dns']['nameservers'];

foreach($dnsArray AS $ns){
    $command = 'echo "nameserver '.$ns.'" >> /etc/resolv.conf';    
    $buffer = '';
    $ssh->writePrompt($command . "\n");
    $ssh->waitPrompt($prompt, $buffer, 2);
        
}

$command = 'echo "options timeout:1" >> /etc/resolv.conf';    
$buffer = '';
$ssh->writePrompt($command . "\n");
$ssh->waitPrompt($prompt, $buffer, 2);

updateStatus('Building', 'Installing the Chef client...');

$tnexp = explode('-', $templateName); 
//$distro = $osexp[0]."-".$tnexp[1];
if( substr( $tnexp[1], 0, 1 ) == "6"){
	$chefClientFilePath = "/var/www/html/neumatic/public/clientconfig/buildfiles/chef_client/centOS6/";
	$chefClientFile = "chef-12.2.1-1.el6.x86_64.rpm";
}elseif( substr( $tnexp[1], 0, 1 ) == "5"){
	$chefClientFilePath = "/var/www/html/neumatic/public/clientconfig/buildfiles/chef_client/centOS5/";
	$chefClientFile = "chef-12.2.1-1.el5.x86_64.rpm";
}

//copy Chef Client rpm to the server

$remoteFile = fopen("ssh2.sftp://".$config['rootUser'].":".$config['rootPassword']."@".$serverName .":22/tmp/".$chefClientFile, 'w');
$srcFile = fopen($chefClientFilePath.$chefClientFile, 'r');
$writtenBytes = stream_copy_to_stream($srcFile, $remoteFile);
fclose($remoteFile);
fclose($srcFile);

//install the chef client
$command = 'rpm -Uvh /tmp/'.$chefClientFile.'; rm -rf /tmp/'.$chefClientFile;    
$buffer = '';
$ssh->writePrompt($command . "\n");
$ssh->waitPrompt($prompt, $buffer, 2);


updateStatus('Building', 'Configuring Chef...');

//create the log file
$command = 'mkdir /var/log/chef; touch /var/log/chef/client.log';    
$buffer = '';
$ssh->writePrompt($command . "\n");
$ssh->waitPrompt($prompt, $buffer, 2);


// remove the /etc/chef dir if it is there
$command = "rm -rf /etc/chef";
$buffer = '';
$ssh->writePrompt($command . "\n");
$ssh->waitPrompt($prompt, $buffer, 2);

// create a new /etc/chef dir
$command = "mkdir /etc/chef";
$buffer = '';
$ssh->writePrompt($command . "\n");
$ssh->waitPrompt($prompt, $buffer, 2);

$command = "rm -f /var/log/chef-initial-run.log";
$buffer = '';
$ssh->writePrompt($command . "\n");
$ssh->waitPrompt($prompt, $buffer, 2);


$command = "mkdir /etc/chef/trusted_certs";
$buffer = '';
$ssh->writePrompt($command . "\n");
$ssh->waitPrompt($prompt, $buffer, 2);

$command = "chmod -R 777 /etc/chef";
$buffer = '';
$ssh->writePrompt($command . "\n");
$ssh->waitPrompt($prompt, $buffer, 2);

$sftpConnect = "ssh2.sftp://".$config['rootUser'].":".$config['rootPassword']."@".$serverName .":22";

//client.rb
$clientRBFile = "/var/www/html/neumatic/public/clientconfig/" . $chefServer . "/client.rb";
$clientRB = file_get_contents($clientRBFile);
$sendClientRBResult = file_put_contents($sftpConnect."/etc/chef/client.rb", $clientRB);

//server.crt
$certFile = "/var/www/html/neumatic/public/clientconfig/" . $chefServer . "/server.crt";
$cert = file_get_contents($certFile);
$sendCertResult = file_put_contents($sftpConnect."/etc/chef/trusted_certs/server.crt", $cert);

//validation.pem
$validationFile = "/var/www/html/neumatic/public/clientconfig/" . $chefServer . "/validation.pem";
$validation = file_get_contents($validationFile);
$sendValidationResult = file_put_contents($sftpConnect."/etc/chef/validation.pem", $validation);

//corehost.key
$corehostkeyFile = "/var/www/html/neumatic/public/clientconfig/corehost.key";
$corehostkey = file_get_contents($corehostkeyFile);
$sendCorehostkeyResult = file_put_contents($sftpConnect."/etc/chef/corehost.key", $corehostkey);

//first-boot.json
$chefRole = $server->getChefRole(); 
$firstBoot = <<<EOF
{"run_list":["role[$chefRole]"]}
EOF;
$sendFirstBootResult = file_put_contents($sftpConnect."/etc/chef/first-boot.json", $firstBoot);

//client run script
$runClientTemplate = <<<EOF
#!/bin/bash

/usr/bin/chef-client -j /etc/chef/first-boot.json -E $environment > /var/log/chef-initial-run.log

EOF;
$sendFileResult = file_put_contents($sftpConnect."/tmp/chefConfig", $runClientTemplate);


$command = "sh /tmp/chefConfig";
$buffer = '';
$ssh->writePrompt($command . "\n");
$ssh->waitPrompt($prompt, $buffer, 2);

//Set up the chef client as a service
$chefServiceFile = "/var/www/html/neumatic/public/clientconfig/buildfiles/etc/init.d/chef-client";
$remoteFile = fopen("ssh2.sftp://".$config['rootUser'].":".$config['rootPassword']."@".$serverName .":22/etc/init.d/chef-client", 'w');
$srcFile = fopen($chefServiceFile, 'r');
$writtenBytes = stream_copy_to_stream($srcFile, $remoteFile);
fclose($remoteFile);
fclose($srcFile);

$command = 'chmod 755 /etc/init.d/chef-client; chkconfig --level 35 chef-client on; service chef-client start';    
$buffer = '';
$ssh->writePrompt($command . "\n");
$ssh->waitPrompt($prompt, $buffer, 2);


updateStatus('Building', 'Cooking...');

// update the chef table indicating that we're starting the bootstrap
$chefModel
    ->setServerId($serverId)
    ->setCookStartTime($dateNow);
$chefTable->save($chefModel);

print "\tsleeping for " . $interval . "\n";
sleep($interval);
$secs += $interval;


// touch the chef watcher log file and set its perms wide open so it can be deleted later
$chefLog = "/opt/neumatic/watcher_log/chef_watch.log." . $serverName;
touch($chefLog);

// spawn the chef watch process
exec("nohup php /opt/neumatic/module/Neumatic/bin/chef_watch.php -i " . $server->getId() . " > " . $chefLog . " 2>&1 &");
chmod($chefLog, 0666);




function sortTimes($a, $b) {
    return strcmp($a['time'], $b['time']);
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


function updateStatus($status, $text) {
    global $server, $serverTable;

    $server->setStatus($status)->setStatusText($text);
    $server = $serverTable->update($server);
    return $server;
}



function ping($host)
{
        exec(sprintf('ping -c 1 -W 5 %s', escapeshellarg($host)), $res, $rval);
        return $rval === 0;
}


