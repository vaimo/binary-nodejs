<?php
namespace Mouf\NodeJsInstaller\Composer\Package;

class ConfigAnalyser
{
    /**
     * @var \Mouf\NodeJsInstaller\Composer\Package\Config\ValueResolver
     */
    private $configValueResolver;

    public function __construct()
    {
        $this->configValueResolver = new \Mouf\NodeJsInstaller\Composer\Package\Config\ValueResolver();
    }

    public function isPluginPackage(\Composer\Package\PackageInterface $package)
    {
        return $package->getType() === \Mouf\NodeJsInstaller\Composer\ConfigKeys::COMPOSER_PLUGIN_TYPE;
    }

    public function ownsNamespace(\Composer\Package\PackageInterface $package, $namespace)
    {
        return (bool)array_filter(
            $this->configValueResolver->resolveNamespaces($package),
            function ($item) use ($namespace) {
                return strpos($namespace, rtrim($item, '\\')) === 0;
            }
        );
    }
}
