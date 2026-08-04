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

/**
 * Event triggered when a word is removed from a Topic word cloud block.
 *
 * @package   block_topicwordcloud
 * @copyright 2026 Sami Simpanen
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class word_deleted extends \core\event\base {
    /**
     * Initialise event data.
     *
     * @return void
     */
    protected function init() {
        $this->data['crud'] = 'd';
        $this->data['edulevel'] = self::LEVEL_TEACHING;
        $this->data['objecttable'] = 'block_instances';
    }

    /**
     * Return the localised event name.
     *
     * @return string
     */
    public static function get_name() {
        return get_string('eventworddeleted', 'block_topicwordcloud');
    }

    /**
     * Return a non-localised event description.
     *
     * @return string
     */
    public function get_description() {
        $word = $this->other['word'] ?? '';
        $wordcount = $this->other['wordcount'] ?? 0;

        return "The user with id '$this->userid' deleted '$wordcount' occurrences of the word '$word' from " .
            "the word cloud block with block instance id '$this->objectid'.";
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

    /**
     * Validate event data.
     *
     * @return void
     */
    protected function validate_data() {
        parent::validate_data();

        if (!isset($this->other['word'])) {
            throw new \coding_exception('The word value must be set in other.');
        }
        if (!isset($this->other['wordcount'])) {
            throw new \coding_exception('The wordcount value must be set in other.');
        }
    }
}
