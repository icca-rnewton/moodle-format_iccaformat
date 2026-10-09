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
        $cardsize            = (string) ($formatoptions['cardsize']             ?: 'medium');
        $hoverstyle          = (string) ($formatoptions['hoverstyle']           ?: 'imgzoom');
        // Read image display options directly — get_format_options() may return empty
        // string for new options on existing courses. We query the DB directly.
        $defaultobjectfit    = 'cover';
        $defaultposition     = 'center';
        $defaultfilter       = 'none';
        $defaultoverlay      = 'none';
        $defaultbgcolour     = '#ffffff';
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
        if (!empty($dbvals['imagebackgroundcolour'])) { $defaultbgcolour = $dbvals['imagebackgroundcolour']; }
        if (!empty($dbvals['cardsize']))   { $cardsize   = $dbvals['cardsize']; }
        if (!empty($dbvals['hoverstyle'])) { $hoverstyle = $dbvals['hoverstyle']; }
        $footerfontsize = (string) ($formatoptions['footerfontsize'] ?: '0.85');
        if (!empty($dbvals['footerfontsize'])) { $footerfontsize = $dbvals['footerfontsize']; }
        // Sanitise — strip anything that isn't a number or decimal point.
        $footerfontsize = preg_replace('/[^0-9.]/', '', $footerfontsize) ?: '1';
        $descfontsize = array_key_exists('descriptionfontsize', $dbvals) ? (string) $dbvals['descriptionfontsize'] : (string) ($formatoptions['descriptionfontsize'] ?? '1');
        $descfontsize = preg_replace('/[^0-9.]/', '', $descfontsize) ?: '1';
        $dropdownfontsize = (string) ($formatoptions['dropdownfontsize'] ?: '0.8');
        if (!empty($dbvals['dropdownfontsize'])) { $dropdownfontsize = $dbvals['dropdownfontsize']; }
        $dropdownfontsize = preg_replace('/[^0-9.]/', '', $dropdownfontsize) ?: '0.8';
        $hoverzoom = max(100, min(150, (int) ($formatoptions['hoverzoom'] ?: 105)));
        if (!empty($dbvals['hoverzoom'])) { $hoverzoom = max(100, min(150, (int)$dbvals['hoverzoom'])); }
        $hovercolour = (string) ($formatoptions['hovercolour'] ?: '#0d3c6f');
        if (!empty($dbvals['hovercolour'])) { $hovercolour = $dbvals['hovercolour']; }
        if (!preg_match('/^#[0-9a-fA-F]{6}$/', $hovercolour)) { $hovercolour = '#0d3c6f'; }

        // Card size → grid column widths.
        $cardsizemap = [
            'xsmall' => ['min' => '180px', 'max' => '220px'],
            'small'  => ['min' => '260px', 'max' => '300px'],
            'medium' => ['min' => '320px', 'max' => '380px'],
            'large'  => ['min' => '400px', 'max' => '480px'],
        ];
        $cardgrid = $cardsizemap[$cardsize] ?? $cardsizemap['medium'];

        // --- Bulk loads (one query each) -------------------------------------
        $optioncache = format_option::get_all_for_course($course->id);

        // Load label rules: course-level first, site-level (courseid=0) as fallback.
        $labelrules = [];
        foreach ($DB->get_records('iccaformat_label_rules', ['courseid' => 0]) as $rule) {
            $labelrules[$rule->modname] = $rule;
        }
        foreach ($DB->get_records('iccaformat_label_rules', ['courseid' => $course->id]) as $rule) {
            $labelrules[$rule->modname] = $rule; // Course rule overrides site rule.
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
        // Read usemodimages directly from DB to avoid Moodle API returning default (1) when stored value is 0.
        $usemodimages        = array_key_exists('usemodimages', $dbvals) ? (int) $dbvals['usemodimages'] : 1;
        $showactivitybar     = array_key_exists('showactivitybar', $dbvals) ? (int) $dbvals['showactivitybar'] : 1;

        foreach ($modinfo->get_section_info_all() as $section) {
            if ($section->section == 0) {
                continue;
            }
            if (!$section->visible && !$canviewhidden) {
                continue;
            }
            // Skip sections that are delegated to a subsection activity — these are
            // subsection interiors and should not appear as top-level section cards.
            if (!empty($section->component) && $section->component === 'mod_subsection') {
                continue;
            }
            $sectioncards[] = $this->build_section_card(
                $section, $course, $format, $modinfo,
                $optioncache, $labelrules, $completiondata,
                $completionbarshowin,
                $borderradius, $completioncolour,
                $defaultobjectfit, $defaultposition, $defaultfilter, $defaultoverlay, $defaultbgcolour,
                $canedit, $canviewhidden, $output
            );
        }

        $data->courseid         = $course->id;
        // Inject hover description CSS as a template variable.
        $data->hovercss = '<style>'
            . '@media(hover:hover){'
            . '.format-iccaformat-card:hover .icca-has-desc{padding-bottom:0.75rem!important;}'
            . '.format-iccaformat-card:hover .icca-hover-desc{max-height:72px!important;opacity:1!important;margin-top:5px!important;}'
            . '}'
            . '@media(hover:none){'
            . '.icca-hover-desc{max-height:72px!important;opacity:1!important;margin-top:5px!important;}'
            . '}'
            . '</style>';
        $data->canedit          = $canedit;
        $data->imageliburl      = (new \moodle_url('/course/format/iccaformat/imagelibrary.php',
            ['courseid' => $course->id]))->out(false);
        $data->labelrulesurl    = (new \moodle_url('/course/format/iccaformat/labelrules.php',
            ['courseid' => $course->id]))->out(false);
        $data->resetoverridesurl = (new \moodle_url('/course/format/iccaformat/ajax_reset_overrides.php',
            ['courseid' => $course->id, 'sesskey' => sesskey()]))->out(false);
        $data->coursename       = format_string($course->fullname);
        $data->courseurl        = (new \moodle_url('/course/view.php', ['id' => $course->id]))->out(false);
        $data->sectioncards     = $sectioncards;
        $data->hassections      = !empty($sectioncards);
        $data->borderradius     = $borderradius;
        $data->completioncolour = $completioncolour;
        $data->hoverstyle       = $hoverstyle;
        $data->hover_none       = ($hoverstyle === 'none');
        $data->hover_lift       = ($hoverstyle === 'lift');
        $data->hover_imgzoom    = ($hoverstyle === 'imgzoom');
        $data->hoverzoom        = $hoverzoom;
        $data->hovercolour      = $hovercolour;
        $data->footerfontsize   = $footerfontsize . 'rem';
        $data->descfontsize     = $descfontsize;
        $data->dropdownfontsize = $dropdownfontsize . 'rem';
        $data->hover_shadow     = ($hoverstyle === 'shadow');
        $data->gridmin          = $cardgrid['min'];
        $data->gridmax          = $cardgrid['max'];
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

        $footerfontsize      = preg_replace('/[^0-9.]/', '', (string) ($formatoptions['footerfontsize'] ?? '1')) ?: '1';
        $descfontsize        = preg_replace('/[^0-9.]/', '', (string) ($formatoptions['descriptionfontsize'] ?? '1')) ?: '1';
        // Read usemodimages directly from DB — Moodle's format API may return
        // the default (1) even when the stored value is 0 (falsy).

        $showactivitybar     = 1; // overridden from dbvals below
        // Read image display options directly — get_format_options() may return empty
        // string for new options on existing courses. We query the DB directly.
        $defaultobjectfit    = 'cover';
        $defaultposition     = 'center';
        $defaultfilter       = 'none';
        $defaultoverlay      = 'none';
        $defaultbgcolour     = '#ffffff';
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
        if (!empty($dbvals['imagebackgroundcolour'])) { $defaultbgcolour   = $dbvals['imagebackgroundcolour']; }
        $usemodimages      = array_key_exists('usemodimages', $dbvals) ? (int) $dbvals['usemodimages'] : 1;
        $showactivitybar   = array_key_exists('showactivitybar', $dbvals) ? (int) $dbvals['showactivitybar'] : 1;

        // Re-use bulk caches already built in export_for_template() where possible.
        // These are re-fetched here since this is called separately by the renderer.
        $optioncache = format_option::get_all_for_course($course->id);

        // Load label rules: course-level first, site-level (courseid=0) as fallback.
        $labelrules = [];
        foreach ($DB->get_records('iccaformat_label_rules', ['courseid' => 0]) as $rule) {
            $labelrules[$rule->modname] = $rule;
        }
        foreach ($DB->get_records('iccaformat_label_rules', ['courseid' => $course->id]) as $rule) {
            $labelrules[$rule->modname] = $rule; // Course rule overrides site rule.
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
        $data->borderradius    = $borderradius;
        $data->footerfontsize  = $footerfontsize . 'rem';
        $data->descfontsize    = $descfontsize;

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

            // Labels render as full-width text spacers between cards.
            if ($cm->modname === 'label') {
                if ($cm->visible || has_capability('moodle/course:viewhiddenactivities', $context)) {
                    $labelcard = new \stdClass();
                    $labelcard->islabel   = true;
                    $labelcard->labelhtml = format_text($cm->content ?? '', FORMAT_HTML,
                        ['context' => $context, 'noclean' => false]);
                    $itemcards[] = $labelcard;
                }
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
                    $optioncache, $labelrules, $completiondata,
                    $completionbarshowin,
                    $borderradius, $completioncolour,
                    $defaultobjectfit, $defaultposition, $defaultfilter, $defaultoverlay, $defaultbgcolour,
                    $output
                );
            } else {
                // Activity card.
                $itemcards[] = $this->build_activity_card(
                    $cm, $course, $format,
                    $optioncache, $labelrules, $completiondata,
                    $borderradius, $completioncolour,
                    $defaultobjectfit, $defaultposition, $defaultfilter, $defaultoverlay, $defaultbgcolour,
                    $context, $output, $usemodimages, $descfontsize, $showactivitybar
                );
            }
        }

        $data->itemcards    = $itemcards;
        $data->hasitemcards = !empty($itemcards);

        // Section/subsection interior progress bar.
        $sectionprogbar   = (int) ($formatoptions['sectionprogbar'] ?? 2);
        if (array_key_exists('sectionprogbar', $dbvals)) { $sectionprogbar = (int) $dbvals['sectionprogbar']; }
        $issubsectionpage = ($section->component === 'mod_subsection');
        $showbar = false;
        if ($sectionprogbar == 2) { $showbar = true; }
        else if ($sectionprogbar == 0 && !$issubsectionpage) { $showbar = true; }
        else if ($sectionprogbar == 1 && $issubsectionpage)  { $showbar = true; }
        $notrackingstr    = get_string('card_notracking', 'format_iccaformat');

        if ($showbar) {
            // Deep count — include all activities in this section AND in subsections.
            $done = 0; $total = 0;
            foreach ($cmids as $cmid) {
                $cm = $modinfo->cms[$cmid] ?? null;
                if (!$cm) continue;
                if ($cm->modname === 'subsection') {
                    // Walk into subsection.
                    foreach ($modinfo->get_section_info_all() as $s) {
                        if ($s->component === 'mod_subsection' && $s->itemid == $cm->instance) {
                            foreach ($modinfo->sections[$s->section] ?? [] as $subcmid) {
                                if (!isset($completiondata[$subcmid])) continue;
                                $subcm = $modinfo->cms[$subcmid] ?? null;
                                if (!$subcm || $subcm->modname === 'label') continue;
                                $total++;
                                $state = $completiondata[$subcmid]->completionstate ?? COMPLETION_INCOMPLETE;
                                if ($state == COMPLETION_COMPLETE || $state == COMPLETION_COMPLETE_PASS) $done++;
                            }
                            break;
                        }
                    }
                } else {
                    if (!isset($completiondata[$cmid])) continue;
                    $cm2 = $modinfo->cms[$cmid] ?? null;
                    if (!$cm2 || $cm2->modname === 'label') continue;
                    $total++;
                    $state = $completiondata[$cmid]->completionstate ?? COMPLETION_INCOMPLETE;
                    if ($state == COMPLETION_COMPLETE || $state == COMPLETION_COMPLETE_PASS) $done++;
                }
            }
            $data->sectionprogress        = true;
            $data->sectionprogresspercent = ($total > 0) ? (int) round(($done / $total) * 100) : 0;
            $data->sectionprogresslabel   = $done . ' / ' . $total;
            $data->sectionhasactivities   = ($total > 0);
            $data->sectionnotracking      = ($total === 0);
            $data->sectionnotrackingstr   = $notrackingstr;
            $data->completioncolour       = $completioncolour;
        } else {
            $data->sectionprogress      = false;
            $data->sectionhasactivities = false;
            $data->sectionnotracking    = false;
            $data->sectionnotrackingstr = $notrackingstr;
            $data->completioncolour     = $completioncolour;
        }

        // ── Course map navigation ─────────────────────────────────────────
        $showcoursemap   = (int) ($formatoptions['showcoursemap']   ?? 1);
        if (!empty($dbvals['showcoursemap']))   { $showcoursemap   = (int) $dbvals['showcoursemap']; }
        $showhomebutton  = (int) ($formatoptions['showhomebutton']  ?? 1);
        if (!empty($dbvals['showhomebutton']))  { $showhomebutton  = (int) $dbvals['showhomebutton']; }

        $data->showcoursemap    = (bool) $showcoursemap;
        $data->showhomebutton   = (bool) $showhomebutton;
        $data->showstrip        = ($sectionprogbar != 3 || $showhomebutton || $showcoursemap);
        $data->shownavbuttons   = ($showhomebutton || $showcoursemap);
        $data->coursemaplabel    = get_string('showcoursemap', 'format_iccaformat');
        $data->coursemapitems    = [];
        $data->hascoursemapitems = false;
        $data->issubsectionmap   = false;
        $data->parentbanner      = null;
        $data->othersections     = [];

        if ($showcoursemap) {
            $issubsection = ($section->component === 'mod_subsection');

            if ($issubsection) {
                $data->issubsectionmap = true;

                // Find parent section.
                $parentsection = null;
                foreach ($modinfo->get_section_info_all() as $s) {
                    if (!$s->visible && !has_capability('moodle/course:viewhiddensections', $context)) continue;
                    foreach ($modinfo->sections[$s->section] ?? [] as $cmid) {
                        $cm = $modinfo->cms[$cmid] ?? null;
                        if ($cm && $cm->modname === 'subsection' && $cm->instance == $section->itemid) {
                            $parentsection = $s;
                            break 2;
                        }
                    }
                }

                if ($parentsection) {
                    // Build parent banner — deep progress count.
                    $pdone = 0; $ptotal = 0;
                    foreach ($modinfo->sections[$parentsection->section] ?? [] as $cmid) {
                        $cm = $modinfo->cms[$cmid] ?? null;
                        if (!$cm) continue;
                        if ($cm->modname === 'subsection') {
                            foreach ($modinfo->get_section_info_all() as $sub) {
                                if ($sub->component !== 'mod_subsection' || $sub->itemid != $cm->instance) continue;
                                foreach ($modinfo->sections[$sub->section] ?? [] as $subcmid) {
                                    if (!isset($completiondata[$subcmid])) continue;
                                    $subcm = $modinfo->cms[$subcmid] ?? null;
                                    if (!$subcm || $subcm->modname === 'label') continue;
                                    $ptotal++;
                                    $st = $completiondata[$subcmid]->completionstate ?? COMPLETION_INCOMPLETE;
                                    if ($st == COMPLETION_COMPLETE || $st == COMPLETION_COMPLETE_PASS) $pdone++;
                                }
                                break;
                            }
                        } else {
                            if (!isset($completiondata[$cmid])) continue;
                            if ($cm->modname === 'label') continue;
                            $ptotal++;
                            $st = $completiondata[$cmid]->completionstate ?? COMPLETION_INCOMPLETE;
                            if ($st == COMPLETION_COMPLETE || $st == COMPLETION_COMPLETE_PASS) $pdone++;
                        }
                    }
                    $pbg = \format_iccaformat\local\format_option::get_from_cache(
                        $optioncache, $parentsection->id, 'imagebackgroundcolour', $defaultbgcolour);
                    $data->parentbanner = [
                        'name'             => $format->get_section_name($parentsection),
                        'url'              => (new \moodle_url('/course/section.php', ['id' => $parentsection->id]))->out(false),
                        'bgcolour'         => $pbg ?: $defaultbgcolour,
                        'hastracking'      => ($ptotal > 0),
                        'percent'          => ($ptotal > 0) ? (int) round(($pdone / $ptotal) * 100) : 0,
                        'label'            => $pdone . ' / ' . $ptotal,
                        'completioncolour' => $completioncolour,
                    ];

                    // Build all top-level sections — parent is iscurrent, others are not.
                    foreach ($modinfo->get_section_info_all() as $s) {
                        if ($s->section == 0) continue;
                        if ($s->component === 'mod_subsection') continue;
                        if (!$s->visible && !has_capability('moodle/course:viewhiddensections', $context)) continue;
                        $mdone = 0; $mtotal = 0;
                        foreach ($modinfo->sections[$s->section] ?? [] as $cmid) {
                            $cm = $modinfo->cms[$cmid] ?? null;
                            if (!$cm) continue;
                            if ($cm->modname === 'subsection') {
                                foreach ($modinfo->get_section_info_all() as $sub) {
                                    if ($sub->component !== 'mod_subsection' || $sub->itemid != $cm->instance) continue;
                                    foreach ($modinfo->sections[$sub->section] ?? [] as $subcmid) {
                                        if (!isset($completiondata[$subcmid])) continue;
                                        $subcm = $modinfo->cms[$subcmid] ?? null;
                                        if (!$subcm || $subcm->modname === 'label') continue;
                                        $mtotal++;
                                        $st = $completiondata[$subcmid]->completionstate ?? COMPLETION_INCOMPLETE;
                                        if ($st == COMPLETION_COMPLETE || $st == COMPLETION_COMPLETE_PASS) $mdone++;
                                    }
                                    break;
                                }
                            } else {
                                if (!isset($completiondata[$cmid])) continue;
                                if ($cm->modname === 'label') continue;
                                $mtotal++;
                                $st = $completiondata[$cmid]->completionstate ?? COMPLETION_INCOMPLETE;
                                if ($st == COMPLETION_COMPLETE || $st == COMPLETION_COMPLETE_PASS) $mdone++;
                            }
                        }
                        $bgcolour = \format_iccaformat\local\format_option::get_from_cache(
                            $optioncache, $s->id, 'imagebackgroundcolour', $defaultbgcolour);
                        $data->coursemapitems[] = [
                            'name'             => $format->get_section_name($s),
                            'url'              => (new \moodle_url('/course/section.php', ['id' => $s->id]))->out(false),
                            'bgcolour'         => $bgcolour ?: $defaultbgcolour,
                            'iscurrent'        => ($s->id === $parentsection->id),
                            'hastracking'      => ($mtotal > 0),
                            'percent'          => ($mtotal > 0) ? (int) round(($mdone / $mtotal) * 100) : 0,
                            'label'            => $mdone . ' / ' . $mtotal,
                            'completioncolour' => $completioncolour,
                            'notrackingstr'    => $notrackingstr,
                        ];
                    }

                    // Build sibling subsections — indented below sections.
                    foreach ($modinfo->sections[$parentsection->section] ?? [] as $cmid) {
                        $cm = $modinfo->cms[$cmid] ?? null;
                        if (!$cm || $cm->modname !== 'subsection') continue;
                        foreach ($modinfo->get_section_info_all() as $sub) {
                            if ($sub->component !== 'mod_subsection' || $sub->itemid != $cm->instance) continue;
                            if (!$sub->visible && !has_capability('moodle/course:viewhiddensections', $context)) break;
                            $mdone = 0; $mtotal = 0;
                            foreach ($modinfo->sections[$sub->section] ?? [] as $subcmid) {
                                if (!isset($completiondata[$subcmid])) continue;
                                $subcm = $modinfo->cms[$subcmid] ?? null;
                                if (!$subcm || $subcm->modname === 'label') continue;
                                $mtotal++;
                                $st = $completiondata[$subcmid]->completionstate ?? COMPLETION_INCOMPLETE;
                                if ($st == COMPLETION_COMPLETE || $st == COMPLETION_COMPLETE_PASS) $mdone++;
                            }
                            $bgcolour = \format_iccaformat\local\format_option::get_from_cache(
                                $optioncache, $sub->id, 'imagebackgroundcolour', $defaultbgcolour);
                            $data->othersections[] = [
                                'name'             => $sub->name ?: get_string('section'),
                                'url'              => (new \moodle_url('/course/section.php', ['id' => $sub->id]))->out(false),
                                'bgcolour'         => $bgcolour ?: $defaultbgcolour,
                                'iscurrent'        => ($sub->id === $section->id),
                                'hastracking'      => ($mtotal > 0),
                                'percent'          => ($mtotal > 0) ? (int) round(($mdone / $mtotal) * 100) : 0,
                                'label'            => $mdone . ' / ' . $mtotal,
                                'completioncolour' => $completioncolour,
                                'notrackingstr'    => $notrackingstr,
                            ];
                            break;
                        }
                    }
                }
            } else {
                // Section page — show all top-level sections, then subsections of current section.
                foreach ($modinfo->get_section_info_all() as $s) {
                    if ($s->section == 0) continue;
                    if ($s->component === 'mod_subsection') continue;
                    if (!$s->visible && !has_capability('moodle/course:viewhiddensections', $context)) continue;
                    $mdone = 0; $mtotal = 0;
                    foreach ($modinfo->sections[$s->section] ?? [] as $cmid) {
                        $cm = $modinfo->cms[$cmid] ?? null;
                        if (!$cm) continue;
                        if ($cm->modname === 'subsection') {
                            foreach ($modinfo->get_section_info_all() as $sub) {
                                if ($sub->component !== 'mod_subsection' || $sub->itemid != $cm->instance) continue;
                                foreach ($modinfo->sections[$sub->section] ?? [] as $subcmid) {
                                    if (!isset($completiondata[$subcmid])) continue;
                                    $subcm = $modinfo->cms[$subcmid] ?? null;
                                    if (!$subcm || $subcm->modname === 'label') continue;
                                    $mtotal++;
                                    $st = $completiondata[$subcmid]->completionstate ?? COMPLETION_INCOMPLETE;
                                    if ($st == COMPLETION_COMPLETE || $st == COMPLETION_COMPLETE_PASS) $mdone++;
                                }
                                break;
                            }
                        } else {
                            if (!isset($completiondata[$cmid])) continue;
                            if ($cm->modname === 'label') continue;
                            $mtotal++;
                            $st = $completiondata[$cmid]->completionstate ?? COMPLETION_INCOMPLETE;
                            if ($st == COMPLETION_COMPLETE || $st == COMPLETION_COMPLETE_PASS) $mdone++;
                        }
                    }
                    $bgcolour = \format_iccaformat\local\format_option::get_from_cache(
                        $optioncache, $s->id, 'imagebackgroundcolour', $defaultbgcolour);
                    $data->coursemapitems[] = [
                        'name'             => $format->get_section_name($s),
                        'url'              => (new \moodle_url('/course/section.php', ['id' => $s->id]))->out(false),
                        'bgcolour'         => $bgcolour ?: $defaultbgcolour,
                        'iscurrent'        => ($s->id === $section->id),
                        'hastracking'      => ($mtotal > 0),
                        'percent'          => ($mtotal > 0) ? (int) round(($mdone / $mtotal) * 100) : 0,
                        'label'            => $mdone . ' / ' . $mtotal,
                        'completioncolour' => $completioncolour,
                        'notrackingstr'    => $notrackingstr,
                    ];
                }

                // Subsections of the current section — indented below.
                foreach ($modinfo->sections[$section->section] ?? [] as $cmid) {
                    $cm = $modinfo->cms[$cmid] ?? null;
                    if (!$cm || $cm->modname !== 'subsection') continue;
                    foreach ($modinfo->get_section_info_all() as $sub) {
                        if ($sub->component !== 'mod_subsection' || $sub->itemid != $cm->instance) continue;
                        if (!$sub->visible && !has_capability('moodle/course:viewhiddensections', $context)) break;
                        $mdone = 0; $mtotal = 0;
                        foreach ($modinfo->sections[$sub->section] ?? [] as $subcmid) {
                            if (!isset($completiondata[$subcmid])) continue;
                            $subcm = $modinfo->cms[$subcmid] ?? null;
                            if (!$subcm || $subcm->modname === 'label') continue;
                            $mtotal++;
                            $st = $completiondata[$subcmid]->completionstate ?? COMPLETION_INCOMPLETE;
                            if ($st == COMPLETION_COMPLETE || $st == COMPLETION_COMPLETE_PASS) $mdone++;
                        }
                        $bgcolour = \format_iccaformat\local\format_option::get_from_cache(
                            $optioncache, $sub->id, 'imagebackgroundcolour', $defaultbgcolour);
                        $data->othersections[] = [
                            'name'             => $sub->name ?: get_string('section'),
                            'url'              => (new \moodle_url('/course/section.php', ['id' => $sub->id]))->out(false),
                            'bgcolour'         => $bgcolour ?: $defaultbgcolour,
                            'iscurrent'        => false,
                            'hastracking'      => ($mtotal > 0),
                            'percent'          => ($mtotal > 0) ? (int) round(($mdone / $mtotal) * 100) : 0,
                            'label'            => $mdone . ' / ' . $mtotal,
                            'completioncolour' => $completioncolour,
                            'notrackingstr'    => $notrackingstr,
                        ];
                        break;
                    }
                }
            }
            $data->hascoursemapitems = !empty($data->coursemapitems);
            $data->hasothersections  = !empty($data->othersections);
            $data->hasparentbanner   = !empty($data->parentbanner);
        }

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
        int $completionbarshowin,
        int $borderradius, string $completioncolour,
        string $defaultobjectfit, string $defaultposition,
        string $defaultfilter, string $defaultoverlay, string $defaultbgcolour,
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
            $defaultobjectfit, $defaultposition, $defaultfilter, $defaultoverlay, $defaultbgcolour);
        $card->imageobjectfit      = $imgcss['objectfit'];
        $card->imageobjectposition = $imgcss['objectposition'];
        $card->imagefilter         = $imgcss['filter'];
        $card->imageoverlay        = $imgcss['overlay'];
        $card->hasoverlay          = $imgcss['hasoverlay'];
        $card->imagebgcolour       = $imgcss['bgcolour'];

        $this->apply_description($card, $section->summary ?? '', $section->summaryformat ?? FORMAT_HTML,
            $optioncache, $section->id, $course->id, 'inbox');

        // Section image — explicit pick first, then label rules default for 'section'.
        $card->imageurl = $this->resolve_element_image(
            $section->id, $optioncache, $course->id, 'section', $labelrules, $output);
        $card->hasimage = !empty($card->imageurl);

        // Section icon — driven by label rules for 'section', with per-card override.
        $sectionrule     = $labelrules['section'] ?? null;
        $sectionnum      = $section->section;
        $iconenabledopt  = format_option::get_from_cache($optioncache, $section->id, format_option::OPT_ICON_ENABLED, null);
        $labelenabledopt = format_option::get_from_cache($optioncache, $section->id, format_option::OPT_LABEL_ENABLED, null);
        $iconoverride    = format_option::get_from_cache($optioncache, $section->id, format_option::OPT_ICON_OVERRIDE, '');
        $labeloverride   = format_option::get_from_cache($optioncache, $section->id, format_option::OPT_LABEL_OVERRIDE, '');
        $iconenabled     = $iconenabledopt !== null
            ? ($iconenabledopt === '1')
            : (!empty($sectionrule->icon_enabled));
        $card->showsectionicon  = false;
        $card->sectionicontext  = '';
        $card->sectioniconclass = '';
        // Custom icon override takes priority over numbering rule.
        if ($iconenabled && !empty($iconoverride)) {
            $card->sectioniconclass = 'fa ' . $iconoverride;
            $card->showsectionicon  = true;
        } else if ($iconenabled && $sectionrule && $sectionnum > 0) {
            $icontype = $sectionrule->icon ?? '';
            if ($icontype === 'numeric') {
                $card->sectionicontext = (string) $sectionnum;
                $card->showsectionicon = true;
            } else if ($icontype === 'alpha') {
                $card->sectionicontext = chr(96 + $sectionnum);
                $card->showsectionicon = true;
            } else if ($icontype === 'ALPHA') {
                $card->sectionicontext = chr(64 + $sectionnum);
                $card->showsectionicon = true;
            } else if ($icontype === 'roman') {
                $card->sectionicontext = $this->to_roman($sectionnum);
                $card->showsectionicon = true;
            } else if (!empty($icontype) && !in_array($icontype, ['none','numeric','alpha','ALPHA','roman'])) {
                $card->sectioniconclass = 'fa ' . $icontype;
                $card->showsectionicon = !empty($sectionrule->icon_enabled);
            }
        }
        // Section label (from label rule or per-card override), with per-card enabled override.
        $rulehaslabel = $sectionrule && !empty($sectionrule->label_enabled) && !empty($sectionrule->label);
        $labelenabled = $labelenabledopt !== null ? ((int)$labelenabledopt === 1) : $rulehaslabel;
        $card->haslabel  = false;
        $card->namelabel = '';
        if ($labelenabled) {
            $card->namelabel = $labeloverride ?: ($sectionrule ? $sectionrule->label : '');
            $card->haslabel  = !empty($card->namelabel);
        }
        $flipopt_sec = format_option::get_from_cache($optioncache, $section->id, format_option::OPT_ICON_LABEL_FLIPPED, null);
        $card->flipped = $flipopt_sec !== null ? ((int)$flipopt_sec === 1) : !empty($sectionrule->flipped);

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
        int $borderradius, string $completioncolour,
        string $defaultobjectfit, string $defaultposition,
        string $defaultfilter, string $defaultoverlay, string $defaultbgcolour,
        $context, renderer_base $output, int $usemodimages = 1,
        string $descfontsize = '1', int $showactivitybar = 1
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
            $defaultobjectfit, $defaultposition, $defaultfilter, $defaultoverlay, $defaultbgcolour);
        $card->imageobjectfit      = $imgcss['objectfit'];
        $card->imageobjectposition = $imgcss['objectposition'];
        $card->imagefilter         = $imgcss['filter'];
        $card->imageoverlay        = $imgcss['overlay'];
        $card->hasoverlay          = $imgcss['hasoverlay'];
        $card->imagebgcolour       = $imgcss['bgcolour'];

        // --- Label and icon (rule → override → modname fallback) -------------
        $labeloverride  = format_option::get_from_cache($optioncache, $cm->id, format_option::OPT_LABEL_OVERRIDE);
        $iconoverride   = format_option::get_from_cache($optioncache, $cm->id, format_option::OPT_ICON_OVERRIDE);
        $iconenabled    = format_option::get_from_cache($optioncache, $cm->id, format_option::OPT_ICON_ENABLED, null);
        $labelenabledopt = format_option::get_from_cache($optioncache, $cm->id, format_option::OPT_LABEL_ENABLED, null);

        $rule = $labelrules[$cm->modname] ?? null;

        $card->label     = $labeloverride ?: ($rule ? $rule->label : null) ?: $cm->modfullname;
        $labelenabled    = $rule ? ($rule->label_enabled ?? 1) : 1;
        // Per-card label_enabled override (null = not set, use rule default).
        if ($labelenabledopt !== null) {
            $labelenabled = (int) $labelenabledopt;
        }
        // Icon: per-card override wins, then rule icon_enabled, then default on.
        $iconon = $iconenabled !== null
            ? ($iconenabled === '1')
            : ($rule ? !empty($rule->icon_enabled) : true);
        $showlabel = (bool) $labelenabled;
        $card->haslabel  = !empty($card->label) && $showlabel;
        $card->icon      = $iconon
            ? ($iconoverride ?: ($rule ? $rule->icon : null) ?: 'fa-cube')
            : null;
        $card->hasicon   = !empty($card->icon);
        // Flipped — per-card override wins over rule.
        $flippedopt = format_option::get_from_cache($optioncache, $cm->id, format_option::OPT_ICON_LABEL_FLIPPED, null);
        $card->flipped  = $flippedopt !== null ? ((int)$flippedopt === 1) : !empty($rule->flipped);

        // --- Image -----------------------------------------------------------
        $card->imageurl = $this->resolve_element_image(
            $cm->id, $optioncache, $course->id, $cm->modname, $labelrules, $output, $usemodimages);
        $card->hasimage = !empty($card->imageurl);

        // --- Description -----------------------------------------------------
        $this->apply_description($card, $cm->content ?? '', FORMAT_HTML,
            $optioncache, $cm->id, $course->id, 'inbox');

        // --- Completion ------------------------------------------------------
        $this->apply_activity_completion($card, $cm, $completiondata, $completioncolour, (bool) $showactivitybar);

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
        array $optioncache, array $labelrules, array $completiondata,
        int $completionbarshowin,
        int $borderradius, string $completioncolour,
        string $defaultobjectfit, string $defaultposition,
        string $defaultfilter, string $defaultoverlay, string $defaultbgcolour,
        renderer_base $output
    ): \stdClass {

        $card = new \stdClass();
        $card->id           = $cm->id;
        $card->borderradius = $borderradius;
        $card->issubsection = true;
        $card->isactivity   = false;
        $card->visible      = (bool) $cm->visible;
        $card->hidden       = !$cm->visible;
        $card->completioncolour  = $completioncolour;
        // Subsection icon — driven by label rules for 'subsection', with per-card override.
        $subsectionrule = $labelrules['subsection'] ?? null;
        // Note: icon/label overrides are stored against the delegated section ID, not cm->id.
        // We resolve the delegated section first, then read options.
        $delegatedsection = null;
        foreach ($modinfo->get_section_info_all() as $s) {
            if ($s->component === 'mod_subsection' && $s->itemid == $cm->instance) {
                $delegatedsection = $s;
                break;
            }
        }
        $optionsid       = $delegatedsection ? $delegatedsection->id : $cm->id;
        $iconenabledopt  = format_option::get_from_cache($optioncache, $optionsid, format_option::OPT_ICON_ENABLED, null);
        $labelenabledopt = format_option::get_from_cache($optioncache, $optionsid, format_option::OPT_LABEL_ENABLED, null);
        $iconoverride    = format_option::get_from_cache($optioncache, $optionsid, format_option::OPT_ICON_OVERRIDE, '');
        $labeloverride   = format_option::get_from_cache($optioncache, $optionsid, format_option::OPT_LABEL_OVERRIDE, '');
        $iconenabled  = $iconenabledopt !== null ? ($iconenabledopt === '1') : (!empty($subsectionrule->icon_enabled));
        // Custom icon override takes priority over rule.
        if ($iconenabled && !empty($iconoverride)) {
            $card->showsectionicon  = true;
            $card->sectioniconclass = 'fa ' . $iconoverride;
        } else {
            $card->showsectionicon = $iconenabled && $subsectionrule && !empty($subsectionrule->icon);
            $card->sectioniconclass = $card->showsectionicon ? 'fa ' . $subsectionrule->icon : '';
        }

        if ($delegatedsection) {
            $card->name = $format->get_section_name($delegatedsection);
            // Apply label rule label if set and enabled, with per-card override.
            $rulehaslabel = $subsectionrule && !empty($subsectionrule->label_enabled) && !empty($subsectionrule->label);
            $labelenabled = $labelenabledopt !== null ? ((int)$labelenabledopt === 1) : $rulehaslabel;
            if ($labelenabled) {
                $card->namelabel = $labeloverride ?: ($subsectionrule ? $subsectionrule->label : '');
                $card->haslabel  = !empty($card->namelabel);
            } else {
                $card->haslabel  = false;
            }
        $flipopt_sub = format_option::get_from_cache($optioncache, $optionsid, format_option::OPT_ICON_LABEL_FLIPPED, null);
        $card->flipped = $flipopt_sub !== null ? ((int)$flipopt_sub === 1) : !empty($subsectionrule->flipped);
            $card->url  = $format->get_view_url($delegatedsection)->out(false);

            $imgcss = $this->resolve_image_css($optioncache, $delegatedsection->id,
                $defaultobjectfit, $defaultposition, $defaultfilter, $defaultoverlay, $defaultbgcolour);
            $card->imageobjectfit      = $imgcss['objectfit'];
            $card->imageobjectposition = $imgcss['objectposition'];
            $card->imagefilter         = $imgcss['filter'];
            $card->imageoverlay        = $imgcss['overlay'];
            $card->hasoverlay          = $imgcss['hasoverlay'];
        $card->imagebgcolour       = $imgcss['bgcolour'];

            $this->apply_description($card, $delegatedsection->summary, $delegatedsection->summaryformat,
                $optioncache, $delegatedsection->id, $course->id, 'inbox');

            $card->imageurl = $this->resolve_element_image(
                $delegatedsection->id, $optioncache, $course->id, 'subsection', $labelrules, $output, $usemodimages ?? 1);

            // Completion bar on subsections + activity breakdown dropdown.
            $showbar = ($completionbarshowin == 1 || $completionbarshowin == 2);
            $this->apply_completion_bar($card, $showbar, $delegatedsection,
                $modinfo, $completiondata, $completioncolour);

            // Build activity breakdown rows for dropdown.
            $notrackingstr = get_string('card_notracking', 'format_iccaformat');
            $activityrows = [];
            foreach ($modinfo->sections[$delegatedsection->section] ?? [] as $subcmid) {
                $subcm = $modinfo->cms[$subcmid] ?? null;
                if (!$subcm || !$subcm->visible || $subcm->modname === 'label') continue;
                $acturl = $subcm->url ? $subcm->url->out(false) : '#';
                if (isset($completiondata[$subcmid])) {
                    $state = $completiondata[$subcmid]->completionstate ?? COMPLETION_INCOMPLETE;
                    $done  = ($state == COMPLETION_COMPLETE || $state == COMPLETION_COMPLETE_PASS);
                    $activityrows[] = [
                        'name'             => $subcm->get_formatted_name(),
                        'url'              => $acturl,
                        'hastracking'      => true,
                        'notracking'       => false,
                        'completiondone'   => $done,
                        'completioncolour' => $done ? $completioncolour : 'rgba(0,0,0,0.08)',
                        'percent'          => $done ? 100 : 0,
                        'label'            => $done ? '✓' : '○',
                        'notrackingstr'    => $notrackingstr,
                    ];
                } else {
                    $activityrows[] = [
                        'name'             => $subcm->get_formatted_name(),
                        'url'              => $acturl,
                        'hastracking'      => false,
                        'notracking'       => true,
                        'completiondone'   => false,
                        'completioncolour' => 'rgba(0,0,0,0.08)',
                        'percent'          => 0,
                        'label'            => '',
                        'notrackingstr'    => $notrackingstr,
                    ];
                }
            }
            if (!empty($activityrows)) {
                $card->hassubsections = true;
                $card->subsectionbars = $activityrows;
            }

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
            $card->imagebgcolour       = $defaultbgcolour;
            $card->showcompletionbar = false;
            $card->restricted   = false;
            $card->restrictioninfo = '';
            $this->apply_description($card, '', FORMAT_HTML,
                $optioncache, $cm->id, $course->id, 'inbox');
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
        int $courseid,
        string $defaultdescdisplay = 'inbox'
    ): void {
        global $DB;

        // For sections/subsections, course_format_options is the authoritative source
        // (set via the section edit form). element_options may have stale 'hidden' values
        // from the old system — check course_format_options first, then element_options,
        // then course default.
        $row = $DB->get_record('course_format_options', [
            'courseid'  => $courseid,
            'sectionid' => $elementid,
            'name'      => 'description_display',
        ]);

        if ($row && $row->value !== '') {
            // Section edit form value wins.
            $descdisplay = (string) $row->value;
        } else {
            // Fall back to element_options (activities), then course default.
            $descdisplay = format_option::get_from_cache(
                $optioncache, $elementid,
                format_option::OPT_DESCRIPTION_DISPLAY,
                $defaultdescdisplay
            ) ?: $defaultdescdisplay;
        }

        // Show description in hover panel if content exists.
        // Strip all HTML, entities, and whitespace to check for real content.
        $plaintext = trim(html_entity_decode(strip_tags($summary ?? ''), ENT_QUOTES, 'UTF-8'));
        $plaintext = preg_replace('/\s+/', '', $plaintext); // remove all whitespace
        if (!empty($plaintext)) {
            $formatted = format_text($summary, $summaryformat,
                ['context' => \context_course::instance($courseid)]);
            // Strip <a> tags (keep inner text) — descriptions render inside an <a> card
            // wrapper; nested <a> tags are invalid HTML and cause browsers to break the
            // outer link, pushing the image to the bottom of the card.
            $formatted = preg_replace('/<a\b[^>]*>(.*?)<\/a>/is', '$1', $formatted);
            $card->description    = $formatted;
            $card->hasdescription = true;
            $card->scrimclass     = 'icca-card-scrim icca-has-desc';
        } else {
            $card->description    = '';
            $card->hasdescription = false;
            $card->scrimclass     = 'icca-card-scrim';
        }
        $card->description_inbox    = false;
        $card->description_belowbox = false;
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
        $notrackingstr = get_string('card_notracking', 'format_iccaformat');
        if ($showbar) {
            $progress = $this->calculate_section_progress($section, $modinfo, $completiondata);
            $card->showcompletionbar  = ($progress['total'] > 0);
            $card->shownotracking     = ($progress['total'] === 0);
            $card->showfooter         = true;
            $card->notrackingstr      = $notrackingstr;
            $card->completionpercent  = $progress['percent'];
            $card->completioncolour   = $completioncolour;
            $card->completionlabel    = $progress['done'] . ' / ' . $progress['total'];
            $card->hassubsections     = !empty($progress['subsections']);
            // Inject completioncolour and notracking string into each subsection bar entry.
            $subsectionbars = [];
            foreach ($progress['subsections'] as $sub) {
                $sub['completioncolour'] = $completioncolour;
                $sub['notrackingstr']    = $notrackingstr;
                $subsectionbars[] = $sub;
            }
            $card->subsectionbars = $subsectionbars;
        } else {
            $card->showcompletionbar = false;
            $card->shownotracking    = false;
            $card->showfooter        = false;
            $card->notrackingstr     = $notrackingstr;
            $card->hassubsections    = false;
            $card->subsectionbars    = [];
        }
    }

    /**
     * Apply completion status fields to an activity card.
     */
    private function apply_activity_completion(
        \stdClass $card,
        $cm,
        array $completiondata,
        string $completioncolour,
        bool $showbar = true
    ): void {
        $card->completioncolour   = $completioncolour;
        $card->notrackingstr      = get_string('card_notracking', 'format_iccaformat');
        $card->hascompletion      = isset($completiondata[$cm->id]);
        $card->completiondone     = false;
        $card->completionfailed   = false;
        $card->completionincomplete = false;
        $card->completionlabel    = '';
        $card->completioncriteria = '';
        $card->showfooter         = $showbar;

        if (!$card->hascompletion) {
            return;
        }

        // Build completion criteria hint.
        $criteriaparts = [];
        if (!empty($cm->completionview)) {
            $criteriaparts[] = get_string('completionview_desc', 'completion');
        }
        if (!empty($cm->completiongradeitemnumber)) {
            $criteriaparts[] = get_string('completionpassgrade_desc', 'completion');
        } else if (property_exists($cm, 'completionexpected') && !empty($cm->completionusegrade)) {
            $criteriaparts[] = get_string('completionusegrade_desc', 'completion');
        }
        if (!empty($criteriaparts)) {
            $card->completioncriteria = implode(', ', $criteriaparts);
        }

        $state = $completiondata[$cm->id]->completionstate ?? COMPLETION_INCOMPLETE;

        if ($state == COMPLETION_COMPLETE || $state == COMPLETION_COMPLETE_PASS) {
            $card->completiondone     = true;
            $card->completionlabel    = get_string('card_completion_done', 'format_iccaformat');
            $card->completioncriteria = ''; // Don't show criteria when done
        } else if ($state == COMPLETION_COMPLETE_FAIL) {
            $card->completionfailed   = true;
            $card->completionlabel    = get_string('card_completion_failed', 'format_iccaformat');
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
        string $defaultfilter, string $defaultoverlay,
        string $defaultbgcolour = '#ffffff'
    ): array {
        $fit      = format_option::get_from_cache($optioncache, $elementid, 'imageobjectfit', '')      ?: $defaultfit;
        $position = format_option::get_from_cache($optioncache, $elementid, 'imageobjectposition', '') ?: $defaultposition;
        $filter   = format_option::get_from_cache($optioncache, $elementid, 'imagefilter', '')         ?: $defaultfilter;
        $overlay  = format_option::get_from_cache($optioncache, $elementid, 'imageoverlay', '')        ?: $defaultoverlay;
        $bgcolour = format_option::get_from_cache($optioncache, $elementid, 'imagebackgroundcolour', '') ?: $defaultbgcolour;

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
            'bgcolour'       => $bgcolour ?: '#ffffff',
        ];
    }

    private function resolve_element_image(
        int $elementid, array $optioncache, int $courseid,
        ?string $modname, array $labelrules, renderer_base $output,
        int $usemodimages = 1
    ): string {
        $imagesource = format_option::get_from_cache($optioncache, $elementid, format_option::OPT_IMAGE_SOURCE);

        // Explicit "no image" override — suppress default too.
        if ($imagesource === 'none') {
            return '';
        }

        if ($imagesource === 'library') {
            $imageid = (int) format_option::get_from_cache($optioncache, $elementid, format_option::OPT_IMAGE_ID, 0);
            if ($imageid) {
                return $this->library_image_url($imageid);
            }
        }

        if ($imagesource === 'upload') {
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

        if ($usemodimages && $modname && isset($labelrules[$modname])
                && !empty($labelrules[$modname]->libraryimageid)
                && empty($labelrules[$modname]->image_hidden)) {
            return $this->library_image_url((int) $labelrules[$modname]->libraryimageid);
        }

        return '';
    }

    /**
     * Build pluginfile URL for a library image.
     */
    /**
     * Convert integer to lowercase Roman numeral string.
     */
    private function to_roman(int $n): string {
        $map = [1000=>'m',900=>'cm',500=>'d',400=>'cd',100=>'c',90=>'xc',
                50=>'l',40=>'xl',10=>'x',9=>'ix',5=>'v',4=>'iv',1=>'i'];
        $result = '';
        foreach ($map as $val => $numeral) {
            while ($n >= $val) {
                $result .= $numeral;
                $n -= $val;
            }
        }
        return $result;
    }

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
     * Static version of library_image_url for use outside class context
     * (e.g. labelrules.php).
     */
    public static function get_library_image_url_static(int $imageid): string {
        global $DB;
        if (!$imageid) {
            return '';
        }
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
     * Calculate section progress recursively, including all subsection activities.
     * Returns total done/total, percent, and per-subsection breakdown for the dropdown.
     * Subsections without tracked activities are included with notracking=true.
     */
    private function calculate_section_progress($section, $modinfo, array $completiondata): array {
        $done  = 0;
        $total = 0;
        $subsections = [];

        // Count direct activities (not inside a subsection).
        $directdone  = 0;
        $directtotal = 0;

        foreach ($modinfo->sections[$section->section] ?? [] as $cmid) {
            $cm = $modinfo->cms[$cmid] ?? null;
            if (!$cm) continue;

            if ($cm->modname === 'subsection') {
                // Find the delegated section for this subsection.
                // Build lookup by itemid for efficiency and correct ordering.
                static $sectionbyitemid = null;
                if ($sectionbyitemid === null) {
                    $sectionbyitemid = [];
                    foreach ($modinfo->get_section_info_all() as $s) {
                        if ($s->component === 'mod_subsection') {
                            $sectionbyitemid[$s->itemid] = $s;
                        }
                    }
                }
                $delegated = $sectionbyitemid[$cm->instance] ?? null;
                if (!$delegated) continue;
                // Skip hidden sections.
                if (!$delegated->visible) continue;

                // Count activities inside the subsection.
                $subdone  = 0;
                $subtotal = 0;
                foreach ($modinfo->sections[$delegated->section] ?? [] as $subcmid) {
                    if (!isset($completiondata[$subcmid])) continue;
                    $subtotal++;
                    $state = $completiondata[$subcmid]->completionstate ?? COMPLETION_INCOMPLETE;
                    if ($state == COMPLETION_COMPLETE || $state == COMPLETION_COMPLETE_PASS) {
                        $subdone++;
                    }
                }
                $done  += $subdone;
                $total += $subtotal;

                // Always include subsection — flag if no tracking.
                if ($subtotal > 0) {
                    $subsections[] = [
                        'name'         => $delegated->name ?: get_string('section'),
                        'url'          => (new \moodle_url('/course/section.php', ['id' => $delegated->id]))->out(false),
                        'done'         => $subdone,
                        'total'        => $subtotal,
                        'percent'      => (int) round(($subdone / $subtotal) * 100),
                        'label'        => $subdone . ' / ' . $subtotal,
                        'notracking'   => false,
                        'hastracking'  => true,
                    ];
                } else {
                    $subsections[] = [
                        'name'         => $delegated->name ?: get_string('section'),
                        'url'          => (new \moodle_url('/course/section.php', ['id' => $delegated->id]))->out(false),
                        'done'         => 0,
                        'total'        => 0,
                        'percent'      => 0,
                        'label'        => '',
                        'notracking'   => true,
                        'hastracking'  => false,
                    ];
                }
            } else {
                // Direct activity.
                if (!isset($completiondata[$cmid])) continue;
                $directtotal++;
                $state = $completiondata[$cmid]->completionstate ?? COMPLETION_INCOMPLETE;
                if ($state == COMPLETION_COMPLETE || $state == COMPLETION_COMPLETE_PASS) {
                    $directdone++;
                }
            }
        }

        $done  += $directdone;
        $total += $directtotal;

        // Include direct activities row if there are trackable direct activities AND subsections.
        if ($directtotal > 0 && !empty($subsections)) {
            array_unshift($subsections, [
                'name'        => $section->name ?: get_string('section'),
                'url'         => (new \moodle_url('/course/section.php', ['id' => $section->id]))->out(false),
                'done'        => $directdone,
                'total'       => $directtotal,
                'percent'     => (int) round(($directdone / $directtotal) * 100),
                'label'       => $directdone . ' / ' . $directtotal,
                'notracking'  => false,
                'hastracking' => true,
            ]);
        }

        $percent = ($total > 0) ? (int) round(($done / $total) * 100) : 0;
        return [
            'done'           => $done,
            'total'          => $total,
            'percent'        => $percent,
            'subsections'    => $subsections,
            'hassubsections' => !empty($subsections),
        ];
    }
}
