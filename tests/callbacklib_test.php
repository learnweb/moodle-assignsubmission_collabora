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
 * Contains the callback tests for the plugin.
 *
 * @package   assignsubmission_collabora
 * @copyright 2019 Benjamin Ellis, Synergy Learning
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

use mod_collabora\collabora;

defined('MOODLE_INTERNAL') || die();

global $CFG;
require_once($CFG->dirroot . '/mod/assign/tests/generator.php');
require_once($CFG->dirroot . '/mod/assign/submission/collabora/classes/callbacklib.php');

/**
 * Contains the callback tests for the plugin.
 *
 * @package   assignsubmission_collabora
 * @copyright 2019 Benjamin Ellis, Synergy Learning
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class assignsubmission_collabora_callback_testcase extends advanced_testcase {

    // Use the generator helper.
    use mod_assign_test_generator;

    public function test_handle_request() {
        global $CFG;
        $this->resetAfterTest();

        $course = $this->getDataGenerator()->create_course();
        $assign = $this->create_instance($course);
        $plugin = $assign->get_submission_plugin_by_type('collabora');

        // Create a submission instance.
        // We need a user - a teacher.
        $teacher = $this->getDataGenerator()->create_and_enrol($course, 'teacher');
        $this->setUser($teacher->id);

        $data = new stdClass();
        // collabora::FORMAT_WORDPROCESSOR - example blank file.
        $data->assignsubmission_collabora_format = collabora::FORMAT_WORDPROCESSOR;
        $data->assignsubmission_collabora_filename = 'test_handle_request';
        // Width never empty - required for all formats.
        $data->assignsubmission_collabora_width = 0;
        // Height never empty - required for all formats.
        $data->assignsubmission_collabora_height = 0;
        $this->assertTrue($plugin->save_settings($data));

        // Get Our initial file created above - which is an empty ods file.
        $fs = get_file_storage();
        $files = $fs->get_area_files(
            $assign->get_context()->id,
            'assignsubmission_collabora',
            collabora::FILEAREA_INITIAL,
            0, '', false, 0, 0, 1);
        $initialfile = reset($files);
        $this->assertNotEmpty($initialfile, 'No initial file created');

        // Get a student to make a submission
        $student = $this->getDataGenerator()->create_and_enrol($course, 'student');
        $this->setUser($student->id);

        $newassignment = true;
        $submission = $assign->get_user_submission($student->id, $newassignment);
        // we have to create the submission file - as per get_form_elements();
        $submissionfilerec = (object) [
            'contextid' => $initialfile->get_contextid(),
            'component' => $initialfile->get_component(),
            'filearea' => $plugin::FILEAREA_USER,
            'itemid' => $student->id,
            'filepath' => '/',
            'filename' => $initialfile->get_filename()
        ];
        // A copy of the empty ods file.
        $file = $fs->create_file_from_storedfile($submissionfilerec, $initialfile);
        $this->assertNotEmpty($file, 'No user submission file created');

        $data = new stdClass();
        $data->submpathnamehash = $file->get_pathnamehash();
        $data->submfilename = $file->get_filename();
        $data->subnewsubmssn = $newassignment;
        $this->assertTrue($plugin->save($submission, $data));

        /* Proper Tests Start here */
        // For this to work we need to set a Collabora URL
        set_config('url', 'http://127.0.0.1:9980', 'mod_collabora');

        // Get the URL we need to call the editing.
        $viewurl = $plugin->get_view_url($submission, $file, $student->id);

        // Extract our WOPI parameter
        $qry = html_entity_decode(parse_url($viewurl, PHP_URL_QUERY));
        $params = array();
        parse_str($qry, $params);

        $relativepath = urldecode(parse_url($params['WOPISrc'], PHP_URL_PATH));
        $accesstoken = $params['access_token'];
        $postdata = null;

        /* Create the request - $relativepath, $accesstoken, $postdata. */
        // Get File Info JSON.
        $fileinfo = json_decode(callbacklib::handle_request($relativepath, $accesstoken, $postdata));

        // Assert a few things about our $fileinfo.
        $this->assertEquals($file->get_filename(), $fileinfo->BaseFileName);
        $this->assertEquals($accesstoken, $fileinfo->UserId);
        $this->assertEquals($file->get_filesize(), $fileinfo->Size);

        // assert Get File 2nd - need to add contents onto the relative path
        $relativepath .= '/contents';
        ob_start();
        callbacklib::handle_request($relativepath, $accesstoken, $postdata);
        $contentsize = ob_get_length();
        $filecontentshash = sha1(ob_get_contents());    // File contents hashed.
        ob_end_clean();

        $this->assertEquals($file->get_filesize(), $contentsize);   // Same Size.
        // Compare the contents.
        $this->assertEquals(($fch = $file->get_contenthash()), $filecontentshash, "'$fch' NOT '$filecontentshash'");

        // Assert PUT File last - Make out fixture file be the edited file.
        $uploadfile = __DIR__ . '/fixtures/test-upload.odt';
        $postdata = file_get_contents($uploadfile);

        // Update our file record - note the filerecord is changed.
        callbacklib::handle_request($relativepath, $accesstoken, $postdata);
        sleep(2);   // give us some time to complete.

        // Now lets get the new file.
        // $fs = get_file_storage();
        $files = $fs->get_area_files(
            $assign->get_context()->id,
            'assignsubmission_collabora',
            $plugin::FILEAREA_USER,
            $student->id,
            '', false, 0, 0, 1);
        $newfile = reset($files);

        $this->assertEquals(strlen($postdata), $newfile->get_filesize());       // Size.
        $this->assertEquals(sha1($postdata), $newfile->get_contenthash());      // Contents.
    }
}
