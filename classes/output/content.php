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

namespace assignsubmission_collabora\output;

/**
 * Class for output the collabora frame.
 *
 * @package    assignsubmission_collabora
 * @author     Andreas Grabs <moodle@grabs-edv.de>
 * @copyright  2020 Grabs EDV-Beratung <moodle@grabs-edv.de>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class content implements \renderable, \templatable {
    /** @var \stdClass $data */
    private $data;

    /**
     * Constructor
     *
     * @param string $id This is a unique css id used for the bootstrap modal.
     * @param string $filename
     * @param \moodle_url $viewurl
     * @param \stdClass $config
     */
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

        // Add a warning notice.
        if (\assignsubmission_collabora\api\collabora_fs::is_testing()) {
            $this->data->hasnotice = true;
            $this->data->noticetype = \core\notification::WARNING;
            $this->data->notice = get_string('collaboraurlnotset', 'mod_collabora');
        }
    }

    /**
     * Function to export the renderer data in a format that is suitable for a
     * mustache template.
     *
     * @param \renderer_base $output Used to do a final render of any components that need to be rendered for export.
     * @return stdClass|array
     */
    public function export_for_template(\renderer_base $output) {
        return $this->data;
    }
}
