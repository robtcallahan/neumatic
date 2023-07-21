<?php

namespace Neumatic\Model;

/**
 * Class NMUserTeam
 * Ties users to teams in a one to many relationship
 * @package Neumatic\Model
 */
class NMUserTeam
{
	// column names
	protected $userId;
	protected $teamId;

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
     * @param mixed $teamId
     * @return $this
     */
    public function setTeamId($teamId) {
        $this->updateChanges(func_get_arg(0));
        $this->teamId = $teamId;
        return $this;
    }

    /**
     * @return mixed
     */
    public function getTeamId() {
        return $this->teamId;
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

}
