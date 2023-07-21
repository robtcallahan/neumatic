<?php

namespace Vmwarephp\Factory;

use Vmwarephp\Vhost;

class Service {
    /**
     * @param Vhost $vhost
     * @return \Vmwarephp\Service
     */
    static function make(Vhost $vhost) {
		return new \Vmwarephp\Service($vhost);
	}

    /**
     * @param Vhost $vhost
     * @return \Vmwarephp\Service
     */
    static function makeConnected(Vhost $vhost) {
		$service = self::make($vhost);
		$service->connect();
		return $service;
	}
}