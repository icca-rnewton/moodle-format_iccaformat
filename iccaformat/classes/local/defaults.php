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
 * Out-of-the-box defaults: the site-wide Card Styling Rules.
 *
 * The default images now belong to local_iccaimagelib, which imports them as site-wide
 * library images, each marked with a bundle key matching a rule key (e.g. quiz). Rules are
 * linked to those images in whichever of these happens first:
 *   - this format's install, if the library is already installed;
 *   - the library telling the format after it imports them
 *     (format_iccaformat_iccaimagelib_bundled_imported() in lib.php);
 *   - "Load defaults" on the site-wide Card Styling Rules page.
 * Linking only ever fills rules that have no image; a chosen image is never replaced.
 *
 * @package   format_iccaformat
 * @copyright 2026 Inns of Court College of Advocacy (Part of COIC)
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace format_iccaformat\local;

/**
 * Default rules and links to the bundled images.
 */
class defaults {
    /**
     * Default site-level rules shipped with the format, keyed by modname
     * ("section" is the format's own key for top-level sections).
     *
     * @return array modname => ['label' => string, 'icon' => string]
     */
    public static function rules(): array {
        return [
            'quiz'         => ['label' => 'Assessment', 'icon' => 'fa-check-square-o'],
            'subsection'   => ['label' => 'Subsection', 'icon' => 'fa-layer-group'],
            'section'      => ['label' => 'Section', 'icon' => 'numeric'],
            'assign'       => ['label' => 'Assignment', 'icon' => 'fa-pencil-square-o'],
            'page'         => ['label' => 'Reading', 'icon' => 'fa-file-text-o'],
            'url'          => ['label' => 'Link', 'icon' => 'fa-link'],
            'resource'     => ['label' => 'File', 'icon' => 'fa-file-o'],
            'forum'        => ['label' => 'Discussion', 'icon' => 'fa-comments-o'],
            'scorm'        => ['label' => 'Course', 'icon' => 'fa-play-circle-o'],
            'h5pactivity'  => ['label' => 'Interactive', 'icon' => 'fa-hand-pointer-o'],
            'lesson'       => ['label' => 'Lesson', 'icon' => 'fa-graduation-cap'],
            'workshop'     => ['label' => 'Workshop', 'icon' => 'fa-users'],
            'choice'       => ['label' => 'Choice', 'icon' => 'fa-question-circle-o'],
            'feedback'     => ['label' => 'Feedback', 'icon' => 'fa-comment-o'],
            'glossary'     => ['label' => 'Glossary', 'icon' => 'fa-book'],
            'book'         => ['label' => 'Guide', 'icon' => 'fa-book'],
            'lti'          => ['label' => 'External', 'icon' => 'fa-external-link'],
            'folder'       => ['label' => 'Folder', 'icon' => 'fa-folder-o'],
            'wiki'         => ['label' => 'Wiki', 'icon' => 'fa-pencil'],
            'data'         => ['label' => 'Database', 'icon' => 'fa-database'],
            'dataform'     => ['label' => 'Data Form', 'icon' => 'fa-wpforms'],
            'cpdrecord'    => ['label' => 'CPD Record', 'icon' => 'fa-cube'],
            'groupmembers' => ['label' => 'Groups', 'icon' => 'fa-group'],
            'chat'         => ['label' => 'Chat', 'icon' => 'fa-comments'],
            'booking'      => ['label' => 'Booking', 'icon' => 'fa-calendar-check-o'],
            'pulse'        => ['label' => 'Reminder', 'icon' => 'fa-bell-o'],
        ];
    }

    /**
     * First-install setup: create the default rules, linking the bundled images if the
     * library has already imported them. Called from db/install.php.
     *
     * @return void
     */
    public static function install(): void {
        self::seed_rules(self::bundled_images());
    }

    /**
     * Bundled images in the library, or none if the library is not installed yet.
     *
     * When both plugins are installed in one upgrade, this format installs first (Moodle
     * installs format plugins before local ones), so the library table may not exist yet.
     *
     * @return array rule key => library image id.
     */
    public static function bundled_images(): array {
        global $DB;
        if (!$DB->get_manager()->table_exists(\local_iccaimagelib\library::TABLE)) {
            return [];
        }
        return \local_iccaimagelib\library::get_bundled_images();
    }

    /**
     * Link bundled images to site-wide rules that have no image. Never replaces a chosen
     * image and never creates rules. Safe to call more than once.
     *
     * @param array $imageids rule key => library image id.
     * @return int Number of rules linked.
     */
    public static function link_images(array $imageids): int {
        global $DB;
        $linked = 0;
        foreach ($imageids as $modname => $imageid) {
            $rule = $DB->get_record('iccaformat_label_rules', ['courseid' => 0, 'modname' => (string) $modname]);
            if ($rule && empty($rule->libraryimageid) && (int) $imageid > 0) {
                $DB->set_field('iccaformat_label_rules', 'libraryimageid', (int) $imageid, ['id' => $rule->id]);
                $linked++;
            }
        }
        return $linked;
    }

    /**
     * Create any missing site-wide default rules, and link images to rules that
     * have none. Existing rules' labels, icons and chosen images are never changed.
     *
     * Rules are only created for modules installed on this site (plus "section").
     *
     * @param  array $imageids key => library image id, from bundled_images().
     * @return void
     */
    public static function seed_rules(array $imageids): void {
        global $DB;

        foreach (self::rules() as $modname => $defaults) {
            if ($modname !== 'section' && !\core_component::get_component_directory('mod_' . $modname)) {
                continue;
            }
            $imageid = $imageids[$modname] ?? null;
            $rule = $DB->get_record('iccaformat_label_rules', ['courseid' => 0, 'modname' => $modname]);
            if (!$rule) {
                $DB->insert_record('iccaformat_label_rules', (object) [
                    'courseid'       => 0,
                    'modname'        => $modname,
                    'label'          => $defaults['label'],
                    'icon'           => $defaults['icon'],
                    'label_enabled'  => 1,
                    'icon_enabled'   => 1,
                    'flipped'        => 0,
                    'image_hidden'   => 0,
                    'libraryimageid' => $imageid,
                    'timemodified'   => time(),
                ]);
            } else if ($imageid && empty($rule->libraryimageid)) {
                $DB->set_field('iccaformat_label_rules', 'libraryimageid', $imageid, ['id' => $rule->id]);
            }
        }
    }
}