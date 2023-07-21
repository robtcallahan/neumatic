<?php

namespace Neumatic\Controller;

use Zend\View\Model\ViewModel;

class IndexController extends Base\BaseController {

    public function indexAction() {
        return new ViewModel();
    }
}
