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
 * Main format class for format_iccaformat.
 *
 * @package   format_iccaformat
 * @copyright 2026 ICCA
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

require_once($CFG->dirroot . '/course/format/lib.php');

/**
 * format_iccaformat — card-based course format with image library, labelling, and completion display.
 */
class format_iccaformat extends core_courseformat\base {

    // ---------------------------------------------------------------------------
    // Element type constants — used in iccaformat_element_options.elementtype.
    // ---------------------------------------------------------------------------

    /** @var int Top-level section. */
    const ELEMENT_SECTION = 1;

    /** @var int Delegated subsection (mod_subsection). */
    const ELEMENT_SUBSECTION = 2;

    /** @var int Course module (activity). */
    const ELEMENT_CM = 3;

    // ---------------------------------------------------------------------------
    // Description display constants — used in iccaformat_element_options.optionvalue
    // for optionname='description_display', and as the course-level default.
    // ---------------------------------------------------------------------------

    /** @var string Show description inside the card box. */
    const DESC_INBOX = 'inbox';

    /** @var string Show description in a text area directly below the card. */
    const DESC_BELOWBOX = 'belowbox';

    /** @var string Do not show description. */
    const DESC_HIDDEN = 'hidden';

    // ---------------------------------------------------------------------------
    // Image source constants — used in iccaformat_element_options for imagesource.
    // ---------------------------------------------------------------------------

    /** @var string Image comes from the site/course image library. */
    const IMG_LIBRARY = 'library';

    /** @var string Image is a direct upload against this element. */
    const IMG_UPLOAD = 'upload';

    // ---------------------------------------------------------------------------
    // Constructor.
    // ---------------------------------------------------------------------------

    /**
     * Constructor. Use course_get_format($courseorid) — do not instantiate directly.
     *
     * @param string $format The format name ('iccaformat').
     * @param int    $courseid
     */
    protected function __construct($format, $courseid) {
        if ($courseid === 0) {
            global $COURSE;
            $courseid = $COURSE->id;
        }
        parent::__construct($format, $courseid);
    }

    // ---------------------------------------------------------------------------
    // Core format feature declarations.
    // ---------------------------------------------------------------------------

    /**
     * Format uses sections.
     * @return bool
     */
    public function uses_sections(): bool {
        return true;
    }

    /**
     * Format supports Moodle's course index sidebar.
     * @return bool
     */
    public function uses_course_index(): bool {
        return true;
    }

    /**
     * No indentation — we use card layout, not an indented list.
     * @return bool
     */
    public function uses_indentation(): bool {
        return false;
    }

    /**
     * Supports Moodle course AJAX features.
     * @return stdClass
     */
    public function supports_ajax(): stdClass {
        $ajaxsupport = new stdClass();
        $ajaxsupport->capable = true;
        return $ajaxsupport;
    }

    /**
     * Compatible with Moodle 4.x output components (React-based course editor).
     * @return bool
     */
    public function supports_components(): bool {
        return true;
    }

    /**
     * News forum is supported.
     * @return bool
     */
    public function supports_news(): bool {
        return true;
    }

    /**
     * Sections can be deleted.
     * @param int|stdClass|section_info $section
     * @return bool
     */
    public function can_delete_section($section): bool {
        return true;
    }

    /**
     * No default blocks — let the theme decide.
     * @return array
     */
    public function get_default_blocks(): array {
        return [BLOCK_POS_LEFT => [], BLOCK_POS_RIGHT => []];
    }

    /**
     * Returns the URL to use for the specified course section.
     *
     * Moodle 4.5 introduced /course/section.php (MDL-79986) as the canonical
     * URL for individual sections. We always return this for non-zero sections
     * so that clicking a section card navigates to its own page rather than
     * anchoring back to the course home.
     *
     * @param  int|stdClass|section_info $section  Section object or section number.
     * @param  array                     $options  Optional parameters.
     * @return moodle_url|null
     */
    public function get_view_url($section, $options = []): ?\moodle_url {
        $course = $this->get_course();

        if (is_object($section)) {
            $sectionno = $section->section;
            $sectionid = $section->id ?? null;
        } else {
            $sectionno = (int) $section;
            $sectionid = null;
        }

        // Section 0 (general) stays on the course page.
        if ($sectionno == 0) {
            return new \moodle_url('/course/view.php', ['id' => $course->id]);
        }

        // For all other sections, use the Moodle 4.5 section page by section id.
        // Fall back to the old view.php?section= param if we don't have the id.
        if ($sectionid) {
            return new \moodle_url('/course/section.php', ['id' => $sectionid]);
        }

        // Resolve section id from modinfo if not provided directly.
        $modinfo = get_fast_modinfo($course);
        $sectioninfo = $modinfo->get_section_info($sectionno);
        if ($sectioninfo) {
            return new \moodle_url('/course/section.php', ['id' => $sectioninfo->id]);
        }

        // Final fallback.
        return new \moodle_url('/course/view.php', ['id' => $course->id, 'section' => $sectionno]);
    }

    // ---------------------------------------------------------------------------
    // Section naming.
    // ---------------------------------------------------------------------------

    /**
     * Returns the display name of a section.
     *
     * @param int|stdClass $section
     * @return string
     */
    public function get_section_name($section): string {
        $section = $this->get_section($section);
        if ((string) $section->name !== '') {
            return format_string(
                $section->name,
                true,
                ['context' => context_course::instance($this->courseid)]
            );
        }
        return $this->get_default_section_name($section);
    }

    /**
     * Returns the default section name.
     *
     * @param stdClass $section
     * @return string
     */
    public function get_default_section_name($section): string {
        if ($section->section == 0) {
            return get_string('section0name', 'format_iccaformat');
        }
        return parent::get_default_section_name($section);
    }

    // ---------------------------------------------------------------------------
    // Course-level format options.
    // These are stored in mdl_course_format_options by Moodle core.
    // All per-element options live in iccaformat_element_options instead.
    // ---------------------------------------------------------------------------

    /**
     * Defines course-level format options.
     *
     * Options:
     *   borderradius         — card corner radius in px (0–24).
     *   completionbarcolour  — hex colour for completion bars, e.g. #2d7fc1.
     *   completionbarshowin  — which levels show completion bars.
     *   descriptiondisplay   — course default for description placement.
     *   hiddensections       — required by core; always 1 (invisible).
     *   coursedisplay        — required by core; always multipage.
     *
     * @param bool $foreditform Whether to include form element definitions.
     * @return array
     */
    public function course_format_options($foreditform = false): array {
        static $courseformatoptions = false;

        if ($courseformatoptions === false) {
            $courseformatoptions = [
                // Required by Moodle core — hidden sections are invisible (not greyed out).
                'hiddensections' => [
                    'default' => 1,
                    'type'    => PARAM_INT,
                ],
                // Required by Moodle core — multipage (each section on its own page).
                'coursedisplay' => [
                    'default' => COURSE_DISPLAY_MULTIPAGE,
                    'type'    => PARAM_INT,
                ],
                // Card corner rounding: 0 = square, 8 = medium, 16 = round, 24 = pill-like.
                'borderradius' => [
                    'default' => (int) (get_config('format_iccaformat', 'default_borderradius') ?: 8),
                    'type'    => PARAM_INT,
                ],
                // Card size preset — controls grid min/max widths.
                'cardsize' => [
                    'default' => get_config('format_iccaformat', 'default_cardsize') ?: 'medium',
                    'type'    => PARAM_ALPHA,
                ],
                'footerfontsize' => [
                    'default' => get_config('format_iccaformat', 'default_footerfontsize') ?: '1',
                    'type'    => PARAM_TEXT,
                ],
                'descriptionfontsize' => [
                    'default' => get_config('format_iccaformat', 'default_descriptionfontsize') ?: '1',
                    'type'    => PARAM_TEXT,
                ],
                'dropdownfontsize' => [
                    'default' => get_config('format_iccaformat', 'default_dropdownfontsize') ?: '1',
                    'type'    => PARAM_TEXT,
                ],
                // Hover animation style.
                'hoverstyle' => [
                    'default' => get_config('format_iccaformat', 'default_hoverstyle') ?: 'imgzoom',
                    'type'    => PARAM_ALPHA,
                ],
                // Image zoom amount (percentage).
                'hoverzoom' => [
                    'default' => (int) (get_config('format_iccaformat', 'default_hoverzoom') ?: 105),
                    'type'    => PARAM_INT,
                ],
                // Shadow hover colour.
                'hovercolour' => [
                    'default' => get_config('format_iccaformat', 'default_hovercolour') ?: '#0d3c6f',
                    'type'    => PARAM_TEXT,
                ],
                // Completion bar fill colour.
                'completionbarcolour' => [
                    'default' => get_config('format_iccaformat', 'default_completionbarcolour') ?: '#0d3c6f',
                    'type'    => PARAM_TEXT,
                ],
                // Which card levels display a completion bar.
                // 0 = sections only, 1 = subsections only, 2 = both.
                'completionbarshowin' => [
                    'default' => (int) (get_config('format_iccaformat', 'default_completionbarshowin') ?: 2),
                    'type'    => PARAM_INT,
                ],
                'sectionprogbar' => [
                    'default' => (int) (get_config('format_iccaformat', 'default_sectionprogbar') ?? 2),
                    'type'    => PARAM_INT,
                ],
                'showactivitybar' => [
                    'default' => (int) (get_config('format_iccaformat', 'default_showactivitybar') ?? 1),
                    'type'    => PARAM_INT,
                ],

                // Navigation.
                'showhomebutton' => [
                    'default' => (int) (get_config('format_iccaformat', 'default_showhomebutton') ?? 1),
                    'type'    => PARAM_INT,
                ],
                'showcoursemap' => [
                    'default' => (int) (get_config('format_iccaformat', 'default_showcoursemap') ?? 1),
                    'type'    => PARAM_INT,
                ],
                // Card image object-fit.
                'imageobjectfit' => [
                    'default' => get_config('format_iccaformat', 'default_imageobjectfit') ?: 'cover',
                    'type'    => PARAM_ALPHA,
                ],
                // Card image object-position.
                'imageobjectposition' => [
                    'default' => get_config('format_iccaformat', 'default_imageobjectposition') ?: 'center',
                    'type'    => PARAM_TEXT,
                ],
                // Card image CSS filter.
                'imagefilter' => [
                    'default' => 'none',
                    'type'    => PARAM_ALPHA,
                ],
                // Card image overlay.
                'imageoverlay' => [
                    'default' => 'none',
                    'type'    => PARAM_ALPHA,
                ],
                // Card image background colour (visible behind transparent PNGs).
                'imagebackgroundcolour' => [
                    'default' => get_config('format_iccaformat', 'default_imagebackgroundcolour') ?: '#0d3c6f',
                    'type'    => PARAM_TEXT,
                ],
            ];
        }

        if ($foreditform && !isset($courseformatoptions['borderradius']['label'])) {
            $courseformatoptionsedit = [
                'hiddensections' => [
                    'label'            => new lang_string('hiddensections'),
                    'element_type'     => 'hidden',
                    'element_attributes' => [[1 => new lang_string('hiddensectionsinvisible')]],
                ],
                'coursedisplay' => [
                    'label'            => new lang_string('coursedisplay'),
                    'element_type'     => 'hidden',
                    'element_attributes' => [[COURSE_DISPLAY_MULTIPAGE => new lang_string('coursedisplay_multi')]],
                ],

                // Card appearance.
                'borderradius' => [
                    'label'        => get_string('borderradius', 'format_iccaformat'),
                    'element_type' => 'select',
                    'element_attributes' => [[
                        0  => get_string('borderradius_square', 'format_iccaformat'),
                        4  => get_string('borderradius_small', 'format_iccaformat'),
                        8  => get_string('borderradius_medium', 'format_iccaformat'),
                        16 => get_string('borderradius_large', 'format_iccaformat'),
                        24 => get_string('borderradius_round', 'format_iccaformat'),
                    ]],
                    'help'           => 'borderradius',
                    'help_component' => 'format_iccaformat',
                ],
                'cardsize' => [
                    'label'        => get_string('cardsize', 'format_iccaformat'),
                    'element_type' => 'select',
                    'element_attributes' => [[
                        'xsmall' => get_string('cardsize_xsmall', 'format_iccaformat'),
                        'small'  => get_string('cardsize_small',  'format_iccaformat'),
                        'medium' => get_string('cardsize_medium', 'format_iccaformat'),
                        'large'  => get_string('cardsize_large',  'format_iccaformat'),
                    ]],
                    'help'           => 'cardsize',
                    'help_component' => 'format_iccaformat',
                ],
                // Hover animation.
                'hoverstyle' => [
                    'label'        => get_string('hoverstyle', 'format_iccaformat'),
                    'element_type' => 'select',
                    'element_attributes' => [[
                        'none'    => get_string('hoverstyle_none',    'format_iccaformat'),
                        'imgzoom' => get_string('hoverstyle_imgzoom', 'format_iccaformat'),
                        'lift'    => get_string('hoverstyle_lift',    'format_iccaformat'),
                        'shadow'  => get_string('hoverstyle_shadow',  'format_iccaformat'),
                    ]],
                    'help'           => 'hoverstyle',
                    'help_component' => 'format_iccaformat',
                ],
                'hoverzoom' => [
                    'label'        => get_string('hoverzoom', 'format_iccaformat'),
                    'element_type' => 'text',
                    'help'           => 'hoverzoom',
                    'help_component' => 'format_iccaformat',
                ],
                'hovercolour' => [
                    'label'        => get_string('hovercolour', 'format_iccaformat'),
                    'element_type' => 'text',
                    'help'           => 'hovercolour',
                    'help_component' => 'format_iccaformat',
                ],
                // Completion bars.
                'completionbarcolour' => [
                    'label'        => get_string('completionbarcolour', 'format_iccaformat'),
                    'element_type' => 'text',
                    'help'           => 'completionbarcolour',
                    'help_component' => 'format_iccaformat',
                ],
                'completionbarshowin' => [
                    'label'        => get_string('completionbarshowin', 'format_iccaformat'),
                    'element_type' => 'select',
                    'element_attributes' => [[
                        3 => get_string('completionbarshowin_none', 'format_iccaformat'),
                        0 => get_string('completionbarshowin_sections', 'format_iccaformat'),
                        1 => get_string('completionbarshowin_subsections', 'format_iccaformat'),
                        2 => get_string('completionbarshowin_both', 'format_iccaformat'),
                    ]],
                    'help'           => 'completionbarshowin',
                    'help_component' => 'format_iccaformat',
                ],
                'sectionprogbar' => [
                    'label'        => get_string('sectionprogbar', 'format_iccaformat'),
                    'element_type' => 'select',
                    'element_attributes' => [[
                        '3' => get_string('completionbarshowin_none',        'format_iccaformat'),
                        '0' => get_string('completionbarshowin_sections',    'format_iccaformat'),
                        '1' => get_string('completionbarshowin_subsections', 'format_iccaformat'),
                        '2' => get_string('completionbarshowin_both',        'format_iccaformat'),
                    ]],
                ],
                'showactivitybar' => [
                    'label'        => get_string('showactivitybar', 'format_iccaformat'),
                    'element_type' => 'advcheckbox',
                ],
                // Navigation.
                'showhomebutton' => [
                    'label'        => get_string('showhomebutton', 'format_iccaformat'),
                    'element_type' => 'select',
                    'element_attributes' => [[
                        1 => get_string('showhomebutton_show', 'format_iccaformat'),
                        0 => get_string('showhomebutton_hide', 'format_iccaformat'),
                    ]],
                ],
                'showcoursemap' => [
                    'label'        => get_string('showcoursemap', 'format_iccaformat'),
                    'element_type' => 'select',
                    'element_attributes' => [[
                        1 => get_string('showcoursemap_show', 'format_iccaformat'),
                        0 => get_string('showcoursemap_hide', 'format_iccaformat'),
                    ]],
                ],

                // Image defaults.
                'imageobjectfit' => [
                    'label'        => get_string('imageobjectfit', 'format_iccaformat'),
                    'element_type' => 'select',
                    'element_attributes' => [[
                        'cover'   => get_string('imageobjectfit_cover',   'format_iccaformat'),
                        'contain' => get_string('imageobjectfit_contain', 'format_iccaformat'),
                        'fill'    => get_string('imageobjectfit_fill',    'format_iccaformat'),
                    ]],
                    'help'           => 'imageobjectfit',
                    'help_component' => 'format_iccaformat',
                ],
                'imageobjectposition' => [
                    'label'        => get_string('imageobjectposition', 'format_iccaformat'),
                    'element_type' => 'select',
                    'element_attributes' => [[
                        'center'       => get_string('imageposition_center',      'format_iccaformat'),
                        'top'          => get_string('imageposition_top',         'format_iccaformat'),
                        'bottom'       => get_string('imageposition_bottom',      'format_iccaformat'),
                        'left'         => get_string('imageposition_left',        'format_iccaformat'),
                        'right'        => get_string('imageposition_right',       'format_iccaformat'),
                        'top left'     => get_string('imageposition_topleft',     'format_iccaformat'),
                        'top right'    => get_string('imageposition_topright',    'format_iccaformat'),
                        'bottom left'  => get_string('imageposition_bottomleft',  'format_iccaformat'),
                        'bottom right' => get_string('imageposition_bottomright', 'format_iccaformat'),
                    ]],
                    'help'           => 'imageobjectposition',
                    'help_component' => 'format_iccaformat',
                ],
                // Typography.
                'descriptionfontsize' => [
                    'label'        => get_string('descriptionfontsize', 'format_iccaformat'),
                    'element_type' => 'text',
                ],
                'footerfontsize' => [
                    'label'        => get_string('footerfontsize', 'format_iccaformat'),
                    'element_type' => 'text',
                    'help'         => 'footerfontsize',
                    'help_component' => 'format_iccaformat',
                ],
                'dropdownfontsize' => [
                    'label'        => get_string('dropdownfontsize', 'format_iccaformat'),
                    'element_type' => 'text',
                    'help'         => 'dropdownfontsize',
                    'help_component' => 'format_iccaformat',
                ],
                'imagefilter' => [
                    'label'        => get_string('imagefilter', 'format_iccaformat'),
                    'element_type' => 'select',
                    'element_attributes' => [[
                        'none'      => get_string('imagefilter_none',      'format_iccaformat'),
                        'grayscale' => get_string('imagefilter_grayscale', 'format_iccaformat'),
                        'sepia'     => get_string('imagefilter_sepia',     'format_iccaformat'),
                        'brighten'  => get_string('imagefilter_brighten',  'format_iccaformat'),
                    ]],
                ],
                'imageoverlay' => [
                    'label'        => get_string('imageoverlay', 'format_iccaformat'),
                    'element_type' => 'select',
                    'element_attributes' => [[
                        'none'  => get_string('imageoverlay_none',  'format_iccaformat'),
                        'light' => get_string('imageoverlay_light', 'format_iccaformat'),
                        'dark'  => get_string('imageoverlay_dark',  'format_iccaformat'),
                    ]],
                ],
                'imagebackgroundcolour' => [
                    'label'        => get_string('imagebackgroundcolour', 'format_iccaformat'),
                    'element_type' => 'text',
                    'help'           => 'imagebackgroundcolour',
                    'help_component' => 'format_iccaformat',
                ],
            ];

            $courseformatoptions = array_merge_recursive($courseformatoptions, $courseformatoptionsedit);
        }

        return $courseformatoptions;
    }

