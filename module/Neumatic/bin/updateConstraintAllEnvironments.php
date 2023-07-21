<?php


if(!isset($argv[1]) OR !isset($argv[2]) OR !isset($argv[3]) OR !isset($argv[4])){
	echo "Usage: php -q updateConstraintAllEnvironments.php {chefServer} {cookbookName} {operator (eq, lt, gt, lteq, gteq)} {version} ";
	die();
}

$chefServer = $argv[1];
$cookbook = $argv[2];
$operator = $argv[3];
$version = $argv[4];

$getEnvironments = json_decode(curlGetUrl("https://neumatic.ops.neustar.biz/chef/getEnvironments?chef_server=".$chefServer));

$environments = $getEnvironments->environments;

foreach($environments AS $env){
	
	$envName = $env->name;
	$updateVersion = curlGetUrl("https://neumatic.ops.neustar.biz/chef/editEnvironmentCookbookVersion/".$envName."/".$cookbook."/".$operator."/".$version."?chef_server=".$chefServer);

}

function curlGetUrl($url, $post = null) {
	$curl = curl_init($url);

        curl_setopt($curl, CURLOPT_HTTPAUTH, CURLAUTH_BASIC);
        curl_setopt($curl, CURLOPT_USERPWD, "s_mkelle:Ken21Seth");
        //curl_setopt($curl, CURLOPT_VERBOSE, true);
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
