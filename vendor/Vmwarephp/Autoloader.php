<?php

namespace Vmwarephp;

class Autoloader {
	private $fileExtension = '.php';
	private $namespace;
	private $includePath;
	private $namespaceSeparator = '\\';

	public function __construct($ns = 'Vmwarephp', $includePath = null) {
		$this->namespace = $ns;
		$this->includePath = $includePath ? : $this->getLibraryPath();
	}

	public function setNamespaceSeparator($sep) {
		$this->namespaceSeparator = $sep;
	}

	public function getNamespaceSeparator() {
		return $this->namespaceSeparator;
	}

	public function setIncludePath($includePath) {
		$this->includePath = $includePath;
	}

	public function getIncludePath() {
		return $this->includePath;
	}

	public function setFileExtension($fileExtension) {
		$this->fileExtension = $fileExtension;
	}

	public function getFileExtension() {
		return $this->fileExtension;
	}

	public function register() {
		spl_autoload_register(array($this, 'loadClass'));
	}

	public function unregister() {
		spl_autoload_unregister(array($this, 'loadClass'));
	}

	public function loadClass($className) {
        #print "loadClass({$className})\n";

		if (strpos($className, $this->namespace) === false) {
            if (preg_match("/STS/", $className)) {
                #print "\tSTS\n";
                // register our autoloader that replaces '\' with '/' and '_' with ''
                $className = (string)str_replace('\\', DIRECTORY_SEPARATOR, $className);
                $className = str_replace('_', DIRECTORY_SEPARATOR, $className);
                return require_once($className . ".php");
            } else {
                #print "\ttypedef\n";
    			return require_once 'TypeDefinitions.inc';
            }
		}
		else if (is_null($this->namespace) || $this->namespace . $this->namespaceSeparator === substr($className, 0, strlen($this->namespace . $this->namespaceSeparator))) {
            #print "\tvmwarephp\n";
			$fileDirectoryPath = '';
			$lastNsPos = strripos($className, $this->namespaceSeparator);
			if ($lastNsPos !== false) {
				$namespace = $this->getNamespaceFromClassName($className, $lastNsPos);
				$shortClassName = $this->getShortClassName($className, $lastNsPos);
				$fileDirectoryPath .= $this->namespaceToPath($namespace);
                $absolutePath = $this->makeAbsolutePath($fileDirectoryPath, $shortClassName);
                if (file_exists($absolutePath)) {
                    return require_once $absolutePath;
			    }
            }
		} else {
            $className = (string)str_replace('\\', DIRECTORY_SEPARATOR, $className);
            $className = str_replace('_', DIRECTORY_SEPARATOR, $className);
            return require_once($className . ".php");
        }
	}

	private function namespaceToPath($namespace) {
		return str_replace($this->namespaceSeparator, DIRECTORY_SEPARATOR, $namespace) . DIRECTORY_SEPARATOR;
	}

	private function getShortClassName($className, $lastNsPos) {
		return substr($className, $lastNsPos + 1);
	}

	private function getNamespaceFromClassName($className, $lastNsPos) {
		return substr($className, 0, $lastNsPos);
	}

	private function makeAbsolutePath($fileDirectory, $shortClassName) {
		return $this->includePath . DIRECTORY_SEPARATOR . $fileDirectory . $shortClassName . $this->fileExtension;
	}

	private function getLibraryPath() {
		return __DIR__ . DIRECTORY_SEPARATOR . '..' . DIRECTORY_SEPARATOR;
	}
}