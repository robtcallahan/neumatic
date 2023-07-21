<?php

namespace Neumatic\Model;

class NMRating
{
    protected $id;
	protected $userId; // foreign key to id in user table
	protected $rating; // decimal (5,1)
	protected $dateRated; // timestamp
	protected $comments; // varchar (25)

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
     * @param mixed $comments
     * @return $this
     */
    public function setComments($comments) {
        $this->updateChanges(func_get_arg(0));
        $this->comments = $comments;
        return $this;
    }

    /**
     * @return mixed
     */
    public function getComments() {
        return $this->comments;
    }

    /**
     * @param mixed $dateRated
     * @return $this
     */
    public function setDateRated($dateRated) {
        $this->updateChanges(func_get_arg(0));
        $this->dateRated = $dateRated;
        return $this;
    }

    /**
     * @return mixed
     */
    public function getDateRated() {
        return $this->dateRated;
    }

    /**
     * @param mixed $rating
     * @return $this
     */
    public function setRating($rating) {
        $this->updateChanges(func_get_arg(0));
        $this->rating = $rating;
        return $this;
    }

    /**
     * @return mixed
     */
    public function getRating() {
        return $this->rating;
    }


}
