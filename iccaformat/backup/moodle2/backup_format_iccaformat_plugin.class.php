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
 * Backup plugin for format_iccaformat.
 *
 * @package   format_iccaformat
 * @copyright 2026 Inns of Court College of Advocacy (Part of COIC)
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

/**
 * Backup plugin class for ICCA Format.
 */
class backup_format_iccaformat_plugin extends backup_format_plugin {

    /**
     * Section-level backup — section element options and image files.
     */
    protected function define_section_plugin_structure() {

        $plugin = $this->get_plugin_element();

        $sectionwrapper = new backup_nested_element(
            $this->get_recommended_name(),
            ['sectionid'],
            []
        );
        $plugin->add_child($sectionwrapper);

        $sectionwrapper->set_source_sql(
            'SELECT id AS sectionid FROM {course_sections} WHERE id = ?',
            [backup::VAR_SECTIONID]
        );

        // Section element options.
        $secoption = new backup_nested_element('secoption', ['id'], [
            'courseid', 'elementid', 'optionname', 'optionvalue',
        ]);
        $sectionwrapper->add_child($secoption);
        $secoption->set_source_table('iccaformat_element_options', [
            'courseid'  => backup::VAR_COURSEID,
            'elementid' => backup::VAR_SECTIONID,
        ]);

        // Annotate section image files.
        $sectionwrapper->annotate_files('format_iccaformat', 'element_image', 'sectionid');

        return $plugin;
    }

    /**
     * Course-level backup — CM options, label rules, library images.
     * All CM element options are backed up here to avoid section-level
     * path complexity.
     */
    protected function define_course_plugin_structure() {

        $plugin = $this->get_plugin_element();

        // ── CM element options ─────────────────────────────────────────────
        $cmoptions = new backup_nested_element('cmoptions');
        $cmoption  = new backup_nested_element('cmoption', ['id'], [
            'courseid', 'elementid', 'optionname', 'optionvalue',
        ]);
        $cmoptions->add_child($cmoption);
        $plugin->add_child($cmoptions);

        // All CM element options for this course (elementtype=3).
        $cmoption->set_source_sql(
            'SELECT eo.id, eo.courseid, eo.elementid, eo.optionname, eo.optionvalue
               FROM {iccaformat_element_options} eo
              WHERE eo.courseid = :courseid
                AND eo.elementtype = 3',
            ['courseid' => backup::VAR_COURSEID]
        );

        // Annotate CM image files — itemid = cmid.
        $cmoption->annotate_files('format_iccaformat', 'element_image', 'elementid');

        // ── Label rules ────────────────────────────────────────────────────
        $labelrules = new backup_nested_element('label_rules');
        $labelrule  = new backup_nested_element('label_rule', ['id'], [
            'courseid', 'modname', 'label', 'label_enabled',
            'icon', 'icon_enabled', 'timemodified',
        ]);
        $labelrules->add_child($labelrule);
        $plugin->add_child($labelrules);
        $labelrule->set_source_table('iccaformat_label_rules',
            ['courseid' => backup::VAR_COURSEID]);

        // ── Course-scoped library images ───────────────────────────────────
        $libraryimages = new backup_nested_element('library_images');
        $libraryimage  = new backup_nested_element('library_image', ['id'], [
            'contextid', 'name', 'description', 'filename',
            'userid', 'timecreated', 'timemodified',
        ]);
        $libraryimages->add_child($libraryimage);
        $plugin->add_child($libraryimages);
        $libraryimage->set_source_sql(
            'SELECT il.*
               FROM {iccaformat_image_library} il
               JOIN {context} ctx ON ctx.id = il.contextid
              WHERE ctx.contextlevel = ' . CONTEXT_COURSE . '
                AND ctx.instanceid = ?',
            [backup::VAR_COURSEID]
        );
        $libraryimage->annotate_files('format_iccaformat', 'library_image', 'id');

        return $plugin;
    }
}
