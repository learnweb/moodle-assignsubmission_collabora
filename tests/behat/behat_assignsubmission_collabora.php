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
 * Behat assignsubmission_collabora steps definitions.
 *
 * @package assignsubmission_collabora
 * @copyright 2019 Benjamin Ellis, Synergy Learning
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

// NOTE: no MOODLE_INTERNAL test here, this file may be required by behat before including /config.php.
use Behat\Gherkin\Node\TableNode;

require_once(__DIR__ . '/../../../../../../lib/behat/behat_base.php');

use Behat\Mink\Exception\ExpectationException;

/**
 * Behat assignsubmission_collabora steps definitions.
 *
 * @package assignsubmission_collabora
 * @copyright 2019 Benjamin Ellis, Synergy Learning
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class behat_assignsubmission_collabora extends behat_base {
    /**
     * Pretend to edit our collabora document.
     *
     * @When /^I edit my collabora assign submission document$/
     */
    public function i_edit_my_collabora_assign_submission_document() {
        // Find the frame by by css selector.
        $this->find_collabora_frame();

        // Check the frame URL - Maybe???
        // Cannot do much more than that :(.
    }

    /**
     * Attempt to find the Collabora frame in the displayed page.
     *
     * @return stdClass nodeElement
     */
    private function find_collabora_frame() {
        $exception = new ExpectationException('Collabora frame was not found', $this->getSession());

        return $this->find('css', '.collabora-frame iframe', $exception);
    }

    /**
     * Adds the selected activity/resource filling the form data with the specified field/value pairs.
     *
     * Sections 0 and 1 are also allowed on frontpage.
     *
     * @Given I add a Assignment to course :coursefullname section :sectionnum and I fill the form with:
     * @param string $coursefullname The course full name of the course.
     * @param int $section The section number
     * @param TableNode $data The activity field/value data
     */
    public function i_add_assignment_and_i_fill_the_form_with($coursefullname, $section, TableNode $data) {

        // Add activity to section.
        $this->execute(
            "behat_assignsubmission_collabora::i_add_assignment_to_course_section",
            [$this->escape($coursefullname), $this->escape($section)]
        );

        // Wait to be redirected.
        $this->execute('behat_general::wait_until_the_page_is_ready');

        // Set form fields.
        $this->execute("behat_forms::i_set_the_following_fields_to_these_values", $data);

        // Save course settings.
        $this->execute("behat_forms::press_button", get_string('savechangesandreturntocourse'));
    }

    /**
     * Open a add activity form page.
     *
     * @Given I add a Assignment to course :coursefullname section :sectionnum
     * @throws coding_exception
     * @param string $coursefullname The course full name of the course.
     * @param string $sectionnum The section number.
     */
    public function i_add_assignment_to_course_section(string $coursefullname, string $sectionnum): void {
        $addurl = new moodle_url('/course/modedit.php', [
            'add' => 'assign',
            'course' => $this->get_course_id($coursefullname),
            'section' => intval($sectionnum),
        ]);
        $this->execute('behat_general::i_visit', [$addurl]);
    }
}
