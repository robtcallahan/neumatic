<?php

namespace Neumatic\Controller;

use Zend\Json\Server\Exception\ErrorException;
use Zend\View\Model\JsonModel;
use Zend\Mvc\MvcEvent;

use STS\Util\Curl;

class JIRAController extends Base\BaseController {

    /**
    /**
     * Internal JIRA id for Host Build Automation
     * @var int
     */
    private $hbaId = 11721;

    /**
     * Host Build Automation project key
     * @var string
     */
    private $hbaKey = 'HBA';

    /**
     * Curl instance
     * @var mixed
     */
    private $curl;

    /**
     * @param MvcEvent $e
     * @return mixed
     */
    public function onDispatch(MvcEvent $e) {
        return parent::onDispatch($e);
    }

    /**
     * This action should never be called
     *
     * @return JsonModel
     */
    public function indexAction() {

        return $this->renderview(array("error"=>"This controller has no output from index. Eventually I would like to display the documentation here."));
    }

    /**
     * Obtain the list of issues types for project
     *
     * @return JsonModel
     */
    public function getIssueTypesAction() {
        /** @var Curl $curl */
        $curl = $this->curl;

        $issueTypes = array(
            array(
                "id" => 1,
                "name" => "Bug",
                "descr" => "A problem which impairs or prevents the functions of the product."
            ),
            array(
                "id" => 4,
                "name" => "Improvement",
                "descr" => "An improvement or enhancement to an existing feature or task."
            ),
        );

        /*
        $curl->setData('');
        $curl->setType('GET');
        $curl->setUrl('https://jira.nexgen.neustar.biz/rest/api/latest/issue/createmeta?projectKeys=' . $this->hbaKey);
        $curl->send();
        $response = $curl->getBody();

        try {
            $json = json_decode($response);
        } catch (\ErrorException $e) {
            return $this->renderView(array("success" => false, "message" => "Unable to decode JSON response"));
        }
        if (!preg_match("/^2/", $curl->getStatus()) || !property_exists($json, 'projects')) {
            return $this->renderView(array("success" => false, "message" => "Unable to obtain issue types"));
        }

        $issueTypes = $json->projects[0]->issuetypes;
        $data = array();
        foreach ($issueTypes as $issueType) {
            $data[] = array(
                "id" => $issueType->id,
                "name" => $issueType->name,
                "description" => $issueType->description
            );
        }
        */
        return $this->renderView(array("success" => true, "issueTypes" => $issueTypes));
    }

    /**
     * Submit a bug/feedback report be creating a new JIRA issue
     *
     * @return JsonModel
     */
    public function submitBugReportAction() {
        $issueTypeId = $this->params()->fromPost('issueTypeId');
        $summary = $this->params()->fromPost('summary');
        $description = $this->params()->fromPost('description');
        $acceptanceCriteria = $this->params()->fromPost('acceptanceCriteria');

        // we're authenticated
        // define the issue values
        $issue = array(
            "fields" => array(
                "project" => array(
                    // Host Build Automation Project
                    "id" => $this->hbaId
                ),
                // from the user
                "summary" => $summary,
                "issuetype" => array(
                    // 1 - bug
                    "id" => $issueTypeId
                ),
                "labels" => array(
                    "Neumatic"
                ),
                "description" => $description,
                "customfield_10102" => $acceptanceCriteria
            )
        );

        // create the CURL instance
        $curl = new \STS\Util\Curl();
        $curl->setType("POST");
        $curl->setVerbose(false);
        $curl->setHeaderOut(false);
        $curl->setUseCookies(false);

        // set the auth header with PHP auth creds
        $curl->setHeader(array("Authorization: Basic " . base64_encode("{$this->_user->getUsername()}:{$this->_user->get('password')}")));

        // make the call to create the new issue
        $curl->setUrl("https://jira.nexgen.neustar.biz/rest/api/latest/issue");
        $curl->setData(json_encode($issue));
        $curl->send();
        $response = $curl->getBody();

        try {
            $json = json_decode($response);
        } catch (\ErrorException $e) {
            return $this->renderView(
                        array("success" => false,
                              "message" => "Unable to decode result into JSON. Unable to create JIRA issue"));
        }

        if (!preg_match("/^2/", $curl->getStatus())) {
            return $this->renderView(
                        array("success" => false,
                              "message" => "HTTP Status returned " . $curl->getStatus() . ". Unable to create JIRA issue",
                              "json" => $json));
        }
        if (!property_exists($json, 'key')) {
            return $this->renderView(
                        array("success" => false,
                              "message" => "JIRA did not return a new Issue key. Unable to create JIRA issue",
                              "json" => $json));
        }

        return $this->renderView(array("success" => true, "key" => $json->key, "id" => $json->id));
    }

    // *****************************************************************************************************************
    // Private methods
    // *****************************************************************************************************************

}
