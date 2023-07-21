<?php

namespace Neumatic\Controller;

use Zend\Mvc\MvcEvent;
use Neumatic\Model;

class ReportsController extends Base\BaseController {
    protected $config;

    protected $chef;
    protected $chefServer;
    protected $timeStart;

    public function onDispatch(MvcEvent $e) {

        // $this->_config = $this->getServiceLocator()->get('Config');

        $this->timeStart = microtime(true);

        $this->defaultCacheLifetime = "300";
        $this->checkCache();

        $this->chefServer = $this->params()->fromQuery('chef_server');

        if ($this->chefServer == "" OR $this->chefServer == null) {
            if (isset($_COOKIE['chef_server'])) {
                $this->chefServer = $_COOKIE['chef_server'];
            } else {
                $this->chefServer = $this->_config['chef']['default']['server'];
                if (!isset($_SESSION)) {
                    setcookie('chef_server', $this->chefServer);
                }

            }
        }

        return parent::onDispatch($e);
    }

    public function indexAction() {
        return $this->renderView(array("message"=>"This controller has no output from index."));
    }

    public function getNodesAction() {
        // mongo connect
        $mongo = new \MongoClient();

        // select a database
        $db         = $mongo->selectDB($this->_config['databases']['mongo']['database']);
        $collection = $db->selectCollection($this->_config['databases']['mongo']['collectionAll']);

        // get all the nodes
        $cursor = $collection->find(array(),
                                    array("_id"                        => 1,
                                          "name"                       => 1,
                                          "automatic.platform"         => 1,
                                          "automatic.platform_version" => 1,
                                          "automatic.roles"            => 1,
                                          "neumatic"                   => 1,
                                    ));

        $nodes = array();
        while ($doc = $cursor->getNext()) {
            $auto = array_key_exists('automatic', $doc) ? $doc['automatic'] : array();
            $nm = array_key_exists('neumatic', $doc) ? $doc['neumatic'] : array();
            $roles = $auto ? $this->getArrayValue($auto, 'roles') : 'NA';
            $roles = is_array($roles) ? implode(",", $roles) : $roles;

            $nodes[] = array(
                "name" => $doc['name'],
                "roles" => $roles,
                "neuCollectionVersion" => $nm ? $this->getArrayValue($nm, 'neuCollectionVersion') : 'NA',
                "platform" => $auto ? $this->getArrayValue($auto, 'platform') : 'NA',
                "platformVersion" => $auto ? $this->getArrayValue($auto, 'platform_version') : 'NA',
                "chefServerName" => $nm ? $this->getArrayValue($nm, 'chefServerName') : 'NA',
                "chefServerEnv" => $nm ? $this->getArrayValue($nm, 'chefServerEnv') : 'NA',
                "chefEntOrg" => $nm ? $this->getArrayValue($nm, 'chefEntOrg') : 'NA',
                "chefVersion" => $nm ? $this->getArrayValue($nm, 'chefVersion') : 'NA',
                "ohaiTimeDiff" => $nm ? $this->getArrayValue($nm, 'oahiTimeDiff') : 'NA',
                "ohaiTimeDiffString" => $nm ? $this->getArrayValue($nm, 'ohaiTimeDiffString') : 'NA',
            );
        }


        usort($nodes, function($a, $b) {
            return strcmp($a['name'], $b['name']);
        });

        return $this->renderView(array(
                                     "success" => true,
                                     "nodes"   => $nodes)
        );
    }

    private function getArrayValue($array, $key) {
        return array_key_exists($key, $array) ? $array[$key] : 'NA';
    }

