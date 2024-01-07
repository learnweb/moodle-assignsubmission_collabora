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
 * Converter for old collabora submissions.
 *
 * @package   assignsubmission_collabora
 * @copyright 2022 Andreas Grabs <moodle@grabs-edv.de>
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace assignsubmission_collabora;

/**
 * Test Setup Trait for callbacklib_test.php and locallib_test.php.
 *
 * @package   assignsubmission_collabora
 * @copyright 2019 Benjamin Ellis, Synergy Learning, 2020 Justus Dieckmann WWU
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class convert {
    /**
     * The method converts the old structure into the new one and
     * is used while upgrade to the new data structure which provides backup and restore with userdata.
     * A new table assignsubmission_collabora is needed therefore and also we do not use the filearea "group" and "user" anymore.
     * Instead we use the filearea "submission_file" for all submissions.
     *
     * @return void
     */
    public static function run() {
        global $DB;

        // Get all assigns with enabled collabora submission.
        $params = [
            'plugin'  => 'collabora',
            'subtype' => 'assignsubmission',
            'name'    => 'enabled',
            'value'   => 1,
        ];
        $sql = 'SELECT distinct assignment as id, assignment
                FROM {assign_plugin_config}
                WHERE plugin = :plugin AND subtype = :subtype AND name = :name AND value = :value';

        if (!$assignids = $DB->get_records_sql_menu($sql, $params)) {
            return;
        }
        foreach ($assignids as $assignid) {
            mtrace('Update submissions for assign: ' . $assignid);
            $assign = $DB->get_record('assign', ['id' => $assignid], 'id, teamsubmission');
            // Process the group submissions.
            if (!empty($assign->teamsubmission)) {
                mtrace('    groupmode');
                $sql = 'SELECT id, groupid FROM {assign_submission} WHERE assignment = :assignment AND groupid > 0';
                if (!$submissions = $DB->get_records_sql($sql, ['assignment' => $assign->id])) {
                    continue;
                }
                // Now get the files by using the context instance and the groupid.
                if (!$cm = get_coursemodule_from_instance('assign', $assignid)) {
                    continue;
                }
                $context = \context_module::instance($cm->id);
                foreach ($submissions as $submission) {
                    mtrace('        submission: ' . $submission->id);
                    // Get the files.
                    if (!$files = $DB->get_records(
                        'files',
                        [
                            'contextid' => $context->id,
                            'filearea'  => 'group',
                            'itemid'    => $submission->groupid,
                        ]
                    )) {
                        continue;
                    }
                    foreach ($files as $file) {
                        mtrace('        fileid: ' . $file->id . ', submissionid: ' . $submission->id);
                        // Set the new filearea and the itemid to $submission->id.
                        $file->filearea = \assignsubmission_collabora\api\collabora_fs::FILEAREA_SUBMIT;
                        $file->itemid   = $submission->id;
                        $DB->update_record('files', $file);
                    }
                    // Create an entry for assignsubmission_collabora.
                    $rec             = new \stdClass();
                    $rec->assignment = $assignid;
                    $rec->submission = $submission->id;
                    $rec->numfiles   = 1;
                    if (!$DB->record_exists('assignsubmission_collabora', (array) $rec)) {
                        $DB->insert_record('assignsubmission_collabora', $rec);
                    }
                }
            } else {
                // Process the user submissions.
                mtrace('    usermode');
                $sql = 'SELECT id, userid FROM {assign_submission} WHERE assignment = :assignment';
                if (!$submissions = $DB->get_records_sql($sql, ['assignment' => $assign->id])) {
                    continue;
                }
                // Now get the files by using the context instance and the groupid.
                if (!$cm = get_coursemodule_from_instance('assign', $assignid)) {
                    continue;
                }
                $context = \context_module::instance($cm->id);
                foreach ($submissions as $submission) {
                    mtrace('        submission: ' . $submission->id);
                    // Get the files.
                    if (!$files = $DB->get_records(
                        'files',
                        [
                            'contextid' => $context->id,
                            'filearea'  => 'user',
                            'itemid'    => $submission->userid,
                        ]
                    )) {
                        continue;
                    }
                    foreach ($files as $file) {
                        mtrace('        fileid: ' . $file->id . ', submissionid: ' . $submission->id);
                        // Set the new filearea and the itemid to $submission->id.
                        $file->filearea = \assignsubmission_collabora\api\collabora_fs::FILEAREA_SUBMIT;
                        $file->itemid   = $submission->id;
                        $DB->update_record('files', $file);
                    }
                    // Create an entry for assignsubmission_collabora.
                    $rec             = new \stdClass();
                    $rec->assignment = $assignid;
                    $rec->submission = $submission->id;
                    $rec->numfiles   = 1;
                    if (!$DB->record_exists('assignsubmission_collabora', (array) $rec)) {
                        $DB->insert_record('assignsubmission_collabora', $rec);
                    }
                }
            }
        }
    }
}
