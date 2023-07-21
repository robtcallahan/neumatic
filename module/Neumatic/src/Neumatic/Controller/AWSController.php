<?php

namespace Neumatic\Controller;

use Zend\Json\Server\Exception\ErrorException;
use Zend\View\Model\JsonModel;
use Zend\Mvc\MvcEvent;

class AWSController extends Base\BaseController
{
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

        return $this->renderview(array("error" => "This controller has no output from index. Eventually I would like to display the documentation here."));
    }

    // *****************************************************************************************************************
    // Private methods
    // *****************************************************************************************************************

}
