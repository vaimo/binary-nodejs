<?php
namespace Mouf\NodeJsInstaller\Nodejs;

use Composer\IO\IOInterface;
use Composer\Util\RemoteFilesystem;
use Mouf\NodeJsInstaller\Utils\FileUtils;
use Mouf\NodeJsInstaller\Composer\Environment;
use Mouf\NodeJsInstaller\Composer\ConfigKeys;

class Installer
{
    /**
     * @var IOInterface
     */
    private $cliIo;

    /**
     * @var RemoteFilesystem
     */
    private $remoteFilesystem;

    /**
     * @var string
     */
    private $vendorDir;

    /**
     * @var string
     */
    private $binDir;

    public function __construct(
        IOInterface $cliIo,
        $vendorDir,
        $binDir
    ) {
        $this->cliIo = $cliIo;
        $this->vendorDir = $vendorDir;
        $this->binDir = $vendorDir;

        $this->remoteFilesystem = new RemoteFilesystem($cliIo);
    }

    /**
     * Checks if NodeJS is installed globally.
     * If yes, will return the version number.
     * If no, will return null.
     *
     * Note: trailing "v" will be removed from version string.
     *
     * @return null|string
     */
    public function getNodeJsGlobalInstallVersion()
    {
        $returnCode = 0;
        $output = '';

        ob_start();
        $version = exec('nodejs -v 2>&1', $output, $returnCode);
        ob_end_clean();

        if ($returnCode !== 0) {
            ob_start();
            $version = exec('node -v 2>&1', $output, $returnCode);
            ob_end_clean();

            if ($returnCode !== 0) {
                return;
            }
        }

        return ltrim($version, 'v');
    }

    /**
     * Returns the full path to NodeJS global install (if available).
     */
    public function getNodeJsGlobalInstallPath()
    {
        $pathToNodeJS = $this->getGlobalInstallPath('nodejs');
        if (!$pathToNodeJS) {
            $pathToNodeJS = $this->getGlobalInstallPath('node');
        }

        return $pathToNodeJS;
    }

    /**
     * Returns the full install path to a command
     * 
     * @param string $command
     * @return string
     */
    public function getGlobalInstallPath($command)
    {
        if (Environment::isWindows()) {
            $result = trim(
                shell_exec('where /F ' . escapeshellarg($command)),
                "\n\r"
            );

            // "Where" can return several lines.
            $lines = explode("\n", $result);

            return $lines[0];
        } else {
            // We want to get output from stdout, not from stderr.
            // Therefore, we use proc_open.
            $descriptorSpec = array(
                0 => array('pipe', 'r'),  // stdin
                1 => array('pipe', 'w'),  // stdout
                2 => array('pipe', 'w'),  // stderr
            );
            $pipes = array();

            $process = proc_open('which ' . escapeshellarg($command), $descriptorSpec, $pipes);

            $stdout = stream_get_contents($pipes[1]);
            fclose($pipes[1]);

            // Let's ignore stderr (it is possible we do not find anything and depending on the OS, stderr will
            // return things or not)
            fclose($pipes[2]);

            proc_close($process);

            return trim($stdout, "\n\r");
        }
    }

    /**
     * Checks if NodeJS is installed locally.
     * 
     * If yes, will return the version number.
     * If no, will return null.
     *
     * Note: trailing "v" will be removed from version string.
     *
     * @return null|string
     */
    public function getNodeJsLocalInstallVersion($binDir)
    {
        $returnCode = 0;
        $output = '';

        $cwd = getcwd();
        
        $projectRoot = FileUtils::getClosestFilePath($this->vendorDir, ConfigKeys::PACKAGE_CONFIG_FILE);
        
        chdir($projectRoot);

        ob_start();

        $cmd = FileUtils::composePath($binDir, 'node -v 2>&1');
        
        $version = exec($cmd, $output, $returnCode);

        ob_end_clean();

        chdir($cwd);

        if ($returnCode !== 0) {
            return null;
        }

        return ltrim($version, 'v');
    }

