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
 * English language strings for format_iccaformat.
 *
 * @package   format_iccaformat
 * @copyright 2026 Inns of Court College of Advocacy (Part of COIC)
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
defined('MOODLE_INTERNAL') || die();

$string['pluginname'] = 'ICCA Format';
$string['plugin_description'] = 'A card-based course format with image library, activity labelling, and completion display.';
$string['sectionname']    = 'Section';
$string['section0name']   = 'General';
$string['newsectionname'] = 'New section';
$string['borderradius']        = 'Card corner style';
$string['borderradius_square'] = 'Square (0px)';
$string['borderradius_small']  = 'Slight (4px)';
$string['borderradius_medium'] = 'Medium (8px)';
$string['borderradius_large']  = 'Rounded (16px)';
$string['borderradius_round']  = 'Very rounded (24px)';
$string['borderradius_help']   = 'Controls how rounded the corners of section, subsection, and activity cards appear.';
$string['completionbarcolour']      = 'Progress bar colour';
$string['completionbarcolour_help'] = 'Hex colour code for completion progress bars, e.g. #2d7fc1.';
$string['completionbarshowin']             = 'Show progress bar on section/subsection cards';
$string['completionbarshowin_sections']    = 'Sections only';
$string['completionbarshowin_subsections'] = 'Subsections only';
$string['completionbarshowin_both']        = 'Sections and subsections';
$string['completionbarshowin_help']        = 'Choose which card types display a completion progress bar.';
$string['descriptiondisplay']          = 'Description display (default)';
$string['descriptiondisplay_help']     = 'Course-wide default for where to display descriptions. Can be overridden per item.';
$string['error_borderradius']        = 'Border radius must be between 0 and 24.';
$string['error_completionbarcolour'] = 'Please enter a valid hex colour code, e.g. #2d7fc1 or #fff.';
$string['imagelibrary']              = 'Image library';
$string['imagelibrary_desc']         = 'Site-wide images available to all courses. Upload once, assign to any card.';
$string['imagelibrary_inuse']        = 'This image is currently assigned to {$a} card(s).';
$string['labelrules']              = 'Activity card default rules';
$string['labelrules_desc']         = 'Set a custom label and icon for each activity type in this course.';
$string['labelrules_image']        = 'Default card image';
$string['card_image_source']         = 'Card image';
$string['card_image_source_library'] = 'Choose from image library';
$string['card_image_source_upload']  = 'Upload an image';
$string['card_label_override']       = 'Custom label';
$string['card_icon_override']        = 'Custom icon (Font Awesome class)';
$string['card_icon_enabled']         = 'Show icon on this card';
$string['card_overrides_explainer']   = 'Icons &amp; Labels';
$string['card_overrides_explainer_desc'] = 'Override the icon and label rules for this card only. Leave unchecked to inherit from Card Styling Rules.';
$string['completionprogress']        = 'Progress';
$string['card_completion_todo']      = 'Not yet completed';
$string['card_completion_done']      = 'Completed';
$string['card_completion_failed']    = 'Not passed';
$string['card_completion_criteria']  = 'Completion requires: {$a}';
$string['backtosection']             = 'Back to {$a}';
$string['card_restricted']           = 'Restricted';
$string['card_hidden']               = 'Hidden from students';
$string['card_restrictedhidden']     = '(Hidden otherwise)';
$string['card_restrictedbysection']  = 'This item is inside a restricted section.';
$string['card_restriction_detail']   = 'Access conditions apply — see activity for details.';
$string['editingmode'] = 'You are in editing mode. Use the course index on the left to reorder sections and activities.';
$string['nosections']  = 'No sections have been added to this course yet.';
$string['privacy:metadata'] = 'The ICCA Format plugin does not store any personal data.';
$string['noactivities']           = 'No activities have been added to this section yet.';
$string['subsection_depthlabel']  = 'Contains sub-activities — click to go deeper';

