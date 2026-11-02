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

namespace format_iccaformat;

use format_iccaformat\local\format_option;

/**
 * Removes a card's options and uploaded image when its activity or section is deleted.
 *
 * Runs whatever the course's current format is: per-card options are kept across a
 * format switch, so they must also be cleaned up after one.
 *
 * @package   format_iccaformat
 * @copyright 2026 Inns of Court College of Advocacy (Part of COIC)
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class observer {
    /**
     * An activity was deleted.
     *
     * @param \core\event\course_module_deleted $event
     * @return void
     */
    public static function course_module_deleted(\core\event\course_module_deleted $event): void {
        self::remove_element((int) $event->courseid, format_option::ELEMENT_CM, (int) $event->objectid);
    }

    /**
     * A section (or a subsection's delegated section) was deleted.
     *
     * @param \core\event\course_section_deleted $event
     * @return void
     */
    public static function course_section_deleted(\core\event\course_section_deleted $event): void {
        self::remove_element((int) $event->courseid, format_option::ELEMENT_SECTION, (int) $event->objectid);
    }

    /**
     * Delete one element's options and its direct-upload image file.
     *
     * @param int $courseid
     * @param int $elementtype One of the format_option::ELEMENT_* constants.
     * @param int $elementid Section id or cmid.
     * @return void
     */
    private static function remove_element(int $courseid, int $elementtype, int $elementid): void {
        if ($courseid <= 0 || $elementid <= 0) {
            return;
        }
        format_option::delete_element($courseid, $elementtype, $elementid);
        $context = \context_course::instance($courseid, IGNORE_MISSING);
        if ($context) {
            get_file_storage()->delete_area_files(
                $context->id,
                'format_iccaformat',
                format_option::filearea($elementtype),
                $elementid
            );
        }
    }
}