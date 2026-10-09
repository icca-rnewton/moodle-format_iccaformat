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
 * Admin settings for format_iccaformat.
 * These are site-wide defaults applied to new courses using this format.
 * Per-course settings override these.
 *
 * @package   format_iccaformat
 * @copyright 2026 Inns of Court College of Advocacy (Part of COIC)
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die;

if ($ADMIN->fulltree) {

    // ── Card appearance ───────────────────────────────────────────────────
    $settings->add(new admin_setting_heading(
        'format_iccaformat/heading_cards',
        get_string('admindefaults_cards', 'format_iccaformat'),
        get_string('admindefaults_cards_desc', 'format_iccaformat')
    ));

    $settings->add(new admin_setting_configselect(
        'format_iccaformat/default_cardsize',
        get_string('cardsize', 'format_iccaformat'),
        get_string('cardsize_help', 'format_iccaformat'),
        'medium',
        [
            'xsmall' => get_string('cardsize_xsmall', 'format_iccaformat'),
            'small'  => get_string('cardsize_small',  'format_iccaformat'),
            'medium' => get_string('cardsize_medium', 'format_iccaformat'),
            'large'  => get_string('cardsize_large',  'format_iccaformat'),
        ]
    ));

    $settings->add(new admin_setting_configselect(
        'format_iccaformat/default_borderradius',
        get_string('borderradius', 'format_iccaformat'),
        get_string('borderradius_help', 'format_iccaformat'),
        '8',
        [
            '0'  => get_string('borderradius_square',  'format_iccaformat'),
            '4'  => get_string('borderradius_small',   'format_iccaformat'),
            '8'  => get_string('borderradius_medium',  'format_iccaformat'),
            '16' => get_string('borderradius_large',   'format_iccaformat'),
            '24' => get_string('borderradius_round',   'format_iccaformat'),
        ]
    ));

    // ── Hover animation ───────────────────────────────────────────────────
    $settings->add(new admin_setting_heading(
        'format_iccaformat/heading_hover',
        get_string('admindefaults_hover', 'format_iccaformat'),
        ''
    ));

    $settings->add(new admin_setting_configselect(
        'format_iccaformat/default_hoverstyle',
        get_string('hoverstyle', 'format_iccaformat'),
        get_string('hoverstyle_help', 'format_iccaformat'),
        'imgzoom',
        [
            'none'    => get_string('hoverstyle_none',    'format_iccaformat'),
            'imgzoom' => get_string('hoverstyle_imgzoom', 'format_iccaformat'),
            'lift'    => get_string('hoverstyle_lift',    'format_iccaformat'),
            'shadow'  => get_string('hoverstyle_shadow',  'format_iccaformat'),
        ]
    ));

    $settings->add(new admin_setting_configtext(
        'format_iccaformat/default_hoverzoom',
        get_string('hoverzoom', 'format_iccaformat'),
        get_string('hoverzoom_help', 'format_iccaformat'),
        '105',
        PARAM_INT
    ));

    $settings->add(new admin_setting_configcolourpicker(
        'format_iccaformat/default_hovercolour',
        get_string('hovercolour', 'format_iccaformat'),
        get_string('hovercolour_help', 'format_iccaformat'),
        '#0d3c6f'
    ));

    // ── Progress bar ──────────────────────────────────────────────────────
    $settings->add(new admin_setting_heading(
        'format_iccaformat/heading_completion',
        get_string('admindefaults_completion', 'format_iccaformat'),
        ''
    ));

    $settings->add(new admin_setting_configcolourpicker(
        'format_iccaformat/default_completionbarcolour',
        get_string('completionbarcolour', 'format_iccaformat'),
        get_string('completionbarcolour_help', 'format_iccaformat'),
        '#0d3c6f'
    ));

    $settings->add(new admin_setting_configselect(
        'format_iccaformat/default_completionbarshowin',
        get_string('completionbarshowin', 'format_iccaformat'),
        '',
        '2',
        [
            '3' => get_string('completionbarshowin_none',        'format_iccaformat'),
            '0' => get_string('completionbarshowin_sections',    'format_iccaformat'),
            '1' => get_string('completionbarshowin_subsections', 'format_iccaformat'),
            '2' => get_string('completionbarshowin_both',        'format_iccaformat'),
        ]
    ));

    $settings->add(new admin_setting_configselect(
        'format_iccaformat/default_sectionprogbar',
        get_string('sectionprogbar', 'format_iccaformat'),
        '',
        '2',
        [
            '3' => get_string('completionbarshowin_none',        'format_iccaformat'),
            '0' => get_string('completionbarshowin_sections',    'format_iccaformat'),
            '1' => get_string('completionbarshowin_subsections', 'format_iccaformat'),
            '2' => get_string('completionbarshowin_both',        'format_iccaformat'),
        ]
    ));

    $settings->add(new admin_setting_configcheckbox(
        'format_iccaformat/default_showactivitybar',
        get_string('showactivitybar', 'format_iccaformat'),
        get_string('showactivitybar_help', 'format_iccaformat'),
        '1'
    ));

    // ── Navigation ────────────────────────────────────────────────────────
    $settings->add(new admin_setting_heading(
        'format_iccaformat/heading_navigation',
        get_string('admindefaults_navigation', 'format_iccaformat'),
        ''
    ));

    $settings->add(new admin_setting_configselect(
        'format_iccaformat/default_showcoursemap',
        get_string('showcoursemap', 'format_iccaformat'),
        '',
        '1',
        [
            '1' => get_string('showcoursemap_show', 'format_iccaformat'),
            '0' => get_string('showcoursemap_hide', 'format_iccaformat'),
        ]
    ));

    $settings->add(new admin_setting_configselect(
        'format_iccaformat/default_showhomebutton',
        get_string('showhomebutton', 'format_iccaformat'),
        '',
        '1',
        [
            '1' => get_string('showhomebutton_show', 'format_iccaformat'),
            '0' => get_string('showhomebutton_hide', 'format_iccaformat'),
        ]
    ));

    // ── Typography ────────────────────────────────────────────────────────
    $settings->add(new admin_setting_heading(
        'format_iccaformat/heading_typography',
        get_string('admindefaults_typography', 'format_iccaformat'),
        ''
    ));

    $settings->add(new admin_setting_configtext(
        'format_iccaformat/default_descriptionfontsize',
        get_string('descriptionfontsize', 'format_iccaformat'),
        get_string('descriptionfontsize_help', 'format_iccaformat'),
        '1',
        PARAM_TEXT
    ));

    $settings->add(new admin_setting_configtext(
        'format_iccaformat/default_footerfontsize',
        get_string('footerfontsize', 'format_iccaformat'),
        get_string('footerfontsize_help', 'format_iccaformat'),
        '1',
        PARAM_TEXT
    ));

    $settings->add(new admin_setting_configtext(
        'format_iccaformat/default_dropdownfontsize',
        get_string('dropdownfontsize', 'format_iccaformat'),
        get_string('dropdownfontsize_help', 'format_iccaformat'),
        '1',
        PARAM_TEXT
    ));



    // ── Image defaults ────────────────────────────────────────────────────
    $settings->add(new admin_setting_heading(
        'format_iccaformat/heading_image',
        get_string('admindefaults_image', 'format_iccaformat'),
        ''
    ));

    $settings->add(new admin_setting_configselect(
        'format_iccaformat/default_imageobjectfit',
        get_string('imageobjectfit', 'format_iccaformat'),
        '',
        'cover',
        [
            'cover'   => get_string('imageobjectfit_cover',   'format_iccaformat'),
            'contain' => get_string('imageobjectfit_contain', 'format_iccaformat'),
            'fill'    => get_string('imageobjectfit_fill',    'format_iccaformat'),
        ]
    ));

    $settings->add(new admin_setting_configselect(
        'format_iccaformat/default_imageobjectposition',
        get_string('imageobjectposition', 'format_iccaformat'),
        '',
        'center',
        [
            'center'       => get_string('imageposition_center',       'format_iccaformat'),
            'top'          => get_string('imageposition_top',          'format_iccaformat'),
            'bottom'       => get_string('imageposition_bottom',       'format_iccaformat'),
            'left'         => get_string('imageposition_left',         'format_iccaformat'),
            'right'        => get_string('imageposition_right',        'format_iccaformat'),
            'top center'   => 'Top center',
            'bottom center'=> 'Bottom center',
        ]
    ));

    $settings->add(new admin_setting_configcolourpicker(
        'format_iccaformat/default_imagebackgroundcolour',
        get_string('imagebackgroundcolour', 'format_iccaformat'),
        get_string('imagebackgroundcolour_help', 'format_iccaformat'),
        '#0d3c6f'
    ));

    // ── Labels & images management links ──────────────────────────────────
    $settings->add(new admin_setting_heading(
        'format_iccaformat/heading_management',
        get_string('admindefaults_management', 'format_iccaformat'),
        'Manage default labels, icons and images for activity cards across all courses: '
        . '<a href="' . new moodle_url('/course/format/iccaformat/labelrules.php') . '">'
            . get_string('labelrules_site', 'format_iccaformat') . '</a>'
        . ' &middot; '
        . '<a href="' . new moodle_url('/course/format/iccaformat/imagelibrary.php') . '">'
            . get_string('formatlibrary_site', 'format_iccaformat') . '</a>'
    ));

}
