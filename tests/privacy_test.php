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
 * Unit tests for assignsubmission_collabora.
 *
 * @package    assignsubmission_collabora
 * @copyright 2019 Benjamin Ellis, Synergy Learning
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

use mod_collabora\collabora;

defined('MOODLE_INTERNAL') || die();

global $CFG;
require_once($CFG->dirroot . '/mod/assign/tests/privacy_test.php');

/**
 * Unit tests for mod/assign/submission/file/classes/collabora/
 *
 * @copyright 2019 Benjamin Ellis, Synergy Learning
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class assignsubmission_collabora_privacy_testcase extends \mod_assign\tests\mod_assign_privacy_testcase {

    /**
     * Convenience function for creating submission data.
     *
     * @param  object   $assign         assign object
     * @param  stdClass $student        user object
     * @param  string   $filename       filename for the file submission
     * @return array   Submission plugin object and the submission object.
     */
    protected function create_file_submission($assign, $student, $filename) {
        global $CFG;

        $plugin = $assign->get_submission_plugin_by_type('collabora');

        $this->setUser($student->id);

        // Create a file submission.
        $submission = $assign->get_user_submission($student->id, true);

        $fs = get_file_storage();
        $submissionfile = (object) array(
            'contextid' => $assign->get_context()->id,
            'component' => 'assignsubmission_collabora',
            'filearea' => $plugin::FILEAREA_USER,
            'itemid' => $student->id,
            'filepath' => '/',
            'filename' => $filename
        );
        $sourcefile = __DIR__ . '/fixtures/test-upload.odt';
        $file = $fs->create_file_from_pathname($submissionfile, $sourcefile);

        $data = new \stdClass();
        $data->submpathnamehash = $file->get_pathnamehash();
        $data->submfilename = $file->get_filename();
        $data->submfileid = $file->get_id();
        $data->subnewsubmssn = 1;
        $plugin->save($submission, $data);

        return [$plugin, $submission];
    }

    /**
     * Test to make sure that get_metadata returns something.
     */
    public function test_get_metadata() {
        $collection = new \core_privacy\local\metadata\collection('assignsubmission_collabora');
        $collection = \assignsubmission_collabora\privacy\provider::get_metadata($collection);
        $this->assertNotEmpty($collection);
    }

    /**
     * Test that submission files are exported for a user.
     */
    public function test_export_submission_user_data() {
        $this->resetAfterTest();
        // Create course, assignment, submission, and then a submission comment.
        $course = $this->getDataGenerator()->create_course();
        // Student.
        $user1 = $this->getDataGenerator()->create_user();
        // Teacher.
        $user2 = $this->getDataGenerator()->create_user();
        $this->getDataGenerator()->enrol_user($user1->id, $course->id, 'student');
        $this->getDataGenerator()->enrol_user($user2->id, $course->id, 'editingteacher');

        $assign = $this->create_instance(['course' => $course]);
        $context = $assign->get_context();

        $studentfilename = 'user1file.odt';
        list($plugin, $submission) = $this->create_file_submission($assign, $user1, $studentfilename);

        $writer = \core_privacy\local\request\writer::with_context($context);
        $this->assertFalse($writer->has_any_data());

        // The student should have a file submission.
        $exportdata = new \mod_assign\privacy\assign_plugin_request_data($context, $assign, $submission, ['Attempt 1']);
        \assignsubmission_collabora\privacy\provider::export_submission_user_data($exportdata);
        // print_object($writer);
        $storedfile = $writer->get_files(['Attempt 1'])[$studentfilename];
        $this->assertInstanceOf('stored_file', $storedfile);
        $this->assertEquals($studentfilename, $storedfile->get_filename());
    }

    /**
     * Setupmethod for test_delete_submission_for_context() and test_delete_submission_for_userid().
     * @return array ($assign, $plugin, $submission, $plugin2, $submission2, $user1).
     */
    public function setup_privacy_tests() {
        $this->resetAfterTest();
        // Create course, assignment, submission, and then a submission comment.
        $course = $this->getDataGenerator()->create_course();
        // Student.
        $user1 = $this->getDataGenerator()->create_user();
        $user2 = $this->getDataGenerator()->create_user();

        $this->getDataGenerator()->enrol_user($user1->id, $course->id, 'student');
        $this->getDataGenerator()->enrol_user($user2->id, $course->id, 'student');

        $assign = $this->create_instance(['course' => $course]);

        $studentfilename = 'user1file.ods';
        list($plugin, $submission) = $this->create_file_submission($assign, $user1, $studentfilename);
        $student2filename = 'user2file.ods';
        list($plugin2, $submission2) = $this->create_file_submission($assign, $user2, $studentfilename);
        return array($assign, $plugin, $submission, $plugin2, $submission2, $user1);
    }

    /**
     * Test that all submission files are deleted for this context.
     */
    public function test_delete_submission_for_context() {
        list($assign, $plugin, $submission, $plugin2, $submission2) = $this->setup_privacy_tests();
        // Only need the context and assign object in this plugin for this operation.
        $requestdata = new \mod_assign\privacy\assign_plugin_request_data($assign->get_context(), $assign);
        \assignsubmission_collabora\privacy\provider::delete_submission_for_context($requestdata);
        // This checks that there are no files in this submission.
        $this->assertTrue($plugin->is_empty($submission));
        $this->assertTrue($plugin2->is_empty($submission2));
    }

    /**
     * TODO Test that the comments for a user are deleted.
     */
    public function test_delete_submission_for_userid() {
        list($assign, $plugin, $submission, $plugin2, $submission2, $user1) = $this->setup_privacy_tests();
        // Only need the context and assign object in this plugin for this operation.
        $requestdata = new \mod_assign\privacy\assign_plugin_request_data($assign->get_context(), $assign, $submission, [], $user1);
        \assignsubmission_collabora\privacy\provider::delete_submission_for_userid($requestdata);

        // This checks that there are no files in this submission.
        $this->assertTrue($plugin->is_empty($submission));
        // There should be files here.
        $this->assertFalse($plugin2->is_empty($submission2));
    }

    /**
     * Test deletion of bulk submissions for a context.
     */
    public function test_delete_submissions() {
        global $DB;

        $this->resetAfterTest();
        // Create course, assignment, submission, and then a submission comment.
        $course = $this->getDataGenerator()->create_course();
        // Student.
        $user1 = $this->getDataGenerator()->create_user();
        $user2 = $this->getDataGenerator()->create_user();
        $user3 = $this->getDataGenerator()->create_user();
        $user4 = $this->getDataGenerator()->create_user();

        $this->getDataGenerator()->enrol_user($user1->id, $course->id, 'student');
        $this->getDataGenerator()->enrol_user($user2->id, $course->id, 'student');
        $this->getDataGenerator()->enrol_user($user3->id, $course->id, 'student');
        $this->getDataGenerator()->enrol_user($user4->id, $course->id, 'student');

        $assign1 = $this->create_instance(['course' => $course]);
        $assign2 = $this->create_instance(['course' => $course]);

        $context1 = $assign1->get_context();        // This is the context we are going to delete.
        $context2 = $assign2->get_context();

        // Context 1 - these should be deleted.
        $student1filename = 'user1file.ods';
        list($plugin1, $submission1) = $this->create_file_submission($assign1, $user1, $student1filename);
        $student2filename = 'user2file.ods';
        list($plugin2, $submission2) = $this->create_file_submission($assign1, $user2, $student2filename);
        $student3filename = 'user3file.ods';
        list($plugin3, $submission3) = $this->create_file_submission($assign1, $user3, $student3filename);

        // Context 2 - these should still remain after the delete.
        $student4filename = 'user4file.ods';
        list($plugin4, $submission4) = $this->create_file_submission($assign2, $user4, $student4filename);
        $student5filename = 'user5file.ods';
        list($plugin5, $submission5) = $this->create_file_submission($assign2, $user3, $student5filename);

        $userids = [
            $user1->id,
            $user3->id
        ];

        // Assert Submission File count - 3 files and 3 folders. Context 1.
        $data = $DB->get_records('files', ['contextid' => $context1->id, 'component' => 'assignsubmission_collabora']);
        $this->assertCount(6, $data);

        // Now do the deletions.
        $deletedata = new \mod_assign\privacy\assign_plugin_request_data($context1, $assign1);
        $deletedata->set_userids($userids);
        $deletedata->populate_submissions_and_grades();
        \assignsubmission_collabora\privacy\provider::delete_submissions($deletedata);

        // User 2's file and folder should still be there.
        $data = $DB->get_records('files', ['contextid' => $context1->id, 'component' => 'assignsubmission_collabora']);
        $this->assertCount(2, $data);

        // Context 2 files should not have been touched.
        $data = $DB->get_records('files', ['contextid' => $context2->id, 'component' => 'assignsubmission_collabora']);
        $this->assertCount(4, $data);
    }
}
