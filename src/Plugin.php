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
     * @var \Mouf\NodeJsInstaller\Composer\OperationAnalyser
     */
    private $operationAnalyser;

    /**
     * @var \Mouf\NodeJsInstaller\Strategy\BootstrapStrategy
     */
    private $bootstrapStrategy;
    
    /**
     * @var \Mouf\NodeJsInstaller\NodeJs\Bootstrap
     */
    private $nodeJsBootstrap;

    /**
     * @param \Composer\Composer       $composer
     * @param \Composer\IO\IOInterface $cliIo
     *
     * @return void
     */
    public function activate(Composer $composer, IOInterface $cliIo): void
    {
        $this->operationAnalyser = new \Mouf\NodeJsInstaller\Composer\OperationAnalyser();

        $composerContextFactory = new \Mouf\NodeJsInstaller\Factory\ComposerContextFactory($composer);
        $composerContext = $composerContextFactory->create();
        
        $this->bootstrapStrategy = new \Mouf\NodeJsInstaller\Strategy\BootstrapStrategy($composerContext);
        
        $this->nodeJsBootstrap = new \Mouf\NodeJsInstaller\NodeJs\Bootstrap(
            $composerContext,
            $cliIo
        );
    }

    /**
     * @inheritDoc
     *
     * @return array
     */
    public static function getSubscribedEvents(): array
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

    /**
     * @param \Composer\Installer\PackageEvent $event
     *
     * @return void
     */
    public function disableFeatures(\Composer\Installer\PackageEvent $event): void
    {
        if (!$this->operationAnalyser->isUninstallOperationForNamespace($event->getOperation(), __NAMESPACE__)) {
            return;
        }

        $this->nodeJsBootstrap->unload();

        $this->nodeJsBootstrap = null;
    }
    
    public function onPostUpdateInstall()
    {
        if (!$this->nodeJsBootstrap || !$this->bootstrapStrategy->shouldAllow()) {
            return;
        }
                 
        $this->nodeJsBootstrap->dispatch();
    }

    /**
     * @param \Composer\Composer       $composer
     * @param \Composer\IO\IOInterface $io
     *
     * @return void
     */
    public function uninstall(Composer $composer, IOInterface $io): void
    {
        // TODO: Implement uninstall() method.
    }

    /**
     * @param \Composer\Composer       $composer
     * @param \Composer\IO\IOInterface $io
     *
     * @return void
     */
    public function deactivate(Composer $composer, IOInterface $io): void
    {
        // TODO: Implement deactivate() method.
    }
}
