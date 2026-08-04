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
use core_external\external_function_parameters;
use core_external\external_single_structure;
use core_external\external_value;

/**
 * External function for approving a word.
 *
 * @package   block_topicwordcloud
 * @copyright 2026 Sami Simpanen
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class approve_word extends base {
    /**
     * Returns description of method parameters.
     *
     * @return external_function_parameters
     */
    public static function execute_parameters(): external_function_parameters {
        return new external_function_parameters([
            'blockinstanceid' => new external_value(PARAM_INT, 'Block instance id'),
            'word' => new external_value(PARAM_RAW_TRIMMED, 'Word to approve'),
        ]);
    }

    /**
     * Execute the external function.
     *
     * @param int $blockinstanceid
     * @param string $word
     * @return array
     */
    public static function execute(int $blockinstanceid, string $word): array {
        $params = self::validate_parameters(self::execute_parameters(), [
            'blockinstanceid' => $blockinstanceid,
            'word' => $word,
        ]);
        self::validate_block_context($params['blockinstanceid']);

        [$state, $message] = manager::approve_word($params['blockinstanceid'], $params['word']);

        return [
            'message' => $message,
            'state' => $state,
        ];
    }

    /**
     * Returns description of method result value.
     *
     * @return external_single_structure
     */
    public static function execute_returns(): external_single_structure {
        return self::response_returns();
    }
}
