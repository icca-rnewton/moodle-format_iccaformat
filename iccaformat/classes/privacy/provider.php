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
 * Privacy provider for format_iccaformat.
 *
 * This plugin stores:
 *   - iccaformat_image_library: userid on images uploaded by a user.
 *   - iccaformat_element_options: no personal data (course/section/cm config only).
 *   - iccaformat_label_rules: no personal data (mod-type config only).
 *
 * @package   format_iccaformat
 * @copyright 2026 Inns of Court College of Advocacy (Part of COIC)
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace format_iccaformat\privacy;

use core_privacy\local\metadata\collection;
use core_privacy\local\request\approved_contextlist;
use core_privacy\local\request\approved_userlist;
use core_privacy\local\request\contextlist;
use core_privacy\local\request\userlist;
use core_privacy\local\request\writer;

defined('MOODLE_INTERNAL') || die();

/**
 * Privacy provider class for ICCA Format.
 */
class provider implements
    \core_privacy\local\metadata\provider,
    \core_privacy\local\request\plugin\provider,
    \core_privacy\local\request\core_userlist_provider {

    /**
     * Describe the personal data stored by this plugin.
     */
    public static function get_metadata(collection $collection): collection {
        $collection->add_database_table(
            'iccaformat_image_library',
            [
                'userid'      => 'privacy:metadata:iccaformat_image_library:userid',
                'name'        => 'privacy:metadata:iccaformat_image_library:name',
                'timecreated' => 'privacy:metadata:iccaformat_image_library:timecreated',
            ],
            'privacy:metadata:iccaformat_image_library'
        );
        return $collection;
    }

    /**
     * Get the list of contexts that contain personal data for the given user.
     */
    public static function get_contexts_for_userid(int $userid): contextlist {
        $contextlist = new contextlist();
        $contextlist->add_from_sql(
            'SELECT DISTINCT il.contextid
               FROM {iccaformat_image_library} il
              WHERE il.userid = :userid',
            ['userid' => $userid]
        );
        return $contextlist;
    }

    /**
     * Get the list of users who have data within a context.
     */
    public static function get_users_in_context(userlist $userlist): void {
        $context = $userlist->get_context();
        $userlist->add_from_sql(
            'userid',
            'SELECT userid FROM {iccaformat_image_library} WHERE contextid = :contextid AND userid > 0',
            ['contextid' => $context->id]
        );
    }

    /**
     * Export personal data for a user in the given contexts.
     */
    public static function export_user_data(approved_contextlist $contextlist): void {
        global $DB;
        $userid = $contextlist->get_user()->id;
        foreach ($contextlist->get_contexts() as $context) {
            $images = $DB->get_records('iccaformat_image_library', [
                'userid'    => $userid,
                'contextid' => $context->id,
            ]);
            if (!empty($images)) {
                $data = array_map(function($img) {
                    return (object)[
                        'name'        => $img->name,
                        'filename'    => $img->filename,
                        'timecreated' => \core_privacy\local\request\transform::datetime($img->timecreated),
                    ];
                }, $images);
                writer::with_context($context)->export_data(
                    [get_string('pluginname', 'format_iccaformat'), get_string('privacy:path:images', 'format_iccaformat')],
                    (object)['images' => array_values($data)]
                );
            }
        }
    }

    /*
     * Erasure: why images are kept and the uploader is removed.
     *
     * A library image is course (or site) content: other cards and Card Styling Rules use it,
     * and deleting it would strip images from courses the user may no longer even teach on.
     * The only personal data in this table is who uploaded the image (userid). Erasure
     * therefore removes that attribution (userid = 0) and keeps the image. This is a
     * deliberate judgement call, recorded here so a reviewer can see the reasoning.
     */

    /**
     * Remove uploader attribution for everyone in a context. Images are kept.
     *
     * @param \context $context
     * @return void
     */
    public static function delete_data_for_all_users_in_context(\context $context): void {
        global $DB;
        $DB->set_field('iccaformat_image_library', 'userid', 0, ['contextid' => $context->id]);
    }

    /**
     * Remove one user's uploader attribution in the approved contexts. Images are kept.
     *
     * @param approved_contextlist $contextlist
     * @return void
     */
    public static function delete_data_for_user(approved_contextlist $contextlist): void {
        global $DB;
        $userid = $contextlist->get_user()->id;
        foreach ($contextlist->get_contexts() as $context) {
            $DB->set_field('iccaformat_image_library', 'userid', 0, [
                'userid'    => $userid,
                'contextid' => $context->id,
            ]);
        }
    }

    /**
     * Remove uploader attribution for a list of users in a context. Images are kept.
     *
     * @param approved_userlist $userlist
     * @return void
     */
    public static function delete_data_for_users(approved_userlist $userlist): void {
        global $DB;
        $context = $userlist->get_context();
        $userids = $userlist->get_userids();
        if (empty($userids)) {
            return;
        }
        [$insql, $inparams] = $DB->get_in_or_equal($userids, SQL_PARAMS_NAMED);
        $inparams['contextid'] = $context->id;
        $DB->set_field_select('iccaformat_image_library', 'userid', 0,
            "contextid = :contextid AND userid $insql", $inparams);
    }
}