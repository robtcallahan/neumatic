<?php

namespace Vmwarephp\Extensions;

use Vmwarephp\ManagedObject;

class DataCenter extends ManagedObject {
    /** @var  \Vmwarephp\Extensions\HostFolder $hostFolder */
    protected $hostFolder;

    /**
     * @return HostFolder
     */
    public function getHostFolder() {
        return $this->hostFolder;
    }

    public function setHostFolder($folder) {
        $this->hostFolder = $folder;
        return $this;
    }
}
