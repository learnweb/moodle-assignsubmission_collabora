# ![moodle-assignsubmission_collabora](pix/icon.png) An assigment submission plugin with Collabora Online integration for Moodle

[![Build Status](https://travis-ci.org/learnweb/moodle-assignsubmission_collabora.svg?branch=master)](https://travis-ci.org/learnweb/moodle-assignsubmission_collabora)

This submodule enables Moodle users to create documents (simple text files, word, spreadsheet and presentation documents) or upload a document via a selfhosted Collabora Online Server i.e. [CODE](https://www.collaboraoffice.com/code/) using the so called WOPI protocol and work collaboratively on this documents and submit it to an assignment.

This plugin is originally written by  Benjamin Ellis from [Synergy Learning](https://www.synergy-learning.com) in 2019 and maintained by [Jan Dageförde](https://github.com/Dagefoerde) from the University of Münster and [Michael Wuttke](https://github.com/moodlebeuth) from the Beuth University of Applied Sciences in Berlin.

## Requirements
- Collabora Online Server (Version 4.0.1 or later) and Moodle Server (Version 3.5 or later) with PHP 7.0 or later.

## Tested Versions
- Collabora Online Server: 4.2.3
- Moodle: 3.7.5

## Installation
This plugin should go into mod/assign/submission/collabora. Upon installation, several default settings need to be defined for this subplugin (see Settings).

## Administrative Settings of the submodule:
![assignsubmission_collabora_administration_submission](https://user-images.githubusercontent.com/2102425/85206162-61d69680-b320-11ea-9a39-7f03864f18b5.png)

- the Collabora URL (the URL of the Collabora Online Server)

## Assignsubmission Types
![assignsubmission_collabora_submission_types](https://user-images.githubusercontent.com/2102425/85206192-9cd8ca00-b320-11ea-9b38-dc21c5bcb6d8.png)

## View of the Collabora Online Editor
![assignsubmission_collabora_office_document](https://user-images.githubusercontent.com/2102425/85206194-9ea28d80-b320-11ea-8184-9cc64c39bf77.png)

## Testing the plugin

If you want to test the collabora plugin on a local Moodle installation and a local Collabora Online Server via docker then you may find the [Collabora-Config.md](https://github.com/learnweb/moodle-mod_collabora/blob/master/Collabora-Config.md) file helpful.

## Use of Collabora trademarks

The name "Collabora" is used to indicate that the plugin provides an integration facility for use of Collabora Online from within Moodle.
The name does not imply an endorsement by Collabora, nor does it indicate who develops and provides the plugin.
This plugin was created and is offered by members of the community.

Note that the plugin also makes use of icons that, some of which are trademarks of Collabora.
The icons are made available to you under conditions that differ from the rest of the plugin; see [pix/LICENSE](pix/LICENSE/).
