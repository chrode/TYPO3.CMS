<?php
namespace TYPO3\CMS\Core\Core;

/***************************************************************
 *  Copyright notice
 *
 *  (c) 2013 Thomas Maroschik <tmaroschik@dfau.de>
 *  All rights reserved
 *
 *  This script is part of the TYPO3 project. The TYPO3 project is
 *  free software; you can redistribute it and/or modify
 *  it under the terms of the GNU General Public License as published by
 *  the Free Software Foundation; either version 2 of the License, or
 *  (at your option) any later version.
 *
 *  The GNU General Public License can be found at
 *  http://www.gnu.org/copyleft/gpl.html.
 *  A copy is found in the textfile GPL.txt and important notices to the license
 *  from the author is found in LICENSE.txt distributed with these scripts.
 *
 *
 *  This script is distributed in the hope that it will be useful,
 *  but WITHOUT ANY WARRANTY; without even the implied warranty of
 *  MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 *  GNU General Public License for more details.
 *
 *  This copyright notice MUST APPEAR in all copies of the script!
 ***************************************************************/

use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\CMS\Core\Utility\ExtensionManagementUtility;

/**
 * Class Loader implementation which loads .php files found in the classes
 * directory of an object.
 */
class ClassLoader {

	const VALID_CLASSNAME_PATTERN = '/^[a-zA-Z_\x7f-\xff][a-zA-Z0-9\\\\_\x7f-\xff]*$/';

	/**
	 * @var ClassAliasMap
	 */
	protected $classAliasMap;

	/**
	 * @var ClassAliasMap
	 */
	static protected $staticAliasMap;

	/**
	 * @var \TYPO3\CMS\Core\Cache\Frontend\PhpFrontend
	 */
	protected $classesCache;

	/**
	 * @var string
	 */
	protected $cacheIdentifier;

	/**
	 * @var array<\TYPO3\Flow\Package\Package>
	 */
	protected $packages = array();

	/**
	 * @var array
	 */
	protected $earlyClassFileAutoloadRegistry = array();

	/**
	 * @var array A list of namespaces this class loader is definitely responsible for
	 */
	protected $packageNamespaces = array(
		'TYPO3\CMS\Core' => 14
	);

	/**
	 * @var array A list of packages and their replaces pointing to class paths
	 */
	protected $packageClassesPaths = array();

	public function __construct() {
		$this->classesCache = new \TYPO3\CMS\Core\Cache\Frontend\PhpFrontend('cache_classes', new \TYPO3\CMS\Core\Cache\Backend\EarlyClassLoaderBackend());
	}

	/**
	 * Get class alias map list injected
	 *
	 * @param ClassAliasMap
	 */
	public function injectClassAliasMap(ClassAliasMap $classAliasMap) {
		$this->classAliasMap = $classAliasMap;
		static::$staticAliasMap = $classAliasMap;
	}

	/**
	 * Get classes cache injected
	 *
	 * @param \TYPO3\CMS\Core\Cache\Frontend\PhpFrontend $classesCache
	 */
	public function injectClassesCache(\TYPO3\CMS\Core\Cache\Frontend\PhpFrontend $classesCache) {
		/** @var $earlyClassLoaderBackend \TYPO3\CMS\Core\Cache\Backend\EarlyClassLoaderBackend */
		$earlyClassLoaderBackend = $this->classesCache->getBackend();
		$this->classesCache = $classesCache;
		$this->classAliasMap->injectClassesCache($classesCache);
		foreach ($earlyClassLoaderBackend->getAll() as $cacheEntryIdentifier => $classFilePath) {
			if (!$this->classesCache->has($cacheEntryIdentifier)) {
				$this->addClassToCache($classFilePath, $cacheEntryIdentifier);
			}
		}
	}

