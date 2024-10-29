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
 * Tests for mod/assign/submission/file/locallib.php.
 *
 * @package   assignsubmission_collabora
 * @copyright 2016 Cameron Ball
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace assignsubmission_collabora;

use assignsubmission_collabora\api\collabora_fs;
use mod_collabora\util as collabora_util;

defined('MOODLE_INTERNAL') || die;

global $CFG;
require_once($CFG->dirroot . '/mod/assign/tests/generator.php');

/**
 * Unit tests for mod/assign/submission/file/locallib.php.
 *
 * @copyright  2016 Cameron Ball
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class locallib_test extends \advanced_testcase {
    // Use the generator helper.
    use \mod_assign_test_generator;

    /**
     * Test submission_is_empty.
     *
     * @covers \assignsubmission_collabora\assign_submission_collabora::submission_is_empty
     * @dataProvider submission_is_empty_testcases
     * @param string $data The file submission data
     * @param bool $expected The expected return value
     * @return void
     */
    public function test_submission_is_empty($data, $expected): void {
        $this->resetAfterTest();

        $course  = $this->getDataGenerator()->create_course();
        $student = $this->getDataGenerator()->create_and_enrol($course, 'student');

        $assign = $this->create_instance(
            $course,
            [
                'assignsubmission_collabora_enabled'  => 1,
                'assignsubmission_collabora_format'   => collabora_util::FORMAT_WORDPROCESSOR,
                'assignsubmission_collabora_height'   => 0,
                'assignsubmission_collabora_filename' => 'initialfile.docx',
            ]
        );

        $this->setUser($student->id);

        $submission             = $assign->get_user_submission($student->id, true);
        $plugin                 = $assign->get_submission_plugin_by_type('collabora');
        $submissiondata         = new \stdClass();
        $submissiondata->id     = $assign->get_context()->instanceid;
        $submissiondata->userid = $student->id;

        if ($data) {
            $filerecord = $plugin->get_filerecord('test.txt', collabora_fs::FILEAREA_SUBMIT, $submission->id);
            $fs         = get_file_storage();
            // Store the new file - This will change the ID and automtically unlock it.
            $fs->create_file_from_string($filerecord, $data['content']);
        }

        $result = $plugin->submission_is_empty($submissiondata);
        $this->assertTrue($result === $expected);
    }

    /**
     * Test submission form.
     * @covers \assignsubmission_collabora\assign_submission_collabora::get_form_elements
     * @return void
     */
    public function test_submissionform(): void {
        global $CFG;
        $this->resetAfterTest();

        $elementstocheck = [
            'submfilename'     => 'hidden',
            'submpathnamehash' => 'hidden',
            'subnewsubmssn'    => 'hidden',
            'warning'          => 'static',
        ];
        require_once($CFG->dirroot . '/mod/assign/submission/collabora/tests/lib/submissionform.php');

        $course  = $this->getDataGenerator()->create_course();
        $student = $this->getDataGenerator()->create_and_enrol($course, 'student');

        $assign = $this->create_instance(
            $course,
            [
                'assignsubmission_collabora_enabled'  => 1,
                'assignsubmission_collabora_format'   => collabora_util::FORMAT_WORDPROCESSOR,
                'assignsubmission_collabora_height'   => 0,
                'assignsubmission_collabora_filename' => 'initialfile.docx',
            ]
        );

        $this->setUser($student->id);
        $context = $assign->get_context();
        $cm      = get_coursemodule_from_id('assign', $context->instanceid);

        $data         = new \stdClass();
        $data->userid = $student->id;
        $form         = new \assignsubmission_collabora\fixtures\submissionform(null, [$assign, $data]);
        $this->assertTrue(!empty($form));

        $mform = $form->test_get_form();
        foreach ($elementstocheck as $elname => $eltype) {
            $el = $mform->getElement($elname);
            $this->assertTrue($el->getType() == $eltype);
        }
    }

    /**
     * Dataprovider for the test_submission_is_empty testcase.
     *
     * @return [] of testcases
     */
    public static function submission_is_empty_testcases(): array {
        return [ // Cases.
            'With changed data' => [
                [
                    'content' => 'filecontent',
                ],
                false, // Expected result.
            ],
            'Without changed data' => [
                null,
                true, // Expected result.
            ],
        ];
    }
}
