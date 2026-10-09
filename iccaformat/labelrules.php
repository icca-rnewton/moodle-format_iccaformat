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
 * Label rules management page for format_iccaformat.
 *
 * Manages the modtype → label/icon mapping used on activity cards.
 *
 * Usage:
 *   Site-wide defaults: /course/format/iccaformat/labelrules.php
 *   Course overrides:   /course/format/iccaformat/labelrules.php?courseid=X
 *
 * @package   format_iccaformat
 * @copyright 2026 ICCA
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once('../../../config.php');

$courseid    = optional_param('courseid', 0, PARAM_INT);
$action      = optional_param('action', 'list', PARAM_ALPHA);
$ruleid      = optional_param('ruleid', 0, PARAM_INT);
$overridemod = optional_param('override', '', PARAM_ALPHANUMEXT); // pre-populate add form

// --- Auth ----------------------------------------------------------------

if ($courseid) {
    $course  = $DB->get_record('course', ['id' => $courseid], '*', MUST_EXIST);
    $context = context_course::instance($courseid);
    require_login($course);
    require_capability('moodle/course:update', $context);
    $issiteadmin = has_capability('moodle/site:config', context_system::instance());
    $pagetitle   = get_string('labelrules_course', 'format_iccaformat');
    $scopeid     = $courseid;
} else {
    $context  = context_system::instance();
    require_login();
    require_capability('moodle/site:config', $context);
    $issiteadmin = true;
    $pagetitle   = get_string('labelrules_site', 'format_iccaformat');
    $scopeid     = 0;
    $courseid    = 0;
    $course      = null;
}

$PAGE->set_url('/course/format/iccaformat/labelrules.php', ['courseid' => $courseid]);
$PAGE->set_context($context);
$PAGE->set_pagelayout($courseid ? 'incourse' : 'admin');
$PAGE->set_title($pagetitle);
$PAGE->set_heading($courseid ? $course->fullname : get_string('labelrules_site', 'format_iccaformat'));

// Default site-level rules shipped with the format.
$defaultrules = [
    'quiz'        => ['label' => 'Assessment',   'icon' => 'fa-check-square-o'],
    'subsection'  => ['label' => 'Subsection',    'icon' => 'fa-layer-group'],
    'section'     => ['label' => 'Section',       'icon' => 'numeric'],
    'assign'      => ['label' => 'Assignment',   'icon' => 'fa-pencil-square-o'],
    'page'        => ['label' => 'Reading',      'icon' => 'fa-file-text-o'],
    'url'         => ['label' => 'Link',         'icon' => 'fa-link'],
    'resource'    => ['label' => 'File',         'icon' => 'fa-file-o'],
    'forum'       => ['label' => 'Discussion',   'icon' => 'fa-comments-o'],
    'scorm'       => ['label' => 'Course',       'icon' => 'fa-play-circle-o'],
    'h5pactivity' => ['label' => 'Interactive',  'icon' => 'fa-hand-pointer-o'],
    'lesson'      => ['label' => 'Lesson',       'icon' => 'fa-graduation-cap'],
    'workshop'    => ['label' => 'Workshop',     'icon' => 'fa-users'],
    'choice'      => ['label' => 'Choice',       'icon' => 'fa-question-circle-o'],
    'feedback'    => ['label' => 'Feedback',     'icon' => 'fa-comment-o'],
    'glossary'    => ['label' => 'Glossary',     'icon' => 'fa-book'],
    'book'        => ['label' => 'Guide',        'icon' => 'fa-book'],
    'lti'         => ['label' => 'External',     'icon' => 'fa-external-link'],
    'folder'      => ['label' => 'Folder',       'icon' => 'fa-folder-o'],
    'wiki'        => ['label' => 'Wiki',         'icon' => 'fa-pencil'],
    'data'        => ['label' => 'Database',     'icon' => 'fa-database'],
    'dataform'    => ['label' => 'Data Form',    'icon' => 'fa-wpforms'],
    'cpdrecord'   => ['label' => 'CPD Record',   'icon' => 'fa-cube'],
    'groupmembers'=> ['label' => 'Groups',       'icon' => 'fa-group'],
];

$selfurl = new moodle_url('/course/format/iccaformat/labelrules.php', ['courseid' => $courseid]);

// --- Handle actions -------------------------------------------------------

if ($action === 'saveskip' && $issiteadmin && confirm_sesskey()) {
    $skipped = optional_param_array('skipped', [], PARAM_ALPHANUMEXT);
    set_config('skip_modtypes', implode(',', $skipped), 'format_iccaformat');
    redirect($selfurl, get_string('skip_modtypes_saved', 'format_iccaformat'),
        null, \core\output\notification::NOTIFY_SUCCESS);
}

if ($action === 'save' && confirm_sesskey()) {
    $modname        = required_param('modname', PARAM_ALPHANUMEXT);
    $label          = optional_param('label', '', PARAM_TEXT);
    $icon           = optional_param('icon', '', PARAM_TEXT);
    $iconenabled    = optional_param('icon_enabled',  0, PARAM_INT);
    $labelenabled   = optional_param('label_enabled', 0, PARAM_INT);
    $flipped        = optional_param('flipped', 0, PARAM_INT);
    $image_hidden   = optional_param('image_hidden', 0, PARAM_INT);
    $libraryimageid = optional_param('libraryimageid', 0, PARAM_INT);

    // If a new image was uploaded, save it to the appropriate library first.
    if (!empty($_FILES['rule_imageupload']['tmp_name']) && $_FILES['rule_imageupload']['error'] === UPLOAD_ERR_OK) {
        $uploadname = optional_param('rule_imagename', '', PARAM_TEXT);
        if (!$uploadname) {
            $uploadname = clean_filename(pathinfo($_FILES['rule_imageupload']['name'], PATHINFO_FILENAME));
            $uploadname = ucfirst(str_replace(['-', '_'], ' ', $uploadname));
        }
        // Route to all-courses library (contextid=1) for site rules, course library for course rules.
        $uploadcontextid = $scopeid === 0
            ? context_system::instance()->id
            : context_course::instance($scopeid)->id;
        $newid = \format_iccaformat\local\image_library::save_new_from_raw_upload(
            $uploadname,
            $_FILES['rule_imageupload']['tmp_name'],
            $_FILES['rule_imageupload']['name'],
            $uploadcontextid,
            $USER->id
        );
        if ($newid) {
            $libraryimageid = $newid;
        }
    }

    $existing = $DB->get_record('iccaformat_label_rules',
        ['courseid' => $scopeid, 'modname' => $modname]);

    $record = (object)[
        'courseid'       => $scopeid,
        'modname'        => $modname,
        'label'          => $label,
        'icon'           => $icon,
        'label_enabled'  => $labelenabled ? 1 : 0,
        'icon_enabled'   => $iconenabled ? 1 : 0,
        'flipped'        => $flipped ? 1 : 0,
        'image_hidden'   => $image_hidden ? 1 : 0,
        'libraryimageid' => $libraryimageid ?: null,
        'timemodified'   => time(),
    ];

    if ($existing) {
        $record->id = $existing->id;
        $DB->update_record('iccaformat_label_rules', $record);
    } else {
        $DB->insert_record('iccaformat_label_rules', $record);
    }

    redirect($selfurl, get_string('labelrules_saved', 'format_iccaformat'),
        null, \core\output\notification::NOTIFY_SUCCESS);
}

// Bulk action handler.
if ($action === 'bulkedit' && confirm_sesskey()) {
    $ruleids = optional_param_array('ruleids', [], PARAM_INT);
    if (!empty($ruleids)) {
        $fields = [
            'icon_enabled'  => optional_param('be_icons',  'nochange', PARAM_ALPHA),
            'label_enabled' => optional_param('be_labels', 'nochange', PARAM_ALPHA),
            'image_hidden'  => optional_param('be_images', 'nochange', PARAM_ALPHA),
            'flipped'       => optional_param('be_flip',   'nochange', PARAM_ALPHA),
        ];
        $updated = 0;
        foreach ($ruleids as $id) {
            $rule = $DB->get_record('iccaformat_label_rules', ['id' => (int)$id, 'courseid' => $scopeid]);
            if (!$rule) continue;
            $changed = false;
            foreach ($fields as $col => $val) {
                if ($val === 'nochange') continue;
                if ($col === 'image_hidden') {
                    if ($val === 'enable')  { $rule->image_hidden = 0; $changed = true; }
                    if ($val === 'disable') { $rule->image_hidden = 1; $changed = true; }
                    if ($val === 'remove')  { $rule->libraryimageid = 0; $rule->image_hidden = 0; $changed = true; }
                } else {
                    $rule->$col = ($val === 'enable') ? 1 : 0;
                    $changed = true;
                }
            }
            if ($changed) {
                $rule->timemodified = time();
                $DB->update_record('iccaformat_label_rules', $rule);
                $updated++;
            }
        }
        redirect($selfurl, $updated . ' rule(s) updated.', null, \core\output\notification::NOTIFY_SUCCESS);
    }
    redirect($selfurl);
}

if ($action === 'bulkaction' && confirm_sesskey()) {
    $bulkop  = optional_param('bulkop', '', PARAM_ALPHA);
    $ruleids = optional_param_array('ruleids', [], PARAM_INT);
    if (!empty($ruleids)) {
        if ($bulkop === 'delete') {
            foreach ($ruleids as $id) {
                $DB->delete_records('iccaformat_label_rules', ['id' => $id, 'courseid' => $scopeid]);
            }
            redirect($selfurl, count($ruleids) . ' rule(s) deleted.', null, \core\output\notification::NOTIFY_SUCCESS);
        } else if ($bulkop === 'resettosite' && $scopeid !== 0) {
            foreach ($ruleids as $id) {
                $DB->delete_records('iccaformat_label_rules', ['id' => $id, 'courseid' => $scopeid]);
            }
            redirect($selfurl, count($ruleids) . ' rule(s) reset to site-wide defaults.', null, \core\output\notification::NOTIFY_SUCCESS);
        } else if ($bulkop === 'resetallcourses' && $scopeid === 0) {
            // Get the modnames for the selected rule IDs.
            list($insql, $params) = $DB->get_in_or_equal($ruleids);
            $selectedrules = $DB->get_records_select('iccaformat_label_rules', "id $insql AND courseid = 0", $params);
            $deleted = 0;
            foreach ($selectedrules as $r) {
                $DB->delete_records_select('iccaformat_label_rules', 'courseid != 0 AND modname = ?', [$r->modname]);
                $deleted++;
            }
            redirect($selfurl, $deleted . ' rule(s) reset across all courses.', null, \core\output\notification::NOTIFY_SUCCESS);
        }
    }
    redirect($selfurl);
}

if ($action === 'resetcourse' && $ruleid && $scopeid !== 0 && confirm_sesskey()) {
    $rule = $DB->get_record('iccaformat_label_rules', ['id' => $ruleid, 'courseid' => $scopeid]);
    if ($rule) {
        $DB->delete_records('iccaformat_label_rules', ['id' => $ruleid]);
    }
    redirect($selfurl, get_string('labelrules_reset_course', 'format_iccaformat'),
        null, \core\output\notification::NOTIFY_SUCCESS);
}

// Bulk reset selected course rules back to site defaults.
if ($action === 'resetselected' && $scopeid !== 0 && confirm_sesskey()) {
    $ids = optional_param_array('ruleids', [], PARAM_INT);
    $deleted = 0;
    foreach ($ids as $id) {
        $rule = $DB->get_record('iccaformat_label_rules', ['id' => (int)$id, 'courseid' => $scopeid]);
        if ($rule) {
            $DB->delete_records('iccaformat_label_rules', ['id' => $rule->id]);
            $deleted++;
        }
    }
    redirect($selfurl, $deleted . ' rule(s) reset to site-wide defaults.',
        null, \core\output\notification::NOTIFY_SUCCESS);
}

