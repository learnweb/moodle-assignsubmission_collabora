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
 * @package    assignsubmission_collabora
 *
 * @author     Andreas Grabs <moodle@grabs-edv.de>
 * @copyright  2020 Grabs EDV-Beratung <moodle@grabs-edv.de>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace assignsubmission_collabora\output;

defined('MOODLE_INTERNAL') || die();

class content implements \renderable, \templatable {
    /** @var \stdClass $data */
    private $data;

    public function __construct(string $id, string $filename, \moodle_url $viewurl, \stdClass $config) {
        global $PAGE;

        $this->data = new \stdClass();
        $this->data->id = $id;
        $this->data->filename = $filename;

        $this->data->viewurl = $viewurl->out(false);

        $this->data->framewidth = empty($config->width) ? '100%' : $config->width . 'px';
        $this->data->frameheight = empty($config->height) ? '60vh' : $config->height . 'px';

        /** @var \mod_collabora\output\renderer $renderer */
        $renderer = $PAGE->get_renderer('mod_collabora');
        $this->data->legacy = !$renderer->is_boost_based();

    }

    public function export_for_template(\renderer_base $output) {
        return $this->data;
    }
}
