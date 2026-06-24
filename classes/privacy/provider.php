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

namespace block_topicwordcloud\privacy;

defined('MOODLE_INTERNAL') || die();

use block_topicwordcloud\local\manager;
use context;
use context_course;
use core_privacy\local\metadata\collection;
use core_privacy\local\metadata\provider as metadata_provider;
use core_privacy\local\request\approved_contextlist;
use core_privacy\local\request\approved_userlist;
use core_privacy\local\request\contextlist;
use core_privacy\local\request\plugin\provider as plugin_provider;
use core_privacy\local\request\transform;
use core_privacy\local\request\userlist;
use core_privacy\local\request\writer;

/**
 * Privacy provider for block_topicwordcloud.
 *
 * @package   block_topicwordcloud
 * @copyright 2026 Sami Simpanen
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class provider implements metadata_provider, plugin_provider, \core_privacy\local\request\core_userlist_provider {
    /**
     * Describe stored metadata.
     *
     * @param collection $collection
     * @return collection
     */
    public static function get_metadata(collection $collection): collection {
        $collection->add_database_table(manager::ENTRY_TABLE, [
            'blockinstanceid' => 'privacy:metadata:blockinstanceid',
            'courseid' => 'privacy:metadata:courseid',
            'contextid' => 'privacy:metadata:contextid',
            'userid' => 'privacy:metadata:userid',
            'rawtext' => 'privacy:metadata:rawtext',
            'timecreated' => 'privacy:metadata:timecreated',
            'timemodified' => 'privacy:metadata:timemodified',
        ], 'privacy:metadata:entries');

        $collection->add_database_table(manager::WORD_TABLE, [
            'entryid' => 'privacy:metadata:entryid',
            'blockinstanceid' => 'privacy:metadata:blockinstanceid',
            'courseid' => 'privacy:metadata:courseid',
            'contextid' => 'privacy:metadata:contextid',
            'userid' => 'privacy:metadata:userid',
            'displayword' => 'privacy:metadata:displayword',
            'normalizedword' => 'privacy:metadata:normalizedword',
            'approved' => 'privacy:metadata:approved',
            'timecreated' => 'privacy:metadata:timecreated',
        ], 'privacy:metadata:words');

        return $collection;
    }

    /**
     * Find contexts containing data for a user.
     *
     * @param int $userid
     * @return contextlist
     */
    public static function get_contexts_for_userid(int $userid): contextlist {
        $contextlist = new contextlist();
        $sql = "SELECT DISTINCT c.id
                  FROM {context} c
                  JOIN {" . manager::WORD_TABLE . "} w
                    ON w.courseid = c.instanceid
                 WHERE c.contextlevel = :contextlevel
                   AND w.userid = :userid";
        $contextlist->add_from_sql($sql, [
            'contextlevel' => CONTEXT_COURSE,
            'userid' => $userid,
        ]);
        return $contextlist;
    }

    /**
     * Export user data.
     *
     * @param approved_contextlist $contextlist
     * @return void
     */
    public static function export_user_data(approved_contextlist $contextlist): void {
        global $DB;

        if (empty($contextlist->count())) {
            return;
        }

        $userid = $contextlist->get_user()->id;

        foreach ($contextlist->get_contexts() as $context) {
            if (!$context instanceof context_course) {
                continue;
            }

            $entries = $DB->get_records(manager::ENTRY_TABLE, [
                'courseid' => $context->instanceid,
                'userid' => $userid,
            ], 'timecreated ASC');

            if (empty($entries)) {
                continue;
            }

            $export = [];
            foreach ($entries as $entry) {
                $words = $DB->get_records(manager::WORD_TABLE, ['entryid' => $entry->id], 'timecreated ASC');
                $exportwords = [];
                foreach ($words as $word) {
                    $exportwords[] = (object) [
                        'displayword' => $word->displayword,
                        'normalizedword' => $word->normalizedword,
                        'approved' => (bool) $word->approved,
                        'timecreated' => transform::datetime($word->timecreated),
                    ];
                }

                $export[] = (object) [
                    'blockinstanceid' => $entry->blockinstanceid,
                    'rawtext' => $entry->rawtext,
                    'timecreated' => transform::datetime($entry->timecreated),
                    'words' => $exportwords,
                ];
            }

            writer::with_context($context)->export_data(
                [get_string('pluginname', 'block_topicwordcloud')],
                (object) ['entries' => $export]
            );
        }
    }

    /**
     * Delete all users' data in a context.
     *
     * @param context $context
     * @return void
     */
    public static function delete_data_for_all_users_in_context(context $context): void {
        global $DB;

        if (!$context instanceof context_course) {
            return;
        }

        $DB->delete_records(manager::WORD_TABLE, ['courseid' => $context->instanceid]);
        $DB->delete_records(manager::ENTRY_TABLE, ['courseid' => $context->instanceid]);
    }

    /**
     * Delete data for one user in approved contexts.
     *
     * @param approved_contextlist $contextlist
     * @return void
     */
    public static function delete_data_for_user(approved_contextlist $contextlist): void {
        global $DB;

        $userid = $contextlist->get_user()->id;

        foreach ($contextlist->get_contexts() as $context) {
            if (!$context instanceof context_course) {
                continue;
            }
            $DB->delete_records(manager::WORD_TABLE, [
                'courseid' => $context->instanceid,
                'userid' => $userid,
            ]);
            $DB->delete_records(manager::ENTRY_TABLE, [
                'courseid' => $context->instanceid,
                'userid' => $userid,
            ]);
        }
    }

    /**
     * Add users present in a context to the privacy userlist.
     *
     * @param userlist $userlist
     * @return void
     */
    public static function get_users_in_context(userlist $userlist): void {
        global $DB;

        $context = $userlist->get_context();
        if (!$context instanceof context_course) {
            return;
        }

        $sql = "SELECT userid
                  FROM {" . manager::WORD_TABLE . "}
                 WHERE courseid = :courseid";
        $userlist->add_from_sql('userid', $sql, ['courseid' => $context->instanceid]);
    }

    /**
     * Delete data for multiple users in a context.
     *
     * @param approved_userlist $userlist
     * @return void
     */
    public static function delete_data_for_users(approved_userlist $userlist): void {
        global $DB;

        $context = $userlist->get_context();
        if (!$context instanceof context_course) {
            return;
        }

        $userids = $userlist->get_userids();
        if (empty($userids)) {
            return;
        }

        [$insql, $params] = $DB->get_in_or_equal($userids, SQL_PARAMS_NAMED);
        $params['courseid'] = $context->instanceid;

        $DB->delete_records_select(manager::WORD_TABLE, "courseid = :courseid AND userid $insql", $params);
        $DB->delete_records_select(manager::ENTRY_TABLE, "courseid = :courseid AND userid $insql", $params);
    }
}