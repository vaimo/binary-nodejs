<?php
namespace Mouf\NodeJsInstaller\NodeJs;

use Composer\Package\AliasPackage;
use Composer\Package\CompletePackage;
use Composer\Util\Filesystem;
use Mouf\NodeJsInstaller\NodeJs\Installer;

class Bootstrap
{
    /**
     * @var \Composer\IO\IOInterface
     */
    private $cliIo;

    /**
     * @var \Composer\Repository\WritableRepositoryInterface
     */
    private $packageRepository;
    
    /**
     * @var \Composer\Package\PackageInterface
     */
    private $rootPackage;

    /**
     * @var string
     */
    private $vendorDir;
    
    /**
     * @var string
     */
    private $binDir;
    
    public function __construct(
        \Composer\IO\IOInterface $cliIo,
        \Composer\Repository\WritableRepositoryInterface $packageRepository,
        \Composer\Package\PackageInterface $rootPackage,
        $vendorDir,
        $binDir
    ) {
        $this->cliIo = $cliIo;
        $this->packageRepository = $packageRepository;
        $this->rootPackage = $rootPackage;
        $this->vendorDir = $vendorDir;
        $this->binDir = $binDir;
    }

    private function getPluginConfig()
    {
        $settings = array(
            'targetDir' => 'vendor/nodejs/nodejs',
            'forceLocal' => false,
            'includeBinInPath' => false,
        );

        $extra = $this->rootPackage->getExtra();

        if (isset($extra['mouf']['nodejs'])) {
            $rootSettings = $extra['mouf']['nodejs'];
            
            $settings = array_merge($settings, $rootSettings);
            $settings['targetDir'] = trim($settings['targetDir'], '/\\');
        }
        
        return $settings;
    }
    
    public function dispatch()
    {
        $settings = $this->getPluginConfig();

        $binDir = $this->binDir;

        $nodeJsVersionMatcher = new \Mouf\NodeJsInstaller\NodeJs\Version\Matcher();

        $versionConstraint = $this->getMergedVersionConstraint();

        $this->verboseLog('<info>NodeJS installer:</info>');
        $this->verboseLog(
            sprintf(' - Requested version: %s', $versionConstraint)
        );

        $nodeJsInstaller = new Installer($this->cliIo, $this->vendorDir, $this->binDir);

        $isLocal = false;

        if ($settings['forceLocal']) {
            $this->verboseLog(' - Forcing local NodeJS install.');
            $this->installLocalVersion($binDir, $nodeJsInstaller, $versionConstraint, $settings['targetDir']);
            $isLocal = true;
        } else {
            $globalVersion = $nodeJsInstaller->getNodeJsGlobalInstallVersion();

            if ($globalVersion !== null) {
                $this->verboseLog(
                    sprintf(' - Global NodeJS install found: v%s', $globalVersion)
                );
                
                $npmPath = $nodeJsInstaller->getGlobalInstallPath('npm');

                if (!$npmPath) {
                    $this->verboseLog(' - No NPM install found');
                    $this->installLocalVersion($binDir, $nodeJsInstaller, $versionConstraint, $settings['targetDir']);
                    $isLocal = true;
                } elseif (!$nodeJsVersionMatcher->isVersionMatching($globalVersion, $versionConstraint)) {
                    $this->installLocalVersion($binDir, $nodeJsInstaller, $versionConstraint, $settings['targetDir']);
                    $isLocal = true;
                } else {
                    $this->verboseLog(
                        sprintf(' - Global NodeJS install matches constraint %s', $versionConstraint)
                    );
                }
            } else {
                $this->verboseLog(' - No global NodeJS install found');
                $this->installLocalVersion($binDir, $nodeJsInstaller, $versionConstraint, $settings['targetDir']);
                $isLocal = true;
            }
        }

        // Now, let's create the bin scripts that start node and NPM
        $nodeJsInstaller->createBinScripts($binDir, $settings['targetDir'], $isLocal);

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
     * @param  string $binDir
     * @param  Installer $nodeJsInstaller
     * @param  string $versionConstraint
     * @param  string $targetDir
     *
     * @throws \Mouf\NodeJsInstaller\Exception\InstallerException
     * @throws \Mouf\NodeJsInstaller\Exception\InstallerNodeVersionException
     */
    private function installLocalVersion($binDir, Installer $nodeJsInstaller, $versionConstraint, $targetDir)
    {
        $nodeJsVersionMatcher = new \Mouf\NodeJsInstaller\NodeJs\Version\Matcher();

        $localVersion = $nodeJsInstaller->getNodeJsLocalInstallVersion($binDir);
        if ($localVersion !== null) {
            $this->verboseLog(
                sprintf(' - Local NodeJS install found: v%s', $localVersion)
            );

            if (!$nodeJsVersionMatcher->isVersionMatching($localVersion, $versionConstraint)) {
                $this->installBestPossibleLocalVersion($nodeJsInstaller, $versionConstraint, $targetDir);
            } else {
                // Question: should we update to the latest version? Should we have a nodejs.lock file???
                $this->verboseLog(
                    sprintf(' - Local NodeJS install matches constraint %s', $versionConstraint)
                );
            }
        } else {
            $this->verboseLog(' - No local NodeJS install found');
            $this->installBestPossibleLocalVersion($nodeJsInstaller, $versionConstraint, $targetDir);
        }
    }

    /**
     * Installs locally the best possible NodeJS version matching $versionConstraint
     *
     * @param  Installer $nodeJsInstaller
     * @param  string $versionConstraint
     * @param  string $targetDir
     *
     * @throws \Mouf\NodeJsInstaller\Exception\InstallerException
     * @throws \Mouf\NodeJsInstaller\Exception\InstallerNodeVersionException
     */
    private function installBestPossibleLocalVersion(Installer $nodeJsInstaller, $versionConstraint, $targetDir)
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

        $nodeJsInstaller->install($bestPossibleVersion, $targetDir);
    }

    /**
     * Gets the version constraint from all included packages and merges it into one constraint.
     */
    private function getMergedVersionConstraint()
    {
        $packagesList = array_merge(
            $this->packageRepository->getCanonicalPackages(),
            array($this->rootPackage)
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

    /**
     * Uninstalls NodeJS.
     * Note: other classes cannot be loaded here since the package has already been removed.
     */
    public function unload()
    {
        $settings = $this->getPluginConfig();

        $binDir = $this->binDir;
        $targetDir = $settings['targetDir'];
        
        $fileSystem = new Filesystem();

        if (file_exists($targetDir)) {
            $this->verboseLog('Removing NodeJS local install');

            // Let's remove target directory
            $fileSystem->remove($targetDir);

            $vendorNodeDir = dirname($targetDir);

            if ($fileSystem->isDirEmpty($vendorNodeDir)) {
                $fileSystem->remove($vendorNodeDir);
            }
        }

        // Now, let's remove the links
        $this->verboseLog('Removing NodeJS and NPM links from Composer bin directory');

        $binNames= array('node', 'npm', 'node.bat', 'npm.bat');
        
        foreach ($binNames as $file) {
            $realFile = $binDir . DIRECTORY_SEPARATOR . $file;

            if (file_exists($realFile)) {
                $fileSystem->remove($realFile);
            }
        }
    }
}
