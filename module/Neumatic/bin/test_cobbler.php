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


try {
    $url = $this->protocol . "://" . $server . "/" . $this->apiPath;
    $client = new XmlRpc\Client($url);
    // ignore ssl cert validation
    $client->getHttpClient()->setOptions(array('sslverifypeer' => false));
}

catch (SoapFault $e) {
    print "message = " . $e->getMessage() . "\n";
    print "trace = " . $e->getTraceAsString() . "\n";
    print "request = " . $soapClient->__getLastRequest() . "\n";
    file_put_contents($xmlFile, $soapClient->__getLastRequest());
}
