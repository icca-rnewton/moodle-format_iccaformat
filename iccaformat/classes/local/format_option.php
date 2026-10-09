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
 * Element options helper for format_iccaformat.
 *
 * Handles all reads and writes to iccaformat_element_options.
 *
 * PERFORMANCE RULE: never call get() or get_all_for_course() inside a loop.
 * Always load the full course option set once with get_all_for_course(), then
 * use get_from_cache() per element during rendering.
 *
 * @package   format_iccaformat
 * @copyright 2026 ICCA
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace format_iccaformat\local;

defined('MOODLE_INTERNAL') || die();

/**
 * Static helper for reading and writing iccaformat_element_options rows.
 */
class format_option {

    /** @var string DB table name. */
    const TABLE = 'iccaformat_element_options';

    // Element type constants (mirrors format_iccaformat class constants).
    const ELEMENT_SECTION    = 1;
    const ELEMENT_SUBSECTION = 2;
    const ELEMENT_CM         = 3;

    // Option name constants — single source of truth for option key strings.
    const OPT_IMAGE_SOURCE        = 'imagesource';
    const OPT_IMAGE_ID            = 'imageid';
    const OPT_DESCRIPTION_DISPLAY = 'description_display';
    const OPT_LABEL_OVERRIDE      = 'label_override';
    const OPT_ICON_OVERRIDE       = 'icon_override';
    const OPT_ICON_ENABLED        = 'icon_enabled';
    const OPT_LABEL_ENABLED       = 'label_enabled';
    const OPT_ICON_LABEL_FLIPPED  = 'icon_label_flipped';

    /**
     * Load ALL element options for a course in a single DB query.
     *
     * Returns a nested array:  $cache[$elementid][$optionname] = $optionvalue
     *
     * Pass the returned array to get_from_cache() during rendering — never
     * call this method inside a section or activity loop.
     *
     * @param  int   $courseid
     * @return array Nested cache array keyed by elementid then optionname.
     */
    public static function get_all_for_course(int $courseid): array {
        global $DB;

        $rows = $DB->get_records(self::TABLE, ['courseid' => $courseid]);

        $cache = [];
        foreach ($rows as $row) {
            $cache[$row->elementid][$row->optionname] = $row->optionvalue;
        }
        return $cache;
    }

    /**
     * Retrieve a single option value from a pre-loaded cache array.
     *
     * @param  array       $cache      From get_all_for_course().
     * @param  int         $elementid  Section id or cmid.
     * @param  string      $optionname One of the OPT_* constants.
     * @param  mixed       $default    Returned when option is not set.
     * @return mixed
     */
    public static function get_from_cache(array $cache, int $elementid, string $optionname, $default = null) {
        return $cache[$elementid][$optionname] ?? $default;
    }

    /**
     * Save a single option for an element (insert or update).
     *
     * @param  int    $courseid
     * @param  int    $elementtype  One of the ELEMENT_* constants.
     * @param  int    $elementid
     * @param  string $optionname
     * @param  mixed  $optionvalue
     * @return void
     */
    public static function set(int $courseid, int $elementtype, int $elementid, string $optionname, $optionvalue): void {
        global $DB;

        $existing = $DB->get_record(self::TABLE, [
            'courseid'   => $courseid,
            'elementid'  => $elementid,
            'optionname' => $optionname,
        ]);

        $now = time();

        if ($existing) {
            $existing->optionvalue  = $optionvalue;
            $existing->timemodified = $now;
            $DB->update_record(self::TABLE, $existing);
        } else {
            $record = (object) [
                'courseid'    => $courseid,
                'elementtype' => $elementtype,
                'elementid'   => $elementid,
                'optionname'  => $optionname,
                'optionvalue' => $optionvalue,
                'timemodified' => $now,
            ];
            $DB->insert_record(self::TABLE, $record);
        }
    }

    /**
     * Delete all options for a specific element (e.g. when a section is deleted).
     *
     * @param  int $courseid
     * @param  int $elementid
     * @return void
     */

    /**
     * Delete a single option value for an element.
     */
    public static function delete(int $courseid, int $elementtype, int $elementid, string $optionname): void {
        global $DB;
        $DB->delete_records(self::TABLE, [
            'courseid'   => $courseid,
            'elementid'  => $elementid,
            'optionname' => $optionname,
        ]);
    }

    public static function delete_element(int $courseid, int $elementid): void {
        global $DB;
        $DB->delete_records(self::TABLE, [
            'courseid'  => $courseid,
            'elementid' => $elementid,
        ]);
    }

    /**
     * Delete all options for an entire course (e.g. on course deletion).
     *
     * @param  int $courseid
     * @return void
     */
    public static function delete_course(int $courseid): void {
        global $DB;
        $DB->delete_records(self::TABLE, ['courseid' => $courseid]);
    }
}
