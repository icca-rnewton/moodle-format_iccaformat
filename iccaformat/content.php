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
 * Main content output class for format_iccaformat.
 *
 * Handles two views:
 *   - Course home: section card grid.
 *   - Section interior: interleaved activity + subsection cards in tutor-set order.
 *
 * Performance rules — never query inside a loop:
 *   - get_fast_modinfo() called once.
 *   - format_option::get_all_for_course() called once (bulk cache).
 *   - Completion fetched in bulk via wholecourse flag.
 *   - Label rules fetched once.
 *
 * @package   format_iccaformat
 * @copyright 2026 ICCA
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace format_iccaformat\output\courseformat;

defined('MOODLE_INTERNAL') || die();

use core_courseformat\output\local\content as content_base;
use format_iccaformat\local\format_option;
use renderer_base;

/**
 * Content output class.
 */
class content extends content_base {

    /**
     * Export data for templates.
     *
     * When editing: calls parent for Moodle's editing infrastructure.
     * When not editing: builds our own data cleanly.
     *
     * @param  renderer_base $output
     * @return \stdClass
     */
    public function export_for_template(renderer_base $output): \stdClass {
        global $DB, $USER, $PAGE;

        $format  = $this->format;
        $course  = $format->get_course();
        $modinfo = get_fast_modinfo($course);
        $context = \context_course::instance($course->id);

        $isediting     = $PAGE->user_is_editing();
        $canedit       = has_capability('moodle/course:update', $context);
        $canviewhidden = has_capability('moodle/course:viewhiddensections', $context);

        // --- Course format options -------------------------------------------
        $formatoptions       = $format->get_format_options();
        $borderradius        = (int)    ($formatoptions['borderradius']        ?? 8);
        $completioncolour    = (string) ($formatoptions['completionbarcolour'] ?? '#2d7fc1');
        $completionbarshowin = (int)    ($formatoptions['completionbarshowin'] ?? 2);
        $defaultdescdisplay  = (string) ($formatoptions['descriptiondisplay']  ?? 'hidden');
        // Read image display options directly — get_format_options() may return empty
        // string for new options on existing courses. We query the DB directly.
        $defaultobjectfit    = 'cover';
        $defaultposition     = 'center';
        $defaultfilter       = 'none';
        $defaultoverlay      = 'none';
        if (!empty($formatoptions['imageobjectfit']))      { $defaultobjectfit  = $formatoptions['imageobjectfit']; }
        if (!empty($formatoptions['imageobjectposition'])) { $defaultposition   = $formatoptions['imageobjectposition']; }
        if (!empty($formatoptions['imagefilter']))         { $defaultfilter     = $formatoptions['imagefilter']; }
        if (!empty($formatoptions['imageoverlay']))        { $defaultoverlay    = $formatoptions['imageoverlay']; }
        // Also check DB directly as fallback.
        global $DB;
        $dbvals = $DB->get_records_menu('course_format_options',
            ['courseid' => $course->id, 'sectionid' => 0,
             'format' => 'iccaformat'], '', 'name,value');
        if (!empty($dbvals['imageobjectfit']))      { $defaultobjectfit  = $dbvals['imageobjectfit']; }
        if (!empty($dbvals['imageobjectposition'])) { $defaultposition   = $dbvals['imageobjectposition']; }
        if (!empty($dbvals['imagefilter']))         { $defaultfilter     = $dbvals['imagefilter']; }
        if (!empty($dbvals['imageoverlay']))        { $defaultoverlay    = $dbvals['imageoverlay']; }

        // --- Bulk loads (one query each) -------------------------------------
        $optioncache = format_option::get_all_for_course($course->id);

        $labelrules = [];
        foreach ($DB->get_records('iccaformat_label_rules', ['courseid' => $course->id]) as $rule) {
            $labelrules[$rule->modname] = $rule;
        }

        $completionenabled = $course->enablecompletion && isloggedin() && !isguestuser();
        $completiondata    = [];
        if ($completionenabled) {
            $ci = new \completion_info($course);
            if ($ci->is_enabled()) {
                foreach ($modinfo->cms as $cm) {
                    if ($cm->completion != COMPLETION_TRACKING_NONE) {
                        $completiondata[$cm->id] = $ci->get_data($cm, true, $USER->id);
                    }
                }
            }
        }

        // In editing mode the renderer calls parent::render_content() directly,
        // so export_for_template() is only called in viewing mode.
        // We never call parent::export_for_template() here — it triggers
        // deprecated renderer methods in Moodle 4.5 / Boost Union.
        $data = new \stdClass();

        // --- Build section card grid (course home) ---------------------------
        $sectioncards = [];
        foreach ($modinfo->get_section_info_all() as $section) {
            if ($section->section == 0) {
                continue;
            }
            if (!$section->visible && !$canviewhidden) {
                continue;
            }
            $sectioncards[] = $this->build_section_card(
                $section, $course, $format, $modinfo,
                $optioncache, $labelrules, $completiondata,
                $completionbarshowin, $defaultdescdisplay,
                $borderradius, $completioncolour,
                $defaultobjectfit, $defaultposition, $defaultfilter, $defaultoverlay,
                $canedit, $canviewhidden, $output
            );
        }

        // DEBUG — remove after confirming image options work.
        $data->debug_imagefit = $defaultobjectfit;
        $data->debug_formatoptions = json_encode(['fit'=>$defaultobjectfit,'pos'=>$defaultposition,'filter'=>$defaultfilter,'overlay'=>$defaultoverlay]);
        $data->courseid         = $course->id;
        $data->coursename       = format_string($course->fullname);
        $data->courseurl        = (new \moodle_url('/course/view.php', ['id' => $course->id]))->out(false);
        $data->sectioncards     = $sectioncards;
        $data->hassections      = !empty($sectioncards);
        $data->borderradius     = $borderradius;
        $data->completioncolour = $completioncolour;
        $data->canedit          = $canedit;
        $data->editingon        = $isediting;

        // --- Section interior view data (set by renderer when on section page) --
        // Populated externally via build_section_interior_data() below.

        return $data;
    }

