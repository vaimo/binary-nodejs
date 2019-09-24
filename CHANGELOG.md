# Changelog

_This file has been auto-generated from the contents of changelog.json_

## 4.0.1 (2019-09-24)

### Fix

* allow the plugin to be installed as dependency to globally installed package (as part of dependency of some global package); previously caused every composer call to crash with class declaration conflict
* removed class reference to dependency that is not listed (and should not be) as dependency

Links: [src](https://github.com/vaimo/binary-nodejs/tree/4.0.1) [diff](https://github.com/vaimo/binary-nodejs/compare/4.0.0...4.0.1)

## 4.0.0 (2019-07-21)

### Breaking

* behaviour: the default behaviour of the package is to install local version of the node even when global available; Flag 'forceLocal' renamed to 'useGlobal', defaults to FALSE

Links: [src](https://github.com/vaimo/binary-nodejs/tree/4.0.0) [diff](https://github.com/vaimo/binary-nodejs/compare/3.0.0...4.0.0)

## 3.0.0 (2019-07-17)

### Breaking

* code: removed Windows support (currently not needed)
* configuration: download path no longer configurable (will always be downloaded to a sub-folder of the package and binary script made to point to it)

### Feature

* switched over to using Composer downloader through creating virtual package for the NodeJs
* no longer downloading nodejs under it's own vendor namespace: rather using a sub-folder within the plugin (as it fully owns the download)

Links: [src](https://github.com/vaimo/binary-nodejs/tree/3.0.0) [diff](https://github.com/vaimo/binary-nodejs/compare/2.0.1...3.0.0)

## 2.0.1 (2019-07-16)

### Fix

* make sure that no errors are encountered when plugin gets uninstalled

### Maintenance

* code re-organized
* all logic that relied on classes to be in certain depth of directory structure removed

Links: [src](https://github.com/vaimo/binary-nodejs/tree/2.0.1) [diff](https://github.com/vaimo/binary-nodejs/compare/2.0.0...2.0.1)

## 2.0.0 (2019-07-09)

### Breaking

* event sequence changed where nodejs observers are triggered very early to allow other plugins that rely on nodejs to be installed to work correctly

### Maintenance

* code styling fixes
* introduced static code analysis with compatibility ruleset (to make sure that hte plugin works with php5.3)

Links: [src](https://github.com/vaimo/binary-nodejs/tree/2.0.0) [diff](https://github.com/vaimo/binary-nodejs/compare/33286fd459b8961cfd92f8982b4e657de527a86a...2.0.0)