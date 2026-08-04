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

defined('MOODLE_INTERNAL') || die();

require_once($CFG->libdir . '/blocklib.php');

use block_base;
use context_block;
use core_text;
use moodle_exception;
use stdClass;

/**
 * Core business logic for the Topic word cloud block.
 *
 * @package   block_topicwordcloud
 * @copyright 2026 Sami Simpanen
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class manager {
    /** @var string */
    public const ENTRY_TABLE = 'block_topicwordcloud_entry';

    /** @var string */
    public const WORD_TABLE = 'block_topicwordcloud_word';

    /** @var string */
    public const WORD_ORDER_CHRONOLOGICAL = 'chronological';

    /** @var string */
    public const WORD_ORDER_ALPHABETICAL = 'alphabetical';

    /** @var string */
    public const WORD_ORDER_FREQUENCY = 'frequency';

    /** @var array */
    protected const WORD_ORDER_VALUES = [
        self::WORD_ORDER_CHRONOLOGICAL,
        self::WORD_ORDER_ALPHABETICAL,
        self::WORD_ORDER_FREQUENCY,
    ];

    /**
     * Return the merged block configuration.
     *
     * @param block_base $block
     * @return stdClass
     */
    public static function get_config_from_block(block_base $block): stdClass {
        $rawconfig = (array) ($block->config ?? []);
        $config = (object) array_merge(self::get_default_config(), $rawconfig);
        $config->allowmultiple = !empty($config->allowmultiple);
        $config->moderationrequired = !empty($config->moderationrequired);
        $config->showusernames = !empty($config->showusernames);
        $config->maxwordsperuser = max(1, (int) ($config->maxwordsperuser ?? 10));
        $config->opentime = empty($config->opentime) ? 0 : (int) $config->opentime;
        $config->closetime = empty($config->closetime) ? 0 : (int) $config->closetime;
        $config->prompttext = trim((string) $config->prompttext);
        $config->stopwords = trim((string) $config->stopwords);
        $config->wordorder = (string) ($config->wordorder ?? self::WORD_ORDER_CHRONOLOGICAL);
        if (!in_array($config->wordorder, self::WORD_ORDER_VALUES, true)) {
            $config->wordorder = self::WORD_ORDER_CHRONOLOGICAL;
        }
        return $config;
    }

    /**
     * Build the client-side state for the block.
     *
     * @param int $blockinstanceid
     * @param int $userid
     * @return array
     */
    public static function build_state(int $blockinstanceid, int $userid): array {
        global $DB;

        $block = self::get_block_instance($blockinstanceid);
        $config = self::get_config_from_block($block);
        $context = context_block::instance($blockinstanceid);

        $totals = [
            'responses' => (int) $DB->count_records(self::ENTRY_TABLE, ['blockinstanceid' => $blockinstanceid]),
            'responders' => self::count_distinct_users($blockinstanceid),
            'uniquewords' => self::count_distinct_words($blockinstanceid, true),
            'pending' => (int) $DB->count_records(self::WORD_TABLE, [
                'blockinstanceid' => $blockinstanceid,
                'approved' => 0,
            ]),
        ];

        $canmanage = has_capability('block/topicwordcloud:managewords', $context);
        $canviewanalytics = $canmanage || has_capability('block/topicwordcloud:viewanalytics', $context);
        $cansubmit = has_capability('block/topicwordcloud:submitwords', $context);
        $windowopen = self::is_submission_window_open($config);
        $existingentries = (int) $DB->count_records(self::ENTRY_TABLE, [
            'blockinstanceid' => $blockinstanceid,
            'userid' => $userid,
        ]);
        $currentwordcount = (int) $DB->count_records(self::WORD_TABLE, [
            'blockinstanceid' => $blockinstanceid,
            'userid' => $userid,
        ]);
        $remainingwords = max(0, $config->maxwordsperuser - $currentwordcount);
        $acceptingresponses = $cansubmit && $windowopen &&
            ($config->allowmultiple || $existingentries === 0) && $remainingwords > 0;

        return [
            'prompthtml' => format_text(
                $config->prompttext ?: get_string('defaultprompt', 'block_topicwordcloud'),
                FORMAT_PLAIN,
                ['context' => $context]
            ),
            'statusmessage' => self::get_submission_window_label($config),
            'cloudwords' => self::get_cloud_words($blockinstanceid, $config->wordorder),
            'analytics' => $canviewanalytics
                ? self::get_analytics($blockinstanceid, $config->showusernames && $canmanage, $config->wordorder)
                : [],
            'pendingwords' => $canmanage && $config->moderationrequired
                ? self::get_pending_words($blockinstanceid, $config->showusernames, $config->wordorder)
                : [],
            'totals' => $totals,
            'canmanage' => $canmanage,
            'canviewanalytics' => $canviewanalytics,
            'cansubmit' => $cansubmit,
            'acceptingresponses' => $acceptingresponses,
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
     * Submit new words for the current user.
     *
     * @param int $blockinstanceid
     * @param int $userid
     * @param string $rawtext
     * @return array
     */
    public static function submit_words(int $blockinstanceid, int $userid, string $rawtext): array {
        global $DB;

        $block = self::get_block_instance($blockinstanceid);
        $config = self::get_config_from_block($block);
        $context = context_block::instance($blockinstanceid);

        require_capability('block/topicwordcloud:submitwords', $context);

        if (!self::is_submission_window_open($config)) {
            throw new moodle_exception('submissionclosed', 'block_topicwordcloud');
        }

        $tokens = self::tokenize_words($rawtext, $config);
        if (empty($tokens)) {
            throw new moodle_exception('nowordsdetected', 'block_topicwordcloud');
        }

        if (
            !$config->allowmultiple &&
            $DB->record_exists(self::ENTRY_TABLE, [
                'blockinstanceid' => $blockinstanceid,
                'userid' => $userid,
            ])
        ) {
            throw new moodle_exception('multipleanswersdisabled', 'block_topicwordcloud');
        }

        $currentcount = (int) $DB->count_records(self::WORD_TABLE, [
            'blockinstanceid' => $blockinstanceid,
            'userid' => $userid,
        ]);

        if (($currentcount + count($tokens)) > $config->maxwordsperuser) {
            throw new moodle_exception('maxwordsreached', 'block_topicwordcloud', '', $config->maxwordsperuser);
        }

        $course = self::get_course_from_block($blockinstanceid);
        $now = time();
        $entry = (object) [
            'blockinstanceid' => $blockinstanceid,
            'courseid' => $course->id,
            'contextid' => $context->id,
            'userid' => $userid,
            'rawtext' => trim($rawtext),
            'timecreated' => $now,
            'timemodified' => $now,
        ];

        $transaction = $DB->start_delegated_transaction();
        $entry->id = $DB->insert_record(self::ENTRY_TABLE, $entry);

        foreach ($tokens as $token) {
            $wordrecord = (object) [
                'entryid' => $entry->id,
                'blockinstanceid' => $blockinstanceid,
                'courseid' => $course->id,
                'contextid' => $context->id,
                'userid' => $userid,
                'displayword' => $token['display'],
                'normalizedword' => $token['normalized'],
                'approved' => $config->moderationrequired ? 0 : 1,
                'timecreated' => $now,
            ];
            $DB->insert_record(self::WORD_TABLE, $wordrecord);
        }
        $transaction->allow_commit();

        $event = \block_topicwordcloud\event\submission_submitted::create([
            'context' => $context,
            'objectid' => $entry->id,
            'relateduserid' => $userid,
            'other' => [
                'blockinstanceid' => $blockinstanceid,
                'wordcount' => count($tokens),
                'moderationrequired' => $config->moderationrequired ? 1 : 0,
            ],
        ]);
        $event->add_record_snapshot(self::ENTRY_TABLE, $entry);
        $event->trigger();

        $message = $config->moderationrequired
            ? get_string('submissionstoredpending', 'block_topicwordcloud')
            : get_string('submissionsaved', 'block_topicwordcloud');

        return [self::build_state($blockinstanceid, $userid), $message];
    }

    /**
     * Reset all words and entries for a block.
     *
     * @param int $blockinstanceid
     * @return array
     */
    public static function reset_block(int $blockinstanceid): array {
        global $DB, $USER;

        $context = context_block::instance($blockinstanceid);
        require_capability('block/topicwordcloud:managewords', $context);

        $entrycount = (int) $DB->count_records(self::ENTRY_TABLE, ['blockinstanceid' => $blockinstanceid]);
        $wordcount = (int) $DB->count_records(self::WORD_TABLE, ['blockinstanceid' => $blockinstanceid]);

        $transaction = $DB->start_delegated_transaction();
        $DB->delete_records(self::WORD_TABLE, ['blockinstanceid' => $blockinstanceid]);
        $DB->delete_records(self::ENTRY_TABLE, ['blockinstanceid' => $blockinstanceid]);
        $transaction->allow_commit();

        \block_topicwordcloud\event\cloud_reset::create([
            'context' => $context,
            'objectid' => $blockinstanceid,
            'other' => [
                'entrycount' => $entrycount,
                'wordcount' => $wordcount,
            ],
        ])->trigger();

        return [self::build_state($blockinstanceid, $USER->id), get_string('cloudresetdone', 'block_topicwordcloud')];
    }

    /**
     * Delete all occurrences of a word from a block.
     *
     * @param int $blockinstanceid
     * @param string $word
     * @return array
     */
    public static function delete_word(int $blockinstanceid, string $word): array {
        global $DB, $USER;

        $context = context_block::instance($blockinstanceid);
        require_capability('block/topicwordcloud:managewords', $context);

        $normalizedword = self::normalize_word($word);
        if ($normalizedword === '') {
            throw new moodle_exception('invalidword', 'block_topicwordcloud');
        }

        $wordcount = (int) $DB->count_records(self::WORD_TABLE, [
            'blockinstanceid' => $blockinstanceid,
            'normalizedword' => $normalizedword,
        ]);

        $DB->delete_records(self::WORD_TABLE, [
            'blockinstanceid' => $blockinstanceid,
            'normalizedword' => $normalizedword,
        ]);

        \block_topicwordcloud\event\word_deleted::create([
            'context' => $context,
            'objectid' => $blockinstanceid,
            'other' => [
                'word' => $normalizedword,
                'wordcount' => $wordcount,
            ],
        ])->trigger();

        return [self::build_state($blockinstanceid, $USER->id), get_string('worddeleted', 'block_topicwordcloud', $normalizedword)];
    }

    /**
     * Approve all pending occurrences of a word.
     *
     * @param int $blockinstanceid
     * @param string $word
     * @return array
     */
    public static function approve_word(int $blockinstanceid, string $word): array {
        global $DB, $USER;

        $context = context_block::instance($blockinstanceid);
        require_capability('block/topicwordcloud:managewords', $context);

        $normalizedword = self::normalize_word($word);
        if ($normalizedword === '') {
            throw new moodle_exception('invalidword', 'block_topicwordcloud');
        }

        $wordcount = (int) $DB->count_records(self::WORD_TABLE, [
            'blockinstanceid' => $blockinstanceid,
            'normalizedword' => $normalizedword,
            'approved' => 0,
        ]);

        $DB->set_field(self::WORD_TABLE, 'approved', 1, [
            'blockinstanceid' => $blockinstanceid,
            'normalizedword' => $normalizedword,
            'approved' => 0,
        ]);

        \block_topicwordcloud\event\word_approved::create([
            'context' => $context,
            'objectid' => $blockinstanceid,
            'other' => [
                'word' => $normalizedword,
                'wordcount' => $wordcount,
            ],
        ])->trigger();

        return [
            self::build_state($blockinstanceid, $USER->id),
            get_string('wordapproved', 'block_topicwordcloud', $normalizedword),
        ];
    }

    /**
     * Get a user facing submission window message.
     *
     * @param stdClass $config
     * @return string
     */
    public static function get_submission_window_label(stdClass $config): string {
        $now = time();

        if (!empty($config->opentime) && $now < $config->opentime) {
            return get_string('opensat', 'block_topicwordcloud', userdate($config->opentime));
        }

        if (!empty($config->closetime) && $now > $config->closetime) {
            return get_string('closedat', 'block_topicwordcloud', userdate($config->closetime));
        }

        if (!empty($config->closetime)) {
            return get_string('openuntil', 'block_topicwordcloud', userdate($config->closetime));
        }

        return get_string('opennow', 'block_topicwordcloud');
    }

    /**
     * Determine whether the submission window is open.
     *
     * @param stdClass $config
     * @return bool
     */
    public static function is_submission_window_open(stdClass $config): bool {
        $now = time();
        if (!empty($config->opentime) && $now < $config->opentime) {
            return false;
        }
        if (!empty($config->closetime) && $now > $config->closetime) {
            return false;
        }
        return true;
    }

    /**
     * Resolve a block instance into a block object.
     *
     * @param int $blockinstanceid
     * @return block_base
     */
    public static function get_block_instance(int $blockinstanceid): block_base {
        global $DB;

        $blockrecord = $DB->get_record('block_instances', [
            'id' => $blockinstanceid,
            'blockname' => 'topicwordcloud',
        ], '*', MUST_EXIST);

        $block = block_instance($blockrecord->blockname, $blockrecord);
        if (!$block) {
            throw new moodle_exception('invalidblockinstance', 'block_topicwordcloud');
        }

        return $block;
    }

    /**
     * Resolve the enclosing course for the block.
     *
     * @param int $blockinstanceid
     * @return stdClass
     */
    public static function get_course_from_block(int $blockinstanceid): stdClass {
        $context = context_block::instance($blockinstanceid);
        $coursecontext = $context->get_course_context(true);
        return get_course($coursecontext->instanceid);
    }

    /**
     * Return configured defaults.
     *
     * @return array
     */
    protected static function get_default_config(): array {
        return [
            'prompttext' => '',
            'allowmultiple' => 1,
            'maxwordsperuser' => 10,
            'opentime' => 0,
            'closetime' => 0,
            'moderationrequired' => 0,
            'showusernames' => 0,
            'stopwords' => '',
            'wordorder' => self::WORD_ORDER_CHRONOLOGICAL,
        ];
    }

    /**
     * Turn free text into an array of words.
     *
     * @param string $rawtext
     * @param stdClass $config
     * @return array
     */
    protected static function tokenize_words(string $rawtext, stdClass $config): array {
        $text = strip_tags($rawtext);
        $text = preg_replace('/[^\p{L}\p{N}\-\s]+/u', ' ', $text);
        $parts = preg_split('/[\s,;]+/u', $text, -1, PREG_SPLIT_NO_EMPTY);
        $stopwords = self::get_stopwords($config);
        $tokens = [];

        foreach ($parts as $part) {
            $normalized = self::normalize_word($part);
            if ($normalized === '' || isset($stopwords[$normalized])) {
                continue;
            }

            $display = trim($part);
            $display = preg_replace('/\s+/u', ' ', $display);
            $display = mb_substr($display, 0, 100);

            $tokens[] = [
                'display' => $display,
                'normalized' => $normalized,
            ];
        }

        return $tokens;
    }

    /**
     * Normalise a single word.
     *
     * @param string $word
     * @return string
     */
    protected static function normalize_word(string $word): string {
        $normalized = trim($word);
        $normalized = preg_replace('/^[^\p{L}\p{N}]+|[^\p{L}\p{N}]+$/u', '', $normalized);
        $normalized = core_text::strtolower($normalized);
        $normalized = preg_replace('/\s+/u', ' ', $normalized);
        $normalized = mb_substr($normalized, 0, 100);
        return $normalized;
    }

    /**
     * Return built-in and custom stop words.
     *
     * @param stdClass $config
     * @return array
     */
    protected static function get_stopwords(stdClass $config): array {
        $defaults = [
            'a', 'an', 'and', 'are', 'at', 'for', 'from', 'in', 'is', 'it', 'of', 'on', 'or', 'that',
            'the', 'this', 'to', 'with', 'ei', 'ja', 'jos', 'kuin', 'kun', 'myös', 'ne', 'niin',
            'oli', 'olla', 'on', 'ovat', 'se', 'tai', 'vain', 'että',
        ];
        $custom = preg_split('/[\s,;]+/u', (string) $config->stopwords, -1, PREG_SPLIT_NO_EMPTY);
        $all = array_merge($defaults, $custom);
        $indexed = [];
        foreach ($all as $word) {
            $normalized = self::normalize_word($word);
            if ($normalized !== '') {
                $indexed[$normalized] = true;
            }
        }
        return $indexed;
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
                  FROM {" . self::WORD_TABLE . "}
                 WHERE blockinstanceid = :blockinstanceid
                   AND approved = 1
              GROUP BY normalizedword
              ORDER BY $order";
        $records = $DB->get_records_sql($sql, ['blockinstanceid' => $blockinstanceid], 0, 40);
        if (empty($records)) {
            return [];
        }

        $maxfrequency = max(array_map(static function ($record) {
            return (int) $record->frequency;
        }, $records));
        $result = [];
        $index = 0;

        foreach ($records as $record) {
            $count = (int) $record->frequency;
            $scale = $maxfrequency > 1 ? (($count - 1) / ($maxfrequency - 1)) : 1;
            $size = 18 + (int) round($scale * 28);
            $result[] = [
                'word' => $record->displayword ?: $record->normalizedword,
                'normalizedword' => $record->normalizedword,
                'count' => $count,
                'size' => $size,
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
        global $DB;

        $order = self::get_word_order_sql($wordorder);
        $sql = "SELECT normalizedword, MIN(displayword) AS displayword, COUNT(*) AS frequency, COUNT(DISTINCT userid) AS usercount,
                       MIN(timecreated) AS firstcreated, MIN(id) AS firstid
                  FROM {" . self::WORD_TABLE . "}
                 WHERE blockinstanceid = :blockinstanceid
                   AND approved = 1
              GROUP BY normalizedword
              ORDER BY $order";
        $records = $DB->get_records_sql($sql, ['blockinstanceid' => $blockinstanceid], 0, 100);
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
            $rows = self::attach_usernames_to_rows($rows, $blockinstanceid, $normalizedwords, true);
        }

        return array_values($rows);
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
        global $DB;

        $order = self::get_word_order_sql($wordorder);
        $sql = "SELECT normalizedword, MIN(displayword) AS displayword, COUNT(*) AS frequency, COUNT(DISTINCT userid) AS usercount,
                       MIN(timecreated) AS firstcreated, MIN(id) AS firstid
                  FROM {" . self::WORD_TABLE . "}
                 WHERE blockinstanceid = :blockinstanceid
                   AND approved = 0
              GROUP BY normalizedword
              ORDER BY $order";
        $records = $DB->get_records_sql($sql, ['blockinstanceid' => $blockinstanceid]);
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
            $rows = self::attach_usernames_to_rows($rows, $blockinstanceid, $normalizedwords, false);
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
            case self::WORD_ORDER_ALPHABETICAL:
                return 'normalizedword ASC';
            case self::WORD_ORDER_FREQUENCY:
                return 'frequency DESC, normalizedword ASC';
            case self::WORD_ORDER_CHRONOLOGICAL:
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
                  FROM {" . self::WORD_TABLE . "} w
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
                  FROM {" . self::ENTRY_TABLE . "}
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
                  FROM {" . self::WORD_TABLE . "}
                 WHERE blockinstanceid = :blockinstanceid
                   AND approved = :approved";
        return (int) $DB->count_records_sql($sql, [
            'blockinstanceid' => $blockinstanceid,
            'approved' => $approved ? 1 : 0,
        ]);
    }
}