    /**
     * Build the data for a section interior view (activities + subsections as cards).
     *
     * Called by the renderer when we are on a section page, after export_for_template().
     * Adds sectioninterior data to the existing $data object.
     *
     * @param  \stdClass      $data         From export_for_template().
     * @param  int            $sectionnum   The section number being viewed.
     * @param  renderer_base  $output
     * @return \stdClass      Modified $data with sectioninterior populated.
     */
    public function build_section_interior_data(\stdClass $data, int $sectionnum, renderer_base $output): \stdClass {
        global $DB, $USER;

        $format  = $this->format;
        $course  = $format->get_course();
        $modinfo = get_fast_modinfo($course);
        $context = \context_course::instance($course->id);

        $canviewhidden = has_capability('moodle/course:viewhiddensections', $context);

        $formatoptions       = $format->get_format_options();
        $borderradius        = (int)    ($formatoptions['borderradius']        ?? 8);
        $completioncolour    = (string) ($formatoptions['completionbarcolour'] ?? '#2d7fc1');
        $completionbarshowin = (int)    ($formatoptions['completionbarshowin'] ?? 2);
        $defaultdescdisplay  = (string) ($formatoptions['descriptiondisplay']  ?? 'hidden');
        // Read image display options directly — get_format_options() may return empty
        // string for new options on existing courses. We query the DB directly.
        $defaultobjectfit    = 'cover';
        $defaultposition     = 'center';
        $defaultfilter       = 'none';
        $defaultoverlay      = 'none';
        if (!empty($formatoptions['imageobjectfit']))      { $defaultobjectfit  = $formatoptions['imageobjectfit']; }
        if (!empty($formatoptions['imageobjectposition'])) { $defaultposition   = $formatoptions['imageobjectposition']; }
        if (!empty($formatoptions['imagefilter']))         { $defaultfilter     = $formatoptions['imagefilter']; }
        if (!empty($formatoptions['imageoverlay']))        { $defaultoverlay    = $formatoptions['imageoverlay']; }
        // Also check DB directly as fallback.
        global $DB;
        $dbvals = $DB->get_records_menu('course_format_options',
            ['courseid' => $course->id, 'sectionid' => 0,
             'format' => 'iccaformat'], '', 'name,value');
        if (!empty($dbvals['imageobjectfit']))      { $defaultobjectfit  = $dbvals['imageobjectfit']; }
        if (!empty($dbvals['imageobjectposition'])) { $defaultposition   = $dbvals['imageobjectposition']; }
        if (!empty($dbvals['imagefilter']))         { $defaultfilter     = $dbvals['imagefilter']; }
        if (!empty($dbvals['imageoverlay']))        { $defaultoverlay    = $dbvals['imageoverlay']; }

        // Re-use bulk caches already built in export_for_template() where possible.
        // These are re-fetched here since this is called separately by the renderer.
        $optioncache = format_option::get_all_for_course($course->id);

        $labelrules = [];
        foreach ($DB->get_records('iccaformat_label_rules', ['courseid' => $course->id]) as $rule) {
            $labelrules[$rule->modname] = $rule;
        }

        $completionenabled = $course->enablecompletion && isloggedin() && !isguestuser();
        $completiondata    = [];
        if ($completionenabled) {
            $ci = new \completion_info($course);
            if ($ci->is_enabled()) {
                foreach ($modinfo->cms as $cm) {
                    if ($cm->completion != COMPLETION_TRACKING_NONE) {
                        $completiondata[$cm->id] = $ci->get_data($cm, true, $USER->id);
                    }
                }
            }
        }

        // --- Get the section being viewed ------------------------------------
        $section = $modinfo->get_section_info($sectionnum);
        if (!$section) {
            return $data;
        }

        // Breadcrumb data.
        $data->viewingsection  = true;
        $data->sectionname     = $format->get_section_name($section);
        $data->sectionnum      = $sectionnum;

        // --- Build interleaved activity + subsection cards -------------------
        // We iterate the cmids in this section in their exact order.
        // This preserves the tutor's arrangement: activity, subsection, activity, etc.
        $itemcards = [];
        $cmids = $modinfo->sections[$sectionnum] ?? [];

        foreach ($cmids as $cmid) {
            if (!isset($modinfo->cms[$cmid])) {
                continue;
            }
            $cm = $modinfo->cms[$cmid];

            // Skip label modules — they're decorative, not navigable cards.
            if ($cm->modname === 'label') {
                continue;
            }

            // Hide invisible cms from students.
            if (!$cm->visible && !has_capability('moodle/course:viewhiddenactivities', $context)) {
                continue;
            }

            if ($cm->modname === 'subsection') {
                // Subsection card — links into the subsection's own section page.
                $itemcards[] = $this->build_subsection_card(
                    $cm, $course, $format, $modinfo,
                    $optioncache, $completiondata,
                    $completionbarshowin, $defaultdescdisplay,
                    $borderradius, $completioncolour,
                    $defaultobjectfit, $defaultposition, $defaultfilter, $defaultoverlay,
                    $output
                );
            } else {
                // Activity card.
                $itemcards[] = $this->build_activity_card(
                    $cm, $course, $format,
                    $optioncache, $labelrules, $completiondata,
                    $defaultdescdisplay, $borderradius, $completioncolour,
                    $defaultobjectfit, $defaultposition, $defaultfilter, $defaultoverlay,
                    $context, $output
                );
            }
        }

        $data->itemcards    = $itemcards;
        $data->hasitemcards = !empty($itemcards);

        return $data;
    }

