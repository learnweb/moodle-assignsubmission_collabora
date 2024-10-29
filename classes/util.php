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

namespace assignsubmission_collabora;

use mod_collabora\api\collabora_fs;
use mod_collabora\util as collabora_util;

/**
 * Util class for assignsubmission_collabora.
 *
 * @package    assignsubmission_collabora
 *
 * @author     Andreas Grabs <moodle@grabs-edv.de>
 * @copyright  2019 Humboldt-Universität zu Berlin <moodle-support@cms.hu-berlin.de>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class util {
    /**
     * Stores the initial file for the Collabora assignment submission.
     *
     * This method handles the logic for storing the initial file based on the submission format selected by the user.
     * It supports different formats like:
     * - system templates  - definined in the system settings
     * - upload            - the user can upload a file
     * - text              - the user can enter text
     * - legacy templates  - the old fashioned hard coded templates (presentation, wordprodessor, spreadsheet)
     *
     * @param \stdClass $filerec The file record object containing information about the file to be stored.
     * @param \stdClass $data The submission data object containing the user's selected format and other relevant information.
     * @return bool True if the file was successfully stored, false otherwise.
     */
    public static function store_initial_file(\stdClass $filerec, \stdClass $data): bool {
        global $CFG;

        $fs = get_file_storage();

        // Store the initial file from the uploaded file.
        // We use the file_save_draft_area_files() method for this.
        if ($data->assignsubmission_collabora_format == collabora_util::FORMAT_UPLOAD) {
            $info = file_get_draft_area_info($data->assignsubmission_collabora_initialfile_filemanager);
            if ($info['filecount']) {
                // Save the uploaded file as the initial file.
                try {
                    file_save_draft_area_files(
                        $data->assignsubmission_collabora_initialfile_filemanager,
                        $filerec->contextid,
                        $filerec->component,
                        $filerec->filearea,
                        $filerec->itemid,
                        static::get_default_fileoptions()
                    );
                    return true;
                } catch (\moodle_exception $e) {
                    return false;
                }
            }
            return false;
        }

        // Store a text file as initial file if the format is text.
        // The initial content comes from the initialtext field.
        if ($data->assignsubmission_collabora_format == collabora_util::FORMAT_TEXT) {
            $filerec->filename .= '.txt';
            if ($fs->create_file_from_string($filerec, $data->assignsubmission_collabora_initialtext)) {
                return true;
            }
            return false;
        }

        // If the format is a value from the legacy templates we store the initial file from one of them.
        $templates = collabora_util::get_legacy_templates();
        if ($filepath = ($templates[$data->assignsubmission_collabora_format] ?? '')) {
            $ext = strtolower(pathinfo($filepath, PATHINFO_EXTENSION));
            $filerec->filename .= '.' . $ext;
            if ($fs->create_file_from_pathname($filerec, $filepath)) {
                return true;
            }
            return false;
        }

        // The last check is for the system templates.
        // It uses the pathnamehash from the template file.
        $fs = get_file_storage();
        $file = $fs->get_file_by_hash($data->assignsubmission_collabora_format);
        if (empty($file)) {
            throw new \moodle_exception('filenotfound', 'error');
        }
        $ext = strtolower(pathinfo($file->get_filename(), PATHINFO_EXTENSION));
        $filerec->filename .= '.' . $ext;
        if ($fs->create_file_from_storedfile($filerec, $file)) {
            return true;
        }
        return false;
    }

    /**
     * The default file options.
     *
     * @return array of file options
     */
    public static function get_default_fileoptions() {
        return [
            'subdirs'        => 0,
            'maxbytes'       => 0,
            'maxfiles'       => 1,
            'accepted_types' => collabora_fs::get_accepted_types(),
        ];
    }

    /**
     * Generate a filename where one is not provided.
     *
     * Moodle error for assignment plugins is a print_error() which is nasty for users.
     *
     * @return string
     */
    public static function generaterandonfilename() {
        return 'aaa' .
            substr(str_shuffle(
                str_repeat('abcdefghijklmnopqrstuvwxyz0123456789ABCDEFGHIJKLMNOPQRSTUVWXYZ',
                    mt_rand(1, 10))), 1, 7);
    }
}