// Card settings page.
$string['cardsettings_image']          = 'Card Styling Overrides';
$string['cardsettings_currentimage']   = 'Current image';
$string['cardsettings_acceptedtypes']  = 'Accepted formats: JPG, PNG, WebP, GIF, SVG';
$string['imagelibrary_empty']          = 'No images in the library yet. Upload one below, or ask your site administrator to add images.';

// Section visibility strings — Moodle looks for these in the active format's lang file.
$string['hidefromothers'] = 'Hide';
$string['showfromothers']  = 'Show';

// Activity and section edit form fieldset.
$string['cardsettings_header']          = 'Card Styling Overrides';
$string['descriptiondisplay_coursdefault'] = 'Use course default';
$string['description_display']          = 'Description display';
$string['description_display_help']     = 'Controls where the section description appears on the card. Leave as course default to use the setting from course settings.';

// Format library.
$string['formatlibrary_site']        = 'Site image library';
$string['formatlibrary_course']      = 'Course image library';
$string['formatlibrary_usages']      = 'uses';
$string['formatlibrary_help']        = 'Images here are available to all courses using ICCA Format.';
$string['formatlibrary_course_help'] = 'Images uploaded for this course only. Available to all cards in this course.';
$string['formatlibrary_imagecount'] = 'images';

// Image display options.
$string['coursesetting_default']       = 'Course default';
$string['imageobjectfit']              = 'Image fit';
$string['imageobjectfit_cover']        = 'Cover (crop to fill)';
$string['imageobjectfit_contain']      = 'Contain (show whole image)';
$string['imageobjectfit_fill']         = 'Fill (stretch to fit)';
$string['imageobjectfit_help']         = 'Controls how the image fills the card area.';
$string['imageobjectposition']         = 'Image position';
$string['imageposition_center']        = 'Center';
$string['imageposition_top']           = 'Top';
$string['imageposition_bottom']        = 'Bottom';
$string['imageposition_left']          = 'Left';
$string['imageposition_right']         = 'Right';
$string['imageposition_topleft']       = 'Top left';
$string['imageposition_topright']      = 'Top right';
$string['imageposition_bottomleft']    = 'Bottom left';
$string['imageposition_bottomright']   = 'Bottom right';
$string['imageobjectposition_help']    = 'Controls which part of the image is shown when cropped.';
$string['imagefilter']                 = 'Image filter';
$string['imagefilter_none']            = 'None';
$string['imagefilter_grayscale']       = 'Greyscale';
$string['imagefilter_sepia']           = 'Sepia';
$string['imagefilter_brighten']        = 'Brighten';
$string['imagefilter_help']            = 'Apply a visual filter to the card image.';
$string['imageoverlay']                = 'Image overlay';
$string['imageoverlay_none']           = 'None';
$string['imageoverlay_light']          = 'Light wash';
$string['imageoverlay_dark']           = 'Dark wash';
$string['imageoverlay_help']           = 'Apply a semi-transparent colour wash over the image.';

// Background colour.
$string['imagebackgroundcolour']      = 'Image background colour';
$string['imagebackgroundcolour_help'] = 'Hex colour shown behind the image. Useful for transparent PNGs — enter your brand colour (e.g. #003366). Leave blank to use white.';
$string['card_description_toggle'] = 'Details';

