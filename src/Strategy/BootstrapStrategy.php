<?php
/**
 * Copyright © Vaimo Group. All rights reserved.
 * See LICENSE_VAIMO.txt for license details.
 */
namespace Mouf\NodeJsInstaller\Strategy;

class BootstrapStrategy
{
    /**
     * @var \Mouf\NodeJsInstaller\Composer\Context
     */
    private $composerContext;

    /**
     * @param \Mouf\NodeJsInstaller\Composer\Context $composerContext
     */
    public function __construct(
        \Mouf\NodeJsInstaller\Composer\Context $composerContext
    ) {
        $this->composerContext = $composerContext;
    }

    public function shouldAllow()
    {
        $composer = $this->composerContext->getLocalComposer();

        $packageResolver = new \Mouf\NodeJsInstaller\Composer\Plugin\PackageResolver(
            array($composer->getPackage())
        );

        $repository = $composer->getRepositoryManager()->getLocalRepository();

        try {
            $packageResolver->resolveForNamespace($repository->getCanonicalPackages(), __NAMESPACE__);
        } catch (\Mouf\NodeJsInstaller\Exception\PackageResolverException $exception) {
            return false;
        }

        return true;
    }
}
