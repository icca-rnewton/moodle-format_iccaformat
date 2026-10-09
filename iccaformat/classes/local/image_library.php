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
 * Image library helper for format_iccaformat.
 *
 * Handles all reads, writes, and serving of images in iccaformat_image_library.
 *
 * File storage:
 *   component = format_iccaformat
 *   filearea  = library_image
 *   contextid = system context id (site-wide) or course context id (course-scoped)
 *   itemid    = iccaformat_image_library.id
 *   filename  = original filename
 *
 * Accepted mime types: image/jpeg, image/png, image/webp, image/gif, image/svg+xml
 * SVGs are sanitised on save to strip embedded scripts.
 *
 * @package   format_iccaformat
 * @copyright 2026 ICCA
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace format_iccaformat\local;

defined('MOODLE_INTERNAL') || die();

/**
 * Static helper for the image library.
 */
class image_library {

    /** @var string DB table name. */
    const TABLE = 'iccaformat_image_library';

    /** @var string Moodle file component. */
    const COMPONENT = 'format_iccaformat';

    /** @var string Moodle file area. */
    const FILEAREA = 'library_image';

    /** @var array Accepted mime types. */
    const ACCEPTED_TYPES = [
        'image/jpeg',
        'image/png',
        'image/webp',
        'image/gif',
        'image/svg+xml',
    ];

    // -------------------------------------------------------------------------
    // Reading.
    // -------------------------------------------------------------------------

    /**
     * Get all site-wide library images (system context).
     *
     * @return array of stdClass records from iccaformat_image_library.
     */
    public static function get_site_images(): array {
        global $DB;
        $syscontextid = \context_system::instance()->id;
        return array_values($DB->get_records(
            self::TABLE,
            ['contextid' => $syscontextid],
            'name ASC'
        ));
    }

    /**
     * Get all course-scoped library images.
     *
     * @param  int   $courseid
     * @return array of stdClass records.
     */
    public static function get_course_images(int $courseid): array {
        global $DB;
        $coursecontextid = \context_course::instance($courseid)->id;
        return array_values($DB->get_records(
            self::TABLE,
            ['contextid' => $coursecontextid],
            'name ASC'
        ));
    }

    /**
     * Get all images available for a course — site-wide + course-scoped.
     * Returns them grouped for display in the picker.
     *
     * @param  int   $courseid
     * @return array ['site' => [...], 'course' => [...]]
     */
    public static function get_all_for_course(int $courseid): array {
        return [
            'site'   => self::get_site_images(),
            'course' => self::get_course_images($courseid),
        ];
    }

    /**
     * Get a single library record by id.
     *
     * @param  int        $imageid
     * @return \stdClass|false
     */
    public static function get(int $imageid) {
        global $DB;
        return $DB->get_record(self::TABLE, ['id' => $imageid]);
    }

    /**
     * Count how many element_options rows reference a library image.
     *
     * @param  int $imageid
     * @return int
     */
    public static function count_usages(int $imageid): int {
        global $DB;
        return $DB->count_records_sql(
            'SELECT COUNT(*) FROM {iccaformat_element_options} WHERE '
            . $DB->sql_compare_text('optionname') . ' = ' . $DB->sql_compare_text('?')
            . ' AND ' . $DB->sql_compare_text('optionvalue') . ' = ' . $DB->sql_compare_text('?'),
            [format_option::OPT_IMAGE_ID, (string) $imageid]
        );
    }

    // -------------------------------------------------------------------------
    // Writing.
    // -------------------------------------------------------------------------

