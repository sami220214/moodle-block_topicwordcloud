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
 * AJAX endpoint for the Topic word cloud block.
 *
 * @package   block_topicwordcloud
 * @copyright 2026 Sami Simpanen
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
define('AJAX_SCRIPT', true);

require_once(__DIR__ . '/../../config.php');
require_once($CFG->libdir . '/blocklib.php');

use block_topicwordcloud\local\manager;

$action = required_param('action', PARAM_ALPHAEXT);
$blockinstanceid = required_param('blockinstanceid', PARAM_INT);

$DB->get_record('block_instances', [
    'id' => $blockinstanceid,
    'blockname' => 'topicwordcloud',
], '*', MUST_EXIST);

$blockcontext = context_block::instance($blockinstanceid);
$coursecontext = $blockcontext->get_course_context(true);
$course = get_course($coursecontext->instanceid);

require_login($course, false);
require_sesskey();

$payload = [];
$message = '';

try {
    switch ($action) {
        case 'refresh':
            $payload = manager::build_state($blockinstanceid, $USER->id);
            break;
        case 'submit':
            $text = required_param('text', PARAM_RAW_TRIMMED);
            [$payload, $message] = manager::submit_words($blockinstanceid, $USER->id, $text);
            break;
        case 'reset':
            [$payload, $message] = manager::reset_block($blockinstanceid);
            break;
        case 'deleteword':
            $word = required_param('word', PARAM_RAW_TRIMMED);
            [$payload, $message] = manager::delete_word($blockinstanceid, $word);
            break;
        case 'approveword':
            $word = required_param('word', PARAM_RAW_TRIMMED);
            [$payload, $message] = manager::approve_word($blockinstanceid, $word);
            break;
        default:
            throw new moodle_exception('invalidaction', 'block_topicwordcloud');
    }

    header('Content-Type: application/json; charset=utf-8');
    echo json_encode([
        'success' => true,
        'message' => $message,
        'state' => $payload,
    ]);
} catch (Throwable $e) {
    header('Content-Type: application/json; charset=utf-8');
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage(),
    ]);
}
