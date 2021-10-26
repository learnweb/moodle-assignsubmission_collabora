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
 * The assign_submission_file class
 *
 * @package assignsubmission_collabora
 * @copyright 2019 Benjamin Ellis, Synergy Learning
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
use mod_collabora\collabora;

defined('MOODLE_INTERNAL') || die();

/**
 * Library class for collabora submission plugin extending submission plugin base class
 *
 * @package assignsubmission_collabora
 * @copyright 2012 NetSpot {@link http://www.netspot.com.au}
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class assign_submission_collabora extends assign_submission_plugin {

    /** @var $callbackurl - Callback URL - could be defined in the collabora class. */
    private $callbackurl = '/mod/assign/submission/collabora/callback.php/wopi/files/';

    /**
     *  Could do with this being defined in the collabora class.
     */
    const FILEAREA_USER = 'user';

    /**
     * The default file options.
     *
     * @return array of file options.
     */
    private function get_default_fileoptions() {
        return array(
            'subdirs' => 0,
            'maxbytes' => 0,
            'maxfiles' => 1,
            'accepted_types' => \mod_collabora\collabora::get_accepted_types()
        );
    }

    /**
     * Function to return the file record object.
     *
     * @param string $filename - might be empty to be filled by caller.
     * @param string $filearea - the file area - default is the initial files area.
     * @param int $itemid - usually the user or group id excpet for initial files.
     * @param string $filepath - we don't use this for our plugin but might do in the future.
     * @return StdClass
     */
    private function get_filerecord($filename = null, $filearea = collabora::FILEAREA_INITIAL, $itemid = 0, $filepath = '/') {
        $contextid = $this->assignment->get_context()->id;
        return(object) [
            'contextid' => $contextid,
            'component' => 'assignsubmission_collabora',
            'filearea' => $filearea,
            'itemid' => $itemid,
            'filepath' => $filepath,
            'filename' => $filename ? clean_filename($filename) : $filename
        ];
    }

    /**
     * Get the Initial file to be copied into the relevant filearea.
     *
     * @param file_storage $fs
     * @return stored_file $file
     */
    private function get_initial_file(file_storage $fs = null) {
        $filerec = $this->get_filerecord();
        if (is_null($fs)) {
            $fs = get_file_storage();
        }
        $files = $fs->get_area_files($filerec->contextid, $filerec->component, $filerec->filearea,
            $filerec->itemid, null, false, 0, 0, 1);
        $file = reset($files);
        return $file;
    }

    /**
     * Returns a link to the initial or submitted file
     *
     * NOT currently used but might be in a later version - NB the plugin_file function will need ...
     * ... to be implemented in lib.php
     *
     * @return string error | html link.
     */
    private function get_file_link() {
        $file = $this->get_initial_file();
        if ($file) {
            $url = moodle_url::make_pluginfile_url($file->get_contextid(), $file->get_component(), $file->get_filearea(),
                $file->get_itemid(), $file->get_filepath(), $file->get_filename(), true);
            return html_writer::link($url, $file->get_filename());
        } else {
            return get_string('missingfile', 'assignsubmission_collabora');
        }
    }

    /**
     * Unset the configuration settings.
     *
     * Setting all the config settings to null values - there is no way to delete config settings.
     *
     * @param string $setting - a config setting name.
     * @return boolean - always true until we do the submissions check.
     */
    private function unset_config($setting = null) {
        // TODO Should we really check if there are no submissions before doing this?
        if (is_null($setting)) {
            // We are removing all our settings and any possible file saved.
            $localconfig = (array) $this->get_thisplugin_config();
            foreach (array_keys($localconfig) as $cfg) {
                $this->set_config($cfg, null);
            }
            // We must also delete the initial file.
            if ($file = $this->get_initial_file()) {
                $file->delete();
            }
        } else {
            // We just delete the requested setting.
            $this->set_config($setting, null);
        }
        return true;
    }

    /**
     * Get the discovery XML file from the collabora server.
     *
     * @return string
     */
    private function load_discovery_xml() {
        $collaboracfg = get_config('mod_collabora');
        return \mod_collabora\collabora::get_discovery_xml($collaboracfg);
    }

    /**
     * Get the URL for editing the given mimetype.
     *
     * STOLEN from mod_collabora class with some changes.
     *
     * @param string $discoveryxml
     * @param string $mimetype
     * @return string
     */
    private function get_url_from_mimetype($discoveryxml, $mimetype) {
        libxml_use_internal_errors(true);
        try {
            $xml = new \SimpleXMLElement($discoveryxml);
        } catch (Exception $e) {
            throw new \moodle_exception('xmlfailmessage', 'assignsubmission_collabora', '', htmlentities($discoveryxml));
        }
        $app = $xml->xpath("//app[@name='{$mimetype}']");
        if (!$app) {
            throw new \moodle_exception('unsupportedtype', 'mod_collabora', '', $mimetype);
        }
        $action = $app[0]->action;
        $url = isset($action ['urlsrc']) ? $action ['urlsrc'] : '';
        if (!$url) {
            throw new \moodle_exception('unsupportedtype', 'mod_collabora', '', $mimetype);
        }
        return(string) $url;
    }

    /**
     * Get the URL of the handler, base on the mimetype of the existing file.
     *
     * STOLEN FROM collabora class - added minor alterations.
     *
     * @param stored_file $submissionfile - the file
     * @return \moodle_url - url.
     */
    private function get_collabora_url(stored_file $submissionfile) {
        $mimetype = $submissionfile->get_mimetype(); // Changed Line.
        $discoveryxml = $this->load_discovery_xml();

        return new \moodle_url(
            $this->get_url_from_mimetype(
                $discoveryxml,
                $mimetype
            )
        );

    }

    /**
     * Return the view url for the API call to Collabora CORE.
     *
     * Partly STOLEN FROM collabora class BUT has changes.
     *
     * @param stdClass $submission
     * @param stored_file $submissionfile
     * @param int $userid - the user id of the person viewing the submission.
     * @param bool $forcereadonly - force the file to be read only.
     * @return \moodle_url $viewurl - A URL
     */
    public function get_view_url(stdClass $submission, stored_file $submissionfile, $userid, $forcereadonly = false) {

        $permission = $this->get_submission_permission_for_user($submission, $userid, $forcereadonly);

        $collaboraurl = $this->get_collabora_url($submissionfile);
        $fileid = $submissionfile->get_pathnamehash() . '_' . $permission;
        $usertoken = md5($userid) . '_'. $userid; // Userid is unique to our installation. - Id will always be the same.

        $callbackurl = new \moodle_url($this->callbackurl . $fileid);

        $params = array(
            'WOPISrc' => $callbackurl->out(),
            'access_token' => $usertoken,
            'lang' => collabora::get_collabora_lang(),
        );

        $collaboraurl->params($params);
        return $collaboraurl;
    }

    /**
     * Return the view url wrapped in the html frame.
     *
     * @param stdClass $submission
     * @param \stored_file $submissionfile - the file object.
     * @param int $userid
     * @param boolean $forcereadonly - If the file should be readonly.
     * @return string - HTML
     */
    private function get_view_htmlframe($submission, $submissionfile, $userid, $forcereadonly = false) {
        global $OUTPUT;

        $config = $this->get_config();

        $viewurl = $this->get_view_url($submission, $submissionfile, $userid, $forcereadonly);
        $id = uniqid();
        $widget = new \assignsubmission_collabora\output\content($id, $submissionfile->get_filename(), $viewurl, $config);

        return $OUTPUT->render($widget);
    }

    /**
     * Function to work out permissions for the submission in question.
     *
     * Owner + Group + Others (Site Admin)
     *  400 = Owner can read
     *  440 = Group can read
     *  444 = All Read only
     *  600 = Owner Can Edit - No Group
     *  660 = Group can Edit
     *  666 = All can Edit - site admin
     *
     * @param stdClass $submission
     * @param int $userid
     * @param bool $forcereadonly - force the document to be readonly
     * @return number = permissions mask
     */
    private function get_submission_permission_for_user($submission, $userid = null, $forcereadonly = false) {
        global $USER;

        if (!$userid) {
            $userid = $USER->id;
        }

        $permission  = 444;     // Default - All can read - User will not get here if they cannot view submissions at least.

        // Site Admins && graders (teachers/managers) cannot edit the file - irrespective of submission status.
        if (!is_siteadmin() && !$this->assignment->can_grade()) {
            // Is the submission editable by the current user? - The lock status is enough to tell us.
            if ($this->assignment->submissions_open($userid, null, $submission)) {
                if (!empty($submission->groupid)) {       // Group membership checked in submissions_open() call.
                    $permission = $forcereadonly ? 440 : 660;
                } else if ($submission->userid == $userid) {
                    $permission = $forcereadonly ? 400 : 600;
                }
            }
        }
        return $permission;
    }

    /**
     * Function to set the form input error.
     *
     * @param string $msg - the error message.
     * @return boolean - always false to set the error tracking flag.
     */
    private function report_form_error($msg) {
        $this->set_error($msg);
        return false; // Return false for error tracking.
    }

    /**
     * To ensure we always only deal with our own config settings.
     *
     * @return StdClass - config object.
     */
    private function get_thisplugin_config() {
        $configkeys = array(
            'format',
            'width',
            'height',
            'initialtext',
            'filename'
        );
        $thisplugincfg = array();
        $assignpluginconfig = (array) $this->get_config();

        foreach ($assignpluginconfig as $assignsetting => $settingvalue) {
            if (in_array($assignsetting, $configkeys)) {
                $thisplugincfg [$assignsetting] = $settingvalue;
            }
        }
        return(object) $thisplugincfg; // Object to keep it consistent.
    }

    /**
     * Should return the name of this plugin type.
     *
     * @return string - the name
     */
    public function get_name() {
        return get_string('pluginname', 'assignsubmission_collabora');
    }

    /**
     * Get the default setting for submission plugin
     *
     * @param MoodleQuickForm $mform - The form to add elements to
     * @return void
     */
    public function get_settings(MoodleQuickForm $mform) {
        if ($this->assignment->has_instance()) {
            $pluginconfig = (array) $this->get_config();
        } else {
            $pluginconfig = array();
        }

        // Use the module's configuration as well as our own.
        $config = (object) (array_merge((array) get_config('mod_collabora'),
            (array) get_config('assignsubmission_collabora'), $pluginconfig));

        $isexisting = false;
        if (!empty($config->format)) {
            $isexisting = true; // Settings have existed before - problem is we may have been disabled!!
        }

        /*
         * Now determine if this plugin has been disabled previously.
         * We need this as parent::disable() cannot be overridden :(
         * And my $this->save_settings() will not override existing settings.
         */
        if ($isexisting && !$config->enabled) {
            $isexisting = !$this->unset_config();
        }

        $filemanageropts = $this->get_default_fileoptions();

        // Format section.
        $mform->addElement('select', 'assignsubmission_collabora_format',
            get_string('format', 'assignsubmission_collabora'), \mod_collabora\collabora::format_menu());
        $mform->setDefault('assignsubmission_collabora_format',
            empty($config->format) ? $config->defaultformat : $config->format);
        if ($isexisting) {
            $mform->freeze('assignsubmission_collabora_format');
        }
        $mform->hideif('assignsubmission_collabora_format', 'assignsubmission_collabora_enabled', 'notchecked');

        // Text File - initial text.
        if (!$isexisting || $config->format === \mod_collabora\collabora::FORMAT_TEXT) {
            $mform->addElement('textarea', 'assignsubmission_collabora_initialtext',
                get_string('initialtext', 'assignsubmission_collabora'));
            $mform->hideif('assignsubmission_collabora_initialtext',
                'assignsubmission_collabora_format', 'neq', \mod_collabora\collabora::FORMAT_TEXT);
            $mform->setDefault('assignsubmission_collabora_initialtext',
                empty($config->initialtext) ? '' : $config->initialtext);
            if ($isexisting) {
                $mform->freeze('assignsubmission_collabora_initialtext');
            }
            $mform->hideif('assignsubmission_collabora_initialtext',
                'assignsubmission_collabora_enabled', 'notchecked');
        }

        // Filename Requirement - added in the name for FORMAT_TEXT as we still need a name.
        if (!$isexisting) {
            $mform->addElement('text', 'assignsubmission_collabora_filename',
                get_string('filename', 'assignsubmission_collabora'), array('size' => '60'));
            $mform->setDefault('assignsubmission_collabora_filename', '');
            $mform->setType('assignsubmission_collabora_filename', PARAM_FILE);
            $mform->hideif('assignsubmission_collabora_filename',
                'assignsubmission_collabora_format', 'eq', \mod_collabora\collabora::FORMAT_UPLOAD);
            if ($isexisting) {
                $mform->freeze('assignsubmission_collabora_filename');
            }
            $mform->hideif('assignsubmission_collabora_filename',
                'assignsubmission_collabora_enabled', 'notchecked');
        }

        // File Manager section.
        if (!$isexisting) {
            $mform->addElement('filemanager', 'assignsubmission_collabora_initialfile_filemanager',
                get_string('initialfile', 'assignsubmission_collabora'), null, $filemanageropts);
            $mform->hideif ('assignsubmission_collabora_initialfile_filemanager',
                'assignsubmission_collabora_format', 'neq', \mod_collabora\collabora::FORMAT_UPLOAD);
            $mform->hideif ('assignsubmission_collabora_initialfile_filemanager',
                'assignsubmission_collabora_enabled', 'notchecked');
        } else {
            $file = $this->get_initial_file();
            $mform->addElement('static', 'initialfile',
                get_string('initialfile', 'assignsubmission_collabora'), $file->get_filename());
        }

        // Width.
        $mform->addElement('text', 'assignsubmission_collabora_width',
            get_string('width', 'assignsubmission_collabora'));
        $mform->setDefault('assignsubmission_collabora_width', 0);
        $mform->setType('assignsubmission_collabora_width', PARAM_INT);
        $mform->hideif('assignsubmission_collabora_width', 'assignsubmission_collabora_enabled', 'notchecked');

        // Height.
        $mform->addElement('text', 'assignsubmission_collabora_height', get_string('height', 'assignsubmission_collabora'));
        $mform->setDefault('assignsubmission_collabora_height', 0);
        $mform->setType('assignsubmission_collabora_height', PARAM_INT);
        $mform->hideif('assignsubmission_collabora_height', 'assignsubmission_collabora_enabled', 'notchecked');
    }

    /**
     * Generate a filename where one is not provided.
     *
     * Moodle error for assignment plugins is a print_error() which is nasty for users.
     *
     * @return string
     */
    private function generaterandonfilename() {
        return ('aaa' .
            substr(str_shuffle(
                str_repeat('abcdefghijklmnopqrstuvwxyz0123456789ABCDEFGHIJKLMNOPQRSTUVWXYZ',
                    mt_rand(1, 10))), 1, 7));
    }

    /**
     * Save the settings for submission plugin.
     *
     * @param stdClass $data - the form data.
     * @return bool - on error the subtype should call set_error and return false.
     */
    public function save_settings(stdClass $data) {
        global $CFG;
        $noerror = true; // Track input errors.

        // Get our own local config settings - if we do not have any settings - this is a new record.
        $newrecord = false;
        $localconfig = $this->get_thisplugin_config();
        if (empty($localconfig->format)) {
            $newrecord = true;
        }

        // Format cannot be empty as it is in the select - always data available -if enabled.
        $this->set_config('format', $data->assignsubmission_collabora_format);
        // Width never empty - required for all formats.
        $this->set_config('width', $data->assignsubmission_collabora_width);
        // Height never empty - required for all formats.
        $this->set_config('height', $data->assignsubmission_collabora_height);

        /*
         * We can save new settings.
         */
        if ($newrecord) {
            // Work out the filename for all types except Uploads.
            $filename = null;
            if ($data->assignsubmission_collabora_format !== collabora::FORMAT_UPLOAD) {
                if (empty(trim($data->assignsubmission_collabora_filename))) {
                    $data->assignsubmission_collabora_filename = $this->generaterandonfilename();
                }
                if (!$filename = clean_filename($data->assignsubmission_collabora_filename)) {
                    $data->assignsubmission_collabora_filename = $this->generaterandonfilename();
                }
                if ($noerror) {
                    $this->set_config('filename', $filename);
                }
            }

            // Create the initial file.
            if ($noerror) {
                $fs = get_file_storage();
                $filerec = $this->get_filerecord($filename);
                switch($data->assignsubmission_collabora_format) {
                    case collabora::FORMAT_UPLOAD :
                        $info = file_get_draft_area_info($data->assignsubmission_collabora_initialfile_filemanager);
                        if ($info['filecount']) {
                            // Save the uploaded file as the initial file.
                            file_save_draft_area_files($data->assignsubmission_collabora_initialfile_filemanager,
                                $filerec->contextid, $filerec->component, $filerec->filearea, $filerec->itemid,
                                $this->get_default_fileoptions());
                        } else {
                            // We will just save a default file instead - avoiding the print_error() in the calling function.
                            $filerec->filename .= $this->generaterandonfilename() . '.docx';
                            $filepath = $CFG->dirroot . '/mod/collabora/blankfiles/blankdocument.docx';
                            $fs->create_file_from_pathname($filerec, $filepath);
                        }
                        break;
                    case collabora::FORMAT_TEXT :
                        if (empty($data->assignsubmission_collabora_initialtext)) {
                            $data->assignsubmission_collabora_initialtext = '';
                        }
                        $this->set_config('initialtext', $data->assignsubmission_collabora_initialtext);
                        $filerec->filename .= '.txt';
                        $fs->create_file_from_string($filerec, $data->assignsubmission_collabora_initialtext);
                        break;
                    case collabora::FORMAT_WORDPROCESSOR :
                        $filerec->filename .= '.docx';
                        $filepath = $CFG->dirroot . '/mod/collabora/blankfiles/blankdocument.docx';
                        $fs->create_file_from_pathname($filerec, $filepath);
                        break;
                    case collabora::FORMAT_SPREADSHEET :
                        $filerec->filename .= '.xlsx';
                        $filepath = $CFG->dirroot . '/mod/collabora/blankfiles/blankspreadsheet.xlsx';
                        $fs->create_file_from_pathname($filerec, $filepath);
                        break;
                    case collabora::FORMAT_PRESENTATION :
                        $filerec->filename .= '.pptx';
                        $filepath = $CFG->dirroot . '/mod/collabora/blankfiles/blankpresentation.pptx';
                        $fs->create_file_from_pathname($filerec, $filepath);
                        break;
                    default :
                        // Should never happen.
                        throw new \coding_exception("Unknown format: {$data->assignsubmission_collabora_format}");
                        break; // Paranoia.
                }
            }
        }
        return $noerror;
    }

    /**
     * View the submission summary and sets whether a view link be provided.
     *
     * @param stdClass $submission
     * @param bool $showviewlink - whether or not to have a link to view the submission file.
     * @return string view text.
     */
    public function view_summary(stdClass $submission, & $showviewlink) {
        global $USER, $DB;
        if (!empty($submission)) {
            $submission = $DB->get_record('assign_submission', array('id' => $submission->id));
        }
        $showviewlink = false;      // Default do not show view link.
        if (self::is_empty($submission)) {
            return get_string('nosubmission', 'assignsubmission_collabora');
        } else {
            // I do not want to show a link on the summary page where the user/group member is ...
            // ... editing as the should use the Edit Submission Button.
            if ($this->assignment->can_grade()) {       // Grader.
                $showviewlink = true;
            } else if (!$this->assignment->can_edit_submission($USER->id)) {
                // If we cannot see the submission any other way - i.e. it is not editable.
                $showviewlink = true;
            }
            if (empty($submission->status)) {   // Should not happen - but has!
                return '**:' . get_string('submissionsubmitted', 'assignsubmission_collabora');
            } else {
                return ucfirst($submission->status);
            }
        }
    }

    /**
     * Save any custom data for this form submission.
     *
     * We do not have to save anything as it will have happened in the callback from the Collabora frame.
     * We will however generate an event for the save.
     *
     * @param stdClass $submission
     * @param stdClass $data - the data submitted from the form
     * @return bool - on error then we set error and return false.
     */
    public function save(stdClass $submission, stdClass $data) {
        global $USER, $DB;
        // The assessable_uploaded event.
        $params = array(
            'context' => context_module::instance($this->assignment->get_course_module()->id),
            'courseid' => $this->assignment->get_course()->id,
            'objectid' => $submission->id,
            'other' => array(
                'content' => '',
                'pathnamehashes' => array(
                    $data->submpathnamehash
               )
           )
        );
        if (!empty($submission->userid) &&($submission->userid != $USER->id)) {
            $params ['relateduserid'] = $submission->userid;
        } else {
            $params ['userid'] = $submission->userid;
        }
        $event = \assignsubmission_file\event\assessable_uploaded::create($params);
        $event->trigger();

        // BORROWED from file submission code.
        $groupname = null;
        $groupid = 0;
        // Get the group name as other fields are not transcribed in the logs and this information is important.
        if (empty($submission->userid) && !empty($submission->groupid)) {
            $groupname = $DB->get_field('groups', 'name', array('id' => $submission->groupid), MUST_EXIST);
            $groupid = $submission->groupid;
        } else {
            $params['relateduserid'] = $submission->userid;
        }
        // End BORROWED.

        // Unset the objectid and other field from params for use in submission events.
        unset($params ['objectid']); // We do not use this as we do not have a separate table.
        unset($params ['other']);
        $params ['other'] = array(
            'submissionid' => $submission->id,
            'submissionattempt' => $submission->attemptnumber,
            'submissionstatus' => $submission->status,
            'submissionfilename' => $data->submfilename,
            'submpathnamehash' => $data->submpathnamehash,
            'groupid' => $groupid,
            'groupname' => $groupname
        );

        if ($data->subnewsubmssn) {
            // A new submission.
            $event = \assignsubmission_collabora\event\submission_created::create($params);
        } else {
            // An updated submission.
            $event = \assignsubmission_collabora\event\submission_updated::create($params);
        }
        $event->set_assign($this->assignment);
        $event->trigger();

        return true;
    }

    /**
     * Produce a list of files suitable for export that represent this submission.
     *
     * @param stdClass $submission
     * @param stdClass $user
     * @return array - return an array of files indexed by filename
     */
    public function get_files(stdClass $submission, stdClass $user) {
        $result = array();

        $fs = get_file_storage();
        if ($submission->groupid) { // Group Submission.
            $filerec = $this->get_filerecord(null, collabora::FILEAREA_GROUP, $submission->groupid);
        } else {
            // We will use the userid.
            $filerec = $this->get_filerecord(null, self::FILEAREA_USER, $submission->userid);
        }

        $files = $fs->get_area_files($filerec->contextid, $filerec->component, $filerec->filearea,
            $filerec->itemid, '', false, 0, 0, 1);
        if ($file = reset($files)) {
            // Do we return the full folder path or just the file name?
            if (isset($submission->exportfullpath) && $submission->exportfullpath == false) {
                $result [$file->get_filename()] = $file;
            } else {
                $result [$file->get_filepath() . $file->get_filename()] = $file;
            }
        }
        return $result;
    }

    /**
     * Get any additional fields for the submission form for this assignment.
     *
     * @param stdClass $submission
     * @param MoodleQuickForm $mform
     * @param stdClass $data - the form data.
     * @param int $userid
     * @return boolean - true if we added anything to the form
     */
    public function get_form_elements($submission, MoodleQuickForm $mform, stdClass $data, $userid = null) {
        global $USER;

        if (is_null($userid)) {
            $userid = empty($data->userid) ? $USER->id : $data->userid;
        }

        // Get or Create our submission file base record.
        $fs = get_file_storage();
        if ($submission->groupid) { // Group Submission.
            $filerec = $this->get_filerecord(null, collabora::FILEAREA_GROUP, $submission->groupid);
        } else {
            // We will use the userid.
            $filerec = $this->get_filerecord(null, self::FILEAREA_USER, $userid);
        }

        // For now we check for the submission file existance 1st.
        $isnewsubmission = 0;
        $files = $fs->get_area_files($filerec->contextid, $filerec->component, $filerec->filearea,
            $filerec->itemid, '', false, 0, 0, 1);
        if (!$submissionfile = reset($files)) {
            // Get the initial file to copy.
            if ($initialfile = $this->get_initial_file($fs)) {
                $filerec->filename = $initialfile->get_filename();
                $submissionfile = $fs->create_file_from_storedfile($filerec, $initialfile);
                $isnewsubmission = 1;
            } else {
                // Should never happen.
                throw new \coding_exception("Missing Initial File.");
            }
        }

        $html = $this->get_view_htmlframe($submission, $submissionfile, $userid);

        $mform->addElement('html', $html);

        // Some hidden fields for the save event.
        $mform->addElement('hidden', 'submfilename', $submissionfile->get_filename());
        $mform->setType('submfilename', PARAM_TEXT);
        $mform->addElement('hidden', 'submpathnamehash', $submissionfile->get_pathnamehash());
        $mform->setType('submpathnamehash', PARAM_RAW);
        $mform->addElement('hidden', 'subnewsubmssn', $isnewsubmission);
        $mform->setType('subnewsubmssn', PARAM_INT);
        // Sometimes required to ensure changes are saved - particuarly for specified text.
        $mform->addElement('static', 'warning', get_string('formsavewarmingpmt', 'assignsubmission_collabora'),
            get_string('formsavewarming', 'assignsubmission_collabora'));

        return true;
    }

    /**
     * View submission - the submission file will always be read only.
     *
     * @param stdClass $submission
     * @return string - html frame of the submitted file.
     */
    public function view(stdClass $submission) {
        global $USER;
        // Get or Create our submission file base record.
        $fs = get_file_storage();
        if (!empty($submission->groupid)) { // Group Submission.
            $filerec = $this->get_filerecord(null, collabora::FILEAREA_GROUP, $submission->groupid);
        } else {
            // We will use the userid.
            $filerec = $this->get_filerecord(null, self::FILEAREA_USER, $submission->userid);
        }

        $files = $fs->get_area_files($filerec->contextid, $filerec->component, $filerec->filearea,
            $filerec->itemid, '', false, 0, 0, 1);
        if (!$submissionfile = reset($files)) {
            // Should never happen.
            throw new \coding_exception("Missing Submission File.");
        }
        // All calls through here must be readonly.
        $forcereadonly = true;

        $userid = $USER->id;
        $html = $this->get_view_htmlframe($submission, $submissionfile, $userid, $forcereadonly);

        return $html;
    }

    /**
     * Return true if this plugin can upgrade an old Moodle 2.2 assignment of this type
     * and version.
     *
     * @param string $type The old assignment subtype
     * @param int $version The old assignment version
     * @return bool True if upgrade is possible
     */
    public function can_upgrade($type, $version) {
        return false;
    }

    /**
     * Formatting for log info.
     *
     * @param stdClass $submission The submission
     * @return string
     */
    public function format_for_log(stdClass $submission) {
        // Do we need to do any more than this?
        return get_string('logmessage', 'assignsubmission_collabora');
    }

    /**
     * Get file areas returns a list of areas this plugin stores files
     *
     * @return array - An array of fileareas(keys) and descriptions(values)
     */
    public function get_file_areas() {
        return array(
            collabora::FILEAREA_INITIAL =>
                ucfirst(get_string('fileareadesc', 'assignsubmission_collabora', collabora::FILEAREA_INITIAL)),
            collabora::FILEAREA_GROUP =>
                ucfirst(get_string('fileareadesc', 'assignsubmission_collabora', collabora::FILEAREA_GROUP)),
            self::FILEAREA_USER =>
                ucfirst(get_string('fileareadesc', 'assignsubmission_collabora', self::FILEAREA_USER))
        );
    }

    /**
     * Copy the plugin specific submission data to a new submission record.
     *
     * @param stdClass $sourcesubmission - Old submission record
     * @param stdClass $destsubmission - New submission record
     * @return bool
     */
    public function copy_submission(stdClass $sourcesubmission, stdClass $destsubmission) {
        return true; // File will always remain the same.
    }

    /**
     * The assignment has been deleted - remove the plugin specific data
     *
     * @return bool
     */
    public function delete_instance() {
        return $this->unset_config();
    }

    /**
     * If true, the plugin will appear on the module settings page and can be
     * enabled/disabled per assignment instance.
     *
     * @return bool
     */
    public function is_configurable() {
        return true;
    }

    /**
     * Is this assignment plugin empty?(ie no submission)
     *
     * @param stdClass $submission assign_submission.
     * @return bool
     */
    public function is_empty(stdClass $submission) {
        $fs = get_file_storage();
        if (!empty($submission->groupid)) { // Group Submission.
            $filerec = $this->get_filerecord(null, collabora::FILEAREA_GROUP, $submission->groupid);
        } else {
            // We will use the userid.
            $filerec = $this->get_filerecord(null, self::FILEAREA_USER, $submission->userid);
        }

        $files = $fs->get_area_files($filerec->contextid, $filerec->component, $filerec->filearea,
            $filerec->itemid, '', false, 0, 0, 1);
        return count($files) == 0;
    }

    /**
     * Determine if a submission is empty - only called on 1st submission always return false???
     *
     * @param stdClass $data The submission data
     * @return bool
     */
    public function submission_is_empty(stdClass $data) {
        return false;
    }

    /**
     * Remove any saved data from this submission.
     *
     * @since Moodle 3.6
     * @param stdClass $submission - assign_submission data
     * @return void
     */
    public function remove(stdClass $submission) {
        $fs = get_file_storage();

        if ($submission->groupid) { // Group Submission.
            $filerec = $this->get_filerecord(null, collabora::FILEAREA_GROUP, $submission->groupid);
        } else {
            // We will use the userid.
            $filerec = $this->get_filerecord(null, self::FILEAREA_USER, $submission->userid);
        }

        // Delete the submission files.
        $fs->delete_area_files($filerec->contextid, $filerec->component, $filerec->filearea,
            $filerec->itemid);
    }


}