    /**
     * Save a new library image from a draft file area.
     *
     * @param  string $name         Display name.
     * @param  string $description  Optional description.
     * @param  int    $draftitemid  Draft file area item id from the form.
     * @param  int    $contextid    System context id (site-wide) or course context id.
     * @param  int    $userid       Uploading user id.
     * @return int    New library record id, or 0 on failure.
     */
    public static function save_new(
        string $name,
        string $description,
        int $draftitemid,
        int $contextid,
        int $userid
    ): int {
        global $DB;

        $fs          = get_file_storage();
        $usercontext = \context_user::instance($userid);

        // Get the file from the draft area.
        $draftfiles = $fs->get_area_files($usercontext->id, 'user', 'draft', $draftitemid, '', false);
        if (empty($draftfiles)) {
            return 0;
        }
        $draftfile = reset($draftfiles);

        // Validate mime type.
        $mimetype = $draftfile->get_mimetype();
        if (!in_array($mimetype, self::ACCEPTED_TYPES)) {
            return 0;
        }

        // Skip if an image with the same name already exists in this context (case-insensitive).
        if ($DB->record_exists_sql(
            'SELECT 1 FROM {' . self::TABLE . '} WHERE contextid = ? AND LOWER(' . $DB->sql_compare_text('name') . ') = LOWER(' . $DB->sql_compare_text('?') . ')',
            [$contextid, $name]
        )) {
            return 0;
        }

        $now = time();

        // Insert the DB record first to get the id (used as itemid in file API).
        $record = (object) [
            'contextid'   => $contextid,
            'name'        => $name,
            'description' => $description,
            'filename'    => $draftfile->get_filename(),
            'userid'      => $userid,
            'timecreated' => $now,
            'timemodified' => $now,
        ];
        $imageid = $DB->insert_record(self::TABLE, $record);

        // Move file from draft to library file area.
        $fileinfo = [
            'contextid' => $contextid,
            'component' => self::COMPONENT,
            'filearea'  => self::FILEAREA,
            'itemid'    => $imageid,
            'filepath'  => '/',
            'filename'  => $draftfile->get_filename(),
        ];

        // For SVGs, sanitise before storing.
        if ($mimetype === 'image/svg+xml') {
            $svgcontent = $draftfile->get_content();
            $svgcontent = self::sanitise_svg($svgcontent);
            $fs->create_file_from_string($fileinfo, $svgcontent);
        } else {
            $fs->create_file_from_storedfile($fileinfo, $draftfile);
        }

        return $imageid;
    }

    /**
     * Update an existing library record's name and description.
     * Does not replace the file — file replacement is a separate upload.
     *
     * @param  int    $imageid
     * @param  string $name
     * @param  string $description
     * @return void
     */
    public static function update_meta(int $imageid, string $name, string $description): void {
        global $DB;
        $DB->update_record(self::TABLE, (object) [
            'id'           => $imageid,
            'name'         => $name,
            'description'  => $description,
            'timemodified' => time(),
        ]);
    }

    /**
     * Replace the file for an existing library record.
     *
     * @param  int    $imageid
     * @param  int    $draftitemid
     * @param  int    $userid
     * @return bool   True on success.
     */
    public static function replace_file(int $imageid, int $draftitemid, int $userid): bool {
        global $DB;

        $record = self::get($imageid);
        if (!$record) {
            return false;
        }

        $fs          = get_file_storage();
        $usercontext = \context_user::instance($userid);
        $draftfiles  = $fs->get_area_files($usercontext->id, 'user', 'draft', $draftitemid, '', false);

        if (empty($draftfiles)) {
            return false;
        }
        $draftfile = reset($draftfiles);
        $mimetype  = $draftfile->get_mimetype();

        if (!in_array($mimetype, self::ACCEPTED_TYPES)) {
            return false;
        }

        // Delete existing file.
        $fs->delete_area_files($record->contextid, self::COMPONENT, self::FILEAREA, $imageid);

        $filename = $draftfile->get_filename();
        $fileinfo = [
            'contextid' => $record->contextid,
            'component' => self::COMPONENT,
            'filearea'  => self::FILEAREA,
            'itemid'    => $imageid,
            'filepath'  => '/',
            'filename'  => $filename,
        ];

        if ($mimetype === 'image/svg+xml') {
            $svgcontent = $draftfile->get_content();
            $fs->create_file_from_string($fileinfo, self::sanitise_svg($svgcontent));
        } else {
            $fs->create_file_from_storedfile($fileinfo, $draftfile);
        }

        $DB->update_record(self::TABLE, (object) [
            'id'           => $imageid,
            'filename'     => $filename,
            'timemodified' => time(),
        ]);

        return true;
    }

