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
 * Card image library management page for format_iccaformat.
 *
 * @package   format_iccaformat
 * @copyright 2026 Inns of Court College of Advocacy (Part of COIC)
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once('../../../config.php');
require_once($CFG->dirroot . '/course/lib.php');

use format_iccaformat\local\image_library;

$courseid = optional_param('courseid', 0, PARAM_INT);
$action   = optional_param('action', 'list', PARAM_ALPHA);
$imageid  = optional_param('imageid', 0, PARAM_INT);

// --- Auth ---------------------------------------------------------------

if ($courseid) {
    $course       = $DB->get_record('course', ['id' => $courseid], '*', MUST_EXIST);
    $context      = context_course::instance($courseid);
    require_login($course);
    require_capability('moodle/course:update', $context);
    $issiteadmin  = has_capability('moodle/site:config', context_system::instance());
    $pagetitle    = get_string('formatlibrary_course', 'format_iccaformat');
    $returnurl    = new moodle_url('/course/view.php', ['id' => $courseid]);
    $libcontextid = $context->id;
} else {
    $context      = context_system::instance();
    require_login();
    require_capability('moodle/site:config', $context);
    $issiteadmin  = true;
    $pagetitle    = get_string('formatlibrary_site', 'format_iccaformat');
    $returnurl    = new moodle_url('/admin/category.php', ['category' => 'courseformats']);
    $libcontextid = $context->id;
    $course       = null;
    $courseid     = 0;
}

$PAGE->set_url('/course/format/iccaformat/imagelibrary.php', ['courseid' => $courseid]);
$PAGE->set_context($context);
$PAGE->set_pagelayout($courseid ? 'incourse' : 'admin');
$PAGE->set_title($pagetitle);
$PAGE->set_heading($courseid ? $course->fullname : get_string('formatlibrary_site', 'format_iccaformat'));

// --- Handle actions -----------------------------------------------------

// Edit image — rename and/or replace file.
if ($action === 'edit' && $imageid && confirm_sesskey()) {
    $record = image_library::get($imageid);
    if ($record && ($issiteadmin || (int)$record->contextid === (int)$libcontextid)) {
        $newname = core_text::substr(trim(optional_param('editname', '', PARAM_TEXT)), 0, 255);
        if ($newname) {
            global $DB;
            $DB->set_field('iccaformat_image_library', 'name', $newname, ['id' => $imageid]);
        }
        // Replace file if one was uploaded — same type checks and SVG cleaning as a new upload.
        if (!empty($_FILES['editfile']['tmp_name']) && $_FILES['editfile']['error'] === UPLOAD_ERR_OK) {
            if (!image_library::replace_from_raw_upload($imageid, $_FILES['editfile']['tmp_name'],
                    $_FILES['editfile']['name'])) {
                redirect(new moodle_url('/course/format/iccaformat/imagelibrary.php', ['courseid' => $courseid]),
                    get_string('imagelibrary_upload_failed', 'format_iccaformat'),
                    null, \core\output\notification::NOTIFY_WARNING);
            }
        }
        redirect(new moodle_url('/course/format/iccaformat/imagelibrary.php', ['courseid' => $courseid]),
            get_string('imagelibrary_imageupdated', 'format_iccaformat'),
            null, \core\output\notification::NOTIFY_SUCCESS);
    }
}

if ($action === 'deletebulk' && confirm_sesskey()) {
    $ids    = optional_param_array('imageids', [], PARAM_INT);
    $deleted = 0;
    foreach ($ids as $delid) {
        $record = image_library::get($delid);
        if ($record && ($issiteadmin || (int)$record->contextid === (int)$libcontextid)) {
            image_library::delete($delid);
            $deleted++;
        }
    }
    redirect(new moodle_url('/course/format/iccaformat/imagelibrary.php', ['courseid' => $courseid]),
        get_string('imagelibrary_deleted_count', 'format_iccaformat', $deleted),
        null, \core\output\notification::NOTIFY_SUCCESS);
}

if ($action === 'delete' && $imageid && confirm_sesskey()) {
    $record = image_library::get($imageid);
    if ($record && (int)$record->contextid === (int)$libcontextid) {
        image_library::delete($imageid);
        redirect(new moodle_url('/course/format/iccaformat/imagelibrary.php', ['courseid' => $courseid]),
            get_string('imagelibrary_deleted', 'format_iccaformat'),
            null, \core\output\notification::NOTIFY_SUCCESS);
    } else if ($record && $issiteadmin) {
        // Fallback — site admins can delete any image.
        image_library::delete($imageid);
        redirect(new moodle_url('/course/format/iccaformat/imagelibrary.php', ['courseid' => $courseid]),
            get_string('imagelibrary_deleted', 'format_iccaformat'),
            null, \core\output\notification::NOTIFY_SUCCESS);
    }
}

// Promote a course image to the site-wide library. Site admins only, and only an image
// from the library being viewed — enforced here, not just by hiding the button.
if ($action === 'promote' && $imageid && $issiteadmin && confirm_sesskey()) {
    $record = image_library::get($imageid);
    $newid  = ($record && (int) $record->contextid === (int) $libcontextid)
        ? image_library::promote_to_format_library($imageid, $USER->id) : 0;
    if ($newid) {
        redirect(new moodle_url('/course/format/iccaformat/imagelibrary.php', ['courseid' => $courseid]),
            get_string('formatlibrary_promoted', 'format_iccaformat'),
            null, \core\output\notification::NOTIFY_SUCCESS);
    }
}