// Label rules.
$string['labelrules_site']              = 'ICCA Format Card Styling Rules (Site wide)';
$string['labelrules_course']            = 'ICCA Format Card Styling Rules (This course)';
$string['labelrules_site_desc']         = 'Site-wide defaults for card appearance — labels, icons and images. These apply to all courses using ICCA Format unless overridden at course level.';
$string['labelrules_course_desc']       = 'Override card appearance for this course. Site defaults apply to any module type not listed here.';
$string['labelrules_add']               = 'Add rule';
$string['labelrules_edit']              = 'Edit rule';
$string['labelrules_saved']             = 'Rule saved.';
$string['labelrules_deleted']           = 'Rule deleted.';
$string['labelrules_defaults_loaded']   = 'Default rules loaded.';
$string['labelrules_load_defaults'] = 'Load default rules';
$string['labelrules_modtype']           = 'Module type';
$string['labelrules_label']             = 'Label text';
$string['labelrules_label_placeholder'] = 'e.g. Assessment';
$string['labelrules_icon']              = 'Icon (Font Awesome class)';
$string['labelrules_icon_help']         = 'e.g. fa-check-square-o — preview updates as you type';
$string['labelrules_icon_enabled']      = 'Show icon';
$string['labelrules_none']              = 'No rules defined yet.';
$string['labelrules_none_course']       = 'No course-level overrides. Showing inherited site defaults below.';
$string['labelrules_site_inherited']    = 'Site defaults (inherited)';
$string['labelrules_inherited_desc']    = 'These apply unless overridden above.';
$string['labelrules_course_overrides']  = 'Course overrides';
$string['labelrules_deleteconfirm']     = 'Delete this rule?';
$string['labelrules_icon_help_short']  = 'Preview updates as you type';

// Card size.
$string['cardsize']        = 'Card size';
$string['cardsize_help']   = 'Controls the width of cards in the grid. Large is similar to Vista format cards (~360px).';

// Hover animation.
$string['hoverstyle']         = 'Hover animation';
$string['hoverstyle_none']    = 'None';
$string['hoverstyle_imgzoom'] = 'Image zoom';
$string['hoverstyle_lift']    = 'Card lift';
$string['hoverstyle_shadow']  = 'Shadow glow';
$string['hoverstyle_help']    = 'Animation shown when a learner hovers over a card.';
$string['hoverzoom']      = 'Image zoom amount (%)';
$string['hoverzoom_help'] = 'How much the image zooms in on hover. 105 = 5% zoom (default). Range: 100–150.';
$string['card_notracking'] = 'No completion tracking';

// Card sizes (renamed).
$string['cardsize_xsmall'] = 'Extra Small';
$string['cardsize_small']  = 'Small';
$string['cardsize_medium'] = 'Medium';
$string['cardsize_large']  = 'Large';

