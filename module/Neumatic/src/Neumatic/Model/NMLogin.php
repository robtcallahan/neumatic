<?php

namespace Neumatic\Model;

class NMLogin
{
    protected $id;
    /**
     * Foreign key to the id of the user table.
     * @var int
     */
    protected $userId;
    /**
     * Number of times the user logged into the tool.
     * @var int
     */
    protected $numLogins;
    /**
     * Timestamp of the last login time of the user
     * @var string
     */
    protected $lastLogin;
    /**
     * IP address of this user.
     * @var string
     */
    protected $ipAddr;
    /**
     * User agent used by the user to access the page
     * @var string
     */
    protected $userAgent;

    /**
     * Not in login table. Used by NMLoginTable to flag if the motd should be displayed.
     * True if last login time was yesterday
     * @var boolean
     */
    protected $showMotd;

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
     * @param mixed $ipAddr
     * @return $this
     */
    public function setIpAddr($ipAddr)
    {
        $this->updateChanges(func_get_arg(0));
        $this->ipAddr = $ipAddr;
        return $this;
    }

    /**
     * @return mixed
     */
    public function getIpAddr()
    {
        return $this->ipAddr;
    }

    /**
     * @param mixed $lastLogin
     * @return $this
     */
    public function setLastLogin($lastLogin)
    {
        $this->updateChanges(func_get_arg(0));
        $this->lastLogin = $lastLogin;
        return $this;
    }

    /**
     * @return mixed
     */
    public function getLastLogin()
    {
        return $this->lastLogin;
    }

    /**
     * @param mixed $numLogins
     * @return $this
     */
    public function setNumLogins($numLogins)
    {
        $this->updateChanges(func_get_arg(0));
        $this->numLogins = $numLogins;
        return $this;
    }

    /**
     * @return mixed
     */
    public function getNumLogins()
    {
        return $this->numLogins;
    }

    /**
     * @param mixed $userAgent
     * @return $this
     */
    public function setUserAgent($userAgent)
    {
        $this->updateChanges(func_get_arg(0));
        $this->userAgent = $userAgent;
        return $this;
    }

    /**
     * @return mixed
     */
    public function getUserAgent()
    {
        return $this->userAgent;
    }

    /**
     * @param mixed $userId
     * @return $this
     */
    public function setUserId($userId)
    {
        $this->updateChanges(func_get_arg(0));
        $this->userId = $userId;
        return $this;
    }

    /**
     * @return mixed
     */
    public function getUserId()
    {
        return $this->userId;
    }

    /**
     * @param boolean $showMotd
     * @return $this
     */
    public function setShowMotd($showMotd) {
        $this->updateChanges(func_get_arg(0));
        $this->showMotd = $showMotd;
        return $this;
    }

    /**
     * @return boolean
     */
    public function getShowMotd() {
        return $this->showMotd;
    }


}
