<?php
/**
 * Copyright © Vaimo Group. All rights reserved.
 * See LICENSE_VAIMO.txt for license details.
 */
namespace Mouf\NodeJsInstaller\Utils;

class FileUtils
{
    public static function composePath()
    {
        $args = \func_get_args();
        
        $pathSegments = \array_map(function ($item, $index) {
            return $index ? \trim($item, \DIRECTORY_SEPARATOR) : \rtrim($item, \DIRECTORY_SEPARATOR);
        }, $args, array_keys($args));

        return \implode(
            \DIRECTORY_SEPARATOR,
            \array_filter($pathSegments)
        );
    }

    public static function getClosestFilePath($path, $fileName)
    {
        while (true) {
            if (\is_dir($path) && \file_exists(self::composePath($path, $fileName))) {
                return $path;
            }

            $parent = \dirname($path);

            if ($parent === $path) {
                break;
            }

            $path = $parent;
        }

        return false;
    }
}
