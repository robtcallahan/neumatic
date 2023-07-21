<?php

namespace Vmwarephp\Extensions;

use Vmwarephp\ManagedObject;

class ComputeResource extends ManagedObject {
    protected $host;
    protected $network;

    /**
     * @return \Vmwarephp\Extensions\HostSystem[]
     */
    public function getHost() {
        return $this->host;
    }

    public function getNetwork() {
        return $this->network;
    }
}
