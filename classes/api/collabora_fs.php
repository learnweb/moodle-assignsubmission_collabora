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

namespace assignsubmission_collabora\api;

/**
 * Main support functions.
 *
 * @package   assignsubmission_collabora
 * @copyright 2022 Andreas Grabs <moodle@grabs-edv.de>
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class collabora_fs extends \mod_collabora\api\base_filesystem {
    /** Define the filearea for submission files */
    public const FILEAREA_SUBMIT = 'submission_file';
    /** Define the filearea for converted pdf files */
    public const FILEAREA_PDFCONVERTED = 'pdfconverted';

    /** @var string */
    private $accesstoken;
    /** @var \context */
    private $context;
    /** @var bool */
    private $writable;
    /** @var \stdClass */
    private $submission;
    /** @var \assign */
    private $assign;

    /**
     * Retrieves the plugin configuration.
     *
     * @return \stdClass The plugin configuration object containing all settings
     */
    public static function get_global_config() {
        static $config;

        if (empty($config)) {
            $config = get_config('assignsubmission_collabora');
        }
        return $config;
    }

    /**
     * Get the moodle user id from the collabora_token table.
     *
     * @param  string $token
     * @return int
     */
    public static function get_userid_from_token($token) {
        [$shastr, $userid] = explode('_', $token);
        if ($shastr != md5($userid)) {
            throw new \moodle_exception('wrong accesstoken');
        }

        return $userid;
    }

    /**
     * Get an instance of this class by using the fileid and the accesstoken comming from the request (collabora server).
     *
     * @param  string $fileid
     * @param  string $accesstoken
     * @return static
     */
    public static function get_instance_by_fileid($fileid, $accesstoken) {
        global $DB;

        $userid = static::get_userid_from_token($accesstoken);
        $user   = $DB->get_record('user', ['id' => $userid], '*', MUST_EXIST);

        [$filehash, $writable] = explode('_', $fileid);

        // Get the stored file.
        $fs = get_file_storage();
        if ($file = $fs->get_file_by_hash($filehash)) {
            // Check if the file is actually one of ours.
            if ($file->get_component() !== 'assignsubmission_collabora') {
                throw new \moodle_exception('invalidrequestnofile', 'assignsubmission_collabora');
            }
        } else {
            throw new \moodle_exception('invalidrequestfile', 'assignsubmission_collabora');
        }

        $instance = new static($user, $file);
        if (empty($writable)) {
            $instance->force_readonly();
        }

        return $instance;
    }

    /**
     * Check the write permission.
     *
     * @param  \assign      $assign
     * @param  \stdClass    $submission
     * @param  int          $userid
     * @param  \stored_file $file
     * @return bool
     */
    public static function check_writable($assign, $submission, $userid, $file) {
        global $CFG, $USER;
        require_once($CFG->dirroot . '/mod/assign/locallib.php');

        // Site Admins && graders (teachers/managers) cannot edit the file - irrespective of submission status.
        if (is_siteadmin() || $assign->can_grade()) {
            if ($USER->id != $userid) {
                return false;
            }
        }
        // Is the submission editable by the current user? - The lock status is enough to tell us.
        if ($assign->submissions_open($userid, null, $submission)) {
            return $file->get_itemid() == $submission->id;
        }

        return false;
    }

    /**
     * Converts a file to PDF format and stores it as a new stored_file object.
     *
     * This method takes an existing stored file, converts it to PDF using the
     * convert_to_pdf() method, and creates a new stored file with the PDF content
     * in a separate file area.
     *
     * @param \stored_file $filetoconvert The file to be converted to PDF
     * @return \stored_file|null The newly created PDF file or null if conversion failed
     */
    public static function convert_to_pdf_file(\stored_file $filetoconvert): ?\stored_file {
        $pdffilename = $filetoconvert->get_filename() . '.pdf';
        $pdffile = null;

        // Get pdf as string and store it as file.
        if ($pdfdata = static::convert_to_pdf($filetoconvert)) {
            $filerecord = (object) [
                'contextid' => $filetoconvert->get_contextid(),
                'component' => 'assignsubmission_collabora',
                'filearea'  => static::FILEAREA_PDFCONVERTED,
                'itemid'    => $filetoconvert->get_itemid(),
                'filepath'  => '/',
                'filename'  => $pdffilename,
            ];

            $fs = get_file_storage();
            $pdffile = $fs->create_file_from_string($filerecord, $pdfdata);
        }

        return $pdffile;
    }

    /**
     * Checks whether PDF conversion is enabled in the global configuration.
     *
     * @return bool True if PDF conversion is enabled, false otherwise
     */
    public static function is_pdf_convert_enabled() {
        $config = static::get_global_config();
        return !empty($config->enablepdfconvert);
    }

    /**
     * Constructor.
     *
     * @param \stdClass    $user
     * @param \stored_file $file
     */
    public function __construct($user, $file) {
        global $CFG;
        require_once($CFG->dirroot . '/mod/assign/locallib.php');

        $this->context = \context::instance_by_id($file->get_contextid());

        [$course, $cm] = get_course_and_cm_from_cmid($this->context->instanceid, 'assign');
        $this->assign  = new \assign($this->context, $cm, $course);
        if ($this->assign->get_instance()->teamsubmission) {
            $this->submission = $this->assign->get_group_submission($user->id, 0, false);
        } else {
            $this->submission = $this->assign->get_user_submission($user->id, false);
        }

        // Userid is unique to our installation. - Id will always be the same.
        $this->accesstoken = md5($user->id) . '_' . $user->id;
        $callbackurl       = new \moodle_url('/mod/assign/submission/collabora/callback.php');
        parent::__construct($user, $file, $callbackurl, 0, false); // This plugin does not use versions at all.

        $this->writable = $this->check_writable($this->assign, $this->submission, $user->id, $file);
    }

    /**
     * Set the filesystem as readonly for the collabora server.
     *
     * @return void
     */
    public function force_readonly() {
        $this->writable = false;
    }

    /* Methods from interface i_filesystem
     * ##################################### */

    /**
     * Is the file read-only?
     *
     * @return bool
     */
    public function is_readonly() {
        return !$this->writable;
    }

    /**
     * Get the fileid that will be returned to retrieve the correct file.
     *
     * @return string
     */
    public function get_file_id() {
        return $this->file->get_pathnamehash() . '_' . $this->writable;
    }

    /**
     * Unique identifier for the current user accessing the document.
     *
     * @return string
     */
    public function get_user_identifier() {
        return $this->accesstoken;
    }

    /**
     * Retrieve the existing unique user token.
     *
     * @return string
     */
    public function get_user_token() {
        return $this->accesstoken;
    }

    /**
     * Update the stored file.
     *
     * @param  string $content
     * @return void
     */
    public function update_file($content) {
        if (static::is_testing()) {
            $this->writable = true;
        }
        parent::update_file($content);
    }
}
