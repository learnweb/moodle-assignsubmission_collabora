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
 * Contains the callback tests for the plugin.
 *
 * @package   assignsubmission_collabora
 * @copyright 2019 Benjamin Ellis, Synergy Learning
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace assignsubmission_collabora;
use \mod_collabora\api\api;
use \assignsubmission_collabora\api\collabora_fs;

defined('MOODLE_INTERNAL') || die();

global $CFG;
require_once($CFG->dirroot . '/mod/assign/tests/generator.php');
require_once($CFG->dirroot . '/mod/assign/submission/collabora/tests/lib/api_setup.php');

/**
 * Contains the callback tests for the plugin.
 *
 * @package   assignsubmission_collabora
 * @copyright 2019 Benjamin Ellis, Synergy Learning
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class callback_test extends \advanced_testcase {

    // Use the generator helper.
    use \mod_assign_test_generator;
    use \assignsubmission_collabora_test_api_setup;

    /**
     * Test the collabora api to see requests are handled right.
     * @covers \mod_collabora\api\api::handle_request
     *
     * @return void
     */
    public function test_handle_request() {
        list($viewurl, $file, $fs, $assign, $plugin, $student) = $this->setup_and_basic_tests_for_view_url();

        $qry = html_entity_decode(parse_url($viewurl, PHP_URL_QUERY));
        $params = array();
        parse_str($qry, $params);

        $relativepath = urldecode(parse_url($params['WOPISrc'], PHP_URL_PATH));
        $accesstoken = $params['access_token'];
        $postdata = null;

        list($requesttyp, $fileid) = api::get_request_and_fileid_from_path($relativepath, $postdata);
        $collaborafs = collabora_fs::get_instance_by_fileid($fileid, $accesstoken);
        $api = new api($requesttyp, $collaborafs, $postdata);

        /* Create the request - $relativepath, $accesstoken, $postdata. */
        // Get File Info JSON.
        $fileinfo = json_decode($api->handle_request(true));

        // Assert a few things about our $fileinfo.
        $this->assertEquals($file->get_filename(), $fileinfo->BaseFileName);
        $this->assertEquals($accesstoken, $fileinfo->UserId);
        $this->assertEquals($file->get_filesize(), $fileinfo->Size);

        // Assert Get File 2nd - need to add contents onto the relative path.
        $relativepath .= '/contents';
        list($requesttyp, $fileid) = api::get_request_and_fileid_from_path($relativepath, $postdata);
        $collaborafs = collabora_fs::get_instance_by_fileid($fileid, $accesstoken);
        $api = new api($requesttyp, $collaborafs, $postdata);
        $content = $api->handle_request(true);
        $contentsize = strlen($content);
        $filecontentshash = sha1($content);    // File contents hashed.

        $this->assertEquals($file->get_filesize(), $contentsize);   // Same Size.
        // Compare the contents.
        $this->assertEquals(($fch = $file->get_contenthash()), $filecontentshash, "'$fch' NOT '$filecontentshash'");

        // Assert PUT File last - Make out fixture file be the edited file.
        $uploadfile = __DIR__ . '/fixtures/test-upload.odt';
        $postdata = file_get_contents($uploadfile);

        // Update our file record - note the filerecord is changed.
        list($requesttyp, $fileid) = api::get_request_and_fileid_from_path($relativepath, $postdata);
        $collaborafs = collabora_fs::get_instance_by_fileid($fileid, $accesstoken);
        $api = new api($requesttyp, $collaborafs, $postdata);
        $api->handle_request(true);

        sleep(2);   // Give us some time to complete.

        $collaborafs = collabora_fs::get_instance_by_fileid($fileid, $accesstoken);
        $newfile = $collaborafs->get_file();

        $this->assertEquals(strlen($postdata), $newfile->get_filesize());       // Size.
        $this->assertEquals(sha1($postdata), $newfile->get_contenthash());      // Contents.
    }
}
