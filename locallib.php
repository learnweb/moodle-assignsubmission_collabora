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
 * The assign_submission_file class.
 *
 * @package assignsubmission_collabora
 * @copyright 2019 Benjamin Ellis, Synergy Learning
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

use assignsubmission_collabora\api\collabora_fs;
use assignsubmission_collabora\util;
use mod_collabora\util as collabora_util;

/**
 * Library class for collabora submission plugin extending submission plugin base class.
 *
 * @package assignsubmission_collabora
 * @copyright 2012 NetSpot {@link http://www.netspot.com.au}
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class assign_submission_collabora extends assign_submission_plugin {
    /**
     * Get a list of file areas associated with the plugin configuration.
     * This is used for backup/restore.
     *
     * @return array names of the fileareas, can be an empty array
     */
    public function get_config_file_areas() {
        return [collabora_fs::FILEAREA_INITIAL];
    }

    /**
     * Function to return the file record object.
     *
     * @param  string   $filename - might be empty to be filled by caller
     * @param  string   $filearea - the file area - default is the initial files area
     * @param  int      $itemid   - usually the user or group id excpet for initial files
     * @param  string   $filepath - we don't use this for our plugin but might do in the future
     * @return StdClass
     */
    public function get_filerecord($filename = null, $filearea = null, $itemid = 0, $filepath = '/') {
        if (empty($filearea)) {
            $filearea = collabora_fs::FILEAREA_INITIAL;
        }
        $contextid = $this->assignment->get_context()->id;

        return (object) [
            'contextid' => $contextid,
            'component' => 'assignsubmission_collabora',
            'filearea'  => $filearea,
            'itemid'    => $itemid,
            'filepath'  => $filepath,
            'filename'  => $filename ? clean_filename($filename) : $filename,
        ];
    }

    /**
     * Get the file link to the document as an html fragment.
     *
     * @return string
     */
    private function get_initial_file_link() {
        $fs    = get_file_storage();
        $files = $fs->get_area_files(
            $this->assignment->get_context()->id, // Param contextid.
            'assignsubmission_collabora', // Param component.
            collabora_fs::FILEAREA_INITIAL, // Param filearea.
            false, // Param itemid.
            'filename', // Param sort.
            false, // Param includedirs.
            0, // Param updatedsince.
            0, // Param limitfrom.
            1 // Param limitnum.
        );
        $file = reset($files);
        if (!$file) {
            return get_string('missingfile', 'mod_collabora');
        }
        $url = moodle_url::make_pluginfile_url(
            $file->get_contextid(),
            $file->get_component(),
            $file->get_filearea(),
            $file->get_itemid(),
            $file->get_filepath(),
            $file->get_filename(),
            true
        );

        return html_writer::link($url, $file->get_filename());
    }

    /**
     * Get file submission information from the database.
     *
     * @param  int   $submissionid
     * @return mixed
     */
    private function get_file_submission($submissionid) {
        global $DB;

        return $DB->get_record('assignsubmission_collabora', ['submission' => $submissionid]);
    }

    /**
     * Get the Initial file to be copied into the relevant filearea.
     *
     * @param  file_storage|null $fs
     * @return stored_file
     */
    private function get_initial_file(?file_storage $fs = null) {
        $filerec = $this->get_filerecord();
        if (null === $fs) {
            $fs = get_file_storage();
        }
        $files = $fs->get_area_files(
            $filerec->contextid,
            $filerec->component,
            $filerec->filearea,
            $filerec->itemid,
            null,
            false,
            0,
            0,
            1
        );
        $file = reset($files);

        return $file;
    }

    /**
     * Returns a link to the initial or submitted file.
     *
     * NOT currently used but might be in a later version - NB the plugin_file function will need ...
     * ... to be implemented in lib.php
     *
     * @return string error | html link
     */
    private function get_file_link() {
        $file = $this->get_initial_file();
        if ($file) {
            $url = moodle_url::make_pluginfile_url(
                $file->get_contextid(),
                $file->get_component(),
                $file->get_filearea(),
                $file->get_itemid(),
                $file->get_filepath(),
                $file->get_filename(),
                true
            );

            return html_writer::link($url, $file->get_filename());
        }

        return get_string('missingfile', 'assignsubmission_collabora');
    }

    /**
     * Unset the configuration settings.
     *
     * Setting all the config settings to null values - there is no way to delete config settings.
     *
     * @param  string $setting - a config setting name
     * @return bool   - always true until we do the submissions check
     */
    private function unset_config($setting = null) {
        if (null === $setting) {
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
     * Return the view url wrapped in the html frame.
     *
     * @param  stdClass    $submission
     * @param  stored_file $submissionfile - the file object
     * @param  int         $userid
     * @param  bool        $forcereadonly  - If the file should be readonly
     * @return string      - HTML
     */
    private function get_view_htmlframe($submission, $submissionfile, $userid, $forcereadonly = false) {
        global $DB, $OUTPUT, $PAGE;

        $user = $DB->get_record('user', ['id' => $userid], '*', MUST_EXIST);

        $config      = $this->get_config();
        $collaborafs = new collabora_fs($user, $submissionfile);
        if ($forcereadonly) {
            $collaborafs->force_readonly();
        }

        $viewurl = $collaborafs->get_view_url();
        $widget  = new assignsubmission_collabora\output\content(
            $submission->id,
            $submissionfile->get_filename(),
            $viewurl,
            $config
        );

        // If we are in testing mode, we have to modify the file because there is no real collabora to do that.
        if (collabora_fs::is_testing()) {
            $user            = $DB->get_record('user', ['id' => $userid]);
            $collaborafstest = new collabora_fs($user, $submissionfile);
            $collaborafstest->update_file(random_string(32));
        }

        $opts = [
            'id'              => $submission->id,
            'component'       => 'assignsubmission_collabora',
            'contextid'       => $submissionfile->get_contextid(),
            'collaboraurl'    => $collaborafs->get_collabora_url()->out(),
            'origincollabora' => $collaborafs->get_collabora_origin(),
            'originmoodle'    => $collaborafs->get_moodle_origin(),
            'courseurl'       => '', // The course url only is used in mod_collabora.
            'aspopup'         => false,
            'iframeid'        => 'collaboraiframe_' . $submission->id,
            'versionviewerid' => 'version_viewer_' . $submission->id,
            'versionmanager'  => false,
            'strback'         => '', // This only is used in mod_collabora.
            'imgbackurl'      => '', // This only is used in mod_collabora.
            'uimode'          => $collaborafs->get_ui_mode(),
        ];
        $PAGE->requires->js_call_amd('mod_collabora/postmessage', 'init', [$opts]);

        return $OUTPUT->render($widget);
    }

    /**
     * To ensure we always only deal with our own config settings.
     *
     * @return stdClass - config object
     */
    private function get_thisplugin_config() {
        $configkeys = [
            'format',
            'height',
            'initialtext',
            'filename',
        ];
        $thisplugincfg      = [];
        $assignpluginconfig = (array) $this->get_config();

        foreach ($assignpluginconfig as $assignsetting => $settingvalue) {
            if (in_array($assignsetting, $configkeys)) {
                $thisplugincfg[$assignsetting] = $settingvalue;
            }
        }

        return (object) $thisplugincfg; // Object to keep it consistent.
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
     * Get the default setting for submission plugin.
     *
     * @param  MoodleQuickForm $mform - The form to add elements to
     * @return void
     */
    public function get_settings(MoodleQuickForm $mform) {
        if ($this->assignment->has_instance()) {
            $pluginconfig = (array) $this->get_config();
        } else {
            $pluginconfig = [];
        }

        // Use the module's configuration as well as our own.
        $config = (object) array_merge(
            (array) get_config('mod_collabora'),
            (array) get_config('assignsubmission_collabora'),
            $pluginconfig
        );

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

        $filemanageropts = util::get_default_fileoptions();

        // Format section.
        if (!$isexisting) {
            $mform->addElement(
                'selectgroups',
                'assignsubmission_collabora_format',
                get_string('format', 'assignsubmission_collabora'),
                collabora_util::grouped_format_menu()
            );
            $defaultformat = $config->defaultformat ?? collabora_util::FORMAT_WORDPROCESSOR;
            $mform->setDefault('assignsubmission_collabora_format', $defaultformat);
        } else {
            $defaultformat = empty($config->format) ? $config->defaultformat : $config->format;
            $mform->addElement(
                'hidden',
                'assignsubmission_collabora_format',
                $defaultformat
            );
            $mform->setType('assignsubmission_collabora_format', PARAM_RAW);
            $mform->setConstant('assignsubmission_collabora_format', $defaultformat);
        }
        $mform->hideif('assignsubmission_collabora_format', 'assignsubmission_collabora_enabled', 'notchecked');

        // Text File - initial text.
        if (!$isexisting || $config->format === collabora_util::FORMAT_TEXT) {
            $mform->addElement(
                'textarea',
                'assignsubmission_collabora_initialtext',
                get_string('initialtext', 'assignsubmission_collabora')
            );
            $mform->hideif(
                'assignsubmission_collabora_initialtext',
                'assignsubmission_collabora_format',
                'neq',
                collabora_util::FORMAT_TEXT
            );
            $mform->setDefault(
                'assignsubmission_collabora_initialtext',
                empty($config->initialtext) ? '' : $config->initialtext
            );
            if ($isexisting) {
                $mform->freeze('assignsubmission_collabora_initialtext');
            }
            $mform->hideif(
                'assignsubmission_collabora_initialtext',
                'assignsubmission_collabora_enabled',
                'notchecked'
            );
        }

        // Filename Requirement - added in the name for FORMAT_TEXT as we still need a name.
        if (!$isexisting) {
            $mform->addElement(
                'text',
                'assignsubmission_collabora_filename',
                get_string('filename', 'assignsubmission_collabora'),
                ['size' => '60']
            );
            $mform->setDefault('assignsubmission_collabora_filename', '');
            $mform->setType('assignsubmission_collabora_filename', PARAM_FILE);
            $mform->hideif(
                'assignsubmission_collabora_filename',
                'assignsubmission_collabora_format',
                'eq',
                collabora_util::FORMAT_UPLOAD
            );
            if ($isexisting) {
                $mform->freeze('assignsubmission_collabora_filename');
            }
            $mform->hideif(
                'assignsubmission_collabora_filename',
                'assignsubmission_collabora_enabled',
                'notchecked'
            );
        }

        // File Manager section.
        if (!$isexisting) {
            $mform->addElement(
                'filemanager',
                'assignsubmission_collabora_initialfile_filemanager',
                get_string('initialfile', 'assignsubmission_collabora'),
                null,
                $filemanageropts
            );
            $mform->hideif(
                'assignsubmission_collabora_initialfile_filemanager',
                'assignsubmission_collabora_format',
                'neq',
                collabora_util::FORMAT_UPLOAD
            );
            $mform->hideif(
                'assignsubmission_collabora_initialfile_filemanager',
                'assignsubmission_collabora_enabled',
                'notchecked'
            );
        } else {
            $mform->addElement('static', 'initialfile', get_string('initialfile', 'mod_collabora'), $this->get_initial_file_link());
        }

        // Height.
        $mform->addElement('text', 'assignsubmission_collabora_height', get_string('height', 'assignsubmission_collabora'));
        $mform->setDefault('assignsubmission_collabora_height', 0);
        $mform->setType('assignsubmission_collabora_height', PARAM_INT);
        $mform->hideif('assignsubmission_collabora_height', 'assignsubmission_collabora_enabled', 'notchecked');
    }

    /**
     * Save the settings for submission plugin.
     *
     * @param  stdClass $data - the form data
     * @return bool     - on error the subtype should call set_error and return false
     */
    public function save_settings(stdClass $data) {
        $noerror = true; // Track input errors.

        // Get our own local config settings - if we do not have any settings - this is a new record.
        $newrecord   = false;
        $localconfig = $this->get_thisplugin_config();
        if (empty($localconfig->format)) {
            $newrecord = true;
        }

        // Format cannot be empty as it is in the select - always data available -if enabled.
        $this->set_config('format', $data->assignsubmission_collabora_format);
        // Height never empty - required for all formats.
        $this->set_config('height', $data->assignsubmission_collabora_height);

        /*
         * We can save new settings.
         */
        if ($newrecord) {
            // Work out the filename for all types except Uploads.
            $filename = trim(clean_filename($data->assignsubmission_collabora_filename));
            if (!$filename) {
                $filename = util::generaterandonfilename();
            }
            if ($noerror) {
                $this->set_config('filename', $filename);
            }

            // Create the initial file.
            if ($noerror) {
                $filerec = $this->get_filerecord($filename);
                if ($data->assignsubmission_collabora_format == collabora_util::FORMAT_TEXT) {
                    if (empty($data->assignsubmission_collabora_initialtext)) {
                        $data->assignsubmission_collabora_initialtext = '';
                    }
                    $this->set_config('initialtext', $data->assignsubmission_collabora_initialtext);
                }
                if (!util::store_initial_file($filerec, $data)) {
                    throw new moodle_exception(
                        'couldnotstoreinitialfile',
                        'assignsubmission_collabora',
                        '',
                        $data->assignsubmission_collabora_format
                    );
                }
            }
        }

        return $noerror;
    }

    /**
     * View the submission summary and sets whether a view link be provided.
     *
     * @param  stdClass $submission
     * @param  bool     $showviewlink - whether or not to have a link to view the submission file
     * @return string   view text
     */
    public function view_summary(stdClass $submission, &$showviewlink) {
        global $USER, $DB;
        if (!empty($submission)) {
            $submission = $DB->get_record('assign_submission', ['id' => $submission->id]);
        }
        $showviewlink = false;      // Default do not show view link.
        if (self::is_empty($submission)) {
            return get_string('nosubmission', 'assignsubmission_collabora');
        }
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
        }

        return ucfirst($submission->status);
    }

    /**
     * Save any custom data for this form submission.
     *
     * We do not have to save anything as it will have happened in the callback from the Collabora frame.
     * We will however generate an event for the save.
     *
     * @param  stdClass $submission
     * @param  stdClass $data       - the data submitted from the form
     * @return bool     - on error then we set error and return false
     */
    public function save(stdClass $submission, stdClass $data) {
        global $USER, $DB;

        $filesubmission = $this->get_file_submission($submission->id);

        // The assessable_uploaded event.
        $params = [
            'context'  => context_module::instance($this->assignment->get_course_module()->id),
            'courseid' => $this->assignment->get_course()->id,
            'objectid' => $submission->id,
            'other'    => [
                'content'        => '',
                'pathnamehashes' => [
                    $data->submpathnamehash,
                ],
            ],
        ];
        if (!empty($submission->userid) && ($submission->userid != $USER->id)) {
            $params['relateduserid'] = $submission->userid;
        } else {
            $params['userid'] = $submission->userid;
        }
        $event = assignsubmission_file\event\assessable_uploaded::create($params);
        $event->trigger();

        // BORROWED from file submission code.
        $groupname = null;
        $groupid   = 0;
        // Get the group name as other fields are not transcribed in the logs and this information is important.
        if (empty($submission->userid) && !empty($submission->groupid)) {
            $groupname = $DB->get_field('groups', 'name', ['id' => $submission->groupid], MUST_EXIST);
            $groupid   = $submission->groupid;
        } else {
            $params['relateduserid'] = $submission->userid;
        }
        // End BORROWED.

        // Unset the objectid and other field from params for use in submission events.
        unset($params['objectid'], $params['other']); // We do not use this as we do not have a separate table.

        $params['other'] = [
            'submissionid'       => $submission->id,
            'submissionattempt'  => $submission->attemptnumber,
            'submissionstatus'   => $submission->status,
            'submissionfilename' => $data->submfilename,
            'submpathnamehash'   => $data->submpathnamehash,
            'groupid'            => $groupid,
            'groupname'          => $groupname,
        ];

        if ($filesubmission) {
            // An updated submission.
            /** @var assignsubmission_collabora\event\submission_updated $event */
            $event = assignsubmission_collabora\event\submission_updated::create($params);
        } else {
            // A new submission.
            $filesubmission             = new stdClass();
            $filesubmission->numfiles   = 1; // We allways have only one file for a submission.
            $filesubmission->submission = $submission->id;
            $filesubmission->assignment = $this->assignment->get_instance()->id;
            $filesubmission->id         = $DB->insert_record('assignsubmission_collabora', $filesubmission);

            /** @var assignsubmission_collabora\event\submission_created $event */
            $event = assignsubmission_collabora\event\submission_created::create($params);
        }
        $event->set_assign($this->assignment);
        $event->trigger();

        return true;
    }

    /**
     * Produce a list of files suitable for export that represent this submission.
     *
     * @param  stdClass $submission
     * @param  stdClass $user
     * @return array    - return an array of files indexed by filename
     */
    public function get_files(stdClass $submission, stdClass $user) {
        $result = [];

        $fs      = get_file_storage();
        $filerec = $this->get_filerecord(null, collabora_fs::FILEAREA_SUBMIT, $submission->id);

        $files = $fs->get_area_files(
            $filerec->contextid,
            $filerec->component,
            $filerec->filearea,
            $filerec->itemid,
            '',
            false,
            0,
            0,
            1
        );
        if ($file = reset($files)) {
            // Do we return the full folder path or just the file name?
            if (isset($submission->exportfullpath) && $submission->exportfullpath == false) {
                $result[$file->get_filename()] = $file;
            } else {
                $result[$file->get_filepath() . $file->get_filename()] = $file;
            }
        }

        return $result;
    }

    /**
     * Get any additional fields for the submission form for this assignment.
     *
     * @param  stdClass        $submission
     * @param  MoodleQuickForm $mform
     * @param  stdClass        $data       - the form data
     * @param  int             $userid
     * @return bool            - true if we added anything to the form
     */
    public function get_form_elements($submission, MoodleQuickForm $mform, stdClass $data, $userid = null) {
        global $USER;

        if (null === $userid) {
            $userid = empty($data->userid) ? $USER->id : $data->userid;
        }

        // Get or Create our submission file base record.
        $fs      = get_file_storage();
        $filerec = $this->get_filerecord(null, collabora_fs::FILEAREA_SUBMIT, $submission->id);

        // For now we check for the submission file existance 1st.
        $isnewsubmission = 0;
        $files           = $fs->get_area_files(
            $filerec->contextid,
            $filerec->component,
            $filerec->filearea,
            $filerec->itemid,
            '',
            false,
            0,
            0,
            1
        );
        if (!$submissionfile = reset($files)) {
            // Get the initial file to copy.
            if ($initialfile = $this->get_initial_file($fs)) {
                $filerec->filename = $initialfile->get_filename();
                $submissionfile    = $fs->create_file_from_storedfile($filerec, $initialfile);
                $isnewsubmission   = 1;
            } else {
                // Should never happen.
                throw new coding_exception('Missing Initial File.');
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
        $mform->addElement(
            'static',
            'warning',
            get_string('formsavewarmingpmt', 'assignsubmission_collabora'),
            get_string('formsavewarming', 'assignsubmission_collabora')
        );

        return true;
    }

    /**
     * View submission - the submission file will always be read only.
     *
     * @param  stdClass $submission
     * @return string   - html frame of the submitted file
     */
    public function view(stdClass $submission) {
        global $USER;

        // Get or Create our submission file base record.
        $fs      = get_file_storage();
        $filerec = $this->get_filerecord(null, collabora_fs::FILEAREA_SUBMIT, $submission->id);

        $files = $fs->get_area_files(
            $filerec->contextid,
            $filerec->component,
            $filerec->filearea,
            $filerec->itemid,
            '',
            false,
            0,
            0,
            1
        );
        if (!$submissionfile = reset($files)) {
            // Should never happen.
            throw new coding_exception('Missing Submission File.');
        }
        // All calls through here must be readonly.
        $forcereadonly = true;

        $userid = $USER->id;
        $html   = $this->get_view_htmlframe($submission, $submissionfile, $userid, $forcereadonly);

        return $html;
    }

    /**
     * Return true if this plugin can upgrade an old Moodle 2.2 assignment of this type
     * and version.
     *
     * @param  string $type    The old assignment subtype
     * @param  int    $version The old assignment version
     * @return bool   True if upgrade is possible
     */
    public function can_upgrade($type, $version) {
        return false;
    }

    /**
     * Get file areas returns a list of areas this plugin stores files.
     *
     * @return array - An array of fileareas(keys) and descriptions(values)
     */
    public function get_file_areas() {
        return [
            collabora_fs::FILEAREA_INITIAL => ucfirst(
                get_string('fileareadesc', 'assignsubmission_collabora', collabora_fs::FILEAREA_INITIAL)
            ),
            collabora_fs::FILEAREA_SUBMIT => ucfirst(
                get_string('fileareadesc', 'assignsubmission_collabora', collabora_fs::FILEAREA_SUBMIT)
            ),
        ];
    }

    /**
     * Copy the plugin specific submission data to a new submission record.
     *
     * @param  stdClass $submission    - Old submission record
     * @param  stdClass $newsubmission - New submission record
     * @return bool
     */
    public function copy_submission(stdClass $submission, stdClass $newsubmission) {
        global $DB;

        $fs      = get_file_storage();
        $filerec = $this->get_filerecord(null, collabora_fs::FILEAREA_SUBMIT, $submission->id);

        $files = $fs->get_area_files(
            $filerec->contextid,
            $filerec->component,
            $filerec->filearea,
            $filerec->itemid,
            '',
            false,
            0,
            0,
            1
        );
        if ($file = reset($files)) {
            $fieldupdates = ['itemid' => $newsubmission->id];
            $fs->create_file_from_storedfile($fieldupdates, $file);
        }

        return true; // File will always remain the same.
    }

    /**
     * The assignment has been deleted - remove the plugin specific data.
     *
     * @return bool
     */
    public function delete_instance() {
        global $DB;
        $this->unset_config();
        $DB->delete_records('assignsubmission_collabora', ['assignment' => $this->assignment->get_instance()->id]);

        return true;
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
     * Is this assignment plugin empty?
     * We check whether or not a submission file exists
     * and if so whether or not the submission file is the same as the initial file.
     *
     * @param  stdClass $submission assign_submission
     * @return bool
     */
    public function is_empty(stdClass $submission) {
        $fs      = get_file_storage();
        $filerec = $this->get_filerecord(null, collabora_fs::FILEAREA_SUBMIT, $submission->id);

        $files = $fs->get_area_files(
            $filerec->contextid,
            $filerec->component,
            $filerec->filearea,
            $filerec->itemid,
            '',
            false,
            0,
            0,
            1
        );
        if (count($files) == 0) { // No file yet.
            return true;
        }
        $file        = array_pop($files);
        $initialfile = $this->get_initial_file($fs);

        return $file->get_contenthash() == $initialfile->get_contenthash();
    }

    /**
     * Determine if a submission is empty before saving.
     * Because the user file is saved in the background by collabora api
     * we can check whether or not the content differs from the initial file. So in fact we do the same as ::is_empty().
     *
     * @param  stdClass $data The submission data
     * @return bool
     */
    public function submission_is_empty(stdClass $data) {
        if (empty($data->id)) {
            return true;
        }

        [$course, $cm] = get_course_and_cm_from_cmid($data->id, 'assign');
        $context       = context_module::instance($cm->id);
        $assign        = new assign($context, $cm, $course);

        if (!empty($assign->get_instance($data->userid)->teamsubmission)) {
            $submission = $assign->get_group_submission($data->userid, 0, false);
        } else {
            $submission = $assign->get_user_submission($data->userid, false);
        }

        return $this->is_empty($submission);
    }

    /**
     * Remove any saved data from this submission.
     *
     * @since Moodle 3.6
     * @param  stdClass $submission - assign_submission data
     * @return void
     */
    public function remove(stdClass $submission) {
        global $DB;

        $fs      = get_file_storage();
        $filerec = $this->get_filerecord(null, collabora_fs::FILEAREA_SUBMIT, $submission->id);

        // Delete the submission files.
        $fs->delete_area_files(
            $filerec->contextid,
            $filerec->component,
            $filerec->filearea,
            $filerec->itemid
        );

        $DB->delete_records('assignsubmission_collabora', ['submission' => $submission->id]);
    }
}
