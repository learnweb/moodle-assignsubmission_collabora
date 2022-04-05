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
 * The collabora callback lib file - created to make it testable.
 *
 * @package   assignsubmission_collabora
 * @copyright 2019 Synergy Learning
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

/**
 * The collabora callback lib file - created to make it testable.
 *
 * @package   assignsubmission_collabora
 * @copyright 2019 Synergy Learning
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class callbacklib {

    /**
     * Handle the API request from the Collabora CORE
     *
     * @param string $relativepath - the called path - wopi/files.
     * @param string $accesstoken - the user's access token we generated with our original call.
     * @param string $postdata - the binary contents of the file to be saved.
     * @throws \moodle_exception
     * @return string | void - json, binary contents of file to edit or nothing after file save.
     */
    public static function handle_request($relativepath, $accesstoken, $postdata) {
        global $CFG;

        list($shastr, $userid) = explode('_', $accesstoken);

        // Nice Regex :) Stolen of course!
        $matches = array();
        if (!preg_match('|/wopi/files/([^/]*)(/contents)?|', $relativepath, $matches)) {
            throw new \moodle_exception('invalidrequest', 'assignsubmission_collabora');
        }
        if (!$fileid = $matches[1]) {
            throw new \moodle_exception('invalidrequest', 'assignsubmission_collabora');
        }
        $hascontents = isset($matches[2]);

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

        // Check if the file is a group file and if the user is a member of the relevant group.
        $canedit = false;
        if (!empty($userpermission)) {       // Paranoid Check.
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
            if ($userpermission >= 600) {
                $canedit = true;
            }
        }

        if ($hascontents && $postdata) {
            // This is a PUT request and so we save an updated file.
            if (!$canedit) {
                throw new \moodle_exception('docreadonly', 'assignsubmission_collabora');
            }

            // Now we get to save the file - STOLEN CODE.
            $filerecord = (object)[
                'contextid' => $file->get_contextid(),
                'component' => $file->get_component(),
                'filearea' => $file->get_filearea(),
                'itemid' => $file->get_itemid(),
                'filepath' => $file->get_filepath(),
                'filename' => $file->get_filename(),
                'timecreated' => $file->get_timecreated(),
                // Time modified will be changed - and so will Version number.
            ];
            $file->delete(); // Remove the old file.
            // Store the new file - This will change the ID and automtically unlock it.
            $fs->create_file_from_string($filerecord, $postdata);

        } else if ($hascontents && !$postdata) {
            // This is a GET request - send back the file.
            if (defined('PHPUNIT_TEST') && PHPUNIT_TEST) {       // Catch a PHP Unit Test.
                $file->readfile();      // Dumps to screen.
            } else {
                send_stored_file($file);
            }
        } else if (!$hascontents && !$postdata) {
            // This is a checkfile request.
            if (!$filename = clean_filename($file->get_filename())) {   // Weird issue with filenames.
                $filename = preg_replace("/\W/", '', $file->get_filename());
            }
            $tz = date_default_timezone_get();
            date_default_timezone_set('UTC');
            $ret = (object) array(
                'BaseFileName' => $filename,
                'OwnerId' => $CFG->siteidentifier,      // Always the same.
                'Size' => (int) $file->get_filesize(),
                'UserId' => $accesstoken,
                'UserFriendlyName' => fullname(core_user::get_user($userid)),
                'UserCanWrite' => $canedit,
                'ReadOnly' => !$canedit,
                'UserCanRename' => false,
                'UserCanNotWriteRelative' => true,
                'LastModifiedTime' => date('c', $file->get_timemodified()),
                'Version' => (string) $file->get_timemodified(),
            );
            date_default_timezone_set($tz);
            if (defined('PHPUNIT_TEST') && PHPUNIT_TEST) {       // Catch a PHP Unit Test.
                return json_encode($ret);
            } else {
                die(json_encode($ret));     // Send back JSON Response.
            }
        } else {
            throw new \moodle_exception('invalidrequesttype', 'assignsubmission_collabora');
        }
    }

}
