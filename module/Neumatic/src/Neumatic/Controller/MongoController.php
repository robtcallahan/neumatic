<?php

namespace Neumatic\Controller;

use Zend\View\Model\JsonModel;
use Zend\Json\Server\Exception\ErrorException;
use Zend\Mvc\MvcEvent;
use Zend\Log\Logger;

use Neumatic\Model;

class MongoController extends Base\BaseController {

    /** @var  \MongoClient */
    private $_mongo;

    /** @var  \MongoDB */
    private $_database;

    /** @var  \MongoCollection */
    private $_collection;

    protected $chefServer;
    private $chefConfig;

    /**
     * @param MvcEvent $e
     * @return mixed
     */
    public function onDispatch(MvcEvent $e) {
        $this->_mongo = new \MongoClient();

        $this->chefServer = $this->params()->fromQuery('chef_server');
        $this->defineChefServer();

        // get our database and collection
        $this->_database = $this->_mongo->selectDB($this->_config['databases']['mongo']['database']);
        $this->_collection = $this->_database->selectCollection($this->_config['databases']['mongo']['collection']);

        return parent::onDispatch($e);
    }

    /**
     * Separate function so we can instantiate chef servers within actions
     */
    private function defineChefServer() {
        if (isset($this->chefServer) && $this->chefServer != "" && $this->chefServer != "ALL") {
            $this->chefConfig = $this->_config['chef'][$this->chefServer];
        } else if ($this->chefServer != "ALL") {
            $this->chefConfig = $this->_config['chef']['default'];
            $this->chefServer = $this->chefConfig['server'];
        } else {
            $this->chefServer = "ALL";
        }
    }

    /**
     * @return \Zend\View\Model\JsonModel
     */
    public function indexAction() {
        $methods = array();
        foreach (get_class_methods(__CLASS__) as $m) {
            if (preg_match("/Action/", $m)) {
                $methods[] = $m;
            }
        }
        return $this->renderView(array("success" => true, "actions" => implode(", ", $methods)));
    }

    /**
     * @return \Zend\View\Model\JsonModel
     */
    public function getNodeAction() {
        $nodeName = $this->params()->fromRoute('param1');
        $doc = $this->_collection->findOne(array("name" => $nodeName));
        return $this->renderView($doc);
    }