    public function checkCookbookRestrictedAllEnvironmentsAction() {
        $cookbookName = $this->params()->fromRoute('param1');
        $getEnvListURL = "https://".$_SERVER['SERVER_NAME']."/chef/getEnvironments?chef_server=".$this->chefServer;
        $getEnvListResult = $this->curlGetUrl($getEnvListURL);
        $environments = $getEnvListResult->environments;
        $failMessages = array();
        foreach ($environments AS $env) {
            if ($env->name != '_default') {
                $getEnvCookbookVersionURL = "https://".$_SERVER['SERVER_NAME']."/chef/getEnvironmentCookbookVersion/".$env->name."/".$cookbookName."?chef_server=".$this->chefServer;
                $getEnvCookbookVersionResult = $this->curlGetUrl($getEnvCookbookVersionURL);
                if ($getEnvCookbookVersionResult->success == 0) {
                    $failMessages[] = $getEnvCookbookVersionResult->error;
                }
            }

        }
        if(count($failMessages) > 0){
            foreach($failMessages AS $msg){
                echo $msg;
                echo "\r\n";
            }
            die();    
        }
        echo "0";
        die();
    }

    public function duplicateNodesReportAction() {

        $getServerListURL = "https://".$_SERVER['SERVER_NAME']."/chef/getServers";
        $getServerListResult = $this->curlGetUrl($getServerListURL);
        $nodeList = array();
        $duplicates = array();
        foreach ($getServerListResult->servers AS $server) {

            if ($server->allowChef == true AND !stristr($server->env, "AWS")) {
                $getNodesURL = "https://".$_SERVER['SERVER_NAME']."/chef/getNodes?chef_server=".$server->fqdn;
                $getNodesResult = $this->curlGetUrl($getNodesURL);
                $nodes = $getNodesResult->nodes;
                foreach ($nodes AS $node) {
                    if (!isset($nodeList[$node])) {
                        $nodeList[$node] = array();

                        $nodeList[$node][$server->fqdn] = "";

                    } else {
                        $nodeList[$node][$server->fqdn] = "";
                        $duplicates[$node] = $nodeList[$node];
                    }

                }
            }

        }

        //print_r($duplicates);
        foreach ($duplicates AS $dupK=>$dupV) {

            foreach ($dupV AS $serverName=>$lastUpdated) {
                $getNodeLastUpdatedUrl = "https://".$_SERVER['SERVER_NAME']."/chef/getNodeLastUpdated/".$dupK."?chef_server=".$serverName;
                print_r($getNodeLastUpdatedUrl);
                $getNodeLastUpdatedResult = $this->curlGetUrl($getNodeLastUpdatedUrl);
                $lastUpdated = $getNodeLastUpdatedResult->updated;

                $lastUpdated = strtotime(str_replace("-", "/", $lastUpdated));

                $duplicates[$dupK][$serverName] = $lastUpdated;

            }

        }
        $notDeleted = "";
        $deleted = "";
        foreach ($duplicates AS $dupK=>$dupV) {
            $highestUpdated = 0;
            $correctServer = "";
            foreach ($dupV AS $server=>$updated) {

                if ($updated == "") {
                    $notDeleted .= "$dupK exists on ";

                    //$lastElement = end($dupV);
                    $lastKey = key($dupV);
                    foreach ($dupV AS $ser=>$upd) {
                        $notDeleted .= $ser;
                        if ($ser != $lastKey) {
                            $notDeleted .= " and ";
                        } else {
                            $notDeleted .= "\r\n \r\n";
                        }
                    }
                    continue 2;
                }

                if ($updated > $highestUpdated) {
                    $highestUpdated = $updated;
                    $correctServer = $server;
                }
            }
            foreach ($dupV AS $server=>$updated) {
                if ($server != $correctServer) {
                    $deleted[$dupK] = $server;
                    $deleteNodeUrl = "https://".$_SERVER['SERVER_NAME']."/chef/deleteNode/".$dupK."?chef_server=".$server;
                    $this->curlGetUrl($deleteNodeUrl);

                    $deleteClientUrl = "https://".$_SERVER['SERVER_NAME']."/chef/deleteClient/".$dupK."?chef_server=".$server;
                    $this->curlGetUrl($deleteClientUrl);

                    $deleted .= "Node $dupV existed on ";
                    $lastElement = end($dupV);
                    //$lastKey = key($dupV);
                    foreach ($dupV AS $ser) {
                        $deleted .= $ser;
                        if ($ser != $lastElement) {
                            $deleted .= " and ";
                        } else {
                            $deleted .= " and was deleted from $server \r\n";
                        }
                    }
                }
            }

        }
        $message = "The following nodes had an issue, such as not having checked in on one server and could not be automatically deleted: \r\n \r\n";
        if ($notDeleted == "") {
            //$nodeDeleted = "None";
        }
        $message .= $notDeleted;
        $message .= "\r\n \r\n----------------------------------------------------------------------------------------------\r\n \r\n";
        $message .= "The following nodes were deleted automatically: \r\n \r\n";
        if ($deleted == "") {
            $deleted = "None";
        }

        $message .= $deleted;
        $message .= "\r\n \r\n \r\n";
        $subject = "Chef Node Duplicate Report";
        $headers = 'From: noreply@neustar.biz'."\r\n".'Reply-To: noreply@neustar.biz'."\r\n".'X-Mailer: PHP/'.phpversion();
        mail("michael.kelley@neustar.biz", $subject, $message, $headers);

        die();

    }