    private function getArchitectureLabel()
    {
        $code = Environment::getArchitecture();
        
        $labels = array(
            32 => 'x86',
            64 => 'x64'
        );
        
        return isset($labels[$code]) ? $labels[$code] : $code;
    }
    
    private function getOsLabel()
    {
        $osLabel = '';
        
        $isArm = Environment::isLinux() && Environment::isArm();
        
        if (Environment::isMacOS()) {
            $osLabel = 'darwin';
        } elseif (Environment::isSunOS()) {
            $osLabel =  'sunos';
        } elseif ($isArm && Environment::isArmV6l()) {
            $osLabel = 'linux-armv6l';
        } elseif ($isArm && Environment::isArmV7l()) {
            $osLabel = 'linux-armv7l';
        } elseif ($isArm && Environment::getArchitecture() === 64) {
            $osLabel = 'linux-arm64';
        } elseif (Environment::isLinux()) {
            $osLabel = 'linux';
        } elseif (Environment::isWindows()) {
            $osLabel = 'windows';
        }
        
        if (!$osLabel) {
            throw new \Mouf\NodeJsInstaller\Exception\InstallerException(
                'Unsupported architecture: ' . PHP_OS . ' - ' . Environment::getArchitecture() . ' bits'
            );
        }
        
        return $osLabel;
    }
    
    /**
     * Returns URL based on version.
     * 
     * URL is dependent on environment
     * 
     * @param  string $version
     * @return string
     * @throws \Mouf\NodeJsInstaller\Exception\InstallerException
     */
    public function getNodeJSUrl($version)
    {
        
        $baseUrl = \Mouf\NodeJsInstaller\NodeJs\Version\Lister::NODEJS_DIST_URL . 'v{{VERSION}}';
        $downloadPath = '';
        
        if (Environment::isWindows()) {
            $binaryName = 'node.exe';
            
            if (version_compare($version, '4.0.0') >= 0) {
                $downloadPath = FileUtils::composePath('win-{{ARCHITECTURE}}', $binaryName);
            } else {
                $downloadPath = Environment::getArchitecture() === 32
                    ? $binaryName
                    : FileUtils::composePath('{{ARCHITECTURE}}', $binaryName);
            }
        } elseif (Environment::isMacOS() || Environment::isSunOS() || Environment::isLinux()) {
            $downloadPath = 'node-v{{VERSION}}-{{OS}}-{{ARCHITECTURE}}.tar.gz';
        } elseif (Environment::isLinux() && Environment::isArm()) {
            if (version_compare($version, '4.0.0') < 0) {
                throw new \Mouf\NodeJsInstaller\Exception\InstallerException(
                    'NodeJS-installer cannot install Node <4.0 on computers with ARM processors. Please ' .
                    'install NodeJS globally on your machine first, then run composer again, or consider ' .
                    'installing a version of NodeJS >=4.0.'
                );
            }

            if (Environment::isArmV6l() || Environment::isArmV7l() || Environment::getArchitecture()) {
                $downloadPath = 'node-v{{VERSION}}-{{OS}}.tar.gz';
            } else {
                throw new \Mouf\NodeJsInstaller\Exception\InstallerException(
                    'NodeJS-installer cannot install Node on computers with ARM 32bits processors ' .
                    'that are not v6l or v7l. Please install NodeJS globally on your machine first, ' .
                    'then run composer again.'
                );
            }
        }
        
        return str_replace(
            array('{{VERSION}}', '{{ARCHITECTURE}}', '{{OS}}'),
            array($version, $this->getArchitectureLabel(), $this->getOsLabel()),
            $baseUrl . '/' . $downloadPath
        );
    }

