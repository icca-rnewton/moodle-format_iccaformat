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
 * Card settings page for format_iccaformat.
 *
 * Handles both section/subsection settings (via ?sectionid=X)
 * and activity settings (via ?cmid=X).
 *
 * Settings available:
 *   - Card image (library picker or direct upload)
 *   - Description display (hidden / in-box / below-box)
 *   - Label override (activities only)
 *   - Icon override (activities only)
 *   - Icon enabled toggle (activities only)
 *
 * @package   format_iccaformat
 * @copyright 2026 ICCA
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once('../../../config.php');
require_once($CFG->dirroot . '/course/lib.php');

use format_iccaformat\local\format_option;
use format_iccaformat\local\image_library;

// --- Parameters --------------------------------------------------------------

$courseid  = required_param('courseid', PARAM_INT);
$sectionid = optional_param('sectionid', 0, PARAM_INT);
$cmid      = optional_param('cmid', 0, PARAM_INT);

if (!$sectionid && !$cmid) {
    throw new moodle_exception('missingparam', 'error', '', 'sectionid or cmid');
}

// --- Auth and context --------------------------------------------------------

$course  = $DB->get_record('course', ['id' => $courseid], '*', MUST_EXIST);
$context = context_course::instance($courseid);

require_login($course);
require_capability('moodle/course:update', $context);

$format  = course_get_format($course);
$modinfo = get_fast_modinfo($course);

// --- Resolve element details -------------------------------------------------

if ($sectionid) {
    // Section or subsection settings.
    $section = $DB->get_record('course_sections', ['id' => $sectionid, 'course' => $courseid], '*', MUST_EXIST);
    $elementtype = ($section->component === 'mod_subsection')
        ? format_option::ELEMENT_SUBSECTION
        : format_option::ELEMENT_SECTION;
    $elementid   = $sectionid;
    $elementname = $format->get_section_name($section);
    $returnurl   = $format->get_view_url($section) ?? new moodle_url('/course/view.php', ['id' => $courseid]);
    $issection   = true;
    $iscm        = false;
} else {
    // Activity settings.
    $cm = get_coursemodule_from_id('', $cmid, $courseid, false, MUST_EXIST);
    require_capability('moodle/course:manageactivities', $context);
    $elementtype = format_option::ELEMENT_CM;
    $elementid   = $cmid;
    $elementname = $cm->name;
    $returnurl   = new moodle_url('/course/view.php', ['id' => $courseid]);
    $issection   = false;
    $iscm        = true;
}

// --- Load existing options ---------------------------------------------------

$optioncache = format_option::get_all_for_course($courseid);

$currentsource  = format_option::get_from_cache($optioncache, $elementid, format_option::OPT_IMAGE_SOURCE, '');
$currentimageid = (int) format_option::get_from_cache($optioncache, $elementid, format_option::OPT_IMAGE_ID, 0);
$currentdescdisplay = format_option::get_from_cache($optioncache, $elementid,
    format_option::OPT_DESCRIPTION_DISPLAY, '');
$currentlabel   = format_option::get_from_cache($optioncache, $elementid, format_option::OPT_LABEL_OVERRIDE, '');
$currenticon    = format_option::get_from_cache($optioncache, $elementid, format_option::OPT_ICON_OVERRIDE, '');
$currenticonenabled = format_option::get_from_cache($optioncache, $elementid, format_option::OPT_ICON_ENABLED, '1');

// --- Image library data ------------------------------------------------------

$libraryimages = image_library::get_all_for_course($courseid);

// Attach URLs to each image record for the picker template.
foreach ($libraryimages['site'] as &$img) {
    $img->url = image_library::get_url($img);
}
foreach ($libraryimages['course'] as &$img) {
    $img->url = image_library::get_url($img);
}
unset($img);