    // -------------------------------------------------------------------------
    // Card builders.
    // -------------------------------------------------------------------------

    /**
     * Build template data for a top-level section card (course home grid).
     */
    private function build_section_card(
        $section, $course, $format, $modinfo,
        array $optioncache, array $labelrules, array $completiondata,
        int $completionbarshowin, string $defaultdescdisplay,
        int $borderradius, string $completioncolour,
        string $defaultobjectfit, string $defaultposition,
        string $defaultfilter, string $defaultoverlay,
        bool $canedit, bool $canviewhidden,
        renderer_base $output
    ): \stdClass {

        $card = new \stdClass();
        $card->id           = $section->id;
        $card->sectionnum   = $section->section;
        $card->name         = $format->get_section_name($section);
        $card->url          = $format->get_view_url($section)->out(false);
        $card->visible      = (bool) $section->visible;
        $card->hidden       = !$section->visible;
        $card->borderradius = $borderradius;
        $card->issubsection = false;
        $card->isactivity   = false;

        $imgcss = $this->resolve_image_css($optioncache, $section->id,
            $defaultobjectfit, $defaultposition, $defaultfilter, $defaultoverlay);
        $card->imageobjectfit      = $imgcss['objectfit'];
        $card->imageobjectposition = $imgcss['objectposition'];
        $card->imagefilter         = $imgcss['filter'];
        $card->imageoverlay        = $imgcss['overlay'];
        $card->hasoverlay          = $imgcss['hasoverlay'];

        $this->apply_description($card, $section->summary, $section->summaryformat,
            $optioncache, $section->id, $defaultdescdisplay, $course->id);

        $card->imageurl = $this->resolve_element_image(
            $section->id, $optioncache, $course->id, null, [], $output);
        $card->hasimage = !empty($card->imageurl);

        $showbar = ($completionbarshowin == 0 || $completionbarshowin == 2);
        $this->apply_completion_bar($card, $showbar, $section, $modinfo,
            $completiondata, $completioncolour);

        $card->restricted     = !$section->available && $section->visible;
        $card->restrictioninfo = '';
        if ($card->restricted) {
            $card->restrictioninfo = $section->availableinfo
                ? format_text($section->availableinfo, FORMAT_HTML)
                : get_string('card_restricted', 'format_iccaformat');
        }

        return $card;
    }

