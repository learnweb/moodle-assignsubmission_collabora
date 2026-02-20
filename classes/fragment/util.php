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

namespace assignsubmission_collabora\fragment;

use assignsubmission_collabora\api\collabora_fs;

/**
 * Util class for fragment api.
 *
 * @package    assignsubmission_collabora
 *
 * @author     Andreas Grabs <moodle@grabs-edv.de>
 * @copyright  2019 Humboldt-Universität zu Berlin <moodle-support@cms.hu-berlin.de>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class util extends \mod_collabora\fragment\util {
    /**
     * Get the wopi src for a javascript as json string.
     *
     * @param [] $args
     * @return string
     */
    protected static function get_wopi_src($args) {
        global $DB, $USER;

        $component = 'assignsubmission_collabora';

        // Id is the submission-id.
        $id = $args['id'] ?? false;
        if (empty($id)) {
            throw new \moodle_exception('missing or wrong id');
        }

        $submission    = $DB->get_record('assign_submission', ['id' => $id], '*', MUST_EXIST);
        [$course, $cm] = get_course_and_cm_from_instance($submission->assignment, 'assign');

        if ((!empty($submission->userid)) && $submission->userid != $USER->id) {
            require_capability('mod/assign:viewgrades', $cm->context);
            $user = $DB->get_record('user', ['id' => $submission->userid], '*', MUST_EXIST);
        } else {
            require_capability('mod/assign:view', $cm->context);
            $user = $USER;
        }

        $fs = get_file_storage();

        // For now we check for the submission file existance 1st.
        $files = $fs->get_area_files(
            $cm->context->id,
            $component,
            collabora_fs::FILEAREA_SUBMIT,
            $submission->id,
            '',
            false,
            0,
            0,
            1
        );
        if (!$submissionfile = reset($files)) {
            $files = $fs->get_area_files(
                $cm->context->id,
                $component,
                collabora_fs::FILEAREA_INITIAL,
                0,
                '',
                false,
                0,
                0,
                1
            );
            if (!$submissionfile = reset($files)) {
                throw new \moodle_exception('Missing initial file.');
            }
        }

        $collaborafs = new collabora_fs($user, $submissionfile);
        $params      = $collaborafs->get_view_params(false); // We don't show the close button at all.

        return json_encode($params);
    }
}