// Footer font size.
$string['footerfontsize']      = 'Card footer font size (rem)';
$string['footerfontsize_help'] = 'Font size for progress bar label, completion status, and No completion tracking text. Enter a number e.g. 0.85, 1, 1.1. Default: 0.85.';
$string['dropdownfontsize']      = 'Progress dropdown font size (rem)';
$string['dropdownfontsize_help'] = 'Font size for activity/subsection names inside the progress dropdown. Enter a number e.g. 0.75, 0.85, 1. Default: 0.8.';
$string['sectionprogbar'] = 'Show progress bar in section/subsection view';
$string['sectionprogbar_on'] = 'Show';
$string['sectionprogbar_off'] = 'Hide';
$string['sectionicons'] = 'Show subsection icon on cards';
$string['sectionicons_on'] = 'Show';
$string['sectionicons_off'] = 'Hide';
$string['labelrules_unruled_title'] = 'Activity types need icons:';
$string['labelrules_unruled_desc'] = 'The following installed activity types have no label rule. Add a rule below to set their label and icon:';
$string['admindefaults_cards'] = 'Card appearance';
$string['admindefaults_cards_desc'] = 'These settings apply to new courses using ICCA Format. Existing courses keep their own settings.';
$string['admindefaults_hover'] = 'Hover animation';
$string['admindefaults_completion'] = 'Progress bar';
$string['admindefaults_typography'] = 'Typography';
$string['admindefaults_labelrules'] = 'Activity card default rules';
$string['admindefaults_labelrules_desc'] = 'Manage site-wide default labels, icons and images for activity cards:';
$string['labelrules_allcovered'] = 'All installed activity types have a label rule. To edit an existing rule, click Edit in the table below.';
$string['labelrules_reload_defaults'] = 'Load default rules';
$string['labelrules_pushtocourses'] = 'Reset courses to default';
$string['labelrules_pushconfirm'] = 'This will remove any course-level override for this activity type, so all courses inherit the site default. Are you sure?';
$string['labelrules_pushed'] = 'Course overrides cleared for {$a} courses — they now inherit the site default.';
$string['labelrules_visibility']    = 'Visibility';
$string['labelrules_show_label']    = 'Show label';
$string['labelrules_show_icon']     = 'Show icon';
$string['labelrules_pushallcourses']  = 'Reset all courses to site defaults';
$string['labelrules_pushallconfirm']  = 'This will remove ALL course-level label/icon overrides. Every course will inherit site defaults. Are you sure?';
$string['labelrules_pushedall']       = 'All course overrides cleared ({$a} entries removed) — all courses now inherit site defaults.';
$string['completionbarshowin_none'] = 'None';
$string['admindefaults_navigation']      = 'Navigation';
$string['admindefaults_image']    = 'Image display defaults';
$string['labelmode']              = 'Activity labels & icons';
$string['labelmode_help']         = 'Master switch for activity label text and icons on cards. Per-activity settings in Label Rules are preserved and restored when switching back to Both.';
$string['labelmode_both']         = 'Both (labels and icons)';
$string['labelmode_iconsonly']    = 'Icons only';
$string['labelmode_labelsonly']   = 'Labels only';
$string['labelmode_none']         = 'None (hide all labels and icons)';
$string['admindefaults_labelmode']      = 'Activity labels & icons';
$string['admindefaults_labelmode_desc'] = 'Master switch applied to new courses. Individual label rules still control per-activity settings when mode is set to Both.';
$string['showcoursemap']      = 'Course map';
$string['showcoursemap_show'] = 'Show';
$string['showcoursemap_hide'] = 'Hide';
$string['hovercolour']      = 'Shadow hover colour';
$string['hovercolour_help'] = 'Hex colour for the shadow glow ring when hovering over cards. Only applies when Hover animation is set to Shadow glow.';
$string['usemodimages']      = 'Use default activity images';
$string['usemodimages_help'] = 'When enabled, activity cards with no assigned image will use the default image set for that activity type in Label Rules. Images assigned individually to a card always take priority.';
$string['usemodimages_yes']  = 'Yes';
$string['usemodimages_no']   = 'No';
$string['labelrules_defaultimage']  = 'Default image';
$string['labelrules_chooseimage']   = 'Choose image';
$string['labelrules_clearimage']    = 'Clear';
$string['admindefaults_imagelibrary_desc'] = 'Manage images available across all courses using ICCA Format.';
$string['admindefaults_management'] = 'ICCA Format Card Styling Rules & image library';
$string['skip_modtypes']      = 'Skip activity types';
$string['skip_modtypes_help'] = 'Comma-separated list of module names to exclude from Activity card defaults. These modules will not appear in the add rule dropdown or the unruled modules list. Example: label,subsection,chat,bigbluebuttonbn';
$string['back_to_settings'] = 'Back to ICCA Format settings';
$string['skip_modtypes_saved'] = 'Skipped activity types updated.';
$string['descriptionfontsize']      = 'Description font size';
$string['descriptionfontsize_help'] = 'Font size for the hover description text on cards. Enter a number in rem units e.g. 1 or 0.9.';

$string['formatlibrary_site_full'] = 'Site image library';
$string['showhomebutton']           = 'Home button';
$string['showhomebutton_show']      = 'Show';
$string['showhomebutton_hide']      = 'Hide';
$string['sectionprogress_label']    = 'Progress';
$string['navigation_label']         = 'Navigation';

