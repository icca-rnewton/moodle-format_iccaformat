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
 * Restore plugin for format_iccaformat.
 *
 * @package   format_iccaformat
 * @copyright 2026 Inns of Court College of Advocacy (Part of COIC)
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

/**
 * Restore plugin class for ICCA Format.
 */
class restore_format_iccaformat_plugin extends restore_format_plugin {
    /**
     * Section-level restore paths.
     */
    public function define_section_plugin_structure() {
        return [
            new restore_path_element(
                'iccaformat_section_wrapper',
                $this->get_pathfor('/')
            ),
            new restore_path_element(
                'iccaformat_secoption',
                $this->get_pathfor('/secoption')
            ),

        ];
    }

    /**
     * Course-level restore paths.
     */
    public function define_course_plugin_structure() {
        return [
            new restore_path_element(
                'iccaformat_cmoption',
                '/course/plugin_format_iccaformat_course/cmoptions/cmoption'
            ),
            new restore_path_element(
                'iccaformat_label_rule',
                '/course/plugin_format_iccaformat_course/label_rules/label_rule'
            ),
            new restore_path_element(
                'iccaformat_library_image',
                '/course/plugin_format_iccaformat_course/library_images/library_image'
            ),
        ];
    }

    /**
     * Process section wrapper — register file mapping for this section.
     */
    public function process_iccaformat_section_wrapper($data) {
        $data         = (object) $data;
        $oldsectionid = (int) ($data->sectionid ?? 0);
        $newsectionid = $this->task->get_sectionid();

        if ($oldsectionid && $newsectionid) {
            $this->set_mapping('iccaformat_section', $oldsectionid, $newsectionid, true);
        }
    }

    /**
     * Restore a section element option row.
     */
    public function process_iccaformat_secoption($data) {
        global $DB;

        $data         = (object) $data;
        $courseid     = $this->task->get_courseid();
        $newsectionid = $this->task->get_sectionid();

        if (!$newsectionid || $data->optionvalue === null || $data->optionvalue === '') {
            return;
        }

        if (
            $DB->record_exists_select(
                'iccaformat_element_options',
                'courseid = ? AND elementid = ? AND optionname = ? AND elementtype IN (1, 2)',
                [$courseid, $newsectionid, $data->optionname]
            )
        ) {
            return;
        }

        $sectioncomponent = $DB->get_field('course_sections', 'component', ['id' => $newsectionid]);
        $elementtype      = ($sectioncomponent === 'mod_subsection') ? 2 : 1;

        $DB->insert_record('iccaformat_element_options', [
            'courseid'    => $courseid,
            'elementid'   => $newsectionid,
            'elementtype' => $elementtype,
            'optionname'  => $data->optionname,
            'optionvalue' => $data->optionvalue,
            'timecreated' => time(),
            'timemodified' => time(),
        ]);
    }

    /**
     * Restore a CM element option row.
     * Now at course level so CM mappings already exist.
     */
    public function process_iccaformat_cmoption($data) {
        global $DB;

        $data     = (object) $data;
        $courseid = $this->get_courseid();
        $oldcmid  = (int) $data->elementid;

        if ($data->optionvalue === null || $data->optionvalue === '') {
            return;
        }

        $newcmid = $this->get_mappingid('course_module', $oldcmid);
        if (!$newcmid) {
            return;
        }

        // Register file mapping old cmid -> new cmid.
        $this->set_mapping('iccaformat_cm', $oldcmid, $newcmid, true);

        // Backups made before activity uploads moved to cm_image have no cmfilearea
        // marker — flag it so after_restore_course() also picks up the old file area.
        if (empty($data->cmfilearea)) {
            $this->set_mapping('iccaformat_legacycmfiles', 1, 1);
        }

        if (
            $DB->record_exists('iccaformat_element_options', [
            'courseid'    => $courseid,
            'elementtype' => 3,
            'elementid'   => $newcmid,
            'optionname'  => $data->optionname,
            ])
        ) {
            return;
        }

        $DB->insert_record('iccaformat_element_options', [
            'courseid'    => $courseid,
            'elementid'   => $newcmid,
            'elementtype' => 3,
            'optionname'  => $data->optionname,
            'optionvalue' => $data->optionvalue,
            'timecreated' => time(),
            'timemodified' => time(),
        ]);
    }

