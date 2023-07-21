<?php

namespace Neumatic\Model;

class NMStandalone
{
    protected $id;
    protected $serverId;

    protected $iLo;
    protected $iso;

    protected $remote;      // is this a remote standalone?

    protected $distSwitch;
    protected $vlanName;
    protected $vlanId;


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
     * @param mixed $distSwitch
     * @return $this
     */
    public function setDistSwitch($distSwitch)
    {
        $this->updateChanges(func_get_arg(0));
        $this->distSwitch = $distSwitch;
        return $this;
    }

    /**
     * @return mixed
     */
    public function getDistSwitch()
    {
        return $this->distSwitch;
    }

    /**
     * @param mixed $vlanId
     * @return $this
     */
    public function setVlanId($vlanId) {
        $this->updateChanges(func_get_arg(0));
        $this->vlanId = $vlanId;
        return $this;
    }

    /**
     * @return mixed
     */
    public function getVlanId() {
        return $this->vlanId;
    }

    /**
     * @param mixed $vlanName
     * @return $this
     */
    public function setVlanName($vlanName) {
        $this->updateChanges(func_get_arg(0));
        $this->vlanName = $vlanName;
        return $this;
    }

    /**
     * @return mixed
     */
    public function getVlanName() {
        return $this->vlanName;
    }

    /**
     * @param mixed $iso
     * @return $this
     */
    public function setIso($iso) {
        $this->updateChanges(func_get_arg(0));
        $this->iso = $iso;
        return $this;
    }

    /**
     * @return mixed
     */
    public function getIso() {
        return $this->iso;
    }

    /**
     * @param mixed $iLo
     * @return $this
     */
    public function setILo($iLo) {
        $this->updateChanges(func_get_arg(0));
        $this->iLo = $iLo;
        return $this;
    }

    /**
     * @return mixed
     */
    public function getILo() {
        return $this->iLo;
    }

    /**
     * @param mixed $remote
     * @return $this
     */
    public function setRemote($remote) {
        $this->updateChanges(func_get_arg(0));
        $this->remote = $remote;
        return $this;
    }

    /**
     * @return mixed
     */
    public function getRemote() {
        return $this->remote;
    }


}