$string['showactivitybar']       = 'Show progress bar on activity cards';
$string['showactivitybar_help']  = 'When enabled, activity cards show a completion progress bar or no tracking message in the card footer.';
$string['admindefaults_sections']      = 'Section defaults';
$string['admindefaults_sections_desc'] = 'Default icon and image settings for section cards. Sections are the top-level groupings in your course. Subsection icons and images are controlled via Activity card default rules. Each course can override these defaults.';
$string['sectionicontype']             = 'Section icon style';
$string['sectionicontype_help']        = 'Choose what appears as the icon badge on section cards. Numbering starts from section 1.';
$string['sectionicontype_none']        = 'None';
$string['sectionicontype_numeric']     = '1, 2, 3...';
$string['sectionicontype_alpha']       = 'a, b, c...';
$string['sectionicontype_ALPHA']       = 'A, B, C...';
$string['sectionicontype_roman']       = 'i, ii, iii...';
$string['sectionicontype_icon']        = 'Custom icon';
$string['sectioncustomicon']           = 'Custom section icon';
$string['sectioncustomicon_help']      = 'Font Awesome icon class to use when "Custom icon" is selected, e.g. fa-book. Leave blank for none.';
$string['sectiondefaultimage']         = 'Default section image';
$string['sectiondefaultimage_help']    = 'Library image ID to use as the default image for section cards that have no explicit image set. Leave as 0 for no default.';
$string['labelrules_reset_course']         = 'Reset to site default';
$string['labelrules_reset_course_confirm'] = 'Remove this course override? The site default rule will apply instead.';
$string['labelrules_override_course']      = 'Override for this course';
$string['labelrules_course_link']          = 'ICCA Format Card Styling Rules (This course)';
$string['labelrules_course_link_desc']     = 'Override activity card labels, icons and images for this course.';
$string['backtocourse'] = 'Back to course';
$string['card_label_enabled']          = 'Show label on this card';
$string['card_image_source_default']   = 'Use default';
$string['card_image_source_noimage']   = 'No image';
$string['homebutton_label'] = 'Course home';

$string['reset_overrides_btn']     = 'Reset all Card Styling Overrides (This course)';
$string['reset_overrides_confirm'] = 'This will remove all individual card icon and label overrides for this course. Cards will revert to inheriting from Card Styling Rules. Are you sure?';

