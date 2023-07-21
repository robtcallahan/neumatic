<?php

namespace Neumatic\Model;

/**
 * Class NMTeam
 * A group of users that has access to NeuMatic systems
 *
 * @package Neumatic\Model
 */
class NMTeam
{
	// column names
	protected $id;
	
	protected $name;
    protected $ownerId;

    protected $ownerFirstName;
   	protected $ownerLastName;
   	protected $ownerUsername;
    protected $ownerEmail;

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
            if ($prop == 'changes') continue;
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
            if ($prop == 'changes') continue;
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
    public function setId($id) {
        $this->updateChanges(func_get_arg(0));
        $this->id = $id;
        return $this;
    }

    /**
     * @return mixed
     */
    public function getId() {
        return $this->id;
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
     * @param mixed $ownerEmail
     * @return $this
     */
    public function setOwnerEmail($ownerEmail) {
        $this->updateChanges(func_get_arg(0));
        $this->ownerEmail = $ownerEmail;
        return $this;
    }

    /**
     * @return mixed
     */
    public function getOwnerEmail() {
        return $this->ownerEmail;
    }

    /**
     * @param mixed $ownerFirstName
     * @return $this
     */
    public function setOwnerFirstName($ownerFirstName) {
        $this->updateChanges(func_get_arg(0));
        $this->ownerFirstName = $ownerFirstName;
        return $this;
    }

    /**
     * @return mixed
     */
    public function getOwnerFirstName() {
        return $this->ownerFirstName;
    }

    /**
     * @param mixed $ownerId
     * @return $this
     */
    public function setOwnerId($ownerId) {
        $this->updateChanges(func_get_arg(0));
        $this->ownerId = $ownerId;
        return $this;
    }

    /**
     * @return mixed
     */
    public function getOwnerId() {
        return $this->ownerId;
    }

    /**
     * @param mixed $ownerLastName
     * @return $this
     */
    public function setOwnerLastName($ownerLastName) {
        $this->updateChanges(func_get_arg(0));
        $this->ownerLastName = $ownerLastName;
        return $this;
    }

    /**
     * @return mixed
     */
    public function getOwnerLastName() {
        return $this->ownerLastName;
    }

    /**
     * @param mixed $ownerUsername
     * @return $this
     */
    public function setOwnerUsername($ownerUsername) {
        $this->updateChanges(func_get_arg(0));
        $this->ownerUsername = $ownerUsername;
        return $this;
    }

    /**
     * @return mixed
     */
    public function getOwnerUsername() {
        return $this->ownerUsername;
    }

}