    /**
     * Validates course format options on edit form submit.
     *
     * @param array $data   Submitted form data.
     * @param array $files  Uploaded files.
     * @param array $errors Errors already found.
     * @return array
     */
    public function edit_form_validation($data, $files, $errors): array {
        $reterrors = [];

        // Validate border radius is within sensible range.
        if (isset($data['borderradius']) && ($data['borderradius'] < 0 || $data['borderradius'] > 24)) {
            $reterrors['borderradius'] = get_string('error_borderradius', 'format_iccaformat');
        }

        // Validate completion bar colour is a hex value if provided.
        if (!empty($data['completionbarcolour'])) {
            if (!preg_match('/^#[0-9a-fA-F]{3}([0-9a-fA-F]{3})?$/', $data['completionbarcolour'])) {
                $reterrors['completionbarcolour'] = get_string('error_completionbarcolour', 'format_iccaformat');
            }
        }

        return $reterrors;
    }

    // ---------------------------------------------------------------------------
    // Navigation.
    // ---------------------------------------------------------------------------

    /**
     * Extend course navigation — currently uses core defaults.
     * Breadcrumb rendering is handled in the output classes, not here.
     *
     * @param global_navigation  $navigation
     * @param navigation_node    $node
     */
    public function extend_course_navigation($navigation, navigation_node $node): void {
        global $PAGE;

        if ($navigation->includesectionnum === false) {
            $selectedsection = optional_param('section', null, PARAM_INT);
            if ($selectedsection !== null
                && (!defined('AJAX_SCRIPT') || AJAX_SCRIPT == '0')
                && $PAGE->url->compare(new moodle_url('/course/view.php'), URL_MATCH_BASE)
            ) {
                $navigation->includesectionnum = $selectedsection;
            }
        }

        parent::extend_course_navigation($navigation, $node);

        // Remove empty general section (section 0) from navigation.
        $modinfo  = get_fast_modinfo($this->get_course());
        $sections = $modinfo->get_sections();
        if (!isset($sections[0])) {
            $section = $modinfo->get_section_info(0);
            $generalsection = $node->get($section->id, navigation_node::TYPE_SECTION);
            if ($generalsection) {
                $generalsection->remove();
            }
        }
    }

    // ---------------------------------------------------------------------------
    // Section edit form options.
    // ---------------------------------------------------------------------------

    /**
     * Defines format options for individual sections.
     * These appear as a fieldset in the Edit section form (course/editsection.php).
     *
     * Moodle stores these in mdl_course_format_options keyed by sectionid.
     * We store image assignments in iccaformat_element_options separately,
     * but description_display is light enough to live here via Moodle's own
     * section options mechanism.
     *
     * @param  bool $foreditform Whether to include form element definitions.
     * @return array
     */
    // -------------------------------------------------------------------------
    // Section edit form options and image handling.
    // -------------------------------------------------------------------------

    /**
     * Defines format options for individual sections.
     * Moodle renders these automatically in the Edit section form.
     *
     * We declare:
     *   - description_display (select)
     *   - sectionimage (filemanager) — stores the draft itemid; actual file
     *     is moved to the element_image file area in update_section_format_options().
     *
     * @param  bool $foreditform
     * @return array
     */
    public function section_format_options($foreditform = false): array {
        $options = [

            'imageobjectfit' => [
                'default' => '',
                'type'    => PARAM_ALPHA,
            ],
            'imageobjectposition' => [
                'default' => '',
                'type'    => PARAM_TEXT,
            ],
            'imagefilter' => [
                'default' => '',
                'type'    => PARAM_ALPHA,
            ],
            'imageoverlay' => [
                'default' => '',
                'type'    => PARAM_ALPHA,
            ],
            'imagebackgroundcolour' => [
                'default' => '',
                'type'    => PARAM_TEXT,
            ],
        ];

        if ($foreditform) {


            $imagepositionopts = [
                ''             => get_string('coursesetting_default', 'format_iccaformat'),
                'center'       => get_string('imageposition_center',      'format_iccaformat'),
                'top'          => get_string('imageposition_top',         'format_iccaformat'),
                'bottom'       => get_string('imageposition_bottom',      'format_iccaformat'),
                'left'         => get_string('imageposition_left',        'format_iccaformat'),
                'right'        => get_string('imageposition_right',       'format_iccaformat'),
                'top left'     => get_string('imageposition_topleft',     'format_iccaformat'),
                'top right'    => get_string('imageposition_topright',    'format_iccaformat'),
                'bottom left'  => get_string('imageposition_bottomleft',  'format_iccaformat'),
                'bottom right' => get_string('imageposition_bottomright', 'format_iccaformat'),
            ];
            $imagefitorpts = [
                ''        => get_string('coursesetting_default',      'format_iccaformat'),
                'cover'   => get_string('imageobjectfit_cover',   'format_iccaformat'),
                'contain' => get_string('imageobjectfit_contain', 'format_iccaformat'),
                'fill'    => get_string('imageobjectfit_fill',    'format_iccaformat'),
            ];
            $imagefilteropts = [
                ''           => get_string('coursesetting_default',     'format_iccaformat'),
                'none'       => get_string('imagefilter_none',      'format_iccaformat'),
                'grayscale'  => get_string('imagefilter_grayscale', 'format_iccaformat'),
                'sepia'      => get_string('imagefilter_sepia',     'format_iccaformat'),
                'brighten'   => get_string('imagefilter_brighten',  'format_iccaformat'),
            ];
            $imageoverlayopts = [
                ''      => get_string('coursesetting_default',    'format_iccaformat'),
                'none'  => get_string('imageoverlay_none',  'format_iccaformat'),
                'light' => get_string('imageoverlay_light', 'format_iccaformat'),
                'dark'  => get_string('imageoverlay_dark',  'format_iccaformat'),
            ];

            $options['imageobjectfit']['label']              = get_string('imageobjectfit', 'format_iccaformat');
            $options['imageobjectfit']['element_type']       = 'select';
            $options['imageobjectfit']['element_attributes'] = [$imagefitorpts];

            $options['imageobjectposition']['label']              = get_string('imageobjectposition', 'format_iccaformat');
            $options['imageobjectposition']['element_type']       = 'select';
            $options['imageobjectposition']['element_attributes'] = [$imagepositionopts];

            // Filter and overlay are rendered as swatch pickers in create_edit_form_elements.
            // Keep as hidden here so Moodle saves the value; the visible UI is injected separately.
            $options['imagefilter']['label']        = '';
            $options['imagefilter']['element_type'] = 'hidden';

            $options['imageoverlay']['label']        = '';
            $options['imageoverlay']['element_type'] = 'hidden';

            $options['imagebackgroundcolour']['label']        = get_string('imagebackgroundcolour', 'format_iccaformat');
            $options['imagebackgroundcolour']['element_type'] = 'text';
        }

        return $options;
    }

