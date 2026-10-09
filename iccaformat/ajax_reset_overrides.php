<?php
/**
 * AJAX endpoint — reset card styling overrides for a course.
 *
 * @copyright 2026 Inns of Court College of Advocacy (Part of COIC)
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

define('AJAX_SCRIPT', true);
require_once('../../../config.php');

$courseid    = required_param('courseid', PARAM_INT);
$reseticons  = optional_param('reseticons',  0, PARAM_INT);
$resetlabels = optional_param('resetlabels', 0, PARAM_INT);
$resetimages = optional_param('resetimages', 0, PARAM_INT);
$resetflipped = optional_param('resetflipped', 0, PARAM_INT);

require_login();
require_sesskey();

$course  = get_course($courseid);
$context = context_course::instance($courseid);
require_capability('moodle/course:update', $context);

global $DB;

$deleted = 0;

if ($reseticons) {
    $deleted += $DB->count_records_select('iccaformat_element_options',
        'courseid = ? AND optionname IN (?, ?)',
        [$courseid, 'icon_enabled', 'icon_override']);
    $DB->delete_records_select('iccaformat_element_options',
        'courseid = ? AND optionname IN (?, ?)',
        [$courseid, 'icon_enabled', 'icon_override']);
}

if ($resetlabels) {
    $deleted += $DB->count_records_select('iccaformat_element_options',
        'courseid = ? AND optionname IN (?, ?)',
        [$courseid, 'label_enabled', 'label_override']);
    $DB->delete_records_select('iccaformat_element_options',
        'courseid = ? AND optionname IN (?, ?)',
        [$courseid, 'label_enabled', 'label_override']);
}

if ($resetimages) {
    $deleted += $DB->count_records_select('iccaformat_element_options',
        'courseid = ? AND optionname IN (?, ?, ?, ?, ?, ?, ?, ?)',
        [$courseid, 'imagesource', 'imageid', 'imageobjectfit', 'imageobjectposition',
         'imagefilter', 'imageoverlay', 'imagebackgroundcolour', 'imagesize']);
    $DB->delete_records_select('iccaformat_element_options',
        'courseid = ? AND optionname IN (?, ?, ?, ?, ?, ?, ?, ?)',
        [$courseid, 'imagesource', 'imageid', 'imageobjectfit', 'imageobjectposition',
         'imagefilter', 'imageoverlay', 'imagebackgroundcolour', 'imagesize']);
}

if ($resetflipped) {
    $DB->delete_records_select('iccaformat_element_options',
        'courseid = ? AND optionname = ?',
        [$courseid, 'icon_label_flipped']);
}

header('Content-Type: application/json');
echo json_encode(['success' => true, 'deleted' => $deleted]);
exit;