    /**
     * Installs NodeJS
     * 
     * @param  string $version
     * @param  string $targetDirectory
     * @throws \Mouf\NodeJsInstaller\Exception\InstallerException
     */
    public function install($version, $targetDirectory)
    {
        $url = $this->getNodeJSUrl($version);

        $this->cliIo->write(
            sprintf('Installing <info>NodeJS v%s</info>', $version)
        );
        
        $this->cliIo->write(
            sprintf('<comment>Using origin: %s</comment>', $url)
        );

        $cwd = getcwd();
        
        chdir($cwd);

        $fileName = FileUtils::composePath(
            $this->vendorDir, 
            pathinfo(parse_url($url, PHP_URL_PATH), PATHINFO_BASENAME)
        );

        $this->remoteFilesystem->copy(parse_url($url, PHP_URL_HOST), $url, $fileName);

        $this->cliIo->write('');

        if (!file_exists($fileName)) {
            $message = sprintf(
                '%s could not be saved to %s, make sure the directory is writable and you have internet connectivity',
                $url,
                $fileName
            );
            
            throw new \UnexpectedValueException($message);
        }

        if (!file_exists($targetDirectory)) {
            mkdir($targetDirectory, 0775, true);
        }

        if (!is_writable($targetDirectory)) {
            throw new \Mouf\NodeJsInstaller\Exception\InstallerException(
                sprintf('\'%s\' is not writable', $targetDirectory)
            );
        }

        if (!Environment::isWindows()) {
            // Now, if we are not in Windows, let's untar.
            $this->extractTo($fileName, $targetDirectory);

            // Let's delete the downloaded file.
            unlink($fileName);
        } else {
            // If we are in Windows, let's move and install NPM.
            rename($fileName, FileUtils::composePath($targetDirectory, basename($fileName)));

            $npmArchiveName = 'npm-1.4.12.zip';
            
            // We have to download the latest available version in a bin for Windows, then upgrade it:
            $url = \Mouf\NodeJsInstaller\NodeJs\Version\Lister::NODEJS_DIST_URL . 'npm/' . $npmArchiveName;
            $npmFileName = FileUtils::composePath($this->vendorDir, $npmArchiveName);
            
            $this->remoteFilesystem->copy(parse_url($url, PHP_URL_HOST), $url, $npmFileName);
            
            $this->unzip($npmFileName, $targetDirectory);

            unlink($npmFileName);
            
            // Let's update NPM
            // 1- Update PATH to run npm.
            $path = getenv('PATH');
            $newPath = realpath($targetDirectory) . ';' . $path;
            
            putenv('PATH=' . $newPath);

            // 2- Run npm
            $cwd2 = getcwd();
            chdir($targetDirectory);

            $returnCode = 0;
            passthru('npm update npm', $returnCode);
            
            if ($returnCode !== 0) {
                throw new \Mouf\NodeJsInstaller\Exception\InstallerException(
                    'An error occurred while updating NPM to latest version.'
                );
            }

            // Finally, let's copy the base npm file for Cygwin
            if (file_exists('node_modules/npm/bin/npm')) {
                copy('node_modules/npm/bin/npm', 'npm');
            }

            chdir($cwd2);
        }

        chdir($cwd);
    }

    /**
     * Extract tar.gz file to target directory.
     *
     * @param string $tarGzFile
     * @param string $targetDir
     * @throws \Mouf\NodeJsInstaller\Exception\InstallerException
     */
    private function extractTo($tarGzFile, $targetDir)
    {
        // Note: we cannot use PharData class because it does not keeps symbolic links.
        // Also, --strip 1 allows us to remove the first directory.

        $output = $return_var = null;

        exec(
            sprintf('tar -xvf %s -C %s --strip 1', $tarGzFile, escapeshellarg($targetDir)), 
            $output, 
            $return_var
        );

        if ($return_var !== 0) {
            throw new \Mouf\NodeJsInstaller\Exception\InstallerException(
                sprintf('An error occurred while un-taring NodeJS (%s) to %s', $tarGzFile, $targetDir)
            );
        }
    }

    public function createBinScripts($binDir, $targetDir, $isLocal)
    {
        $cwd = getcwd();

        $projectRoot = FileUtils::getClosestFilePath($this->vendorDir, ConfigKeys::PACKAGE_CONFIG_FILE);
        
        chdir($projectRoot);

        if (!file_exists($binDir)) {
            $result = mkdir($binDir, 0775, true);
            if ($result === false) {
                throw new \Mouf\NodeJsInstaller\Exception\InstallerException(
                    'Unable to create directory ' . $binDir
                );
            }
        }

        $fullTargetDir = realpath($targetDir);
        $binDir = realpath($binDir);

        $suffix = '';
        $binFiles = ['node', 'npm'];
        
        if (Environment::isWindows()) {
            $suffix .= '.bat';
        }

        foreach ($binFiles as $binFile) {
            $this->createBinScript($binDir, $fullTargetDir, $binFile . $suffix, $binFile, $isLocal);
        }
        
        chdir($cwd);
    }

