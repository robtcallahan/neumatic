<?php

namespace Vmwarephp;

use Vmwarephp\Exception as Ex;

class Vhost {
	private $service;

    /**
     * @param $host
     * @param $username
     * @param $password
     */
    public function __construct($host, $username, $password) {
		$this->host = $host;
		$this->username = $username;
		$this->password = $password;
	}

    /**
     * @return string
     */
    public function getPort() {
		$port = parse_url($this->host, PHP_URL_PORT);
		return $port ? : '443';
	}

    /**
     * @param $propertyName
     * @return mixed
     * @throws \InvalidArgumentException
     */
    public function __get($propertyName) {
		if (!isset($this->$propertyName)) throw new \InvalidArgumentException('Property ' . $propertyName . ' not set on this object!');
		return $this->$propertyName;
	}

    /**
     * @param $propertyName
     * @param $value
     */
    public function __set($propertyName, $value) {
		$this->validateProperty($propertyName, $value);
		$this->$propertyName = $value;
	}

    /**
     * @param $method
     * @param $arguments
     * @return mixed
     */
    public function __call($method, $arguments) {
		if (!$this->service) $this->initializeService();
        return call_user_func_array(array($this->service, __FUNCTION__), func_get_args());
	}

    public function destoryTask() {
        if (!$this->service) $this->initializeService();
        return call_user_func_array(array($this->service, 'Destroy_Task'), func_get_args());
    }

    public function powerOnVMTask() {
        if (!$this->service) $this->initializeService();
        return call_user_func_array(array($this->service, 'PowerOnVM_Task'), func_get_args());
    }

    public function powerOffVMTask() {
        if (!$this->service) $this->initializeService();
        return call_user_func_array(array($this->service, 'PowerOffVM_Task'), func_get_args());
    }

    public function recommendDatastores() {
        if (!$this->service) $this->initializeService();
        return call_user_func_array(array($this->service, 'RecommendDatastores'), func_get_args());
    }

    public function findAllManagedObjects() {
        if (!$this->service) $this->initializeService();
        return call_user_func_array(array($this->service, __FUNCTION__), func_get_args());
    }

    public function findOneManagedObject() {
        if (!$this->service) $this->initializeService();
        return call_user_func_array(array($this->service, __FUNCTION__), func_get_args());
    }

    public function findManagedObjectByName() {
        $method = 'findManagedObjectByName';
        if (!$this->service) $this->initializeService();
        return call_user_func_array(array($this->service, $method), func_get_args());
    }

    /**
     * @return mixed
     */
    public function getApiType() {
		return $this->getServiceContent()->about->apiType;
	}

    /**
     * @param \Vmwarephp\Service $service
     */
    public function changeService(\Vmwarephp\Service $service) {
		$this->service = $service;
	}

    /**
     * @return \Vmwarephp\Service
     */
    public function getService() {
        $this->initializeService();
        return $this->service;
    }

    /**
     *
     */
    private function initializeService() {
		if (!$this->service) {
            $this->service = \Vmwarephp\Factory\Service::makeConnected($this);
        }
	}

    public function disconnect() {
        if ($this->service) {
            $this->service->logout($this->service->getSessionManager());
        }
    }


    /**
     * @param $propertyName
     * @param $value
     * @throws Exception\InvalidVhost
     */
    private function validateProperty($propertyName, $value) {
		if (in_array($propertyName, array('host', 'username', 'password')) && empty($value))
			throw new Exception\InvalidVhost('Vhost ' . ucfirst($propertyName) . ' cannot be empty!');
	}
}