    /**
     * Adds the card image picker to the section edit form.
     * Called by editsection_form::definition(). $forsection=true for section forms.
     */
    public function create_edit_form_elements(&$mform, $forsection = false): array {
        $elements = parent::create_edit_form_elements($mform, $forsection);
        if (!$forsection) {
            $courseid = $this->get_courseid();

            $current = $this->get_format_options();
            $fields  = ['imageobjectfit', 'imageobjectposition',
                        'imagebackgroundcolour', 'borderradius', 'completionbarshowin',
                        'completionbarcolour', 'cardsize', 'hoverstyle', 'hoverzoom',
                        'footerfontsize', 'dropdownfontsize',
                        'sectionprogbar', 'showactivitybar', 'showhomebutton', 'showcoursemap'];
            foreach ($fields as $field) {
                if (!empty($current[$field]) && $mform->elementExists($field)) {
                    $mform->getElement($field)->setValue($current[$field]);
                }
            }

            // Inject section headers via JS — insertElementBefore doesn't work reliably
            // for html elements in Moodle's course edit form renderer.
            $headermap = [
                'borderradius'        => get_string('admindefaults_cards',        'format_iccaformat'),
                'hoverstyle'          => get_string('admindefaults_hover',        'format_iccaformat'),
                'completionbarcolour' => get_string('admindefaults_completion',   'format_iccaformat'),
                'showhomebutton'      => get_string('admindefaults_navigation',   'format_iccaformat'),
                'imageobjectfit'      => get_string('admindefaults_image',        'format_iccaformat'),
                'footerfontsize'      => get_string('admindefaults_typography',   'format_iccaformat'),
            ];
            $js = 'document.addEventListener("DOMContentLoaded", function() {';
            foreach ($headermap as $field => $heading) {
                $escapedheading = addslashes($heading);
                $js .= '
                (function() {
                    var el = document.getElementById("fitem_id_' . $field . '");
                    if (el) {
                        var div = document.createElement("div");
                        div.style.cssText = "grid-column:1/-1;border-top:1px solid #dee2e6;margin:1.25rem 0 0.25rem;padding-top:0.5rem;";
                        div.innerHTML = "<strong style=\'font-size:0.95rem;color:#495057;\'>' . $escapedheading . '</strong>";
                        el.parentNode.insertBefore(div, el);
                    }
                })();';
            }
            $js .= '});';
            $mform->addElement('html', '<script>' . $js . '</script>');



            // Build swatch pickers for filter and overlay and set as static element values.
            $coursesvg = '<svg width="88" height="58" viewBox="0 0 90 60" xmlns="http://www.w3.org/2000/svg">'
                . '<rect width="90" height="60" fill="#e8f4fc"/>'
                . '<rect x="4" y="4" width="82" height="52" rx="3" fill="none" stroke="#a0b8c8" stroke-width="1.5"/>'
                . '<circle cx="22" cy="18" r="7" fill="#f5c518"/>'
                . '<polygon points="10,52 35,28 52,44 65,32 80,52" fill="#4caf50"/>'
                . '<polygon points="50,52 65,32 80,52" fill="#388e3c"/>'
                . '</svg>';

            $coursefilteropts  = ['none' => get_string('imagefilter_none','format_iccaformat'), 'grayscale' => get_string('imagefilter_grayscale','format_iccaformat'), 'sepia' => get_string('imagefilter_sepia','format_iccaformat'), 'brighten' => get_string('imagefilter_brighten','format_iccaformat')];
            $coursefiltercsses = ['none' => '', 'grayscale' => 'grayscale(100%)', 'sepia' => 'sepia(80%)', 'brighten' => 'brightness(135%)'];
            $courseoverlayopts  = ['none' => get_string('imageoverlay_none','format_iccaformat'), 'light' => get_string('imageoverlay_light','format_iccaformat'), 'dark' => get_string('imageoverlay_dark','format_iccaformat')];
            $courseoverlaycsses = ['none' => '', 'light' => 'background:rgba(255,255,255,0.4);', 'dark' => 'background:rgba(0,0,0,0.4);'];
            $curfilter  = $current['imagefilter']  ?: 'none';
            $curoverlay = $current['imageoverlay'] ?: 'none';

            // Replace filter/overlay selects with swatch pickers via JS.
            // The selects are already in the right position — JS hides them and
            // inserts swatch HTML immediately after, syncing back to the select on click.
            $filteroptsjs  = json_encode(['none' => get_string('imagefilter_none','format_iccaformat'), 'grayscale' => get_string('imagefilter_grayscale','format_iccaformat'), 'sepia' => get_string('imagefilter_sepia','format_iccaformat'), 'brighten' => get_string('imagefilter_brighten','format_iccaformat')]);
            $filtercssjs   = json_encode(['none' => '', 'grayscale' => 'grayscale(100%)', 'sepia' => 'sepia(80%)', 'brighten' => 'brightness(135%)']);
            $overlayoptsjs = json_encode(['none' => get_string('imageoverlay_none','format_iccaformat'), 'light' => get_string('imageoverlay_light','format_iccaformat'), 'dark' => get_string('imageoverlay_dark','format_iccaformat')]);
            $overlaycssjs  = json_encode(['none' => '', 'light' => 'background:rgba(255,255,255,0.4);', 'dark' => 'background:rgba(0,0,0,0.4);']);
            $svgjs         = json_encode($coursesvg);

            $mform->addElement('html', '<script>
(function() {
    var svg = ' . $svgjs . ';
    function buildSwatchRow(selectEl, opts, cssmap, overlaymap) {
        var cur = selectEl.value || Object.keys(opts)[0];
        var wrap = document.createElement("div");
        wrap.style.cssText = "display:flex;gap:8px;flex-wrap:wrap;margin:2px 0;";
        Object.keys(opts).forEach(function(val) {
            var lbl = document.createElement("label");
            lbl.style.cssText = "text-align:center;cursor:pointer;display:block;";
            var isactive = (val === cur);
            var box = document.createElement("span");
            box.setAttribute("data-val", val);
            box.style.cssText = "display:block;width:90px;height:60px;border-radius:6px;overflow:hidden;position:relative;cursor:pointer;"
                + "border:" + (isactive ? "2.5px solid #0d6efd" : "0.5px solid #dee2e6") + ";";
            var inner = document.createElement("span");
            inner.style.cssText = "display:block;width:100%;height:100%;" + (cssmap[val] ? "filter:" + cssmap[val] + ";" : "");
            inner.innerHTML = svg;
            box.appendChild(inner);
            if (overlaymap && overlaymap[val]) {
                var ov = document.createElement("span");
                ov.style.cssText = "position:absolute;inset:0;" + overlaymap[val];
                box.appendChild(ov);
            }
            var lbltxt = document.createElement("span");
            lbltxt.style.cssText = "display:block;font-size:11px;margin-top:3px;color:" + (isactive ? "#084298" : "#6c757d") + ";font-weight:" + (isactive ? "500" : "normal") + ";";
            lbltxt.textContent = opts[val];
            lbl.appendChild(box);
            lbl.appendChild(lbltxt);
            box.addEventListener("click", function() {
                selectEl.value = val;
                wrap.querySelectorAll("[data-val]").forEach(function(b) {
                    b.style.border = "0.5px solid #dee2e6";
                    b.nextElementSibling && (b.nextElementSibling.style.color = "#6c757d") && (b.nextElementSibling.style.fontWeight = "normal");
                });
                wrap.querySelectorAll("label span:last-child").forEach(function(s) {
                    s.style.color = "#6c757d"; s.style.fontWeight = "normal";
                });
                box.style.border = "2.5px solid #0d6efd";
                lbltxt.style.color = "#084298"; lbltxt.style.fontWeight = "500";
            });
            wrap.appendChild(lbl);
        });
        return wrap;
    }
    function replaceSelect(id, opts, cssmap, overlaymap) {
        var sel = document.getElementById(id);
        if (!sel) return;
        sel.style.display = "none";
        var swatches = buildSwatchRow(sel, opts, cssmap, overlaymap);
        sel.parentNode.insertBefore(swatches, sel.nextSibling);
    }
    function init() {
        replaceSelect("id_imagefilter",  ' . $filteroptsjs . ', ' . $filtercssjs . ', null);
        replaceSelect("id_imageoverlay", ' . $overlayoptsjs . ', {}, ' . $overlaycssjs . ');
    }
    if (document.readyState === "loading") { document.addEventListener("DOMContentLoaded", init); }
    else { setTimeout(init, 100); }
})();
</script>');

            // Add colour pickers next to hex text fields.
            $bgval  = $current['imagebackgroundcolour'] ?? '#ffffff';
            $bgval  = $bgval ?: '#ffffff';
            $barval = $current['completionbarcolour'] ?? '#0d3c6f';
            $barval = $barval ?: '#0d3c6f';
            $hoverval = $current['hovercolour'] ?? '#0d3c6f';
            $hoverval = $hoverval ?: '#0d3c6f';
            $mform->addElement('html', '
<script>
window.addEventListener("load", function() {
    function addColourPicker(fieldId, initialVal) {
        var textEl = document.getElementById(fieldId);
        if (!textEl) return;
        textEl.style.fontFamily = "monospace";
        textEl.style.width = "110px";
        textEl.style.display = "inline-block";
        textEl.style.verticalAlign = "middle";
        var picker = document.createElement("input");
        picker.type = "color";
        picker.value = initialVal;
        picker.style.cssText = "width:38px;height:38px;padding:2px;border:1px solid #ced4da;border-radius:4px;cursor:pointer;margin-left:6px;vertical-align:middle;flex-shrink:0;";
        textEl.parentNode.insertBefore(picker, textEl.nextSibling);
        picker.addEventListener("input", function() { textEl.value = this.value; });
        textEl.addEventListener("input", function() {
            var v = this.value.trim();
            if (/^#[0-9a-fA-F]{6}$/.test(v)) picker.value = v;
        });
        var cur = textEl.value.trim();
        if (/^#[0-9a-fA-F]{6}$/.test(cur)) picker.value = cur;
        else picker.value = initialVal;
    }
    addColourPicker("id_imagebackgroundcolour", ' . json_encode($bgval) . ');
    addColourPicker("id_completionbarcolour",   ' . json_encode($barval) . ');
    addColourPicker("id_hovercolour",           ' . json_encode($hoverval) . ');

    // Show hovercolour only when shadow is selected, hoverzoom only when imgzoom.
    function syncHoverFields() {
        var style = document.getElementById("id_hoverstyle");
        if (!style) return;
        var val = style.value;
        var zoomRow   = document.getElementById("fitem_id_hoverzoom");
        var colourRow = document.getElementById("fitem_id_hovercolour");
        if (zoomRow)   zoomRow.style.display   = (val === "imgzoom") ? "" : "none";
        if (colourRow) colourRow.style.display  = (val === "shadow")  ? "" : "none";
    }
    syncHoverFields();
    var styleEl = document.getElementById("id_hoverstyle");
    if (styleEl) styleEl.addEventListener("change", syncHoverFields);
});
</script>');
            return $elements;
        }
        $courseid  = $this->get_courseid();

        // Get sectionid from URL — more reliable than reading the form element value
        // since getValue() can return an array before the form is submitted.
        $sectionid = optional_param('id', 0, PARAM_INT); // editsection.php passes ?id=sectionid
        $optioncache    = $sectionid ? \format_iccaformat\local\format_option::get_all_for_course($courseid) : [];
        $currentsource  = \format_iccaformat\local\format_option::get_from_cache($optioncache, $sectionid, \format_iccaformat\local\format_option::OPT_IMAGE_SOURCE, '');
        $currentimageid = (int) \format_iccaformat\local\format_option::get_from_cache($optioncache, $sectionid, \format_iccaformat\local\format_option::OPT_IMAGE_ID, 0);
        $sec_iconenabled  = \format_iccaformat\local\format_option::get_from_cache($optioncache, $sectionid, \format_iccaformat\local\format_option::OPT_ICON_ENABLED, null);
        $sec_labelenabled = \format_iccaformat\local\format_option::get_from_cache($optioncache, $sectionid, \format_iccaformat\local\format_option::OPT_LABEL_ENABLED, null);
        $sec_labeloverride = \format_iccaformat\local\format_option::get_from_cache($optioncache, $sectionid, \format_iccaformat\local\format_option::OPT_LABEL_OVERRIDE, '');
        $sec_iconoverride  = \format_iccaformat\local\format_option::get_from_cache($optioncache, $sectionid, \format_iccaformat\local\format_option::OPT_ICON_OVERRIDE, '');

        // Force image display option values from element_options onto the section form selects.
        // Moodle renders these from section_format_options but reads defaults not element_options.
        $secdisplayfields = ['imageobjectfit', 'imageobjectposition', 'imagefilter', 'imageoverlay', 'imagebackgroundcolour'];
        foreach ($secdisplayfields as $field) {
            $val = \format_iccaformat\local\format_option::get_from_cache($optioncache, $sectionid, $field, '');
            if ($val !== '' && $mform->elementExists($field)) {
                $mform->getElement($field)->setValue($val);
            }
        }

        $libraryimages  = \format_iccaformat\local\image_library::get_all_for_course($courseid);
        $courseimages   = $libraryimages['course'];
        $siteimages     = $libraryimages['site'];
        $libpageurl     = (new \moodle_url('/course/format/iccaformat/imagelibrary.php', ['courseid' => $courseid]))->out(false);
        $coursecontext  = \context_course::instance($courseid);

        // Header.
        $elements[] = $mform->addElement('header', 'iccaformat_sec_img_header',
            get_string('cardsettings_image', 'format_iccaformat'));

        // Current image preview.
        $previewurl = '';
        if ($currentsource === 'upload' && $sectionid) {
            $fs2   = get_file_storage();
            $exist = $fs2->get_area_files($coursecontext->id, 'format_iccaformat', 'element_image', $sectionid, 'itemid', false);
            if (!empty($exist)) {
                $ef = reset($exist);
                $previewurl = \moodle_url::make_pluginfile_url($coursecontext->id, 'format_iccaformat', 'element_image', $sectionid, '/', $ef->get_filename())->out(false);
            }
        } else if ($currentsource === 'library' && $currentimageid) {
            $librecord = \format_iccaformat\local\image_library::get($currentimageid);
            if ($librecord) {
                $previewurl = \format_iccaformat\local\image_library::get_url($librecord);
            }
        }
        if ($previewurl) {
            $elements[] = $mform->addElement('static', 'iccaformat_sec_preview', '',
                '<div class="mb-2"><p class="small text-muted mb-1">' . get_string('cardsettings_currentimage', 'format_iccaformat') . '</p>'
                . '<img src="' . $previewurl . '" style="max-width:200px;max-height:120px;object-fit:cover;border-radius:6px;border:1px solid rgba(0,0,0,0.1);"></div>');
        }

        // ── Image picker — unified three-way UI (section form) ───────────────
        $draftitemid = 0;
        if ($sectionid) {
            file_prepare_draft_area($draftitemid, $coursecontext->id, 'format_iccaformat',
                'element_image', $sectionid, ['maxbytes' => 5 * 1024 * 1024, 'maxfiles' => 1]);
        }

        $isnoimage  = ($currentsource === 'none');
        $isdefault  = ($currentsource === '' || $currentsource === 'default');
        $isupload   = ($currentsource === 'upload');
        $islib      = !$isnoimage && !$isdefault && !$isupload;

        $thumbfn2 = function($img, $checked) {
            $url    = \format_iccaformat\local\image_library::get_url($img);
            $border = $checked ? '2.5px solid #0d3c6f' : '1px solid #dee2e6';
            $tick   = $checked ? '<span style="position:absolute;top:4px;right:4px;background:#0d3c6f;color:#fff;border-radius:50%;width:20px;height:20px;display:flex;align-items:center;justify-content:center;font-size:11px;">✓</span>' : '';
            $h  = '<div class="icca-picker-tile' . ($checked ? ' icca-picker-selected' : '') . '"'
                . ' onclick="iccaSecPickerSelectImg(' . (int)$img->id . ', this)"'
                . ' style="border-radius:8px;overflow:hidden;border:' . $border . ';background:#f8f9fa;'
                . 'position:relative;cursor:pointer;transition:border-color 0.15s,box-shadow 0.15s;">';
            $h .= '<img src="' . $url . '" alt="' . s($img->name) . '"'
                . ' style="width:100%;height:80px;object-fit:cover;display:block;">';
            $h .= $tick;
            $h .= '<div style="padding:5px 7px;background:#fff;border-top:1px solid #f0f0f0;">'
                . '<div style="font-size:0.8rem;color:#495057;white-space:nowrap;overflow:hidden;text-overflow:ellipsis;font-weight:500;">'
                . s($img->name) . '</div></div>';
            $h .= '</div>';
            return $h;
        };

        $coursethumbshtml = '';
        if (!empty($courseimages)) {
            foreach ($courseimages as $img) {
                $coursethumbshtml .= $thumbfn2($img, $currentsource === 'library' && $currentimageid === (int)$img->id);
            }
        } else {
            $coursethumbshtml = '<p style="font-size:0.85rem;color:#adb5bd;padding:0.5rem 0;">'
                . get_string('imagelibrary_empty', 'format_iccaformat') . '</p>';
        }

        $sitethumbshtml = '';
        if (!empty($siteimages)) {
            foreach ($siteimages as $img) {
                $sitethumbshtml .= $thumbfn2($img, $currentsource === 'library' && $currentimageid === (int)$img->id);
            }
        }

        $tabact = 'background:#e8f0fb;color:#0d3c6f;border-color:#9ec5fe;font-weight:500;';
        $tabina = 'background:transparent;color:#6c757d;border-color:#dee2e6;';
        $tabcss = 'padding:6px 14px;border-radius:6px;font-size:1rem;cursor:pointer;border:1px solid;';

        $sh  = '<div class="format-iccaformat-imgpicker" style="margin-bottom:0.5rem;">';

        // Mode tabs.
        $sh .= '<div style="display:flex;gap:6px;margin-bottom:12px;">';
        $sh .= '<button type="button" id="icca_sec_btn_library" onclick="iccaSecPickerMode(\'library\')"'
            . ' style="' . $tabcss . ($islib ? $tabact : $tabina) . '">'
            . get_string('card_image_source_library', 'format_iccaformat') . '</button>';
        $sh .= '<button type="button" id="icca_sec_btn_upload" onclick="iccaSecPickerMode(\'upload\')"'
            . ' style="' . $tabcss . ($isupload ? $tabact : $tabina) . '">'
            . '&#8593; ' . get_string('card_image_source_upload', 'format_iccaformat') . '</button>';
        $sh .= '<button type="button" id="icca_sec_btn_default" onclick="iccaSecPickerMode(\'default\')"'
            . ' style="' . $tabcss . ($isdefault ? $tabact : $tabina) . '">'
            . get_string('card_image_source_default', 'format_iccaformat') . '</button>';
        $sh .= '<button type="button" id="icca_sec_btn_none" onclick="iccaSecPickerMode(\'none\')"'
            . ' style="' . $tabcss . ($isnoimage ? $tabact : $tabina) . '">'
            . '&#8960; ' . get_string('card_image_source_noimage', 'format_iccaformat') . '</button>';
        $sh .= '</div>';

        // Library panel.
        $sh .= '<div id="icca_sec_library" style="display:' . ($islib ? 'block' : 'none') . ';">';
        $sh .= '<div style="font-size:0.75rem;font-weight:600;color:#6c757d;text-transform:uppercase;'
            . 'letter-spacing:0.07em;margin-bottom:8px;padding-bottom:5px;border-bottom:1px solid #dee2e6;'
            . 'display:flex;justify-content:space-between;">'
            . get_string('formatlibrary_course', 'format_iccaformat')
            . '<span style="font-weight:normal;">' . count($courseimages) . ' ' . get_string('formatlibrary_imagecount', 'format_iccaformat') . '</span>'
            . '</div>';
        $sh .= '<div style="display:grid;grid-template-columns:repeat(auto-fill,minmax(120px,1fr));gap:10px;margin-bottom:12px;">'
            . $coursethumbshtml . '</div>';

        if (!empty($siteimages)) {
            $sh .= '<div style="font-size:0.75rem;font-weight:600;color:#6c757d;text-transform:uppercase;'
                . 'letter-spacing:0.07em;margin-bottom:8px;padding-bottom:5px;border-bottom:1px solid #dee2e6;'
                . 'display:flex;justify-content:space-between;cursor:pointer;" onclick="iccaSecToggleFormatLib(this)">'
                . '<span><i class="fa fa-chevron-right" id="icca-sec-formatchev" style="font-size:10px;transition:transform 0.2s;margin-right:4px;"></i>'
                . get_string('formatlibrary_site', 'format_iccaformat') . '</span>'
                . '<span style="font-weight:normal;">' . count($siteimages) . ' ' . get_string('formatlibrary_imagecount', 'format_iccaformat') . '</span>'
                . '</div>';
            $sh .= '<div id="icca_sec_formatlibgrid" style="display:none;grid-template-columns:repeat(auto-fill,minmax(120px,1fr));gap:10px;margin-bottom:12px;opacity:0.8;">'
                . $sitethumbshtml . '</div>';
        }

        $sh .= '<a href="' . $libpageurl . '" target="_blank"'
            . ' style="font-size:0.85rem;color:#0d3c6f;text-decoration:none;display:inline-flex;align-items:center;gap:4px;">'
            . '<i class="fa fa-external-link" style="font-size:11px;"></i> '
            . get_string('imagelibrary', 'format_iccaformat') . '</a>';
        $sh .= '</div>';

        // Upload panel.
        $sh .= '<div id="icca_sec_upload" style="display:' . ($isupload ? 'block' : 'none') . ';">';
        $sh .= '<div style="border:1.5px dashed #dee2e6;border-radius:8px;padding:1.5rem;'
            . 'text-align:center;background:#f8f9fa;color:#adb5bd;">'
            . '<i class="fa fa-image" style="font-size:28px;display:block;margin-bottom:8px;"></i>'
            . '<p style="font-size:0.9rem;margin:0;">' . get_string('cardsettings_acceptedtypes', 'format_iccaformat') . '</p>'
            . '</div>';
        $sh .= '</div>';

        // No image panel.
        $sh .= '<div id="icca_sec_default" style="display:' . ($isdefault ? 'block' : 'none') . ';padding:1rem;text-align:center;color:#6c757d;">'
        . '<i class="fa fa-magic" style="font-size:24px;display:block;margin-bottom:8px;"></i>'
        . '<p style="font-size:0.9rem;margin:0;">Card will use the default image from the activity card default rule for this module type.</p>'
        . '</div>';
        $sh .= '<div id="icca_sec_noimage" style="display:' . ($isnoimage ? 'block' : 'none') . ';">';;
        $sh .= '<div style="border:1.5px dashed #dee2e6;border-radius:8px;padding:1.5rem;'
            . 'text-align:center;background:#f8f9fa;color:#adb5bd;">'
            . '<i class="fa fa-ban" style="font-size:28px;display:block;margin-bottom:8px;"></i>'
            . '<p style="font-size:0.9rem;margin:0;">No image — card will show background colour only.</p>'
            . '</div>';
        $sh .= '</div>';

        $sh .= '</div>';

        $sh .= '<script>
function iccaSecPickerMode(mode) {
    var panels = {library:"icca_sec_library", upload:"icca_sec_upload", default:"icca_sec_default", none:"icca_sec_noimage"};
    var btns   = {library:"icca_sec_btn_library", upload:"icca_sec_btn_upload", default:"icca_sec_btn_default", none:"icca_sec_btn_none"};
    var srcHidden = document.querySelector("input[name=\'iccaformat_sec_imagesource\']");
    var act = "background:#e8f0fb;color:#0d3c6f;border-color:#9ec5fe;font-weight:500;border-style:solid;";
    var ina = "background:transparent;color:#6c757d;border-color:#dee2e6;font-weight:normal;border-style:solid;";
    Object.keys(panels).forEach(function(m) {
        var p = document.getElementById(panels[m]);
        var b = document.getElementById(btns[m]);
        if (p) p.style.display = (m === mode) ? "block" : "none";
        if (b) {
            b.style.cssText = b.style.cssText.replace(/background:[^;]+;|color:[^;]+;|border-color:[^;]+;|font-weight:[^;]+;|border-style:[^;]+;/g, "");
            b.style.cssText += (m === mode) ? act : ina;
        }
    });
    if (srcHidden) srcHidden.value = mode;
    var fpItem = document.getElementById("fitem_id_iccaformat_sec_imagefile");
    if (fpItem) fpItem.style.display = (mode === "upload") ? "" : "none";
    // Clear imageid when not in library mode.
    if (mode !== "library") {
        var idHidden = document.querySelector("input[name=\"iccaformat_sec_imageid\"]");
        if (idHidden) idHidden.value = 0;
    }
}
function iccaSecPickerSelectImg(imgid, tile) {
    document.querySelectorAll(".icca-picker-tile").forEach(function(t) {
        t.style.border = "1px solid #dee2e6";
        t.style.boxShadow = "";
        var tick = t.querySelector(".icca-picker-tick");
        if (tick) tick.remove();
    });
    tile.style.border = "2.5px solid #0d3c6f";
    tile.style.boxShadow = "0 2px 8px rgba(0,0,0,0.1)";
    var tick = document.createElement("span");
    tick.className = "icca-picker-tick";
    tick.style.cssText = "position:absolute;top:4px;right:4px;background:#0d3c6f;color:#fff;border-radius:50%;width:20px;height:20px;display:flex;align-items:center;justify-content:center;font-size:11px;";
    tick.textContent = "✓";
    tile.appendChild(tick);
    var idHidden = document.querySelector("input[name=\"iccaformat_sec_imageid\"]");
    if (idHidden) idHidden.value = imgid;
    var srcHidden = document.querySelector("input[name=\'iccaformat_sec_imagesource\']");
    if (srcHidden) srcHidden.value = "library";
}
function iccaSecToggleFormatLib(header) {
    var grid = document.getElementById("icca_sec_formatlibgrid");
    var chev = document.getElementById("icca-sec-formatchev");
    if (!grid) return;
    var open = grid.style.display !== "none";
    grid.style.display = open ? "none" : "grid";
    if (chev) chev.style.transform = open ? "" : "rotate(90deg)";
}
window.addEventListener("load", function() {
    setTimeout(function() {
        var fpItem = document.getElementById("fitem_id_iccaformat_sec_imagefile");
        if (fpItem) fpItem.style.display = "none";
    }, 300);
});
</script>';

        $elements[] = $mform->addElement('static', 'iccaformat_sec_imagepicker', '', $sh);
        $elements[] = $mform->addElement('hidden', 'iccaformat_sec_imagesource',
            $isnoimage ? 'none' : ($isdefault ? 'default' : ($isupload ? 'upload' : 'library')));
        $mform->setType('iccaformat_sec_imagesource', PARAM_ALPHA);
        $elements[] = $mform->addElement('hidden', 'iccaformat_sec_imageid', $currentimageid);
        $mform->setType('iccaformat_sec_imageid', PARAM_INT);
        $elements[] = $mform->addElement('filepicker', 'iccaformat_sec_imagefile',
            get_string('card_image_source_upload', 'format_iccaformat'), null,
            ['maxbytes' => 5 * 1024 * 1024, 'accepted_types' => ['.jpg','.jpeg','.png','.webp','.gif','.svg']]);
        $mform->setDefault('iccaformat_sec_imagefile', $draftitemid);

        // ── Filter and overlay swatch pickers ─────────────────────────────
        $filtersvg2 = '<svg width="88" height="58" viewBox="0 0 90 60" xmlns="http://www.w3.org/2000/svg">'
            . '<rect width="90" height="60" fill="#e8f4fc"/>'
            . '<rect x="4" y="4" width="82" height="52" rx="3" fill="none" stroke="#a0b8c8" stroke-width="1.5"/>'
            . '<circle cx="22" cy="18" r="7" fill="#f5c518"/>'
            . '<polygon points="10,52 35,28 52,44 65,32 80,52" fill="#4caf50"/>'
            . '<polygon points="50,52 65,32 80,52" fill="#388e3c"/>'
            . '</svg>';

        $secfilteropts = [
            ''          => get_string('coursesetting_default', 'format_iccaformat'),
            'none'      => get_string('imagefilter_none',      'format_iccaformat'),
            'grayscale' => get_string('imagefilter_grayscale', 'format_iccaformat'),
            'sepia'     => get_string('imagefilter_sepia',     'format_iccaformat'),
            'brighten'  => get_string('imagefilter_brighten',  'format_iccaformat'),
        ];
        $secfiltercsses = [
            '' => '', 'none' => '',
            'grayscale' => 'grayscale(100%)',
            'sepia'     => 'sepia(80%)',
            'brighten'  => 'brightness(135%)',
        ];
        $secoverlayopts = [
            ''      => get_string('coursesetting_default', 'format_iccaformat'),
            'none'  => get_string('imageoverlay_none',  'format_iccaformat'),
            'light' => get_string('imageoverlay_light', 'format_iccaformat'),
            'dark'  => get_string('imageoverlay_dark',  'format_iccaformat'),
        ];
        $secoverlaycsses = [
            '' => '', 'none' => '',
            'light' => 'background:rgba(255,255,255,0.4);',
            'dark'  => 'background:rgba(0,0,0,0.4);',
        ];

        $secfilterval  = \format_iccaformat\local\format_option::get_from_cache(
            $optioncache, $sectionid, 'imagefilter', '');
        $secoverlayval = \format_iccaformat\local\format_option::get_from_cache(
            $optioncache, $sectionid, 'imageoverlay', '');

        $elements[] = $mform->addElement('static', 'imagefilter_swatches',
            get_string('imagefilter', 'format_iccaformat'),
            format_iccaformat_swatch_picker('sec_filter', 'imagefilter',
                $secfilteropts, $secfilterval, $filtersvg2, [], $secfiltercsses));

        $elements[] = $mform->addElement('static', 'imageoverlay_swatches',
            get_string('imageoverlay', 'format_iccaformat'),
            format_iccaformat_swatch_picker('sec_overlay', 'imageoverlay',
                $secoverlayopts, $secoverlayval, $filtersvg2, $secoverlaycsses, []));

        // Add colour picker swatch next to imagebackgroundcolour text field.
        $elements[] = $mform->addElement('html', '
<script>
window.addEventListener("load", function() {
    setTimeout(function() {
        var textEl = document.getElementById("id_imagebackgroundcolour");
        if (!textEl) return;
        textEl.style.fontFamily = "monospace";
        textEl.style.width = "110px";
        var picker = document.createElement("input");
        picker.type = "color";
        picker.value = "#ffffff";
        picker.style.cssText = "width:48px;height:36px;padding:2px;border:1px solid #ced4da;border-radius:4px;cursor:pointer;margin-left:6px;vertical-align:middle;";
        textEl.parentNode.insertBefore(picker, textEl.nextSibling);
        picker.addEventListener("input", function() { textEl.value = this.value; });
        textEl.addEventListener("input", function() {
            var v = this.value.trim();
            if (/^#[0-9a-fA-F]{6}$/.test(v)) picker.value = v;
        });
        var cur = textEl.value.trim();
        if (/^#[0-9a-fA-F]{6}$/.test(cur)) picker.value = cur;
    }, 300);
});
</script>');

        // Card overrides — unlock pattern.
        // Each field only stores a value when explicitly overridden.
        $sec_icon_overridden   = ($sec_iconenabled !== null);
        $sec_label_overridden  = ($sec_labelenabled !== null);
        $sec_cicon_overridden  = ($sec_iconoverride !== '');
        $sec_clabel_overridden = ($sec_labeloverride !== '');
        $sec_flipped           = \format_iccaformat\local\format_option::get_from_cache($optioncache, $sectionid, \format_iccaformat\local\format_option::OPT_ICON_LABEL_FLIPPED, null);
        $sec_flip_overridden   = ($sec_flipped !== null);

        $explainer = '<div style="margin-top:8px;">'
            . '<span style="font-size:1rem;font-weight:500;color:#495057;">'
            . get_string('card_overrides_explainer', 'format_iccaformat') . '</span>'
            . '<p style="font-size:0.9rem;color:#6c757d;margin:2px 0 0;">'
            . get_string('card_overrides_explainer_desc', 'format_iccaformat') . '</p>'
            . '</div>';
        $overridejs = iccaformat_build_override_js('sec');
        $overridehtml = iccaformat_build_override_html('sec', [
            'icon'         => ['label' => get_string('card_icon_enabled',    'format_iccaformat'), 'type' => 'showhide',  'overridden' => $sec_icon_overridden,   'val' => $sec_iconenabled ?? '1',    'ovr_field' => 'iccaformat_sec_icon_enabled_override',  'val_field' => 'iccaformat_sec_icon_enabled'],
            'label'        => ['label' => get_string('card_label_enabled',   'format_iccaformat'), 'type' => 'showhide',  'overridden' => $sec_label_overridden,  'val' => $sec_labelenabled ?? '1',   'ovr_field' => 'iccaformat_sec_label_enabled_override', 'val_field' => 'iccaformat_sec_label_enabled'],
            'customicon'   => ['label' => get_string('card_icon_override',   'format_iccaformat'), 'type' => 'text',      'overridden' => $sec_cicon_overridden,  'val' => $sec_iconoverride,          'ovr_field' => 'iccaformat_sec_cicon_override_set',     'val_field' => 'iccaformat_sec_icon_override'],
            'customlabel'  => ['label' => get_string('card_label_override',  'format_iccaformat'), 'type' => 'text',      'overridden' => $sec_clabel_overridden, 'val' => $sec_labeloverride,         'ovr_field' => 'iccaformat_sec_clabel_override_set',    'val_field' => 'iccaformat_sec_label_override'],
            'flipped'      => ['label' => get_string('card_flip',            'format_iccaformat'), 'type' => 'showhide',  'overridden' => $sec_flip_overridden,   'val' => $sec_flipped ?? '0',        'ovr_field' => 'iccaformat_sec_flip_override_set',      'val_field' => 'iccaformat_sec_flipped',        'poslabel' => 'Yes', 'neglabel' => 'No'],
        ]);
        $elements[] = $mform->addElement('static', 'iccaformat_sec_overrides', '', $explainer . $overridehtml . $overridejs);

        // Register hidden fields with Moodle so they pass through form submission.
        $elements[] = $mform->addElement('hidden', 'iccaformat_sec_icon_enabled_override',  $sec_icon_overridden  ? '1' : '');
        $elements[] = $mform->addElement('hidden', 'iccaformat_sec_label_enabled_override', $sec_label_overridden ? '1' : '');
        $elements[] = $mform->addElement('hidden', 'iccaformat_sec_cicon_override_set',     $sec_cicon_overridden  ? '1' : '');
        $elements[] = $mform->addElement('hidden', 'iccaformat_sec_clabel_override_set',    $sec_clabel_overridden ? '1' : '');
        $elements[] = $mform->addElement('hidden', 'iccaformat_sec_flip_override_set',      $sec_flip_overridden  ? '1' : '');
        $elements[] = $mform->addElement('hidden', 'iccaformat_sec_icon_enabled',   $sec_iconenabled  ?? '');
        $elements[] = $mform->addElement('hidden', 'iccaformat_sec_label_enabled',  $sec_labelenabled ?? '');
        $elements[] = $mform->addElement('hidden', 'iccaformat_sec_icon_override',  $sec_iconoverride);
        $elements[] = $mform->addElement('hidden', 'iccaformat_sec_label_override', $sec_labeloverride);
        $elements[] = $mform->addElement('hidden', 'iccaformat_sec_flipped',        $sec_flipped ?? '');
        foreach (['iccaformat_sec_icon_enabled_override', 'iccaformat_sec_label_enabled_override',
                  'iccaformat_sec_cicon_override_set', 'iccaformat_sec_clabel_override_set',
                  'iccaformat_sec_flip_override_set',
                  'iccaformat_sec_icon_enabled', 'iccaformat_sec_label_enabled',
                  'iccaformat_sec_icon_override', 'iccaformat_sec_label_override',
                  'iccaformat_sec_flipped'] as $fname) {
            $mform->setType($fname, PARAM_TEXT);
        }


        return $elements;
    }

    /**
     * Saves section format options after the edit section form is submitted.
     * Moves the uploaded file from draft area to element_image file area.
     *
     * @param  stdClass $data Submitted form data.
     * @return bool
     */
    public function update_section_format_options($data): bool {
        global $DB, $USER;

        $dataarray = (array) $data;
        $sectionid = (int) ($dataarray['id'] ?? 0);
        $courseid  = (int) $this->get_courseid();

        // Let parent save description_display first.
        $result = parent::update_section_format_options($data);

        if (!$sectionid || !$courseid) {
            return $result;
        }

        $context = \context_course::instance($courseid);
        $section = $DB->get_record('course_sections', ['id' => $sectionid, 'course' => $courseid]);
        if (!$section) {
            return $result;
        }

        $etype = ($section->component === 'mod_subsection')
            ? \format_iccaformat\local\format_option::ELEMENT_SUBSECTION
            : \format_iccaformat\local\format_option::ELEMENT_SECTION;

        $imagesource = $dataarray['iccaformat_sec_imagesource'] ?? '';
        $imageid     = (int) ($dataarray['iccaformat_sec_imageid'] ?? 0);

        if ($imagesource === 'library' && $imageid) {
            // Library image selected.
            \format_iccaformat\local\format_option::set($courseid, $etype, $sectionid,
                \format_iccaformat\local\format_option::OPT_IMAGE_SOURCE, 'library');
            \format_iccaformat\local\format_option::set($courseid, $etype, $sectionid,
                \format_iccaformat\local\format_option::OPT_IMAGE_ID, $imageid);

        } else if ($imagesource === 'upload') {
            // File upload — save to course library and link as library image.
            // This keeps a single source of truth for all images.
            $draftitemid = (int) ($dataarray['iccaformat_sec_imagefile'] ?? 0);

            if ($draftitemid) {
                $usercontext = \context_user::instance($USER->id);
                $fs          = get_file_storage();
                $draftfiles  = $fs->get_area_files($usercontext->id, 'user', 'draft',
                    $draftitemid, 'itemid', false);

                if (!empty($draftfiles)) {
                    $draftfile = reset($draftfiles);
                    $mimetype  = $draftfile->get_mimetype();
                    $accepted  = ['image/jpeg','image/png','image/webp','image/gif','image/svg+xml'];

                    if (in_array($mimetype, $accepted)) {
                        $imgname = pathinfo($draftfile->get_filename(), PATHINFO_FILENAME);
                        // Save to course library via save_new().
                        $newlibid = \format_iccaformat\local\image_library::save_new(
                            $imgname, '', $draftitemid, $context->id, $USER->id
                        );
                        if ($newlibid) {
                            // Link card to library record.
                            \format_iccaformat\local\format_option::set($courseid, $etype, $sectionid,
                                \format_iccaformat\local\format_option::OPT_IMAGE_SOURCE, 'library');
                            \format_iccaformat\local\format_option::set($courseid, $etype, $sectionid,
                                \format_iccaformat\local\format_option::OPT_IMAGE_ID, $newlibid);
                            // Clean up any old element_image file.
                            $fs->delete_area_files($context->id, 'format_iccaformat', 'element_image', $sectionid);
                        }
                    }
                } else {
                    // No new file — preserve existing.
                    \format_iccaformat\local\format_option::set($courseid, $etype, $sectionid,
                        \format_iccaformat\local\format_option::OPT_IMAGE_SOURCE, 'upload');
                }
            }

        } else if ($imagesource === 'none') {
            // Explicit no image.
            $fs = get_file_storage();
            $fs->delete_area_files($context->id, 'format_iccaformat', 'element_image', $sectionid);
            \format_iccaformat\local\format_option::set($courseid, $etype, $sectionid,
                \format_iccaformat\local\format_option::OPT_IMAGE_SOURCE, 'none');
            \format_iccaformat\local\format_option::set($courseid, $etype, $sectionid,
                \format_iccaformat\local\format_option::OPT_IMAGE_ID, 0);

        } else {
            // 'default' or '' — use default from rule.
            $fs = get_file_storage();
            $fs->delete_area_files($context->id, 'format_iccaformat', 'element_image', $sectionid);
            \format_iccaformat\local\format_option::set($courseid, $etype, $sectionid,
                \format_iccaformat\local\format_option::OPT_IMAGE_SOURCE, 'default');
            \format_iccaformat\local\format_option::set($courseid, $etype, $sectionid,
                \format_iccaformat\local\format_option::OPT_IMAGE_ID, 0);
        }

        // Save image display options to element_options so they override course defaults.
        // These also get saved to course_format_options by parent, but we store in
        // element_options so the resolve chain in content.php finds them per-element.
        $validfit      = ['', 'cover', 'contain', 'fill'];
        $validposition = ['', 'center', 'top', 'bottom', 'left', 'right',
                          'top left', 'top right', 'bottom left', 'bottom right'];
        $validfilter   = ['', 'none', 'grayscale', 'sepia', 'brighten'];
        $validoverlay  = ['', 'none', 'light', 'dark'];

        $fit      = $dataarray['imageobjectfit']      ?? '';
        $position = $dataarray['imageobjectposition'] ?? '';
        $filter   = $dataarray['imagefilter']         ?? '';
        $overlay  = $dataarray['imageoverlay']        ?? '';
        $bgcolour = trim($dataarray['imagebackgroundcolour'] ?? '');

        if (in_array($fit, $validfit)) {
            \format_iccaformat\local\format_option::set($courseid, $etype, $sectionid, 'imageobjectfit', $fit);
        }
        if (in_array($position, $validposition)) {
            \format_iccaformat\local\format_option::set($courseid, $etype, $sectionid, 'imageobjectposition', $position);
        }
        if (in_array($filter, $validfilter)) {
            \format_iccaformat\local\format_option::set($courseid, $etype, $sectionid, 'imagefilter', $filter);
        }
        if (in_array($overlay, $validoverlay)) {
            \format_iccaformat\local\format_option::set($courseid, $etype, $sectionid, 'imageoverlay', $overlay);
        }
        if (preg_match('/^#[0-9a-fA-F]{3,6}$/', $bgcolour) || $bgcolour === '') {
            \format_iccaformat\local\format_option::set($courseid, $etype, $sectionid, 'imagebackgroundcolour', $bgcolour);
        }

        // Save icon_enabled and label_enabled — only if explicitly overridden.
        $iconoverrideset  = !empty($dataarray['iccaformat_sec_icon_enabled_override']);
        $labeloverrideset = !empty($dataarray['iccaformat_sec_label_enabled_override']);
        if ($iconoverrideset) {
            $iconenabled = ($dataarray['iccaformat_sec_icon_enabled'] ?? '1') === '0' ? '0' : '1';
            \format_iccaformat\local\format_option::set($courseid, $etype, $sectionid,
                \format_iccaformat\local\format_option::OPT_ICON_ENABLED, $iconenabled);
        } else {
            \format_iccaformat\local\format_option::delete($courseid, $etype, $sectionid,
                \format_iccaformat\local\format_option::OPT_ICON_ENABLED);
        }
        if ($labeloverrideset) {
            $labelenabled = ($dataarray['iccaformat_sec_label_enabled'] ?? '1') === '0' ? '0' : '1';
            \format_iccaformat\local\format_option::set($courseid, $etype, $sectionid,
                \format_iccaformat\local\format_option::OPT_LABEL_ENABLED, $labelenabled);
        } else {
            \format_iccaformat\local\format_option::delete($courseid, $etype, $sectionid,
                \format_iccaformat\local\format_option::OPT_LABEL_ENABLED);
        }

        // Save flipped override.
        $flipoverrideset = !empty($dataarray['iccaformat_sec_flip_override_set']);
        if ($flipoverrideset) {
            $flipped = ($dataarray['iccaformat_sec_flipped'] ?? '0') === '1' ? '1' : '0';
            \format_iccaformat\local\format_option::set($courseid, $etype, $sectionid,
                \format_iccaformat\local\format_option::OPT_ICON_LABEL_FLIPPED, $flipped);
        } else {
            \format_iccaformat\local\format_option::delete($courseid, $etype, $sectionid,
                \format_iccaformat\local\format_option::OPT_ICON_LABEL_FLIPPED);
        }

        // Save custom label and icon overrides — only if non-empty.
        $labeloverride = trim($dataarray['iccaformat_sec_label_override'] ?? '');
        $iconoverride  = trim($dataarray['iccaformat_sec_icon_override']  ?? '');
        if ($labeloverride !== '') {
            \format_iccaformat\local\format_option::set($courseid, $etype, $sectionid,
                \format_iccaformat\local\format_option::OPT_LABEL_OVERRIDE, $labeloverride);
        } else {
            \format_iccaformat\local\format_option::delete($courseid, $etype, $sectionid,
                \format_iccaformat\local\format_option::OPT_LABEL_OVERRIDE);
        }
        if ($iconoverride !== '') {
            \format_iccaformat\local\format_option::set($courseid, $etype, $sectionid,
                \format_iccaformat\local\format_option::OPT_ICON_OVERRIDE, $iconoverride);
        } else {
            \format_iccaformat\local\format_option::delete($courseid, $etype, $sectionid,
                \format_iccaformat\local\format_option::OPT_ICON_OVERRIDE);
        }

        return $result;
    }

    /**
     * @param  null|int|\section_info $section
     * @return array
     */
    public function get_format_options($section = null): array {
        $options = parent::get_format_options($section);

        if ($section === null) {
            // Ensure image display options always have defaults.
            $imagedefaults = [
                'imageobjectfit'        => 'cover',
                'imageobjectposition'   => 'center',
                'imagefilter'           => 'none',
                'imageoverlay'          => 'none',
                'imagebackgroundcolour' => '#ffffff',
                'cardsize'              => 'medium',
                'footerfontsize'        => '1',
                'dropdownfontsize'      => '0.8',
                'hoverstyle'            => 'imgzoom',
                'hoverzoom'             => 105,
            ];
            foreach ($imagedefaults as $key => $default) {
                if (empty($options[$key])) {
                    $options[$key] = $default;
                }
            }
        }

        return $options;
    }

} // End class format_iccaformat.

function iccaformat_build_override_html(string $prefix, array $fields): string {
    $html = '<div style="display:flex;flex-direction:column;gap:8px;margin-top:8px;">';
    foreach ($fields as $key => $f) {
        $id       = 'icca_ovr_' . $prefix . '_' . $key;
        $checked  = $f['overridden'] ? 'checked' : '';
        $type     = $f['type'];
        $val      = htmlspecialchars($f['val'] ?? '', ENT_QUOTES);
        $label    = htmlspecialchars($f['label'], ENT_QUOTES);
        $expanded = $f['overridden'] ? 'block' : 'none';
        $ovr_name = $f['ovr_field'];
        $val_name = $f['val_field'];

        $html .= '<div style="border:0.5px solid rgba(0,0,0,0.12);border-radius:6px;overflow:hidden;">';
        $html .= '<div style="display:flex;align-items:center;justify-content:space-between;padding:6px 10px;background:#f8f9fa;">';
        $html .= '<span style="font-size:1rem;color:#495057;">' . $label . '</span>';
        $html .= '<label style="display:flex;align-items:center;gap:5px;font-size:0.9rem;color:#6c757d;cursor:pointer;margin:0;">';
        $html .= '<input type="checkbox" id="' . $id . '_chk" ' . $checked
            . ' onchange="iccaOvrToggle(\'' . $id . '\',\'' . $ovr_name . '\',\'' . $val_name . '\')"'
            . ' style="accent-color:#0d3c6f;width:14px;height:14px;"> Override</label>';
        $html .= '</div>';
        $html .= '<div id="' . $id . '_panel" style="display:' . $expanded . ';padding:8px 10px;background:#fff;border-top:0.5px solid rgba(0,0,0,0.08);">';

        if ($type === 'showhide') {
            $showchk   = ($val === '1' || $val === '') ? 'checked' : '';
            $hidechk   = ($val === '0') ? 'checked' : '';
            $poslabel  = $f['poslabel'] ?? 'Show';
            $neglabel  = $f['neglabel'] ?? 'Hide';
            $html .= '<label style="display:inline-flex;align-items:center;gap:5px;font-size:1rem;margin-right:16px;cursor:pointer;">'
                . '<input type="radio" name="' . $id . '_radio" value="1" ' . $showchk
                . ' onchange="iccaOvrSyncVal(\'' . $val_name . '\', \'1\')" style="accent-color:#0d3c6f;"> ' . $poslabel . '</label>';
            $html .= '<label style="display:inline-flex;align-items:center;gap:5px;font-size:1rem;cursor:pointer;">'
                . '<input type="radio" name="' . $id . '_radio" value="0" ' . $hidechk
                . ' onchange="iccaOvrSyncVal(\'' . $val_name . '\', \'0\')" style="accent-color:#0d3c6f;"> ' . $neglabel . '</label>';
        } else {
            $html .= '<input type="text" id="' . $id . '_text" value="' . $val . '" autocomplete="off"'
                . ' oninput="iccaOvrSyncVal(\'' . $val_name . '\', this.value)"'
                . ' style="width:100%;border:1px solid #dee2e6;border-radius:4px;padding:5px 8px;font-size:1rem;">';
        }

        $html .= '</div></div>';
    }
    $html .= '</div>';
    return $html;
}

function iccaformat_build_override_js(string $prefix): string {
    return "<script>
function iccaOvrToggle(id, ovrName, valName) {
    var chk   = document.getElementById(id + '_chk');
    var panel = document.getElementById(id + '_panel');
    var ovrh  = document.querySelector('[name=' + JSON.stringify(ovrName) + ']');
    var valh  = document.querySelector('[name=' + JSON.stringify(valName) + ']');
    if (panel) panel.style.display = chk.checked ? 'block' : 'none';
    if (ovrh)  ovrh.value = chk.checked ? '1' : '';
    if (!chk.checked && valh) valh.value = '';
}
function iccaOvrSyncVal(valName, val) {
    var valh = document.querySelector('[name=' + JSON.stringify(valName) + ']');
    if (valh) valh.value = val;
}
</script>";
}



// =============================================================================
// Module-level functions (outside the class — called by Moodle core directly).
// =============================================================================

/**
 * Serves files from format_iccaformat file areas.
 *
 * File areas handled:
 *   library_image  — images from iccaformat_image_library (system or course context).
 *                    Access: any logged-in user (images are decorative, not sensitive).
 *   element_image  — direct-upload images for individual sections/activities.
 *                    Access: must be enrolled in the course (course context).
 *
 * @param stdClass $course        Course record.
 * @param stdClass $cm            Course module (null for format files).
 * @param context  $context       Context.
 * @param string   $filearea      File area name.
 * @param array    $args          Remaining URL arguments [itemid, filename].
 * @param bool     $forcedownload Whether to force download.
 * @param array    $options       Additional options.
 * @return bool|void False if file not found, otherwise sends file and exits.
 */
function format_iccaformat_pluginfile($course, $cm, $context, $filearea, $args, $forcedownload, array $options = []) {

    // Library images: served to any logged-in user.
    if ($filearea === 'library_image') {
        require_login();

        $itemid  = (int) array_shift($args);
        $filename = array_pop($args);
        $filepath = $args ? '/' . implode('/', $args) . '/' : '/';

        $fs   = get_file_storage();
        $file = $fs->get_file($context->id, 'format_iccaformat', 'library_image', $itemid, $filepath, $filename);

        if (!$file || $file->is_directory()) {
            return false;
        }

        // Images are not sensitive — cache aggressively, no forced download.
        send_stored_file($file, 60 * 60 * 24 * 7, 0, false, $options);
        return;
    }

    // Element images: served only to users enrolled in the course.
    if ($filearea === 'element_image') {
        require_login($course);

        $coursecontext = \context_course::instance($course->id);
        $itemid   = (int) array_shift($args);
        $filename = array_pop($args);
        $filepath = $args ? '/' . implode('/', $args) . '/' : '/';

        $fs   = get_file_storage();
        $file = $fs->get_file($coursecontext->id, 'format_iccaformat', 'element_image', $itemid, $filepath, $filename);

        if (!$file || $file->is_directory()) {
            return false;
        }

        send_stored_file($file, 60 * 60 * 24 * 7, 0, false, $options);
        return;
    }

    return false;
}

/**
 * Injects ICCA Format card settings into every activity edit form
 * when the course uses format_iccaformat.
 *
 * Called by Moodle core from course/moodleform_mod.php for every mod edit.
 * We bail immediately if the current course is not using our format.
 *
 * @param  \moodleform_mod $formwrapper The mod edit form wrapper.
 * @param  \MoodleQuickForm $mform      The form object.
 * @return void
 */
/**
 * Implements callback inplace_editable() allowing section names to be edited in-place
 * via the pencil icon on the course page.
 *
 * @param  string $itemtype
 * @param  int    $itemid
 * @param  mixed  $newvalue
 * @return \core\output\inplace_editable|null
 */
function format_iccaformat_inplace_editable($itemtype, $itemid, $newvalue) {
    global $DB, $CFG;
    require_once($CFG->dirroot . '/course/lib.php');
    if ($itemtype === 'sectionname' || $itemtype === 'sectionnamenl') {
        $section = $DB->get_record_sql(
            'SELECT s.* FROM {course_sections} s JOIN {course} c ON s.course = c.id
             WHERE s.id = ? AND c.format = ?',
            [$itemid, 'iccaformat'], MUST_EXIST);
        return course_get_format($section->course)->inplace_editable_update_section_name(
            $section, $itemtype, $newvalue);
    }
}

/**
 * Build filter/overlay swatch picker HTML for iccaformat edit forms.
 *
 * @param  string $group     Unique group ID (e.g. 'act_filter', 'sec_overlay')
 * @param  string $fieldname Hidden input name
 * @param  array  $options   ['value' => 'Label', ...] — value '' means course default
 * @param  string $current   Currently selected value
 * @param  string $svgicon   SVG markup for the preview image
 * @param  array  $overlays  Optional: map of value => inline style for overlay divs
 * @param  array  $filters   Optional: map of value => CSS filter string
 * @return string HTML
 */
function format_iccaformat_swatch_picker(
    string $group, string $fieldname, array $options,
    string $current, string $svgicon,
    array $overlays = [], array $filters = []
): string {
    $html = '<div class="icca-swatch-row" style="display:flex;gap:8px;flex-wrap:wrap;align-items:flex-start;margin-bottom:4px;">';
    foreach ($options as $val => $labelstr) {
        $isactive  = ($val === $current) || ($current === '' && $val === '');
        $border    = $isactive ? '2.5px solid #0d6efd' : '0.5px solid #dee2e6';
        $labcolor  = $isactive ? '#084298' : '#6c757d';
        $labweight = $isactive ? '500' : 'normal';
        $filter    = isset($filters[$val]) ? $filters[$val] : '';
        $overlay   = isset($overlays[$val]) ? $overlays[$val] : '';

        $html .= '<label style="text-align:center;cursor:pointer;display:block;">';
        $html .= '<input type="radio" name="' . s($fieldname) . '" value="' . s($val) . '"'
            . ($isactive ? ' checked' : '')
            . ' class="icca-swatch-radio" style="position:absolute;opacity:0;pointer-events:none;">';
        $html .= '<span class="icca-swatch-box" style="display:block;width:90px;height:60px;border-radius:6px;'
            . 'border:' . $border . ';overflow:hidden;position:relative;cursor:pointer;">';
        $html .= '<span style="display:block;width:100%;height:100%;' . ($filter ? 'filter:' . $filter . ';' : '') . '">'
            . $svgicon . '</span>';
        if ($overlay) {
            $html .= '<span style="position:absolute;inset:0;' . $overlay . '"></span>';
        }
        $html .= '</span>';
        $html .= '<span class="icca-swatch-label" style="display:block;font-size:11px;margin-top:3px;'
            . 'color:' . $labcolor . ';font-weight:' . $labweight . ';">'
            . s($labelstr) . '</span>';
        $html .= '</label>';
    }
    $html .= '</div>';

    // JS to update border/label on click and sync hidden field.
    $html .= '<script>
(function(){
    function initSwatches(group) {
        var row = document.querySelector(".icca-swatch-row[data-group=\'' . s($group) . '\']");
        if (!row) {
            var rows = document.querySelectorAll(".icca-swatch-row");
            rows.forEach(function(r, i) {
                if (r.querySelector("input[name=\'' . s($fieldname) . '\']")) row = r;
            });
        }
        if (!row) { setTimeout(function(){ initSwatches(group); }, 200); return; }
        row.setAttribute("data-group", "' . s($group) . '");
        row.querySelectorAll(".icca-swatch-radio").forEach(function(radio) {
            radio.closest("label").querySelector(".icca-swatch-box").addEventListener("click", function() {
                radio.checked = true;
                var hidden = (function(n){var els=document.querySelectorAll("input");for(var i=0;i<els.length;i++){if(els[i].type==="hidden"&&els[i].name===n)return els[i];}return null;})(radio.name);
                if (hidden) hidden.value = radio.value;
                row.querySelectorAll(".icca-swatch-box").forEach(function(b) {
                    b.style.border = "0.5px solid #dee2e6";
                });
                row.querySelectorAll(".icca-swatch-label").forEach(function(l) {
                    l.style.color = "#6c757d"; l.style.fontWeight = "normal";
                });
                this.style.border = "2.5px solid #0d6efd";
                radio.closest("label").querySelector(".icca-swatch-label").style.color = "#084298";
                radio.closest("label").querySelector(".icca-swatch-label").style.fontWeight = "500";
            });
        });
    }
    if (document.readyState === "loading") { document.addEventListener("DOMContentLoaded", function(){ initSwatches("' . s($group) . '"); }); }
    else { initSwatches("' . s($group) . '"); }
})();
</script>';

    return $html;
}

function format_iccaformat_coursemodule_standard_elements($formwrapper, $mform): void {
    global $CFG, $DB;

    // Only inject when this course uses our format.
    $course = $formwrapper->get_course();
    if (!$course || $course->format !== 'iccaformat') {
        return;
    }

    $cm = $formwrapper->get_current();

    // Labels are not rendered as cards — skip.
    if ($cm && $cm->modulename === 'label') {
        return;
    }


    $courseid = $course->id;
    // $cm->id is the module instance id, NOT the course module id.
    // $cm->coursemodule is the actual cmid (matches mdl_course_modules.id).
    $cmid = (int) ($cm ? ($cm->coursemodule ?? $cm->id ?? 0) : 0);

    // Load existing options if editing an existing cm.
    $optioncache    = $cmid ? \format_iccaformat\local\format_option::get_all_for_course($courseid) : [];
    $currentsource  = \format_iccaformat\local\format_option::get_from_cache($optioncache, $cmid,
        \format_iccaformat\local\format_option::OPT_IMAGE_SOURCE, '');
    $currentimageid = (int) \format_iccaformat\local\format_option::get_from_cache($optioncache, $cmid,
        \format_iccaformat\local\format_option::OPT_IMAGE_ID, 0);
    $currentlabel   = \format_iccaformat\local\format_option::get_from_cache($optioncache, $cmid,
        \format_iccaformat\local\format_option::OPT_LABEL_OVERRIDE, '');
    $currenticon    = \format_iccaformat\local\format_option::get_from_cache($optioncache, $cmid,
        \format_iccaformat\local\format_option::OPT_ICON_OVERRIDE, '');
    $iconenabled    = \format_iccaformat\local\format_option::get_from_cache($optioncache, $cmid,
        \format_iccaformat\local\format_option::OPT_ICON_ENABLED, null); // null = not set, inherit from rule
    $labelenabled   = \format_iccaformat\local\format_option::get_from_cache($optioncache, $cmid,
        \format_iccaformat\local\format_option::OPT_LABEL_ENABLED, null); // null = not set, inherit from rule

    // Load library images for picker — split into course and Format library.
    $libraryimages = \format_iccaformat\local\image_library::get_all_for_course($courseid);
    $courseimages  = $libraryimages['course'];
    $siteimages    = $libraryimages['site'];
    $allimages     = array_merge($courseimages, $siteimages);
    $syscontextid  = \context_system::instance()->id;
    $issiteadmin   = has_capability('moodle/site:config', \context_system::instance());
    $libpageurl    = (new \moodle_url('/course/format/iccaformat/imagelibrary.php', ['courseid' => $courseid]))->out(false);

    // ── Header ────────────────────────────────────────────────────────────────
    $mform->addElement('header', 'iccaformat_card_header',
        get_string('cardsettings_header', 'format_iccaformat'));

    // ── Image source ──────────────────────────────────────────────────────────
    // Show current image preview if one is set.
    if ($currentsource === 'upload' || $currentsource === 'library') {
        $previewurl = '';
        if ($currentsource === 'upload' && $cmid) {
            $coursecontext = \context_course::instance($courseid);
            $fs2 = get_file_storage();
            $existing = $fs2->get_area_files($coursecontext->id, 'format_iccaformat',
                'element_image', $cmid, 'itemid', false);
            if (!empty($existing)) {
                $existingfile = reset($existing);
                $previewurl = \moodle_url::make_pluginfile_url(
                    $coursecontext->id, 'format_iccaformat', 'element_image',
                    $cmid, '/', $existingfile->get_filename()
                )->out(false);
            }
        } else if ($currentsource === 'library' && $currentimageid) {
            $librecord = \format_iccaformat\local\image_library::get($currentimageid);
            if ($librecord) {
                $previewurl = \format_iccaformat\local\image_library::get_url($librecord);
            }
        }
        if ($previewurl) {
            $mform->addElement('static', 'iccaformat_currentimage', '',
                '<div class="mb-2">'
                . '<p class="small text-muted mb-1">' . get_string('cardsettings_currentimage', 'format_iccaformat') . '</p>'
                . '<img src="' . $previewurl . '" alt="" '
                . 'style="max-width:200px;max-height:120px;object-fit:cover;border-radius:6px;border:1px solid rgba(0,0,0,0.1);">'
                . '</div>'
            );
        }
    }

    // ── Image picker — new unified UI ────────────────────────────────────
    // State: library (default) | upload | none
    // Hidden field carries the source; radio buttons carry the imageid.
    // Filepicker draft area carries uploaded file.
    $draftitemid = 0;
    if ($cmid) {
        file_prepare_draft_area($draftitemid,
            \context_course::instance($courseid)->id,
            'format_iccaformat', 'element_image', $cmid,
            ['maxbytes' => 5 * 1024 * 1024, 'maxfiles' => 1]
        );
    }

    $isnoimage = ($currentsource === 'none');
    $isdefault = ($currentsource === '' || $currentsource === 'default');
    $isupload  = ($currentsource === 'upload');
    $islibrary = ($currentsource === 'library') || (!$isnoimage && !$isdefault && !$isupload);

    // ── Image picker — redesigned to match library UI ────────────────────────

    // Build tile HTML for a single library image.
    $thumbfn = function($img, $checked) {
        $url    = \format_iccaformat\local\image_library::get_url($img);
        $border = $checked ? '2.5px solid #0d3c6f' : '1px solid #dee2e6';
        $tick   = $checked ? '<span style="position:absolute;top:4px;right:4px;background:#0d3c6f;color:#fff;border-radius:50%;width:20px;height:20px;display:flex;align-items:center;justify-content:center;font-size:11px;">✓</span>' : '';
        $h  = '<div class="icca-picker-tile' . ($checked ? ' icca-picker-selected' : '') . '"'
            . ' onclick="iccaPickerSelectImg(' . (int)$img->id . ', this)"'
            . ' style="border-radius:8px;overflow:hidden;border:' . $border . ';background:#f8f9fa;'
            . 'position:relative;cursor:pointer;transition:border-color 0.15s,box-shadow 0.15s;">';
        $h .= '<img src="' . $url . '" alt="' . s($img->name) . '"'
            . ' style="width:100%;height:80px;object-fit:cover;display:block;">';
        $h .= $tick;
        $h .= '<div style="padding:5px 7px;background:#fff;border-top:1px solid #f0f0f0;">'
            . '<div style="font-size:0.8rem;color:#495057;white-space:nowrap;overflow:hidden;text-overflow:ellipsis;font-weight:500;">'
            . s($img->name) . '</div></div>';
        $h .= '</div>';
        return $h;
    };

    // Build grids.
    $courselibhtml = '';
    if (!empty($courseimages)) {
        foreach ($courseimages as $img) {
            $checked = ($currentsource === 'library' && $currentimageid === (int)$img->id);
            $courselibhtml .= $thumbfn($img, $checked);
        }
    } else {
        $courselibhtml = '<p style="font-size:0.85rem;color:#adb5bd;padding:0.5rem 0;">'
            . get_string('imagelibrary_empty', 'format_iccaformat') . '</p>';
    }

    $formatlibhtml = '';
    if (!empty($siteimages)) {
        foreach ($siteimages as $img) {
            $checked = ($currentsource === 'library' && $currentimageid === (int)$img->id);
            $formatlibhtml .= $thumbfn($img, $checked);
        }
    }

    $libpageurlstr = (new \moodle_url('/course/format/iccaformat/imagelibrary.php',
        ['courseid' => $courseid]))->out(false);

    $html  = '<div class="format-iccaformat-imgpicker" style="margin-bottom:0.5rem;">';

    // ── Mode tabs ─────────────────────────────────────────────────────────────
    $tabact = 'background:#e8f0fb;color:#0d3c6f;border-color:#9ec5fe;font-weight:500;';
    $tabina = 'background:transparent;color:#6c757d;border-color:#dee2e6;';
    $tabcss = 'padding:6px 14px;border-radius:6px;font-size:1rem;cursor:pointer;border:1px solid;';

    $html .= '<div style="display:flex;gap:6px;margin-bottom:12px;">';
    $html .= '<button type="button" id="icca_btn_library" onclick="iccaPickerMode(\'library\')"'
        . ' style="' . $tabcss . ($islibrary ? $tabact : $tabina) . '">'
        . get_string('card_image_source_library', 'format_iccaformat') . '</button>';
    $html .= '<button type="button" id="icca_btn_upload" onclick="iccaPickerMode(\'upload\')"'
        . ' style="' . $tabcss . ($isupload ? $tabact : $tabina) . '">'
        . '&#8593; ' . get_string('card_image_source_upload', 'format_iccaformat') . '</button>';
    $html .= '<button type="button" id="icca_act_btn_default" onclick="iccaPickerMode(\'default\')"'
        . ' style="' . $tabcss . ($isdefault ? $tabact : $tabina) . '">'
        . get_string('card_image_source_default', 'format_iccaformat') . '</button>';
    $html .= '<button type="button" id="icca_act_btn_none" onclick="iccaPickerMode(\'none\')"'
        . ' style="' . $tabcss . ($isnoimage ? $tabact : $tabina) . '">'
        . '&#8960; ' . get_string('card_image_source_noimage', 'format_iccaformat') . '</button>';
    $html .= '</div>';

    // ── Library panel ─────────────────────────────────────────────────────────
    $html .= '<div id="icca_act_library" style="display:' . ($islibrary ? 'block' : 'none') . ';">';

    // Course images section.
    $html .= '<div style="font-size:0.75rem;font-weight:600;color:#6c757d;text-transform:uppercase;'
        . 'letter-spacing:0.07em;margin-bottom:8px;padding-bottom:5px;border-bottom:1px solid #dee2e6;'
        . 'display:flex;justify-content:space-between;">'
        . get_string('formatlibrary_course', 'format_iccaformat')
        . '<span style="font-weight:normal;">' . count($courseimages) . ' ' . get_string('formatlibrary_imagecount', 'format_iccaformat') . '</span>'
        . '</div>';
    $html .= '<div style="display:grid;grid-template-columns:repeat(auto-fill,minmax(120px,1fr));gap:10px;margin-bottom:12px;">'
        . $courselibhtml . '</div>';

    // Card image library section (collapsible).
    if (!empty($siteimages)) {
        $html .= '<div style="font-size:0.75rem;font-weight:600;color:#6c757d;text-transform:uppercase;'
            . 'letter-spacing:0.07em;margin-bottom:8px;padding-bottom:5px;border-bottom:1px solid #dee2e6;'
            . 'display:flex;justify-content:space-between;cursor:pointer;" onclick="iccaToggleFormatLib(this)">'
            . '<span><i class="fa fa-chevron-right" id="icca-formatchev" style="font-size:10px;transition:transform 0.2s;margin-right:4px;"></i>'
            . get_string('formatlibrary_site', 'format_iccaformat') . '</span>'
            . '<span style="font-weight:normal;">' . count($siteimages) . ' ' . get_string('formatlibrary_imagecount', 'format_iccaformat') . '</span>'
            . '</div>';
        $html .= '<div id="icca_formatlibgrid" style="display:none;grid-template-columns:repeat(auto-fill,minmax(120px,1fr));gap:10px;margin-bottom:12px;opacity:0.8;">'
            . $formatlibhtml . '</div>';
    }

    $html .= '<a href="' . $libpageurlstr . '" target="_blank"'
        . ' style="font-size:0.85rem;color:#0d3c6f;text-decoration:none;display:inline-flex;align-items:center;gap:4px;">'
        . '<i class="fa fa-external-link" style="font-size:11px;"></i> '
        . get_string('imagelibrary', 'format_iccaformat') . '</a>';
    $html .= '</div>'; // library panel

    // ── Upload panel ──────────────────────────────────────────────────────────
    $html .= '<div id="icca_act_upload" style="display:' . ($isupload ? 'block' : 'none') . ';">';
    $html .= '<div style="border:1.5px dashed #dee2e6;border-radius:8px;padding:1.5rem;'
        . 'text-align:center;background:#f8f9fa;color:#adb5bd;">'
        . '<i class="fa fa-image" style="font-size:28px;display:block;margin-bottom:8px;"></i>'
        . '<p style="font-size:0.9rem;margin:0;">' . get_string('cardsettings_acceptedtypes', 'format_iccaformat') . '</p>'
        . '</div>';
    $html .= '</div>';

    // ── No image panel ────────────────────────────────────────────────────────
    $html .= '<div id="icca_act_default" style="display:' . ($isdefault ? 'block' : 'none') . ';padding:1rem;text-align:center;color:#6c757d;">'
        . '<i class="fa fa-magic" style="font-size:24px;display:block;margin-bottom:8px;"></i>'
        . '<p style="font-size:0.9rem;margin:0;">Card will use the default image from the activity card default rule for this module type.</p>'
        . '</div>';
    $html .= '<div id="icca_act_noimage" style="display:' . ($isnoimage ? 'block' : 'none') . ';">';
    $html .= '<div style="border:1.5px dashed #dee2e6;border-radius:8px;padding:1.5rem;'
        . 'text-align:center;background:#f8f9fa;color:#adb5bd;">'
        . '<i class="fa fa-ban" style="font-size:28px;display:block;margin-bottom:8px;"></i>'
        . '<p style="font-size:0.9rem;margin:0;">No image — card will show background colour only.</p>'
        . '</div>';
    $html .= '</div>';

    $html .= '</div>'; // end picker

    // JS.
    $html .= '<script>
function iccaPickerMode(mode) {
    var panels = {library:"icca_act_library", upload:"icca_act_upload", default:"icca_act_default", none:"icca_act_noimage"};
    var btns   = {library:"icca_btn_library", upload:"icca_btn_upload", default:"icca_act_btn_default", none:"icca_act_btn_none"};
    var srcHidden = document.querySelector("input[name=\'iccaformat_imagesource\']");
    var act = "background:#e8f0fb;color:#0d3c6f;border-color:#9ec5fe;font-weight:500;border-style:solid;";
    var ina = "background:transparent;color:#6c757d;border-color:#dee2e6;font-weight:normal;border-style:solid;";
    Object.keys(panels).forEach(function(m) {
        var p = document.getElementById(panels[m]);
        var b = document.getElementById(btns[m]);
        if (p) p.style.display = (m === mode) ? "block" : "none";
        if (b) {
            b.style.cssText = b.style.cssText.replace(/background:[^;]+;|color:[^;]+;|border-color:[^;]+;|font-weight:[^;]+;|border-style:[^;]+;/g, "");
            b.style.cssText += (m === mode) ? act : ina;
        }
    });
    if (srcHidden) srcHidden.value = mode;
    // If switching to library, deselect imageid unless one is selected.
    if (mode !== "library") {
        var idHidden = document.querySelector("input[name=\"iccaformat_imageid\"]");
        if (idHidden && mode !== "library") idHidden.value = 0;
    }
}
function iccaPickerSelectImg(imgid, tile) {
    // Deselect all tiles.
    document.querySelectorAll(".icca-picker-tile").forEach(function(t) {
        t.style.border = "1px solid #dee2e6";
        t.style.boxShadow = "";
        var tick = t.querySelector(".icca-picker-tick");
        if (tick) tick.remove();
    });
    // Select this tile.
    tile.style.border = "2.5px solid #0d3c6f";
    tile.style.boxShadow = "0 2px 8px rgba(0,0,0,0.1)";
    var tick = document.createElement("span");
    tick.className = "icca-picker-tick";
    tick.style.cssText = "position:absolute;top:4px;right:4px;background:#0d3c6f;color:#fff;border-radius:50%;width:20px;height:20px;display:flex;align-items:center;justify-content:center;font-size:11px;";
    tick.textContent = "✓";
    tile.appendChild(tick);
    // Update hidden field.
    var idHidden = document.querySelector("input[name=\"iccaformat_imageid\"]");
    if (idHidden) idHidden.value = imgid;
    var srcHidden = document.querySelector("input[name=\'iccaformat_imagesource\']");
    if (srcHidden) srcHidden.value = "library";
}
function iccaToggleFormatLib(header) {
    var grid = document.getElementById("icca_formatlibgrid");
    var chev = document.getElementById("icca-formatchev");
    if (!grid) return;
    var open = grid.style.display !== "none";
    grid.style.display = open ? "none" : "grid";
    if (chev) chev.style.transform = open ? "" : "rotate(90deg)";
}
window.addEventListener("load", function() {
    setTimeout(function() {
        var idHidden  = document.querySelector("input[name=\"iccaformat_imageid\"]");
        var fpItem    = document.getElementById("fitem_id_iccaformat_imagefile");
        if (fpItem) fpItem.style.display = "none";
        var srcHidden = document.querySelector("input[name=\'iccaformat_imagesource\']");
        function syncFp() {
            if (!srcHidden) return;
            fpItem = document.getElementById("fitem_id_iccaformat_imagefile");
            if (fpItem) fpItem.style.display = (srcHidden.value === "upload") ? "" : "none";
        }
        document.getElementById("icca_btn_upload") && document.getElementById("icca_btn_upload").addEventListener("click", syncFp);
        document.getElementById("icca_btn_library") && document.getElementById("icca_btn_library").addEventListener("click", syncFp);
        document.getElementById("icca_btn_none") && document.getElementById("icca_btn_none").addEventListener("click", syncFp);
    }, 300);
});
</script>';

    $mform->addElement('static', 'iccaformat_imagepicker', '', $html);
    $mform->addElement('hidden', 'iccaformat_imagesource',
        $isnoimage ? 'none' : ($isdefault || $currentsource === '' ? 'default' : ($isupload ? 'upload' : 'library')));
    $mform->setType('iccaformat_imagesource', PARAM_ALPHA);
    $mform->addElement('hidden', 'iccaformat_imageid', $currentimageid);
    $mform->setType('iccaformat_imageid', PARAM_INT);
    $mform->addElement('filepicker', 'iccaformat_imagefile',
        get_string('card_image_source_upload', 'format_iccaformat'), null, [
            'maxbytes'       => 5 * 1024 * 1024,
            'accepted_types' => ['.jpg', '.jpeg', '.png', '.webp', '.gif', '.svg'],
        ]);
    $mform->setDefault('iccaformat_imagefile', $draftitemid);

    // ── Description display ───────────────────────────────────────────────
    // ── Image display options ─────────────────────────────────────────────
    $currentobjectfit      = \format_iccaformat\local\format_option::get_from_cache($optioncache, $cmid,
        'imageobjectfit', '');
    $currentobjectposition = \format_iccaformat\local\format_option::get_from_cache($optioncache, $cmid,
        'imageobjectposition', '');
    $currentimagefilter    = \format_iccaformat\local\format_option::get_from_cache($optioncache, $cmid,
        'imagefilter', '');
    $currentimageoverlay   = \format_iccaformat\local\format_option::get_from_cache($optioncache, $cmid,
        'imageoverlay', '');

    $mform->addElement('select', 'iccaformat_imageobjectfit',
        get_string('imageobjectfit', 'format_iccaformat'), [
            ''        => get_string('coursesetting_default',  'format_iccaformat'),
            'cover'   => get_string('imageobjectfit_cover',   'format_iccaformat'),
            'contain' => get_string('imageobjectfit_contain', 'format_iccaformat'),
            'fill'    => get_string('imageobjectfit_fill',    'format_iccaformat'),
        ]);
    $mform->setType('iccaformat_imageobjectfit', PARAM_ALPHA);
    $mform->setDefault('iccaformat_imageobjectfit', $currentobjectfit);
    if ($cmid && $currentobjectfit !== '') {
        $mform->getElement('iccaformat_imageobjectfit')->setValue($currentobjectfit);
    }

    $mform->addElement('select', 'iccaformat_imageobjectposition',
        get_string('imageobjectposition', 'format_iccaformat'), [
            ''             => get_string('coursesetting_default',         'format_iccaformat'),
            'center'       => get_string('imageposition_center',      'format_iccaformat'),
            'top'          => get_string('imageposition_top',         'format_iccaformat'),
            'bottom'       => get_string('imageposition_bottom',      'format_iccaformat'),
            'left'         => get_string('imageposition_left',        'format_iccaformat'),
            'right'        => get_string('imageposition_right',       'format_iccaformat'),
            'top left'     => get_string('imageposition_topleft',     'format_iccaformat'),
            'top right'    => get_string('imageposition_topright',    'format_iccaformat'),
            'bottom left'  => get_string('imageposition_bottomleft',  'format_iccaformat'),
            'bottom right' => get_string('imageposition_bottomright', 'format_iccaformat'),
        ]);
    $mform->setType('iccaformat_imageobjectposition', PARAM_TEXT);
    $mform->setDefault('iccaformat_imageobjectposition', $currentobjectposition);
    if ($cmid && $currentobjectposition !== '') {
        $mform->getElement('iccaformat_imageobjectposition')->setValue($currentobjectposition);
    }

    // ── Image filter swatch picker ───────────────────────────────────────
    $filtersvg = '<svg width="88" height="58" viewBox="0 0 90 60" xmlns="http://www.w3.org/2000/svg">'
        . '<rect width="90" height="60" fill="#e8f4fc"/>'
        . '<rect x="4" y="4" width="82" height="52" rx="3" fill="none" stroke="#a0b8c8" stroke-width="1.5"/>'
        . '<circle cx="22" cy="18" r="7" fill="#f5c518"/>'
        . '<polygon points="10,52 35,28 52,44 65,32 80,52" fill="#4caf50"/>'
        . '<polygon points="50,52 65,32 80,52" fill="#388e3c"/>'
        . '</svg>';
    $filteropts = [
        ''          => get_string('coursesetting_default', 'format_iccaformat'),
        'none'      => get_string('imagefilter_none',      'format_iccaformat'),
        'grayscale' => get_string('imagefilter_grayscale', 'format_iccaformat'),
        'sepia'     => get_string('imagefilter_sepia',     'format_iccaformat'),
        'brighten'  => get_string('imagefilter_brighten',  'format_iccaformat'),
    ];
    $filtercsses = [
        ''          => '',
        'none'      => '',
        'grayscale' => 'grayscale(100%)',
        'sepia'     => 'sepia(80%)',
        'brighten'  => 'brightness(135%)',
    ];
    $mform->addElement('static', 'iccaformat_imagefilter_swatches',
        get_string('imagefilter', 'format_iccaformat'),
        format_iccaformat_swatch_picker('act_filter', 'iccaformat_imagefilter',
            $filteropts, $currentimagefilter, $filtersvg, [], $filtercsses));
    $mform->addElement('hidden', 'iccaformat_imagefilter', $currentimagefilter);
    $mform->setType('iccaformat_imagefilter', PARAM_ALPHA);

    // ── Image overlay swatch picker ───────────────────────────────────────
    $overlayopts = [
        ''      => get_string('coursesetting_default', 'format_iccaformat'),
        'none'  => get_string('imageoverlay_none',  'format_iccaformat'),
        'light' => get_string('imageoverlay_light', 'format_iccaformat'),
        'dark'  => get_string('imageoverlay_dark',  'format_iccaformat'),
    ];
    $overlaycsses = [
        ''      => '',
        'none'  => '',
        'light' => 'background:rgba(255,255,255,0.4);',
        'dark'  => 'background:rgba(0,0,0,0.4);',
    ];
    $mform->addElement('static', 'iccaformat_imageoverlay_swatches',
        get_string('imageoverlay', 'format_iccaformat'),
        format_iccaformat_swatch_picker('act_overlay', 'iccaformat_imageoverlay',
            $overlayopts, $currentimageoverlay, $filtersvg, $overlaycsses, []));
    $mform->addElement('hidden', 'iccaformat_imageoverlay', $currentimageoverlay);
    $mform->setType('iccaformat_imageoverlay', PARAM_ALPHA);

    $currentbgcolour = \format_iccaformat\local\format_option::get_from_cache($optioncache, $cmid,
        'imagebackgroundcolour', '');
    if ($currentbgcolour === '') {
        $courseformatoptions = $course ? course_get_format($course)->get_format_options() : [];
        $currentbgcolour     = $courseformatoptions['imagebackgroundcolour'] ?? '#ffffff';
        $currentbgcolour     = $currentbgcolour ?: '#ffffff';
        $bgisoverride = false;
    } else {
        $bgisoverride = true;
    }
    $bgcolourval = $currentbgcolour ?: '#ffffff';

    // Registered hidden field — Moodle reads this in post_actions.
    $mform->addElement('hidden', 'iccaformat_imagebackgroundcolour', $bgisoverride ? $bgcolourval : '');
    $mform->setType('iccaformat_imagebackgroundcolour', PARAM_TEXT);

    // Visual picker — text field then colour swatch.
    $mform->addElement('static', 'iccaformat_imagebackgroundcolour_picker',
        get_string('imagebackgroundcolour', 'format_iccaformat')
        . (!$bgisoverride ? ' <span class="badge bg-secondary ms-1" style="font-size:10px;font-weight:normal;">'
            . get_string('coursesetting_default', 'format_iccaformat') . '</span>' : ''),
        '<div class="d-flex align-items-center" style="gap:0.4rem;">'
        . '<input type="text" id="icca_bgcolour_text"'
        . ' value="' . ($bgisoverride ? s($bgcolourval) : '') . '"'
        . ' placeholder="' . s($bgcolourval) . '"'
        . ' maxlength="7" class="form-control" style="width:110px;font-family:monospace;">'
        . '<input type="color" id="icca_bgcolour_picker" value="' . s($bgcolourval) . '"'
        . ' style="width:38px;height:38px;padding:2px;border:1px solid #ced4da;border-radius:4px;cursor:pointer;flex-shrink:0;">'
        . '</div>'
        . '<div class="form-text text-muted">' . get_string('imagebackgroundcolour_help', 'format_iccaformat') . '</div>'
        . '<script>
(function() {
    function init() {
        var text   = document.getElementById("icca_bgcolour_text");
        var picker = document.getElementById("icca_bgcolour_picker");
        // Find hidden field by name — more reliable than getElementById in injected forms.
        var hidden = document.querySelector("input[name=\'iccaformat_imagebackgroundcolour\']");
        if (!text || !picker) { setTimeout(init, 200); return; }

        // Sync all three on picker change.
        picker.addEventListener("input", function() {
            text.value = this.value;
            if (hidden) hidden.value = this.value;
        });

        // Sync picker and hidden on text change.
        text.addEventListener("input", function() {
            var v = this.value.trim();
            if (/^#[0-9a-fA-F]{6}$/.test(v)) {
                picker.value = v;
                if (hidden) hidden.value = v;
            } else if (v === "") {
                picker.value = "' . s($bgcolourval) . '";
                if (hidden) hidden.value = "";
            }
        });

        // Initialise hidden from current text value on load.
        if (hidden) hidden.value = text.value;
    }
    if (document.readyState === "loading") {
        document.addEventListener("DOMContentLoaded", init);
    } else {
        setTimeout(init, 100);
    }
})();
</script>'
    );
    $mform->setType('iccaformat_imagebackgroundcolour', PARAM_TEXT);

    // Card overrides — unlock pattern.
    $icon_overridden   = ($iconenabled !== null);
    $label_overridden  = ($labelenabled !== null);
    $cicon_overridden  = ($currenticon !== '');
    $clabel_overridden = ($currentlabel !== '');
    $flippedopt        = \format_iccaformat\local\format_option::get_from_cache($optioncache, $cmid, \format_iccaformat\local\format_option::OPT_ICON_LABEL_FLIPPED, null);
    $flip_overridden   = ($flippedopt !== null);

    $explainer = '<div style="margin-top:8px;">'
        . '<span style="font-size:1rem;font-weight:500;color:#495057;">'
        . get_string('card_overrides_explainer', 'format_iccaformat') . '</span>'
        . '<p style="font-size:0.9rem;color:#6c757d;margin:2px 0 0;">'
        . get_string('card_overrides_explainer_desc', 'format_iccaformat') . '</p>'
        . '</div>';
    $overridehtml = iccaformat_build_override_html('cm', [
        'icon'        => ['label' => get_string('card_icon_enabled',   'format_iccaformat'), 'type' => 'showhide', 'overridden' => $icon_overridden,   'val' => $iconenabled  ?? '1', 'ovr_field' => 'iccaformat_icon_enabled_override',  'val_field' => 'iccaformat_icon_enabled'],
        'label'       => ['label' => get_string('card_label_enabled',  'format_iccaformat'), 'type' => 'showhide', 'overridden' => $label_overridden,  'val' => $labelenabled ?? '1', 'ovr_field' => 'iccaformat_label_enabled_override', 'val_field' => 'iccaformat_label_enabled'],
        'customicon'  => ['label' => get_string('card_icon_override',  'format_iccaformat'), 'type' => 'text',     'overridden' => $cicon_overridden,  'val' => $currenticon,         'ovr_field' => 'iccaformat_cicon_override_set',     'val_field' => 'iccaformat_icon_override'],
        'customlabel' => ['label' => get_string('card_label_override', 'format_iccaformat'), 'type' => 'text',     'overridden' => $clabel_overridden, 'val' => $currentlabel,        'ovr_field' => 'iccaformat_clabel_override_set',    'val_field' => 'iccaformat_label_override'],
        'flipped'     => ['label' => get_string('card_flip',           'format_iccaformat'), 'type' => 'showhide', 'overridden' => $flip_overridden,   'val' => $flippedopt ?? '0',   'ovr_field' => 'iccaformat_flip_override_set',      'val_field' => 'iccaformat_flipped',     'poslabel' => 'Yes', 'neglabel' => 'No'],
    ]);
    $overridejs = iccaformat_build_override_js('cm');
    $mform->addElement('static', 'iccaformat_overrides', '', $explainer . $overridehtml . $overridejs);

    // Register hidden fields with Moodle so they pass through form submission.
    // Values initialised from stored options — JS updates them when UI changes.
    $mform->addElement('hidden', 'iccaformat_icon_enabled_override',  $icon_overridden  ? '1' : '');
    $mform->addElement('hidden', 'iccaformat_label_enabled_override', $label_overridden ? '1' : '');
    $mform->addElement('hidden', 'iccaformat_cicon_override_set',     $cicon_overridden  ? '1' : '');
    $mform->addElement('hidden', 'iccaformat_clabel_override_set',    $clabel_overridden ? '1' : '');
    $mform->addElement('hidden', 'iccaformat_flip_override_set',      $flip_overridden  ? '1' : '');
    $mform->addElement('hidden', 'iccaformat_icon_enabled',   $iconenabled  ?? '');
    $mform->addElement('hidden', 'iccaformat_label_enabled',  $labelenabled ?? '');
    $mform->addElement('hidden', 'iccaformat_icon_override',  $currenticon);
    $mform->addElement('hidden', 'iccaformat_label_override', $currentlabel);
    $mform->addElement('hidden', 'iccaformat_flipped',        $flippedopt ?? '');
    foreach (['iccaformat_icon_enabled_override', 'iccaformat_label_enabled_override',
              'iccaformat_cicon_override_set', 'iccaformat_clabel_override_set',
              'iccaformat_flip_override_set',
              'iccaformat_icon_enabled', 'iccaformat_label_enabled',
              'iccaformat_icon_override', 'iccaformat_label_override', 'iccaformat_flipped'] as $fname) {
        $mform->setType($fname, PARAM_TEXT);
    }


}

/**
 * Saves ICCA Format card settings after an activity edit form is submitted.
 *
 * Called by Moodle core after the activity instance has been created/updated.
 * $data->coursemodule is the cmid.
 *
 * @param  stdClass $data   Submitted form data.
 * @param  stdClass $course Course record.
 * @return stdClass $data   Unchanged (we just write to our own tables).
 */
function format_iccaformat_coursemodule_edit_post_actions($data, $course): stdClass {
    global $USER;

    if ($course->format !== 'iccaformat') {
        return $data;
    }




    $cmid      = (int) $data->coursemodule;
    $courseid  = (int) $course->id;
    $context   = \context_course::instance($courseid);
    $etype     = \format_iccaformat\local\format_option::ELEMENT_CM;

    // ── Image ────────────────────────────────────────────────────────────────
    $imagesource = $data->iccaformat_imagesource ?? '';
    $imageid     = (int) ($data->iccaformat_imageid ?? 0);

    if ($imagesource === 'library' && $imageid) {
        \format_iccaformat\local\format_option::set($courseid, $etype, $cmid,
            \format_iccaformat\local\format_option::OPT_IMAGE_SOURCE, 'library');
        \format_iccaformat\local\format_option::set($courseid, $etype, $cmid,
            \format_iccaformat\local\format_option::OPT_IMAGE_ID, $imageid);

    } else if ($imagesource === 'upload') {
        // File upload — save to course library and link as library image.
        $draftitemid = (int) ($data->iccaformat_imagefile ?? 0);
        if ($draftitemid) {
            $fs          = get_file_storage();
            $usercontext = \context_user::instance($USER->id);
            $draftfiles  = $fs->get_area_files($usercontext->id, 'user', 'draft',
                $draftitemid, 'itemid', false);

            if (!empty($draftfiles)) {
                $draftfile = reset($draftfiles);
                $mimetype  = $draftfile->get_mimetype();

                if (in_array($mimetype, \format_iccaformat\local\image_library::ACCEPTED_TYPES)) {
                    $imgname  = pathinfo($draftfile->get_filename(), PATHINFO_FILENAME);
                    $newlibid = \format_iccaformat\local\image_library::save_new(
                        $imgname, '', $draftitemid, $context->id, $USER->id
                    );
                    if ($newlibid) {
                        \format_iccaformat\local\format_option::set($courseid, $etype, $cmid,
                            \format_iccaformat\local\format_option::OPT_IMAGE_SOURCE, 'library');
                        \format_iccaformat\local\format_option::set($courseid, $etype, $cmid,
                            \format_iccaformat\local\format_option::OPT_IMAGE_ID, $newlibid);
                        // Clean up any old element_image file.
                        $fs->delete_area_files($context->id, 'format_iccaformat', 'element_image', $cmid);
                    }
                }
            }
        }

        } else if ($imagesource === 'none') {
        // Explicit no image — suppress default too.
        \format_iccaformat\local\format_option::set($courseid, $etype, $cmid,
            \format_iccaformat\local\format_option::OPT_IMAGE_SOURCE, 'none');
        \format_iccaformat\local\format_option::set($courseid, $etype, $cmid,
            \format_iccaformat\local\format_option::OPT_IMAGE_ID, 0);

        } else {
        // 'default' or '' — use default from rule.
        \format_iccaformat\local\format_option::set($courseid, $etype, $cmid,
            \format_iccaformat\local\format_option::OPT_IMAGE_SOURCE, 'default');
        \format_iccaformat\local\format_option::set($courseid, $etype, $cmid,
            \format_iccaformat\local\format_option::OPT_IMAGE_ID, 0);
    }

    // ── Description display ──────────────────────────────────────────────────
    $descdisplay = $data->iccaformat_description_display ?? '';
    if (in_array($descdisplay, ['', 'hidden', 'inbox', 'belowbox'])) {
        \format_iccaformat\local\format_option::set($courseid, $etype, $cmid,
            \format_iccaformat\local\format_option::OPT_DESCRIPTION_DISPLAY, $descdisplay);
    }

    // ── Image display options ────────────────────────────────────────────────
    $validfit      = ['', 'cover', 'contain', 'fill'];
    $validposition = ['', 'center', 'top', 'bottom', 'left', 'right',
                      'top left', 'top right', 'bottom left', 'bottom right'];
    $validfilter   = ['', 'none', 'grayscale', 'sepia', 'brighten'];
    $validoverlay  = ['', 'none', 'light', 'dark'];

    $objectfit      = $data->iccaformat_imageobjectfit      ?? '';
    $objectposition = $data->iccaformat_imageobjectposition ?? '';
    $imagefilter    = $data->iccaformat_imagefilter         ?? '';
    $imageoverlay   = $data->iccaformat_imageoverlay        ?? '';

    if (in_array($objectfit, $validfit)) {
        \format_iccaformat\local\format_option::set($courseid, $etype, $cmid, 'imageobjectfit', $objectfit);
    }
    if (in_array($objectposition, $validposition)) {
        \format_iccaformat\local\format_option::set($courseid, $etype, $cmid, 'imageobjectposition', $objectposition);
    }
    if (in_array($imagefilter, $validfilter)) {
        \format_iccaformat\local\format_option::set($courseid, $etype, $cmid, 'imagefilter', $imagefilter);
    }
    if (in_array($imageoverlay, $validoverlay)) {
        \format_iccaformat\local\format_option::set($courseid, $etype, $cmid, 'imageoverlay', $imageoverlay);
    }

    $bgcolour = trim($data->iccaformat_imagebackgroundcolour ?? '');
    if (preg_match('/^#[0-9a-fA-F]{3,6}$/', $bgcolour) || $bgcolour === '') {
        \format_iccaformat\local\format_option::set($courseid, $etype, $cmid, 'imagebackgroundcolour', $bgcolour);
    }

    // ── Label and icon — unlock pattern ──────────────────────────────────────
    $label = trim($data->iccaformat_label_override ?? '');
    $icon  = trim($data->iccaformat_icon_override  ?? '');

    // Icon enabled — only save if override is explicitly set.
    if (!empty($data->iccaformat_icon_enabled_override)) {
        $iconenabled = ($data->iccaformat_icon_enabled ?? '1') === '0' ? '0' : '1';
        \format_iccaformat\local\format_option::set($courseid, $etype, $cmid,
            \format_iccaformat\local\format_option::OPT_ICON_ENABLED, $iconenabled);
    } else {
        \format_iccaformat\local\format_option::delete($courseid, $etype, $cmid,
            \format_iccaformat\local\format_option::OPT_ICON_ENABLED);
    }

    // Label enabled.
    if (!empty($data->iccaformat_label_enabled_override)) {
        $labelenabled = ($data->iccaformat_label_enabled ?? '1') === '0' ? '0' : '1';
        \format_iccaformat\local\format_option::set($courseid, $etype, $cmid,
            \format_iccaformat\local\format_option::OPT_LABEL_ENABLED, $labelenabled);
    } else {
        \format_iccaformat\local\format_option::delete($courseid, $etype, $cmid,
            \format_iccaformat\local\format_option::OPT_LABEL_ENABLED);
    }

    // Flipped.
    if (!empty($data->iccaformat_flip_override_set)) {
        $flipped = ($data->iccaformat_flipped ?? '0') === '1' ? '1' : '0';
        \format_iccaformat\local\format_option::set($courseid, $etype, $cmid,
            \format_iccaformat\local\format_option::OPT_ICON_LABEL_FLIPPED, $flipped);
    } else {
        \format_iccaformat\local\format_option::delete($courseid, $etype, $cmid,
            \format_iccaformat\local\format_option::OPT_ICON_LABEL_FLIPPED);
    }

    // Custom label — only save if non-empty.
    $label = trim($data->iccaformat_label_override ?? '');
    if ($label !== '') {
        \format_iccaformat\local\format_option::set($courseid, $etype, $cmid,
            \format_iccaformat\local\format_option::OPT_LABEL_OVERRIDE, $label);
    } else {
        \format_iccaformat\local\format_option::delete($courseid, $etype, $cmid,
            \format_iccaformat\local\format_option::OPT_LABEL_OVERRIDE);
    }

    // Custom icon — only save if non-empty.
    $icon = trim($data->iccaformat_icon_override ?? '');
    if ($icon !== '') {
        \format_iccaformat\local\format_option::set($courseid, $etype, $cmid,
            \format_iccaformat\local\format_option::OPT_ICON_OVERRIDE, $icon);
    } else {
        \format_iccaformat\local\format_option::delete($courseid, $etype, $cmid,
            \format_iccaformat\local\format_option::OPT_ICON_OVERRIDE);
    }

    return $data;
}
