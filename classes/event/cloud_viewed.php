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

namespace block_topicwordcloud\event;

defined('MOODLE_INTERNAL') || die();

/**
 * Event triggered when a user views a Topic word cloud block instance.
 *
 * @package   block_topicwordcloud
 * @copyright 2026 Sami Simpanen
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class cloud_viewed extends \core\event\base {
    /**
     * Initialise event data.
     *
     * @return void
     */
    protected function init() {
        $this->data['crud'] = 'r';
        $this->data['edulevel'] = self::LEVEL_PARTICIPATING;
        $this->data['objecttable'] = 'block_instances';
    }

    /**
     * Return the localised event name.
     *
     * @return string
     */
    public static function get_name() {
        return get_string('eventcloudviewed', 'block_topicwordcloud');
    }

    /**
     * Return a non-localised event description.
     *
     * @return string
     */
    public function get_description() {
        return "The user with id '$this->userid' viewed the word cloud block with block instance id " .
            "'$this->objectid'.";
    }

    /**
     * Return the course page where the block can be viewed.
     *
     * @return \moodle_url|null
     */
    public function get_url() {
        if (empty($this->courseid)) {
            return null;
        }

        return new \moodle_url('/course/view.php', ['id' => $this->courseid]);
    }
}