if ($action === 'upload' && confirm_sesskey()) {
    $added   = 0;
    $skipped = 0;

    // Multi-file upload — files come as arrays when name="imagefile_direct[]"
    $files = $_FILES['imagefile_direct'] ?? [];
    if (!empty($files['tmp_name'])) {
        // Normalise to array form whether one or many files submitted.
        $tmpnames  = is_array($files['tmp_name']) ? $files['tmp_name'] : [$files['tmp_name']];
        $filenames = is_array($files['name']) ? $files['name'] : [$files['name']];
        $errors    = is_array($files['error']) ? $files['error'] : [$files['error']];

        foreach ($tmpnames as $i => $tmp) {
            if (empty($tmp) || ($errors[$i] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
                $skipped++;
                continue;
            }
            $origname = $filenames[$i] ?? 'image';
            // Derive a name from the filename — strip extension, replace separators with spaces.
            $autoname = preg_replace('/\.[^.]+$/', '', $origname);
            $autoname = trim(preg_replace('/[_\-]+/', ' ', $autoname));
            $autoname = ucwords(strtolower($autoname));
            if (!$autoname) { $autoname = 'Image ' . ($i + 1);
            }

            $newid = image_library::save_new_from_raw_upload(
                $autoname, $tmp, $origname, $libcontextid, $USER->id
            );
            if ($newid) { $added++;
            } else { $skipped++;
            }
        }
    }

    if ($added > 0) {
        $msg = $added === 1
            ? get_string('imagelibrary_updated', 'format_iccaformat')
            : get_string('imagelibrary_added_multiple', 'format_iccaformat', $added);
        if ($skipped > 0) {
            $msg .= ' ' . get_string('imagelibrary_upload_skipped', 'format_iccaformat', $skipped);
        }
        redirect(new moodle_url('/course/format/iccaformat/imagelibrary.php', ['courseid' => $courseid]),
            $msg, null, \core\output\notification::NOTIFY_SUCCESS);
    } else {
        $msg = $skipped > 0
            ? get_string('imagelibrary_upload_allskipped', 'format_iccaformat', $skipped)
            : get_string('imagelibrary_upload_failed', 'format_iccaformat');
        redirect(new moodle_url('/course/format/iccaformat/imagelibrary.php', ['courseid' => $courseid]),
            $msg, null, \core\output\notification::NOTIFY_WARNING);
    }
}

// --- Load images --------------------------------------------------------

$images     = image_library::get_all_for_context($libcontextid);
$sysctxid   = context_system::instance()->id;
$siteimages = ($libcontextid !== $sysctxid)
    ? image_library::get_all_for_context($sysctxid)
    : [];

// --- Helper: format filesize --------------------------------------------

function icca_format_filesize(int $bytes): string {
    if ($bytes >= 1048576) { return round($bytes / 1048576, 1) . 'MB';
    }
    if ($bytes >= 1024) {    return round($bytes / 1024)       . 'KB';
    }
    return $bytes . 'B';
}

// --- Helper: render image tile ------------------------------------------

function icca_render_tile($img, $courseid, $isformatlib, $issiteadmin) {
    global $DB;
    $imgurl = \format_iccaformat\local\image_library::get_url($img);

    $elementusages = \format_iccaformat\local\image_library::count_usages($img->id);
    $labelusages   = $DB->count_records('iccaformat_label_rules', ['libraryimageid' => $img->id]);
    $usages        = $elementusages + $labelusages;
    $candelete     = (!$isformatlib || $issiteadmin);
    $incourses     = ($elementusages > 0 || $labelusages > 0);

    $fs      = get_file_storage();
    $file    = $fs->get_file($img->contextid, 'format_iccaformat', 'library_image',
        $img->id, '/', $img->filename);
    $sizestr = $file ? icca_format_filesize($file->get_filesize()) : '';
    $ext     = $file ? strtoupper(pathinfo($img->filename, PATHINFO_EXTENSION)) : '';
    $roclass = $isformatlib && !$issiteadmin ? ' icca-tile-ro' : '';

    echo '<div class="icca-lib-tile' . $roclass . '"'
        . ' data-imgid="' . (int)$img->id . '"'
        . ' data-haslabel="' . ($labelusages > 0 ? '1' : '0') . '"'
        . ' data-hascourse="' . ($incourses ? '1' : '0') . '"'
        . ' data-name="' . s(strtolower($img->name)) . '"'
        . ' onclick="iccaTileClick(this)">';
    echo '<img src="' . $imgurl . '" alt="' . s($img->name) . '" class="icca-tile-thumb">';

    // Select checkbox — top left.
    if ($candelete) {
        echo '<label class="icca-tile-check" title="' . get_string('imagelibrary_ui_select', 'format_iccaformat') . '" onclick="event.stopPropagation();">'
            . '<input type="checkbox" class="icca-tile-checkbox" value="' . (int)$img->id . '"'
            . ' onchange="iccaUpdateBulkBar()" onclick="event.stopPropagation();">'
            . '</label>';
    }

    // Action buttons — top right (edit + delete).
    if ($candelete) {
        $delurl = new moodle_url('/course/format/iccaformat/imagelibrary.php',
            ['courseid' => $courseid, 'action' => 'delete', 'imageid' => $img->id, 'sesskey' => sesskey()]);
        $delconfirm = $usages > 0
            ? get_string('imagelibrary_deleteconfirm_inuse', 'format_iccaformat', $usages)
            : get_string('imagelibrary_deleteconfirm', 'format_iccaformat');
        echo '<div class="icca-tile-actions" onclick="event.stopPropagation()">';
        echo '<a href="#" class="icca-tile-action icca-tile-edit"'
            . ' title="' . get_string('imagelibrary_ui_edit', 'format_iccaformat') . '" onclick="event.stopPropagation();iccaOpenEdit(' . (int)$img->id . ', \''
            . addslashes(s($img->name)) . '\'); return false;">'
            . '<i class="fa fa-pencil" aria-hidden="true"></i></a>';
        echo '<a href="' . $delurl . '" class="icca-tile-action icca-tile-del"'
            . ' onclick="event.stopPropagation();return confirm(\'' . addslashes($delconfirm) . '\');"'
            . ' title="' . get_string('delete') . '">'
            . '<i class="fa fa-trash" aria-hidden="true"></i></a>';
        echo '</div>';
    }

    echo '<div class="icca-tile-foot">';
    // Top row: name
    echo '<div class="icca-tile-name">' . s($img->name) . '</div>';
    if ($ext || $sizestr) {
        echo '<div class="icca-tile-meta">' . s(trim($ext . ($sizestr ? ' · ' . $sizestr : ''))) . '</div>';
    }
    // Bottom row: pills left, actions right.
    echo '<div class="icca-tile-foot-row">';
    // Pills left.
    echo '<div class="icca-tile-pills">';
    if ($labelusages > 0) {
        echo '<span class="icca-pill icca-pill-label" title="' . get_string('imagelibrary_ui_usedinrules', 'format_iccaformat', $labelusages) . '">'
            . '<i class="fa fa-star" aria-hidden="true"></i> ' . $labelusages . '</span>';
    }
    if ($incourses) {
        echo '<span class="icca-pill icca-pill-course" title="' . get_string('imagelibrary_ui_usedincourses', 'format_iccaformat') . '">'
            . '<i class="fa fa-graduation-cap" aria-hidden="true"></i></span>';
    }
    echo '</div>';
    // Actions right: promote (course images only) + download.
    echo '<div class="icca-tile-foot-actions">';
    if (!$isformatlib && $issiteadmin && $candelete) {
        $promoteurl = new moodle_url('/course/format/iccaformat/imagelibrary.php',
            ['courseid' => $courseid, 'action' => 'promote', 'imageid' => $img->id, 'sesskey' => sesskey()]);
        echo '<a href="' . $promoteurl . '" class="icca-tile-action icca-tile-promote"'
            . ' title="' . get_string('formatlibrary_promote', 'format_iccaformat') . '"'
            . ' onclick="event.stopPropagation();">+</a>';
    }
    echo '<a href="' . htmlspecialchars($imgurl, ENT_QUOTES) . '" download="' . s($img->filename) . '"'
        . ' class="icca-tile-action icca-tile-download"'
        . ' title="' . get_string('imagelibrary_ui_download', 'format_iccaformat') . '" onclick="event.stopPropagation();">'
        . '<i class="fa fa-download" aria-hidden="true"></i></a>';
    echo '</div>';
    echo '</div>'; // foot-row
    echo '</div>'; // tile-foot
    echo '</div>'; // tile
}

function icca_render_grid($images, $courseid, $isformatlib, $issiteadmin) {
    if (empty($images)) {
        echo '<p class="icca-lib-empty">' . get_string('imagelibrary_empty', 'format_iccaformat') . '</p>';
        return;
    }
    echo '<div class="icca-lib-grid">';
    foreach ($images as $img) {
        icca_render_tile($img, $courseid, $isformatlib, $issiteadmin);
    }
    echo '</div>';
}

// --- Output -------------------------------------------------------------

echo $OUTPUT->header();

// Back to settings link + cross-link button.
if ($courseid === 0) {
    echo html_writer::link(
        new moodle_url('/admin/settings.php', ['section' => 'formatsettingiccaformat']),
        '&#8592; ' . get_string('back_to_settings', 'format_iccaformat'),
        ['style' => 'font-size:1rem;color:#6c757d;text-decoration:none;display:inline-block;margin-bottom:0.75rem;']
    );
    echo '<br>';
    echo html_writer::link(
        new moodle_url('/course/format/iccaformat/labelrules.php'),
        get_string('labelrules_site', 'format_iccaformat'),
        ['class' => 'btn btn-secondary', 'style' => 'margin-bottom:1rem;']
    );
    echo '<p style="font-size:1rem;color:#6c757d;margin-bottom:1rem;">' . get_string('imagelibrary_ui_managerules', 'format_iccaformat') . '</p>';
} else {
    // Course page — link back to course and to course labelrules.
    echo html_writer::link(
        new moodle_url('/course/view.php', ['id' => $courseid]),
        '&#8592; ' . get_string('backtocourse', 'format_iccaformat'),
        ['style' => 'font-size:1rem;color:#6c757d;text-decoration:none;display:inline-block;margin-bottom:0.75rem;']
    );
    echo '<br>';
    echo html_writer::link(
        new moodle_url('/course/format/iccaformat/labelrules.php', ['courseid' => $courseid]),
        get_string('labelrules_course', 'format_iccaformat'),
        ['class' => 'btn btn-secondary', 'style' => 'margin-bottom:1rem;']
    );
    echo '<p style="font-size:1rem;color:#6c757d;margin-bottom:1rem;">' . get_string('imagelibrary_ui_managerules', 'format_iccaformat') . '</p>';
}
?>
<style>
.icca-lib-wrap { display:grid; grid-template-columns:260px 1fr; gap:1.5rem; align-items:start; margin-top:1rem; }

/* Sidebar */
.icca-lib-sidebar { background:#f8f9fa; border-radius:8px; padding:1.25rem; border:1px solid #dee2e6; position:sticky; top:1rem; }
.icca-lib-sidebar h3 { font-size:1rem; font-weight:600; color:#495057; margin-bottom:14px; display:flex; align-items:center; gap:7px; }
.icca-lib-sidebar label { display:block; font-size:1rem; color:#6c757d; margin-bottom:4px; margin-top:12px; }
.icca-lib-sidebar label:first-of-type { margin-top:0; }
.icca-lib-sidebar input[type=text] { width:100%; border:1px solid #dee2e6; border-radius:6px; padding:7px 10px; font-size:1rem; background:#fff; }
.icca-lib-sidebar .icca-file-area { width:100%; border:1.5px dashed #dee2e6; border-radius:6px; padding:14px 10px; text-align:center; background:#fff; color:#adb5bd; font-size:1rem; cursor:pointer; margin-top:4px; position:relative; overflow:hidden; }
.icca-lib-sidebar .icca-file-area i { display:block; font-size:22px; margin-bottom:5px; color:#ced4da; }
.icca-lib-sidebar .icca-file-area span { color:#0d3c6f; text-decoration:underline; }
.icca-lib-sidebar .icca-file-area input[type=file] { position:absolute; inset:0; opacity:0; cursor:pointer; width:100%; height:100%; }
.icca-lib-sidebar .icca-file-hint { font-size:0.85rem; color:#adb5bd; margin-top:5px; }
.icca-lib-sidebar .icca-add-btn { width:100%; margin-top:14px; padding:9px; background:#0d3c6f; color:#fff; border:none; border-radius:6px; font-size:1rem; cursor:pointer; font-weight:500; }
.icca-lib-sidebar .icca-add-btn:hover { background:#0a2f57; }
.icca-lib-sidebar .icca-sidebar-note { margin-top:14px; padding-top:14px; border-top:1px solid #dee2e6; font-size:0.9rem; color:#adb5bd; line-height:1.5; }
.icca-lib-sidebar .icca-sidebar-note a { color:#0d3c6f; }
.icca-lib-altlink { margin-bottom:1rem; }

/* Grid area */
.icca-lib-grid-area { }
.icca-lib-grid-meta { display:flex; justify-content:space-between; align-items:center; margin-bottom:10px; }
.icca-lib-grid-meta span { font-size:1rem; color:#6c757d; }
.icca-lib-section-head { font-size:0.8rem; font-weight:600; color:#6c757d; text-transform:uppercase; letter-spacing:0.07em; margin-bottom:10px; padding-bottom:6px; border-bottom:1px solid #dee2e6; display:flex; align-items:center; justify-content:space-between; }
.icca-lib-section-head .count { font-weight:normal; color:#adb5bd; text-transform:none; letter-spacing:0; font-size:1rem; }

/* Tiles */
.icca-lib-grid { display:grid; grid-template-columns:repeat(auto-fill,minmax(180px,1fr)); gap:10px; margin-bottom:1.5rem; }
.icca-lib-tile { border-radius:8px; overflow:hidden; border:1px solid #dee2e6; background:#f8f9fa; position:relative; transition:border-color 0.15s, box-shadow 0.15s; }
.icca-lib-tile:hover { border-color:#0d3c6f; box-shadow:0 2px 8px rgba(0,0,0,0.1); }

.icca-tile-thumb { width:100%; height:120px; object-fit:cover; display:block; }
.icca-tile-foot { padding:6px 8px; background:#fff; border-top:1px solid #f0f0f0; }
.icca-tile-name { font-size:1rem; color:#495057; white-space:nowrap; overflow:hidden; text-overflow:ellipsis; font-weight:500; margin-bottom:3px; }
.icca-tile-meta { font-size:0.75rem; color:#adb5bd; }
.icca-tile-foot-row { display:flex; align-items:center; justify-content:space-between; gap:4px; }
.icca-tile-pills { display:flex; align-items:center; gap:4px; flex-wrap:nowrap; overflow:hidden; }
.icca-pill { display:inline-flex; align-items:center; gap:3px; font-size:0.7rem; font-weight:500; padding:2px 6px; border-radius:8px; white-space:nowrap; line-height:1; min-height:20px; }
.icca-pill-label { background:#e8f0fb; color:#0d3c6f; }
.icca-pill-course { background:#f0f0f0; color:#6c757d; }
.icca-tile-foot-actions { display:flex; gap:3px; flex-shrink:0; opacity:0; transition:opacity 0.15s; }
.icca-lib-tile:hover .icca-tile-foot-actions { opacity:1; }
.icca-tile-actions { position:absolute; top:4px; right:4px; display:flex; gap:3px; opacity:0; transition:opacity 0.15s; }
.icca-lib-tile:hover .icca-tile-actions { opacity:1; }
.icca-tile-action { background:rgba(255,255,255,0.95); border:none; border-radius:4px; width:22px; height:22px; font-size:1rem; display:flex; align-items:center; justify-content:center; text-decoration:none; box-shadow:0 1px 3px rgba(0,0,0,0.15); cursor:pointer; }
.icca-tile-del { color:#dc3545; }
.icca-tile-del:hover { background:#dc3545; color:#fff; }
.icca-tile-edit { color:#0d6e3c; }
.icca-tile-edit:hover { background:#0d6e3c; color:#fff; }
.icca-tile-download { color:#0d3c6f; }
.icca-tile-download:hover { background:#0d3c6f; color:#fff; }
.icca-tile-foot-promote { position:absolute; bottom:6px; right:6px; opacity:0; transition:opacity 0.15s; }
.icca-lib-tile:hover .icca-tile-foot-promote { opacity:1; }

.icca-tile-check { position:absolute; top:5px; left:5px; width:20px; height:20px; opacity:0; transition:opacity 0.15s; cursor:pointer; display:flex; align-items:center; justify-content:center; }
.icca-lib-tile:hover .icca-tile-check,
.icca-tile-check:has(input:checked) { opacity:1; }
.icca-tile-check input { width:16px; height:16px; cursor:pointer; accent-color:#0d3c6f; }
.icca-lib-tile.selected { border-color:#0d3c6f; box-shadow:0 0 0 2px #0d3c6f; }
.icca-lib-tile.selected .icca-tile-check { opacity:1; }
.icca-tile-ro { opacity:0.75; }
.icca-tile-ro:hover { opacity:1; border-color:#adb5bd; box-shadow:none; }
.icca-lib-empty { font-size:1rem; color:#adb5bd; padding:1rem 0; }
</style>
<?php

echo '<div class="icca-lib-wrap">';

// Bulk delete bar — shown when images are selected.
$bulkurl = new moodle_url('/course/format/iccaformat/imagelibrary.php',
    ['courseid' => $courseid, 'action' => 'deletebulk', 'sesskey' => sesskey()]);
echo '<form id="icca-bulk-bar" method="post" action="' . $bulkurl . '" style="display:none;grid-column:1/-1;background:#fff3cd;border:1px solid #ffe082;border-radius:8px;padding:10px 14px;align-items:center;gap:12px;margin-bottom:4px;">';
echo '<input type="hidden" name="sesskey" value="' . sesskey() . '">';
echo '<span id="icca-bulk-count" style="font-size:1rem;color:#856404;font-weight:500;">' . get_string('imagelibrary_ui_nselected', 'format_iccaformat', 0) . '</span>';
echo '<button type="submit" class="btn btn-danger btn-sm" style="display:inline-block;"'
    . ' onclick="return iccaBulkDeleteConfirm();">'
    . '<i class="fa fa-trash" aria-hidden="true"></i> ' . get_string('imagelibrary_ui_deleteselected', 'format_iccaformat') . '</button>';
echo '<button type="button" class="btn btn-secondary btn-sm" style="display:inline-block;" onclick="iccaClearSelection()">' . get_string('imagelibrary_ui_clearselection', 'format_iccaformat') . '</button>';
echo '</form>';


// ── Sidebar ──────────────────────────────────────────────────────────────
$uploadurl = new moodle_url('/course/format/iccaformat/imagelibrary.php',
    ['courseid' => $courseid, 'action' => 'upload', 'sesskey' => sesskey()]);

echo '<div class="icca-lib-sidebar">';
echo '<h3><i class="fa fa-cloud-upload" aria-hidden="true"></i> '
    . get_string('imagelibrary_add', 'format_iccaformat') . '</h3>';

echo '<form method="post" enctype="multipart/form-data" action="' . $uploadurl . '">';
echo '<input type="hidden" name="sesskey" value="' . sesskey() . '">';

echo '<label for="libname_new" id="libname_label">' . get_string('imagelibrary_name', 'format_iccaformat') . '</label>';
echo '<input type="text" id="libname_new" name="libname" placeholder="' . get_string('imagelibrary_name', 'format_iccaformat') . '" autocomplete="new-password">';
echo '<p id="libname_hint" style="font-size:0.85rem;color:#6c757d;margin:-8px 0 10px;display:none;">' . get_string('imagelibrary_ui_namehint', 'format_iccaformat') . '</p>';

echo '<div style="display:flex;justify-content:space-between;align-items:center;margin-top:12px;">';
echo '<label style="margin:0;">' . get_string('imagelibrary_file', 'format_iccaformat') . '</label>';
echo '<button type="button" id="icca-clear-btn" onclick="iccaClearUpload()" style="padding:3px 12px;background:transparent;border:1px solid #dee2e6;border-radius:6px;font-size:0.9rem;color:#6c757d;cursor:pointer;">' . get_string('imagelibrary_ui_clear', 'format_iccaformat') . '</button>';
echo '</div>';
echo '<div class="icca-file-area">';
echo '<i class="fa fa-image" aria-hidden="true"></i>';
echo '<span id="icca-file-label">' . get_string('imagelibrary_ui_dropfiles', 'format_iccaformat') . ' <span style="color:#0d3c6f;text-decoration:underline;">' . get_string('imagelibrary_ui_browse', 'format_iccaformat') . '</span></span>';
echo '<input type="file" id="libfile_new" name="imagefile_direct[]" accept=".jpg,.jpeg,.png,.webp,.gif,.svg" multiple>';
echo '</div>';
echo '<p class="icca-file-hint">' . get_string('imagelibrary_ui_filetypes', 'format_iccaformat') . '</p>';

echo '<button type="submit" id="icca-add-btn" class="icca-add-btn" disabled style="width:100%;margin-top:14px;opacity:0.5;cursor:not-allowed;">'
    . get_string('imagelibrary_add', 'format_iccaformat') . '</button>';
echo '</form>';

$iccalibjs = [];
foreach (['addtolib', 'addn', 'nfiles', 'visibleof', 'nselected', 'confirmdelete', 'dropfiles', 'browse'] as $iccalibkey) {
    $iccalibjs[$iccalibkey] = get_string('imagelibrary_ui_' . $iccalibkey, 'format_iccaformat');
}
// Lang strings for this page's scripts. {$a} (or {$a->x}) is replaced in JS where used.
echo '<script>var iccaLibStr = ' . json_encode($iccalibjs, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) . ';'
    . 'function iccaLibBrowse() { var w = document.createElement("span"); w.appendChild(document.createTextNode(iccaLibStr.dropfiles + " "));'
    . ' var b = document.createElement("span"); b.style.cssText = "color:#0d3c6f;text-decoration:underline;";'
    . ' b.textContent = iccaLibStr.browse; w.appendChild(b); return w.innerHTML; }</script>';
echo '<script>
(function() {
    var nameInput = document.getElementById("libname_new");
    var nameLabel = document.getElementById("libname_label");
    var nameHint  = document.getElementById("libname_hint");
    var fileInput = document.getElementById("libfile_new");
    var fileLabel = document.getElementById("icca-file-label");
    var btn       = document.getElementById("icca-add-btn");
    btn.textContent = iccaLibStr.addtolib;

    function setNameFromFile() {
        if (fileInput.files && fileInput.files.length === 1) {
            var filename = fileInput.files[0].name.replace(/\.[^.]+$/, "").replace(/[_\-]+/g, " ");
            filename = filename.charAt(0).toUpperCase() + filename.slice(1);
            nameInput.value = filename;
        }
    }

    function check() {
        var count = fileInput.files ? fileInput.files.length : 0;
        var multi = count > 1;

        if (multi) {
            nameInput.style.display = "none";
            nameLabel.style.display = "none";
            nameHint.style.display  = "block";
            nameInput.removeAttribute("required");
            fileLabel.textContent = iccaLibStr.nfiles.replace("{$a}", count);
        } else if (count === 1) {
            nameInput.style.display = "";
            nameLabel.style.display = "";
            nameHint.style.display  = "none";
            fileLabel.textContent = fileInput.files[0].name;
        } else {
            nameInput.style.display = "";
            nameLabel.style.display = "";
            nameHint.style.display  = "none";
            nameInput.value = "";
            fileLabel.innerHTML = iccaLibBrowse();
        }

        var ready = count > 0 && (multi || nameInput.value.trim() !== "");
        btn.disabled = !ready;
        btn.style.opacity = ready ? "1" : "0.5";
        btn.style.cursor  = ready ? "pointer" : "not-allowed";
        btn.textContent   = multi ? iccaLibStr.addn.replace("{$a}", count) : iccaLibStr.addtolib;
    }

    nameInput.addEventListener("input", check);
    fileInput.addEventListener("change", function() {
        setNameFromFile();
        check();
    });
    check();
})();
function iccaLibFilter() {
    var search    = (document.getElementById("icca-lib-search").value || "").toLowerCase().trim();
    var wantLabel = document.getElementById("icca-filter-label").checked;
    var wantCourse= document.getElementById("icca-filter-course").checked;
    var tiles     = document.querySelectorAll(".icca-lib-tile");
    var visible   = 0;
    tiles.forEach(function(tile) {
        var name      = (tile.dataset.name || "").toLowerCase();
        var hasLabel  = tile.dataset.haslabel === "1";
        var hasCourse = tile.dataset.hascourse === "1";
        var nameMatch = !search || name.includes(search);
        var tagMatch;
        if (wantLabel && wantCourse) { tagMatch = true; }
        else if (wantLabel && !wantCourse) { tagMatch = hasLabel; }
        else if (!wantLabel && wantCourse) { tagMatch = hasCourse; }
        else { tagMatch = !hasLabel && !hasCourse; }
        var show = nameMatch && tagMatch;
        tile.style.display = show ? "" : "none";
        if (show) visible++;
    });
    var countEl = document.getElementById("icca-lib-count");
    if (countEl) countEl.textContent = iccaLibStr.visibleof.replace("{$a->visible}", visible).replace("{$a->total}", tiles.length);
}
window.addEventListener("load", function() { iccaLibFilter(); });
</script>';

// Course view note.
if ($courseid) {
    echo '<p class="icca-sidebar-note">'
        . get_string('imagelibrary_ui_coursenote', 'format_iccaformat') . ' '
        . '<a href="' . new moodle_url('/course/format/iccaformat/imagelibrary.php') . '">'
        . get_string('formatlibrary_site', 'format_iccaformat') . '</a>.'
        . '</p>';
}
// Admin view: no sidebar note needed, the button is at the top.

echo '</div>'; // sidebar

// ── Grid area ─────────────────────────────────────────────────────────────
echo '<div class="icca-lib-grid-area">';

// Filter bar.
echo '<div style="background:#fff;border:1px solid #dee2e6;border-radius:8px;padding:10px 14px;margin-bottom:12px;display:flex;align-items:center;gap:16px;flex-wrap:wrap;">';
echo '<input type="text" id="icca-lib-search" placeholder="Search by name..." oninput="iccaLibFilter()"'
    . ' style="border:1px solid #dee2e6;border-radius:6px;padding:6px 10px;font-size:1rem;flex:1;min-width:160px;">';
echo '<label style="display:flex;align-items:center;gap:6px;font-size:1rem;cursor:pointer;white-space:nowrap;">'
    . '<input type="checkbox" id="icca-filter-label" checked onchange="iccaLibFilter()" style="accent-color:#0d3c6f;width:16px;height:16px;">'
    . '<i class="fa fa-star" style="font-size:0.75rem;color:#0d3c6f;" aria-hidden="true"></i> ' . get_string('imagelibrary_ui_legend_rule', 'format_iccaformat') . '</label>';
echo '<label style="display:flex;align-items:center;gap:6px;font-size:1rem;cursor:pointer;white-space:nowrap;">'
    . '<input type="checkbox" id="icca-filter-course" checked onchange="iccaLibFilter()" style="accent-color:#6c757d;width:16px;height:16px;">'
    . '<i class="fa fa-graduation-cap" style="font-size:0.75rem;color:#6c757d;" aria-hidden="true"></i> ' . get_string('imagelibrary_ui_legend_course', 'format_iccaformat') . '</label>';
echo '<span id="icca-lib-count" style="font-size:0.85rem;color:#adb5bd;margin-left:auto;"></span>';
echo '</div>';

if ($courseid === 0) {
    // Site admin — single grid.
    echo '<div class="icca-lib-grid-meta">';
    echo '<span>' . count($images) . ' ' . get_string('formatlibrary_imagecount', 'format_iccaformat') . '</span>';
    echo '</div>';
    icca_render_grid($images, $courseid, true, $issiteadmin);
} else {
    // Course — two sections.
    echo '<div class="icca-lib-section-head">';
    echo get_string('formatlibrary_course', 'format_iccaformat');
    echo '<span class="count">' . count($images) . ' ' . get_string('formatlibrary_imagecount', 'format_iccaformat') . '</span>';
    echo '</div>';
    icca_render_grid($images, $courseid, false, $issiteadmin);

    echo '<div class="icca-lib-section-head">';
    echo get_string('formatlibrary_site', 'format_iccaformat');
    echo '<span class="count">' . count($siteimages) . ' ' . get_string('formatlibrary_imagecount', 'format_iccaformat') . ' &middot; read-only</span>';
    echo '</div>';
    icca_render_grid($siteimages, $courseid, true, $issiteadmin);
}

echo '</div>'; // grid-area
echo '</div>'; // wrap

// ── Edit image modal ─────────────────────────────────────────────────────
$editactionurl = new moodle_url('/course/format/iccaformat/imagelibrary.php',
    ['courseid' => $courseid, 'action' => 'edit', 'sesskey' => sesskey()]);
echo '<div id="icca-edit-modal" style="display:none;position:fixed;inset:0;background:rgba(0,0,0,0.5);z-index:99999;align-items:center;justify-content:center;">';
echo '<div style="background:#fff;border-radius:10px;padding:1.5rem;width:90%;max-width:420px;box-shadow:0 8px 32px rgba(0,0,0,0.2);">';
echo '<h4 style="margin:0 0 1rem;font-size:1.1rem;">' . get_string('imagelibrary_ui_editimage', 'format_iccaformat') . '</h4>';
echo '<form id="icca-edit-form" method="post" enctype="multipart/form-data" action="' . $editactionurl . '">';
echo '<input type="hidden" name="sesskey" value="' . sesskey() . '">';
echo '<input type="hidden" id="icca-edit-imageid" name="imageid" value="">';
echo '<label style="font-size:1rem;font-weight:500;display:block;margin-bottom:4px;">' . get_string('name') . '</label>';
echo '<input type="text" id="icca-edit-name" name="editname" style="width:100%;border:1px solid #dee2e6;border-radius:6px;padding:7px 10px;font-size:1rem;margin-bottom:1rem;" autocomplete="off">';
echo '<label style="font-size:1rem;font-weight:500;display:block;margin-bottom:4px;">' . get_string('imagelibrary_ui_replacefile', 'format_iccaformat') . ' <span style="font-size:0.85rem;color:#6c757d;font-weight:normal;">' . get_string('imagelibrary_ui_optional', 'format_iccaformat') . '</span></label>';
echo '<input type="file" id="icca-edit-file" name="editfile" accept=".jpg,.jpeg,.png,.webp,.gif,.svg" style="width:100%;margin-bottom:1rem;">';
echo '<div style="display:flex;gap:8px;justify-content:flex-end;">';
echo '<button type="button" onclick="iccaCloseEdit()" style="padding:8px 16px;background:transparent;border:1px solid #dee2e6;border-radius:6px;font-size:1rem;cursor:pointer;">' . get_string('cancel') . '</button>';
echo '<button type="submit" style="padding:8px 16px;background:#0d3c6f;color:#fff;border:none;border-radius:6px;font-size:1rem;cursor:pointer;font-weight:500;">' . get_string('savechanges') . '</button>';
echo '</div>';
echo '</form></div></div>';

?>
<script>
(function() {
    var fileInput = document.getElementById('libfile_new');
    var nameInput = document.getElementById('libname_new');
    var fileLabel = document.getElementById('icca-file-label');
    if (!fileInput) return;

    fileInput.addEventListener('change', function() {
        if (!this.files || !this.files[0]) return;
        var filename = this.files[0].name;
        // Update label.
        if (fileLabel) fileLabel.textContent = filename;
        // Auto-fill name if empty.
        if (nameInput && !nameInput.value) {
            var name = filename.replace(/\.[^/.]+$/, '').replace(/[_\-]+/g, ' ');
            name = name.replace(/\b\w/g, function(c) { return c.toUpperCase(); });
            nameInput.value = name;
        }
    });
})();
function iccaTileClick(tile) {
    var cb = tile.querySelector(".icca-tile-checkbox");
    if (!cb) return;
    cb.checked = !cb.checked;
    iccaUpdateBulkBar();
}
function iccaUpdateBulkBar() {
    var checked = document.querySelectorAll(".icca-tile-checkbox:checked");
    var bar     = document.getElementById("icca-bulk-bar");
    var count   = document.getElementById("icca-bulk-count");
    var form    = document.getElementById("icca-bulk-bar");
    form.querySelectorAll("input[name='imageids[]']").forEach(function(el){ el.remove(); });
    checked.forEach(function(cb) {
        var input = document.createElement("input");
        input.type = "hidden";
        input.name = "imageids[]";
        input.value = cb.value;
        form.appendChild(input);
        cb.closest(".icca-lib-tile").classList.add("selected");
    });
    document.querySelectorAll(".icca-lib-tile").forEach(function(tile) {
        var cb = tile.querySelector(".icca-tile-checkbox");
        if (cb && !cb.checked) tile.classList.remove("selected");
    });
    if (bar) bar.style.display = checked.length > 0 ? "flex" : "none";
    if (count) count.textContent = iccaLibStr.nselected.replace("{$a}", checked.length);
}
function iccaClearSelection() {
    document.querySelectorAll(".icca-tile-checkbox").forEach(function(cb){ cb.checked = false; });
    document.querySelectorAll(".icca-lib-tile").forEach(function(t){ t.classList.remove("selected"); });
    var bar = document.getElementById("icca-bulk-bar");
    if (bar) bar.style.display = "none";
}
function iccaBulkDeleteConfirm() {
    var count = document.querySelectorAll(".icca-tile-checkbox:checked").length;
    return confirm(iccaLibStr.confirmdelete.replace("{$a}", count));
}
function iccaClearUpload() {
    var fileInput = document.getElementById("libfile_new");
    var nameInput = document.getElementById("libname_new");
    var fileLabel = document.getElementById("icca-file-label");
    var btn       = document.getElementById("icca-add-btn");
    if (fileInput) fileInput.value = "";
    if (nameInput) nameInput.value = "";
    if (fileLabel) fileLabel.innerHTML = iccaLibBrowse();
    if (btn) { btn.disabled = true; btn.style.opacity = "0.5"; btn.style.cursor = "not-allowed"; btn.textContent = iccaLibStr.addtolib; }
}
function iccaOpenEdit(imgid, imgname) {
    document.getElementById("icca-edit-imageid").value = imgid;
    document.getElementById("icca-edit-name").value    = imgname;
    document.getElementById("icca-edit-file").value    = "";
    document.getElementById("icca-edit-modal").style.display = "flex";
}
function iccaCloseEdit() {
    document.getElementById("icca-edit-modal").style.display = "none";
}
document.addEventListener("DOMContentLoaded", function() {
    var modal = document.getElementById("icca-edit-modal");
    if (modal) modal.addEventListener("click", function(e) { if (e.target === this) iccaCloseEdit(); });
});
</script>
<?php
echo $OUTPUT->footer();