<?php
// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.
//
// Moodle is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
// GNU General Public License for more details.
//
// You should have received a copy of the GNU General Public License
// along with Moodle.  If not, see <http://www.gnu.org/licenses/>.

/**
 * This file defines the admin settings for this plugin
 *
 * @package   assignsubmission_collabora
 * @copyright 2012 NetSpot {@link http://www.netspot.com.au}
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die;

use mod_collabora\util as collabora_util;

$settings->add(
    new \admin_setting_configselect(
        'assignsubmission_collabora/enablepdfconvert',
        get_string('setting_enablepdfconvert', 'assignsubmission_collabora'),
        get_string('setting_enablepdfconvert_help', 'assignsubmission_collabora'),
        1,
        collabora_util::yesno_options()
    )
);