if ($action === 'delete' && $ruleid && confirm_sesskey()) {
    $rule = $DB->get_record('iccaformat_label_rules', ['id' => $ruleid]);
    if ($rule && (int)$rule->courseid === $scopeid) {
        $DB->delete_records('iccaformat_label_rules', ['id' => $ruleid]);
    }
    redirect($selfurl, get_string('labelrules_deleted', 'format_iccaformat'),
        null, \core\output\notification::NOTIFY_SUCCESS);
}

if ($action === 'seedefaults' && $issiteadmin && $scopeid === 0 && confirm_sesskey()) {
    foreach ($defaultrules as $modname => $defaults) {
        if (!$DB->record_exists('iccaformat_label_rules', ['courseid' => 0, 'modname' => $modname])) {
            $DB->insert_record('iccaformat_label_rules', (object)[
                'courseid'     => 0,
                'modname'      => $modname,
                'label'        => $defaults['label'],
                'icon'         => $defaults['icon'],
                'label_enabled' => 1,
                    'icon_enabled'  => 1,
                    'flipped'       => 0,
                'timemodified' => time(),
            ]);
        }
    }
    redirect($selfurl, get_string('labelrules_defaults_loaded', 'format_iccaformat'),
        null, \core\output\notification::NOTIFY_SUCCESS);
}

// --- Action: push ALL site rules to all courses ---------------------------
// Push selected modnames to all courses (reset those course rules).
if ($scopeid === 0 && $action === 'pushselectedcourses' && confirm_sesskey()) {
    $modnames = optional_param_array('modnames', [], PARAM_ALPHANUMEXT);
    $pushed = 0;
    foreach ($modnames as $modname) {
        $DB->delete_records_select('iccaformat_label_rules',
            'courseid != 0 AND modname = ?', [$modname]);
        $pushed++;
    }
    redirect($selfurl, $pushed . ' rule(s) reset to site-wide defaults across all courses.',
        null, \core\output\notification::NOTIFY_SUCCESS);
}

if ($scopeid === 0 && optional_param('action', '', PARAM_ALPHA) === 'pushallcourses') {
    require_sesskey();
    // Delete ALL course-level overrides so every course inherits site defaults.
    $pushed = $DB->count_records_select('iccaformat_label_rules', 'courseid > 0');
    $DB->delete_records_select('iccaformat_label_rules', 'courseid > 0');
    redirect($selfurl,
        get_string('labelrules_pushedall', 'format_iccaformat', $pushed),
        null, \core\output\notification::NOTIFY_SUCCESS);
}

// --- Action: push site rule to all courses --------------------------------
if ($scopeid === 0 && optional_param('action', '', PARAM_ALPHA) === 'pushtocourses') {
    require_sesskey();
    $pushmod = required_param('modname', PARAM_ALPHANUMEXT);
    $siterule = $DB->get_record('iccaformat_label_rules', ['courseid' => 0, 'modname' => $pushmod]);
    if ($siterule) {
        // Delete course-level overrides for this mod so courses inherit the site default.
        $pushed = $DB->count_records_select('iccaformat_label_rules',
            'modname = :modname AND courseid > 0', ['modname' => $pushmod]);
        $DB->delete_records_select('iccaformat_label_rules',
            'modname = :modname AND courseid > 0', ['modname' => $pushmod]);
        redirect($selfurl,
            get_string('labelrules_pushed', 'format_iccaformat', $pushed),
            null, \core\output\notification::NOTIFY_SUCCESS);
    }
}

// --- Load current rules ---------------------------------------------------

$rules = $DB->get_records('iccaformat_label_rules', ['courseid' => $scopeid], 'modname ASC');

// Load site rules for reference when viewing course page.
$siterules_raw = ($scopeid !== 0)
    ? $DB->get_records('iccaformat_label_rules', ['courseid' => 0], 'modname ASC')
    : [];
// Re-key by modname for easy lookup.
$siterules = [];
foreach ($siterules_raw as $r) {
    $siterules[$r->modname] = $r;
}

// Get available module types — exclude any that already have a rule at this scope.
// Also exclude non-activity mods that shouldn't have label rules.
// Load skip list from admin settings — no hardcoded fallback.
// Empty = nothing skipped, all modules available.
$skipraw      = get_config('format_iccaformat', 'skip_modtypes');
$skipraw      = ($skipraw !== false) ? $skipraw : '';
$skipmodtypes = array_filter(array_map('trim', explode(',', $skipraw)));
// Get all mod names as a simple flat array.
$allmodnames  = array_keys($DB->get_records_menu('modules', [], 'name ASC', 'name,name'));
$existingmods = array_column(array_values($rules), 'modname');
// Build availmodtypes as a simple array for reliable filtering.
$availmodtypes = [];
foreach ($allmodnames as $m) {
    if (!in_array($m, $existingmods, true) && !in_array($m, $skipmodtypes, true)) {
        $availmodtypes[] = $m;
    }
}
sort($availmodtypes);
// Keep allmodtypes as associative for plugin manager loop below.
$allmodtypes = array_combine($allmodnames, $allmodnames);

// Build friendly name map using core_plugin_manager.
$modnamesmap = [];
$pluginman   = core_plugin_manager::instance();
$modplugins  = $pluginman->get_plugins_of_type('mod');
foreach ($allmodtypes as $mname) {
    if (isset($modplugins[$mname])) {
        $modnamesmap[$mname] = $modplugins[$mname]->displayname ?: $mname;
    } else {
        $modnamesmap[$mname] = $mname;
    }
}

// Find mods that have no rule at any scope (site or course) — flag for attention.
$siterulemodnames = array_keys($defaultrules);
$dbsiterules = $DB->get_records('iccaformat_label_rules', ['courseid' => 0], '', 'modname');
foreach ($dbsiterules as $r) {
    $siterulemodnames[] = $r->modname;
}
$siterulemodnames = array_unique($siterulemodnames);
$unruledmods = [];
foreach ($allmodnames as $m) {
    if (!in_array($m, $siterulemodnames, true) && !in_array($m, $skipmodtypes, true)) {
        $unruledmods[] = $m;
    }
}

// --- Output ---------------------------------------------------------------

echo $OUTPUT->header();

// Back link — different target for site vs course.
if ($scopeid === 0) {
    echo html_writer::link(
        new moodle_url('/admin/settings.php', ['section' => 'formatsettingiccaformat']),
        '&#8592; ' . get_string('back_to_settings', 'format_iccaformat'),
        ['style' => 'font-size:1rem;color:#6c757d;text-decoration:none;display:inline-block;margin-bottom:0.5rem;']
    );
} else {
    echo html_writer::link(
        new moodle_url('/course/view.php', ['id' => $courseid]),
        '&#8592; ' . get_string('backtocourse', 'format_iccaformat'),
        ['style' => 'font-size:1rem;color:#6c757d;text-decoration:none;display:inline-block;margin-bottom:0.5rem;']
    );
}
echo $OUTPUT->heading($pagetitle, 2);
echo '<p style="font-size:1rem;color:#6c757d;margin-bottom:1.25rem;">'
    . get_string($scopeid === 0 ? 'labelrules_site_desc' : 'labelrules_course_desc', 'format_iccaformat')
    . '</p>';

