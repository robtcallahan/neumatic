<?php

namespace Vmwarephp\Extensions;

use Vmwarephp\ManagedObject;

class Network extends ManagedObject {
    protected $summary;

    public function getSummary() {
        return $this->summary;
    }
}
