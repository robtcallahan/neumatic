#!/usr/bin/php
<?php

include __DIR__ . "/../../../module/Neumatic/config/global.php";
include __DIR__ . "/../../SimpleHTMLDOM/SimpleHTMLDOM.php";

$config = require_once(__DIR__ . "/../../../module/Neumatic/config/module.config.php");

$debug = false;

#$pidFile = "/var/run/websocketd.pid";
$pidFile = "/var/tmp/websocketd.pid";


// get the pid of websocketd
$command = 'ps -ef | grep websocketd | grep 8443 | grep -v grep | awk "{print \$2}"';
$pid = system($command);
file_put_contents($pidFile, $pid);


// Loop forever with a 15 second sleep in between
while (1) {
    $childs = array();

    // Fork a child process for each type of data being retrieved
    for ($i = 0; $i < 5; $i++) {
        $pid = pcntl_fork();
        if ($pid == -1)
            die('Could not fork');

        if ($pid) {
            $childs[] = $pid;
        } else {
            // switch for each function
            switch($i) {
                case 0:
                    if ($debug) echo json_encode(array("message" => "child getStats({$i}), pid {$pid}")) . "\n";
                    getStats($config);
                    break;
                case 1:
                    if ($debug) echo json_encode(array("message" => "child getMonit({$i}), pid {$pid}")) . "\n";
                    getMonit($config);
                    break;
                case 2:
                    if ($debug) echo json_encode(array("message" => "child getWeather({$i}), pid {$pid}")) . "\n";
                    getWeather($config);
                    break;
                case 3:
                    if ($debug) echo json_encode(array("message" => "child getQuote({$i}), pid {$pid}")) . "\n";
                    getQuote($config);
                    break;
                case 4:
                    if ($debug) echo json_encode(array("message" => "child getStock({$i}), pid {$pid}")) . "\n";
                    getStock($config);
                    break;
            }

            // The child process needed to end the loop.
            exit();
        }
    }

    // wait for all to complete
    while (count($childs) > 0) {
        foreach ($childs as $key => $pid) {
            if ($debug) echo json_encode(array("message" => "running {$key}, pid {$pid}")) . "\n";
            $res = pcntl_waitpid($pid, $status, WNOHANG);

            // If the process has already exited
            if ($res == -1 || $res > 0)
                unset($childs[$key]);
        }
        sleep(1);
    }

    sleep(15);
}

/**
 * Get the current Neustar (NSR) stock price from marketwatch.com
 * @param $config
 */
function getStock($config) {
    $html = file_get_html("{$config['stockQuoteURL']}");

    print json_encode(
        array(
            "stock" => array(
                "price"     => '$' . $html->find('div[class=pricewrap]', 0)->find('p[class="data bgLast"]', 0)->plaintext,
                "change"    => $html->find('div[class=lastpricedetails]', 0)->find('p', 1)->find('span', 0)->plaintext,
                "changePct" => $html->find('div[class=lastpricedetails]', 0)->find('p', 1)->find('span', 1)->plaintext
            )
        )
    ) . "\n";
}

/**
 * get the quote of the day using the NeuMatic API
 * @param $config
 */
function getQuote($config) {
    #$err = fopen("php://stderr", "w");
    #fwrite($err, "getQuote()");

    $qotd = array(
        "quote"  => "Whever you go, there you are.",
        "author" => "I don't know"
    );

    $quoteCategories = array(
        'management',
        'inspire',
        'sports',
        'life',
        'funny',
        'love',
        'art'
    );
    $quoteCategory   = $quoteCategories[rand(0, 6)];

    $cacheFile = $config['dataDir'] . "/" . $quoteCategory;
    $stat      = null;
    if (file_exists($cacheFile)) {
        $stat = stat($cacheFile);
        #fwrite($err, "cache file {$cacheFile} exists\n");
    }

    if (!file_exists($cacheFile) || time() - $stat['mtime'] > 60 * 60 * 24) {
        $url = "http://api.theysaidso.com/qod.json?category={$quoteCategory}";
        #fwrite($err, "curlGetUrl: {$url}\n");

        $json = curlGetUrl($config, $url);
        if (property_exists($json, 'contents')) {
            $qotd = array(
                'quote'  => $json->contents->quotes[0]->quote,
                'author' => $json->contents->quotes[0]->author
            );
            file_put_contents($cacheFile, serialize($qotd));
        } else {
            #fwrite($err, "contents not available: " . print_r($json, true) . "\n");
        }
    }

    if (file_exists($cacheFile)) {
        $qotd = unserialize(file_get_contents($cacheFile));
    }

    print json_encode(
            array(
                "qotd" => array(
                    "quote"  => $qotd['quote'],
                    "author" => $qotd['author'],
                )
            )
    ) . "\n";
    #fclose($err);
}

/**
 * Get build stats from NeuMatic
 * @param $config
 */
function getStats($config) {
    $statsJson = curlGetUrl($config, "{$config['neumaticAPIServerProtocol']}://{$config['neumaticAPIServer']}/{$config['statsAPI']}");
    print json_encode(
            array(
                "stats"      => $statsJson->stats
            )
        ) . "\n";
}

/**
 * get the forecast from WeatherUnderground
 * @param $config
 */
