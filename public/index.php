<?php
/**
 * This makes our life easier when dealing with paths. Everything is relative
 * to the application root now.
 */
error_reporting(E_ALL);
date_default_timezone_set("America/New_York");
chdir(dirname(__DIR__));

// Setup autoloading
require 'init_autoloader.php';

/**
 * Fix for:
 *
 * Declaration of Zend\\Stdlib\\ArrayObject::offsetGet()
 * must be compatible with that of ArrayAccess::offsetGet()
 */
$libDir = getenv('ZF2_PATH');

//require $libDir . '/Zend/Stdlib/compatibility/autoload.php';
//require $libDir . '/Zend/Session/compatibility/autoload.php';

// Run the application!
Zend\Mvc\Application::init(require 'config/application.config.php')->run();