    /**
     * Build template data for an activity card (inside a section).
     */
    private function build_activity_card(
        $cm, $course, $format,
        array $optioncache, array $labelrules, array $completiondata,
        string $defaultdescdisplay, int $borderradius, string $completioncolour,
        string $defaultobjectfit, string $defaultposition,
        string $defaultfilter, string $defaultoverlay,
        $context, renderer_base $output
    ): \stdClass {

        $card = new \stdClass();
        $card->id           = $cm->id;
        $card->name         = $cm->get_formatted_name();
        $card->url          = $cm->url ? $cm->url->out(false) : '#';
        $card->visible      = (bool) $cm->visible;
        $card->hidden       = !$cm->visible;
        $card->borderradius = $borderradius;
        $card->issubsection = false;
        $card->isactivity   = true;
        $card->modname      = $cm->modname;

        $imgcss = $this->resolve_image_css($optioncache, $cm->id,
            $defaultobjectfit, $defaultposition, $defaultfilter, $defaultoverlay);
        $card->imageobjectfit      = $imgcss['objectfit'];
        $card->imageobjectposition = $imgcss['objectposition'];
        $card->imagefilter         = $imgcss['filter'];
        $card->imageoverlay        = $imgcss['overlay'];
        $card->hasoverlay          = $imgcss['hasoverlay'];

        // --- Label and icon (rule → override → modname fallback) -------------
        $labeloverride = format_option::get_from_cache($optioncache, $cm->id, format_option::OPT_LABEL_OVERRIDE);
        $iconoverride  = format_option::get_from_cache($optioncache, $cm->id, format_option::OPT_ICON_OVERRIDE);
        $iconenabled   = format_option::get_from_cache($optioncache, $cm->id, format_option::OPT_ICON_ENABLED, '1');

        $rule = $labelrules[$cm->modname] ?? null;

        $card->label     = $labeloverride ?: ($rule ? $rule->label : null) ?: $cm->modfullname;
        $card->haslabel  = !empty($card->label);
        $card->icon      = $iconenabled === '1'
            ? ($iconoverride ?: ($rule ? $rule->icon : null))
            : null;
        $card->hasicon   = !empty($card->icon);

        // --- Image -----------------------------------------------------------
        $card->imageurl = $this->resolve_element_image(
            $cm->id, $optioncache, $course->id, $cm->modname, $labelrules, $output);
        $card->hasimage = !empty($card->imageurl);

        // --- Description -----------------------------------------------------
        $this->apply_description($card, $cm->content ?? '', FORMAT_HTML,
            $optioncache, $cm->id, $defaultdescdisplay, $course->id);

        // --- Completion ------------------------------------------------------
        $this->apply_activity_completion($card, $cm, $completiondata, $completioncolour);

        // --- Restrictions ----------------------------------------------------
        $card->restricted     = !$cm->available;
        $card->restrictioninfo = '';
        if ($card->restricted && !$cm->availableinfo) {
            $card->restrictioninfo = get_string('card_restricted', 'format_iccaformat');
        } else if ($card->restricted) {
            $card->restrictioninfo = format_text($cm->availableinfo, FORMAT_HTML);
        }

        return $card;
    }