    public function environmentCookbookRestrictionReportAction() {
        $cookbookName = $this->params()->fromRoute('param1');
        $dependencyCookbookName = $this->params()->fromRoute('param2');

        $cleanServerName = strtolower(trim(preg_replace('#\W+#', '_', $this->chefServer), '_'));

        $dateTime = date("Ymd-Hi");

        header("Content-type: text/csv");
        header("Content-Disposition: attachment; filename=".$cookbookName."-".$cleanServerName."-".$dateTime.".csv");
        header("Pragma: no-cache");
        header("Expires: 0");

        if ($dependencyCookbookName != "") {
            echo "FQDN,Environment,Applied,".$cookbookName." Version,".$dependencyCookbookName." Version, Since Last run\n";
        } else {
            echo "FQDN,Environment,Applied,".$cookbookName." Version,Since Last run\n";
        }

        $getCookbookVersionsURL = "https://".$_SERVER['SERVER_NAME']."/chef/getCookbookVersions/".$cookbookName."?chef_server=".$this->chefServer;
        $getCookbookVersionsResult = $this->curlGetUrl($getCookbookVersionsURL);
        $versionList = array();
        foreach ($getCookbookVersionsResult->versions AS $cbvers) {
            $getCookbookVersionDetailsURL = "https://".$_SERVER['SERVER_NAME']."/chef/getCookbookVersionDetails/".$cookbookName."/".$cbvers."?chef_server=".$this->chefServer;
            $getCookbookVersionDetailsResult = $this->curlGetUrl($getCookbookVersionDetailsURL);

            foreach ($getCookbookVersionDetailsResult->cookbook->metadata->dependencies AS $k=>$v) {
                $v_exp = explode(" ", $v);
                $v_ver = $v_exp[1];

                $versionList[$cbvers]['dependencies'][$k] = $v_ver;

            }
        }

        $serverMaxVersion = $getCookbookVersionsResult->versions[0];

        $environmentList = array();

        $getEnvironmentListURL = "https://".$_SERVER['SERVER_NAME']."/chef/getEnvironments?chef_server=".$this->chefServer;
        $getEnvironmentListResult = $this->curlGetUrl($getEnvironmentListURL);
        foreach ($getEnvironmentListResult->environments AS $env) {
            $environmentList[] = $env->name;

        }

        $environments = array();

        foreach ($environmentList AS $environmentName) {
            $environments[$environmentName] = array();
            // echo $environmentName;
            $getEnvironmentURL = "https://".$_SERVER['SERVER_NAME']."/chef/getEnvironment/".$environmentName."?chef_server=".$this->chefServer;
            $getEnvironmentResult = $this->curlGetUrl($getEnvironmentURL);
            $environmentCookbookVersions = $getEnvironmentResult->environment->cookbook_versions;

            //print_r($environmentCookbookVersions);
            foreach ($environmentCookbookVersions AS $cbName=>$envcvb) {
                if ($cbName == $cookbookName) {
                    $environments[$environmentName]['operator'] = $envcvb->operator;
                    $environments[$environmentName]['version'] = $envcvb->version;

                    continue;
                }
            }

            $getEnvironmentNodesURL = "https://".$_SERVER['SERVER_NAME']."/chef/getEnvironmentNodes/".$environmentName."?chef_server=".$this->chefServer;
            $getEnvironmentNodesResult = $this->curlGetUrl($getEnvironmentNodesURL);
            //$environments[$environmentName]['nodes'] = $getEnvironmentNodesResult->nodes;
            foreach ($getEnvironmentNodesResult->nodes AS $nodeName) {
                $environments[$environmentName]['nodes'][$nodeName] = array();

                $recipeApplied = false;

                $getNodeURL = "https://".$_SERVER['SERVER_NAME']."/chef/getNode/".$nodeName."?chef_server=".$this->chefServer;
                $getNodeResult = $this->curlGetUrl($getNodeURL);
                $recipes = $getNodeResult->node->automatic->recipes;

                foreach ($recipes AS $recipe) {
                    if ($recipe == $cookbookName) {
                        $recipeApplied = true;

                        continue;
                    }
                }
                if ($recipeApplied == true) {
                    $applied = "True";
                    if (isset($environments[$environmentName]['version'])) {
                        $version = $environments[$environmentName]['version'];
                    } else {
                        $version = $serverMaxVersion;
                    }

                } else {
                    $applied = "False";
                    $version = "";
                }

                $currentTime = time();
                $ohaiTime = $getNodeResult->node->automatic->ohai_time;

                $difference = $this->seconds2human($currentTime - $ohaiTime);

                if ($dependencyCookbookName != "") {

                    $dependencyVersion = $versionList[$version]['dependencies'][$dependencyCookbookName];

                    echo "$nodeName,$environmentName,$applied,$version,$dependencyVersion,$difference\n";

                } else {

                    echo "$nodeName,$environmentName,$applied,$version,$difference\n";

                }

            }

        }

        die();
    }

