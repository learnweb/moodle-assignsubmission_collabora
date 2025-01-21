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
 * @copyright  2018 Adrian Greeve <adrian@moodle.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace assignsubmission_collabora\privacy;

use mod_collabora\util as collabora_util;

defined('MOODLE_INTERNAL') || die;

global $CFG;
require_once($CFG->dirroot . '/mod/assign/tests/privacy/provider_test.php');
if (class_exists('\\mod_assign\\tests\\provider_testcase')) {
    class_alias(\mod_assign\tests\provider_testcase::class, 'assign_provider_test');
} else {
    class_alias(\mod_assign\privacy\provider_test::class, 'assign_provider_test');
}

/**
 * Unit tests for mod/assign/submission/file/classes/privacy/.
 *
 * @copyright  2018 Adrian Greeve <adrian@moodle.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class provider_test extends \assign_provider_test {
    /** A fix structure to configure a assignment instance. */
    public const COLLABORACFG = [
        'assignsubmission_collabora_enabled'  => 1,
        'assignsubmission_collabora_format'   => collabora_util::FORMAT_WORDPROCESSOR,
        'assignsubmission_collabora_height'   => 0,
        'assignsubmission_collabora_filename' => 'initialfile.docx',
    ];

    /**
     * Convenience function for creating feedback data.
     *
     * @param  object   $assign   assign object
     * @param  stdClass $student  user object
     * @param  string   $filename filename for the collabora submission
     * @return array    submission plugin object and the submission object
     */
    protected function create_collabora_submission($assign, $student, $filename) {
        global $CFG;
        // Create a file submission with the test pdf.
        $submission = $assign->get_user_submission($student->id, true);

        $this->setUser($student->id);

        $fs             = get_file_storage();
        $filesubmission = (object) [
            'contextid' => $assign->get_context()->id,
            'component' => 'assignsubmission_collabora',
            'filearea'  => \assignsubmission_collabora\api\collabora_fs::FILEAREA_SUBMIT,
            'itemid'    => $submission->id,
            'filepath'  => '/',
            'filename'  => $filename,
        ];
        // The submission file must be unique or at least have another content than the initialfile.
        $filecontent = random_string(32);
        $fi          = $fs->create_file_from_string($filesubmission, $filecontent);

        $data                   = new \stdClass();
        $data->submpathnamehash = $fi->get_pathnamehash();
        $data->submfilename     = $fi->get_filename();
        $plugin                 = $assign->get_submission_plugin_by_type('collabora');
        $plugin->save($submission, $data);

        return [$plugin, $submission];
    }

    /**
     * Quick test to make sure that get_metadata returns something.
     * @covers \assignsubmission_collabora\privacy\provider::get_metadata
     * @return void
     */
    public function test_get_metadata(): void {
        $collection = new \core_privacy\local\metadata\collection('assignsubmission_collabora');
        $collection = \assignsubmission_collabora\privacy\provider::get_metadata($collection);
        $this->assertNotEmpty($collection);
    }

    /**
     * Test that submission files are exported for a user.
     * @covers \assignsubmission_collabora\privacy\provider::export_submission_user_data
     * @return void
     */
    public function test_export_submission_user_data(): void {
        $this->resetAfterTest();
        // Create course, assignment, submission, and then a feedback comment.
        $course = $this->getDataGenerator()->create_course();
        // Student.
        $user1 = $this->getDataGenerator()->create_user();
        // Teacher.
        $user2 = $this->getDataGenerator()->create_user();
        $this->getDataGenerator()->enrol_user($user1->id, $course->id, 'student');
        $this->getDataGenerator()->enrol_user($user2->id, $course->id, 'editingteacher');

        $options = array_merge(['course' => $course], self::COLLABORACFG);
        /** @var \assign $assign */
        $assign = $this->create_instance($options);

        $context = $assign->get_context();

        $studentfilename           = 'user1file.docx';
        list($plugin, $submission) = $this->create_collabora_submission($assign, $user1, $studentfilename);

        /** @var \core_privacy\tests\request\content_writer $writer */
        $writer = \core_privacy\local\request\writer::with_context($context);
        $this->assertFalse($writer->has_any_data());

        // The student should have a file submission.
        $exportdata = new \mod_assign\privacy\assign_plugin_request_data($context, $assign, $submission, ['Attempt 1']);
        \assignsubmission_collabora\privacy\provider::export_submission_user_data($exportdata);

        $storedfiles = $writer->get_files(['Attempt 1']);
        $storedfile  = array_pop($storedfiles);
        $this->assertInstanceOf('stored_file', $storedfile);
        $this->assertEquals($studentfilename, $storedfile->get_filename());
    }

    /**
     * Test that all submission files are deleted for this context.
     * @covers \assignsubmission_collabora\privacy\provider::delete_submission_for_context
     * @return void
     */
    public function test_delete_submission_for_context(): void {
        $this->resetAfterTest();
        // Create course, assignment, submission, and then a feedback comment.
        $course = $this->getDataGenerator()->create_course();
        // Student.
        $user1 = $this->getDataGenerator()->create_user();
        $user2 = $this->getDataGenerator()->create_user();

        $this->getDataGenerator()->enrol_user($user1->id, $course->id, 'student');
        $this->getDataGenerator()->enrol_user($user2->id, $course->id, 'student');

        $options = array_merge(['course' => $course], self::COLLABORACFG);
        /** @var \assign $assign */
        $assign = $this->create_instance($options);

        $context = $assign->get_context();

        $studentfilename             = 'user1file.pdf';
        list($plugin, $submission)   = $this->create_collabora_submission($assign, $user1, $studentfilename);
        $student2filename            = 'user2file.pdf';
        list($plugin2, $submission2) = $this->create_collabora_submission($assign, $user2, $student2filename);

        // Only need the context and assign object in this plugin for this operation.
        $requestdata = new \mod_assign\privacy\assign_plugin_request_data($context, $assign);
        \assignsubmission_collabora\privacy\provider::delete_submission_for_context($requestdata);
        // This checks that there are no files in this submission.
        $this->assertTrue($plugin->is_empty($submission));
        $this->assertTrue($plugin2->is_empty($submission2));
    }

    /**
     * Test that the comments for a user are deleted.
     * @covers \assignsubmission_collabora\privacy\provider::delete_submission_for_userid
     * @return void
     */
    public function test_delete_submission_for_userid(): void {
        global $DB;
        $this->resetAfterTest();
        // Create course, assignment, submission, and then a feedback comment.
        $course = $this->getDataGenerator()->create_course();
        // Student.
        $user1 = $this->getDataGenerator()->create_user();
        $user2 = $this->getDataGenerator()->create_user();

        $this->getDataGenerator()->enrol_user($user1->id, $course->id, 'student');
        $this->getDataGenerator()->enrol_user($user2->id, $course->id, 'student');

        $options = array_merge(['course' => $course], self::COLLABORACFG);
        /** @var \assign $assign */
        $assign = $this->create_instance($options);

        $context = $assign->get_context();

        $studentfilename             = 'user1file.pdf';
        list($plugin, $submission)   = $this->create_collabora_submission($assign, $user1, $studentfilename);
        $student2filename            = 'user2file.pdf';
        list($plugin2, $submission2) = $this->create_collabora_submission($assign, $user2, $student2filename);

        // Only need the context and assign object in this plugin for this operation.
        $requestdata = new \mod_assign\privacy\assign_plugin_request_data($context, $assign, $submission, [], $user1);
        \assignsubmission_collabora\privacy\provider::delete_submission_for_userid($requestdata);
        // This checks that there are no files in this submission.
        $this->assertTrue($plugin->is_empty($submission));
        // There should be files here.
        $this->assertFalse($plugin2->is_empty($submission2));
    }

    /**
     * Test deletion of bulk submissions for a context.
     * @covers \assignsubmission_collabora\privacy\provider::delete_submissions
     * @return void
     */
    public function test_delete_submissions(): void {
        global $DB;

        $this->resetAfterTest();
        // Create course, assignment, submission, and then a feedback comment.
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

        $options = array_merge(['course' => $course], self::COLLABORACFG);
        /** @var \assign $assign1 */
        $assign1 = $this->create_instance($options);
        /** @var \assign $assign2 */
        $assign2 = $this->create_instance($options);

        $context1 = $assign1->get_context();
        $context2 = $assign2->get_context();

        $student1filename            = 'user1file.pdf';
        list($plugin1, $submission1) = $this->create_collabora_submission($assign1, $user1, $student1filename);
        $student2filename            = 'user2file.pdf';
        list($plugin2, $submission2) = $this->create_collabora_submission($assign1, $user2, $student2filename);
        $student3filename            = 'user3file.pdf';
        list($plugin3, $submission3) = $this->create_collabora_submission($assign1, $user3, $student3filename);
        $student4filename            = 'user4file.pdf';
        list($plugin4, $submission4) = $this->create_collabora_submission($assign2, $user4, $student4filename);
        $student5filename            = 'user5file.pdf';
        list($plugin5, $submission5) = $this->create_collabora_submission($assign2, $user3, $student5filename);

        $submissionids = [
            $submission1->id,
            $submission3->id,
        ];

        $select = 'contextid = :contextid
                   AND component = :component
                   AND filearea = :filearea
                   AND filesize > 0
        ';
        $params = [
            'contextid' => $assign1->get_context()->id,
            'component' => 'assignsubmission_collabora',
            'filearea'  => \assignsubmission_collabora\api\collabora_fs::FILEAREA_SUBMIT,
        ];
        $data = $DB->get_records_select('files', $select, $params);
        $this->assertCount(3, $data);

        $data = $DB->get_records('assignsubmission_collabora', ['assignment' => $assign1->get_instance()->id]);
        $this->assertCount(3, $data);

        // Records in the second assignment (not being touched).
        $data = $DB->get_records('assignsubmission_collabora', ['assignment' => $assign2->get_instance()->id]);
        $this->assertCount(2, $data);

        $userids = [
            $user1->id,
            $user3->id,
        ];

        $deletedata = new \mod_assign\privacy\assign_plugin_request_data($context1, $assign1);
        $deletedata->set_userids($userids);
        $deletedata->populate_submissions_and_grades();
        \assignsubmission_collabora\privacy\provider::delete_submissions($deletedata);

        $select = 'contextid = :contextid
                   AND component = :component
                   AND filearea = :filearea
                   AND filesize > 0
        ';
        $params = [
            'contextid' => $assign1->get_context()->id,
            'component' => 'assignsubmission_collabora',
            'filearea'  => \assignsubmission_collabora\api\collabora_fs::FILEAREA_SUBMIT,
        ];
        $data = $DB->get_records_select('files', $select, $params);
        $this->assertCount(1, $data);

        // Submission 1 and 3 have been removed. We should be left with submission2.
        $data = $DB->get_records('assignsubmission_collabora', ['assignment' => $assign1->get_instance()->id]);
        $this->assertCount(1, $data);

        // This should be untouched.
        $data = $DB->get_records('assignsubmission_collabora', ['assignment' => $assign2->get_instance()->id]);
        $this->assertCount(2, $data);
    }
}