    /**
     * Copy script into $binDir, replacing PATH with $fullTargetDir
     *
     * @param string $binDir
     * @param string $fullTargetDir
     * @param string $scriptName
     * @param string $target
     * @param bool $isLocal
     */
    private function createBinScript($binDir, $fullTargetDir, $scriptName, $target, $isLocal)
    {
        $packageRoot = FileUtils::getClosestFilePath(__DIR__, ConfigKeys::PACKAGE_CONFIG_FILE);
        $binScriptPath = FileUtils::composePath($packageRoot, 'bin', ($isLocal ? 'local' : 'global'), $scriptName);
        
        $content = file_get_contents($binScriptPath);
        
        if ($isLocal) {
            $path = $this->makePathRelative($fullTargetDir, $binDir);
        } else {
            if ($scriptName === 'node') {
                $path = $this->getNodeJsGlobalInstallPath();
            } else {
                $path = $this->getGlobalInstallPath($target);
            }

            if (strpos($path, $binDir) === 0) {
                // we found the local installation that already exists.

                return;
            }
        }
        
        $scriptPath = FileUtils::composePath($binDir, $scriptName);
        
        file_put_contents($scriptPath, sprintf($content, $path));
        
        chmod($scriptPath, 0755);
    }

    /**
     * Shamelessly stolen from Symfony's FileSystem. Thanks guys!
     * Given an existing path, convert it to a path relative to a given starting path.
     *
     * @param string $endPath   Absolute path of target
     * @param string $startPath Absolute path where traversal begins
     *
     * @return string Path of target relative to starting path
     */
    private function makePathRelative($endPath, $startPath)
    {
        // Normalize separators on Windows
        if ('\\' === DIRECTORY_SEPARATOR) {
            $endPath = strtr($endPath, '\\', '/');
            $startPath = strtr($startPath, '\\', '/');
        }
        
        // Split the paths into arrays
        $startPathArr = explode('/', trim($startPath, '/'));
        $endPathArr = explode('/', trim($endPath, '/'));
        // Find for which directory the common path stops
        $index = 0;
        
        while (isset($startPathArr[$index], $endPathArr[$index]) && $startPathArr[$index] === $endPathArr[$index]) {
            $index++;
        }
        
        // Determine how deep the start path is relative to the common path (ie, "web/bundles" = 2 levels)
        $depth = count($startPathArr) - $index;
        
        $traverser = str_repeat('../', $depth);
        $endPathRemainder = implode('/', array_slice($endPathArr, $index));
        
        // Construct $endPath from traversing to the common path, then to the remaining $endPath
        $relativePath = $traverser . ($endPathRemainder !== '' ? $endPathRemainder . '/' : '');

        return ($relativePath === '') ? './' : $relativePath;
    }

    private function unzip($zipFileName, $targetDir)
    {
        $zip = new \ZipArchive();
        $res = $zip->open($zipFileName);
        
        if ($res === true) {
            // extract it to the path we determined above
            $zip->extractTo($targetDir);
            $zip->close();
        } else {
            throw new \Mouf\NodeJsInstaller\Exception\InstallerException(
                sprintf('Unable to extract file %s', $zipFileName)
            );
        }
    }

    /**
     * Adds the vendor/bin directory into the path.
     * Note: the vendor/bin is prepended in order to be applied BEFORE an existing install of node.
     *
     * @param string $binDir
     */
    public function registerPath($binDir)
    {
        $path = getenv('PATH');
        
        if (Environment::isWindows()) {
            $valueSeparator = ';';
        } else {
            $valueSeparator = ':';
        }

        putenv('PATH=' . realpath($binDir) . $valueSeparator . $path);
    }
}