function getWeather($config) {
    #$err = fopen("php://stderr", "w");
    #fwrite($err, "getWeather()");

    $weather = array(
        "weatherTime"     => "Now",
        "weatherLocation" => "Here",
        "conditions"      => "Airy with nice colors and textures",
        "conditionsIcon"  => "http://icons-ak.wxug.com/i/c/k/sunny.gif",
        "title1"          => "Total",
        "forecast1"       => "Most light",
        "icon1"           => "http://icons-ak.wxug.com/i/c/k/sunny.gif",
        "title2"          => "Tonight",
        "forecast2"       => "Mostly dark",
        "icon2"           => "http://icons-ak.wxug.com/i/c/k/nt_clear.gif"
    );

    $cacheFile = $config['dataDir'] . "/weather.ser";
    $stat      = null;

    if (file_exists($cacheFile)) {
        #fwrite($err, "cache file {$cacheFile} exists\n");
        $stat = stat($cacheFile);
    }
    if (!file_exists($cacheFile) || time() - $stat['mtime'] > 60 * 60) {
        $url = "{$config['wundergroundAPIProtocol']}://{$config['wundergroundAPIServer']}/{$config['wundergroundAPIKey']}/{$config['forecastStering']}";
        #fwrite($err, "curlGetUrl: {$url}\n");

        $weatherJson = curlGetUrl($config, $url);
        if (property_exists($weatherJson, 'forecast')) {
            $weather = array(
                "weatherTime"     => $weatherJson->current_observation->observation_time,
                "weatherLocation" => $weatherJson->current_observation->display_location->full,
                "conditions"      => $weatherJson->current_observation->temp_f . " degrees.  " .
                    $weatherJson->current_observation->weather . ".",
                "conditionsIcon"  => $weatherJson->current_observation->icon_url,
                "title1"          => $weatherJson->forecast->txt_forecast->forecastday[0]->title,
                "forecast1"       => $weatherJson->forecast->txt_forecast->forecastday[0]->fcttext,
                "icon1"           => $weatherJson->forecast->txt_forecast->forecastday[0]->icon_url,
                "title2"          => $weatherJson->forecast->txt_forecast->forecastday[1]->title,
                "forecast2"       => $weatherJson->forecast->txt_forecast->forecastday[1]->fcttext,
                "icon2"           => $weatherJson->forecast->txt_forecast->forecastday[1]->icon_url
            );
            file_put_contents($cacheFile, serialize($weather));
        } else {
            #fwrite($err, "forecast not available: " . print_r($weatherJson, true) .  "\n");
        }
    }
    if (file_exists($cacheFile)) {
        $weather = unserialize(file_get_contents($cacheFile));
    }

    // final return structure
    print json_encode(
            array(
                "weather" => $weather
            )
        ) . "\n";
    #fclose($err);
}

/**
 * get the monit stats for our production server
 * @param $config
 */
function getMonit($config) {
    #$err = fopen("php://stderr", "w");
    #fwrite($err, "getMonit()");

    $output = array();
    exec("hostname", $output);
    $url = "http://{$output[0]}:{$config['monitPort']}/";
    #fwrite($err, "file_get_html: {$url}\n");
    $html = file_get_html($url);

    // get the first header row which contains server stats
    $cells = $html->find('table[id="header-row"]', 0)->find('tr', 1)->find('td');

    // remove the &nbsp; strings from load and cpu
    $load = str_replace("&nbsp;", "   ", $cells[2]->plaintext);
    $cpu  = str_replace("&nbsp;", "   ", $cells[3]->plaintext);

    // put the structure together
    $server = array(
        "name"   => $cells[0]->plaintext,
        "status" => $cells[1]->plaintext,
        "load"   => $load,
        "cpu"    => $cpu,
        "memory" => $cells[4]->plaintext,
        "swap"   => $cells[5]->plaintext,
    );


    // get the monitor stats
    $monitors  = array();
    $firstTime = true;

    try {
        // loop over the 4th table
        foreach ($html->find('table[id="header-row"]', 3)->find('tr') as $tr) {
            // skip the header row
            if ($firstTime) {
                $firstTime = false;
                continue;
            }

            // may be multiple protocols and classes. loop over and define the protos structure
            $protos = array();
            foreach ($tr->find('td', 2)->find('span') as $span) {
                $protos[] = array(
                    "text"  => $span->plaintext,
                    "class" => $span->getAttribute('class')
                );
            }

            // construct the monitor object and add to the montors array
            $mon        = array(
                "host"        => $tr->find('td', 0)->find('a', 0)->plaintext,
                "status"      => $tr->find('td', 1)->find('span', 0)->plaintext,
                "statusClass" => $tr->find('td', 1)->find('span', 0)->getAttribute('class'),
                "protocols"   => $protos,
            );
            $monitors[] = $mon;
        }
        // sort by the monitor name
        usort($monitors, function ($a, $b) {
            return strcmp($a['host'], $b['host']);
        });
    } catch (\ErrorException $e) {
        // unable to find the monitors data. just move on. nothing to see here
    }

    $date = date('Y-m-d H:i:s');
    print json_encode(
            array(
                "lastUpdate" => $date,
                "server"     => $server,
                "monitors"   => $monitors
            )
        ) . "\n";
    #fclose($err);
}

/**
 * @param $config
 * @param $url
 * @return array|mixed
 */
function curlGetUrl($config, $url) {
    $username = $config['stsappsUser'];
    $password = $config['stsappsPassword'];

    $curl = curl_init($url);

    curl_setopt($curl, CURLOPT_HTTPAUTH, CURLAUTH_BASIC);
    if (strstr($url, "https") !== -1) {
        curl_setopt($curl, CURLOPT_USERPWD, "{$username}:{$password}");
    }
    curl_setopt($curl, CURLOPT_VERBOSE, false);
    curl_setopt($curl, CURLOPT_HEADER, false);
    curl_setopt($curl, CURLOPT_SSL_VERIFYPEER, false);
    curl_setopt($curl, CURLOPT_SSL_VERIFYHOST, false);
    curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);

    $response = curl_exec($curl);
    curl_close($curl);

    try {
        $json = json_decode($response);
    } catch (\ErrorException $e) {
        return array("success" => false, "message" => "Could not decode JSON from {$url}");
    }
    return $json;
}
