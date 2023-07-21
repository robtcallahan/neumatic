<?php

namespace Neumatic\Model;

class NMVLAN
{
    protected $id;
    protected $distSwitchId;
    protected $vlanId;
    protected $name;
    protected $network;
    protected $netmask;
    protected $gateway;
    protected $enabled;

    /**
     * Keeps track of properties that have their values changed
     *
     * @var array
     */
    protected $changes = array();

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
     * @param mixed $distSwitchId
     * @return $this
     */
    public function setDistSwitchId($distSwitchId) {
        $this->updateChanges(func_get_arg(0));
        $this->distSwitchId = $distSwitchId;
        return $this;
    }

    /**
     * @return mixed
     */
    public function getDistSwitchId() {
        return $this->distSwitchId;
    }

    /**
     * @param mixed $enabled
     * @return $this
     */
    public function setEnabled($enabled) {
        $this->updateChanges(func_get_arg(0));
        $this->enabled = $enabled;
        return $this;
    }

    /**
     * @return mixed
     */
    public function getEnabled() {
        return $this->enabled;
    }

    /**
     * @param mixed $gateway
     * @return $this
     */
    public function setGateway($gateway) {
        $this->updateChanges(func_get_arg(0));
        $this->gateway = $gateway;
        return $this;
    }

    /**
     * @return mixed
     */
    public function getGateway() {
        return $this->gateway;
    }

    /**
     * @param mixed $name
     * @return $this
     */
    public function setName($name) {
        $this->updateChanges(func_get_arg(0));
        $this->name = $name;
        return $this;
    }

    /**
     * @return mixed
     */
    public function getName() {
        return $this->name;
    }

    /**
     * @param mixed $network
     * @return $this
     */
    public function setNetwork($network) {
        $this->updateChanges(func_get_arg(0));
        $this->network = $network;
        return $this;
    }

    /**
     * @return mixed
     */
    public function getNetwork() {
        return $this->network;
    }

    /**
     * @param mixed $netmask
     * @return $this
     */
    public function setNetmask($netmask) {
        $this->updateChanges(func_get_arg(0));
        $this->netmask = $netmask;
        return $this;
    }

    /**
     * @return mixed
     */
    public function getNetmask() {
        return $this->netmask;
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


}
