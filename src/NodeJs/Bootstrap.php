<?php
namespace Mouf\NodeJsInstaller\NodeJs;

use Composer\Package\AliasPackage;
use Composer\Package\CompletePackage;
use Mouf\NodeJsInstaller\Utils\FileUtils;
use Mouf\NodeJsInstaller\Composer\Internal\ConfigKeys;

class Bootstrap
{
    /**
     * @var \Composer\IO\IOInterface
     */
    private $cliIo;

    /**
     * @var \Composer\Composer
     */
    private $composerRuntime;
    
    public function __construct(
        \Composer\IO\IOInterface $cliIo,
        \Composer\Composer $composerRuntime
    ) {
        $this->cliIo = $cliIo;
        $this->composerRuntime = $composerRuntime;
    }

    private function getPluginConfig()
    {
        $defaults = array(
            'useGlobal' => false,
            'includeBinInPath' => false,
        );

        $rootPackage = $this->composerRuntime->getPackage();
        
        $extra = $rootPackage->getExtra();

        if (isset($extra['mouf']['nodejs'])) {
            $rootSettings = $extra['mouf']['nodejs'];
            
            $defaults = array_replace($defaults, $rootSettings);
        }
        
        return $defaults;
    }
    
    public function dispatch()
    {
        $packageRepository = $this->composerRuntime->getRepositoryManager()->getLocalRepository();
        
        $settings = $this->getPluginConfig();

        $composerConfig = $this->composerRuntime->getConfig();
        
        $vendorDir = $composerConfig->get('vendor-dir');
        $binDir = $composerConfig->get('bin-dir');

        $nodeJsVersionMatcher = new \Mouf\NodeJsInstaller\NodeJs\Version\Matcher();

        $versionConstraint = $this->getMergedVersionConstraint();

        $this->verboseLog('<info>NodeJS installer:</info>');
        $this->verboseLog(
            sprintf(' - Requested version: %s', $versionConstraint)
        );

        $ownerPackage = $this->resolveForNamespace($packageRepository, __NAMESPACE__);
        
        $downloadManager = $this->composerRuntime->getDownloadManager();
        
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
    
    public function resolveForNamespace(\Composer\Repository\WritableRepositoryInterface $repository, $namespace)
    {
        $packages = $repository->getCanonicalPackages();

        foreach ($packages as $package) {
            if (!$this->isPluginPackage($package)) {
                continue;
            }

            if (!$this->ownsNamespace($package, $namespace)) {
                continue;
            }

            return $package;
        }

        throw new \Vaimo\ComposerPatches\Exceptions\PackageResolverException(
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
     * @return \Composer\Package\Package
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
            } else {
                // Question: should we update to the latest version? Should we have a nodejs.lock file???
                $this->verboseLog(
                    sprintf(' - Local NodeJS install matches constraint %s', $versionConstraint)
                );
            }
        } else {
            $this->verboseLog(' - No local NodeJS install found');
            return $this->installBestPossibleLocalVersion($nodeJsInstaller, $versionConstraint);
        }
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
        $nodeJsVersionsLister = new \Mouf\NodeJsInstaller\NodeJs\Version\Lister($this->cliIo);
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
        $packagesList = array_merge(
            $this->composerRuntime->getRepositoryManager()->getLocalRepository()->getCanonicalPackages(),
            array($this->composerRuntime->getPackage())
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
        $composerConfig = $this->composerRuntime->getConfig();

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
