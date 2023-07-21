<?php

$modulesDirectory = "/var/www/html/neumatic/module/";

$modulesDirectoryFileList = scandir($modulesDirectory);

$modules = array();

foreach ($modulesDirectoryFileList AS $modulesFile) {
    if ($modulesFile === '.' OR $modulesFile === '..' OR $modulesFile === '.svn') continue;
    if (is_dir($modulesDirectory . '/' . $modulesFile)) {
        $modules[] = $modulesFile;
    }
}

# add the ChefServerApi in the vendor directory as a module
$modules[] = "ChefServerApi";
$modules[] = "Vmwarephp";

return array(
    'modules'                 => $modules,

    // These are various options for the listeners attached to the ModuleManager
    'module_listener_options' => array(

        'module_paths'      => array(
            './module',
            './vendor',
        ),

        'config_glob_paths' => array(
            'config/autoload/{,*.}{global,local}.php',
        ),

    ),

    // Initial configuration with which to seed the ServiceManager.
    'service_manager'         => Array(
        'factories' => Array(
            'Zend\Log' => function () {
                    $log = new Zend\Log\Logger();
                    #$fireWriter = new Zend\Log\Writer\FirePhp();
                    #$log->addWriter($fireWriter);
                    $logWriter = new Zend\Log\Writer\Stream('/var/log/neumatic/neumatic.log');
                    $logWriter->setFormatter(new \Zend\Log\Formatter\Simple('%message%'));
                    $log->addWriter($logWriter);
                    //$log->addWriter(new Zend\Log\Writer\Stream("php://output"));
                    Zend\Log\Logger::registerErrorHandler($log);
                    return $log;
                },
            'Version' => function() {
                    $cmd = "cat /var/www/html/neumatic/public/ABOUT";
                    $version = `$cmd`;
                    return $version;
                }
        ) // factories
    ), // service_manager
);

 /*
$modulesDirectory = "/var/www/html/Neumatic/module/";

$modulesDirectoryFileList = scandir($modulesDirectory);

$modules = array();

foreach($modulesDirectoryFileList AS $modulesFile){
	if($modulesFile === '.' OR $modulesFile === '..' OR $modulesFile === '.svn') continue;
	if(is_dir($modulesDirectory.'/'.$modulesFile)){
		$modules[] = $modulesFile;
	}
}

# add the ChefServerApi in the vendor directory as a module
$modules[] = "ChefServerApi";

return array(
    'modules' => $modules,
    'module_listener_options' => array(
        'config_glob_paths'    => array(
            'config/autoload/{,*.}{global,local}.php',
        ),
        'module_paths' => array(
            './module',
            './vendor',
        ),
    ),
);
  * 
  * 
  */