    /**
     * @return JsonModel
     */
    public function getNodesAction() {
        $criteria = array();
        if ($this->chefServer != "ALL") {
            $criteria = array("neumatic.chefServerFqdn" => "{$this->chefServer}");
        }
        $cursor = $this->_collection->find(
            $criteria,
            array(
                "automatic.hostname" => 1,
                "automatic.fqdn" => 1,
                "neumatic.chefServerName" => 1,
                "neumatic.chefServerFqdn" => 1,
                "neumatic.chefVersion" => 1,
                "neumatic.chefVersionStatus" => 1,
                "neumatic.ohaiTime" => 1,
                "neumatic.ohaiTimeString" => 1,
                "neumatic.ohaiTimeDiff" => 1,
                "neumatic.ohaiTimeDiffString" => 1,
                "neumatic.ohaiTimeStatus" => 1,
                "automatic.roles" => 1,
                "automatic.cpu.total" => 1,
                "automatic.memory.total" => 1,
                "automatic.dmi.system.manufacturer" => 1,
                "automatic.dmi.system.product_name" => 1,
                "automatic.platform" => 1,
                "automatic.platform_version" => 1,
                "chef_environment" => 1,
            )
        );

        $nodes = array();
        foreach ($cursor as $doc) {
            if (!array_key_exists('automatic', $doc)) continue;

            if (array_key_exists('memory', $doc['automatic'])) {
                if (preg_match("/(\d+)kB/", $doc['automatic']['memory']['total'], $m)) {
                    $memory = round($m[1] / 1024 / 1024);
                } else {
                    $memory = $doc['automatic']['memory']['total'];
                }
            } else {
                $memory = "";
            }

            if (array_key_exists('dmi', $doc['automatic']) && array_key_exists('system', $doc['automatic']['dmi'])) {
                $manufacturer = $doc['automatic']['dmi']['system']['manufacturer'];
                $model = $doc['automatic']['dmi']['system']['product_name'];
                if (preg_match("/ProLiant ([\w\d\s]+)/", $model, $m)) {
                    $model = $m[1];
                } else if ($model == "VMware Virtual Platform") {
                    $model = "VMWare VM";
                }
            } else {
                $manufacturer = "";
                $model = "";
            }

            if (array_key_exists('name', $doc)) {
                $name = $doc['name'];
            } else if (array_key_exists('hostname', $doc['automatic'])) {
                $name = $doc['automatic']['hostname'];
            } else if (array_key_exists("node", $doc) && array_key_exists("name", $doc['node'])) {
                $name = $doc['node']['name'];
            } else {
                $name = "";
            }

            if ($name != "") {
                $nodes[] = array(
                    "hostname" => $name,
                    "fqdn" => array_key_exists('fqdn', $doc['automatic']) ? $doc['automatic']['fqdn'] : '',
                    "memory" => $memory,
                    "numCpu" => array_key_exists('cpu', $doc['automatic']) ? $doc['automatic']['cpu']['total'] : '',
                    "manufacturer" => $manufacturer,
                    "model" => $model,
                    "os" => array_key_exists('platform', $doc['automatic']) ? $doc['automatic']['platform'] . " " . $doc['automatic']['platform_version'] : '',
                    "chefServerName" => $doc['neumatic']['chefServerName'],
                    "chefServerFqdn" => $doc['neumatic']['chefServerFqdn'],
                    "chefVersion" => $doc['neumatic']['chefVersion'],
                    "chefVersionStatus" => $doc['neumatic']['chefVersionStatus'],
                    "ohaiTime" => $doc['neumatic']['ohaiTime'],
                    "ohaiTimeString" => $doc['neumatic']['ohaiTimeString'],
                    "ohaiTimeDiff" => $doc['neumatic']['ohaiTimeDiff'],
                    "ohaiTimeDiffString" => $doc['neumatic']['ohaiTimeDiffString'],
                    "ohaiTimeStatus" => $doc['neumatic']['ohaiTimeStatus'],
                    "environment" => $doc['chef_environment'],
                    "role" => array_key_exists('roles', $doc['automatic']) ? implode(",", $doc['automatic']['roles']) : '',
                );
            }
        }

        usort($nodes, function($a, $b) {
            return strcmp($a["hostname"], $b["hostname"]);
        });
        return $this->renderView(array(
            "success" => true,
            "logLevel" => Logger::DEBUG,
            "logOutput" => count($nodes) . " nodes returned for Chef server " . $this->chefServer,
            "chefServer" => $this->chefServer,
            "nodes" => $nodes
        ));
    }