    /**
     * Delete a library image and its file.
     * Does not remove element_options references — caller should handle that
     * or warn the user first via count_usages().
     *
     * @param  int  $imageid
     * @return void
     */
    public static function delete(int $imageid): void {
        global $DB;

        $record = self::get($imageid);
        if (!$record) {
            return;
        }

        // Delete the stored file.
        $fs = get_file_storage();
        $fs->delete_area_files($record->contextid, self::COMPONENT, self::FILEAREA, $imageid);

        // Delete the DB record.
        $DB->delete_records(self::TABLE, ['id' => $imageid]);
    }

    // -------------------------------------------------------------------------
    // URL generation.
    // -------------------------------------------------------------------------

    /**
     * Build the pluginfile URL for a library image.
     *
     * @param  \stdClass $record  Library record.
     * @return string    URL string, or empty string if file not found.
     */
    public static function get_url(\stdClass $record): string {
        return \moodle_url::make_pluginfile_url(
            $record->contextid,
            self::COMPONENT,
            self::FILEAREA,
            $record->id,
            '/',
            $record->filename
        )->out(false);
    }

    /**
     * Build a thumbnail URL for the image picker.
     * For raster images this is the same as get_url() — Moodle serves them
     * directly without resizing at this stage.
     *
     * @param  \stdClass $record
     * @return string
     */
    public static function get_thumb_url(\stdClass $record): string {
        return self::get_url($record);
    }

    /**
     * Save a new library image from an already-stored file (e.g. after uploading
     * to element_image first, then copying to library).
     *
     * @param  string       $name
     * @param  string       $description
     * @param  stored_file  $storedfile
     * @param  int          $contextid
     * @param  int          $userid
     * @return int  New library record id, or 0 on failure.
     */
    public static function save_new_from_stored_file(
        string $name,
        string $description,
        \stored_file $storedfile,
        int $contextid,
        int $userid
    ): int {
        global $DB;

        $mimetype = $storedfile->get_mimetype();
        if (!in_array($mimetype, self::ACCEPTED_TYPES)) {
            return 0;
        }

        $now = time();
        $record = (object) [
            'contextid'    => $contextid,
            'name'         => $name,
            'description'  => $description,
            'filename'     => $storedfile->get_filename(),
            'userid'       => $userid,
            'timecreated'  => $now,
            'timemodified' => $now,
        ];
        $imageid = $DB->insert_record(self::TABLE, $record);

        $fs = get_file_storage();
        $fileinfo = [
            'contextid' => $contextid,
            'component' => self::COMPONENT,
            'filearea'  => self::FILEAREA,
            'itemid'    => $imageid,
            'filepath'  => '/',
            'filename'  => $storedfile->get_filename(),
        ];

        if ($mimetype === 'image/svg+xml') {
            $fs->create_file_from_string($fileinfo,
                self::sanitise_svg($storedfile->get_content()));
        } else {
            $fs->create_file_from_storedfile($fileinfo, $storedfile);
        }

        return $imageid;
    }

    /**
     * Get all library images for a specific context.
     *
     * @param  int   $contextid
     * @return array of stdClass records.
     */
    public static function get_all_for_context(int $contextid): array {
        global $DB;
        return array_values($DB->get_records(self::TABLE, ['contextid' => $contextid], 'name ASC'));
    }