    /**
     * Restore label rule.
     */
    public function process_iccaformat_label_rule($data) {
        global $DB;

        $data           = (object) $data;
        $data->courseid = $this->task->get_courseid();

        if (
            $DB->record_exists('iccaformat_label_rules', [
            'courseid' => $data->courseid,
            'modname'  => $data->modname,
            ])
        ) {
            return;
        }

        unset($data->id);
        $data->timemodified = time();
        $DB->insert_record('iccaformat_label_rules', $data);
    }

    /**
     * Restore a course library image record into local_iccaimagelib.
     *
     * @param array $data
     */
    public function process_iccaformat_library_image($data) {
        $data      = (object) $data;
        $contextid = (int) \context_course::instance($this->task->get_courseid())->id;
        $newid     = \local_iccaimagelib\library::restore_record($data, $contextid, (int) $this->task->get_userid());
        $this->set_mapping('iccaformat_library_image', $data->id, $newid, true);
    }

    /**
     * After section restore — restore section image files only.
     * CM files are deferred to after_restore_course().
     */
    public function after_restore_section() {
        $this->add_related_files('format_iccaformat', 'element_image', 'iccaformat_section');
    }

    /**
     * After course restore — restore CM and library image files.
     */
    public function after_restore_course() {
        $this->add_related_files('format_iccaformat', 'cm_image', 'iccaformat_cm');

        // Older backups stored activity uploads in element_image alongside section
        // uploads. Restore them there, then move them across with the same helper the
        // upgrade step uses (ambiguous ids are left in place rather than guessed).
        if ($this->get_mappingid('iccaformat_legacycmfiles', 1)) {
            $this->add_related_files('format_iccaformat', 'element_image', 'iccaformat_cm');
            \format_iccaformat\local\format_option::migrate_legacy_cm_files($this->get_courseid());
        }

        // Library image files. Backups made before the library moved to local_iccaimagelib hold
        // them under format_iccaformat/library_image: restore those too, then hand them over.
        $this->add_related_files(
            \local_iccaimagelib\library::COMPONENT,
            \local_iccaimagelib\library::FILEAREA,
            'iccaformat_library_image'
        );
        $this->add_related_files('format_iccaformat', 'library_image', 'iccaformat_library_image');
        \local_iccaimagelib\library::adopt_legacy_files((int) \context_course::instance($this->get_courseid())->id);

        $this->remap_library_image_references();
    }

    /**
     * Point restored library-image references at the restored copies.
     *
     * Card Styling Rules (libraryimageid) and per-card overrides (imageid) are
     * restored before the course's library images, so they still hold the
     * backup's old image ids. Run once everything is in:
     *   - an id the backup also contained is mapped to the restored copy;
     *   - an id that exists on this site but wasn't in the backup (a site-wide
     *     library image, or another course's) is left as it is;
     *   - an id that doesn't exist on this site at all is cleared, so the card
     *     falls back to its default instead of a broken image.
     */
    private function remap_library_image_references(): void {
        global $DB;

        $courseid = $this->get_courseid();
        $resolve  = function (int $oldid) use ($DB): int {
            if ($oldid <= 0) {
                return 0;
            }
            $newid = (int) $this->get_mappingid('iccaformat_library_image', $oldid);
            if ($newid) {
                return $newid;
            }
            return \local_iccaimagelib\library::get($oldid) ? $oldid : 0;
        };

        $rules = $DB->get_records_select(
            'iccaformat_label_rules',
            'courseid = ? AND libraryimageid > 0',
            [$courseid],
            '',
            'id, libraryimageid'
        );
        foreach ($rules as $rule) {
            $newid = $resolve((int) $rule->libraryimageid);
            if ($newid !== (int) $rule->libraryimageid) {
                $DB->set_field('iccaformat_label_rules', 'libraryimageid', $newid ?: null, ['id' => $rule->id]);
            }
        }

        $options = $DB->get_records_select(
            'iccaformat_element_options',
            'courseid = ? AND optionname = ?',
            [$courseid, 'imageid'],
            '',
            'id, optionvalue'
        );
        foreach ($options as $option) {
            $oldid = (int) $option->optionvalue;
            $newid = $resolve($oldid);
            if ($newid !== $oldid) {
                $DB->set_field('iccaformat_element_options', 'optionvalue', (string) $newid, ['id' => $option->id]);
            }
        }
    }
}