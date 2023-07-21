<?php

namespace Vmwarephp\Extensions;

use Vmwarephp\ManagedObject;

class Task extends ManagedObject {
    private $info;

    public function getInfo() {
        return $this->info;
    }
}