    public function nodeNeuCollectionComplianceAction() {
        $nodeName = $this->params()->fromRoute('param1');

        $getServerListURL = "https://".$_SERVER['SERVER_NAME']."/chef/getServers";
        $getServerListResult = $this->curlGetUrl($getServerListURL);

        //$nodeLists = array();
        $nodeInChef = false;
        $chefServer = "";

        foreach ($getServerListResult->servers AS $server) {
            if ($server->fqdn == "roc-chef01.ticprod.com") {
                continue;
            }

            $getNodesURL = "https://".$_SERVER['SERVER_NAME']."/chef/getNodes?chef_server=".$server->fqdn;
            $getNodesResult = $this->curlGetUrl($getNodesURL);

            $nodeList = $getNodesResult->nodes;
            if (in_array($nodeName, $nodeList)) {
                $chefServer = $server->fqdn;
                $nodeInChef = true;
                break;
            }

        }

        $output = array();
        $output['node'] = $nodeName;
        if ($nodeInChef === true) {
            $output['nodeInChef'] = "true";

            $getNodeURL = "https://".$_SERVER['SERVER_NAME']."/chef/getNode/".$nodeName."?chef_server=".$chefServer;
            $getNodeResult = $this->curlGetUrl($getNodeURL);

            $nodeObj = $getNodeResult->node;

            $environmentName = $nodeObj->chef_environment;
            $recipes = $nodeObj->automatic->recipes;

            $currentTime = time();
            $ohaiTime = $nodeObj->automatic->ohai_time;

            $difference = $this->seconds2human($currentTime - $ohaiTime);

            $getEnvironmentURL = "https://".$_SERVER['SERVER_NAME']."/chef/getEnvironment/".$environmentName."?chef_server=".$chefServer;
            $getEnvironmentResult = $this->curlGetUrl($getEnvironmentURL);
            $environmentCookbookVersions = $getEnvironmentResult->environment->cookbook_versions;

            $collectionCookbooks = array("neu_common"=> array("name"=>"neu_common"), "neu_security"=> array("name"=>"neu_security"), "neu_collector"=> array("name"=>"neu_collector"), "neu_monitor"=> array("name"=>"neu_monitor"));

            $cbOperator = "";
            $cbVersion = "";
            foreach ($collectionCookbooks AS $cookbookName=>$cookbookData) {

                foreach ($environmentCookbookVersions AS $cbName=>$envcvb) {
                    if ($cbName == $cookbookName) {
                        $cbOperator = $envcvb->operator;
                        $cbVersion = $envcvb->version;

                        continue;
                    }
                }
                $collectionCookbooks[$cookbookName]['operator'] = $cbOperator;
                $collectionCookbooks[$cookbookName]['version'] = $cbVersion;

                $recipeApplied = false;

                foreach ($recipes AS $recipe) {
                    if ($recipe == $cookbookName) {
                        $recipeApplied = true;

                        continue;
                    }
                }
                if ($recipeApplied === true) {
                    $collectionCookbooks[$cookbookName]['applied'] = "true";

                } else {
                    $collectionCookbooks[$cookbookName]['applied'] = "false";

                }

            }
            $output['cookbooks'] = $collectionCookbooks;
            $output['environment'] = $environmentName;
            $output['chefServer'] = $chefServer;
            $output['lastCheckIn'] = $difference;

        } else {
            $output['nodeInChef'] = "false";
            $output['cookbooks'] = "";

            $output['environment'] = "";
            $output['chefServer'] = "";
            $output['lastCheckIn'] = "";

        }
        $outputType = $this->params()->fromRoute('param2');
        if ($outputType == "csv") {
            $nodeInChef = ($nodeInChef) ? 'true' : 'false';
            echo $nodeName.",".$nodeInChef.",".$chefServer.",".$environmentName.",".$difference.",".$collectionCookbooks['neu_common']['version'].",".$collectionCookbooks['neu_security']['version'].",".$collectionCookbooks['neu_collector']['version'].",".$collectionCookbooks['neu_monitor']['version'];
            exit();
        }
        return $this->renderView(array("success"=>true, "data"=>$output, "logOutput"=>"Got the neu_collection version information for node $nodeName", "logLevel"=>\Zend\Log\Logger::DEBUG, ));

    }

