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
 * Contains the event tests for the plugin.
 *
 * @package   assignsubmission_collabora
 * @copyright 2019 Benjamin Ellis, Synergy Learning
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace assignsubmission_collabora;
use \assignsubmission_collabora\api\collabora_fs;

defined('MOODLE_INTERNAL') || die();

global $CFG;
require_once($CFG->dirroot . '/mod/assign/tests/generator.php');

/**
 * Contains the event tests for the plugin.
 *
 * @package   assignsubmission_collabora
 * @copyright 2019 Benjamin Ellis, Synergy Learning
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class events_test extends \advanced_testcase {

    // Use the generator helper.
    use \mod_assign_test_generator;

    /**
     * Setup Method for test_assessable_uploaded(), test_submission_created() and test_submission_updated()
     * @return array ($file, $plugin, $assign, $submission, $sink, $dummy, $course)
     */
    public function setup_submission() {
        $this->resetAfterTest();

        $course = $this->getDataGenerator()->create_course();
        $student = $this->getDataGenerator()->create_and_enrol($course, 'student');
        $assign = $this->create_instance($course);
        $context = $assign->get_context();

        $this->setUser($student->id);
        $submission = $assign->get_user_submission($student->id, true);
        $plugin = $assign->get_submission_plugin_by_type('collabora');
        $filearea = collabora_fs::FILEAREA_SUBMIT;

        $fs = get_file_storage();
        $dummy = (object) array(
            'contextid' => $context->id,
            'component' => 'assignsubmission_collabora',
            'filearea' => $filearea,
            'itemid' => $submission->id,
            'filepath' => '/',
            'filename' => 'myassignmnent.docx'
        );
        $file = $fs->create_file_from_string($dummy, 'Content of ' . $dummy->filename);
        $sink = $this->redirectEvents();
        return array($file, $plugin, $assign, $submission, $sink, $dummy, $course);
    }

    /**
     * Test that the assessable_uploaded event is fired when a file submission has been made.
     * @covers \assignsubmission_collabora\event\assessable_uploaded
     */
    public function test_assessable_uploaded() {
        list($file, $plugin, $assign, $submission, $sink) = $this->setup_submission();
        $data = new \stdClass();
        $data->submpathnamehash = $file->get_pathnamehash();
        $data->submfilename = $file->get_filename();
        $data->submfileid = $file->get_id();
        $data->subnewsubmssn = 1;
        $plugin->save($submission, $data);
        $events = $sink->get_events();

        $this->assertCount(2, $events);  // There are 2 events in the save() method.
        $event = $events[0];    // We want the 1st event.
        $this->assertInstanceOf('\assignsubmission_file\event\assessable_uploaded', $event);
        $this->assertEquals($assign->get_context()->id, $event->contextid);
        $this->assertEquals($submission->id, $event->objectid);
        $this->assertCount(1, $event->other['pathnamehashes']); // Only ever 1 file.
        $this->assertEquals($file->get_pathnamehash(), $event->other['pathnamehashes'][0]);
        $this->assertEventContextNotUsed($event);
    }

    /**
     * Test that the submission_created event is fired when a file submission is saved.
     * @covers \assignsubmission_collabora\event\submission_created
     */
    public function test_submission_created() {
        list($file, $plugin, $assign, $submission, $sink, $dummy) = $this->setup_submission();
        $data = new \stdClass();
        $data->submpathnamehash = $file->get_pathnamehash();
        $data->submfilename = $dummy->filename;
        $data->subnewsubmssn = 1;           // New file.
        $plugin->save($submission, $data);
        $events = $sink->get_events();

        $this->assertCount(2, $events);
        $event = array_pop($events);        // Last event.
        $this->assertInstanceOf('\assignsubmission_collabora\event\submission_created', $event);
        $this->assertEquals($assign->get_context()->id, $event->contextid);
        $this->assertEquals($event->other['submissionid'], $submission->id);
        $this->assertEquals($event->other['submissionattempt'], $submission->attemptnumber);
        $this->assertEquals($event->other['submissionstatus'], $submission->status);
        $this->assertEquals($event->other['submissionfilename'], $data->submfilename);
        $this->assertEquals($event->other['submpathnamehash'], $data->submpathnamehash);
        $this->assertEventContextNotUsed($event);
    }

    /**
     * Test that the submission_updated event is fired when a file submission is saved when an existing submission already exists.
     * @covers \assignsubmission_collabora\event\submission_updated
     */
    public function test_submission_updated() {
        list($file, , $assign, $submission, $sink, $dummy, $course) = $this->setup_submission();
        $plugin = $assign->get_submission_plugin_by_type('collabora');
        $data = new \stdClass();
        $data->submpathnamehash = $file->get_pathnamehash();
        $data->submfilename = $dummy->filename;
        $data->subnewsubmssn = 1;           // New file.
        // Create a submission.
        $plugin->save($submission, $data);
        // Update a submission.
        $data->subnewsubmssn = 0;       // Updated file.
        $plugin->save($submission, $data);
        $events = $sink->get_events();

        $this->assertCount(4, $events);     // Fired 2 times each by save().
        // We want to test the last event fired.
        $event = array_pop($events);
        $this->assertInstanceOf('\assignsubmission_collabora\event\submission_updated', $event);
        $this->assertEquals($assign->get_context()->id, $event->contextid);
        $this->assertEquals($course->id, $event->courseid);
        $this->assertEquals($submission->id, $event->other['submissionid']);
        $this->assertEquals($submission->attemptnumber, $event->other['submissionattempt']);
        $this->assertEquals($submission->status, $event->other['submissionstatus']);
        $this->assertEquals($submission->userid, $event->relateduserid);
    }
}
