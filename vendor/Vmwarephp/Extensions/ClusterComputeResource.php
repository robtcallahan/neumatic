<?php

namespace Vmwarephp\Extensions;

use Vmwarephp\ManagedObject;

/**
 * @property  \Vmwarephp\Extensions\HostSystem[] host
 */
class ClusterComputeResource extends ManagedObject {
    protected $network;

    public function getNetwork() {
        return $this->network;
    }
}