    public function nodeCookbookVersionAction() {
        $nodeName = $this->params()->fromRoute('param1');
        $cookbookName = $this->params()->fromRoute('param2');

        //need to get list of servers and locate the correct server for the node.
        //foreach server chef/checkNodeExists?

        $cleanServerName = strtolower(trim(preg_replace('#\W+#', '_', $this->chefServer), '_'));

        $dateTime = date("Ymd-Hi");

        $getCookbookVersionsURL = "https://".$_SERVER['SERVER_NAME']."/chef/getCookbookVersions/".$cookbookName."?chef_server=".$this->chefServer;
        $getCookbookVersionsResult = $this->curlGetUrl($getCookbookVersionsURL);
        $versionList = array();
        foreach ($getCookbookVersionsResult->versions AS $cbvers) {
            $getCookbookVersionDetailsURL = "https://".$_SERVER['SERVER_NAME']."/chef/getCookbookVersionDetails/".$cookbookName."/".$cbvers."?chef_server=".$this->chefServer;
            $getCookbookVersionDetailsResult = $this->curlGetUrl($getCookbookVersionDetailsURL);

            foreach ($getCookbookVersionDetailsResult->cookbook->metadata->dependencies AS $k=>$v) {
                $v_exp = explode(" ", $v);
                $v_ver = $v_exp[1];

                $versionList[$cbvers]['dependencies'][$k] = $v_ver;

            }
        }

        $serverMaxVersion = $getCookbookVersionsResult->versions[0];

        // get the node environment

        $getNodeURL = "https://".$_SERVER['SERVER_NAME']."/chef/getNode/".$nodeName."?chef_server=".$this->chefServer;
        $getNodeResult = $this->curlGetUrl($getNodeURL);
        $nodeObj = $getNodeResult->node;
        $environmentName = $nodeObj->chef_environment;

        $getEnvironmentURL = "https://".$_SERVER['SERVER_NAME']."/chef/getEnvironment/".$environmentName."?chef_server=".$this->chefServer;
        $getEnvironmentResult = $this->curlGetUrl($getEnvironmentURL);
        $environmentCookbookVersions = $getEnvironmentResult->environment->cookbook_versions;

        print_r($environmentCookbookVersions);

        foreach ($environmentCookbookVersions AS $cbName=>$envcvb) {
            if ($cbName == $cookbookName) {
                $cbOperator = $envcvb->operator;
                $cbVersion = $envcvb->version;

                continue;
            }
        }
        if (isset($cbOperator) AND isset($cbVersion) AND $cbOperator != null AND $cbOperator != "" AND $cbVersion != null AND $cbVersion != "") {

            echo $cookbookName.$cbOperator.$cbVersion;

        } else {
            echo "environment version restriction not set for cookbook ".$cookbookName;
        }

        $recipes = $nodeObj->automatic->recipes;

        $recipeApplied = false;

        foreach ($recipes AS $recipe) {
            if ($recipe == $cookbookName) {
                $recipeApplied = true;

                continue;
            }
        }
        if ($recipeApplied === true) {
            echo "true";
        } else {
            echo "false";
        }

        die();

        // get the version restrictions on the environment
        $environments = array();

        foreach ($environmentList AS $environmentName) {
            $environments[$environmentName] = array();
            // echo $environmentName;
            $getEnvironmentURL = "https://".$_SERVER['SERVER_NAME']."/chef/getEnvironment/".$environmentName."?chef_server=".$this->chefServer;
            $getEnvironmentResult = $this->curlGetUrl($getEnvironmentURL);
            $environmentCookbookVersions = $getEnvironmentResult->environment->cookbook_versions;

            //print_r($environmentCookbookVersions);
            foreach ($environmentCookbookVersions AS $cbName=>$envcvb) {
                if ($cbName == $cookbookName) {
                    $environments[$environmentName]['operator'] = $envcvb->operator;
                    $environments[$environmentName]['version'] = $envcvb->version;

                    continue;
                }
            }

            $getEnvironmentNodesURL = "https://".$_SERVER['SERVER_NAME']."/chef/getEnvironmentNodes/".$environmentName."?chef_server=".$this->chefServer;
            $getEnvironmentNodesResult = $this->curlGetUrl($getEnvironmentNodesURL);
            //$environments[$environmentName]['nodes'] = $getEnvironmentNodesResult->nodes;
            foreach ($getEnvironmentNodesResult->nodes AS $nodeName) {
                $environments[$environmentName]['nodes'][$nodeName] = array();

                $recipeApplied = false;

                $getNodeURL = "https://".$_SERVER['SERVER_NAME']."/chef/getNode/".$nodeName."?chef_server=".$this->chefServer;
                $getNodeResult = $this->curlGetUrl($getNodeURL);
                $recipes = $getNodeResult->node->automatic->recipes;

                foreach ($recipes AS $recipe) {
                    if ($recipe == $cookbookName) {
                        $recipeApplied = true;

                        continue;
                    }
                }
                if ($recipeApplied == true) {
                    $applied = "True";
                    if (isset($environments[$environmentName]['version'])) {
                        $version = $environments[$environmentName]['version'];
                    } else {
                        $version = $serverMaxVersion;
                    }

                } else {
                    $applied = "False";
                    $version = "";
                }

                $currentTime = time();
                $ohaiTime = $getNodeResult->node->automatic->ohai_time;

                $difference = $this->seconds2human($currentTime - $ohaiTime);

                if ($dependencyCookbookName != "") {

                    $dependencyVersion = $versionList[$version]['dependencies'][$dependencyCookbookName];

                    echo "$nodeName,$environmentName,$applied,$version,$dependencyVersion,$difference\n";

                } else {

                    echo "$nodeName,$environmentName,$applied,$version,$difference\n";

                }

            }

        }

        die();
    }

    private function seconds2human($ss) {
        $s = $ss % 60;
        $m = floor(($ss % 3600) / 60);
        $h = floor(($ss % 86400) / 3600);
        $d = floor(($ss % 2592000) / 86400);
        $M = floor($ss / 2592000);
        $out = "";
        $out .= ($M > 0 ? "$M months " : "");
        $out .= ($d > 0 ? "$d days " : "");
        $out .= ($h > 0 ? "$h hours " : "");
        $out .= ($m > 0 ? "$m minutes " : "");
        return $out;
    }

}
