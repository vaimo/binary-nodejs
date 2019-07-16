<?php
namespace Mouf\NodeJsInstaller\Composer;

class Environment
{
    public static function isWindows()
    {
        return defined('PHP_WINDOWS_VERSION_BUILD');
    }
    
    public static function isMacOS()
    {
        return PHP_OS === 'Darwin';
    }
    
    public static function isSunOS()
    {
        return PHP_OS === 'SunOS';
    }
    
    public static function isLinux()
    {
        return PHP_OS === 'Linux';
    }
    
    public static function isArm()
    {
        return stripos(php_uname('m'), 'arm') === 0;
    }
    
    public static function isArmV7l()
    {
        return php_uname('m') === 'armv7l';
    }
    
    public static function isArmV6l()
    {
        return php_uname('m') === 'armv6l';
    }
    
    public static function getArchitecture()
    {
        return 8 * PHP_INT_SIZE;
    }
}
