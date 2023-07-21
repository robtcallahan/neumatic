<?php

namespace Neumatic\Model;

class NMServer
{

    protected $id;
    protected $name;
    protected $oldName;
    protected $serverType;

    protected $ownerId;
    protected $teamId;
    protected $ldapUserGroup;
    protected $ldapHostGroup;

    protected $sysId;
    protected $businessServiceName;
    protected $businessServiceId;
    protected $subsystemName;
    protected $subsystemId;
    protected $cmdbEnvironment;

    protected $description;

    protected $location;
    protected $locationId;

    protected $network;
    protected $subnetMask;
    protected $gateway;
    protected $macAddress;
    protected $ipAddress;

    protected $cobblerServer;
    protected $cobblerDistro;
    protected $cobblerKickstart;
    protected $cobblerMetadata;

    protected $remoteServer;

    protected $chefServer;
    protected $chefRole;
    protected $chefEnv;

    protected $dateCreated;
    protected $userCreated;
    protected $dateUpdated;
    protected $userUpdated;

    protected $okToBuild;
    protected $buildStep;
    protected $buildSteps;
    protected $status;
    protected $statusText;

    protected $timeBuildStart;
    protected $timeBuildEnd;

    protected $dateBuilt;
    protected $userBuilt;
    protected $dateFirstCheckin;

    protected $archived; // boolean flag


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
     * @param mixed $chefEnv
     * @return $this
     */
    public function setChefEnv($chefEnv)
    {
        $this->updateChanges(func_get_arg(0));
        $this->chefEnv = $chefEnv;
        return $this;
    }

    /**
     * @return mixed
     */
    public function getChefEnv()
    {
        return $this->chefEnv;
    }

    /**
     * @param mixed $chefRole
     * @return $this
     */
    public function setChefRole($chefRole)
    {
        $this->updateChanges(func_get_arg(0));
        $this->chefRole = $chefRole;
        return $this;
    }

    /**
     * @return mixed
     */
    public function getChefRole()
    {
        return $this->chefRole;
    }

    /**
     * @param mixed $chefServer
     * @return $this
     */
    public function setChefServer($chefServer)
    {
        $this->updateChanges(func_get_arg(0));
        $this->chefServer = $chefServer;
        return $this;
    }

    /**
     * @return mixed
     */
    public function getChefServer()
    {
        return $this->chefServer;
    }

    /**
     * @param mixed $cobblerDistro
     * @return $this
     */
    public function setCobblerDistro($cobblerDistro)
    {
        $this->updateChanges(func_get_arg(0));
        $this->cobblerDistro = $cobblerDistro;
        return $this;
    }

    /**
     * @return mixed
     */
    public function getCobblerDistro()
    {
        return $this->cobblerDistro;
    }

    /**
     * @param mixed $cobblerKickstart
     * @return $this
     */
    public function setCobblerKickstart($cobblerKickstart)
    {
        $this->updateChanges(func_get_arg(0));
        $this->cobblerKickstart = $cobblerKickstart;
        return $this;
    }

    /**
     * @return mixed
     */
    public function getCobblerKickstart()
    {
        return $this->cobblerKickstart;
    }

    /**
     * @param mixed $gateway
     * @return $this
     */
    public function setGateway($gateway)
    {
        $this->updateChanges(func_get_arg(0));
        $this->gateway = $gateway;
        return $this;
    }

