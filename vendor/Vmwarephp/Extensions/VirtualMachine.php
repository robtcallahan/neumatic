<?php
namespace Vmwarephp\Extensions;

use Vmwarephp\ManagedObject;

/**
 * @property mixed runtime
 * @property  summary
 */
class VirtualMachine extends ManagedObject {

    protected $guest;

	function takeSnapshot($name, $memory = null, $quiesce = null, $description = '') {
		$snapshotTask = $this->CreateSnapshot_Task(array('name' => $name, 'description' => $description, 'memory' => $memory, 'quiesce' => $quiesce));
		return $snapshotTask;
	}

	function isAccessible() {
		return in_array($this->configStatus, array('green', 'gray'));
	}

	function isTemplate() {
		return $this->summary->config->template;
	}

	function getParentDatastoreName() {
		preg_match('/\[(.*)\]/', $this->summary->config->vmPathName, $matches);
		return $matches[1];
	}

	function getParentDatastore() {
		foreach ($this->datastore as $datastore)
			if ($datastore->name == $this->getParentDatastoreName()) return $datastore;
	}

	function hasSnapshots() {
		return $this->snapshot ? true : false;
	}

	function getUsedSpace() {
		return $this->summary->storage->committed;
	}

	function getProvisionedSpace() {
		return $this->summary->storage->committed + $this->summary->storage->uncommitted;
	}

	function getHardware() {
		return $this->config->hardware;
	}

	function getGuest() {
		return $this->guest;
	}
}