$string['labelrules_reset_course_btn']         = 'Reset Course Card Styling Rules';
$string['labelrules_reset_course_dialog_desc'] = 'This will remove course-level Card Styling Rules, reverting them to the site-wide rules set below. Select which rules to reset:';
$string['labelrules_reset_allcourses_btn']  = 'Reset All Courses to Site Wide Rules';
$string['labelrules_reset_allcourses_desc'] = 'This will remove ALL course-level Card Styling Rules across every course, reverting them to the site-wide rules. This affects all courses using ICCA Format and cannot be undone.';
$string['labelrules_load_defaults_desc'] = 'Adds any missing default Card Styling Rules from the built-in defaults. Existing rules are not overwritten. Useful on first install or after adding new activity types.';
$string['labelrules_reset_courses_sitewide'] = 'Reset Courses to This Rule';
$string['labelrules_flip']  = 'Flip icon and label';
$string['card_flip']        = 'Flip icon and label';
$string['admindefaults_carddefaults']   = 'Card labels, icons and images';
$string['admindefaults_carddefaults_desc'] = 'Controls what shows on cards by default. Card Styling Rules and per-card overrides take priority over these settings.';
$string['carddefaults_sections']        = 'Sections';
$string['carddefaults_subsections']     = 'Subsections';
$string['carddefaults_activities']      = 'Activities';
$string['carddefault_icons']            = 'Icons';
$string['carddefault_labels']           = 'Labels';
$string['carddefault_images']           = 'Images';
$string['carddefault_flip']             = 'Flip icon & label';
$string['bulkedit_btn']    = 'Bulk edit…';
$string['bulkedit_title']  = 'Bulk edit — set values for selected rules:';
$string['bulkedit_hint']   = 'Only fields set to Enable or Disable will be changed. "No change" rows are left untouched.';
$string['bulkedit_apply']  = 'Apply to 0 rule(s)';
$string['bulkedit_clear']  = 'Clear selection';
$string['labelrules_image_hidden']      = 'Hide image on cards';
$string['labelrules_image_hidden_desc'] = 'Image is stored but hidden. Uncheck to show it again.';
$string['labelrules_image_hidden_pill'] = 'Image hidden';
$string['bulkedit_images_hint']         = 'For images: Enable shows the image, Disable hides it (keeps it stored), Remove deletes it permanently.';
$string['labelrules_bulk_updated'] = '{$a} rule(s) updated.';
$string['labelrules_bulk_deleted'] = '{$a} rule(s) deleted.';
$string['labelrules_bulk_resettosite'] = '{$a} rule(s) reset to site-wide defaults.';
$string['labelrules_bulk_resetallcourses'] = '{$a} rule(s) reset across all courses.';
$string['labelrules_bulk_pushedselected'] = '{$a} rule(s) reset to site-wide defaults across all courses.';
$string['coursemaplabels'] = 'Course map labels';
$string['coursemaplabels_show'] = 'Show';
$string['coursemaplabels_hide'] = 'Hide';
$string['coursemaplabels_help'] = 'Shows a heading above each row of the course map. The headings use the label text from Card Styling Rules for Sections and Subsections (for example "Lesson"), and still appear when labels are switched off on the cards themselves.';
$string['coursemaplabels_desc'] = 'Default for new courses. Shows a heading above each row of the course map, using the Card Styling Rules label for Sections and Subsections.';
$string['coursemap_sectionsheading'] = 'Section';
$string['coursemap_subsectionsheading'] = 'Subsection';
$string['card_hiddenrestrictionnote'] = 'Students can\'t see this while it\'s hidden. These restrictions will apply once you show it.';
$string['labelrules_ui_manageimagelib_site'] = 'Manage the site image library.';
$string['labelrules_ui_manageimagelib_course'] = 'Manage this course\'s image library.';
$string['labelrules_ui_resetselected'] = 'Reset selected';
$string['labelrules_ui_searchicons'] = 'Search icons...';
$string['labelrules_ui_imagename'] = 'Image name';
$string['labelrules_ui_searchimages'] = 'Search images...';
$string['labelrules_ui_available'] = 'Available';
$string['labelrules_ui_skipped'] = 'Skipped';
$string['labelrules_ui_skip'] = 'Skip';
$string['labelrules_ui_unskip'] = 'Unskip';
$string['labelrules_ui_nselected'] = '{$a} selected';
$string['labelrules_ui_be_enable'] = 'Enable';
$string['labelrules_ui_be_disable'] = 'Disable';
$string['labelrules_ui_be_remove'] = 'Remove';
$string['labelrules_ui_be_nochange'] = 'No change';
$string['labelrules_ui_search'] = 'Search...';
$string['labelrules_ui_hideoverridden'] = 'Hide overridden';
$string['labelrules_ui_flipped'] = 'Flipped';
$string['labelrules_ui_flipped_title'] = 'Icon and label order is flipped';
$string['labelrules_ui_vis_label'] = 'Label';
$string['labelrules_ui_vis_icon'] = 'Icon';
$string['labelrules_ui_overridden'] = 'Overridden';
$string['labelrules_ui_confirm_resetallcourses'] = 'Reset all courses to the site-wide rule for this module type?';
$string['labelrules_ui_js_selectrule'] = 'Please select at least one rule.';
$string['labelrules_ui_js_selectfield'] = 'Please select at least one field to change.';
$string['labelrules_ui_js_confirmbulkedit'] = 'Apply bulk edit to {$a} rule(s)?';
$string['labelrules_ui_js_confirmreset'] = 'Reset {$a} rule(s)?';
$string['labelrules_ui_js_confirmdelete'] = 'Delete {$a} rule(s)? This cannot be undone.';
$string['labelrules_ui_js_applyto'] = 'Apply to {$a} rule(s)';
$string['imagelibrary_ui_addtolib'] = 'Add to library';
$string['imagelibrary_ui_addn'] = 'Add {$a} images to library';
$string['imagelibrary_ui_nfiles'] = '{$a} files selected';