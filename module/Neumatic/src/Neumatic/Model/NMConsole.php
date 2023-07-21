<?php

namespace Neumatic\Model;

class NMConsole
{
    protected $id;
    protected $serverId;
    protected $consoleLog;
    protected $consoleWatcherLog;
    protected $consoleRunning;


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
     * @param mixed $consoleLog
     * @return $this
     */
    public function setConsoleLog($consoleLog)
    {
        $this->updateChanges(func_get_arg(0));
        $this->consoleLog = $consoleLog;
        return $this;
    }

    /**
     * @return mixed
     */
    public function getConsoleLog()
    {
        return $this->consoleLog;
    }

    /**
     * @param mixed $consoleWatcherLog
     * @return $this
     */
    public function setConsoleWatcherLog($consoleWatcherLog)
    {
        $this->updateChanges(func_get_arg(0));
        $this->consoleWatcherLog = $consoleWatcherLog;
        return $this;
    }

    /**
     * @return mixed
     */
    public function getConsoleWatcherLog()
    {
        return $this->consoleWatcherLog;
    }

    /**
     * @param mixed $consoleRunning
     * @return $this
     */
    public function setConsoleRunning($consoleRunning)
    {
        $this->updateChanges(func_get_arg(0));
        $this->consoleRunning = $consoleRunning;
        return $this;
    }

    /**
     * @return mixed
     */
    public function getConsoleRunning()
    {
        return $this->consoleRunning;
    }

}