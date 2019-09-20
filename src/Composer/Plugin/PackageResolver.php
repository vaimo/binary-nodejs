<?php
/**
 * Copyright © Vaimo Group. All rights reserved.
 * See LICENSE_VAIMO.txt for license details.
 */
namespace Mouf\NodeJsInstaller\Composer\Plugin;

use Mouf\NodeJsInstaller\Composer\Internal\ConfigKeys;

class PackageResolver
{
    /**
     * @var \Composer\Package\PackageInterface[]
     */
    private $additionalPackages;
    
    public function __construct(
        array $additionalPackages
    ) {
        $this->additionalPackages = $additionalPackages;
    }

    public function resolveForNamespace(array $packages, $namespace)
    {
        $packages = array_merge($this->additionalPackages, array_values($packages));
        
        foreach ($packages as $package) {
            if (!$this->isPluginPackage($package)) {
                continue;
            }

            if (!$this->ownsNamespace($package, $namespace)) {
                continue;
            }

            return $package;
        }

        throw new \Mouf\NodeJsInstaller\Exception\PackageResolverException(
            'Failed to detect the plugin package'
        );
    }

    public function isPluginPackage(\Composer\Package\PackageInterface $package)
    {
        return $package->getType() === ConfigKeys::COMPOSER_PLUGIN_TYPE;
    }

    public function ownsNamespace(\Composer\Package\PackageInterface $package, $namespace)
    {
        return (bool)array_filter(
            $this->getConfig($package),
            function ($item) use ($namespace) {
                return strpos($namespace, rtrim($item, '\\')) === 0;
            }
        );
    }

    private function getConfig(\Composer\Package\PackageInterface $package)
    {
        $autoload = $package->getAutoload();

        if (!isset($autoload[ConfigKeys::PSR4_CONFIG])) {
            return array();
        }

        return array_keys($autoload[ConfigKeys::PSR4_CONFIG]);
    }
}