    /**
     * Build template data for a subsection card (inside a section).
     *
     * Subsections are mod_subsection course modules. Their content lives in
     * a delegated section — we resolve that section for name and progress.
     */
    private function build_subsection_card(
        $cm, $course, $format, $modinfo,
        array $optioncache, array $completiondata,
        int $completionbarshowin, string $defaultdescdisplay,
        int $borderradius, string $completioncolour,
        string $defaultobjectfit, string $defaultposition,
        string $defaultfilter, string $defaultoverlay,
        renderer_base $output
    ): \stdClass {

        $card = new \stdClass();
        $card->id           = $cm->id;
        $card->borderradius = $borderradius;
        $card->issubsection = true;
        $card->isactivity   = false;
        $card->visible      = (bool) $cm->visible;
        $card->hidden       = !$cm->visible;
        $card->completioncolour = $completioncolour;

        // Resolve the delegated section for this subsection cm.
        // The section's id is stored as the cm's instance id for mod_subsection.
        $delegatedsection = null;
        foreach ($modinfo->get_section_info_all() as $s) {
            if ($s->component === 'mod_subsection' && $s->itemid == $cm->instance) {
                $delegatedsection = $s;
                break;
            }
        }

        if ($delegatedsection) {
            $card->name = $format->get_section_name($delegatedsection);
            $card->url  = $format->get_view_url($delegatedsection)->out(false);

            $imgcss = $this->resolve_image_css($optioncache, $delegatedsection->id,
                $defaultobjectfit, $defaultposition, $defaultfilter, $defaultoverlay);
            $card->imageobjectfit      = $imgcss['objectfit'];
            $card->imageobjectposition = $imgcss['objectposition'];
            $card->imagefilter         = $imgcss['filter'];
            $card->imageoverlay        = $imgcss['overlay'];
            $card->hasoverlay          = $imgcss['hasoverlay'];

            $this->apply_description($card, $delegatedsection->summary, $delegatedsection->summaryformat,
                $optioncache, $delegatedsection->id, $defaultdescdisplay, $course->id);

            $card->imageurl = $this->resolve_element_image(
                $delegatedsection->id, $optioncache, $course->id, null, [], $output);

            // Completion bar on subsections.
            $showbar = ($completionbarshowin == 1 || $completionbarshowin == 2);
            $this->apply_completion_bar($card, $showbar, $delegatedsection,
                $modinfo, $completiondata, $completioncolour);

            // Restrictions.
            $card->restricted     = !$delegatedsection->available && $delegatedsection->visible;
            $card->restrictioninfo = $card->restricted
                ? get_string('card_restricted', 'format_iccaformat') : '';
        } else {
            // Fallback if delegated section not resolved.
            $card->name         = $cm->get_formatted_name();
            $card->url          = '#';
            $card->imageurl     = '';
            $card->imageobjectfit      = $defaultobjectfit;
            $card->imageobjectposition = $defaultposition;
            $card->imagefilter         = 'none';
            $card->imageoverlay        = '';
            $card->hasoverlay          = false;
            $card->showcompletionbar = false;
            $card->restricted   = false;
            $card->restrictioninfo = '';
            $this->apply_description($card, '', FORMAT_HTML,
                $optioncache, $cm->id, $defaultdescdisplay, $course->id);
        }

        $card->hasimage = !empty($card->imageurl);

        return $card;
    }

