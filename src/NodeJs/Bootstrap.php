<?php
namespace Mouf\NodeJsInstaller\NodeJs;

use Composer\Package\AliasPackage;
use Composer\Package\CompletePackage;
use Composer\Config;

class Bootstrap
{
    /**
     * @var \Mouf\NodeJsInstaller\Composer\Context
     */
    private $composerContext;
    
    /**
     * @var \Composer\IO\IOInterface
     */
    private $cliIo;

    /**
     * @var Config
     */
    private $config;
    
    public function __construct(
        \Mouf\NodeJsInstaller\Composer\Context $composerContext,
        \Composer\IO\IOInterface $cliIo,
        Config $config
    ) {
        $this->composerContext = $composerContext;
        $this->cliIo = $cliIo;
        $this->config = $config;
    }

    private function getPluginConfig()
    {
        $defaults = array(
            'useGlobal' => false,
            'includeBinInPath' => false,
        );

        $composer = $this->composerContext->getLocalComposer();
        $rootPackage = $composer->getPackage();
        
        $extra = $rootPackage->getExtra();

        if (isset($extra['mouf']['nodejs'])) {
            $rootSettings = $extra['mouf']['nodejs'];
            
            $defaults = array_replace($defaults, $rootSettings);
        }
        
        return $defaults;
    }
    
    public function dispatch()
    {
        $settings = $this->getPluginConfig();

        $composer = $this->composerContext->getLocalComposer();
        $composerConfig = $composer->getConfig();
        
        $vendorDir = $composerConfig->get('vendor-dir');
        $binDir = $composerConfig->get('bin-dir');

        $nodeJsVersionMatcher = new \Mouf\NodeJsInstaller\NodeJs\Version\Matcher();

        $versionConstraint = $this->getMergedVersionConstraint();

        $this->verboseLog('<info>NodeJS installer:</info>');
        $this->verboseLog(
            sprintf(' - Requested version: %s', $versionConstraint)
        );
        
        $packages = $this->composerContext->getActivePackages();

        $packageResolver = new \Mouf\NodeJsInstaller\Composer\Plugin\PackageResolver(
            array($composer->getPackage())
        );
        
        $ownerPackage = $packageResolver->resolveForNamespace($packages, __NAMESPACE__);
        
        $downloadManager = $composer->getDownloadManager();
        
        $nodeJsInstaller = new Installer(
            $ownerPackage,
            $downloadManager,
            $this->cliIo,
            $vendorDir
        );

        $isLocal = false;

        $package = false;

        $globalVersion = null;

        if ($settings['useGlobal']) {
            $globalVersion = $nodeJsInstaller->getNodeJsGlobalInstallVersion();
        }

        if ($globalVersion !== null) {
            $this->verboseLog(
                sprintf(' - Global NodeJS install found: v%s', $globalVersion)
            );

            $npmPath = $nodeJsInstaller->getGlobalInstallPath('npm');

            if (!$npmPath) {
                $this->verboseLog(' - No NPM install found');
                $package = $this->installLocalVersion($binDir, $nodeJsInstaller, $versionConstraint);
                $isLocal = true;
            } elseif (!$nodeJsVersionMatcher->isVersionMatching($globalVersion, $versionConstraint)) {
                $package = $this->installLocalVersion($binDir, $nodeJsInstaller, $versionConstraint);
                $isLocal = true;
            } else {
                $this->verboseLog(
                    sprintf(' - Global NodeJS install matches constraint %s', $versionConstraint)
                );
            }
        } else {
            $this->verboseLog(' - No global NodeJS install found');
            
            $package = $this->installLocalVersion($binDir, $nodeJsInstaller, $versionConstraint);
            
            $isLocal = true;
        }
        
        if ($package) {
            $installPath = $nodeJsInstaller->getInstallPath($package);

            // Now, let's create the bin scripts that start node and NPM
            $nodeJsInstaller->createBinScripts($binDir, $installPath, $isLocal);
        }

        // Finally, let's register vendor/bin in the PATH.
        if ($settings['includeBinInPath']) {
            $nodeJsInstaller->registerPath($binDir);
        }
    }
    


    
    /**
     * Writes message only in verbose mode.
     * @param string $message
     */
    private function verboseLog($message)
    {
        if ($this->cliIo->isVerbose()) {
            $this->cliIo->write($message);
        }
    }

