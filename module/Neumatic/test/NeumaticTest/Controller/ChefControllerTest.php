<?php

namespace NeumaticTest\Controller;

use Neumatic\Controller\ChefController;
use Zend\Http\Request;
use Zend\Http\Response;
use Zend\Mvc\MvcEvent;
use Zend\Mvc\Router\RouteMatch;
use PHPUnit_Framework_TestCase;
use Zend\Test\PHPUnit\Controller\AbstractHttpControllerTestCase;
use Zend\Stdlib\Parameters;
use Zend\Http\Header\Cookie;


class NeumaticControllerTest extends AbstractHttpControllerTestCase {
    protected $traceError = true;

    public function setUp() {
        $_SERVER['SERVER_NAME'] = "stlabvnode30.va.neustar.com";
        set_include_path(get_include_path().PATH_SEPARATOR.'/var/www/html/neumatic');
       
        include '/var/www/html/neumatic/module/Neumatic/test/config/application.config.php';
       
        $bootstrap        = \Zend\Mvc\Application::init(include 'config/application.config.php');
        $this->controller = new ChefController();
        $this->request    = new Request();
        $this->routeMatch = new RouteMatch(array('controller' => 'chef'));
        $this->event      = $bootstrap->getMvcEvent();
        $this->event->setRouteMatch($this->routeMatch);
        $this->controller->setEvent($this->event);
        $this->controller->setEventManager($bootstrap->getEventManager());
        $this->controller->setServiceLocator($bootstrap->getServiceManager()); 
        
        $this->_config = $this->controller->getServiceLocator()->get('Config');
        $this->request->setQuery(new Parameters(array('chef_server'=>'stopcdvvcm01.va.neustar.com')));
        
        $this->testEnv1 = "neumaticUnitTest1";
        $this->testEnv2 = "neumaticUnitTest2";
        
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
    
   
    /************************ Servers *****************************/
  
    public function testGetServersAction(){
        echo __FUNCTION__;
        $this->routeMatch->setParam('action', 'getServers');
        
        $result   = $this->controller->dispatch($this->request);
        $response = $this->controller->getResponse();

        $this->assertEquals(200, $response->getStatusCode());
        $this->assertInstanceOf('Zend\View\Model\ViewModel', $result);
        
        $this->assertEquals('stopcdvvcm01.va.neustar.com', $result->servers[0]['name']);
        
        
    }
 
    /********************** userGroups **************************/
 
    public function testGetUserGroupsAction(){
        echo __FUNCTION__;
        $this->routeMatch->setParam('action', 'getUserGroups');
        $this->routeMatch->setParam('param1', $this->_config['testUsername']);
        
        
        $result   = $this->controller->dispatch($this->request);
        $response = $this->controller->getResponse();

        $this->assertEquals(200, $response->getStatusCode());
        $this->assertInstanceOf('Zend\View\Model\ViewModel', $result);
        
        $this->assertEquals(1, $result->success);
        $this->assertEquals("STSOPS", $result->groups[0]);
        
    }
    
   
    /******************** Environment ***************************/
    
    public function testCheckAuthorizedEnvironmentEditAction(){
        // First test success, then test failure 
        echo __FUNCTION__;
        $this->routeMatch->setParam('action', 'checkAuthorizedEnvironmentEdit');
        $this->routeMatch->setParam('param1', $this->testEnv1);
        
        //$this->request->setQuery(new Parameters(array('chef_server'=>'stopvprchef01.va.neustar.com')));
        $result   = $this->controller->dispatch($this->request);
        
        $response = $this->controller->getResponse();

        $this->assertEquals(200, $response->getStatusCode());
        $this->assertInstanceOf('Zend\View\Model\ViewModel', $result);
        
        $this->assertEquals(true, $result->success);
        $this->assertEquals(true, $result->authorized);
        
        $this->routeMatch->setParam('param1', $this->testEnv2);
        $result   = $this->controller->dispatch($this->request);
        
        $response = $this->controller->getResponse();
        
        $this->assertEquals(200, $response->getStatusCode());
        $this->assertInstanceOf('Zend\View\Model\ViewModel', $result);
        
        $this->assertEquals(true, $result->success);
        $this->assertEquals(false, $result->authorized);
    }
    
    public function testCheckEnvironmentExistsAction(){
        echo __FUNCTION__;
        // success test 
        $this->routeMatch->setParam('action', 'checkEnvironmentExists');
        
        $this->routeMatch->setParam('param1', $this->testEnv1);
        
        
        $result   = $this->controller->dispatch($this->request);
        
        $response = $this->controller->getResponse();

        $this->assertEquals(200, $response->getStatusCode());
        $this->assertInstanceOf('Zend\View\Model\ViewModel', $result);
        
        $this->assertEquals(true, $result->success);
        $this->assertEquals(true, $result->exists);
        
        // failure test 
        $this->routeMatch->setParam('param1', "thisDoesNotExist");
        
        
        $result   = $this->controller->dispatch($this->request);
        
        $response = $this->controller->getResponse();

        $this->assertEquals(200, $response->getStatusCode());
        $this->assertInstanceOf('Zend\View\Model\ViewModel', $result);
        
        $this->assertEquals(true, $result->success);
        $this->assertEquals(false, $result->exists);
        
                
    }
    
    public function testGetEnvironmentAction(){
        echo __FUNCTION__;
        
        $this->routeMatch->setParam('action', 'getEnvironment');
        
        $this->routeMatch->setParam('param1', $this->testEnv1);
        
        $result   = $this->controller->dispatch($this->request);
        
        $response = $this->controller->getResponse();

        $this->assertEquals(200, $response->getStatusCode());
        $this->assertInstanceOf('Zend\View\Model\ViewModel', $result);
        
        $this->assertEquals(true, $result->success);
        $this->assertEquals($this->testEnv1, $result->environment->name);
        
        
    }
    

    

}