	/**
	 * Loads php files containing classes or interfaces found in the classes directory of
	 * a package and specifically registered classes.
	 *
	 * @param string $className Name of the class/interface to load
	 * @param bool $require TRUE if file should be required
	 * @return boolean
	 */
	public function loadClass($className, $require = TRUE) {
		if ($className[0] === '\\') {
			$className = substr($className, 1);
		}

		if (!$this->isValidClassname($className)) {
			return FALSE;
		}

		$cacheEntryIdentifier = strtolower(str_replace('\\', '_', $className));
		$cacheEntryCreated = FALSE;

		// Loads any known class via caching framework
		if ($require) {
			if ($this->classesCache->has($cacheEntryIdentifier) && $this->classesCache->requireOnce($cacheEntryIdentifier) !== FALSE) {
				$cacheEntryCreated = TRUE;
			}
		}

		if (!$cacheEntryCreated) {
			$cacheEntryCreated = $this->createCacheEntryForClassFromCorePackage($className, $cacheEntryIdentifier);
		}

		if (!$cacheEntryCreated) {
			$cacheEntryCreated = $this->createCacheEntryForClassFromEarlyAutoloadRegistry($className, $cacheEntryIdentifier);
		}

		if (!$cacheEntryCreated) {
			$cacheEntryCreated = $this->createCacheEntryForClassFromRegisteredPackages($className, $cacheEntryIdentifier);
		}

		if (!$cacheEntryCreated) {
			$cacheEntryCreated = $this->createCacheEntryForClassByNamingConvention($className, $cacheEntryIdentifier);
		}

		if ($cacheEntryCreated && $require) {
			if ($this->classesCache->has($cacheEntryIdentifier) && $this->classesCache->requireOnce($cacheEntryIdentifier) !== FALSE) {
				$cacheEntryCreated = TRUE;
			}
		}

		return $cacheEntryCreated;
	}

	/**
	 * Find out if a class name is valid
	 *
	 * @param string $className
	 * @return bool
	 */
	protected function isValidClassname($className) {
		return (bool) preg_match(self::VALID_CLASSNAME_PATTERN, $className);
	}

	/**
	 * Create cache entry for class from core package
	 *
	 * @param string $className
	 * @param string $cacheEntryIdentifier
	 * @return boolean TRUE if cache entry exists
	 */
	protected function createCacheEntryForClassFromCorePackage($className, $cacheEntryIdentifier) {
		if (substr($cacheEntryIdentifier, 0, 14) === 'typo3_cms_core') {
			$classesFolder = substr($cacheEntryIdentifier, 15, 5) === 'tests' ? '' : 'Classes/';
			$classFilePath = PATH_typo3 . 'sysext/core/' . $classesFolder . str_replace('\\', '/', substr($className, 15)) . '.php';
			if (@file_exists($classFilePath)) {
				$this->addClassToCache($classFilePath, $cacheEntryIdentifier);
				return TRUE;
			}
		}
		return FALSE;
	}

	/**
	 * Create early class name autoload registry cache
	 *
	 * @param string $className
	 * @param string $cacheEntryIdentifier
	 * @return boolean TRUE if cache file was created
	 */
	protected function createCacheEntryForClassFromEarlyAutoloadRegistry($className, $cacheEntryIdentifier) {
		if (isset($this->earlyClassFileAutoloadRegistry[$lowercasedClassName = strtolower($className)])) {
			if (@file_exists($this->earlyClassFileAutoloadRegistry[$lowercasedClassName])) {
				$this->addClassToCache($this->earlyClassFileAutoloadRegistry[$lowercasedClassName], $cacheEntryIdentifier);
				return TRUE;
			}
		}
		return FALSE;
	}

