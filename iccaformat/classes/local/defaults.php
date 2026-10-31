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
 * Out-of-the-box defaults: site-wide Card Styling Rules and the bundled card images.
 *
 * The images in pix/cardimages/ (one SVG per rule key, e.g. quiz.svg) are copied into
 * the site-wide Card Image Library on first install and linked to the default rules,
 * so a new site has fully styled cards with no setup. After that the library copy is
 * what cards use; admins can rename, replace or delete it like any other image.
 *
 * @package   format_iccaformat
 * @copyright 2026 Inns of Court College of Advocacy (Part of COIC)
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace format_iccaformat\local;

defined('MOODLE_INTERNAL') || die();

/**
 * Default rules and bundled images.
 */
class defaults {

    /** @var string Bundled images folder, relative to $CFG->dirroot. */
    const IMAGEDIR = '/course/format/iccaformat/pix/cardimages';

    /**
     * Default site-level rules shipped with the format, keyed by modname
     * ("section" is the format's own key for top-level sections).
     *
     * @return array modname => ['label' => string, 'icon' => string]
     */
    public static function rules(): array {
        return [
            'quiz'         => ['label' => 'Assessment',  'icon' => 'fa-check-square-o'],
            'subsection'   => ['label' => 'Subsection',  'icon' => 'fa-layer-group'],
            'section'      => ['label' => 'Section',     'icon' => 'numeric'],
            'assign'       => ['label' => 'Assignment',  'icon' => 'fa-pencil-square-o'],
            'page'         => ['label' => 'Reading',     'icon' => 'fa-file-text-o'],
            'url'          => ['label' => 'Link',        'icon' => 'fa-link'],
            'resource'     => ['label' => 'File',        'icon' => 'fa-file-o'],
            'forum'        => ['label' => 'Discussion',  'icon' => 'fa-comments-o'],
            'scorm'        => ['label' => 'Course',      'icon' => 'fa-play-circle-o'],
            'h5pactivity'  => ['label' => 'Interactive', 'icon' => 'fa-hand-pointer-o'],
            'lesson'       => ['label' => 'Lesson',      'icon' => 'fa-graduation-cap'],
            'workshop'     => ['label' => 'Workshop',    'icon' => 'fa-users'],
            'choice'       => ['label' => 'Choice',      'icon' => 'fa-question-circle-o'],
            'feedback'     => ['label' => 'Feedback',    'icon' => 'fa-comment-o'],
            'glossary'     => ['label' => 'Glossary',    'icon' => 'fa-book'],
            'book'         => ['label' => 'Guide',       'icon' => 'fa-book'],
            'lti'          => ['label' => 'External',    'icon' => 'fa-external-link'],
            'folder'       => ['label' => 'Folder',      'icon' => 'fa-folder-o'],
            'wiki'         => ['label' => 'Wiki',        'icon' => 'fa-pencil'],
            'data'         => ['label' => 'Database',    'icon' => 'fa-database'],
            'dataform'     => ['label' => 'Data Form',   'icon' => 'fa-wpforms'],
            'cpdrecord'    => ['label' => 'CPD Record',  'icon' => 'fa-cube'],
            'groupmembers' => ['label' => 'Groups',      'icon' => 'fa-group'],
            'chat'         => ['label' => 'Chat',        'icon' => 'fa-comments'],
            'booking'      => ['label' => 'Booking',     'icon' => 'fa-calendar-check-o'],
            'pulse'        => ['label' => 'Reminder',    'icon' => 'fa-bell-o'],
        ];
    }

    /**
     * First-install setup: import the bundled images and create the default rules
     * with those images linked. Called from db/install.php.
     *
     * @return void
     */
    public static function install(): void {
        self::seed_rules(self::import_bundled_images());
    }

    /**
     * Library name for a bundled image, e.g. "Assessment (default)".
     *
     * @param  string $key Rule key / file name without extension.
     * @return string
     */
    public static function image_name(string $key): string {
        $rules = self::rules();
        $label = $rules[$key]['label'] ?? $key;
        return get_string('bundledimage_name', 'format_iccaformat', $label);
    }

    /**
     * Copy every bundled image into the site-wide library, skipping any whose
     * library name already exists. Safe to call more than once.
     *
     * @return array key => library image id (new or existing).
     */
    public static function import_bundled_images(): array {
        global $CFG, $DB;

        $dir = $CFG->dirroot . self::IMAGEDIR;
        $files = is_dir($dir) ? glob($dir . '/*.svg') : [];
        if (!$files) {
            return [];
        }

        $syscontext = \context_system::instance();
        $existing   = self::find_bundled_images();
        $admin      = get_admin();
        $userid     = $admin ? (int) $admin->id : 0;
        $fs         = get_file_storage();
        $ids        = [];

        foreach ($files as $path) {
            $key = basename($path, '.svg');
            if (isset($existing[$key])) {
                $ids[$key] = $existing[$key];
                continue;
            }
            $now = time();
            $imageid = (int) $DB->insert_record(image_library::TABLE, (object) [
                'contextid'    => $syscontext->id,
                'name'         => self::image_name($key),
                'description'  => '',
                'filename'     => $key . '.svg',
                'userid'       => $userid,
                'timecreated'  => $now,
                'timemodified' => $now,
            ]);
            $fs->create_file_from_string([
                'contextid' => $syscontext->id,
                'component' => image_library::COMPONENT,
                'filearea'  => image_library::FILEAREA,
                'itemid'    => $imageid,
                'filepath'  => '/',
                'filename'  => $key . '.svg',
            ], image_library::sanitise_svg((string) file_get_contents($path)));
            $ids[$key] = $imageid;
        }
        return $ids;
    }

    /**
     * Bundled images already present in the site-wide library, matched by name.
     *
     * @return array key => library image id.
     */
    public static function find_bundled_images(): array {
        global $DB;
        $syscontextid = \context_system::instance()->id;
        $ids = [];
        foreach (array_keys(self::rules()) as $key) {
            $id = $DB->get_field_select(image_library::TABLE, 'id',
                'contextid = ? AND ' . $DB->sql_compare_text('name') . ' = ' . $DB->sql_compare_text('?'),
                [$syscontextid, self::image_name($key)], IGNORE_MULTIPLE);
            if ($id) {
                $ids[$key] = (int) $id;
            }
        }
        return $ids;
    }

    /**
     * Create any missing site-wide default rules, and link images to rules that
     * have none. Existing rules' labels, icons and chosen images are never changed.
     *
     * Rules are only created for modules installed on this site (plus "section").
     *
     * @param  array $imageids key => library image id, from import/find above.
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