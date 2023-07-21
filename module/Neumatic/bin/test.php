#!/usr/bin/php
<?php

// default time zone
date_default_timezone_set("America/New_York");

// define the include path where all our class files are found
$dirName = dirName(__DIR__);
set_include_path(
	implode(':',
	        array(
                "/opt/sts-lib",
                "/usr/share/php",
                "/usr/share/pear",
                "/usr/share/php/ZF2/library",
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

// config file
$config = require(__DIR__ . "/../config/module.config.php");

$node="dnsa-sinsp01-08.prod.ultradns.net";
$chef="ap-southeast-1";
$s="https://stlabvsts01.va.neustar.com";

echo "delete chef client local\n";
curlGet("{$s}/chef/deleteClient/{$node}?chef_server=chef.ops.neustar.biz/organizations/ultradns");
echo "delete chef node local\n";
curlGet("{$s}/chef/deleteNode/{$node}?chef_server=chef.ops.neustar.biz/organizations/ultradns");
echo "delete chef client {$chef}\n";
curlGet("{$s}/chef/deleteClient/{$node}?chef_server={$chef}.chef.ops.neustar.biz/organizations/ultradns");
echo "delete chef node {$chef}\n";
curlGet("{$s}/chef/deleteNode/{$node}?chef_server={$chef}.chef.ops.neustar.biz/organizations/ultradns");

echo "delete ldap\n";
curlGet("{$s}/ldap/deleteHost/{$node}");


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
