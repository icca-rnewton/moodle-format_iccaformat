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
 * Activity control menu for format_iccaformat.
 *
 * Card settings for activities are now injected into the standard Edit
 * activity form via format_iccaformat_coursemodule_standard_elements()
 * in lib.php — no separate menu item needed.
 *
 * @package   format_iccaformat
 * @copyright 2026 ICCA
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace format_iccaformat\output\courseformat\content\cm;

defined('MOODLE_INTERNAL') || die();

use core_courseformat\output\local\content\cm\controlmenu as controlmenu_base;

/**
 * Activity control menu — uses parent implementation unchanged.
 * Card settings appear in the Edit activity form fieldset.
 */
class controlmenu extends controlmenu_base {
    // No overrides needed — activity card settings live in the Edit activity form.
}
