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

namespace block_topicwordcloud\local;


use context_block;
use stdClass;

/**
 * Builds the client-side state for the Topic word cloud block.
 *
 * @package   block_topicwordcloud
 * @copyright 2026 Sami Simpanen
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class state_builder {
    /**
     * Build the client-side state for the block.
     *
     * @param int $blockinstanceid
     * @param int $userid
     * @return array
     */
    public static function build(int $blockinstanceid, int $userid): array {
        global $DB;

        $block = manager::get_block_instance($blockinstanceid);
        $config = manager::get_config_from_block($block);
        $context = context_block::instance($blockinstanceid);
        $canmanage = has_capability('block/topicwordcloud:managewords', $context);
        $canviewanalytics = $canmanage || has_capability('block/topicwordcloud:viewanalytics', $context);
        $cansubmit = has_capability('block/topicwordcloud:submitwords', $context);
        $existingentries = (int) $DB->count_records(manager::ENTRY_TABLE, [
            'blockinstanceid' => $blockinstanceid,
            'userid' => $userid,
        ]);
        $currentwordcount = (int) $DB->count_records(manager::WORD_TABLE, [
            'blockinstanceid' => $blockinstanceid,
            'userid' => $userid,
        ]);
        $remainingwords = max(0, $config->maxwordsperuser - $currentwordcount);

        return [
            'prompthtml' => format_text(
                $config->prompttext ?: get_string('defaultprompt', 'block_topicwordcloud'),
                FORMAT_PLAIN,
                ['context' => $context]
            ),
            'statusmessage' => manager::get_submission_window_label($config),
            'cloudwords' => self::get_cloud_words($blockinstanceid, $config->wordorder),
            'analytics' => self::get_analytics_data($blockinstanceid, $config, $canmanage, $canviewanalytics),
            'pendingwords' => self::get_pending_words_data($blockinstanceid, $config, $canmanage),
            'totals' => self::get_totals($blockinstanceid),
            'canmanage' => $canmanage,
            'canviewanalytics' => $canviewanalytics,
            'cansubmit' => $cansubmit,
            'acceptingresponses' => self::is_accepting_responses($config, $cansubmit, $existingentries, $remainingwords),
            'showusernames' => $config->showusernames && $canmanage,
            'moderationrequired' => $config->moderationrequired,
            'remainingwords' => $remainingwords,
            'limits' => [
                'allowmultiple' => $config->allowmultiple,
                'maxwordsperuser' => $config->maxwordsperuser,
            ],
        ];
    }

    /**
     * Return aggregate totals for the block.
     *
     * @param int $blockinstanceid
     * @return array
     */
    protected static function get_totals(int $blockinstanceid): array {
        global $DB;

        return [
            'responses' => (int) $DB->count_records(manager::ENTRY_TABLE, ['blockinstanceid' => $blockinstanceid]),
            'responders' => self::count_distinct_users($blockinstanceid),
            'uniquewords' => self::count_distinct_words($blockinstanceid, true),
            'pending' => (int) $DB->count_records(manager::WORD_TABLE, [
                'blockinstanceid' => $blockinstanceid,
                'approved' => 0,
            ]),
        ];
    }

    /**
     * Determine whether the user can currently submit responses.
     *
     * @param stdClass $config
     * @param bool $cansubmit
     * @param int $existingentries
     * @param int $remainingwords
     * @return bool
     */
    protected static function is_accepting_responses(
        stdClass $config,
        bool $cansubmit,
        int $existingentries,
        int $remainingwords
    ): bool {
        return $cansubmit && manager::is_submission_window_open($config) &&
            ($config->allowmultiple || $existingentries === 0) && $remainingwords > 0;
    }

    /**
     * Return analytics data when the user may see it.
     *
     * @param int $blockinstanceid
     * @param stdClass $config
     * @param bool $canmanage
     * @param bool $canviewanalytics
     * @return array
     */
    protected static function get_analytics_data(
        int $blockinstanceid,
        stdClass $config,
        bool $canmanage,
        bool $canviewanalytics
    ): array {
        if (!$canviewanalytics) {
            return [];
        }

        return self::get_analytics($blockinstanceid, $config->showusernames && $canmanage, $config->wordorder);
    }

    /**
     * Return pending moderation data when the user may manage it.
     *
     * @param int $blockinstanceid
     * @param stdClass $config
     * @param bool $canmanage
     * @return array
     */
    protected static function get_pending_words_data(int $blockinstanceid, stdClass $config, bool $canmanage): array {
        if (!$canmanage || !$config->moderationrequired) {
            return [];
        }

        return self::get_pending_words($blockinstanceid, $config->showusernames, $config->wordorder);
    }

    /**
     * Get cloud words with calculated sizes.
     *
     * @param int $blockinstanceid
     * @param string $wordorder
     * @return array
     */
    protected static function get_cloud_words(int $blockinstanceid, string $wordorder): array {
        global $DB;

        $order = self::get_word_order_sql($wordorder);
        $sql = "SELECT normalizedword, MIN(displayword) AS displayword, COUNT(*) AS frequency,
                       MIN(timecreated) AS firstcreated, MIN(id) AS firstid
                  FROM {" . manager::WORD_TABLE . "}
                 WHERE blockinstanceid = :blockinstanceid
                   AND approved = 1
              GROUP BY normalizedword
              ORDER BY $order";
        $records = $DB->get_records_sql($sql, ['blockinstanceid' => $blockinstanceid], 0, 40);
        if (empty($records)) {
            return [];
        }

        return self::format_cloud_words($records);
    }

    /**
     * Format cloud rows for display.
     *
     * @param array $records
     * @return array
     */
    protected static function format_cloud_words(array $records): array {
        $maxfrequency = max(array_map(static function ($record) {
            return (int) $record->frequency;
        }, $records));
        $result = [];
        $index = 0;

        foreach ($records as $record) {
            $count = (int) $record->frequency;
            $scale = $maxfrequency > 1 ? (($count - 1) / ($maxfrequency - 1)) : 1;
            $result[] = [
                'word' => $record->displayword ?: $record->normalizedword,
                'normalizedword' => $record->normalizedword,
                'count' => $count,
                'size' => 18 + (int) round($scale * 28),
                'colorindex' => $index % 6,
            ];
            $index++;
        }

        return $result;
    }

    /**
     * Get aggregated analytics for approved words.
     *
     * @param int $blockinstanceid
     * @param bool $showusernames
     * @param string $wordorder
     * @return array
     */
    protected static function get_analytics(int $blockinstanceid, bool $showusernames, string $wordorder): array {
        $records = self::get_aggregated_word_records($blockinstanceid, true, $wordorder, 100);
        return self::format_word_rows($records, $blockinstanceid, $showusernames, true);
    }

    /**
     * Get pending words for moderation.
     *
     * @param int $blockinstanceid
     * @param bool $showusernames
     * @param string $wordorder
     * @return array
     */
    protected static function get_pending_words(int $blockinstanceid, bool $showusernames, string $wordorder): array {
        $records = self::get_aggregated_word_records($blockinstanceid, false, $wordorder);
        return self::format_word_rows($records, $blockinstanceid, $showusernames, false);
    }

    /**
     * Get aggregated word records.
     *
     * @param int $blockinstanceid
     * @param bool $approved
     * @param string $wordorder
     * @param int|null $limit
     * @return array
     */
    protected static function get_aggregated_word_records(
        int $blockinstanceid,
        bool $approved,
        string $wordorder,
        ?int $limit = null
    ): array {
        global $DB;

        $order = self::get_word_order_sql($wordorder);
        $sql = "SELECT normalizedword, MIN(displayword) AS displayword, COUNT(*) AS frequency, COUNT(DISTINCT userid) AS usercount,
                       MIN(timecreated) AS firstcreated, MIN(id) AS firstid
                  FROM {" . manager::WORD_TABLE . "}
                 WHERE blockinstanceid = :blockinstanceid
                   AND approved = :approved
              GROUP BY normalizedword
              ORDER BY $order";
        $params = [
            'blockinstanceid' => $blockinstanceid,
            'approved' => $approved ? 1 : 0,
        ];

        if ($limit === null) {
            return $DB->get_records_sql($sql, $params);
        }

        return $DB->get_records_sql($sql, $params, 0, $limit);
    }

    /**
     * Format aggregated word records.
     *
     * @param array $records
     * @param int $blockinstanceid
     * @param bool $showusernames
     * @param bool $approved
     * @return array
     */
    protected static function format_word_rows(
        array $records,
        int $blockinstanceid,
        bool $showusernames,
        bool $approved
    ): array {
        if (empty($records)) {
            return [];
        }

        $rows = [];
        $normalizedwords = [];

        foreach ($records as $record) {
            $normalizedwords[] = $record->normalizedword;
            $rows[$record->normalizedword] = [
                'word' => $record->displayword ?: $record->normalizedword,
                'normalizedword' => $record->normalizedword,
                'count' => (int) $record->frequency,
                'usercount' => (int) $record->usercount,
                'usernames' => [],
            ];
        }

        if ($showusernames && !empty($normalizedwords)) {
            $rows = self::attach_usernames_to_rows($rows, $blockinstanceid, $normalizedwords, $approved);
        }

        return array_values($rows);
    }

    /**
     * Return SQL ordering for aggregated word rows.
     *
     * @param string $wordorder
     * @return string
     */
    protected static function get_word_order_sql(string $wordorder): string {
        switch ($wordorder) {
            case manager::WORD_ORDER_ALPHABETICAL:
                return 'normalizedword ASC';
            case manager::WORD_ORDER_FREQUENCY:
                return 'frequency DESC, normalizedword ASC';
            case manager::WORD_ORDER_CHRONOLOGICAL:
            default:
                return 'firstcreated ASC, firstid ASC, normalizedword ASC';
        }
    }

    /**
     * Add usernames to analytics rows.
     *
     * @param array $rows
     * @param int $blockinstanceid
     * @param array $normalizedwords
     * @param bool $approved
     * @return array
     */
    protected static function attach_usernames_to_rows(
        array $rows,
        int $blockinstanceid,
        array $normalizedwords,
        bool $approved
    ): array {
        global $DB;

        [$insql, $params] = $DB->get_in_or_equal($normalizedwords, SQL_PARAMS_NAMED, 'word');
        $params['blockinstanceid'] = $blockinstanceid;
        $params['approved'] = $approved ? 1 : 0;

        $sql = "SELECT w.normalizedword, u.id, u.firstname, u.lastname, u.middlename, u.alternatename,
                       u.firstnamephonetic, u.lastnamephonetic
                  FROM {" . manager::WORD_TABLE . "} w
                  JOIN {user} u
                    ON u.id = w.userid
                 WHERE w.blockinstanceid = :blockinstanceid
                   AND w.approved = :approved
                   AND w.normalizedword $insql
              ORDER BY w.normalizedword ASC, u.lastname ASC, u.firstname ASC";

        $seen = [];
        $records = $DB->get_records_sql($sql, $params);
        foreach ($records as $record) {
            $key = $record->normalizedword . ':' . $record->id;
            if (isset($seen[$key]) || !isset($rows[$record->normalizedword])) {
                continue;
            }
            $seen[$key] = true;
            $rows[$record->normalizedword]['usernames'][] = fullname($record);
        }

        return $rows;
    }

    /**
     * Count unique responders.
     *
     * @param int $blockinstanceid
     * @return int
     */
    protected static function count_distinct_users(int $blockinstanceid): int {
        global $DB;

        $sql = "SELECT COUNT(DISTINCT userid)
                  FROM {" . manager::ENTRY_TABLE . "}
                 WHERE blockinstanceid = :blockinstanceid";
        return (int) $DB->count_records_sql($sql, ['blockinstanceid' => $blockinstanceid]);
    }

    /**
     * Count unique words.
     *
     * @param int $blockinstanceid
     * @param bool $approved
     * @return int
     */
    protected static function count_distinct_words(int $blockinstanceid, bool $approved): int {
        global $DB;

        $sql = "SELECT COUNT(DISTINCT normalizedword)
                  FROM {" . manager::WORD_TABLE . "}
                 WHERE blockinstanceid = :blockinstanceid
                   AND approved = :approved";
        return (int) $DB->count_records_sql($sql, [
            'blockinstanceid' => $blockinstanceid,
            'approved' => $approved ? 1 : 0,
        ]);
    }
}
