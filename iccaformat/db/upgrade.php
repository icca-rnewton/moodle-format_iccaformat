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
 * Upgrade steps for format_iccaformat.
 *
 * @package   format_iccaformat
 * @copyright 2026 Inns of Court College of Advocacy (Part of COIC)
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

function xmldb_format_iccaformat_upgrade($oldversion) {
    global $DB;
    $dbman = $DB->get_manager();

    if ($oldversion < 2026060301) {
        $table = new xmldb_table('iccaformat_label_rules');
        $field = new xmldb_field('flipped', XMLDB_TYPE_INTEGER, '1', null, XMLDB_NOTNULL, null, '0', 'label_enabled');
        if (!$dbman->field_exists($table, $field)) {
            $dbman->add_field($table, $field);
        }
        upgrade_plugin_savepoint(true, 2026060301, 'format', 'iccaformat');
    }

    if ($oldversion < 2026060401) {
        $table = new xmldb_table('iccaformat_label_rules');
        $field = new xmldb_field('image_hidden', XMLDB_TYPE_INTEGER, '1', null, XMLDB_NOTNULL, null, '0', 'flipped');
        if (!$dbman->field_exists($table, $field)) {
            $dbman->add_field($table, $field);
        }
        upgrade_plugin_savepoint(true, 2026060401, 'format', 'iccaformat');
    }

    if ($oldversion < 2026102100) {
        // Section ids and cmids are separate sequences, so one number can be both a
        // section and an activity in the same course. Make elementtype part of the
        // unique key. Existing rows cannot violate the wider index (they already
        // satisfied the narrower one), so this is a straight index swap.
        $table    = new xmldb_table('iccaformat_element_options');
        $oldindex = new xmldb_index('courseid-elementid-optionname', XMLDB_INDEX_UNIQUE,
            ['courseid', 'elementid', 'optionname']);
        if ($dbman->index_exists($table, $oldindex)) {
            $dbman->drop_index($table, $oldindex);
        }
        $newindex = new xmldb_index('uniq-element-option', XMLDB_INDEX_UNIQUE,
            ['courseid', 'elementtype', 'elementid', 'optionname']);
        if (!$dbman->index_exists($table, $newindex)) {
            $dbman->add_index($table, $newindex);
        }

        // Activity direct-upload images move from element_image to cm_image so they
        // can no longer share an itemid with a section's image. Re-runnable: files
        // already moved are gone from element_image; ambiguous ids are left in place.
        $courseids = $DB->get_fieldset_sql(
            "SELECT DISTINCT ctx.instanceid
               FROM {files} f
               JOIN {context} ctx ON ctx.id = f.contextid AND ctx.contextlevel = ?
              WHERE f.component = ? AND f.filearea = ? AND f.filename <> ?",
            [CONTEXT_COURSE, 'format_iccaformat', 'element_image', '.']);
        foreach ($courseids as $courseid) {
            $result = \format_iccaformat\local\format_option::migrate_legacy_cm_files((int) $courseid);
            if ($result['ambiguous']) {
                debugging("format_iccaformat: course {$courseid} has {$result['ambiguous']} card image(s) " .
                    "on an id that is both a section and an activity; left in place.", DEBUG_NORMAL);
            }
        }

        upgrade_plugin_savepoint(true, 2026102100, 'format', 'iccaformat');
    }

    return true;
}