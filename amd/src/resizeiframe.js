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
 * @author     Andreas Grabs <moodle@grabs-edv.de>
 * @copyright  2020 Grabs EDV-Beratung <moodle@grabs-edv.de>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

define(['jquery'], function($) {
    /**
     * Resize the iframe depending on the current window size.
     * @returns void
     */
    function resizeIframe() {
        var $iframe = $('iframe.collabora-iframe');
        if (!$iframe.length) {
            return;
        }
        var viewheight = $(window).height();
        var frametop = $iframe.offset().top;
        var height = viewheight - frametop - 30;
        if (height < 300) {
            height = 300;
        }
        $iframe.attr('height', height);
    }

    return {
        init: function() {
            $(window).on('resize', resizeIframe);
            resizeIframe();
        }
    };
});
