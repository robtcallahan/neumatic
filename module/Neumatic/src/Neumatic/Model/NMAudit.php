<?php

namespace Neumatic\Model;

class NMAudit
{
  	protected $id;
  	protected $userId;
    protected $userName;
    protected $dateTime;
    protected $ipAddress;
    protected $method;
    protected $uri;
    protected $controller;
    protected $function;
    protected $parameters;
    protected $descr;

    /**
     * Keeps track of properties that have their values changed
     *
     * @var array
     */
    protected $changes = array();

    /**
     * @return string
     */
    public function __toString()
    {
        $return = "";
        foreach (get_class_vars(__CLASS__) as $prop => $x) {
            if (property_exists($this, $prop)) {
                $return .= sprintf("%-25s => %s\n", $prop, $this->$prop);
            }
        }
        return $return;
    }

    /**
     * @return object
     */
    public function toObject()
    {
        $obj = (object)array();
        foreach (get_class_vars(__CLASS__) as $prop => $x) {
            if (property_exists($this, $prop)) {
                $obj->$prop = $this->$prop;
            }
        }
        return $obj;
    }


    // *******************************************************************************
    // Getters and Setters
    // *******************************************************************************

    /**
     * @param $prop
     * @return mixed
     */
    public function get($prop)
    {
        return $this->$prop;
    }

    /**
     * @param $prop
     * @param $value
     * @return mixed
     */
    public function set($prop, $value)
    {
        $this->$prop = $value;
        return $this;
    }

    /**
     * @return array
     */
    public function getChanges()
    {
        return $this->changes;
    }

    /**
     *
     */
    public function clearChanges()
    {
        $this->changes = array();
    }

    /**
     * @param $value
     */
    private function updateChanges($value)
    {
        $trace = debug_backtrace();

        // get the calling method name, eg., setSysId
        $callerMethod = $trace[1]["function"];

        // perform a replace to remove "set" from the method name and change first letter to lowercase
        // so, setSysId becomes sysId. This will be the property name that needs to be added to the changes array
        $prop = preg_replace_callback(
            "/^set(\w)/",
            function ($matches) {
                return strtolower($matches[1]);
            },
            $callerMethod
        );

        // update the changes array to keep track of this properties orig and new values
        if ($this->$prop != $value) {
            if (!array_key_exists($prop, $this->changes)) {
                $this->changes[$prop] = (object)array(
                    'originalValue' => $this->$prop,
                    'modifiedValue' => $value
                );
            } else {
                $this->changes[$prop]->modifiedValue = $value;
            }
        }
    }

    /**
     * @param mixed $id
     * @return $this
     */
    public function setId($id)
    {
        $this->updateChanges(func_get_arg(0));
        $this->id = $id;
        return $this;
    }

    /**
     * @return mixed
     */
    public function getId()
    {
        return $this->id;
    }

    /**
     * @param mixed $parameters
     * @return $this
     */
    public function setParameters($parameters) {
        $this->updateChanges(func_get_arg(0));
        $this->parameters = $parameters;
        return $this;
    }

    /**
     * @return mixed
     */
    public function getParameters() {
        return $this->parameters;
    }

    /**
     * @param mixed $controller
     * @return $this
     */
    public function setController($controller) {
        $this->updateChanges(func_get_arg(0));
        $this->controller = $controller;
        return $this;
    }

    /**
     * @return mixed
     */
    public function getController() {
        return $this->controller;
    }

    /**
     * @param mixed $dateTime
     * @return $this
     */
    public function setDateTime($dateTime) {
        $this->updateChanges(func_get_arg(0));
        $this->dateTime = $dateTime;
        return $this;
    }

    /**
     * @return mixed
     */
    public function getDateTime() {
        return $this->dateTime;
    }

    /**
     * @param mixed $descr
     * @return $this
     */
    public function setDescr($descr) {
        $this->updateChanges(func_get_arg(0));
        $this->descr = $descr;
        return $this;
    }

    /**
     * @return mixed
     */
    public function getDescr() {
        return $this->descr;
    }

    /**
     * @param mixed $function
     * @return $this
     */
    public function setFunction($function) {
        $this->updateChanges(func_get_arg(0));
        $this->function = $function;
        return $this;
    }

    /**
     * @return mixed
     */
    public function getFunction() {
        return $this->function;
    }

    /**
     * @param mixed $ipAddress
     * @return $this
     */
    public function setIpAddress($ipAddress) {
        $this->updateChanges(func_get_arg(0));
        $this->ipAddress = $ipAddress;
        return $this;
    }

    /**
     * @return mixed
     */
    public function getIpAddress() {
        return $this->ipAddress;
    }

    /**
     * @param mixed $method
     * @return $this
     */
    public function setMethod($method) {
        $this->updateChanges(func_get_arg(0));
        $this->method = $method;
        return $this;
    }

    /**
     * @return mixed
     */
    public function getMethod() {
        return $this->method;
    }

    /**
     * @param mixed $uri
     * @return $this
     */
    public function setUri($uri) {
        $this->updateChanges(func_get_arg(0));
        $this->uri = $uri;
        return $this;
    }

    /**
     * @return mixed
     */
    public function getUri() {
        return $this->uri;
    }

    /**
     * @param mixed $userId
     * @return $this
     */
    public function setUserId($userId) {
        $this->updateChanges(func_get_arg(0));
        $this->userId = $userId;
        return $this;
    }

    /**
     * @return mixed
     */
    public function getUserId() {
        return $this->userId;
    }

    /**
     * @param mixed $userName
     * @return $this
     */
    public function setUserName($userName) {
        $this->updateChanges(func_get_arg(0));
        $this->userName = $userName;
        return $this;
    }

    /**
     * @return mixed
     */
    public function getUserName() {
        return $this->userName;
    }


}