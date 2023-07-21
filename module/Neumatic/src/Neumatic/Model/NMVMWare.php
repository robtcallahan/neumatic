<?php

namespace Neumatic\Model;

class NMVMWare
{
    protected $id;
    protected $serverId;

    protected $vSphereServer;
    protected $vSphereSite;
    protected $dcName;
    protected $dcUid;
    protected $ccrName;
    protected $ccrUid;
    protected $rpUid;
    protected $hsName;

    protected $instanceUuid;

    protected $vmSize;
    protected $numCPUs;
    protected $memoryGB;

    protected $vlanName;
    protected $vlanId;
    
    protected $templateName;
    
    protected $templateId;
    
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
     * @param mixed $instanceUuid
     * @return $this
     */
    public function setInstanceUuid($instanceUuid)
    {
        $this->updateChanges(func_get_arg(0));
        $this->instanceUuid = $instanceUuid;
        return $this;
    }

    /**
     * @return mixed
     */
    public function getInstanceUuid()
    {
        return $this->instanceUuid;
    }

    /**
     * @param mixed $memoryGB
     * @return $this
     */
    public function setMemoryGB($memoryGB)
    {
        $this->updateChanges(func_get_arg(0));
        $this->memoryGB = $memoryGB;
        return $this;
    }

    /**
     * @return mixed
     */
    public function getMemoryGB()
    {
        return $this->memoryGB;
    }

    /**
     * @param mixed $numCPUs
     * @return $this
     */
    public function setNumCPUs($numCPUs)
    {
        $this->updateChanges(func_get_arg(0));
        $this->numCPUs = $numCPUs;
        return $this;
    }

    /**
     * @return mixed
     */
    public function getNumCPUs()
    {
        return $this->numCPUs;
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

    /**
     * @param mixed $vSphereServer
     * @return $this
     */
    public function setVSphereServer($vSphereServer)
    {
        $this->updateChanges(func_get_arg(0));
        $this->vSphereServer = $vSphereServer;
        return $this;
    }

    /**
     * @return mixed
     */
    public function getVSphereServer()
    {
        return $this->vSphereServer;
    }

    /**
     * @param mixed $vmSize
     * @return $this
     */
    public function setVmSize($vmSize)
    {
        $this->updateChanges(func_get_arg(0));
        $this->vmSize = $vmSize;
        return $this;
    }

    /**
     * @return mixed
     */
    public function getVmSize()
    {
        return $this->vmSize;
    }

    /**
     * @param mixed $ccrName
     * @return $this
     */
    public function setCcrName($ccrName)
    {
        $this->updateChanges(func_get_arg(0));
        $this->ccrName = $ccrName;
        return $this;
    }

    /**
     * @return mixed
     */
    public function getCcrName()
    {
        return $this->ccrName;
    }

    /**
     * @param mixed $ccrUid
     * @return $this
     */
    public function setCcrUid($ccrUid)
    {
        $this->updateChanges(func_get_arg(0));
        $this->ccrUid = $ccrUid;
        return $this;
    }

    /**
     * @return mixed
     */
    public function getCcrUid()
    {
        return $this->ccrUid;
    }

    /**
     * @param mixed $dcName
     * @return $this
     */
    public function setDcName($dcName)
    {
        $this->updateChanges(func_get_arg(0));
        $this->dcName = $dcName;
        return $this;
    }

    /**
     * @return mixed
     */
    public function getDcName()
    {
        return $this->dcName;
    }

    /**
     * @param mixed $dcUid
     * @return $this
     */
    public function setDcUid($dcUid)
    {
        $this->updateChanges(func_get_arg(0));
        $this->dcUid = $dcUid;
        return $this;
    }

    /**
     * @return mixed
     */
    public function getDcUid()
    {
        return $this->dcUid;
    }

    /**
     * @param mixed $vSphereSite
     * @return $this
     */
    public function setVSphereSite($vSphereSite)
    {
        $this->updateChanges(func_get_arg(0));
        $this->vSphereSite = $vSphereSite;
        return $this;
    }

    /**
     * @return mixed
     */
    public function getVSphereSite()
    {
        return $this->vSphereSite;
    }

    /**
     * @param mixed $rpUid
     * @return $this
     */
    public function setRpUid($rpUid)
    {
        $this->updateChanges(func_get_arg(0));
        $this->rpUid = $rpUid;
        return $this;
    }

    /**
     * @return mixed
     */
    public function getRpUid()
    {
        return $this->rpUid;
    }

    /**
     * @param mixed $hsName
     * @return $this
     */
    public function setHsName($hsName) {
        $this->updateChanges(func_get_arg(0));
        $this->hsName = $hsName;
        return $this;
    }

    /**
     * @return mixed
     */
    public function getHsName() {
        return $this->hsName;
    }
    
    /**
     * @param mixed $templateName
     * @return $this
     */
    public function setTemplateName($templateName)
    {
        $this->updateChanges(func_get_arg(0));
        $this->templateName = $templateName;
        return $this;
    }

    /**
     * @return mixed
     */
    public function getTemplateName()
    {
        return $this->templateName;
    }
    
     /**
     * @param mixed $templateId
     * @return $this
     */
    public function setTemplateId($templateId)
    {
        $this->updateChanges(func_get_arg(0));
        $this->templateId = $templateId;
        return $this;
    }

    /**
     * @return mixed
     */
    public function getTemplateId()
    {
        return $this->templateId;
    }
    

}