    // -------------------------------------------------------------------------
    // Shared helpers.
    // -------------------------------------------------------------------------

    /**
     * Apply description display fields to a card.
     */
    private function apply_description(
        \stdClass $card,
        string $summary,
        int $summaryformat,
        array $optioncache,
        int $elementid,
        string $defaultdescdisplay,
        int $courseid
    ): void {
        $descdisplay = format_option::get_from_cache(
            $optioncache, $elementid,
            format_option::OPT_DESCRIPTION_DISPLAY,
            $defaultdescdisplay
        );
        $card->description_inbox    = ($descdisplay === 'inbox');
        $card->description_belowbox = ($descdisplay === 'belowbox');
        $card->description = ($descdisplay !== 'hidden' && !empty($summary))
            ? format_text($summary, $summaryformat,
                ['context' => \context_course::instance($courseid)])
            : '';
    }

    /**
     * Apply completion bar fields to a section or subsection card.
     */
    private function apply_completion_bar(
        \stdClass $card,
        bool $showbar,
        $section,
        $modinfo,
        array $completiondata,
        string $completioncolour
    ): void {
        if ($showbar) {
            $progress = $this->calculate_section_progress($section, $modinfo, $completiondata);
            $card->showcompletionbar = ($progress['total'] > 0);
            $card->completionpercent = $progress['percent'];
            $card->completioncolour  = $completioncolour;
            $card->completionlabel   = $progress['done'] . ' / ' . $progress['total'];
        } else {
            $card->showcompletionbar = false;
        }
    }

    /**
     * Apply completion status fields to an activity card.
     */
    private function apply_activity_completion(
        \stdClass $card,
        $cm,
        array $completiondata,
        string $completioncolour
    ): void {
        $card->completioncolour   = $completioncolour;
        $card->hascompletion      = isset($completiondata[$cm->id]);
        $card->completiondone     = false;
        $card->completionfailed   = false;
        $card->completionincomplete = false;
        $card->completionlabel    = '';

        if (!$card->hascompletion) {
            return;
        }

        $state = $completiondata[$cm->id]->completionstate ?? COMPLETION_INCOMPLETE;

        if ($state == COMPLETION_COMPLETE || $state == COMPLETION_COMPLETE_PASS) {
            $card->completiondone  = true;
            $card->completionlabel = get_string('card_completion_done', 'format_iccaformat');
        } else if ($state == COMPLETION_COMPLETE_FAIL) {
            $card->completionfailed = true;
            $card->completionlabel  = get_string('card_completion_failed', 'format_iccaformat');
        } else {
            $card->completionincomplete = true;
            $card->completionlabel      = get_string('card_completion_todo', 'format_iccaformat');
        }
    }

    /**
     * Resolve image URL for any card element.
     * Order: element upload → element library ref → label rule image → empty.
     */

