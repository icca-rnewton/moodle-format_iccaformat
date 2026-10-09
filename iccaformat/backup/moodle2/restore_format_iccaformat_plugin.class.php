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
 * Restore plugin for format_iccaformat.
 *
 * @package   format_iccaformat
 * @copyright 2026 Inns of Court College of Advocacy (Part of COIC)
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

/**
 * Restore plugin class for ICCA Format.
 */
class restore_format_iccaformat_plugin extends restore_format_plugin {

    /**
     * Section-level restore paths.
     */
    public function define_section_plugin_structure() {
        return [
            new restore_path_element(
                'iccaformat_section_wrapper',
                $this->get_pathfor('/')
            ),
            new restore_path_element(
                'iccaformat_secoption',
                $this->get_pathfor('/secoption')
            ),

        ];
    }

    /**
     * Course-level restore paths.
     */
    public function define_course_plugin_structure() {
        return [
            new restore_path_element(
                'iccaformat_cmoption',
                '/course/plugin_format_iccaformat_course/cmoptions/cmoption'
            ),
            new restore_path_element(
                'iccaformat_label_rule',
                '/course/plugin_format_iccaformat_course/label_rules/label_rule'
            ),
            new restore_path_element(
                'iccaformat_library_image',
                '/course/plugin_format_iccaformat_course/library_images/library_image'
            ),
        ];
    }

    /**
     * Process section wrapper — register file mapping for this section.
     */
    public function process_iccaformat_section_wrapper($data) {
        $data         = (object) $data;
        $oldsectionid = (int) ($data->sectionid ?? 0);
        $newsectionid = $this->task->get_sectionid();

        if ($oldsectionid && $newsectionid) {
            $this->set_mapping('iccaformat_section', $oldsectionid, $newsectionid, true);
        }
    }

    /**
     * Restore a section element option row.
     */
    public function process_iccaformat_secoption($data) {
        global $DB;

        $data         = (object) $data;
        $courseid     = $this->task->get_courseid();
        $newsectionid = $this->task->get_sectionid();

        if (!$newsectionid || $data->optionvalue === null || $data->optionvalue === '') {
            return;
        }

        if ($DB->record_exists('iccaformat_element_options', [
            'courseid'   => $courseid,
            'elementid'  => $newsectionid,
            'optionname' => $data->optionname,
        ])) {
            return;
        }

        $sectioncomponent = $DB->get_field('course_sections', 'component', ['id' => $newsectionid]);
        $elementtype      = ($sectioncomponent === 'mod_subsection') ? 2 : 1;

        $DB->insert_record('iccaformat_element_options', [
            'courseid'    => $courseid,
            'elementid'   => $newsectionid,
            'elementtype' => $elementtype,
            'optionname'  => $data->optionname,
            'optionvalue' => $data->optionvalue,
            'timecreated' => time(),
            'timemodified'=> time(),
        ]);
    }

    /**
     * Restore a CM element option row.
     * Now at course level so CM mappings already exist.
     */
    public function process_iccaformat_cmoption($data) {
        global $DB;

        $data     = (object) $data;
        $courseid = $this->get_courseid();
        $oldcmid  = (int) $data->elementid;

        if ($data->optionvalue === null || $data->optionvalue === '') {
            return;
        }

        $newcmid = $this->get_mappingid('course_module', $oldcmid);
        if (!$newcmid) {
            return;
        }

        // Register file mapping old cmid -> new cmid.
        $this->set_mapping('iccaformat_cm', $oldcmid, $newcmid, true);

        if ($DB->record_exists('iccaformat_element_options', [
            'courseid'   => $courseid,
            'elementid'  => $newcmid,
            'optionname' => $data->optionname,
        ])) {
            return;
        }

        $DB->insert_record('iccaformat_element_options', [
            'courseid'    => $courseid,
            'elementid'   => $newcmid,
            'elementtype' => 3,
            'optionname'  => $data->optionname,
            'optionvalue' => $data->optionvalue,
            'timecreated' => time(),
            'timemodified'=> time(),
        ]);
    }

    /**
     * Restore label rule.
     */
    public function process_iccaformat_label_rule($data) {
        global $DB;

        $data           = (object) $data;
        $data->courseid = $this->task->get_courseid();

        if ($DB->record_exists('iccaformat_label_rules', [
            'courseid' => $data->courseid,
            'modname'  => $data->modname,
        ])) {
            return;
        }

        unset($data->id);
        $data->timemodified = time();
        $DB->insert_record('iccaformat_label_rules', $data);
    }

    /**
     * Restore library image record.
     */
    public function process_iccaformat_library_image($data) {
        global $DB;

        $data   = (object) $data;
        $oldid  = $data->id;

        $data->contextid    = \context_course::instance($this->task->get_courseid())->id;
        $data->timecreated  = time();
        $data->timemodified = time();

        if (!$DB->record_exists('user', ['id' => $data->userid])) {
            $data->userid = $this->task->get_userid();
        }

        unset($data->id);
        $newid = $DB->insert_record('iccaformat_image_library', $data);
        $this->set_mapping('iccaformat_library_image', $oldid, $newid, true);
    }

    /**
     * After section restore — restore section image files only.
     * CM files are deferred to after_restore_course().
     */
    public function after_restore_section() {
        $this->add_related_files('format_iccaformat', 'element_image', 'iccaformat_section');
    }

    /**
     * After course restore — restore CM and library image files.
     */
    public function after_restore_course() {
        $this->add_related_files('format_iccaformat', 'element_image', 'iccaformat_cm');
        $this->add_related_files('format_iccaformat', 'library_image', 'iccaformat_library_image');
    }
}
