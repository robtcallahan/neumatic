<?php

namespace Neumatic\Model;

/**
 * Class NMUser
 * A NeuMatic User
 * @package Neumatic\Model
 */
class NMUser
{
	// column names
	protected $id;
	
	protected $firstName;
	protected $lastName;
	protected $username;

	protected $empId;
	protected $title;
	protected $dept;
	protected $office;
	protected $email;

	protected $officePhone;
	protected $mobilePhone;

	protected $userType;
    protected $numServerBuilds;
    protected $maxPoolServers;

    protected $dateCreated;
    protected $userCreated;
    protected $dateUpdated;
    protected $userUpdated;

    /**
     * Keeps track of properties that have their values changed
     *
     * @var array
     */
    protected $changes = array();

    /**
     * @var array
     */
    protected $ldapUserGroups;

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
     * @param mixed $userType
     * @return $this
     */
    public function setUserType($userType)
    {
        $this->updateChanges(func_get_arg(0));
        $this->userType = $userType;
        return $this;
    }

    /**
     * @return mixed
     */
    public function getUserType()
    {
        return $this->userType;
    }

    /**
     * @param mixed $dept
     * @return $this
     */
    public function setDept($dept)
    {
        $this->updateChanges(func_get_arg(0));
        $this->dept = $dept;
        return $this;
    }

    /**
     * @return mixed
     */
    public function getDept()
    {
        return $this->dept;
    }

    /**
     * @param mixed $email
     * @return $this
     */
    public function setEmail($email)
    {
        $this->updateChanges(func_get_arg(0));
        $this->email = $email;
        return $this;
    }

    /**
     * @return mixed
     */
    public function getEmail()
    {
        return $this->email;
    }

    /**
     * @param mixed $empId
     * @return $this
     */
    public function setEmpId($empId)
    {
        $this->updateChanges(func_get_arg(0));
        $this->empId = $empId;
        return $this;
    }

    /**
     * @return mixed
     */
    public function getEmpId()
    {
        return $this->empId;
    }

    /**
     * @param mixed $firstName
     * @return $this
     */
    public function setFirstName($firstName)
    {
        $this->updateChanges(func_get_arg(0));
        $this->firstName = $firstName;
        return $this;
    }

    /**
     * @return mixed
     */
    public function getFirstName()
    {
        return $this->firstName;
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
     * @param mixed $lastName
     * @return $this
     */
    public function setLastName($lastName)
    {
        $this->updateChanges(func_get_arg(0));
        $this->lastName = $lastName;
        return $this;
    }

    /**
     * @return mixed
     */
    public function getLastName()
    {
        return $this->lastName;
    }

    /**
     * @param mixed $mobilePhone
     * @return $this
     */
    public function setMobilePhone($mobilePhone)
    {
        $this->updateChanges(func_get_arg(0));
        $this->mobilePhone = $mobilePhone;
        return $this;
    }

    /**
     * @return mixed
     */
    public function getMobilePhone()
    {
        return $this->mobilePhone;
    }

    /**
     * @param mixed $office
     * @return $this
     */
    public function setOffice($office)
    {
        $this->updateChanges(func_get_arg(0));
        $this->office = $office;
        return $this;
    }

    /**
     * @return mixed
     */
    public function getOffice()
    {
        return $this->office;
    }

    /**
     * @param mixed $officePhone
     * @return $this
     */
    public function setOfficePhone($officePhone)
    {
        $this->updateChanges(func_get_arg(0));
        $this->officePhone = $officePhone;
        return $this;
    }

    /**
     * @return mixed
     */
    public function getOfficePhone()
    {
        return $this->officePhone;
    }

    /**
     * @param mixed $title
     * @return $this
     */
    public function setTitle($title)
    {
        $this->updateChanges(func_get_arg(0));
        $this->title = $title;
        return $this;
    }

    /**
     * @return mixed
     */
    public function getTitle()
    {
        return $this->title;
    }

    /**
     * @param mixed $dateCreated
     * @return $this
     */
    public function setDateCreated($dateCreated)
    {
        $this->updateChanges(func_get_arg(0));
        $this->dateCreated = $dateCreated;
        return $this;
    }

    /**
     * @return mixed
     */
    public function getDateCreated()
    {
        return $this->dateCreated;
    }

    /**
     * @param mixed $dateUpdated
     * @return $this
     */
    public function setDateUpdated($dateUpdated)
    {
        $this->updateChanges(func_get_arg(0));
        $this->dateUpdated = $dateUpdated;
        return $this;
    }

    /**
     * @return mixed
     */
    public function getDateUpdated()
    {
        return $this->dateUpdated;
    }

    /**
     * @param mixed $userCreated
     * @return $this
     */
    public function setUserCreated($userCreated)
    {
        $this->updateChanges(func_get_arg(0));
        $this->userCreated = $userCreated;
        return $this;
    }

    /**
     * @return mixed
     */
    public function getUserCreated()
    {
        return $this->userCreated;
    }

    /**
     * @param mixed $userUpdated
     * @return $this
     */
    public function setUserUpdated($userUpdated)
    {
        $this->updateChanges(func_get_arg(0));
        $this->userUpdated = $userUpdated;
        return $this;
    }

    /**
     * @return mixed
     */
    public function getUserUpdated()
    {
        return $this->userUpdated;
    }

    /**
     * @param mixed $username
     * @return $this
     */
    public function setUsername($username)
    {
        $this->updateChanges(func_get_arg(0));
        $this->username = $username;
        return $this;
    }

    /**
     * @return mixed
     */
    public function getUsername()
    {
        return $this->username;
    }

    /**
     * @param mixed $numServerBuilds
     * @return $this
     */
    public function setNumServerBuilds($numServerBuilds) {
        $this->updateChanges(func_get_arg(0));
        $this->numServerBuilds = $numServerBuilds;
        return $this;
    }

    /**
     * @return mixed
     */
    public function getNumServerBuilds() {
        return $this->numServerBuilds;
    }

    /**
     * @param mixed $maxPoolServers
     * @return $this
     */
    public function setMaxPoolServers($maxPoolServers) {
        $this->updateChanges(func_get_arg(0));
        $this->maxPoolServers = $maxPoolServers;
        return $this;
    }

    /**
     * @return mixed
     */
    public function getMaxPoolServers() {
        return $this->maxPoolServers;
    }

}
