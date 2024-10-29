moodle-assignsubmission_collabora
=================================

Changes
-------

### v4.5.0
* 2024-10-30 -  Add support for new templates from mod_collabora.

### v4.3.1
* 2024-01-07 -  Adding postmessage support which is used by the mod_collabora since v4.3.1!
* 2024-01-07 -  Compatibility to Moodle 4.3
* 2024-01-11 -  Update github actions

### v4.2-r1

* 2023-04-27 -  Optimize testing mode.
* 2023-04-27 -  Fix coding style.

### v4.1-r1

* 2022-11-24 -  Optimize github actions workflow
* 2022-11-23 -  Adapt testing to the new data structure
* 2022-11-21 -  Better access check. If an assign is closed, a file becomes automatically readonly even if the same iframe is used.
* 2022-11-21 -  New data structure for collabora submissions in order to support backup/restore with userdata
* 2022-11-19 -  New abstraction for filesystem which makes it easier to share the API code between mod_collabora and this plugin.

### v4.0-beta-3

* 2022-04-04 -  Ensure LastModifiedTime is in UTC (#32)

### v4.0-beta-2

* 2022-03-06 - update requirements

### v3.11-r2

* 2022-03-04 - fix backup restore - missing inital file
* 2022-03-05 - replace travis by github actions and apply coding style

### v3.11-r1

* 2021-10-26 - use mod_collabora to load discovery xml into cache

### v3.10-r1

* 2020-10-13 - adjust .travis.yml to check moodle 3.10 properly

### v3.9-r5

* 2020-11-12 - Fix problems with loading the iframe (#8)
* 2020-11-12 - Add fullscreen mode like mod_collabora (#8)
* 2020-11-12 - Fix visibility of instance settings when not active (#8)

### v3.9-r4

* 2020-10-03 - add simple multi lang support (#7)

### v3.9-r1

* 2020-06-24 - updated version.php, CHANGES.md & README.md
* 2020-06-20 - Merge pull request #5 from justusdieckmann/travis/moodle39
* 2020-06-17 - Travis: Update for Moodle 3.9 (PR #5)

### v3.8-r3
* 2020-05-17 - Fix fullscreen for firefox (PR #4) (Thanks to Andreas Grabs for this PR)

### v3.8-r2
* 2019-12-22 - Fix missing filename issue: A random filename is now generated if none is provided
* 2019-12-22 - Leverage existing code and assets in mod_collabora

### v3.8-r1
* 2019-12-05 - add pix folder
* 2019-12-05 - add CHANGES.md & README.md
* 2019-12-05 - add travis.yml file
* 2019-12-20 - Initial plugin commit
