<?php
/*******************************************************************************
 *
 * $Id: global.php 81526 2013-11-28 19:27:43Z rcallaha $
 * $Date: 2013-11-28 14:27:43 -0500 (Thu, 28 Nov 2013) $
 * $Author: rcallaha $
 * $Revision: 81526 $
 * $HeadURL: https://svn.ultradns.net/svn/sts_tools/acdc/trunk/config/global.php $
 *
 *******************************************************************************
 */

// default time zone
date_default_timezone_set("America/New_York");

// define the include path where all our class files are found
set_include_path(
	implode(':',
	        array(
                __DIR__ . "/../src",
                __DIR__ . "/../../../vendor",
                __DIR__ . "/../../../../sts-lib",
                "/usr/share/php/ZF2/library",
                "/opt/sts-lib",
                "/usr/share/php",
                "/usr/share/pear",
	        )
	)
);

// register our autoloader that replaces '\' with '/' and '_' with ''
spl_autoload_register(function ($className) {
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
