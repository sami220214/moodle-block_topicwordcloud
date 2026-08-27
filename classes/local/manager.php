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
        return state_builder::build($blockinstanceid, $userid);
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
        return word_service::submit($blockinstanceid, $userid, $rawtext);
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
            self::build_state($blockinstanceid, $USER->id), get_string('wordapproved', 'block_topicwordcloud', $normalizedword),
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
}
