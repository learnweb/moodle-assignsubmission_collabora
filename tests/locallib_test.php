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
 * Tests for mod/assign/submission/collabora/locallib.php
 *
 * @package   assignsubmission_collabora
 * @copyright 2019 Benjamin Ellis, Synergy Learning
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace assignsubmission_collabora;
use assignsubmission_collabora\test_setup_trait;

defined('MOODLE_INTERNAL') || die();

global $CFG;
require_once($CFG->dirroot . '/mod/assign/tests/generator.php');

/**
 * Unit tests for mod/assign/submission/file/locallib.php
 *
 * @copyright 2019 Benjamin Ellis, Synergy Learning
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class locallib_test extends \advanced_testcase {

    // Use the generator helper.
    use \mod_assign_test_generator;
    use \assignsubmission_collabora\test_setup_trait;

    /** @var $course - The course object */
    protected $course;

    /** @var $assign - The assign object. */
    protected $assign;

    /** @var $plugins - Array of the plugins created in the current course - pre function */
    protected $plugins = array();

    /**
     * Helper function to setup the environment to test our plugin.
     *
     * @return stdClass $plugin assignsubmission_collabora instance.
     */
    protected function get_submissionplugin_instance() {
        if (empty($this->assign)) {     // The new plugin.
            $this->course = $this->getDataGenerator()->create_course();
            $this->assign = $this->create_instance($this->course);
        }
        $plugin = $this->assign->get_submission_plugin_by_type('collabora');
        $this->plugins[] = $plugin;
        return $plugin;
    }

    /**
     * Helper Function to actually save a submission file - usually done by get_form_elements() function.
     *
     * @param stdClass $plugin
     * @param stdClass $student
     * @param stdClass $submission
     * @param string $filename - name of new file.
     */
    protected function create_submission_file($plugin, $student, $submission, $filename = 'myassignmnent.docx') {

        $this->setUser($student->id);       // Won't hurt to make sure.

        if ($itemid = $submission->groupid) { // Group Submission.
            $filearea = \mod_collabora\api\collabora_fs::FILEAREA_GROUP;
        } else {
            $filearea = $plugin::FILEAREA_USER;
            $itemid = $student->id;
        }

        $fs = get_file_storage();
        $dummy = (object) array(
            'contextid' => $this->assign->get_context()->id,
            'component' => 'assignsubmission_collabora',
            'filearea' => $filearea,
            'itemid' => $itemid,
            'filepath' => '/',
            'filename' => $filename
        );

        $content = "\nLorem ipsum dolor sit amet, consectetur adipiscing elit,
                        sed do eiusmod tempor incididunt ut labore et dolore magna aliqua.
                        Ut enim ad minim veniam, quis nostrud exercitation ullamco
                        laboris nisi ut aliquip ex ea commodo consequat.
                        Duis aute irure dolor in reprehenderit in voluptate velit esse cillum dolore eu fugiat nulla pariatur.
                        Excepteur sint occaecat
                        cupidatat non proident, sunt in culpa qui officia deserunt mollit anim id est laborum.\n";

        $file = $fs->create_file_from_string($dummy, 'Content of ' . $dummy->filename . $content);
        return $file;
    }

    /**
     * Test get_name().
     */
    public function test_get_name() {
        $this->resetAfterTest();
        // Get the relevant plugin.
        $plugin = $this->get_submissionplugin_instance();
        $this->assertEquals(get_string('pluginname', 'assignsubmission_collabora'), $plugin->get_name());
    }

    /**
     * Not required adds fields to the settings form
     */
    public function test_get_settings() {
        $this->assertTrue(true);
    }

    /**
     * Test the save_settings() function.
     */
    public function test_save_settings() {
        $this->resetAfterTest();
        $plugin = $this->get_submissionplugin_instance();

        // We need a user - a teacher.
        $teacher = $this->getDataGenerator()->create_and_enrol($this->course, 'teacher');
        $this->setUser($teacher->id);

        // Recreate the form data.
        $data = new \stdClass();

        // The initial format: collabora::FORMAT_TEXT.
        $data->assignsubmission_collabora_format = \mod_collabora\api\collabora_fs::FORMAT_TEXT;
        $data->assignsubmission_collabora_filename = 'test_text_upload';
        // Width never empty - required for all formats.
        $data->assignsubmission_collabora_width = 0;
        // Height never empty - required for all formats.
        $data->assignsubmission_collabora_height = 0;
        $data->assignsubmission_collabora_initialtext = "Lorem ipsum dolor sit amet, consectetur adipiscing elit,
sed do eiusmod tempor incididunt ut labore et dolore magna aliqua.
Ut enim ad minim veniam, quis nostrud exercitation ullamco laboris nisi ut aliquip ex ea commodo consequat.
Duis aute irure dolor in reprehenderit in voluptate velit esse cillum dolore eu fugiat nulla pariatur. Excepteur sint occaecat
cupidatat non proident, sunt in culpa qui officia deserunt mollit anim id est laborum.";
        $this->assertTrue($plugin->save_settings($data));

        // The example blank file: \mod_collabora\api\collabora_fs::FORMAT_WORDPROCESSOR.
        $plugin = $this->get_submissionplugin_instance();
        unset($data->assignsubmission_collabora_initialtext);
        $data->assignsubmission_collabora_format = \mod_collabora\api\collabora_fs::FORMAT_WORDPROCESSOR;
        $this->assertTrue($plugin->save_settings($data));

        // This will be the initial format: collabora::FORMAT_UPLOAD.
        $plugin = $this->get_submissionplugin_instance();
        $uploadfile = __DIR__ . '/fixtures/test-upload.odt';
        unset($data->assignsubmission_collabora_filename);

        // Create a draft file - our teacher user is uploading :).
        $fs = get_file_storage();
        $itemid = file_get_unused_draft_itemid();
        $filerecord = array(
            'contextid' => \context_user::instance($teacher->id)->id,
            'component' => 'user',
            'filearea' => 'draft',
            'filepath' => '/',
            'filename' => basename($uploadfile),
            'itemid' => $itemid,
        );

        $fs->create_file_from_pathname($filerecord, $uploadfile); // File in draft area.
        $data->assignsubmission_collabora_initialfile_filemanager = $itemid;
        $this->assertTrue($plugin->save_settings($data));
    }

    /**
     * Test view_summary() function.
     */
    public function test_view_summary() {
        $this->resetAfterTest();
        $plugin = $this->get_submissionplugin_instance();
        // We need a submission to test this.

        $student = $this->getDataGenerator()->create_and_enrol($this->course, 'student');
        $this->setUser($student->id);

        $newassignment = true;
        $submission = $this->assign->get_user_submission($student->id, $newassignment);
        $nosubmissiontxt = get_string('nosubmission', 'assignsubmission_collabora');
        $this->assertEquals($nosubmissiontxt, $plugin->view_summary($submission, $newassignment));

        // Now we we resubmit our submission.
        $newassignment = false;
        $submission = $this->assign->get_user_submission($student->id, $newassignment);
        // We need to save a submission file to change the report.
        $this->create_submission_file($plugin, $student, $submission);
        $submissiontxt = $submission->status;
        $this->assertEquals($submissiontxt, strtolower($plugin->view_summary($submission, $newassignment)));
    }

    /**
     * This function creates form elements and displays collabora editor in frame.
     * Additionally it creates - if required - the new submission file which complicates ...
     * ... collabara submissions a bit of an issue.
     */
    public function test_get_form_elements() {
        $this->assertTrue(true);
    }

    /**
     * Test save() function - This function is tested several times by the events test scripts ...
     * ... as the function only creates events - all the work is done in get_form_elements().
     */
    public function test_save() {
        $this->resetAfterTest();
        $plugin = $this->get_submissionplugin_instance();

        $student = $this->getDataGenerator()->create_and_enrol($this->course, 'student');
        $this->setUser($student->id);

        $newassignment = true;
        $submission = $this->assign->get_user_submission($student->id, $newassignment);
        $file = $this->create_submission_file($plugin, $student, $submission);

        $data = new \stdClass();
        $data->submpathnamehash = $file->get_pathnamehash();
        $data->submfilename = $file->get_filename();
        $data->submfileid = $file->get_id();
        $data->subnewsubmssn = 1;
        $this->assertTrue($plugin->save($submission, $data));
    }

    /**
     * Test get_files() function.
     */
    public function test_get_files() {
        $this->resetAfterTest();
        $plugin = $this->get_submissionplugin_instance();

        $student = $this->getDataGenerator()->create_and_enrol($this->course, 'student');
        $this->setUser($student->id);

        $newassignment = true;
        $submission = $this->assign->get_user_submission($student->id, $newassignment);
        $file = $this->create_submission_file($plugin, $student, $submission);

        // Make the submission require no file paths.
        $submission->exportfullpath = false;

        $allfiles = $plugin->get_files($submission, $student);
        $this->assertArrayHasKey($file->get_filename(), $allfiles);
        $filepathhash = $allfiles[$file->get_filename()]->get_pathnamehash();
        $this->assertEquals($file->get_pathnamehash(), $filepathhash);
    }

    /**
     * Test view() function - This returns a HTML string - How do we assert.
     */
    public function test_view() {
        global $CFG;

        $this->resetAfterTest();
        $plugin = $this->get_submissionplugin_instance();

        $student = $this->getDataGenerator()->create_and_enrol($this->course, '');
        $this->setUser($student->id);

        $submission = $this->assign->get_user_submission($student->id, true);
        $this->create_submission_file($plugin, $student, $submission);

        // For this to work we need to set a Collabora URL.
        // Put the discovery.xml into the cache to make the test independend to an existing collabora server.
        $baseurl = 'https://example.org/';
        set_config('url', $baseurl, 'mod_collabora');
        $cache = \cache::make('mod_collabora', 'discovery');
        $xml = file_get_contents($CFG->dirroot.'/mod/assign/submission/collabora/tests/fixtures/discovery.xml');
        $cache->set($baseurl, $xml);

        $this->assertNotEmpty($plugin->view($submission));

    }

    /**
     * Test can_upgrade() function.  Passing silly values as we always expect to return false.
     */
    public function test_can_upgrade() {
        $this->resetAfterTest();
        $plugin = $this->get_submissionplugin_instance();
        $this->assertFalse($plugin->can_upgrade('notused', 'notused'));
    }

    /**
     * Test format_for_log() function.
     */
    public function test_format_for_log() {
        $this->resetAfterTest();
        $plugin = $this->get_submissionplugin_instance();

        $student = $this->getDataGenerator()->create_and_enrol($this->course, '');
        $this->setUser($student->id);

        $submission = $this->assign->get_user_submission($student->id, true);

        // Assert we have a response - Do we need any more?
        $this->assertNotEmpty($plugin->format_for_log($submission));

        // Double check response.
        $response = get_string('logmessage', 'assignsubmission_collabora');
        $this->assertEquals($response, $plugin->format_for_log($submission));
    }

    /**
     * Test get file areas() - hmmmm.
     */
    public function test_get_file_areas() {
        $this->resetAfterTest();
        $plugin = $this->get_submissionplugin_instance();

        $fileareas = $plugin->get_file_areas();
        $this->assertCount(3, $fileareas);
        $this->assertArrayHasKey(\mod_collabora\api\collabora_fs::FILEAREA_GROUP, $fileareas);
        $this->assertArrayHasKey(\mod_collabora\api\collabora_fs::FILEAREA_INITIAL, $fileareas);
        $this->assertArrayHasKey($plugin::FILEAREA_USER, $fileareas);
    }

    /**
     * Function does nothing currently.
     */
    public function test_copy_submission() {
        $this->assertTrue(true);
    }

    /**
     * Delete the submission related data - tricky.
     */
    public function test_delete_instance() {
        $this->resetAfterTest();
        $plugin = $this->get_submissionplugin_instance();

        // We need a user - a teacher.
        $teacher = $this->getDataGenerator()->create_and_enrol($this->course, 'teacher');
        $this->setUser($teacher->id);

        // We create some settings for the file.
        $data = new \stdClass();

        // This will be the initial file: collabora::FORMAT_SPREADSHEET.
        $data->assignsubmission_collabora_format = \mod_collabora\api\collabora_fs::FORMAT_SPREADSHEET;
        $data->assignsubmission_collabora_filename = 'test_delete_instance';
        // Width never empty - required for all formats.
        $data->assignsubmission_collabora_width = 0;
        // Height never empty - required for all formats.
        $data->assignsubmission_collabora_height = 0;
        // We save the initial file and settings.
        $this->assertTrue($plugin->save_settings($data), 'Initial File Save - before delete');

        // We already have the settings in data.
        $settings = $plugin->get_config();

        // We need to get the initial file details.
        $fs = get_file_storage();
        $files = $fs->get_area_files(
            $this->assign->get_context()->id,
            'assignsubmission_collabora',
            \mod_collabora\api\collabora_fs::FILEAREA_INITIAL,
            0, null, false, 0, 0, 1);
        $initialfile = reset($files);

        // Now we call the delete_instance().
        $this->assertTrue($plugin->delete_instance($data), 'Deletion of the instance');

        // We check that the initial file was deleted - function shoudl return false.
        $this->assertFalse($fs->get_file_by_hash($initialfile->get_pathnamehash()), 'Initial File delete.');

        // We check if we get any settings if we ask for settings.
        $newsettings = $plugin->get_config();
        $this->assertFalse(($settings == $newsettings));  // Are the arrays different.
    }

    /**
     * Test is_configurable() function.
     *
     * The plugin is not configurable and so always returns false.
     *
     */
    public function test_is_configurable() {
        $this->resetAfterTest();
        $plugin = $this->get_submissionplugin_instance();
        $this->assertTrue($plugin->is_configurable());
    }

    /**
     * Test is_empty() function.
     */
    public function test_is_empty() {
        $this->resetAfterTest();
        $plugin = $this->get_submissionplugin_instance();
        // We need a submission to test this.

        $student = $this->getDataGenerator()->create_and_enrol($this->course, 'student');
        $this->setUser($student->id);

        $newassignment = true;
        $submission = $this->assign->get_user_submission($student->id, $newassignment);
        // New assignment - should be empty.
        $this->assertTrue($plugin->is_empty($submission));

        // Now we we resubmit our submission.
        $submission = $this->assign->get_user_submission($student->id, false);
        $this->create_submission_file($plugin, $student, $submission);

        // We need to save a submission file to change the report.
        $this->assertFalse($plugin->is_empty($submission));
    }

    /**
     * Test submission_is_empty - always returns false.
     *
     */
    public function test_submission_is_empty() {
        $this->resetAfterTest();
        $plugin = $this->get_submissionplugin_instance();
        $data = new \stdClass();
        $this->assertFalse($plugin->submission_is_empty($data));
    }

    /**
     * TODO Test get_view_url() function.  Difficult this as this relies on a lot of other ...
     * ... functionality so we will test a URL in the right format is returned.
     */
    public function test_get_view_url() {
        global $CFG;
        // Get the viewurl.
        // The returned elements are: $viewurl, $file, $fs, $assign, $plugin, $student.
        // We only need the $viewurl. All the other elements returned are not needed.
        list($viewurl) = $this->setup_and_basic_tests_for_view_url();
        $partofwoipsrc = 'WOPISrc='.urlencode($CFG->wwwroot);
        $this->assertStringContainsString($partofwoipsrc, $viewurl);

        // Extract our WOPI parameter.
        $qry = parse_url($viewurl, PHP_URL_QUERY);
        list($wopisrc, $callpath) = explode('=', $qry);
        $callpath = urldecode($callpath);
        $this->assertStringContainsString('wopi/file', $callpath);

        $params = parse_url($callpath);
        $this->assertNotEmpty($params['path']);

    }

}