// --- Handle form submission --------------------------------------------------

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_sesskey();

    $action = optional_param('action', '', PARAM_ALPHA);

    if ($action === 'save') {
        // Image source.
        $imagesource = optional_param('imagesource', '', PARAM_ALPHA);
        $imageid     = optional_param('imageid', 0, PARAM_INT);

        if ($imagesource === 'library' && $imageid) {
            format_option::set($courseid, $elementtype, $elementid, format_option::OPT_IMAGE_SOURCE, 'library');
            format_option::set($courseid, $elementtype, $elementid, format_option::OPT_IMAGE_ID, $imageid);
        } else if ($imagesource === 'upload') {
            // Handle file upload.
            $draftitemid = optional_param('imagedraft', 0, PARAM_INT);
            if ($draftitemid) {
                $fs          = get_file_storage();
                $usercontext = context_user::instance($USER->id);
                $draftfiles  = $fs->get_area_files($usercontext->id, 'user', 'draft', $draftitemid, '', false);

                if (!empty($draftfiles)) {
                    $draftfile = reset($draftfiles);
                    $mimetype  = $draftfile->get_mimetype();

                    if (in_array($mimetype, image_library::ACCEPTED_TYPES)) {
                        // Delete any existing upload for this element.
                        $fs->delete_area_files($context->id, 'format_iccaformat', 'element_image', $elementid);

                        $fileinfo = [
                            'contextid' => $context->id,
                            'component' => 'format_iccaformat',
                            'filearea'  => 'element_image',
                            'itemid'    => $elementid,
                            'filepath'  => '/',
                            'filename'  => $draftfile->get_filename(),
                        ];

                        if ($mimetype === 'image/svg+xml') {
                            $fs->create_file_from_string($fileinfo,
                                image_library::sanitise_svg($draftfile->get_content()));
                        } else {
                            $fs->create_file_from_storedfile($fileinfo, $draftfile);
                        }

                        format_option::set($courseid, $elementtype, $elementid,
                            format_option::OPT_IMAGE_SOURCE, 'upload');
                        // Clear any library imageid.
                        format_option::set($courseid, $elementtype, $elementid,
                            format_option::OPT_IMAGE_ID, 0);
                    }
                }
            }
        } else if ($imagesource === 'none') {
            format_option::set($courseid, $elementtype, $elementid, format_option::OPT_IMAGE_SOURCE, '');
            format_option::set($courseid, $elementtype, $elementid, format_option::OPT_IMAGE_ID, 0);
            // Delete any direct upload.
            $fs = get_file_storage();
            $fs->delete_area_files($context->id, 'format_iccaformat', 'element_image', $elementid);
        }

        // Also save library image globally if requested.
        $savetolibrary = optional_param('savetolibrary', 0, PARAM_INT);
        $libdraftitemid = optional_param('imagedraft', 0, PARAM_INT);
        if ($imagesource === 'upload' && $savetolibrary && $libdraftitemid) {
            $libname = optional_param('libname', $elementname, PARAM_TEXT);
            $libdesc = optional_param('libdesc', '', PARAM_TEXT);
            $libscope = optional_param('libscope', 'course', PARAM_ALPHA);
            $libcontextid = ($libscope === 'site')
                ? context_system::instance()->id
                : $context->id;
            image_library::save_new($libname, $libdesc, $libdraftitemid, $libcontextid, $USER->id);
        }

        // Description display.
        $descdisplay = optional_param('description_display', '', PARAM_ALPHA);
        if (in_array($descdisplay, ['hidden', 'inbox', 'belowbox'])) {
            format_option::set($courseid, $elementtype, $elementid,
                format_option::OPT_DESCRIPTION_DISPLAY, $descdisplay);
        }

        // Label and icon (cm only).
        if ($iscm) {
            $labeloverride = optional_param('label_override', '', PARAM_TEXT);
            $iconoverride  = optional_param('icon_override', '', PARAM_TEXT);
            $iconenabled   = optional_param('icon_enabled', '0', PARAM_INT) ? '1' : '0';

            format_option::set($courseid, $elementtype, $elementid,
                format_option::OPT_LABEL_OVERRIDE, $labeloverride);
            format_option::set($courseid, $elementtype, $elementid,
                format_option::OPT_ICON_OVERRIDE, $iconoverride);
            format_option::set($courseid, $elementtype, $elementid,
                format_option::OPT_ICON_ENABLED, $iconenabled);
        }

        // Purge format caches so changes appear immediately.
        course_modinfo::clear_instance_cache($course);

        redirect($returnurl, get_string('cardsettings_saved', 'format_iccaformat'),
            null, \core\output\notification::NOTIFY_SUCCESS);
    }
}

