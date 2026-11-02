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

namespace format_iccaformat\privacy;

/**
 * Privacy provider for format_iccaformat: this plugin stores no personal data.
 *
 * Its tables hold course configuration only: per-card options
 * (iccaformat_element_options) and Card Styling Rules (iccaformat_label_rules). Image
 * library uploads, and who uploaded them, now belong to local_iccaimagelib, which has its
 * own provider. Images uploaded directly to a card (element_image, cm_image file areas)
 * are course content, treated the same way as core's Page activity treats its files.
 *
 * @package   format_iccaformat
 * @copyright 2026 Inns of Court College of Advocacy (Part of COIC)
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class provider implements \core_privacy\local\metadata\null_provider {
    /**
     * Language string explaining why no data is stored.
     *
     * @return string
     */
    public static function get_reason(): string {
        return 'privacy:metadata';
    }
}