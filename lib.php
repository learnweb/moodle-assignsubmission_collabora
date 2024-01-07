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
 * Some hook functions.
 *
 * @package   assignsubmission_collabora
 * @copyright 2024 Andreas Grabs
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

/**
 * Get an html fragment.
 *
 * @param  mixed  $args an array or object with context and parameters needed to get the data
 * @return string The html fragment we want to use by ajax
 */
function assignsubmission_collabora_output_fragment_get_html($args) {
    return \assignsubmission_collabora\fragment\util::get_html($args);
}