    /**
     * Checks local NodeJS version, performs install if needed.
     *
     * @param string $binDir
     * @param Installer $nodeJsInstaller
     * @param string $versionConstraint
     * @return \Composer\Package\Package|null
     *
     * @throws \Mouf\NodeJsInstaller\Exception\InstallerException
     * @throws \Mouf\NodeJsInstaller\Exception\InstallerNodeVersionException
     */
    private function installLocalVersion($binDir, Installer $nodeJsInstaller, $versionConstraint)
    {
        $nodeJsVersionMatcher = new \Mouf\NodeJsInstaller\NodeJs\Version\Matcher();

        $localVersion = $nodeJsInstaller->getNodeJsLocalInstallVersion($binDir);
        
        if ($localVersion !== null) {
            $this->verboseLog(
                sprintf(' - Local NodeJS install found: v%s', $localVersion)
            );

            if (!$nodeJsVersionMatcher->isVersionMatching($localVersion, $versionConstraint)) {
                return $this->installBestPossibleLocalVersion($nodeJsInstaller, $versionConstraint);
            }

            // Question: should we update to the latest version? Should we have a nodejs.lock file???
            $this->verboseLog(
                sprintf(' - Local NodeJS install matches constraint %s', $versionConstraint)
            );
        } else {
            $this->verboseLog(' - No local NodeJS install found');
            return $this->installBestPossibleLocalVersion($nodeJsInstaller, $versionConstraint);
        }
        
        return null;
    }

    /**
     * Installs locally the best possible NodeJS version matching $versionConstraint
     *
     * @param Installer $nodeJsInstaller
     * @param string $versionConstraint
     * @return \Composer\Package\Package
     *
     * @throws \Mouf\NodeJsInstaller\Exception\InstallerException
     * @throws \Mouf\NodeJsInstaller\Exception\InstallerNodeVersionException
     */
    private function installBestPossibleLocalVersion(Installer $nodeJsInstaller, $versionConstraint)
    {
        $nodeJsVersionsLister = new \Mouf\NodeJsInstaller\NodeJs\Version\Lister($this->cliIo, $this->config);
        $allNodeJsVersions = $nodeJsVersionsLister->getList();

        $nodeJsVersionMatcher = new \Mouf\NodeJsInstaller\NodeJs\Version\Matcher();
        $bestPossibleVersion = $nodeJsVersionMatcher->findBestMatchingVersion($allNodeJsVersions, $versionConstraint);

        if ($bestPossibleVersion === null) {
            throw new \Mouf\NodeJsInstaller\Exception\InstallerNodeVersionException(
                sprintf('No NodeJS version could be found for constraint \'%s\'', $versionConstraint)
            );
        }

        return $nodeJsInstaller->install($bestPossibleVersion);
    }

    /**
     * Gets the version constraint from all included packages and merges it into one constraint.
     */
    private function getMergedVersionConstraint()
    {
        $composer = $this->composerContext->getLocalComposer();
        
        $packagesList = array_merge(
            $composer->getRepositoryManager()->getLocalRepository()->getCanonicalPackages(),
            array($composer->getPackage())
        );

        $versions = array();

        foreach ($packagesList as $package) {
            if ($package instanceof AliasPackage) {
                $package = $package->getAliasOf();
            }
            
            if ($package instanceof CompletePackage) {
                $extra = $package->getExtra();
                
                if (isset($extra['mouf']['nodejs']['version'])) {
                    $versions[] = $extra['mouf']['nodejs']['version'];
                }
            }
        }

        if (!empty($versions)) {
            return implode(', ', $versions);
        }

        return '*';
    }
    
    public function unload()
    {
        $composer = $this->composerContext->getLocalComposer();
        $composerConfig = $composer->getConfig();

        $binDir = $composerConfig->get('bin-dir');
        
        $fileSystem = new \Composer\Util\Filesystem();

        $binNames= array('node', 'npm', 'node.bat', 'npm.bat');
        
        foreach ($binNames as $file) {
            $filePath = $binDir . DIRECTORY_SEPARATOR . $file;

            if (!file_exists($filePath)) {
                continue;
            }

            $fileSystem->remove($filePath);
        }
    }
}
