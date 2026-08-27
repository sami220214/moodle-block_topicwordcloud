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
use core_text;
use moodle_exception;
use stdClass;

/**
 * Handles word submissions for the Topic word cloud block.
 *
 * @package   block_topicwordcloud
 * @copyright 2026 Sami Simpanen
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class word_service {
    /**
     * Submit new words for the current user.
     *
     * @param int $blockinstanceid
     * @param int $userid
     * @param string $rawtext
     * @return array
     */
    public static function submit(int $blockinstanceid, int $userid, string $rawtext): array {
        global $DB;

        $block = manager::get_block_instance($blockinstanceid);
        $config = manager::get_config_from_block($block);
        $context = context_block::instance($blockinstanceid);
        $tokens = self::get_valid_submission_tokens($blockinstanceid, $userid, $rawtext, $config, $context);
        $course = manager::get_course_from_block($blockinstanceid);
        $now = time();
        $entry = self::create_entry($blockinstanceid, $userid, $rawtext, $course->id, $context->id, $now);

        $transaction = $DB->start_delegated_transaction();
        $entry->id = $DB->insert_record(manager::ENTRY_TABLE, $entry);
        self::insert_words($entry, $tokens, $config, $course->id, $context->id, $now);
        $transaction->allow_commit();

        self::trigger_submission_event($entry, $context, $blockinstanceid, $userid, count($tokens), $config);

        return [manager::build_state($blockinstanceid, $userid), self::get_submission_message($config)];
    }

    /**
     * Validate the submission and return tokenized words.
     *
     * @param int $blockinstanceid
     * @param int $userid
     * @param string $rawtext
     * @param stdClass $config
     * @param context_block $context
     * @return array
     */
    protected static function get_valid_submission_tokens(
        int $blockinstanceid,
        int $userid,
        string $rawtext,
        stdClass $config,
        context_block $context
    ): array {
        require_capability('block/topicwordcloud:submitwords', $context);
        self::require_submission_window_open($config);

        $tokens = self::tokenize_words($rawtext, $config);
        if (empty($tokens)) {
            throw new moodle_exception('nowordsdetected', 'block_topicwordcloud');
        }

        self::require_multiple_submission_allowed($blockinstanceid, $userid, $config);
        self::require_word_limit_available($blockinstanceid, $userid, $tokens, $config);

        return $tokens;
    }

    /**
     * Require the submission window to be open.
     *
     * @param stdClass $config
     * @return void
     */
    protected static function require_submission_window_open(stdClass $config): void {
        if (!manager::is_submission_window_open($config)) {
            throw new moodle_exception('submissionclosed', 'block_topicwordcloud');
        }
    }

    /**
     * Require another submission to be allowed for the user.
     *
     * @param int $blockinstanceid
     * @param int $userid
     * @param stdClass $config
     * @return void
     */
    protected static function require_multiple_submission_allowed(int $blockinstanceid, int $userid, stdClass $config): void {
        global $DB;

        if ($config->allowmultiple) {
            return;
        }

        if ($DB->record_exists(manager::ENTRY_TABLE, ['blockinstanceid' => $blockinstanceid, 'userid' => $userid])) {
            throw new moodle_exception('multipleanswersdisabled', 'block_topicwordcloud');
        }
    }

    /**
     * Require enough remaining word capacity for the submission.
     *
     * @param int $blockinstanceid
     * @param int $userid
     * @param array $tokens
     * @param stdClass $config
     * @return void
     */
    protected static function require_word_limit_available(
        int $blockinstanceid,
        int $userid,
        array $tokens,
        stdClass $config
    ): void {
        global $DB;

        $currentcount = (int) $DB->count_records(manager::WORD_TABLE, [
            'blockinstanceid' => $blockinstanceid,
            'userid' => $userid,
        ]);

        if (($currentcount + count($tokens)) > $config->maxwordsperuser) {
            throw new moodle_exception('maxwordsreached', 'block_topicwordcloud', '', $config->maxwordsperuser);
        }
    }

    /**
     * Create an entry record object.
     *
     * @param int $blockinstanceid
     * @param int $userid
     * @param string $rawtext
     * @param int $courseid
     * @param int $contextid
     * @param int $time
     * @return stdClass
     */
    protected static function create_entry(
        int $blockinstanceid,
        int $userid,
        string $rawtext,
        int $courseid,
        int $contextid,
        int $time
    ): stdClass {
        return (object) [
            'blockinstanceid' => $blockinstanceid,
            'courseid' => $courseid,
            'contextid' => $contextid,
            'userid' => $userid,
            'rawtext' => trim($rawtext),
            'timecreated' => $time,
            'timemodified' => $time,
        ];
    }

    /**
     * Insert submitted words.
     *
     * @param stdClass $entry
     * @param array $tokens
     * @param stdClass $config
     * @param int $courseid
     * @param int $contextid
     * @param int $time
     * @return void
     */
    protected static function insert_words(
        stdClass $entry,
        array $tokens,
        stdClass $config,
        int $courseid,
        int $contextid,
        int $time
    ): void {
        global $DB;

        foreach ($tokens as $token) {
            $DB->insert_record(manager::WORD_TABLE, (object) [
                'entryid' => $entry->id,
                'blockinstanceid' => $entry->blockinstanceid,
                'courseid' => $courseid,
                'contextid' => $contextid,
                'userid' => $entry->userid,
                'displayword' => $token['display'],
                'normalizedword' => $token['normalized'],
                'approved' => $config->moderationrequired ? 0 : 1,
                'timecreated' => $time,
            ]);
        }
    }

    /**
     * Trigger the submission event.
     *
     * @param stdClass $entry
     * @param context_block $context
     * @param int $blockinstanceid
     * @param int $userid
     * @param int $wordcount
     * @param stdClass $config
     * @return void
     */
    protected static function trigger_submission_event(
        stdClass $entry,
        context_block $context,
        int $blockinstanceid,
        int $userid,
        int $wordcount,
        stdClass $config
    ): void {
        $event = \block_topicwordcloud\event\submission_submitted::create([
            'context' => $context,
            'objectid' => $entry->id,
            'relateduserid' => $userid,
            'other' => [
                'blockinstanceid' => $blockinstanceid,
                'wordcount' => $wordcount,
                'moderationrequired' => $config->moderationrequired ? 1 : 0,
            ],
        ]);
        $event->add_record_snapshot(manager::ENTRY_TABLE, $entry);
        $event->trigger();
    }

    /**
     * Get the submission result message.
     *
     * @param stdClass $config
     * @return string
     */
    protected static function get_submission_message(stdClass $config): string {
        return $config->moderationrequired
            ? get_string('submissionstoredpending', 'block_topicwordcloud')
            : get_string('submissionsaved', 'block_topicwordcloud');
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
}