    /**
     * Save a new library image from a raw $_FILES upload (no draft area).
     *
     * @param  string $name
     * @param  string $tmpfile   Path to temp file from $_FILES.
     * @param  string $filename  Original filename.
     * @param  int    $contextid
     * @param  int    $userid
     * @return int  New library record id, or 0 on failure.
     */
    public static function save_new_from_raw_upload(
        string $name,
        string $tmpfile,
        string $filename,
        int $contextid,
        int $userid
    ): int {
        global $DB;

        $mimetype = mime_content_type($tmpfile);
        if (!in_array($mimetype, self::ACCEPTED_TYPES)) {
            return 0;
        }

        // Skip if an image with the same name already exists in this context (case-insensitive).
        if ($DB->record_exists_sql(
            'SELECT 1 FROM {' . self::TABLE . '} WHERE contextid = ? AND LOWER(' . $DB->sql_compare_text('name') . ') = LOWER(' . $DB->sql_compare_text('?') . ')',
            [$contextid, $name]
        )) {
            return 0;
        }

        $filename = clean_filename($filename);
        $now      = time();
        $record   = (object)[
            'contextid'    => $contextid,
            'name'         => $name,
            'description'  => '',
            'filename'     => $filename,
            'userid'       => $userid,
            'timecreated'  => $now,
            'timemodified' => $now,
        ];
        $imageid = $DB->insert_record(self::TABLE, $record);

        $fs       = get_file_storage();
        $fileinfo = [
            'contextid' => $contextid,
            'component' => self::COMPONENT,
            'filearea'  => self::FILEAREA,
            'itemid'    => $imageid,
            'filepath'  => '/',
            'filename'  => $filename,
        ];

        if ($mimetype === 'image/svg+xml') {
            $svgcontent = file_get_contents($tmpfile);
            $fs->create_file_from_string($fileinfo, self::sanitise_svg($svgcontent));
        } else {
            $fs->create_file_from_pathname($fileinfo, $tmpfile);
        }

        return $imageid;
    }

    /**
     * Auto-add an uploaded image to the course library.
     *
     * Called automatically whenever an image is saved to element_image —
     * no tutor action required. Creates a library record scoped to the
     * course context so the image is available in the picker for all
     * other cards in this course.
     *
     * If a library record already exists for this exact file in this course
     * (matched by filename and contextid) it is not duplicated.
     *
     * @param  stored_file $storedfile  The file just saved to element_image.
     * @param  int         $contextid  Course context id.
     * @param  string      $name       Display name (defaults to filename without extension).
     * @param  int         $userid     User who uploaded.
     * @return int  Library record id (new or existing).
     */
    public static function auto_add_from_stored_file(
        \stored_file $storedfile,
        int $contextid,
        string $name,
        int $userid
    ): int {
        global $DB;

        $filename = $storedfile->get_filename();
        $mimetype = $storedfile->get_mimetype();

        if (!in_array($mimetype, self::ACCEPTED_TYPES)) {
            return 0;
        }

        // Use filename without extension as default name if not provided.
        if (empty($name)) {
            $name = pathinfo($filename, PATHINFO_FILENAME);
        }

        // Check for existing record with same filename in this context.
        $existing = $DB->get_record(self::TABLE, [
            'contextid' => $contextid,
            'filename'  => $filename,
        ]);

        if ($existing) {
            // Update the file in case it changed.
            $fs = get_file_storage();
            $fs->delete_area_files($contextid, self::COMPONENT, self::FILEAREA, $existing->id);

            $fileinfo = [
                'contextid' => $contextid,
                'component' => self::COMPONENT,
                'filearea'  => self::FILEAREA,
                'itemid'    => $existing->id,
                'filepath'  => '/',
                'filename'  => $filename,
            ];

            if ($mimetype === 'image/svg+xml') {
                $fs->create_file_from_string($fileinfo, self::sanitise_svg($storedfile->get_content()));
            } else {
                $fs->create_file_from_storedfile($fileinfo, $storedfile);
            }

            $DB->update_record(self::TABLE, (object)[
                'id'           => $existing->id,
                'timemodified' => time(),
            ]);

            return $existing->id;
        }

        // New record.
        $now    = time();
        $record = (object)[
            'contextid'    => $contextid,
            'name'         => $name,
            'description'  => '',
            'filename'     => $filename,
            'userid'       => $userid,
            'timecreated'  => $now,
            'timemodified' => $now,
        ];
        $imageid = $DB->insert_record(self::TABLE, $record);

        $fs       = get_file_storage();
        $fileinfo = [
            'contextid' => $contextid,
            'component' => self::COMPONENT,
            'filearea'  => self::FILEAREA,
            'itemid'    => $imageid,
            'filepath'  => '/',
            'filename'  => $filename,
        ];

        if ($mimetype === 'image/svg+xml') {
            $fs->create_file_from_string($fileinfo, self::sanitise_svg($storedfile->get_content()));
        } else {
            $fs->create_file_from_storedfile($fileinfo, $storedfile);
        }

        return $imageid;
    }

