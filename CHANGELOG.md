# Changelog

_This file has been auto-generated from the contents of changelog.json_

## 2.0.1

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