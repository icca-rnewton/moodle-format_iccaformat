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
 * Section control menu for format_iccaformat.
 *
 * Card settings for sections are now part of the Edit section form
 * via section_format_options() in lib.php — no extra menu item needed.
 *
 * @package   format_iccaformat
 * @copyright 2026 ICCA
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace format_iccaformat\output\courseformat\content\section;

defined('MOODLE_INTERNAL') || die();

use core_courseformat\output\local\content\section\controlmenu as controlmenu_base;

/**
 * Section control menu — uses parent implementation unchanged.
 * Card settings appear in the Edit section form fieldset.
 */
class controlmenu extends controlmenu_base {
    // No overrides needed — section card settings live in Edit section form.
}