    /**
     * @return mixed
     */
    public function getGateway()
    {
        return $this->gateway;
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
     * @param mixed $ipAddress
     * @return $this
     */
    public function setIpAddress($ipAddress)
    {
        $this->updateChanges(func_get_arg(0));
        $this->ipAddress = $ipAddress;
        return $this;
    }

    /**
     * @return mixed
     */
    public function getIpAddress()
    {
        return $this->ipAddress;
    }

    /**
     * @param mixed $location
     * @return $this
     */
    public function setLocation($location)
    {
        $this->updateChanges(func_get_arg(0));
        $this->location = $location;
        return $this;
    }

    /**
     * @return mixed
     */
    public function getLocation()
    {
        return $this->location;
    }

    /**
     * @param mixed $macAddress
     * @return $this
     */
    public function setMacAddress($macAddress)
    {
        $this->updateChanges(func_get_arg(0));
        $this->macAddress = $macAddress;
        return $this;
    }

    /**
     * @return mixed
     */
    public function getMacAddress()
    {
        return $this->macAddress;
    }

    /**
     * @param mixed $name
     * @return $this
     */
    public function setName($name)
    {
        $this->updateChanges(func_get_arg(0));
        $this->name = $name;
        return $this;
    }

    /**
     * @return mixed
     */
    public function getName()
    {
        return $this->name;
    }

    /**
     * @param mixed $oldName
     * @return $this
     */
    public function setOldName($oldName) {
        $this->updateChanges(func_get_arg(0));
        $this->oldName = $oldName;
        return $this;
    }

    /**
     * @return mixed
     */
    public function getOldName() {
        return $this->oldName;
    }

    /**
     * @param mixed $subnetMask
     * @return $this
     */
    public function setSubnetMask($subnetMask)
    {
        $this->updateChanges(func_get_arg(0));
        $this->subnetMask = $subnetMask;
        return $this;
    }

    /**
     * @return mixed
     */
    public function getSubnetMask()
    {
        return $this->subnetMask;
    }

    /**
     * @param mixed $dateBuilt
     * @return $this
     */
    public function setDateBuilt($dateBuilt)
    {
        $this->updateChanges(func_get_arg(0));
        $this->dateBuilt = $dateBuilt;
        return $this;
    }

    /**
     * @return mixed
     */
    public function getDateBuilt()
    {
        return $this->dateBuilt;
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
     * @param mixed $dateFirstCheckin
     * @return $this
     */
    public function setDateFirstCheckin($dateFirstCheckin)
    {
        $this->updateChanges(func_get_arg(0));
        $this->dateFirstCheckin = $dateFirstCheckin;
        return $this;
    }

    /**
     * @return mixed
     */
    public function getDateFirstCheckin()
    {
        return $this->dateFirstCheckin;
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
     * @param mixed $userBuilt
     * @return $this
     */
    public function setUserBuilt($userBuilt)
    {
        $this->updateChanges(func_get_arg(0));
        $this->userBuilt = $userBuilt;
        return $this;
    }

    /**
     * @return mixed
     */
    public function getUserBuilt()
    {
        return $this->userBuilt;
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
     * @param mixed $status
     * @return $this
     */
    public function setStatus($status)
    {
        $this->updateChanges(func_get_arg(0));
        $this->status = $status;
        return $this;
    }

    /**
     * @return mixed
     */
    public function getStatus()
    {
        return $this->status;
    }

    /**
     * @param mixed $statusText
     * @return $this
     */
    public function setStatusText($statusText)
    {
        $this->updateChanges(func_get_arg(0));
        $this->statusText = $statusText;
        return $this;
    }

    /**
     * @return mixed
     */
    public function getStatusText()
    {
        return $this->statusText;
    }

    /**
     * @param mixed $timeBuildEnd
     * @return $this
     */
    public function setTimeBuildEnd($timeBuildEnd)
    {
        $this->updateChanges(func_get_arg(0));
        $this->timeBuildEnd = $timeBuildEnd;
        return $this;
    }

    /**
     * @return mixed
     */
    public function getTimeBuildEnd()
    {
        return $this->timeBuildEnd;
    }

    /**
     * @param mixed $timeBuildStart
     * @return $this
     */
    public function setTimeBuildStart($timeBuildStart)
    {
        $this->updateChanges(func_get_arg(0));
        $this->timeBuildStart = $timeBuildStart;
        return $this;
    }

    /**
     * @return mixed
     */
    public function getTimeBuildStart()
    {
        return $this->timeBuildStart;
    }

    /**
     * @param mixed $serverType
     * @return $this
     */
    public function setServerType($serverType)
    {
        $this->updateChanges(func_get_arg(0));
        $this->serverType = $serverType;
        return $this;
    }

    /**
     * @return mixed
     */
    public function getServerType()
    {
        return $this->serverType;
    }

    /**
     * @param mixed $cobblerServer
     * @return $this
     */
    public function setCobblerServer($cobblerServer)
    {
        $this->updateChanges(func_get_arg(0));
        $this->cobblerServer = $cobblerServer;
        return $this;
    }

    /**
     * @return mixed
     */
    public function getCobblerServer()
    {
        return $this->cobblerServer;
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
     * @param mixed $okToBuild
     * @return $this
     */
    public function setOkToBuild($okToBuild) {
        $this->updateChanges(func_get_arg(0));
        $this->okToBuild = $okToBuild;
        return $this;
    }

    /**
     * @return mixed
     */
    public function getOkToBuild() {
        return $this->okToBuild;
    }

    /**
     * @param mixed $businessServiceId
     * @return $this
     */
    public function setBusinessServiceId($businessServiceId) {
        $this->updateChanges(func_get_arg(0));
        $this->businessServiceId = $businessServiceId;
        return $this;
    }

    /**
     * @return mixed
     */
    public function getBusinessServiceId() {
        return $this->businessServiceId;
    }

    /**
     * @param mixed $businessServiceName
     * @return $this
     */
    public function setBusinessServiceName($businessServiceName) {
        $this->updateChanges(func_get_arg(0));
        $this->businessServiceName = $businessServiceName;
        return $this;
    }

    /**
     * @return mixed
     */
    public function getBusinessServiceName() {
        return $this->businessServiceName;
    }

    /**
     * @param mixed $subsystemId
     * @return $this
     */
    public function setSubsystemId($subsystemId) {
        $this->updateChanges(func_get_arg(0));
        $this->subsystemId = $subsystemId;
        return $this;
    }

    /**
     * @return mixed
     */
    public function getSubsystemId() {
        return $this->subsystemId;
    }

    /**
     * @param mixed $subsystemName
     * @return $this
     */
    public function setSubsystemName($subsystemName) {
        $this->updateChanges(func_get_arg(0));
        $this->subsystemName = $subsystemName;
        return $this;
    }

    /**
     * @return mixed
     */
    public function getSubsystemName() {
        return $this->subsystemName;
    }

    /**
     * @param mixed $sysId
     * @return $this
     */
    public function setSysId($sysId) {
        $this->updateChanges(func_get_arg(0));
        $this->sysId = $sysId;
        return $this;
    }

    /**
     * @return mixed
     */
    public function getSysId() {
        return $this->sysId;
    }

    /**
     * @param mixed $cmdbEnvironment
     * @return $this
     */
    public function setCmdbEnvironment($cmdbEnvironment) {
        $this->updateChanges(func_get_arg(0));
        $this->cmdbEnvironment = $cmdbEnvironment;
        return $this;
    }

    /**
     * @return mixed
     */
    public function getCmdbEnvironment() {
        return $this->cmdbEnvironment;
    }

    /**
     * @param mixed $archived
     * @return $this
     */
    public function setArchived($archived) {
        $this->updateChanges(func_get_arg(0));
        $this->archived = $archived;
        return $this;
    }

    /**
     * @return mixed
     */
    public function getArchived() {
        return $this->archived;
    }

    /**
     * @param mixed $description
     * @return $this
     */
    public function setDescription($description) {
        $this->updateChanges(func_get_arg(0));
        $this->description = $description;
        return $this;
    }

    /**
     * @return mixed
     */
    public function getDescription() {
        return $this->description;
    }

    /**
     * @param mixed $cobblerMetadata
     * @return $this
     */
    public function setCobblerMetadata($cobblerMetadata) {
        $this->updateChanges(func_get_arg(0));
        $this->cobblerMetadata = $cobblerMetadata;
        return $this;
    }

    /**
     * @return mixed
     */
    public function getCobblerMetadata() {
        return $this->cobblerMetadata;
    }

    /**
     * @param mixed $remoteServer
     * @return $this
     */
    public function setRemoteServer($remoteServer) {
        $this->updateChanges(func_get_arg(0));
        $this->remoteServer = $remoteServer;
        return $this;
    }

    /**
     * @return mixed
     */
    public function getRemoteServer() {
        return $this->remoteServer;
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
     * @param mixed $locationId
     * @return $this
     */
    public function setLocationId($locationId) {
        $this->updateChanges(func_get_arg(0));
        $this->locationId = $locationId;
        return $this;
    }

    /**
     * @return mixed
     */
    public function getLocationId() {
        return $this->locationId;
    }

    /**
     * @param mixed $buildStep
     * @return $this
     */
    public function setBuildStep($buildStep) {
        $this->updateChanges(func_get_arg(0));
        $this->buildStep = $buildStep;
        return $this;
    }

    /**
     * @return mixed
     */
    public function getBuildStep() {
        return $this->buildStep;
    }

    /**
     * @param mixed $buildSteps
     * @return $this
     */
    public function setBuildSteps($buildSteps) {
        $this->updateChanges(func_get_arg(0));
        $this->buildSteps = $buildSteps;
        return $this;
    }

    /**
     * @return mixed
     */
    public function getBuildSteps() {
        return $this->buildSteps;
    }

    /**
     * @param mixed $ldapHostGroup
     * @return $this
     */
    public function setLdapHostGroup($ldapHostGroup) {
        $this->updateChanges(func_get_arg(0));
        $this->ldapHostGroup = $ldapHostGroup;
        return $this;
    }

    /**
     * @return mixed
     */
    public function getLdapHostGroup() {
        return $this->ldapHostGroup;
    }

    /**
     * @param mixed $ldapUserGroup
     * @return $this
     */
    public function setLdapUserGroup($ldapUserGroup) {
        $this->updateChanges(func_get_arg(0));
        $this->ldapUserGroup = $ldapUserGroup;
        return $this;
    }

    /**
     * @return mixed
     */
    public function getLdapUserGroup() {
        return $this->ldapUserGroup;
    }

 }