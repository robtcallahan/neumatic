<?php

namespace Neumatic\Model;

class NMQuota
{
    protected $id;
    protected $dcUid;
    protected $ccrUid;
    protected $businessServiceName;
    protected $businessServiceId;
    protected $cpus;
    protected $memoryGB;
    protected $storageGB;

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
     * @param mixed $businessServiceId
     * @return $this
     */
    public function setBusinessServiceId($businessServiceId) {
        $this->updateChanges(func_get_arg(0));
        $this->businessServiceId = $businessServiceId;
        return $this;
    }

    /**
     * @return mixed
     */
    public function getBusinessServiceId() {
        return $this->businessServiceId;
    }

    /**
     * @param mixed $businessServiceName
     * @return $this
     */
    public function setBusinessServiceName($businessServiceName) {
        $this->updateChanges(func_get_arg(0));
        $this->businessServiceName = $businessServiceName;
        return $this;
    }

    /**
     * @return mixed
     */
    public function getBusinessServiceName() {
        return $this->businessServiceName;
    }

    /**
     * @param mixed $cpus
     * @return $this
     */
    public function setCpus($cpus) {
        $this->updateChanges(func_get_arg(0));
        $this->cpus = $cpus;
        return $this;
    }

    /**
     * @return mixed
     */
    public function getCpus() {
        return $this->cpus;
    }

    /**
     * @param mixed $memoryGB
     * @return $this
     */
    public function setMemoryGB($memoryGB) {
        $this->updateChanges(func_get_arg(0));
        $this->memoryGB = $memoryGB;
        return $this;
    }

    /**
     * @return mixed
     */
    public function getMemoryGB() {
        return $this->memoryGB;
    }

    /**
     * @param mixed $storageGB
     * @return $this
     */
    public function setStorageGB($storageGB) {
        $this->updateChanges(func_get_arg(0));
        $this->storageGB = $storageGB;
        return $this;
    }

    /**
     * @return mixed
     */
    public function getStorageGB() {
        return $this->storageGB;
    }

    /**
     * @param mixed $ccrUid
     * @return $this
     */
    public function setCcrUid($ccrUid) {
        $this->updateChanges(func_get_arg(0));
        $this->ccrUid = $ccrUid;
        return $this;
    }

    /**
     * @return mixed
     */
    public function getCcrUid() {
        return $this->ccrUid;
    }

    /**
     * @param mixed $dcUid
     * @return $this
     */
    public function setDcUid($dcUid) {
        $this->updateChanges(func_get_arg(0));
        $this->dcUid = $dcUid;
        return $this;
    }

    /**
     * @return mixed
     */
    public function getDcUid() {
        return $this->dcUid;
    }


}