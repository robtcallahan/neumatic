<?php 
namespace Vmwarephp;
 
class Module
{
    public function getAutoloaderConfig()
    {
         
        return array(
            'Zend\Loader\StandardAutoloader' => array(
                'namespaces' => array(
                    __NAMESPACE__ => __DIR__,
                ),
            ),
        );
 
    }
 
}