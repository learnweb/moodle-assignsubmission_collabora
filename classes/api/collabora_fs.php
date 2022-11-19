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
 * Main support functions
 *
 * @package   mod_collabora
 * @copyright 2019 Davo Smith, Synergy Learning
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class collabora_fs extends \mod_collabora\api\base_filesystem {
    private $userpermission;
    /** @var string */
    private $accesstoken;

    /**
     * Get the moodle user id from the collabora_token table
     *
     * @param string $token
     * @return int
     */
    public static function get_userid_from_token($token) {
        list($shastr, $userid) = explode('_', $token);
        if ($shastr != md5($userid)) {
            throw new \moodle_exception('wrong accesstoken');
        }
        return $userid;
    }

    public static function get_instance_by_fileid($fileid, $accesstoken) {
        global $DB;

        $userid = static::get_userid_from_token($accesstoken);
        $user = $DB->get_record('user', array('id' => $userid), '*', MUST_EXIST);

        list($filehash, $userpermission) = explode('_', $fileid);
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

        return new static($user, $file, $userpermission);

    }

    /**
     * Constructor
     *
     * @param \stdClass $user
     * @param \stored_file $file
     * @param int $userpermission
     */
    public function __construct($user, $file, $userpermission) {
        $this->userpermission = $userpermission;
        // Userid is unique to our installation. - Id will always be the same.
        $this->accesstoken = md5($user->id) . '_'. $user->id;
        $callbackurl = new \moodle_url('/mod/assign/submission/collabora/callback.php');
        parent::__construct($user, $file, $callbackurl);
    }

    public function deeper_check_permissions() {
        global $CFG;

        require_once($CFG->dirroot . '/mod/assign/locallib.php');

        $userid = $this->user->id;

        $context = \context::instance_by_id($this->get_file()->get_contextid());
        $cm = get_coursemodule_from_id('assign', $context->instanceid);
        list ($course, $cm) = get_course_and_cm_from_cmid($context->instanceid, 'assign');
        $assign = new \assign($context, $cm, $course);
        $submission = $assign->get_user_submission($userid, false);

        $permission  = 444;     // Default - All can read.

        // Site Admins && graders (teachers/managers) cannot edit the file - irrespective of submission status.
        if (!is_siteadmin() && !$assign->can_grade()) {
            // Is the submission editable by the current user? - The lock status is enough to tell us.
            if ($assign->submissions_open($userid, null, $submission)) {
                if (!empty($submission->groupid)) {       // Group membership checked in submissions_open() call.
                    $permission = 660;
                } else if ($submission->userid == $userid) {
                    $permission = 600;
                }
            }
        }
        return $permission >= 600;

    }

    /* Methods from interface i_filesystem
     * ##################################### */

    /**
     * Is the file read-only?
     *
     * @return bool
     */
    public function is_readonly() {
        // Check if the file is a group file and if the user is a member of the relevant group.
        if (!empty($this->userpermission)) {
            // Work out the permissions.
            /*
             * - Owner + Group + Others (Site Admin)
             400 = Owner can read
             440 = Group can read
             444 = All Read only
             600 = Owner Can Edit - No Group
             660 = Group can Edit
             666 = All can Edit - site admin
             */
            if ($this->userpermission >= 600) {
                return !$this->deeper_check_permissions();
            }
        }
        return true;
    }

    /**
     * Get the fileid that will be returned to retrieve the correct file.
     *
     * @return string
     */
    public function get_file_id() {
        return $this->file->get_pathnamehash() . '_' . $this->userpermission;
    }

    /**
     * Unique identifier for the current user accessing the document.
     *
     * @return string
     */
    public function get_user_identifier() {
        return $this->accesstoken;
    }

    public function get_user_token() {
        return $this->accesstoken;
    }
}
