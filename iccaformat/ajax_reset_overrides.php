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
 * AJAX endpoint — reset per-card overrides for a course.
 *
 * @package   format_iccaformat
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
    $deleted += $DB->count_records_select('iccaformat_element_options',
        'courseid = ? AND optionname = ?',
        [$courseid, 'icon_label_flipped']);
    $DB->delete_records_select('iccaformat_element_options',
        'courseid = ? AND optionname = ?',
        [$courseid, 'icon_label_flipped']);
}

header('Content-Type: application/json');
echo json_encode(['success' => true, 'deleted' => $deleted]);
exit;