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

namespace block_topicwordcloud\external;

defined('MOODLE_INTERNAL') || die();

use block_topicwordcloud\local\manager;
use context_block;
use core_external\external_api;
use core_external\external_function_parameters;
use core_external\external_multiple_structure;
use core_external\external_single_structure;
use core_external\external_value;

/**
 * Shared external service helpers for the Topic word cloud block.
 *
 * @package   block_topicwordcloud
 * @copyright 2026 Sami Simpanen
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
abstract class base extends external_api {
    /**
     * Return the standard block instance parameter definition.
     *
     * @return external_function_parameters
     */
    protected static function block_parameters(): external_function_parameters {
        return new external_function_parameters([
            'blockinstanceid' => new external_value(PARAM_INT, 'Block instance id'),
        ]);
    }

    /**
     * Validate the block context for an external request.
     *
     * @param int $blockinstanceid
     * @return context_block
     */
    protected static function validate_block_context(int $blockinstanceid): context_block {
        manager::get_block_instance($blockinstanceid);
        $context = context_block::instance($blockinstanceid);
        self::validate_context($context);
        return $context;
    }

    /**
     * Return the common response structure.
     *
     * @return external_single_structure
     */
    protected static function response_returns(): external_single_structure {
        return new external_single_structure([
            'message' => new external_value(PARAM_TEXT, 'User-facing status message'),
            'state' => self::state_returns(),
        ]);
    }

    /**
     * Return the client state structure.
     *
     * @return external_single_structure
     */
    protected static function state_returns(): external_single_structure {
        $wordstructure = new external_single_structure([
            'word' => new external_value(PARAM_TEXT, 'Display word'),
            'normalizedword' => new external_value(PARAM_TEXT, 'Normalised word'),
            'count' => new external_value(PARAM_INT, 'Word count'),
            'size' => new external_value(PARAM_INT, 'Calculated display size', VALUE_OPTIONAL),
            'colorindex' => new external_value(PARAM_INT, 'Calculated colour index', VALUE_OPTIONAL),
            'usercount' => new external_value(PARAM_INT, 'Number of users', VALUE_OPTIONAL),
            'usernames' => new external_multiple_structure(
                new external_value(PARAM_TEXT, 'User full name'),
                'User full names',
                VALUE_OPTIONAL
            ),
        ]);

        return new external_single_structure([
            'prompthtml' => new external_value(PARAM_RAW, 'Formatted prompt HTML'),
            'statusmessage' => new external_value(PARAM_TEXT, 'Current submission window status'),
            'cloudwords' => new external_multiple_structure($wordstructure, 'Approved cloud words'),
            'analytics' => new external_multiple_structure($wordstructure, 'Analytics rows'),
            'pendingwords' => new external_multiple_structure($wordstructure, 'Pending moderation rows'),
            'totals' => new external_single_structure([
                'responses' => new external_value(PARAM_INT, 'Total responses'),
                'responders' => new external_value(PARAM_INT, 'Total responders'),
                'uniquewords' => new external_value(PARAM_INT, 'Total unique approved words'),
                'pending' => new external_value(PARAM_INT, 'Total pending words'),
            ]),
            'canmanage' => new external_value(PARAM_BOOL, 'Whether the user can manage words'),
            'canviewanalytics' => new external_value(PARAM_BOOL, 'Whether the user can view analytics'),
            'cansubmit' => new external_value(PARAM_BOOL, 'Whether the user can submit words'),
            'acceptingresponses' => new external_value(PARAM_BOOL, 'Whether responses are currently accepted'),
            'showusernames' => new external_value(PARAM_BOOL, 'Whether usernames should be shown'),
            'moderationrequired' => new external_value(PARAM_BOOL, 'Whether moderation is enabled'),
            'remainingwords' => new external_value(PARAM_INT, 'Remaining words for the current user'),
            'limits' => new external_single_structure([
                'allowmultiple' => new external_value(PARAM_BOOL, 'Whether multiple submissions are allowed'),
                'maxwordsperuser' => new external_value(PARAM_INT, 'Maximum words per user'),
            ]),
        ]);
    }
}
