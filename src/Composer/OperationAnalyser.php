<?php
namespace Mouf\NodeJsInstaller\Composer;

use Composer\DependencyResolver\Operation\OperationInterface;

class OperationAnalyser
{
    /**
     * @var \Mouf\NodeJsInstaller\Composer\Package\ConfigAnalyser
     */
    private $configAnalyser;

    public function __construct()
    {
        $this->configAnalyser = new \Mouf\NodeJsInstaller\Composer\Package\ConfigAnalyser();
    }

    public function isUninstallOperationForNamespace(OperationInterface $operation, $namespace)
    {
        if (!$operation instanceof \Composer\DependencyResolver\Operation\UninstallOperation) {
            return false;
        }
        
        return $this->configAnalyser->ownsNamespace($operation->getPackage(), $namespace);
    }
}
