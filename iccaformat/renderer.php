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
 * Renderer for format_iccaformat.
 *
 * @package   format_iccaformat
 * @copyright 2026 ICCA
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

use core_courseformat\output\section_renderer;

/**
 * Format renderer.
 */
class format_iccaformat_renderer extends section_renderer {

    /**
     * Render the course content.
     *
     * Editing mode   → core courseformat content class + core template.
     *                  Gives full Moodle editing UI with no reimplementation.
     * Viewing mode   → our card templates.
     *   sectionnum=0 → course home card grid.
     *   sectionnum>0 → section interior card grid.
     *
     * @return void
     */
    public function render_content(): void {
        global $PAGE;

        $format = course_get_format($this->page->course->id);

        // --- Editing mode: use core content class + core template ------------
        // This gives "Add activity", drag handles, visibility toggles etc.
        // We use the core_courseformat base class directly (not our subclass)
        // to avoid any risk of our export_for_template() being called.
        if ($PAGE->user_is_editing()) {
            $contentclass = 'core_courseformat\output\local\content';
            $widget       = new $contentclass($format);
            $data         = $widget->export_for_template($this);
            echo $this->render_from_template('core_courseformat/local/content', $data);
            return;
        }

        // --- Viewing mode: our card layout -----------------------------------
        $course  = $format->get_course();
        $modinfo = get_fast_modinfo($course);

        // Detect section context.
        $sectionnum = $format->get_sectionnum();
        if (!$sectionnum) {
            $sectionid = optional_param('id', 0, PARAM_INT);
            if ($sectionid) {
                foreach ($modinfo->get_section_info_all() as $s) {
                    if ($s->id == $sectionid) {
                        $sectionnum = $s->section;
                        break;
                    }
                }
            }
        }

        $contentclass = $format->get_output_classname('content');
        $widget       = new $contentclass($format);
        $data         = $widget->export_for_template($this);

        if ($sectionnum) {
            $data     = $widget->build_section_interior_data($data, $sectionnum, $this);
            $template = 'format_iccaformat/local/content/section_interior';
        } else {
            $template = 'format_iccaformat/local/content/section_cards';
        }

        echo $this->render_from_template($template, $data);
    }
}