    /**
     * Promote a course-scoped library image to the Format library (system context).
     *
     * Copies the file and creates a new system-context library record.
     * The course-scoped record is left intact.
     *
     * @param  int    $imageid   iccaformat_image_library.id of the course image.
     * @param  int    $userid    User performing the promotion.
     * @return int    New Format library record id, or 0 on failure.
     */
    public static function promote_to_format_library(int $imageid, int $userid): int {
        global $DB;

        $record = self::get($imageid);
        if (!$record) {
            return 0;
        }

        $syscontextid = \context_system::instance()->id;

        // Don't duplicate if already in system context.
        if ($record->contextid === $syscontextid) {
            return $imageid;
        }

        // Check if an identical filename already exists in system context.
        $existing = $DB->get_record(self::TABLE, [
            'contextid' => $syscontextid,
            'filename'  => $record->filename,
        ]);
        if ($existing) {
            return $existing->id;
        }

        $fs         = get_file_storage();
        $sourcefile = $fs->get_file(
            $record->contextid, self::COMPONENT, self::FILEAREA,
            $imageid, '/', $record->filename
        );

        if (!$sourcefile) {
            return 0;
        }

        return self::save_new_from_stored_file(
            $record->name,
            $record->description,
            $sourcefile,
            $syscontextid,
            $userid
        );
    }

    // -------------------------------------------------------------------------
    // SVG sanitisation.
    // -------------------------------------------------------------------------

    /**
     * Sanitise SVG content to remove embedded scripts, event handlers,
     * and external resource references that could be security risks.
     *
     * This is a basic but effective sanitiser. It:
     *   - Strips <script> tags and their content.
     *   - Strips on* event handler attributes (onclick, onload, etc.).
     *   - Strips javascript: href and xlink:href values.
     *   - Strips <foreignObject> elements (can embed HTML).
     *   - Strips use[href] references to external resources.
     *
     * @param  string $svgcontent Raw SVG content.
     * @return string Sanitised SVG content.
     */
    public static function sanitise_svg(string $svgcontent): string {
        // Strip script tags.
        $svgcontent = preg_replace('/<script\b[^>]*>.*?<\/script>/is', '', $svgcontent);

        // Strip foreignObject elements.
        $svgcontent = preg_replace('/<foreignObject\b[^>]*>.*?<\/foreignObject>/is', '', $svgcontent);

        // Strip on* event attributes.
        $svgcontent = preg_replace('/\bon\w+\s*=\s*["\'][^"\']*["\']/i', '', $svgcontent);

        // Strip javascript: URIs in href and xlink:href.
        $svgcontent = preg_replace('/\b(xlink:)?href\s*=\s*["\']javascript:[^"\']*["\']/i', '', $svgcontent);

        // Strip external http/https references in xlink:href (use elements pulling external SVG symbols).
        $svgcontent = preg_replace('/\bxlink:href\s*=\s*["\']https?:[^"\']*["\']/i', '', $svgcontent);

        return $svgcontent;
    }
}
