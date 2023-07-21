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

$consoleRawFile = "/tmp/console_raw.log.stlabckvm01.va.neustar.com";

$data = array();
$lifo = array();
$buffer = "";

try {
    $raw = fopen($consoleRawFile, "r");
    print "Read\n";
    $c = fgetc($raw);
    while(!feof($raw)) {
        if (ord($c) == 10) {
            // line feed. add this line to the end of the data array
            // and add the line to the lifo
            capture($data, $lifo, $buffer);

            print "Read\n";
            $c = fgetc($raw);

        } else if (ord($c) == 27) {
            print "ESC (outer)\n";
            // check to see if this is a duplicate line, if not add to data array
            capture($data, $lifo, $buffer);

            // initialize CSI string container
            $csi = "";

            // loop until the end of the escape sequence and then assign the line to the array
            // if it doesn't already exist w/in the last 25 entries
            print "Read\n";
            $c = fgetc($raw);
            while(preg_match("/[0-9;mHJ[]+/", $c)) {
                $csi .= $c;
                print "In ESC: ({$csi}) ";

                // check for a CSI cursor position sequence (CSI n ; m H)
                if (preg_match("/(\d+);(\d+)H/", $csi, $m)) {
                    print "\nCUP\n";
                    // if m == 1 then add to data string as this is moving to first char of the line
                    if ($m[2] == 1) {
                        print "Found CR\n";
                        capture($data, $lifo, $buffer);
                        break;
                    }
                    $csi = "";
                }
                print "Read\n";
                $c = fgetc($raw);
            }

            print "\n";
            $buffer = "";
            print "buffer(1) = {$buffer}\n";

        } else {
            // just append to buffer
            $buffer .= $c;
            print "buffer(2) = {$buffer}\n";
            print "Read\n";
            $c = fgetc($raw);
        }
    }

    fclose($raw);

    file_put_contents("cooked", implode("\n", $data));
}

catch (Exception $e) {
    print "message = " . $e->getMessage() . "\n";
    print "trace = " . $e->getTraceAsString() . "\n";
}

function shiftLifo(&$lifo) {
    // if lifo > 30, drop the last line
    if (count($lifo) > 30) {
        array_shift($lifo);
    }
}

function repeated($lifo, $string) {
    foreach($lifo as $line) {
        if ($string == $line) {
            return true;
        }
    }
    return false;
}

function capture(&$data, &$lifo, &$buffer) {
    if (!repeated($lifo, $buffer)) {
        print "buffer (out) = {$buffer}\n";
        $data[] = $buffer;
        $lifo[] = $buffer;
        shiftLifo($lifo);
    } else {
        print "Repeated: {$buffer}\n";
    }
    // clear the buffer
    $buffer = "";
}
