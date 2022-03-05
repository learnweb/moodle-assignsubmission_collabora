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
     * Pretend to edit our collabora document
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
     * @return stdClass NodeElement.
     */
    private function find_collabora_frame() {
        $exception = new ExpectationException('Collabora frame was not found', $this->getSession());
        return $this->find('css', '.collabora-frame iframe', $exception);
    }
}
