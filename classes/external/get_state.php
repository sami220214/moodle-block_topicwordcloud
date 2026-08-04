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

use block_topicwordcloud\local\manager;
use core_external\external_function_parameters;
use core_external\external_single_structure;

/**
 * External function for retrieving Topic word cloud state.
 *
 * @package   block_topicwordcloud
 * @copyright 2026 Sami Simpanen
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class get_state extends base {
    /**
     * Returns description of method parameters.
     *
     * @return external_function_parameters
     */
    public static function execute_parameters(): external_function_parameters {
        return self::block_parameters();
    }

    /**
     * Execute the external function.
     *
     * @param int $blockinstanceid
     * @return array
     */
    public static function execute(int $blockinstanceid): array {
        global $USER;

        $params = self::validate_parameters(self::execute_parameters(), [
            'blockinstanceid' => $blockinstanceid,
        ]);
        self::validate_block_context($params['blockinstanceid']);

        return [
            'message' => '',
            'state' => manager::build_state($params['blockinstanceid'], $USER->id),
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
