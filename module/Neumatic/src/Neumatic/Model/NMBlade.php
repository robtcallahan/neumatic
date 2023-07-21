<?php

namespace Neumatic\Model;

class NMBlade
{
    protected $id;
    protected $serverId;

    protected $distSwitch;

    protected $vlanName;
    protected $vlanId;

    protected $chassisName;
    protected $chassisId;
    protected $bladeName;
    protected $bladeId;
    protected $bladeSlot;


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
     * @param mixed $bladeId
     * @return $this
     */
    public function setBladeId($bladeId)
    {
        $this->updateChanges(func_get_arg(0));
        $this->bladeId = $bladeId;
        return $this;
    }

    /**
     * @return mixed
     */
    public function getBladeId()
    {
        return $this->bladeId;
    }

    /**
     * @param mixed $bladeName
     * @return $this
     */
    public function setBladeName($bladeName)
    {
        $this->updateChanges(func_get_arg(0));
        $this->bladeName = $bladeName;
        return $this;
    }

    /**
     * @return mixed
     */
    public function getBladeName()
    {
        return $this->bladeName;
    }

    /**
     * @param mixed $bladeSlot
     * @return $this
     */
    public function setBladeSlot($bladeSlot)
    {
        $this->updateChanges(func_get_arg(0));
        $this->bladeSlot = $bladeSlot;
        return $this;
    }

    /**
     * @return mixed
     */
    public function getBladeSlot()
    {
        return $this->bladeSlot;
    }

    /**
     * @param mixed $chassisId
     * @return $this
     */
    public function setChassisId($chassisId)
    {
        $this->updateChanges(func_get_arg(0));
        $this->chassisId = $chassisId;
        return $this;
    }

    /**
     * @return mixed
     */
    public function getChassisId()
    {
        return $this->chassisId;
    }

    /**
     * @param mixed $chassisName
     * @return $this
     */
    public function setChassisName($chassisName)
    {
        $this->updateChanges(func_get_arg(0));
        $this->chassisName = $chassisName;
        return $this;
    }

    /**
     * @return mixed
     */
    public function getChassisName()
    {
        return $this->chassisName;
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
    public function setVlanId($vlanId)
    {
        $this->updateChanges(func_get_arg(0));
        $this->vlanId = $vlanId;
        return $this;
    }

    /**
     * @return mixed
     */
    public function getVlanId()
    {
        return $this->vlanId;
    }

    /**
     * @param mixed $vlanName
     * @return $this
     */
    public function setVlanName($vlanName)
    {
        $this->updateChanges(func_get_arg(0));
        $this->vlanName = $vlanName;
        return $this;
    }

    /**
     * @return mixed
     */
    public function getVlanName()
    {
        return $this->vlanName;
    }


}