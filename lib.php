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
 * Some hook functions.
 *
 * @package   assignsubmission_collabora
 * @copyright 2024 Andreas Grabs
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

/**
 * Get an html fragment.
 *
 * @param  mixed  $args an array or object with context and parameters needed to get the data
 * @return string The html fragment we want to use by ajax
 */
function assignsubmission_collabora_output_fragment_get_html($args) {
    return \assignsubmission_collabora\fragment\util::get_html($args);
}

/**
 * Send a stored_file to the browser.
 *
 * @param  \stdClass|int  $course
 * @param  \stdClass|null $cm
 * @param  \context       $context
 * @param  string         $filearea
 * @param  array          $args
 * @param  bool           $forcedownload
 * @param  array          $options
 * @return void
 */
function assignsubmission_collabora_pluginfile($course, $cm, $context, $filearea, $args, $forcedownload, array $options = []) {
    if ($context->contextlevel != CONTEXT_MODULE) {
        return;
    }
    require_login($course, false, $cm);
    // File link only occurs on the edit settings page, so restrict access to teachers.
    if (!has_capability('moodle/course:manageactivities', $context)) {
        return;
    }

    if ($filearea !== \mod_collabora\api\collabora_fs::FILEAREA_INITIAL) {
        return;
    }

    $itemid = (int) array_shift($args);
    if ($itemid !== 0) {
        return;
    }

    $filename = array_pop($args);
    $filepath = '/' . implode('/', $args);
    if ($filepath !== '/') {
        $filepath .= '/';
    }

    $fs   = get_file_storage();
    $file = $fs->get_file($context->id, 'assignsubmission_collabora', $filearea, $itemid, $filepath, $filename);

    if (!$file) {
        return;
    }

    send_stored_file($file, null, 0, $forcedownload, $options);
}