    /**
     * Resolve image display CSS for a card from element options + course defaults.
     * Returns array with keys: object-fit, object-position, filter, overlay.
     */
    private function resolve_image_css(
        array $optioncache, int $elementid,
        string $defaultfit, string $defaultposition,
        string $defaultfilter, string $defaultoverlay
    ): array {
        $fit      = format_option::get_from_cache($optioncache, $elementid, 'imageobjectfit', '')      ?: $defaultfit;
        $position = format_option::get_from_cache($optioncache, $elementid, 'imageobjectposition', '') ?: $defaultposition;
        $filter   = format_option::get_from_cache($optioncache, $elementid, 'imagefilter', '')         ?: $defaultfilter;
        $overlay  = format_option::get_from_cache($optioncache, $elementid, 'imageoverlay', '')        ?: $defaultoverlay;

        $filtercss = match($filter) {
            'grayscale' => 'grayscale(100%)',
            'sepia'     => 'sepia(80%)',
            'brighten'  => 'brightness(130%)',
            default     => 'none',
        };

        $overlaycss = match($overlay) {
            'light' => 'rgba(255,255,255,0.35)',
            'dark'  => 'rgba(0,0,0,0.35)',
            default => '',
        };

        return [
            'objectfit'      => $fit      ?: 'cover',
            'objectposition' => $position ?: 'center',
            'filter'         => $filtercss,
            'overlay'        => $overlaycss,
            'hasoverlay'     => !empty($overlaycss),
        ];
    }

    private function resolve_element_image(
        int $elementid, array $optioncache, int $courseid,
        ?string $modname, array $labelrules, renderer_base $output
    ): string {
        $imagesource = format_option::get_from_cache($optioncache, $elementid, format_option::OPT_IMAGE_SOURCE);

        if ($imagesource === 'library') {
            $imageid = (int) format_option::get_from_cache($optioncache, $elementid, format_option::OPT_IMAGE_ID, 0);
            if ($imageid) {
                return $this->library_image_url($imageid);
            }
        }

        if ($imagesource === 'upload') {
            // Look up the actual stored filename — we can't assume it.
            // Static cache avoids repeated queries when many cards are rendered.
            static $filecache = [];
            $cachekey = $courseid . '_' . $elementid;
            if (!array_key_exists($cachekey, $filecache)) {
                $context = \context_course::instance($courseid);
                $fs      = get_file_storage();
                $files   = $fs->get_area_files(
                    $context->id, 'format_iccaformat', 'element_image',
                    $elementid, 'itemid', false
                );
                $filecache[$cachekey] = !empty($files) ? reset($files) : null;
            }
            if ($filecache[$cachekey]) {
                $context = \context_course::instance($courseid);
                return \moodle_url::make_pluginfile_url(
                    $context->id, 'format_iccaformat', 'element_image',
                    $elementid, '/', $filecache[$cachekey]->get_filename()
                )->out(false);
            }
        }

        if ($modname && isset($labelrules[$modname]) && !empty($labelrules[$modname]->libraryimageid)) {
            return $this->library_image_url((int) $labelrules[$modname]->libraryimageid);
        }

        return '';
    }

    /**
     * Build pluginfile URL for a library image.
     */
    private function library_image_url(int $imageid): string {
        global $DB;
        $record = $DB->get_record('iccaformat_image_library', ['id' => $imageid]);
        if (!$record) {
            return '';
        }
        $context = \context::instance_by_id($record->contextid);
        return \moodle_url::make_pluginfile_url(
            $context->id, 'format_iccaformat', 'library_image',
            $imageid, '/', $record->filename
        )->out(false);
    }

    /**
     * Calculate section completion progress from bulk-loaded data.
     */
    private function calculate_section_progress($section, $modinfo, array $completiondata): array {
        $done  = 0;
        $total = 0;
        foreach ($modinfo->sections[$section->section] ?? [] as $cmid) {
            if (!isset($completiondata[$cmid])) {
                continue;
            }
            $total++;
            $state = $completiondata[$cmid]->completionstate ?? COMPLETION_INCOMPLETE;
            if ($state == COMPLETION_COMPLETE || $state == COMPLETION_COMPLETE_PASS) {
                $done++;
            }
        }
        $percent = ($total > 0) ? (int) round(($done / $total) * 100) : 0;
        return ['done' => $done, 'total' => $total, 'percent' => $percent];
    }
}