	/**
	 * Create cache entry from registered packages
	 *
	 * @param string $className
	 * @param string $cacheEntryIdentifier
	 * @return boolean TRUE File was created
	 */
	protected function createCacheEntryForClassFromRegisteredPackages($className, $cacheEntryIdentifier) {;
		foreach ($this->packageNamespaces as $packageNamespace => $packageData) {
			if (substr(str_replace('_', '\\', $className), 0, $packageData['namespaceLength']) === $packageNamespace) {
				if ($packageData['substituteNamespaceInPath']) {
					// If it's a TYPO3 package, classes don't comply to PSR-0.
					// The namespace part is substituted.
					$classPathAndFilename = '/' . str_replace('\\', '/', ltrim(substr($className, $packageData['namespaceLength']), '\\')) . '.php';
				} else {
					// make the classname PSR-0 compliant by replacing underscores only in the classname not in the namespace
					$classPathAndFilename  = '';
					$lastNamespacePosition = strrpos($className, '\\');
					if ($lastNamespacePosition !== FALSE) {
						$namespace = substr($className, 0, $lastNamespacePosition);
						$className = substr($className, $lastNamespacePosition + 1);
						$classPathAndFilename  = str_replace('\\', '/', $namespace) . '/';
					}
					$classPathAndFilename .= str_replace('_', '/', $className) . '.php';
				}
				if (strtolower(substr($className, $packageData['namespaceLength'], 5)) === 'tests') {
					$classPathAndFilename = $packageData['packagePath'] . $classPathAndFilename;
				} else {
					$classPathAndFilename = $packageData['classesPath'] . $classPathAndFilename;
				}
				if (@file_exists($classPathAndFilename)) {
					$this->addClassToCache($classPathAndFilename, $cacheEntryIdentifier);
					return TRUE;
				}
			}
		}
		return FALSE;
	}

	/**
	 * Try to load a given class name based on 'extbase' naming convention into the registry.
	 * If the file is found it writes an entry to $classNameToFileMapping and re-caches the
	 * array to the file system to save this lookup for next call.
	 *
	 * @param string $className Class name to find source file of
	 * @param string $classCacheEntryIdentifier
	 * @return boolean TRUE if was created
	 */
	protected function createCacheEntryForClassByNamingConvention($className, $classCacheEntryIdentifier) {
		$delimiter = '_';
		// To handle namespaced class names, split the class name at the
		// namespace delimiters.
		if (strpos($className, '\\') !== FALSE) {
			$delimiter = '\\';
		}

		$classNameParts = explode($delimiter, $className, 4);

		// We only handle classes that follow the convention Vendor\Product\Classname or is longer
		// so we won't deal with class names that only have one or two parts
		if (count($classNameParts) <= 2) {
			return FALSE;
		}

		if (isset($classNameParts[0]) && $classNameParts[0] === 'TYPO3' && (isset($classNameParts[1]) && $classNameParts[1] === 'CMS')) {
			$extensionKey = GeneralUtility::camelCaseToLowerCaseUnderscored($classNameParts[2]);
			$classNameWithoutVendorAndProduct = $classNameParts[3];
		} else {
			$extensionKey = GeneralUtility::camelCaseToLowerCaseUnderscored($classNameParts[1]);
			$classNameWithoutVendorAndProduct = $classNameParts[2];

			if (isset($classNameParts[3])) {
				$classNameWithoutVendorAndProduct .= $delimiter . $classNameParts[3];
			}
		}

		if ($extensionKey && isset($this->packageClassesPaths[$extensionKey])) {
			if (substr(strtolower($classNameWithoutVendorAndProduct), 0, 5) === 'tests') {
				$classesPath = $this->packages[$extensionKey]->getPackagePath();
			} else {
				$classesPath = $this->packageClassesPaths[$extensionKey];
			}
			$classFilePath = $classesPath . strtr($classNameWithoutVendorAndProduct, $delimiter, '/') . '.php';
			if (@file_exists($classFilePath)) {
				$this->addClassToCache($classFilePath, $classCacheEntryIdentifier);
				return TRUE;
			}
		}

		return FALSE;
	}

	/**
	 * Get cache identifier
	 *
	 * @return string identifier
	 */
	protected function getCacheIdentifier() {
		return $this->cacheIdentifier;
	}

