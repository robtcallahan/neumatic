#!/usr/bin/php
<?php


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

$config = require_once(__DIR__ . "/../config/module.config.php");


try {

    print "hi\n";
    $results = curlGet("https://stlabvsts01.va.neustar.com/ldap/deleteHost/3094");
    print_r($results);
}
catch (Exception $e) {
    print("\n");
   	printf("%-12s => %s\n",   "returnCode", 1);
   	printf("%-12s => %s\n",   "errorCode",  $e->getCode());
   	printf("%-12s => %s\n",   "errorText",  $e->getMessage());
   	printf("%-12s => %s\n",   "errorFile",  $e->getFile());
   	printf("%-12s => %s\n",   "errorLine",  $e->getLine());
   	printf("%-12s => \n%s\n", "errorStack", $e->getTraceAsString());
   	exit;
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

function outlog($logMsg) {
    print $logMsg;
}

