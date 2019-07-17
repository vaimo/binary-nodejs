<?php
namespace Mouf\NodeJsInstaller\Composer\Package\Config;

use Mouf\NodeJsInstaller\Composer\Internal\ConfigKeys;

class ValueResolver
{
    public function resolveNamespaces(\Composer\Package\PackageInterface $package)
    {
        $autoload = $package->getAutoload();

        if (!isset($autoload[ConfigKeys::PSR4_CONFIG])) {
            return array();
        }

        return array_keys($autoload[ConfigKeys::PSR4_CONFIG]);
    }
}
