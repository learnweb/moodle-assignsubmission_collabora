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
    var frame1;
    var frame2;

    var loadsrc = function (frame, src) {
        frame.attr("src", src);
    };

    return {
        'init': function(frameurl, id) {
            frame1 = $("#collaboraiframe_" + id);
            frame2 = $("#collaboraiframe2_" + id);

            $("#collaboramodal_" + id).on("show.bs.modal", function () {
                frame1.attr("src", "about:blank");
                frame2.attr("src", frameurl);
                $("body").addClass("modal-open");
            });
            $("#collaboramodal_" + id).on("hide.bs.modal", function () {
                frame2.attr("src", "about:blank");
                frame1.attr("src", frameurl);
                $("body").removeClass("modal-open");
            });

            var interval = setInterval(function () {
                if (frame1.is(":visible")) {
                    loadsrc(frame1, frameurl);
                    clearInterval(interval);
                }
            }, 500);
        }
    };

});
