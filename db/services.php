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
 * External service declarations for the Topic word cloud block.
 *
 * @package   block_topicwordcloud
 * @copyright 2026 Sami Simpanen
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

$functions = [
    'block_topicwordcloud_get_state' => [
        'classname' => 'block_topicwordcloud\external\get_state',
        'description' => 'Get the current Topic word cloud block state.',
        'type' => 'read',
        'ajax' => true,
    ],
    'block_topicwordcloud_submit_words' => [
        'classname' => 'block_topicwordcloud\external\submit_words',
        'description' => 'Submit words to a Topic word cloud block.',
        'type' => 'write',
        'ajax' => true,
        'capabilities' => 'block/topicwordcloud:submitwords',
    ],
    'block_topicwordcloud_reset_cloud' => [
        'classname' => 'block_topicwordcloud\external\reset_cloud',
        'description' => 'Reset a Topic word cloud block.',
        'type' => 'write',
        'ajax' => true,
        'capabilities' => 'block/topicwordcloud:managewords',
    ],
    'block_topicwordcloud_delete_word' => [
        'classname' => 'block_topicwordcloud\external\delete_word',
        'description' => 'Delete a word from a Topic word cloud block.',
        'type' => 'write',
        'ajax' => true,
        'capabilities' => 'block/topicwordcloud:managewords',
    ],
    'block_topicwordcloud_approve_word' => [
        'classname' => 'block_topicwordcloud\external\approve_word',
        'description' => 'Approve a pending word in a Topic word cloud block.',
        'type' => 'write',
        'ajax' => true,
        'capabilities' => 'block/topicwordcloud:managewords',
    ],
];
