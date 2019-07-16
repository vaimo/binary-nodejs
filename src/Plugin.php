<?php
namespace Mouf\NodeJsInstaller;

use Composer\Composer;
use Composer\EventDispatcher\EventSubscriberInterface;
use Composer\IO\IOInterface;
use Composer\Plugin\PluginInterface;
use Composer\Script\ScriptEvents;
use Composer\Installer\InstallerEvents;

/**
 * This class is the entry point for the NodeJs plugin.
 *
 * @author David Négrier
 */
class Plugin implements PluginInterface, EventSubscriberInterface
{
    const DOWNLOAD_NODEJS_EVENT = 'download-nodejs';
    
    /**
     * @var Composer
     */
    private $composer;
    
    /**
     * @var IOInterface
     */
    private $cliIo;

    /**
     * @var \Mouf\NodeJsInstaller\Composer\OperationAnalyser
     */
    private $operationAnalyser;

    /**
     * @var \Mouf\NodeJsInstaller\NodeJs\Bootstrap
     */
    private $nodeJsBootstrap;

    public function activate(Composer $composer, IOInterface $cliIo)
    {
        $this->composer = $composer;
        $this->cliIo = $cliIo;

        $this->operationAnalyser = new \Mouf\NodeJsInstaller\Composer\OperationAnalyser();
        
        $this->nodeJsBootstrap = new \Mouf\NodeJsInstaller\NodeJs\Bootstrap(
            $cliIo,
            $this->composer->getRepositoryManager()->getLocalRepository(),
            $composer->getPackage(),
            $composer->getConfig()->get('vendor-dir'),
            $composer->getConfig()->get('bin-dir')
        );
    }

    /**
     * @inheritDoc
     *
     * @return array
     */
    public static function getSubscribedEvents()
    {
        return array(
            InstallerEvents::PRE_DEPENDENCIES_SOLVING => array(
                array('onPostUpdateInstall', 199),
            ),
            ScriptEvents::POST_INSTALL_CMD => array(
                array('onPostUpdateInstall', 199),
            ),
            ScriptEvents::POST_UPDATE_CMD => array(
                array('onPostUpdateInstall', 199),
            ),
            self::DOWNLOAD_NODEJS_EVENT => array(
                array('onPostUpdateInstall', 199)
            ),
            \Composer\Installer\PackageEvents::PRE_PACKAGE_UNINSTALL => 'disableFeatures'
        );
    }

    public function disableFeatures(\Composer\Installer\PackageEvent $event)
    {
        if (!$this->operationAnalyser->isUninstallOperationForNamespace($event->getOperation(), __NAMESPACE__)) {
            return;
        }

        $this->nodeJsBootstrap->unload();

        $this->nodeJsBootstrap = null;
    }
    
    public function onPostUpdateInstall()
    {
        if (!$this->nodeJsBootstrap) {
            return;
        }
                 
        $this->nodeJsBootstrap->dispatch();
    }
}
