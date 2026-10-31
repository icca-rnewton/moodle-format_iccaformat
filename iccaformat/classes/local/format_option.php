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

    // Namespaces. Section and course-module ids come from separate sequences,
    // so the same number can be both a section and an activity in one course.
    // Sections and subsections share one namespace (both are course_sections ids);
    // activities have their own.
    const NS_SECTION = 'section';
    const NS_CM      = 'cm';

    // Direct-upload file areas, one per namespace, so itemids can never collide.
    const FILEAREA_SECTION = 'element_image';
    const FILEAREA_CM      = 'cm_image';

    /**
     * Namespace for an element type.
     *
     * @param  int $elementtype One of the ELEMENT_* constants.
     * @return string NS_SECTION or NS_CM.
     */
    public static function ns(int $elementtype): string {
        return ($elementtype === self::ELEMENT_CM) ? self::NS_CM : self::NS_SECTION;
    }

    /**
     * Direct-upload file area for an element type.
     *
     * @param  int $elementtype One of the ELEMENT_* constants.
     * @return string File area name.
     */
    public static function filearea(int $elementtype): string {
        return ($elementtype === self::ELEMENT_CM) ? self::FILEAREA_CM : self::FILEAREA_SECTION;
    }

    /**
     * SQL fragment + params restricting rows to the namespace of $elementtype.
     *
     * @param  int $elementtype
     * @return array [string $sql, array $params]
     */
    private static function type_condition(int $elementtype): array {
        if (self::ns($elementtype) === self::NS_CM) {
            return ['elementtype = ?', [self::ELEMENT_CM]];
        }
        return ['elementtype IN (?, ?)', [self::ELEMENT_SECTION, self::ELEMENT_SUBSECTION]];
    }

    /**
     * Load ALL element options for a course in a single query.
     *
     * Returns a nested array:  $cache[$namespace][$elementid][$optionname] = $optionvalue
     *
     * Pass the returned array to get_from_cache() during rendering — never
     * call this method inside a section or activity loop.
     *
     * @param  int   $courseid
     * @return array Nested cache array keyed by namespace, elementid, then optionname.
     */
    public static function get_all_for_course(int $courseid): array {
        global $DB;

        $rows = $DB->get_records(self::TABLE, ['courseid' => $courseid]);

        $cache = [self::NS_SECTION => [], self::NS_CM => []];
        foreach ($rows as $row) {
            $cache[self::ns((int) $row->elementtype)][$row->elementid][$row->optionname] = $row->optionvalue;
        }
        return $cache;
    }

    /**
     * Retrieve a single option value from a pre-loaded cache array.
     *
     * @param  array       $cache       From get_all_for_course().
     * @param  int         $elementtype One of the ELEMENT_* constants (SECTION and SUBSECTION are equivalent here).
     * @param  int         $elementid   Section id or cmid.
     * @param  string      $optionname  One of the OPT_* constants.
     * @param  mixed       $default     Returned when option is not set.
     * @return mixed
     */
    public static function get_from_cache(array $cache, int $elementtype, int $elementid,
            string $optionname, $default = null) {
        return $cache[self::ns($elementtype)][$elementid][$optionname] ?? $default;
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

        [$typesql, $typeparams] = self::type_condition($elementtype);
        $existing = $DB->get_records_select(self::TABLE,
            'courseid = ? AND elementid = ? AND optionname = ? AND ' . $typesql,
            array_merge([$courseid, $elementid, $optionname], $typeparams),
            'id ASC', '*', 0, 1);
        $existing = $existing ? reset($existing) : null;

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
     * Delete a single option value for an element.
     *
     * @param  int    $courseid
     * @param  int    $elementtype One of the ELEMENT_* constants.
     * @param  int    $elementid
     * @param  string $optionname
     * @return void
     */
    public static function delete(int $courseid, int $elementtype, int $elementid, string $optionname): void {
        global $DB;
        [$typesql, $typeparams] = self::type_condition($elementtype);
        $DB->delete_records_select(self::TABLE,
            'courseid = ? AND elementid = ? AND optionname = ? AND ' . $typesql,
            array_merge([$courseid, $elementid, $optionname], $typeparams));
    }

    /**
     * Delete all options for a specific element (e.g. when a section is deleted).
     *
     * @param  int $courseid
     * @param  int $elementtype One of the ELEMENT_* constants.
     * @param  int $elementid
     * @return void
     */
    public static function delete_element(int $courseid, int $elementtype, int $elementid): void {
        global $DB;
        [$typesql, $typeparams] = self::type_condition($elementtype);
        $DB->delete_records_select(self::TABLE,
            'courseid = ? AND elementid = ? AND ' . $typesql,
            array_merge([$courseid, $elementid], $typeparams));
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

    /**
     * Move activity direct-upload images out of the shared legacy file area.
     *
     * Before 2026102100 both sections and activities stored direct uploads in
     * element_image with itemid = sectionid or cmid. Activity files now live in
     * cm_image. A file is moved only when its itemid is an activity in this course
     * and NOT also a section in this course — an id that is both is ambiguous and
     * is left in place (and counted) rather than guessed at.
     *
     * Safe to re-run: a file already present in cm_image is not duplicated.
     * Used by the upgrade step and by restore of backups made before the split.
     *
     * @param  int $courseid
     * @return array ['moved' => int, 'ambiguous' => int]
     */
    public static function migrate_legacy_cm_files(int $courseid): array {
        global $DB;

        $result  = ['moved' => 0, 'ambiguous' => 0];
        $context = \context_course::instance($courseid, IGNORE_MISSING);
        if (!$context) {
            return $result;
        }

        $fs    = get_file_storage();
        $files = $fs->get_area_files($context->id, 'format_iccaformat', self::FILEAREA_SECTION, false, 'itemid', false);
        if (!$files) {
            return $result;
        }

        $cmids      = $DB->get_fieldset_select('course_modules', 'id', 'course = ?', [$courseid]);
        $sectionids = $DB->get_fieldset_select('course_sections', 'id', 'course = ?', [$courseid]);
        $cmids      = array_flip(array_map('intval', $cmids));
        $sectionids = array_flip(array_map('intval', $sectionids));

        foreach ($files as $file) {
            $itemid = (int) $file->get_itemid();
            if (!isset($cmids[$itemid])) {
                continue; // Not an activity — a section file, or an orphan. Leave it.
            }
            if (isset($sectionids[$itemid])) {
                $result['ambiguous']++;
                continue;
            }
            if (!$fs->file_exists($context->id, 'format_iccaformat', self::FILEAREA_CM, $itemid,
                    $file->get_filepath(), $file->get_filename())) {
                $fs->create_file_from_storedfile(['filearea' => self::FILEAREA_CM], $file);
            }
            $file->delete();
            $result['moved']++;
        }

        return $result;
    }
}