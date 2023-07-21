<?php

namespace Neumatic\Model;

class NMChef
{
  	protected $id;
  	protected $serverId;
  	protected $server;
  	protected $role;
  	protected $environment;
  	protected $version;
  	protected $versionStatus;
  	protected $ohaiTime;           // formated time
    protected $ohaiTimeInt;        // internal time value
  	protected $ohaiTimeDiff;       // seconds of time between last check in and current time
  	protected $ohaiTimeDiffString; // formated value of time difference (days, hours, mins)
  	protected $ohaiTimeStatus;
  	protected $cookStartTime;
  	protected $cookEndTime;
  	protected $cookTimeString;

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
     * @param mixed $serverId
     * @return $this
     */
    public function setServerId($serverId)
    {
        $this->updateChanges(func_get_arg(0));
        $this->serverId = $serverId;
        return $this;
    }

    /**
     * @return mixed
     */
    public function getServerId()
    {
        return $this->serverId;
    }

    /**
     * @param mixed $cookEndTime
     * @return $this
     */
    public function setCookEndTime($cookEndTime) {
        $this->updateChanges(func_get_arg(0));
        $this->cookEndTime = $cookEndTime;
        return $this;
    }

    /**
     * @return mixed
     */
    public function getCookEndTime() {
        return $this->cookEndTime;
    }

    /**
     * @param mixed $cookStartTime
     * @return $this
     */
    public function setCookStartTime($cookStartTime) {
        $this->updateChanges(func_get_arg(0));
        $this->cookStartTime = $cookStartTime;
        return $this;
    }

    /**
     * @return mixed
     */
    public function getCookStartTime() {
        return $this->cookStartTime;
    }

    /**
     * @param mixed $cookTimeString
     * @return $this
     */
    public function setCookTimeString($cookTimeString) {
        $this->updateChanges(func_get_arg(0));
        $this->cookTimeString = $cookTimeString;
        return $this;
    }

    /**
     * @return mixed
     */
    public function getCookTimeString() {
        return $this->cookTimeString;
    }

    /**
     * @param mixed $environment
     * @return $this
     */
    public function setEnvironment($environment) {
        $this->updateChanges(func_get_arg(0));
        $this->environment = $environment;
        return $this;
    }

    /**
     * @return mixed
     */
    public function getEnvironment() {
        return $this->environment;
    }

    /**
     * @param mixed $ohaiTime
     * @return $this
     */
    public function setOhaiTime($ohaiTime) {
        $this->updateChanges(func_get_arg(0));
        $this->ohaiTime = $ohaiTime;
        return $this;
    }

    /**
     * @return mixed
     */
    public function getOhaiTime() {
        return $this->ohaiTime;
    }

    /**
     * @param mixed $ohaiTimeDiff
     * @return $this
     */
    public function setOhaiTimeDiff($ohaiTimeDiff) {
        $this->updateChanges(func_get_arg(0));
        $this->ohaiTimeDiff = $ohaiTimeDiff;
        return $this;
    }

    /**
     * @return mixed
     */
    public function getOhaiTimeDiff() {
        return $this->ohaiTimeDiff;
    }

    /**
     * @param mixed $ohaiTimeDiffString
     * @return $this
     */
    public function setOhaiTimeDiffString($ohaiTimeDiffString) {
        $this->updateChanges(func_get_arg(0));
        $this->ohaiTimeDiffString = $ohaiTimeDiffString;
        return $this;
    }

    /**
     * @return mixed
     */
    public function getOhaiTimeDiffString() {
        return $this->ohaiTimeDiffString;
    }

    /**
     * @param mixed $ohaiTimeStatus
     * @return $this
     */
    public function setOhaiTimeStatus($ohaiTimeStatus) {
        $this->updateChanges(func_get_arg(0));
        $this->ohaiTimeStatus = $ohaiTimeStatus;
        return $this;
    }

    /**
     * @return mixed
     */
    public function getOhaiTimeStatus() {
        return $this->ohaiTimeStatus;
    }

    /**
     * @param mixed $role
     * @return $this
     */
    public function setRole($role) {
        $this->updateChanges(func_get_arg(0));
        $this->role = $role;
        return $this;
    }

    /**
     * @return mixed
     */
    public function getRole() {
        return $this->role;
    }

    /**
     * @param mixed $server
     * @return $this
     */
    public function setServer($server) {
        $this->updateChanges(func_get_arg(0));
        $this->server = $server;
        return $this;
    }

    /**
     * @return mixed
     */
    public function getServer() {
        return $this->server;
    }

    /**
     * @param mixed $version
     * @return $this
     */
    public function setVersion($version) {
        $this->updateChanges(func_get_arg(0));
        $this->version = $version;
        return $this;
    }

    /**
     * @return mixed
     */
    public function getVersion() {
        return $this->version;
    }

    /**
     * @param mixed $versionStatus
     * @return $this
     */
    public function setVersionStatus($versionStatus) {
        $this->updateChanges(func_get_arg(0));
        $this->versionStatus = $versionStatus;
        return $this;
    }

    /**
     * @return mixed
     */
    public function getVersionStatus() {
        return $this->versionStatus;
    }

    /**
     * @param mixed $ohaiTimeInt
     * @return $this
     */
    public function setOhaiTimeInt($ohaiTimeInt) {
        $this->updateChanges(func_get_arg(0));
        $this->ohaiTimeInt = $ohaiTimeInt;
        return $this;
    }

    /**
     * @return mixed
     */
    public function getOhaiTimeInt() {
        return $this->ohaiTimeInt;
    }


}