	/**
	 * Get cache entry identifier
	 *
	 * @return string identifier
	 */
	protected function getCacheEntryIdentifier() {
		$cacheIdentifier = $this->getCacheIdentifier();
		return $cacheIdentifier !== NULL ? 'ClassLoader_' . $this->getCacheIdentifier() : NULL;
	}

	/**
	 * Set cache identifier
	 *
	 * @param string $cacheIdentifier Cache identifier
	 * @return ClassLoader
	 */
	public function setCacheIdentifier($cacheIdentifier) {
		$this->cacheIdentifier = $cacheIdentifier;
		$this->classAliasMap->setCacheIdentifier($cacheIdentifier);
		return $this;
	}

	/**
	 * Sets the available packages
	 *
	 * @param array $packages An array of \TYPO3\Flow\Package\Package objects
	 * @return ClassLoader
	 */
	public function setPackages(array $packages) {
		$this->packages = $packages;
		if (!$this->loadPackageNamespacesFromCache()) {
			$this->buildPackageNamespaces();
			$this->buildPackageClassesPathsForLegacyExtensions();
			$this->savePackageNamespacesAndClassesPathsToCache();
			// Rebuild the class alias map too because ext_autoload can contain aliases
			$classNameToAliasMapping = $this->classAliasMap->setPackagesButDontBuildMappingFilesReturnClassNameToAliasMappingInstead($packages);
			$this->buildAutoloadRegistryAndSaveToCache();
			$this->classAliasMap->buildMappingFiles($classNameToAliasMapping);
		} else {
			$this->classAliasMap->setPackages($packages);
		}
		return $this;
	}

	/**
	 * Load package namespaces from cache
	 *
	 * @return boolean TRUE if package namespaces were loaded
	 */
	protected function loadPackageNamespacesFromCache() {
		$cacheEntryIdentifier = $this->getCacheEntryIdentifier();
		if ($cacheEntryIdentifier !== NULL && $this->classesCache->has($cacheEntryIdentifier)) {
			list($packageNamespaces, $packageClassesPaths) = $this->classesCache->requireOnce($cacheEntryIdentifier);
			if (is_array($packageNamespaces) && is_array($packageClassesPaths)) {
				$this->packageNamespaces = $packageNamespaces;
				$this->packageClassesPaths = $packageClassesPaths;
				return TRUE;
			}
		}
		return FALSE;
	}

	/**
	 * Build package namespaces
	 *
	 * @return void
	 */
	protected function buildPackageNamespaces() {
		/** @var $package \TYPO3\Flow\Package\Package */
		foreach ($this->packages as $package) {
			$packageNamespace = $package->getNamespace();
			// Ignore legacy extensions with unkown vendor name
			if ($packageNamespace[0] !== '*') {
				$this->packageNamespaces[$packageNamespace] = array(
					'namespaceLength' => strlen($packageNamespace),
					'classesPath' => $package->getClassesPath(),
					'packagePath' => $package->getPackagePath(),
					'substituteNamespaceInPath' => ($package instanceof \TYPO3\CMS\Core\Package\Package)
				);
			}
		}
		// Sort longer package namespaces first, to find specific matches before generic ones
		$sortPackages = function($a, $b) {
			if (($lenA = strlen($a)) === ($lenB = strlen($b))) {
				return strcmp($a, $b);
			}
			return ($lenA > $lenB) ? -1 : 1;
		};
		uksort($this->packageNamespaces, $sortPackages);
	}