// --- Page setup --------------------------------------------------------------

$PAGE->set_url('/course/format/iccaformat/section_settings.php',
    ['courseid' => $courseid, 'sectionid' => $sectionid, 'cmid' => $cmid]);
$PAGE->set_context($context);
$PAGE->set_course($course);
$PAGE->set_pagelayout('incourse');
$PAGE->set_title(get_string('cardsettings', 'format_iccaformat') . ': ' . $elementname);
$PAGE->set_heading($course->fullname);

// Draft file area for upload.
$draftitemid = file_get_submitted_draft_itemid('imagedraft');
file_prepare_draft_area($draftitemid, $context->id, 'format_iccaformat', 'element_image', $elementid);

// --- Output ------------------------------------------------------------------

echo $OUTPUT->header();
echo $OUTPUT->heading(get_string('cardsettings', 'format_iccaformat') . ': ' . $elementname, 3);

// Build template data.
$templatedata = [
    'courseid'          => $courseid,
    'sectionid'         => $sectionid,
    'cmid'              => $cmid,
    'elementname'       => $elementname,
    'sesskey'           => sesskey(),
    'returnurl'         => $returnurl->out(false),
    'issection'         => $issection,
    'iscm'              => $iscm,
    'draftitemid'       => $draftitemid,

    // Current values.
    'currentsource'         => $currentsource,
    'currentimageid'        => $currentimageid,
    'source_none'           => ($currentsource === '' || $currentsource === null),
    'source_library'        => ($currentsource === 'library'),
    'source_upload'         => ($currentsource === 'upload'),
    'currentdescdisplay'    => $currentdescdisplay,
    'desc_hidden'           => ($currentdescdisplay === 'hidden' || !$currentdescdisplay),
    'desc_inbox'            => ($currentdescdisplay === 'inbox'),
    'desc_belowbox'         => ($currentdescdisplay === 'belowbox'),
    'currentlabel'          => $currentlabel,
    'currenticon'           => $currenticon,
    'icon_enabled'          => ($currenticonenabled === '1'),

    // Library images.
    'hassiteimages'   => !empty($libraryimages['site']),
    'siteimages'      => $libraryimages['site'],
    'hascourseimages' => !empty($libraryimages['course']),
    'courseimages'    => $libraryimages['course'],

    // Current image preview (if set).
    'haspreview' => false,
    'previewurl' => '',
];

// If a library image is selected, build its preview URL.
if ($currentsource === 'library' && $currentimageid) {
    $librecord = image_library::get($currentimageid);
    if ($librecord) {
        $templatedata['haspreview'] = true;
        $templatedata['previewurl'] = image_library::get_url($librecord);
    }
} else if ($currentsource === 'upload') {
    // Build pluginfile URL for the existing upload.
    $fs = get_file_storage();
    $files = $fs->get_area_files($context->id, 'format_iccaformat', 'element_image', $elementid, '', false);
    if (!empty($files)) {
        $file = reset($files);
        $templatedata['haspreview'] = true;
        $templatedata['previewurl'] = moodle_url::make_pluginfile_url(
            $context->id, 'format_iccaformat', 'element_image',
            $elementid, '/', $file->get_filename()
        )->out(false);
    }
}

echo $OUTPUT->render_from_template('format_iccaformat/local/card_settings', $templatedata);

echo $OUTPUT->footer();