    public function getNodesCSVAction() {
        $criteria = array();

        /*
        if ($this->chefServer != "ALL") {
            $criteria = array("neumatic.chefServerFqdn" => "{$this->chefServer}");
        }
        */

        $cursor = $this->_collection->find(
            $criteria,
            array(
                "automatic.hostname" => 1,
                "automatic.fqdn" => 1,
                "neumatic.chefServerName" => 1,
                "neumatic.chefServerFqdn" => 1,
                "neumatic.chefVersion" => 1,
                "neumatic.chefVersionStatus" => 1,
                "neumatic.ohaiTime" => 1,
                "neumatic.ohaiTimeString" => 1,
                "neumatic.ohaiTimeDiff" => 1,
                "neumatic.ohaiTimeDiffString" => 1,
                "neumatic.ohaiTimeStatus" => 1,
                "automatic.cpu.total" => 1,
                "automatic.memory.total" => 1,
                "automatic.dmi.system.manufacturer" => 1,
                "automatic.dmi.system.product_name" => 1,
                "automatic.platform" => 1,
                "automatic.platform_version" => 1,
            )
        );

        $nodes = array();
        foreach ($cursor as $doc) {
            if (!array_key_exists('automatic', $doc)) continue;

            if (array_key_exists('memory', $doc['automatic'])) {
                if (preg_match("/(\d+)kB/", $doc['automatic']['memory']['total'], $m)) {
                    $memory = round($m[1] / 1024 / 1024);
                } else {
                    $memory = $doc['automatic']['memory']['total'];
                }
            } else {
                $memory = "";
            }

            if (array_key_exists('dmi', $doc['automatic']) && array_key_exists('system', $doc['automatic']['dmi'])) {
                $manufacturer = $doc['automatic']['dmi']['system']['manufacturer'];
                $model = $doc['automatic']['dmi']['system']['product_name'];
                if (preg_match("/ProLiant ([\w\d\s]+)/", $model, $m)) {
                    $model = $m[1];
                } else if ($model == "VMware Virtual Platform") {
                    $model = "VMWare VM";
                }
            } else {
                $manufacturer = "";
                $model = "";
            }

            if (array_key_exists('name', $doc)) {
                $name = $doc['name'];
            } else if (array_key_exists('hostname', $doc['automatic'])) {
                $name = $doc['automatic']['hostname'];
            } else if (array_key_exists("node", $doc) && array_key_exists("name", $doc['node'])) {
                $name = $doc['node']['name'];
            } else {
                $name = "";
            }

            if ($name != "") {
                $nodes[] = array(
                    "fqdn" => array_key_exists('fqdn', $doc['automatic']) ? $doc['automatic']['fqdn'] : '',
                    "manufacturer" => $manufacturer,
                    "model" => $model,
                    "memory" => $memory,
                    "numCpu" => array_key_exists('cpu', $doc['automatic']) ? $doc['automatic']['cpu']['total'] : '',
                    "os" => array_key_exists('platform', $doc['automatic']) ? $doc['automatic']['platform'] . " " . $doc['automatic']['platform_version'] : '',
                    "chefServerName" => $doc['neumatic']['chefServerName'],
                    "chefServerFqdn" => $doc['neumatic']['chefServerFqdn'],
                    "chefVersion" => $doc['neumatic']['chefVersion'],
                    "chefVersionStatus" => $doc['neumatic']['chefVersionStatus'],
                    "ohaiTime" => $doc['neumatic']['ohaiTime'],
                    "ohaiTimeString" => $doc['neumatic']['ohaiTimeString'],
                    "ohaiTimeDiff" => $doc['neumatic']['ohaiTimeDiff'],
                    "ohaiTimeDiffString" => $doc['neumatic']['ohaiTimeDiffString'],
                    "ohaiTimeStatus" => $doc['neumatic']['ohaiTimeStatus'],
                    "hostname" => $name,
                );
            }
        }

        usort($nodes, function($a, $b) {
            return strcmp($a["hostname"], $b["hostname"]);
        });

        $output = '"FQDN","Manufacturer","Model","Memory","CPUs","OS","Chef Server","Chef Version","Ohai Time","Ohai Time Diff","Ohai Time Status"' . "\n";
        foreach ($nodes as $n) {
            $output .= '"' . $n['fqdn'] . '"' .
                ',"' . $n['manufacturer'] . '"' .
                ',"' . $n['model'] . '"' .
                ',"' . $n['memory'] . '"' .
                ',"' . $n['numCpu'] . '"' .
                ',"' . $n['os'] . '"' .
                ',"' . $n['chefServerFqdn'] . '"' .
                ',"' . $n['chefVersion'] . '"' .
                ',"' . $n['ohaiTimeString'] . '"' .
                ',"' . $n['ohaiTimeDiffString'] . '"' .
                ',"' . $n['ohaiTimeStatus'] . '"' . "\n";
        }
        return $this->renderView($output, 'pre');
    }


    // *****************************************************************************************************************
    // Private methods
    // *****************************************************************************************************************

}