	/**
	 * Build autoload registry
	 *
	 * @return void
	 */
	protected function buildAutoloadRegistryAndSaveToCache() {
		$classFileAutoloadRegistry = array();
		foreach ($this->packages as $package) {
			/** @var $package \TYPO3\CMS\Core\Package\Package */
			if ($package instanceof \TYPO3\CMS\Core\Package\Package) {
				$classFilesFromAutoloadRegistry = $package->getClassFilesFromAutoloadRegistry();
				if (is_array($classFilesFromAutoloadRegistry)) {
					$classFileAutoloadRegistry = array_merge($classFileAutoloadRegistry, $classFilesFromAutoloadRegistry);
				}
			}
		}
		foreach ($classFileAutoloadRegistry as $className => $classFilePath) {
			if (@file_exists($classFilePath)) {
				$this->addClassToCache($classFilePath, strtolower(str_replace('\\', '_', $className)));
			}
		}
	}

	/**
	 * Builds the classes paths for legacy extensions with unknown vendor name
	 *
	 * @return void
	 */
	protected function buildPackageClassesPathsForLegacyExtensions() {
		foreach ($this->packages as $package) {
			if ($package instanceof \TYPO3\CMS\Core\Package\PackageInterface) {
				$this->packageClassesPaths[$package->getPackageKey()] = $package->getClassesPath();
				foreach ($package->getPackageReplacementKeys() as $packageToReplace => $versionConstraint) {
					$this->packageClassesPaths[$packageToReplace] = $package->getClassesPath();
				}
			}
		}
	}

	/**
	 * Save package namespaces and classes paths to cache
	 *
	 * @return void
	 */
	protected function savePackageNamespacesAndClassesPathsToCache() {
		$cacheEntryIdentifier = $this->getCacheEntryIdentifier();
		if ($cacheEntryIdentifier !== NULL) {
			$this->classesCache->set(
				$this->getCacheEntryIdentifier(),
				'return ' . var_export(array($this->packageNamespaces, $this->packageClassesPaths), TRUE) . ';'
			);
		}
	}

	/**
	 * Adds a single class to class loader cache.
	 *
	 * @param string $classFilePathAndName Physical path of file containing $className
	 * @param string $classCacheEntryIdentifier
	 */
	protected function addClassToCache($classFilePathAndName, $classCacheEntryIdentifier) {
		/** @var $classesCacheBackend \TYPO3\CMS\Core\Cache\Backend\EarlyClassLoaderBackend|\TYPO3\CMS\Core\Cache\Backend\ClassLoaderBackend */
		$classesCacheBackend = $this->classesCache->getBackend();
		$classesCacheBackend->setLinkToPhpFile(
			$classCacheEntryIdentifier,
			$classFilePathAndName
		);
	}

	/**
	 * This method is necessary for the early loading of the cores autoload registry
	 *
	 * @param array $classFileAutoloadRegistry
	 */
	public function setEarlyClassFileAutoloadRegistry($classFileAutoloadRegistry) {
		$this->earlyClassFileAutoloadRegistry = $classFileAutoloadRegistry;
	}

	/**
	 * Set alias for class name
	 *
	 * @param string $aliasClassName
	 * @param string $originalClassName
	 * @return boolean
	 */
	public function setAliasForClassName($aliasClassName, $originalClassName) {
		return $this->classAliasMap->setAliasForClassName($aliasClassName, $originalClassName);
	}

	/**
	 * Get class name for alias
	 *
	 * @param string $alias
	 * @return mixed
	 */
	static public function getClassNameForAlias($alias) {
		return static::$staticAliasMap->getClassNameForAlias($alias);
	}

	/**
	 * Get alias for class name
	 *
	 * @param string $className
	 * @deprecated since 6.2, use getAliasesForClassName instead. will be removed 2 versions later
	 * @return mixed
	 */
	static public function getAliasForClassName($className) {
		$aliases = static::$staticAliasMap->getAliasesForClassName($className);
		return (is_array($aliases) && isset($aliases[0])) ? $aliases[0] : NULL;
	}

	/**
	 * Get an aliases for a class name
	 *
	 * @param string $className
	 * @return mixed
	 */
	static public function getAliasesForClassName($className) {
		return static::$staticAliasMap->getAliasesForClassName($className);
	}

}