// Action bar.
if ($scopeid === 0) {
    $seedurl   = new moodle_url($selfurl, ['action' => 'seedefaults', 'sesskey' => sesskey()]);
    $seedlabel = get_string('labelrules_load_defaults', 'format_iccaformat');
    echo '<div style="display:flex;align-items:center;gap:8px;flex-wrap:wrap;margin-bottom:1.5rem;">';
    echo '<button type="button" class="btn btn-secondary" onclick="iccaShowLoadDefaultsDialog()">'
        . $seedlabel . '</button>';
    echo '<div style="width:1px;height:28px;background:#dee2e6;margin:0 4px;"></div>';
    echo html_writer::link(
        new moodle_url('/course/format/iccaformat/imagelibrary.php'),
        get_string('formatlibrary_site', 'format_iccaformat'),
        ['class' => 'btn btn-secondary']
    );
    echo '<span style="font-size:0.85rem;color:#6c757d;margin-left:4px;">Manage Card Image Library (Site wide).</span>';
    echo '</div>';

    // Site-wide reset dialog — with checkboxes per modname.
    if (!empty($rules)) {
        $pushallurl = new moodle_url($selfurl, ['action' => 'pushallcourses', 'sesskey' => sesskey()]);
        echo '<div id="icca-site-rule-reset-dialog" style="display:none;position:fixed;inset:0;background:rgba(0,0,0,0.5);z-index:99999;align-items:center;justify-content:center;">';
        echo '<div style="background:#fff;border-radius:10px;padding:1.5rem;width:90%;max-width:480px;box-shadow:0 8px 32px rgba(0,0,0,0.2);">';
        echo '<h4 style="margin:0 0 0.5rem;font-size:1.1rem;">' . get_string('labelrules_reset_allcourses_btn', 'format_iccaformat') . '</h4>';
        echo '<p style="font-size:0.9rem;color:#6c757d;margin:0 0 1rem;">'
            . get_string('labelrules_reset_allcourses_desc', 'format_iccaformat') . '</p>';
        echo '<form method="post" action="' . new moodle_url($selfurl, ['action' => 'pushselectedcourses', 'sesskey' => sesskey()]) . '">';
        echo '<input type="hidden" name="sesskey" value="' . sesskey() . '">';
        echo '<label style="display:flex;align-items:center;gap:8px;font-size:1rem;margin-bottom:10px;cursor:pointer;font-weight:500;">';
        echo '<input type="checkbox" id="icca-site-reset-all" onchange="iccaSiteResetSelectAll(this)" style="accent-color:#0d3c6f;width:16px;height:16px;"> Select all</label>';
        echo '<div style="border-top:0.5px solid #dee2e6;padding-top:10px;display:flex;flex-direction:column;gap:6px;margin-bottom:1rem;">';
        foreach ($rules as $rule) {
            $moddisplay = $modnamesmap[$rule->modname] ?? $rule->modname;
            $label = $rule->label ? ' <span style="color:#adb5bd;font-size:0.85rem;">— ' . s($rule->label) . '</span>' : '';
            echo '<label style="display:flex;align-items:center;gap:8px;font-size:1rem;cursor:pointer;">';
            echo '<input type="checkbox" name="modnames[]" value="' . s($rule->modname) . '" class="icca-site-reset-chk" onchange="iccaSiteResetUpdateAll()" style="accent-color:#0d3c6f;width:16px;height:16px;">';
            echo s($moddisplay) . $label . '</label>';
        }
        echo '</div>';
        echo '<div style="display:flex;gap:8px;justify-content:flex-end;">';
        echo '<button type="button" onclick="iccaCloseSiteRuleResetDialog()" style="padding:8px 16px;background:transparent;border:1px solid #dee2e6;border-radius:6px;font-size:1rem;cursor:pointer;">Cancel</button>';
        echo '<button type="submit" style="padding:8px 16px;background:#dc3545;color:#fff;border:none;border-radius:6px;font-size:1rem;cursor:pointer;font-weight:500;">Reset selected</button>';
        echo '</div>';
        echo '</form></div></div>';
    }

    // Load defaults dialog.
    echo '<div id="icca-load-defaults-dialog" style="display:none;position:fixed;inset:0;background:rgba(0,0,0,0.5);z-index:99999;align-items:center;justify-content:center;">';
    echo '<div style="background:#fff;border-radius:10px;padding:1.5rem;width:90%;max-width:440px;box-shadow:0 8px 32px rgba(0,0,0,0.2);">';
    echo '<h4 style="margin:0 0 0.5rem;font-size:1.1rem;">' . get_string('labelrules_load_defaults', 'format_iccaformat') . '</h4>';
    echo '<p style="font-size:0.9rem;color:#6c757d;margin:0 0 1rem;">' . get_string('labelrules_load_defaults_desc', 'format_iccaformat') . '</p>';
    echo '<div style="display:flex;gap:8px;justify-content:flex-end;">';
    echo '<button type="button" onclick="iccaCloseLoadDefaultsDialog()" style="padding:8px 16px;background:transparent;border:1px solid #dee2e6;border-radius:6px;font-size:1rem;cursor:pointer;">Cancel</button>';
    echo html_writer::link($seedurl, get_string('labelrules_load_defaults', 'format_iccaformat'),
        ['class' => 'btn btn-secondary', 'style' => 'padding:8px 16px;']);
    echo '</div>';
    echo '</div></div>';
} else {
    // Course scope — link to course image library + reset dialog button.
    echo '<div style="display:flex;align-items:center;gap:8px;flex-wrap:wrap;margin-bottom:1.5rem;">';
    echo html_writer::link(
        new moodle_url('/course/format/iccaformat/imagelibrary.php', ['courseid' => $courseid]),
        get_string('formatlibrary_course', 'format_iccaformat'),
        ['class' => 'btn btn-secondary']
    );
    echo '<span style="font-size:0.85rem;color:#6c757d;margin-left:4px;">Manage Card Image Library (This course).</span>';
    echo '</div>';

    // Reset dialog — only shown if course has rules.
    if (!empty($rules)) {
        $reseturl = new moodle_url($selfurl, ['action' => 'resetselected', 'sesskey' => sesskey()]);
        echo '<div id="icca-rule-reset-dialog" style="display:none;position:fixed;inset:0;background:rgba(0,0,0,0.5);z-index:99999;align-items:center;justify-content:center;">';
        echo '<div style="background:#fff;border-radius:10px;padding:1.5rem;width:90%;max-width:460px;box-shadow:0 8px 32px rgba(0,0,0,0.2);">';
        echo '<h4 style="margin:0 0 0.5rem;font-size:1.1rem;">' . get_string('labelrules_reset_course_btn', 'format_iccaformat') . '</h4>';
        echo '<p style="font-size:0.9rem;color:#6c757d;margin:0 0 1rem;">'
            . get_string('labelrules_reset_course_dialog_desc', 'format_iccaformat') . '</p>';
        echo '<form method="post" action="' . $reseturl . '">';
        echo '<input type="hidden" name="sesskey" value="' . sesskey() . '">';
        echo '<label style="display:flex;align-items:center;gap:8px;font-size:1rem;margin-bottom:10px;cursor:pointer;font-weight:500;">';
        echo '<input type="checkbox" id="icca-rule-reset-all" onchange="iccaRuleResetSelectAll(this)" style="accent-color:#0d3c6f;width:16px;height:16px;"> Select all</label>';
        echo '<div style="border-top:0.5px solid #dee2e6;padding-top:10px;display:flex;flex-direction:column;gap:6px;margin-bottom:1rem;">';
        foreach ($rules as $rule) {
            $moddisplay = $modnamesmap[$rule->modname] ?? $rule->modname;
            $label = $rule->label ? ' <span style="color:#adb5bd;font-size:0.85rem;">— ' . s($rule->label) . '</span>' : '';
            echo '<label style="display:flex;align-items:center;gap:8px;font-size:1rem;cursor:pointer;">';
            echo '<input type="checkbox" name="ruleids[]" value="' . (int)$rule->id . '" class="icca-rule-reset-chk" onchange="iccaRuleResetUpdateAll()" style="accent-color:#0d3c6f;width:16px;height:16px;">';
            echo s($moddisplay) . $label . '</label>';
        }
        echo '</div>';
        echo '<div style="display:flex;gap:8px;justify-content:flex-end;">';
        echo '<button type="button" onclick="iccaCloseRuleResetDialog()" style="padding:8px 16px;background:transparent;border:1px solid #dee2e6;border-radius:6px;font-size:1rem;cursor:pointer;">Cancel</button>';
        echo '<button type="submit" style="padding:8px 16px;background:#dc3545;color:#fff;border:none;border-radius:6px;font-size:1rem;cursor:pointer;font-weight:500;">Reset selected</button>';
        echo '</div>';
        echo '</form></div></div>';
    }
}
?>
<style>
.icca-lr-wrap { display:grid; grid-template-columns:480px 1fr; gap:1.5rem; align-items:start; }
.icca-lr-card { background:#fff; border-radius:8px; border:1px solid #dee2e6; overflow:visible; margin-bottom:1rem; }
.icca-lr-card:last-child { margin-bottom:0; }
.icca-lr-card-head { padding:10px 14px; background:#f8f9fa; border-bottom:1px solid #dee2e6; border-radius:8px 8px 0 0; display:flex; align-items:center; gap:8px; }
.icca-lr-card-head h3 { font-size:1rem; font-weight:600; color:#495057; margin:0; flex:1; }
.icca-lr-card-head .badge { font-size:0.75rem; background:#0d3c6f; color:#fff; border-radius:10px; padding:4px 10px; }
.icca-lr-card-body { padding:14px; }
.icca-lr-form-group { margin-bottom:12px; }
.icca-lr-form-group label { display:block; font-size:1rem; color:#6c757d; margin-bottom:4px; }
.icca-lr-form-group select,
.icca-lr-form-group input[type=text] { width:100%; border:1px solid #dee2e6; border-radius:6px; padding:7px 10px; font-size:1rem; }
/* Rules table */
.icca-lr-table { width:100%; border-collapse:collapse; font-size:1rem; }
.icca-lr-table th { background:#f8f9fa; padding:10px 12px; text-align:left; font-weight:600; font-size:1rem; text-transform:uppercase; letter-spacing:0.05em; color:#6c757d; border-bottom:2px solid #dee2e6; white-space:nowrap; }
.icca-lr-table td { padding:10px 12px; border-bottom:1px solid #f0f0f0; vertical-align:middle; }
.icca-lr-table tbody tr:last-child td { border-bottom:none; }
.icca-lr-table tbody tr:hover td { background:#f8f9fa; }
.icca-mod-badge { display:inline-block; background:#e9ecef; color:#495057; border-radius:4px; padding:2px 8px; font-size:1rem; font-family:monospace; }
.icca-label-pill { display:inline-flex; align-items:center; gap:4px; background:#e8f0fb; color:#0d3c6f; border-radius:10px; padding:3px 10px; font-size:1rem; }
.icca-label-off { opacity:0.4; text-decoration:line-through; }
.icca-action-links { display:flex; gap:8px; white-space:nowrap; flex-wrap:wrap; }
.icca-vis-tick { color:#1a6b3a; }
.icca-vis-cross { color:#adb5bd; }
</style>
<?php

$editrule   = $ruleid ? $DB->get_record('iccaformat_label_rules', ['id' => $ruleid]) : null;
$iconval    = $editrule ? s($editrule->icon) : '';
$labelval   = $editrule ? s($editrule->label) : '';
$iconchecked        = (!$editrule || $editrule->icon_enabled)  ? 'checked' : '';
$labelchecked       = (!$editrule || !isset($editrule->label_enabled) || $editrule->label_enabled) ? 'checked' : '';
$flipchecked        = ($editrule && !empty($editrule->flipped)) ? 'checked' : '';
$imagehiddenchecked = ($editrule && !empty($editrule->image_hidden)) ? 'checked' : '';
$currentimgid = $editrule ? (int)($editrule->libraryimageid ?? 0) : 0;
$formurl = new moodle_url($selfurl, ['action' => 'save', 'sesskey' => sesskey()]);

$libimages = $DB->get_records_sql(
    'SELECT il.* FROM {iccaformat_image_library} il
      JOIN {context} ctx ON ctx.id = il.contextid
     WHERE ctx.contextlevel = ' . CONTEXT_SYSTEM . '
        OR (ctx.contextlevel = ' . CONTEXT_COURSE . ' AND ctx.instanceid = :courseid)
     ORDER BY il.name',
    ['courseid' => $course ? $course->id : 0]
);

$allmodssorted = $allmodnames;
sort($allmodssorted);

echo '<div class="icca-lr-wrap">';

// ── LEFT PANEL ─────────────────────────────────────────────────────────────
echo '<div>';

// Skip modules card (site admin only).
$skipcount = count($skipmodtypes);
$skipurl2  = new moodle_url($selfurl, ['action' => 'saveskip', 'sesskey' => sesskey()]);

echo '<div class="icca-lr-card" id="icca-edit-card">';
echo '<div class="icca-lr-card-head">';
echo '<h3>' . ($editrule ? get_string('labelrules_edit', 'format_iccaformat') : get_string('labelrules_add', 'format_iccaformat')) . '</h3>';
echo '</div>';
echo '<div class="icca-lr-card-body">';
echo '<form method="post" enctype="multipart/form-data" action="' . $formurl . '">';
echo '<input type="hidden" name="sesskey" value="' . sesskey() . '">';
if ($editrule) {
    echo '<input type="hidden" name="modname" value="' . s($editrule->modname) . '">';
}

if (!$editrule && empty($availmodtypes)) {
    echo '<p class="text-muted" style="font-size:1rem;">' . get_string('labelrules_allcovered', 'format_iccaformat') . '</p>';
} else {
    // Mod type.
    echo '<div class="icca-lr-form-group">';
    echo '<label>' . get_string('labelrules_modtype', 'format_iccaformat') . '</label>';
    if ($editrule) {
        echo '<div style="padding:7px 0;font-weight:500;font-family:monospace;">' . s($editrule->modname) . '</div>';
    } else {
        // If overridemod is set, also add it to available list if not already there.
        $selectmodtypes = $availmodtypes;
        if ($overridemod && !in_array($overridemod, $selectmodtypes, true)) {
            $selectmodtypes[] = $overridemod;
            sort($selectmodtypes);
        }
        echo '<select name="modname" id="lr_modname" style="width:100%;border:1px solid #dee2e6;border-radius:6px;padding:7px 10px;font-size:1rem;">';
        echo '<option value="">— ' . get_string('choosedots') . ' —</option>';
        foreach ($selectmodtypes as $mname) {
            $fn  = !empty($modnamesmap[$mname]) ? $modnamesmap[$mname] : $mname;
            $lbl = $fn !== $mname ? $fn . ' (' . $mname . ')' : $mname;
            $sel = ($overridemod === $mname) ? ' selected' : '';
            echo '<option value="' . s($mname) . '"' . $sel . '>' . s($lbl) . '</option>';
        }
        echo '</select>';
    }
    echo '</div>';

    // Pre-fill label from override modname if set.
    if ($overridemod) {
        // Find the site rule for this mod to pre-fill ALL values from the site rule.
        $siterule = $siterules[$overridemod] ?? null;
        $prelabel = $siterule ? s($siterule->label) : s($modnamesmap[$overridemod] ?? $overridemod);
        if ($siterule) {
            // Pre-fill icon, visibility, and image from site rule if not already editing a course rule.
            if (!$editrule) {
                $iconval       = s($siterule->icon ?? '');
                $iconchecked   = !empty($siterule->icon_enabled)  ? 'checked' : '';
                $labelchecked  = !empty($siterule->label_enabled) ? 'checked' : '';
                $flipchecked         = !empty($siterule->flipped) ? 'checked' : '';
                $imagehiddenchecked  = !empty($siterule->image_hidden) ? 'checked' : '';
                $currentimgid  = (int)($siterule->libraryimageid ?? 0);
            }
        }
    }

    // Label.
    $displaylabel = $labelval ?: ($overridemod ? ($prelabel ?? '') : '');
    echo '<div class="icca-lr-form-group">';
    echo '<label for="lr_label">' . get_string('labelrules_label', 'format_iccaformat') . '</label>';
    echo '<input type="text" id="lr_label" name="label" value="' . $displaylabel . '"'
        . ' placeholder="' . get_string('labelrules_label_placeholder', 'format_iccaformat') . '"'
        . ' maxlength="100">';
    echo '</div>';

    // Icon — numbering dropdown for section, FA picker for everything else.
    $issectionrule = ($editrule && $editrule->modname === 'section') || ($overridemod === 'section');
    echo '<div class="icca-lr-form-group">';
    echo '<label>' . get_string('labelrules_icon', 'format_iccaformat') . '</label>';
    echo '<input type="hidden" id="lr_icon" name="icon" value="' . $iconval . '">';
    if ($issectionrule) {
        // Section gets numbering style dropdown.
        $numtypes = [
            'none'    => get_string('sectionicontype_none',    'format_iccaformat'),
            'numeric' => get_string('sectionicontype_numeric', 'format_iccaformat'),
            'alpha'   => get_string('sectionicontype_alpha',   'format_iccaformat'),
            'ALPHA'   => get_string('sectionicontype_ALPHA',   'format_iccaformat'),
            'roman'   => get_string('sectionicontype_roman',   'format_iccaformat'),
            'icon'    => get_string('sectionicontype_icon',    'format_iccaformat'),
        ];
        $specialvals = ['none','numeric','alpha','ALPHA','roman','icon'];
        $curtype = in_array($iconval, $specialvals) ? $iconval : ($iconval ? 'icon' : 'numeric');
        echo '<select id="lr_section_icontype" onchange="iccaSectionIconTypeChange(this.value)" style="width:100%;border:1px solid #dee2e6;border-radius:6px;padding:7px 10px;font-size:1rem;margin-bottom:8px;">';
        foreach ($numtypes as $val => $label) {
            $sel = ($curtype === $val) ? ' selected' : '';
            echo '<option value="' . s($val) . '"' . $sel . '>' . s($label) . '</option>';
        }
        echo '</select>';
        // Custom icon picker — shown only when "Custom icon" selected.
        $customiconval = (!in_array($iconval, ['none','numeric','alpha','ALPHA','roman','']) ? $iconval : '');
        echo '<div id="lr_section_custompicker" style="display:' . ($curtype === 'icon' ? 'block' : 'none') . ';">';
        echo '<div style="position:relative;">';
        echo '<div style="display:flex;align-items:center;gap:6px;">';
        echo '<span id="lr_icon_preview" style="width:34px;height:34px;border:1px solid #dee2e6;border-radius:6px;display:flex;align-items:center;justify-content:center;flex-shrink:0;color:#6c757d;background:#f8f9fa;">';
        echo '<i id="lr_icon_preview_i" class="' . ($customiconval ? 'fa ' . $customiconval : '') . '" aria-hidden="true"></i>';
        echo '</span>';
        echo '<input type="text" id="lr_icon_search" value="' . s($customiconval) . '" placeholder="Search icons..." autocomplete="off" style="font-family:monospace;font-size:1rem;flex:1;border:1px solid #dee2e6;border-radius:6px;padding:7px 10px;">';
        echo '</div>';
        echo '<div id="lr_icon_dropdown" style="display:none;position:absolute;top:100%;left:42px;right:0;background:#fff;border:1px solid #dee2e6;border-radius:4px;box-shadow:0 4px 12px rgba(0,0,0,0.1);max-height:200px;overflow-y:auto;z-index:1000;margin-top:2px;"></div>';
        echo '</div></div>';
    } else {
        // Normal FA icon picker.
        echo '<div style="position:relative;">';
        echo '<div style="display:flex;align-items:center;gap:6px;">';
        echo '<span id="lr_icon_preview" style="width:34px;height:34px;border:1px solid #dee2e6;border-radius:6px;display:flex;align-items:center;justify-content:center;flex-shrink:0;color:#6c757d;background:#f8f9fa;">';
        echo '<i id="lr_icon_preview_i" class="' . ($iconval ?: '') . '" aria-hidden="true"></i>';
        echo '</span>';
        echo '<input type="text" id="lr_icon_search" value="' . $iconval . '" placeholder="Search icons..." autocomplete="off" style="font-family:monospace;font-size:1rem;flex:1;border:1px solid #dee2e6;border-radius:6px;padding:7px 10px;">';
        echo '</div>';
        echo '<div id="lr_icon_dropdown" style="display:none;position:absolute;top:100%;left:42px;right:0;background:#fff;border:1px solid #dee2e6;border-radius:4px;box-shadow:0 4px 12px rgba(0,0,0,0.1);max-height:200px;overflow-y:auto;z-index:1000;margin-top:2px;"></div>';
        echo '</div>';
        echo '<p style="font-size:11px;color:#adb5bd;margin:3px 0 0;">' . get_string('labelrules_icon_help_short', 'format_iccaformat') . '</p>';
    }
    echo '</div>';

    // Default image.
    echo '<div class="icca-lr-form-group">';
    echo '<label>' . get_string('labelrules_defaultimage', 'format_iccaformat') . '</label>';
    echo '<input type="hidden" id="lr_imageid" name="libraryimageid" value="' . $currentimgid . '">';

    // Image hidden overlay on preview when checked.
    $hiddenpreviewstyle = $imagehiddenchecked ? 'position:relative;' : 'position:relative;';
    echo '<div style="display:flex;align-items:center;gap:8px;">';
    echo '<div id="lr_img_preview_wrap" style="position:relative;width:56px;height:38px;border-radius:4px;overflow:hidden;flex-shrink:0;">';
    echo '<div id="lr_img_preview" style="width:56px;height:38px;border:1px solid #dee2e6;border-radius:4px;overflow:hidden;background:#f8f9fa;display:flex;align-items:center;justify-content:center;">';
    if ($currentimgid) {
        $previewurl = \format_iccaformat\output\courseformat\content::get_library_image_url_static($currentimgid);
        echo '<img src="' . $previewurl . '" style="width:100%;height:100%;object-fit:cover;" alt="">';
    } else {
        echo '<span style="font-size:16px;color:#dee2e6;">&#8960;</span>';
    }
    echo '</div>';
    // Hidden overlay.
    echo '<div id="lr_img_hidden_overlay" style="display:' . ($imagehiddenchecked ? 'flex' : 'none') . ';position:absolute;inset:0;background:rgba(0,0,0,0.55);align-items:center;justify-content:center;border-radius:4px;">'
        . '<svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="#fff" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">'
        . '<path d="M17.94 17.94A10.07 10.07 0 0112 20c-7 0-11-8-11-8a18.45 18.45 0 015.06-5.94M9.9 4.24A9.12 9.12 0 0112 4c7 0 11 8 11 8a18.5 18.5 0 01-2.16 3.19m-6.72-1.07a3 3 0 11-4.24-4.24"/>'
        . '<line x1="1" y1="1" x2="23" y2="23"/></svg>'
        . '</div>';
    echo '</div>';
    echo '<div style="display:flex;flex-direction:column;gap:4px;">';
    echo '<button type="button" class="btn btn-secondary btn-sm" onclick="iccaLrPickImg()" style="font-size:1rem;padding:3px 10px;">' . get_string('labelrules_chooseimage', 'format_iccaformat') . '</button>';
    echo '<button type="button" class="btn btn-secondary btn-sm" onclick="iccaLrShowUpload()" style="font-size:1rem;padding:3px 10px;">&#8593; Upload image</button>';
    echo '<button type="button" class="btn btn-sm" onclick="iccaLrClearImg()" style="font-size:1rem;padding:3px 10px;color:#adb5bd;border:1px solid #dee2e6;">' . get_string('labelrules_clearimage', 'format_iccaformat') . '</button>';
    echo '</div></div>';
    // Hide image checkbox.
    echo '<label style="display:flex;align-items:center;gap:7px;font-size:0.95rem;margin-top:8px;cursor:pointer;">'
        . '<input type="checkbox" name="image_hidden" value="1" id="lr_image_hidden" ' . $imagehiddenchecked
        . ' onchange="document.getElementById(\'lr_img_hidden_overlay\').style.display=this.checked?\'flex\':\'none\'"'
        . ' style="accent-color:#0d3c6f;width:15px;height:15px;"> '
        . get_string('labelrules_image_hidden', 'format_iccaformat') . '</label>';
    echo '<p style="font-size:0.85rem;color:#adb5bd;margin:2px 0 0;">' . get_string('labelrules_image_hidden_desc', 'format_iccaformat') . '</p>';

    // Upload panel.
    echo '<div id="lr_upload_panel" style="display:none;margin-top:6px;border:1px solid #dee2e6;border-radius:6px;padding:10px;background:#f8f9fa;">';
    $uploadlibhint = $scopeid === 0
        ? 'Image will be added to the All courses image library.'
        : 'Image will be added to this course\'s image library.';
    echo '<p style="font-size:0.85rem;color:#6c757d;margin:0 0 8px;">' . $uploadlibhint . '</p>';
    echo '<label style="font-size:0.85rem;font-weight:500;display:block;margin-bottom:3px;">Image name</label>';
    echo '<input type="text" name="rule_imagename" id="lr_upload_name" autocomplete="off"'
        . ' style="width:100%;border:1px solid #dee2e6;border-radius:4px;padding:5px 8px;font-size:1rem;margin-bottom:8px;">';
    echo '<label style="font-size:0.85rem;font-weight:500;display:block;margin-bottom:3px;">File</label>';
    echo '<input type="file" name="rule_imageupload" id="lr_upload_file" accept=".jpg,.jpeg,.png,.webp,.gif,.svg"'
        . ' onchange="iccaLrUploadFileChanged(this)"'
        . ' style="width:100%;font-size:0.9rem;">';
    echo '</div>';
    echo '<div id="lr_img_panel" style="display:none;margin-top:6px;border:1px solid #dee2e6;border-radius:6px;background:#fff;">' . 
        '<div style="padding:8px;border-bottom:1px solid #dee2e6;">' .
        '<input type="text" id="lr_img_search" placeholder="Search images..." oninput="iccaLrFilterImgs(this.value)"' .
        ' style="width:100%;border:1px solid #dee2e6;border-radius:4px;padding:5px 8px;font-size:1rem;"></div>' .
        '<div id="lr_img_grid" style="display:grid;grid-template-columns:repeat(auto-fill,minmax(90px,1fr));gap:8px;padding:8px;max-height:260px;overflow-y:auto;">';
    foreach ($libimages as $img) {
        $imgurl = \format_iccaformat\output\courseformat\content::get_library_image_url_static((int)$img->id);
        $issel  = ((int)$img->id === $currentimgid);
        $border = $issel ? '2.5px solid #0d6efd' : '1px solid #dee2e6';
        $jsurl  = addslashes($imgurl);
        echo '<div class="lr-img-tile" data-name="' . s(strtolower($img->name)) . '"'
            . ' onclick="iccaLrSelectImg(' . (int)$img->id . ', \'' . $jsurl . '\')"'
            . ' style="cursor:pointer;border-radius:6px;overflow:hidden;border:' . $border . ';background:#f8f9fa;">'
            . '<img src="' . htmlspecialchars($imgurl, ENT_QUOTES) . '" data-imgid="' . (int)$img->id . '"'
            . ' style="width:100%;height:60px;object-fit:cover;display:block;">'
            . '<div style="padding:3px 5px;font-size:0.75rem;color:#495057;white-space:nowrap;overflow:hidden;text-overflow:ellipsis;">'
            . s($img->name) . '</div></div>';
    }
    echo '</div></div></div>';

    // Visibility.
    echo '<div class="icca-lr-form-group">';
    echo '<label>' . get_string('labelrules_visibility', 'format_iccaformat') . '</label>';
    echo '<div style="display:flex;gap:14px;">';
    echo '<label style="display:flex;align-items:center;gap:5px;font-size:1rem;cursor:pointer;font-weight:normal;">';
    echo '<input type="checkbox" name="label_enabled" value="1" ' . $labelchecked . '> ' . get_string('labelrules_show_label', 'format_iccaformat');
    echo '</label>';
    echo '<label style="display:flex;align-items:center;gap:5px;font-size:1rem;cursor:pointer;font-weight:normal;">';
    echo '<input type="checkbox" name="icon_enabled" value="1" ' . $iconchecked . '> ' . get_string('labelrules_show_icon', 'format_iccaformat');
    echo '</label><label style="display:flex;align-items:center;gap:5px;font-size:1rem;cursor:pointer;font-weight:normal;">';
    echo '<input type="checkbox" name="flipped" value="1" ' . $flipchecked . '> ' . get_string('labelrules_flip', 'format_iccaformat');
    echo '</label>';
    echo '</div></div>';

    // Submit.
    echo '<button type="submit" class="btn btn-primary" style="width:100%;justify-content:center;">';
    echo $editrule ? get_string('savechanges') : get_string('add');
    echo '</button>';
}
echo '</form>';
echo '</div></div>'; // card-body, card

if ($scopeid === 0) {
    echo '<div class="icca-lr-card">';
    echo '<div class="icca-lr-card-head">';
        echo '<h3>' . get_string('skip_modtypes', 'format_iccaformat') . '</h3>';
    if ($skipcount) echo '<span class="badge">' . $skipcount . ' skipped</span>';
    echo '</div>';
    echo '<div class="icca-lr-card-body">';
    echo '<p style="font-size:1rem;color:#6c757d;margin-bottom:10px;">'
        . get_string('skip_modtypes_help', 'format_iccaformat') . '</p>';
    echo '<form method="post" action="' . $skipurl2 . '">';
    echo '<input type="hidden" name="sesskey" value="' . sesskey() . '">';
    echo '<div style="display:grid;grid-template-columns:1fr auto 1fr;gap:8px;align-items:center;">';
    // Available.
    echo '<div>';
    echo '<label style="display:block;font-size:1rem;font-weight:600;color:#6c757d;text-transform:uppercase;letter-spacing:0.05em;margin-bottom:4px;">Available</label>';
    echo '<select id="icca-skip-available" multiple size="10" style="width:100%;border:1px solid #dee2e6;border-radius:6px;padding:4px;font-size:1rem;">';
    foreach ($allmodssorted as $m) {
        if (!in_array($m, $skipmodtypes, true)) {
            $fn = !empty($modnamesmap[$m]) && $modnamesmap[$m] !== $m ? $modnamesmap[$m] . ' (' . $m . ')' : $m;
            echo '<option value="' . s($m) . '">' . s($fn) . '</option>';
        }
    }
    echo '</select></div>';
    // Arrows.
    echo '<div style="display:flex;flex-direction:column;gap:6px;">';
    echo '<button type="button" onclick="iccaMoveSkip(\'available\',\'skipped\')" style="padding:5px 8px;border:1px solid #dee2e6;border-radius:6px;background:#f8f9fa;cursor:pointer;font-size:1rem;" title="Skip">&rarr;</button>';
    echo '<button type="button" onclick="iccaMoveSkip(\'skipped\',\'available\')" style="padding:5px 8px;border:1px solid #dee2e6;border-radius:6px;background:#f8f9fa;cursor:pointer;font-size:1rem;" title="Unskip">&larr;</button>';
    echo '</div>';
    // Skipped.
    echo '<div>';
    echo '<label style="display:block;font-size:1rem;font-weight:600;color:#6c757d;text-transform:uppercase;letter-spacing:0.05em;margin-bottom:4px;">Skipped</label>';
    echo '<select id="icca-skip-skipped" name="skipped[]" multiple size="10" style="width:100%;border:1px solid #dee2e6;border-radius:6px;padding:4px;font-size:1rem;">';
    foreach ($allmodssorted as $m) {
        if (in_array($m, $skipmodtypes, true)) {
            $fn = !empty($modnamesmap[$m]) && $modnamesmap[$m] !== $m ? $modnamesmap[$m] . ' (' . $m . ')' : $m;
            echo '<option value="' . s($m) . '" selected>' . s($fn) . '</option>';
        }
    }
    echo '</select></div>';
    echo '</div>';
    echo '<button type="submit" onclick="iccaSelectAllSkipped()" class="btn btn-primary" style="margin-top:10px;width:100%;justify-content:center;">'
        . get_string('savechanges') . '</button>';
    echo '</form>';
    echo '</div></div>';
}

// Add / edit rule card.
echo '</div>'; // left panel

// ── RIGHT PANEL — rules table ───────────────────────────────────────────────
echo '<div>';
echo '<div class="icca-lr-card">';
// Bulk action bar (hidden until rows selected).
$bulkaction_url  = new moodle_url($selfurl, ['action' => 'bulkaction', 'sesskey' => sesskey()]);
$bulkedit_url    = new moodle_url($selfurl, ['action' => 'bulkedit',   'sesskey' => sesskey()]);
echo '<form id="icca-lr-bulk-form" method="post" action="' . $bulkaction_url . '">';
echo '<input type="hidden" name="sesskey" value="' . sesskey() . '">';
echo '<input type="hidden" name="scopeid" value="' . $scopeid . '">';

// Bulk action bar.
echo '<div id="icca-lr-bulk-bar" style="display:none;padding:8px 14px;background:#fff3cd;border:0.5px solid #ffe082;border-radius:8px;margin-bottom:8px;align-items:center;gap:10px;flex-wrap:wrap;">'
    . '<span id="icca-lr-bulk-count" style="font-size:1rem;color:#856404;font-weight:500;">0 selected</span>'
    . '<button type="button" class="btn btn-secondary btn-sm" onclick="iccaLrToggleBulkEdit()">'
        . get_string('bulkedit_btn', 'format_iccaformat') . '</button>'
    . ($scopeid === 0
        ? '<button type="submit" name="bulkop" value="resetallcourses" class="btn btn-secondary btn-sm" onclick="return iccaLrBulkConfirmReset()">'
            . get_string('labelrules_reset_courses_sitewide', 'format_iccaformat') . '</button>'
        : '<button type="submit" name="bulkop" value="resettosite" class="btn btn-secondary btn-sm" onclick="return iccaLrBulkConfirmReset()">'
            . get_string('labelrules_reset_course', 'format_iccaformat') . '</button>')
    . '<button type="submit" name="bulkop" value="delete" class="btn btn-danger btn-sm" onclick="return iccaLrBulkConfirmDelete()">'
        . get_string('delete') . '</button>'
    . '<button type="button" class="btn btn-sm" onclick="iccaLrClearSelection()" style="border:1px solid #dee2e6;background:transparent;color:#6c757d;">'
        . get_string('bulkedit_clear', 'format_iccaformat') . '</button>'
    . '</div>';

// Bulk edit panel (hidden until "Bulk edit" clicked).
echo '<div id="icca-lr-bulkedit-panel" style="display:none;margin:0 0 8px;background:#f0f4fa;border:0.5px solid #c8d8ef;border-radius:8px;padding:14px;">';
echo '<p style="font-size:1rem;font-weight:500;margin:0 0 12px;">' . get_string('bulkedit_title', 'format_iccaformat') . '</p>';

// Radio grid.
$be_fields = [
    'be_icons'  => get_string('carddefault_icons',  'format_iccaformat'),
    'be_labels' => get_string('carddefault_labels', 'format_iccaformat'),
    'be_images' => get_string('carddefault_images', 'format_iccaformat'),
    'be_flip'   => get_string('carddefault_flip',   'format_iccaformat'),
];
echo '<table style="border-collapse:collapse;">';
echo '<thead><tr>'
    . '<th style="text-align:left;font-size:0.85rem;font-weight:500;color:#6c757d;padding:4px 20px 4px 0;min-width:140px;"></th>'
    . '<th style="text-align:center;font-size:0.85rem;font-weight:500;color:#6c757d;padding:4px 16px;">Enable</th>'
    . '<th style="text-align:center;font-size:0.85rem;font-weight:500;color:#6c757d;padding:4px 16px;">Disable</th>'
    . '<th style="text-align:center;font-size:0.85rem;font-weight:500;color:#6c757d;padding:4px 16px;">Remove</th>'
    . '<th style="text-align:center;font-size:0.85rem;font-weight:500;color:#6c757d;padding:4px 16px;">No change</th>'
    . '</tr></thead><tbody>';

foreach ($be_fields as $fname => $flabel) {
    $isimages = ($fname === 'be_images');
    echo '<tr style="border-top:0.5px solid #dee2e6;">'
        . '<td style="padding:8px 20px 8px 0;font-size:1rem;color:#495057;">' . $flabel . '</td>'
        . '<td style="text-align:center;padding:8px 16px;"><input type="radio" name="' . $fname . '" value="enable" style="accent-color:#0d3c6f;width:15px;height:15px;"></td>'
        . '<td style="text-align:center;padding:8px 16px;"><input type="radio" name="' . $fname . '" value="disable" style="accent-color:#0d3c6f;width:15px;height:15px;"></td>'
        . '<td style="text-align:center;padding:8px 16px;">'
            . ($isimages
                ? '<input type="radio" name="' . $fname . '" value="remove" style="accent-color:#0d3c6f;width:15px;height:15px;">'
                : '<span style="color:#dee2e6;font-size:11px;">—</span>')
        . '</td>'
        . '<td style="text-align:center;padding:8px 16px;"><input type="radio" name="' . $fname . '" value="nochange" checked style="accent-color:#0d3c6f;width:15px;height:15px;"></td>'
        . '</tr>';
}
echo '</tbody></table>';
echo '<p style="font-size:0.85rem;color:#6c757d;margin:8px 0 12px;">'
    . get_string('bulkedit_hint', 'format_iccaformat')
    . ' ' . get_string('bulkedit_images_hint', 'format_iccaformat') . '</p>';
echo '<div style="display:flex;gap:8px;">'
    . '<button type="button" id="icca-lr-bulkedit-apply" class="btn btn-primary btn-sm" onclick="iccaLrApplyBulkEdit(\'' . $bulkedit_url . '\')">'
        . get_string('bulkedit_apply', 'format_iccaformat') . '</button>'
    . '<button type="button" class="btn btn-sm" onclick="iccaLrToggleBulkEdit()" style="border:1px solid #dee2e6;background:transparent;color:#6c757d;">'
        . get_string('cancel') . '</button>'
    . '</div>';
echo '</div>';
echo '</form>';
echo '<div class="icca-lr-card-head">';
$courserulecount = count($rules); // course-level overrides only
$totalruleable   = count($allmodnames); // all possible mods
if ($scopeid !== 0) {
    // Course scope: show inherited + overrides in the pill.
    $totalvisible = $courserulecount + count($siterules);
    echo '<h3 style="flex:1;">' . get_string('labelrules_course', 'format_iccaformat') . '</h3>';
    echo '<span class="badge" style="background:#0d3c6f;">'
        . $courserulecount . ' override' . ($courserulecount !== 1 ? 's' : '') . ', '
        . count($siterules) . ' inherited</span>';
} else {
    $rulecount = $courserulecount;
    echo '<h3 style="flex:1;">' . get_string('labelrules_site', 'format_iccaformat') . '</h3>';
    if (count($unruledmods) > 0) {
        echo '<span class="badge" style="background:#0d3c6f;" title="' . implode(', ', $unruledmods) . ' have no rule yet">'
            . $rulecount . ' / ' . ($rulecount + count($unruledmods)) . ' rules</span>';
    } else {
        echo '<span class="badge">' . $rulecount . ' rules</span>';
    }
}
echo '<input type="text" id="icca-lr-search" placeholder="Search..." oninput="iccaLrSearch(this.value, false)"'
    . ' style="border:1px solid #dee2e6;border-radius:6px;padding:4px 10px;font-size:1rem;width:140px;margin-left:8px;">';
if ($scopeid !== 0) {
    echo '<label style="display:inline-flex;align-items:center;gap:5px;font-size:0.9rem;color:#6c757d;margin-left:10px;cursor:pointer;white-space:nowrap;">'
        . '<input type="checkbox" id="icca-hide-overridden" onchange="iccaLrSearch(document.getElementById(\'icca-lr-search\').value, false)" style="accent-color:#0d3c6f;">'
        . ' Hide overridden</label>';
}
echo '</div>';

// Rules table.
if (!empty($rules)) {
    echo '<table class="icca-lr-table">';
    echo '<thead><tr>';
    echo '<th style="width:32px;"><input type="checkbox" id="icca-lr-select-all" onchange="iccaLrSelectAll(this)" style="accent-color:#0d3c6f;width:14px;height:14px;"></th>';
    echo '<th>' . get_string('labelrules_modtype', 'format_iccaformat') . '</th>';
    echo '<th>' . get_string('labelrules_label', 'format_iccaformat') . '</th>';
    echo '<th>' . get_string('labelrules_icon', 'format_iccaformat') . '</th>';
    echo '<th>' . get_string('labelrules_defaultimage', 'format_iccaformat') . '</th>';
    echo '<th>' . get_string('labelrules_visibility', 'format_iccaformat') . '</th>';
    echo '<th></th>';
    echo '</tr></thead><tbody>';
    foreach ($rules as $rule) {
        $labelon    = (!isset($rule->label_enabled) || $rule->label_enabled);
        $iconon     = !empty($rule->icon_enabled);
        $flippedon  = !empty($rule->flipped);
        $imghidden  = !empty($rule->image_hidden);
        echo '<tr class="icca-lr-row" data-modname="' . s($rule->modname) . '" data-label="' . s(strtolower($rule->label ?? '')) . '">';
        echo '<td style="width:32px;"><input type="checkbox" name="ruleids[]" value="' . (int)$rule->id . '" class="icca-lr-chk" onchange="iccaLrUpdateBulkBar()" style="accent-color:#0d3c6f;width:14px;height:14px;"></td>';
        echo '<td><span class="icca-mod-badge">' . s($rule->modname) . '</span></td>';
        echo '<td>';
        if ($rule->label) {
            $offclass = $labelon ? '' : ' icca-label-off';
            echo '<span class="icca-label-pill' . $offclass . '">' . s($rule->label) . '</span>';
        } else {
            echo '<span style="color:#adb5bd;font-size:1rem;">—</span>';
        }
        echo '</td>';
        echo '<td>';
        if ($rule->icon) {
            $offstyle = $iconon ? '' : 'opacity:0.4;';
            $specialicons = ['none','numeric','alpha','ALPHA','roman'];
            if (in_array($rule->icon, $specialicons) && $rule->modname === 'section') {
                // Show numbering style label instead of FA icon.
                $labels = ['none'=>'None','numeric'=>'1, 2, 3...','alpha'=>'a, b, c...','ALPHA'=>'A, B, C...','roman'=>'i, ii, iii...'];
                echo '<span style="display:inline-flex;align-items:center;gap:5px;font-size:1rem;' . $offstyle . '">';
                echo '<code style="font-size:1rem;color:#6c757d;">' . ($labels[$rule->icon] ?? $rule->icon) . '</code>';
                echo '</span>';
            } else {
                echo '<span style="display:inline-flex;align-items:center;gap:5px;font-size:1rem;' . $offstyle . '">';
                echo '<i class="fa ' . s($rule->icon) . '" aria-hidden="true"></i>';
                echo '<code style="font-size:1rem;color:#6c757d;">' . s($rule->icon) . '</code>';
                echo '</span>';
            }
        } else {
            echo '<span style="color:#adb5bd;font-size:1rem;">—</span>';
        }
        echo '</td>';
        echo '<td>';
        if (!empty($rule->libraryimageid)) {
            $imgurl = \format_iccaformat\output\courseformat\content::get_library_image_url_static((int)$rule->libraryimageid);
            echo '<div style="position:relative;display:inline-block;width:120px;height:80px;border-radius:4px;overflow:hidden;border:1px solid #dee2e6;">';
            echo '<img src="' . htmlspecialchars($imgurl, ENT_QUOTES) . '" style="width:120px;height:80px;object-fit:cover;display:block;" alt="">';
            if (!empty($rule->image_hidden)) {
                echo '<div style="position:absolute;inset:0;background:rgba(200,200,200,0.65);display:flex;align-items:center;justify-content:center;">'
                    . '<svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="#555" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">'
                    . '<path d="M17.94 17.94A10.07 10.07 0 0112 20c-7 0-11-8-11-8a18.45 18.45 0 015.06-5.94M9.9 4.24A9.12 9.12 0 0112 4c7 0 11 8 11 8a18.5 18.5 0 01-2.16 3.19m-6.72-1.07a3 3 0 11-4.24-4.24"/>'
                    . '<line x1="1" y1="1" x2="23" y2="23"/></svg>'
                    . '</div>';
            }
            echo '</div>';
        } else {
            echo '<span style="color:#adb5bd;font-size:1rem;">None</span>';
        }
        echo '</td>';
        echo '<td>';
        echo '<div style="display:flex;gap:8px;">';
        echo '<span style="font-size:1rem;" class="' . ($labelon ? 'icca-vis-tick' : 'icca-vis-cross') . '">'
            . ($labelon ? '✓' : '✗') . ' Label</span>';
        echo '<span style="font-size:1rem;" class="' . ($iconon ? 'icca-vis-tick' : 'icca-vis-cross') . '">'
            . ($iconon ? '✓' : '✗') . ' Icon</span>';
        if ($flippedon) {
            echo '<span style="font-size:0.8rem;background:#e8f0fb;color:#0d3c6f;border-radius:4px;padding:1px 7px;font-weight:500;" title="Icon and label order is flipped">⇄ Flipped</span>';
        }
        if ($imghidden) {
            // Image hidden state shown on thumbnail — no pill needed here.
        }
        echo '</div>';
        echo '</td>';
        echo '<td>';
        $editurl   = new moodle_url($selfurl, ['ruleid' => $rule->id]);
        $deleteurl = new moodle_url($selfurl, ['action' => 'delete', 'ruleid' => $rule->id, 'sesskey' => sesskey()]);
        echo '<div class="icca-action-links">';
        echo html_writer::link($editurl, get_string('edit'), ['class' => 'btn btn-secondary btn-sm', 'style' => 'display:inline-block;']);
        if ($scopeid === 0) {
            // Site-wide: Reset all courses for this modname + Delete.
            $resetallurl = new moodle_url($selfurl, ['action' => 'pushtocourses', 'modname' => $rule->modname, 'sesskey' => sesskey()]);
            echo html_writer::link($resetallurl, get_string('labelrules_reset_courses_sitewide', 'format_iccaformat'), [
                'class'   => 'btn btn-warning btn-sm',
                'style'   => 'display:inline-block;',
                'onclick' => "return confirm('Reset all courses to the site-wide rule for this module type?');",
            ]);
            echo html_writer::link($deleteurl, get_string('delete'), [
                'class'   => 'btn btn-danger btn-sm',
                'style'   => 'display:inline-block;',
                'onclick' => "return confirm('" . get_string('labelrules_deleteconfirm', 'format_iccaformat') . "');",
            ]);
        }
        if ($scopeid !== 0) {
            // Course page: Delete only (same effect as reset — removes the course override).
            echo html_writer::link($deleteurl, get_string('delete'), [
                'class'   => 'btn btn-danger btn-sm',
                'style'   => 'display:inline-block;',
                'onclick' => "return confirm('" . get_string('labelrules_deleteconfirm', 'format_iccaformat') . "');",
            ]);
        }
        echo '</div>';
        echo '</td>';
        echo '</tr>';
    }
    echo '</tbody></table>';
} else {
    echo '<p style="padding:1rem;color:#adb5bd;font-size:1rem;">' . get_string('labelrules_none', 'format_iccaformat') . '</p>';
}

// Inherited site rules (course view).
if ($scopeid !== 0 && !empty($siterules)) {
    echo '<div style="padding:8px 14px;background:#f8f9fa;border-top:2px solid #dee2e6;font-size:11px;font-weight:600;color:#6c757d;text-transform:uppercase;letter-spacing:0.07em;">'
        . get_string('labelrules_site_inherited', 'format_iccaformat') . '</div>';
    echo '<table class="icca-lr-table">';
    echo '<thead><tr>';
    echo '<th style="width:32px;"></th>';
    echo '<th>' . get_string('labelrules_modtype', 'format_iccaformat') . '</th>';
    echo '<th>' . get_string('labelrules_label', 'format_iccaformat') . '</th>';
    echo '<th>' . get_string('labelrules_icon', 'format_iccaformat') . '</th>';
    echo '<th>' . get_string('labelrules_defaultimage', 'format_iccaformat') . '</th>';
    echo '<th>' . get_string('labelrules_visibility', 'format_iccaformat') . '</th>';
    echo '<th></th>';
    echo '</tr></thead><tbody>';
    $courseexistingmods = array_column(array_values($rules), 'modname');
    foreach ($siterules as $rule) {
        $labelon    = (!isset($rule->label_enabled) || $rule->label_enabled);
        $iconon     = !empty($rule->icon_enabled);
        $flippedon  = !empty($rule->flipped);
        $imghidden  = !empty($rule->image_hidden);
        $alreadyoverridden = in_array($rule->modname, $courseexistingmods, true);
        // Icon display — handle numbering types for section.
        $specialicons = ['none','numeric','alpha','ALPHA','roman'];
        $iconlabels = ['none'=>'None','numeric'=>'1, 2, 3...','alpha'=>'a, b, c...','ALPHA'=>'A, B, C...','roman'=>'i, ii, iii...'];
        if ($rule->icon && in_array($rule->icon, $specialicons) && $rule->modname === 'section') {
            $icondisplay = '<code style="font-size:1rem;color:#6c757d;">' . ($iconlabels[$rule->icon] ?? $rule->icon) . '</code>';
        } else if ($rule->icon) {
            $icondisplay = '<i class="fa ' . s($rule->icon) . '" aria-hidden="true" style="' . ($iconon ? '' : 'opacity:0.4;') . '"></i>'
                . ' <code style="font-size:0.85rem;color:#6c757d;">' . s($rule->icon) . '</code>';
        } else {
            $icondisplay = '<span style="color:#adb5bd;font-size:1rem;">—</span>';
        }
        echo '<tr class="icca-lr-inherited-row" data-modname="' . s($rule->modname) . '" data-label="' . s(strtolower($rule->label ?? '')) . '" data-overridden="' . ($alreadyoverridden ? '1' : '0') . '" style="opacity:' . ($alreadyoverridden ? '0.4' : '0.75') . ';">';
        echo '<td></td>'; // Empty — no checkbox on inherited rows.
        echo '<td><span class="icca-mod-badge">' . s($rule->modname) . '</span></td>';
        echo '<td>' . ($rule->label ? '<span class="icca-label-pill' . ($labelon ? '' : ' icca-label-off') . '">' . s($rule->label) . '</span>' : '<span style="color:#adb5bd;font-size:1rem;">—</span>') . '</td>';
        echo '<td>' . $icondisplay . '</td>';
        $imgtd = '';
        if (!empty($rule->libraryimageid)) {
            $rimgurl = \format_iccaformat\output\courseformat\content::get_library_image_url_static((int)$rule->libraryimageid);
            $imgtd = '<div style="position:relative;display:inline-block;width:120px;height:80px;border-radius:4px;overflow:hidden;border:1px solid #dee2e6;">'
                . '<img src="' . htmlspecialchars($rimgurl, ENT_QUOTES) . '" style="width:120px;height:80px;object-fit:cover;display:block;" alt="">';
            if (!empty($rule->image_hidden)) {
                $imgtd .= '<div style="position:absolute;inset:0;background:rgba(200,200,200,0.65);display:flex;align-items:center;justify-content:center;">'
                    . '<svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="#555" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">'
                    . '<path d="M17.94 17.94A10.07 10.07 0 0112 20c-7 0-11-8-11-8a18.45 18.45 0 015.06-5.94M9.9 4.24A9.12 9.12 0 0112 4c7 0 11 8 11 8a18.5 18.5 0 01-2.16 3.19m-6.72-1.07a3 3 0 11-4.24-4.24"/>'
                    . '<line x1="1" y1="1" x2="23" y2="23"/></svg>'
                    . '</div>';
            }
            $imgtd .= '</div>';
        } else {
            $imgtd = '<span style="color:#adb5bd;font-size:1rem;">None</span>';
        }
        echo '<td>' . $imgtd . '</td>';
        echo '<td><div style="display:flex;gap:8px;"><span style="font-size:1rem;" class="' . ($labelon ? 'icca-vis-tick' : 'icca-vis-cross') . '">' . ($labelon ? '✓' : '✗') . ' Label</span><span style="font-size:1rem;" class="' . ($iconon ? 'icca-vis-tick' : 'icca-vis-cross') . '">' . ($iconon ? '✓' : '✗') . ' Icon</span>' . ($flippedon ? '<span style="font-size:0.8rem;background:#e8f0fb;color:#0d3c6f;border-radius:4px;padding:1px 7px;font-weight:500;" title="Icon and label order is flipped">⇄ Flipped</span>' : '') . '</div></td>';
        echo '<td>';
        if (!$alreadyoverridden) {
            // Pre-populate the add form with this modname to create an override.
            $overrideurl = new moodle_url($selfurl, ['override' => $rule->modname]);
            echo html_writer::link($overrideurl, get_string('labelrules_override_course', 'format_iccaformat'), [
                'class' => 'btn btn-secondary btn-sm',
                'style' => 'display:inline-block;',
            ]);
        } else {
            echo '<span style="font-size:0.85rem;color:#6c757d;font-style:italic;">Overridden</span>';
        }
        echo '</td>';
        echo '</tr>';
    }
    echo '</tbody></table>';
}

echo '</div></div>'; // card, right panel
echo '</div>'; // two-col wrap

?>
<script>
// Auto-fill label from module name.
(function() {
    var modsel = document.getElementById('lr_modname');
    var labelinput = document.getElementById('lr_label');
    if (!modsel || !labelinput) return;
    modsel.addEventListener('change', function() {
        if (labelinput.value) return; // Don't overwrite if already filled.
        var opt = modsel.options[modsel.selectedIndex];
        if (!opt || !opt.value) return;
        // Use friendly name — strip the (modname) part if present.
        var text = opt.text.replace(/\s*\([^)]+\)\s*$/, '').trim();
        labelinput.value = text;
    });
})();
function iccaMoveSkip(fromId, toId) {
    var from = document.getElementById('icca-skip-' + fromId);
    var to   = document.getElementById('icca-skip-' + toId);
    Array.from(from.selectedOptions).forEach(function(opt) {
        from.removeChild(opt);
        to.appendChild(opt);
        opt.selected = false;
    });
    [from, to].forEach(function(sel) {
        var opts = Array.from(sel.options).sort(function(a,b){ return a.text.localeCompare(b.text); });
        sel.innerHTML = '';
        opts.forEach(function(o){ sel.appendChild(o); });
    });
}
function iccaSelectAllSkipped() {
    var sel = document.getElementById('icca-skip-skipped');
    if (sel) Array.from(sel.options).forEach(function(o){ o.selected = true; });
}
function iccaSectionIconTypeChange(val) {
    var picker = document.getElementById('lr_section_custompicker');
    var iconHidden = document.getElementById('lr_icon');
    if (picker) picker.style.display = (val === 'icon') ? 'block' : 'none';
    // For non-custom types, set the hidden icon value directly.
    if (val !== 'icon') {
        if (iconHidden) iconHidden.value = val;
        var preview = document.getElementById('lr_icon_preview_i');
        if (preview) { preview.className = ''; preview.textContent = ''; }
    } else {
        // Sync from search field.
        var search = document.getElementById('lr_icon_search');
        if (iconHidden && search) iconHidden.value = search.value;
    }
}
function iccaLrFilterImgs(val) {
    val = val.toLowerCase().trim();
    document.querySelectorAll('.lr-img-tile').forEach(function(tile) {
        tile.style.display = (!val || tile.dataset.name.includes(val)) ? '' : 'none';
    });
}
function iccaLrShowUpload() {
    var panel = document.getElementById('lr_upload_panel');
    var imgPanel = document.getElementById('lr_img_panel');
    if (imgPanel) imgPanel.style.display = 'none';
    if (panel) panel.style.display = panel.style.display === 'none' ? 'block' : 'none';
}
function iccaLrUploadFileChanged(input) {
    var nameField = document.getElementById('lr_upload_name');
    if (nameField && input.files && input.files.length === 1 && !nameField.value) {
        var filename = input.files[0].name.replace(/\.[^.]+$/, '').replace(/[_\-]+/g, ' ');
        nameField.value = filename.charAt(0).toUpperCase() + filename.slice(1);
    }
}
function iccaLrPickImg() {
    var uploadPanel = document.getElementById('lr_upload_panel');
    if (uploadPanel) uploadPanel.style.display = 'none';
    var panel = document.getElementById('lr_img_panel');
    if (panel) panel.style.display = panel.style.display === 'none' ? 'block' : 'none';
}
function iccaLrSelectImg(imgid, imgurl) {
    document.getElementById('lr_imageid').value = imgid;
    var preview = document.getElementById('lr_img_preview');
    preview.innerHTML = '<img src="' + imgurl + '" style="width:100%;height:100%;object-fit:cover;" alt="">';
    document.querySelectorAll('.lr-img-tile').forEach(function(el) {
        el.style.border = (el.getAttribute('onclick') || '').indexOf('(' + imgid + ',') !== -1 ? '2.5px solid #0d6efd' : '1px solid #dee2e6';
    });
    document.getElementById('lr_img_panel').style.display = 'none';
    var search = document.getElementById('lr_img_search');
    if (search) { search.value = ''; iccaLrFilterImgs(''); }
}
function iccaLrClearImg() {
    document.getElementById('lr_imageid').value = 0;
    document.getElementById('lr_img_preview').innerHTML = '<span style="font-size:16px;color:#dee2e6;">&#8960;</span>';
    document.querySelectorAll('#lr_img_panel img').forEach(function(el) {
        el.style.border = '1px solid #dee2e6';
    });
}
function iccaShowSiteRuleResetDialog() {
    document.querySelectorAll('.icca-site-reset-chk').forEach(function(cb){ cb.checked = false; });
    document.getElementById('icca-site-reset-all').checked = false;
    document.getElementById('icca-site-rule-reset-dialog').style.display = 'flex';
}
function iccaCloseSiteRuleResetDialog() {
    document.getElementById('icca-site-rule-reset-dialog').style.display = 'none';
}
function iccaSiteResetSelectAll(cb) {
    document.querySelectorAll('.icca-site-reset-chk').forEach(function(el){ el.checked = cb.checked; });
}
function iccaSiteResetUpdateAll() {
    var all = Array.from(document.querySelectorAll('.icca-site-reset-chk')).every(function(el){ return el.checked; });
    document.getElementById('icca-site-reset-all').checked = all;
}
function iccaShowLoadDefaultsDialog() {
    document.getElementById('icca-load-defaults-dialog').style.display = 'flex';
}
function iccaCloseLoadDefaultsDialog() {
    document.getElementById('icca-load-defaults-dialog').style.display = 'none';
}
function iccaLrSelectAll(cb) {
    document.querySelectorAll('.icca-lr-chk').forEach(function(el) {
        var row = el.closest('tr');
        if (!row || row.style.display !== 'none') el.checked = cb.checked;
    });
    iccaLrUpdateBulkBar();
}
function iccaLrClearSelection() {
    document.querySelectorAll('.icca-lr-chk').forEach(function(el){ el.checked = false; });
    var sa = document.getElementById('icca-lr-select-all');
    if (sa) sa.checked = false;
    iccaLrUpdateBulkBar();
}
function iccaLrUpdateBulkBar() {
    var checked = document.querySelectorAll('.icca-lr-chk:checked');
    var bar     = document.getElementById('icca-lr-bulk-bar');
    var count   = document.getElementById('icca-lr-bulk-count');
    var form    = document.getElementById('icca-lr-bulk-form');
    if (bar) bar.style.display = checked.length > 0 ? 'flex' : 'none';
    if (count) count.textContent = checked.length + ' selected';
    var applyBtn = document.getElementById('icca-lr-bulkedit-apply');
    if (applyBtn) applyBtn.textContent = 'Apply to ' + checked.length + ' rule(s)';
    // Hide bulk edit panel when selection cleared.
    if (checked.length === 0) {
        var panel = document.getElementById('icca-lr-bulkedit-panel');
        if (panel) panel.style.display = 'none';
    }
    // Sync checkboxes into the bulk form.
    if (form) {
        form.querySelectorAll('input[name="ruleids[]"]').forEach(function(el){ el.remove(); });
        checked.forEach(function(cb) {
            var inp = document.createElement('input');
            inp.type = 'hidden'; inp.name = 'ruleids[]'; inp.value = cb.value;
            form.appendChild(inp);
        });
    }
    var sa = document.getElementById('icca-lr-select-all');
    var all = document.querySelectorAll('.icca-lr-chk');
    if (sa && all.length > 0) sa.checked = checked.length === all.length;
}
function iccaLrToggleBulkEdit() {
    var panel = document.getElementById('icca-lr-bulkedit-panel');
    if (!panel) return;
    var visible = panel.style.display !== 'none';
    panel.style.display = visible ? 'none' : 'block';
    if (!visible) {
        // Reset all radios to "no change" when opening.
        panel.querySelectorAll('input[type="radio"][value="nochange"]').forEach(function(r){ r.checked = true; });
    }
}
function iccaLrApplyBulkEdit(url) {
    var count = document.querySelectorAll('.icca-lr-chk:checked').length;
    if (!count) { alert('Please select at least one rule.'); return; }
    // Check at least one field has a non-nochange value.
    var hasChange = Array.from(document.querySelectorAll('#icca-lr-bulkedit-panel input[type="radio"]:checked'))
        .some(function(r){ return r.value !== 'nochange'; });
    if (!hasChange) { alert('Please select at least one field to change.'); return; }
    if (!confirm('Apply bulk edit to ' + count + ' rule(s)?')) return;

    var form = document.createElement('form');
    form.method = 'post';
    form.action = url;

    var addField = function(name, val) {
        var i = document.createElement('input');
        i.type = 'hidden'; i.name = name; i.value = val;
        form.appendChild(i);
    };
    addField('sesskey', M.cfg.sesskey);
    addField('scopeid', document.querySelector('input[name="scopeid"]').value);

    document.querySelectorAll('.icca-lr-chk:checked').forEach(function(cb){
        addField('ruleids[]', cb.value);
    });
    ['be_icons','be_labels','be_images','be_flip'].forEach(function(fname){
        var checked = document.querySelector('#icca-lr-bulkedit-panel input[name="' + fname + '"]:checked');
        if (checked) addField(fname, checked.value);
    });

    document.body.appendChild(form);
    form.submit();
}
function iccaLrBulkConfirmReset() {
    var count = document.querySelectorAll('.icca-lr-chk:checked').length;
    if (!count) { alert('Please select at least one rule.'); return false; }
    return confirm('Reset ' + count + ' rule(s)?');
}
function iccaLrBulkConfirmDelete() {
    var count = document.querySelectorAll('.icca-lr-chk:checked').length;
    if (!count) { alert('Please select at least one rule.'); return false; }
    return confirm('Delete ' + count + ' rule(s)? This cannot be undone.');
}
function iccaShowRuleResetDialog() {
    document.querySelectorAll('.icca-rule-reset-chk').forEach(function(cb){ cb.checked = false; });
    document.getElementById('icca-rule-reset-all').checked = false;
    document.getElementById('icca-rule-reset-dialog').style.display = 'flex';
}
function iccaCloseRuleResetDialog() {
    document.getElementById('icca-rule-reset-dialog').style.display = 'none';
}
function iccaRuleResetSelectAll(cb) {
    document.querySelectorAll('.icca-rule-reset-chk').forEach(function(el){ el.checked = cb.checked; });
}
function iccaRuleResetUpdateAll() {
    var all = Array.from(document.querySelectorAll('.icca-rule-reset-chk')).every(function(el){ return el.checked; });
    document.getElementById('icca-rule-reset-all').checked = all;
}
document.addEventListener('DOMContentLoaded', function() {
    var d = document.getElementById('icca-rule-reset-dialog');
    if (d) d.addEventListener('click', function(e){ if (e.target === this) iccaCloseRuleResetDialog(); });
    var ds = document.getElementById('icca-site-rule-reset-dialog');
    if (ds) ds.addEventListener('click', function(e){ if (e.target === this) iccaCloseSiteRuleResetDialog(); });
    var dl = document.getElementById('icca-load-defaults-dialog');
    if (dl) dl.addEventListener('click', function(e){ if (e.target === this) iccaCloseLoadDefaultsDialog(); });
});
function iccaLrSearch(val, exact) {
    val = (val || '').toLowerCase();
    var hideOverridden = document.getElementById('icca-hide-overridden');
    var hiding = hideOverridden && hideOverridden.checked;
    // Filter course override rows.
    document.querySelectorAll('.icca-lr-row').forEach(function(row) {
        var modname = (row.dataset.modname || '').toLowerCase();
        var label   = (row.dataset.label   || '').toLowerCase();
        var match = !val || (exact ? modname === val : (modname.includes(val) || label.includes(val)));
        row.style.display = match ? '' : 'none';
    });
    // Filter inherited rows.
    document.querySelectorAll('.icca-lr-inherited-row').forEach(function(row) {
        var modname    = (row.dataset.modname || '').toLowerCase();
        var label      = (row.dataset.label   || '').toLowerCase();
        var overridden = row.dataset.overridden === '1';
        var nameMatch  = !val || (exact ? modname === val : (modname.includes(val) || label.includes(val)));
        var show = nameMatch && !(hiding && overridden);
        row.style.display = show ? '' : 'none';
    });
}

// Scroll to and flash the edit card when editing a rule.
(function() {
    var card = document.getElementById('icca-edit-card');
    if (!card) return;
    var hasRuleid   = window.location.search.indexOf('ruleid=') !== -1;
    var hasOverride = window.location.search.indexOf('override=') !== -1;
    if (!hasRuleid && !hasOverride) return;
    var editModname = '<?= $editrule ? s($editrule->modname) : ($overridemod ? s($overridemod) : '') ?>';
    if (editModname) {
        var searchEl = document.getElementById('icca-lr-search');
        if (searchEl) {
            searchEl.value = editModname;
            if (typeof iccaLrSearch === 'function') iccaLrSearch(editModname, true);
        }
    }
    setTimeout(function() {
        var rect = card.getBoundingClientRect();
        window.scrollBy({ top: rect.top - 80, behavior: 'smooth' });
        card.style.transition = 'box-shadow 0.3s ease';
        card.style.boxShadow = '0 0 0 3px #0d3c6f, 0 4px 16px rgba(0,0,0,0.15)';
        setTimeout(function() {
            card.style.boxShadow = '';
        }, 1500);
    }, 150);
})();
</script>
<?php

echo html_writer::start_tag('script');
$iconlist = json_encode([
    "fa-home", "fa-user", "fa-users", "fa-group", "fa-cog", "fa-cogs", "fa-wrench",
    "fa-search", "fa-bell", "fa-star", "fa-heart", "fa-flag", "fa-bookmark",
    "fa-tag", "fa-tags", "fa-lock", "fa-unlock", "fa-key", "fa-shield",
    "fa-globe", "fa-map", "fa-map-marker", "fa-compass", "fa-clock-o",
    "fa-file", "fa-file-o", "fa-file-text", "fa-file-text-o", "fa-file-pdf-o",
    "fa-file-word-o", "fa-file-excel-o", "fa-file-powerpoint-o", "fa-file-image-o",
    "fa-file-archive-o", "fa-file-code-o", "fa-files-o", "fa-copy", "fa-clone",
    "fa-folder", "fa-folder-o", "fa-folder-open", "fa-folder-open-o",
    "fa-book", "fa-graduation-cap", "fa-mortar-board", "fa-university",
    "fa-pencil", "fa-pencil-square-o", "fa-edit", "fa-lightbulb-o",
    "fa-flask", "fa-envelope", "fa-envelope-o", "fa-comment", "fa-comment-o",
    "fa-comments", "fa-comments-o", "fa-phone", "fa-video-camera",
    "fa-microphone", "fa-bullhorn", "fa-rss", "fa-share", "fa-share-alt",
    "fa-play", "fa-play-circle", "fa-play-circle-o", "fa-pause", "fa-stop",
    "fa-film", "fa-music", "fa-headphones", "fa-image", "fa-camera",
    "fa-check", "fa-check-circle", "fa-check-circle-o", "fa-check-square",
    "fa-check-square-o", "fa-times", "fa-times-circle", "fa-circle", "fa-circle-o",
    "fa-tasks", "fa-list", "fa-list-ul", "fa-list-ol", "fa-th", "fa-th-large",
    "fa-table", "fa-bars", "fa-bar-chart", "fa-line-chart", "fa-pie-chart",
    "fa-database", "fa-server", "fa-cloud", "fa-cloud-upload", "fa-cloud-download",
    "fa-download", "fa-upload", "fa-external-link", "fa-link",
    "fa-refresh", "fa-repeat", "fa-undo", "fa-arrow-right", "fa-arrow-left",
    "fa-plus", "fa-plus-circle", "fa-minus", "fa-minus-circle",
    "fa-question", "fa-question-circle", "fa-question-circle-o",
    "fa-info", "fa-info-circle", "fa-exclamation-triangle", "fa-warning",
    "fa-trophy", "fa-certificate", "fa-thumbs-up", "fa-thumbs-down", "fa-hand-pointer-o",
    "fa-cube", "fa-cubes", "fa-puzzle-piece", "fa-archive",
    "fa-briefcase", "fa-calendar", "fa-calendar-o", "fa-print",
    "fa-wpforms", "fa-address-card", "fa-clipboard",
    "fa-solid fa-layer-group", "fa-solid fa-chalkboard-user",
    "fa-solid fa-graduation-cap", "fa-solid fa-book-open",
    "fa-solid fa-clipboard-list", "fa-solid fa-file-lines",
    "fa-solid fa-circle-check", "fa-solid fa-lightbulb",
    "fa-solid fa-brain", "fa-solid fa-microscope", "fa-solid fa-flask",
    "fa-solid fa-scale-balanced", "fa-solid fa-gavel",
    "fa-solid fa-landmark", "fa-solid fa-building-columns",
    "fa-solid fa-scroll", "fa-solid fa-feather", "fa-solid fa-pen-nib",
    "fa-solid fa-highlighter", "fa-solid fa-folder-open",
    "fa-solid fa-boxes-stacked",
]);
echo '
(function() {
    var icons = ' . $iconlist . ';

    var hidden   = document.getElementById("lr_icon");
    var search   = document.getElementById("lr_icon_search");
    var dropdown = document.getElementById("lr_icon_dropdown");
    var preview  = document.getElementById("lr_icon_preview_i");

    if (!hidden || !search || !dropdown || !preview) return;

    function renderIcon(cls) {
        // FA4 uses "fa fa-xxx", FA5 solid uses "fa-solid fa-xxx"
        if (cls.indexOf("fa-solid") === 0) return cls;
        return cls ? (cls.indexOf("fa-solid") === 0 ? cls : "fa " + cls) : "";
    }

    function updatePreview(cls) {
        preview.className = renderIcon(cls);
    }

    function showDropdown(query) {
        var q = query.toLowerCase().trim();
        var matches = q.length === 0
            ? icons.slice().sort()
            : icons.filter(function(ic) {
                return ic.replace("fa-solid ", "").replace("fa-", "").indexOf(q) !== -1;
              }).sort();

        if (matches.length === 0) {
            dropdown.style.display = "none";
            return;
        }

        dropdown.innerHTML = "";
        matches.forEach(function(ic) {
            var row = document.createElement("div");
            row.style.cssText = "display:flex;align-items:center;gap:10px;padding:7px 12px;cursor:pointer;font-size:1rem;font-family:monospace;border-bottom:1px solid #f0f0f0;";
            row.innerHTML = "<i class=\"" + renderIcon(ic) + "\" style=\"width:20px;text-align:center;font-size:16px;color:#495057;\"></i><span>" + ic + "</span>";
            row.addEventListener("mousedown", function(e) {
                e.preventDefault();
                hidden.value = ic;
                search.value = ic;
                updatePreview(ic);
                dropdown.style.display = "none";
            });
            row.addEventListener("mouseenter", function() { this.style.background = "#f8f9fa"; });
            row.addEventListener("mouseleave", function() { this.style.background = ""; });
            dropdown.appendChild(row);
        });
        dropdown.style.display = "block";
    }

    search.addEventListener("input", function() {
        updatePreview(this.value.trim());
        hidden.value = this.value.trim();
        showDropdown(this.value.trim());
    });

    search.addEventListener("focus", function() {
        showDropdown(this.value.trim());
    });

    document.addEventListener("click", function(e) {
        if (!dropdown.contains(e.target) && e.target !== search) {
            dropdown.style.display = "none";
        }
    });

    // Initialise preview.
    updatePreview(hidden.value);
})();
';
echo html_writer::end_tag('script');

echo $OUTPUT->footer();

