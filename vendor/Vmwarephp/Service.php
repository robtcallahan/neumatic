<?php

namespace Vmwarephp;

use Vmwarephp\Exception\Soap;
use Vmwarephp\Factory\SoapClient;
use Vmwarephp\Factory\SoapMessage;

class Service {
	private $soapClient;
	private $vhost;
	private $typeConverter;
	private $serviceContent;
	private $session;
    private $sessionManager;
	private $clientFactory;

    /**
     * @param Vhost $vhost
     * @param SoapClient $soapClientFactory
     */
    function __construct(Vhost $vhost, SoapClient $soapClientFactory = null) {
		$this->vhost = $vhost;
		$this->clientFactory = $soapClientFactory ? : new SoapClient();
		$this->soapClient = $this->clientFactory->make($this->vhost);
		$this->typeConverter = new TypeConverter($this);
	}

    /**
     * @param $method
     * @param $arguments
     * @return null|ManagedObject
     */
    function __call($method, $arguments) {
		if ($this->isMethodAPropertyRetrieval($method)) return $this->getQueriedProperty($method, $arguments);
		$managedObject = $arguments[0];
		$actionArguments = isset($arguments[1]) ? $arguments[1] : array();
		return $this->makeSoapCall($method, SoapMessage::makeUsingManagedObject($managedObject, $actionArguments));
	}

    /**
     * @param $objectType
     * @param $propertiesToCollect
     * @return mixed
     */
    function findAllManagedObjects($objectType, $propertiesToCollect) {
		$propertyCollector = $this->getPropertyCollector();
		return $propertyCollector->collectAll($objectType, $propertiesToCollect);
	}

    /**
     * @param $objectType
     * @param $referenceId
     * @param $propertiesToCollect
     * @return mixed
     */
    function findOneManagedObject($objectType, $referenceId, $propertiesToCollect) {
		$propertyCollector = $this->getPropertyCollector();
		return $propertyCollector->collectPropertiesFor($objectType, $referenceId, $propertiesToCollect);
	}

    /**
     * @param $objectType
     * @param $name
     * @param array $propertiesToCollect
     * @return mixed|null
     */
    function findManagedObjectByName($objectType, $name, $propertiesToCollect = array()) {
		$propertiesToCollect = array_merge($propertiesToCollect, array('name'));
		$allObjects = $this->findAllManagedObjects($objectType, $propertiesToCollect);
		$objects = array_filter($allObjects, function ($object) use ($name) {
			return $object->name == $name;
		});
		return empty($objects) ? null : end($objects);
	}

    /**
     * @return mixed
     */
    function connect() {
		if ($this->session) {
			return $this->session;
		}
		$this->sessionManager = $this->getSessionManager();
		$this->session = $this->sessionManager->acquireSession($this->vhost->username, $this->vhost->password);
		return $this->session;
	}

    /**
     * @return null|ManagedObject
     */
    function getServiceContent() {
		if (!$this->serviceContent)
			$this->serviceContent = $this->makeSoapCall('RetrieveServiceContent', SoapMessage::makeForServiceInstance());
		return $this->serviceContent;
	}

    /**
     * @param $response
     * @return null|ManagedObject
     */
    public function convertResponse($response) {
		$responseVars = get_object_vars($response);
		if (isset($response->returnval) || (array_key_exists('returnval', $responseVars) && is_null($responseVars['returnval'])))
			return $this->typeConverter->convert($response->returnval);
		return $this->typeConverter->convert($response);
	}

    /**
     * @param $method
     * @param $soapMessage
     * @return null|ManagedObject
     * @throws Exception\Soap
     */
    public function makeSoapCall($method, $soapMessage) {
		$this->soapClient->_classmap = $this->clientFactory->getClientClassMap();
		try {
			$result = $this->soapClient->$method($soapMessage);
		} catch (\SoapFault $soapFault) {
			$this->soapClient->_classmap = null;
            throw new Soap($soapFault);
		}
		$this->soapClient->_classmap = null;
		return $this->convertResponse($result);
	}

    /**
     * @return mixed
     */
    public function getLastRequest() {
        return $this->soapClient->__last_request;
    }

    public function getSoapClient() {
        return $this->soapClient;
    }

    /**
     * @param $method
     * @param $arguments
     * @return null
     */
    private function getQueriedProperty($method, $arguments) {
		$propertyToRetrieve = $this->generateNameForThePropertyToRetrieve($method);
		$content = $this->getServiceContent();
		if (isset($content->$propertyToRetrieve)) return $content->$propertyToRetrieve;
		$managedObject = $arguments[0];
		$foundManagedObject = $this->findOneManagedObject($managedObject->getReferenceType(),
			$managedObject->getReferenceId(), array($propertyToRetrieve));
		if (!isset($foundManagedObject->$propertyToRetrieve)) return null;
		return $foundManagedObject->$propertyToRetrieve;
	}

    /**
     * @param $calledMethod
     * @return int
     */
    private function isMethodAPropertyRetrieval($calledMethod) {
		return preg_match('/^get/', $calledMethod);
	}

    /**
     * @param $calledMethod
     * @return string
     */
    private function generateNameForThePropertyToRetrieve($calledMethod) {
		return lcfirst(substr($calledMethod, 3));
	}
}
