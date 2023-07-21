<?php

namespace Neumatic\Model;

class NMLease
{
  	protected $id;
  	protected $serverId;
    protected $leaseStart;          // start date of lease (create date of the VM)
    protected $leaseDuration;       // days until expiration
    protected $expired;             // boolean - has this lease expired or not
    protected $extensionInDays;     // the length of a lease extension
    protected $numExtensionsAllowed;// how many lease extensions are allowed
    protected $numTimesExtended;    // how many extensions have been requested

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
     * @param mixed $expired
     * @return $this
     */
    public function setExpired($expired) {
        $this->updateChanges(func_get_arg(0));
        $this->expired = $expired;
        return $this;
    }

    /**
     * @return mixed
     */
    public function getExpired() {
        return $this->expired;
    }

    /**
     * @param mixed $leaseDuration
     * @return $this
     */
    public function setLeaseDuration($leaseDuration) {
        $this->updateChanges(func_get_arg(0));
        $this->leaseDuration = $leaseDuration;
        return $this;
    }

    /**
     * @return mixed
     */
    public function getLeaseDuration() {
        return $this->leaseDuration;
    }

    /**
     * @param mixed $leaseStart
     * @return $this
     */
    public function setLeaseStart($leaseStart) {
        $this->updateChanges(func_get_arg(0));
        $this->leaseStart = $leaseStart;
        return $this;
    }

    /**
     * @return mixed
     */
    public function getLeaseStart() {
        return $this->leaseStart;
    }

    /**
     * @param mixed $extensionInDays
     * @return $this
     */
    public function setExtensionInDays($extensionInDays) {
        $this->updateChanges(func_get_arg(0));
        $this->extensionInDays = $extensionInDays;
        return $this;
    }

    /**
     * @return mixed
     */
    public function getExtensionInDays() {
        return $this->extensionInDays;
    }

    /**
     * @param mixed $numExtensionsAllowed
     * @return $this
     */
    public function setNumExtensionsAllowed($numExtensionsAllowed) {
        $this->updateChanges(func_get_arg(0));
        $this->numExtensionsAllowed = $numExtensionsAllowed;
        return $this;
    }

    /**
     * @return mixed
     */
    public function getNumExtensionsAllowed() {
        return $this->numExtensionsAllowed;
    }

    /**
     * @param mixed $numTimesExtended
     * @return $this
     */
    public function setNumTimesExtended($numTimesExtended) {
        $this->updateChanges(func_get_arg(0));
        $this->numTimesExtended = $numTimesExtended;
        return $this;
    }

    /**
     * @return mixed
     */
    public function getNumTimesExtended() {
        return $this->numTimesExtended;
    }


}