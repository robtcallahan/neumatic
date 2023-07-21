<?php

namespace NeumaticTest\Controller;

use Neumatic\Controller\GitLabController;
use Zend\Http\Request;
use Zend\Http\Response;
use Zend\Mvc\MvcEvent;
use Zend\Mvc\Router\RouteMatch;
use PHPUnit_Framework_TestCase;
use Zend\Test\PHPUnit\Controller\AbstractHttpControllerTestCase;
use Zend\Stdlib\Parameters;


class GitLabControllerTest extends AbstractHttpControllerTestCase {
    protected $traceError = true;

    public function setUp() {
        $_SERVER['SERVER_NAME'] = "stlabvnode30.va.neustar.com";
        set_include_path(get_include_path().PATH_SEPARATOR.'/var/www/html/neumatic');
       
        include '/var/www/html/neumatic/module/Neumatic/test/config/application.config.php';
       
        $bootstrap        = \Zend\Mvc\Application::init(include 'config/application.config.php');
        $this->controller = new GitLabController();
        $this->request    = new Request();
        $this->routeMatch = new RouteMatch(array('controller' => 'git'));
        $this->event      = $bootstrap->getMvcEvent();
        $this->event->setRouteMatch($this->routeMatch);
        $this->controller->setEvent($this->event);
        $this->controller->setEventManager($bootstrap->getEventManager());
        $this->controller->setServiceLocator($bootstrap->getServiceManager()); 
        
        $this->_config = $this->controller->getServiceLocator()->get('Config');
        $this->request->setQuery(new Parameters(array('chef_server'=>'stopcdvvcm01.va.neustar.com')));
        
        $this->testProject = "does_nothing";
        
        parent::setUp();
    }

    public function testIndexActionCanBeAccessed() {
        echo __FUNCTION__;
        $this->routeMatch->setParam('action', 'index');
        
        $result   = $this->controller->dispatch($this->request);
        $response = $this->controller->getResponse();

        $this->assertEquals(200, $response->getStatusCode());
        $this->assertInstanceOf('Zend\View\Model\ViewModel', $result);
        $this->assertEquals('This controller has no output from index.', $result->message);
        

        
    }
    
    public function testGetProjectsAction(){
        echo __FUNCTION__;
        
        $this->routeMatch->setParam('action', 'getProjects');
        
        $result   = $this->controller->dispatch($this->request);
        $response = $this->controller->getResponse();

        $this->assertEquals(200, $response->getStatusCode());
        $this->assertInstanceOf('Zend\View\Model\ViewModel', $result);
        
        $this->assertTrue($result->success);
        $this->assertTrue(isset($result->projects[0]['name']));
        
    }
    
    public function testGetProjectDetailsAction(){
        echo __FUNCTION__;
        
        $this->routeMatch->setParam('action', 'getProjectDetails');
        $this->routeMatch->setParam('param1', $this->testProject);
        $result   = $this->controller->dispatch($this->request);
        $response = $this->controller->getResponse();

        $this->assertEquals(200, $response->getStatusCode());
        $this->assertInstanceOf('Zend\View\Model\ViewModel', $result);
        
        $this->assertTrue($result->success);
        $this->assertTrue(isset($result->project['name']));
        $this->assertEquals($this->testProject, $result->project['name']);
        
    }
   
  
    
    

}
