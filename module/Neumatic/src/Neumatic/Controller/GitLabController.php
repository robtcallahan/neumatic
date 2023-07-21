<?php

namespace Neumatic\Controller;

class GitLabController extends Base\BaseController {

    protected $gitLabConfig;
    protected $chefServer;

    public function onDispatch(\Zend\Mvc\MvcEvent $e) {
        if ($this->params()->fromQuery('chef_server')) {
            $this->chefServer = $this->params()->fromQuery('chef_server');
        }
        
        if(isset($this->chefServer)){
            if($this->chefServer == "" OR $this->chefServer == null){
                if(isset($_COOKIE['chef_server'])){
                    $this->chefServer = $_COOKIE['chef_server'];
                }else{
                    $this->chefServer = $this->_config['chef']['default']['server'];
                    setcookie('chef_server', $this->chefServer);
                }
            }    
        }else{
            $this->chefServer = $this->_config['chef']['default']['server'];
            setcookie('chef_server', $this->chefServer);
        }

        $this->gitLabConfig = $this->_config['chef'][$this->chefServer]['GitLab'];

        return parent::onDispatch($e);
    }

    public function indexAction() {

        return $this->renderview(array("message"=>"This controller has no output from index."));
    }

    public function getProjectsAction() {

        if ($projects = $this->getProjects()) {
            return $this->renderview(array(
                "success"=>true, 
                "projects"=>$projects), "json");
        }
        return $this->renderview(array("success"=>false, "message"=>$projects), "json");
    }

    private function getProjects() {

        $projects = array();
        foreach ($this->gitLabConfig AS $groupName=>$group) {

            $URL = $group['server']."/api/v3/groups/".$group['groupId']."?private_token=".$group['private_token'];

            $group = $this->curlGetUrl($URL);
            //$group = json_decode($group);
            foreach ($group->projects AS $project) {
                $project = $this->object_to_array($project);
                $projects[] = $project;
            }
        }
        usort($projects, function(array $a, array $b) {
            return strnatcasecmp($a['name'], $b['name']);
        });
        if (is_array($projects)) {
            return $projects;
        }
        return false;
    }

    public function getProjectDetailsAction() {
        $projectId = $this->params()->fromRoute('param1');
        if ($project = $this->getProjectDetails($projectId)) {
            return $this->renderview(array(
                "success"=>true, 
                "project"=>$project
                ));

        }

        return $this->renderview(array(
            "success"=>false, 
            "message"=>"Error getting details for project ".$projectId
            ));

    }

    private function getProjectDetails($projectId) {
        $projects = $this->getProjects();

        foreach ($projects AS $project) {
            if ($project['id'] == $projectId OR $project['name'] == $projectId) {
                return $project;
            }
        }
        return false;
    }


    public function getProjectTagsAction() {
        $projectId = $this->params()->fromRoute('param1');

        $projectDetails = $this->getProjectDetails($projectId);

        if (!is_numeric($projectId)) {
            $projectId = $projectDetails['id'];
        }

        $groupName = $projectDetails['namespace']['name'];

        $server = $this->gitLabConfig[$groupName]['server'];
        $token =  $this->gitLabConfig[$groupName]['private_token'];

        $URL = $server."/api/v3/projects/".$projectId."/repository/tags?private_token=".$token;
       

        $tags = $this->curlGetUrl($URL);

        if (isset($tags)) {

            return $this->renderview(array("success"=>'1', "tags"=>$tags), "json");
        }

        return $this->renderview(array("success"=>'0', "message"=>$tags), "json");

    }

    
    public function importGitTagAction() {

        
        $projectId = $this->params()->fromRoute('param1');
        $tag = $this->params()->fromRoute('param2');
        
        $project = $this->getProjectDetails($projectId);
        $projectName = strtolower($project['name']);
        $groupName = $project['namespace']['path'];
        
        $server = $this->gitLabConfig[$groupName]['server'];
        $token = $this->gitLabConfig[$groupName]['private_token'];

       
        $command = "sudo -u stsapps /var/www/html/neumatic/bin/importCookbookFromGitTag.sh ".$projectName." ".$tag." ".$groupName." ".$this->chefServer." 2>&1";
        
        exec($command, $result);
        $result_out = "";
        foreach($result AS $res){
            if(stristr($res, "ERROR:") OR stristr($res, "WARNING:")){
                $result_out .= $res." \n\r";
            }    
        }
        
        if (stristr(end($result), "Uploaded 1 cookbook.")) {
            $output = array("success"=>true, "message"=>$result);
        } else {
            //
            $output = array("success"=>false, "message"=>$result_out);
        }
        
        $uid = $this->_user->getUsername(); 
        $this->cachePathBase = "/var/www/html/neumatic/data/cache/".$uid."/Chef/".$this->chefServer."/";
        $this->clearCache("getCookbooks");
        return $this->renderview($output, "json");
    }

    public function getProjectIdFromNameAction() {
        //due to bugs in the current version of the GitLab API deployed at git.nexgen.neustar.biz there is no easy way to get a project ID from the name.
        $projectGroup = $this->params()->fromRoute('param1');
        $projectName = $this->params()->fromRoute('param2');

        if ($projectId = $this->getProjectIdFromName($projectGroup, $projectName)) {
            return $this->renderview(array("success"=>'1', "id"=>$projectId), "json");
        }
        return $this->renderview(array("success"=>'0', "message"=>"No match for project name ".$projectName), "json");
    }

    private function getProjectIdFromName($projectGroup, $projectName) {
        $server = $this->gitLabConfig[$projectGroup]['server'];
        $token = $this->gitLabConfig[$projectGroup]['private_token'];
        $groupId = $this->gitLabConfig[$projectGroup]['groupId'];

        $URL = $server."/api/v3/groups/".$groupId."?private_token=".$token;

        $group = $this->curlGetUrl($URL);
        //$group = json_decode($group);
        foreach ($group->projects AS $project) {
            $project = $this->object_to_array($project);
            if ($project['name'] == $projectName) {
                return $project['id'];
            }
        }
        return false;
    }

    private function object_to_array($obj) {
        $arr = array();
        $arrObj = is_object($obj) ? get_object_vars($obj) : $obj;
        foreach ($arrObj as $key=>$val) {
            $val = (is_array($val) || is_object($val)) ? $this->object_to_array($val) : $val;
            $arr[$key] = $val;
        }
        return $arr;
    }

}
