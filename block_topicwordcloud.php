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

use block_topicwordcloud\local\manager;
use core\output\html_writer;

/**
 * Block definition for Topic word cloud.
 *
 * @package   block_topicwordcloud
 * @copyright 2026 Sami Simpanen
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class block_topicwordcloud extends block_base {
    /**
     * Initialise the block.
     *
     * @return void
     */
    public function init(): void {
        $this->title = get_string('pluginname', 'block_topicwordcloud');
    }

    /**
     * Allow block configuration.
     *
     * @return bool
     */
    public function instance_allow_config(): bool {
        return true;
    }

    /**
     * Allow multiple instances of the block on a page.
     *
     * @return bool
     */
    public function instance_allow_multiple(): bool {
        return true;
    }

    /**
     * Restrict the block to course pages.
     *
     * @return array
     */
    public function applicable_formats(): array {
        return [
            'all' => false,
            'course-view' => true,
        ];
    }

    /**
     * Render block contents.
     *
     * @return stdClass
     */
    public function get_content(): stdClass {
        if ($this->content !== null) {
            return $this->content;
        }

        $this->content = new stdClass();
        $this->content->footer = '';

        if (empty($this->instance) || empty($this->instance->id)) {
            $this->content->text = $this->get_missing_instance_content();
            return $this->content;
        }

        $this->trigger_cloud_viewed_event();

        $config = manager::get_config_from_block($this);
        $rootid = $this->get_root_id();

        $this->content->text = $this->get_wrapper_html($config, $rootid);
        $this->page->requires->js_call_amd('block_topicwordcloud/cloud', 'init', [
            $rootid,
        ]);

        return $this->content;
    }

    /**
     * Return fallback content for an incomplete block instance.
     *
     * @return string
     */
    private function get_missing_instance_content(): string {
        return get_string('pluginname', 'block_topicwordcloud');
    }

    /**
     * Trigger the cloud viewed event.
     *
     * @return void
     */
    private function trigger_cloud_viewed_event(): void {
        \block_topicwordcloud\event\cloud_viewed::create([
            'context' => $this->context,
            'objectid' => (int) $this->instance->id,
        ])->trigger();
    }

    /**
     * Get the DOM root id for this block instance.
     *
     * @return string
     */
    private function get_root_id(): string {
        return 'block-topicwordcloud-' . $this->instance->id;
    }

    /**
     * Build the block wrapper markup.
     *
     * @param stdClass $config
     * @param string $rootid
     * @return string
     */
    private function get_wrapper_html(stdClass $config, string $rootid): string {
        $statusmessage = manager::get_submission_window_label($config);
        $prompt = format_text(
            $config->prompttext ?: get_string('defaultprompt', 'block_topicwordcloud'),
            FORMAT_PLAIN,
            ['context' => $this->context]
        );

        $wrapper = html_writer::start_div('', [
            'id' => $rootid,
            'class' => 'block-topicwordcloud',
            'data-blockinstanceid' => (string) $this->instance->id,
        ]);
        $wrapper .= html_writer::div($prompt, 'block-topicwordcloud__prompt');
        $wrapper .= html_writer::start_div('block-topicwordcloud__status', [
            'data-region' => 'status',
            'aria-live' => 'polite',
        ]);
        $wrapper .= s($statusmessage);
        $wrapper .= html_writer::end_div();

        $wrapper .= html_writer::start_tag('form', [
            'class' => 'block-topicwordcloud__form',
            'data-region' => 'form',
        ]);
        $wrapper .= html_writer::tag('label', get_string('inputlabel', 'block_topicwordcloud'), [
            'class' => 'sr-only',
            'for' => $rootid . '-input',
        ]);
        $wrapper .= html_writer::tag('textarea', '', [
            'id' => $rootid . '-input',
            'class' => 'block-topicwordcloud__input',
            'rows' => 3,
            'placeholder' => get_string('inputplaceholder', 'block_topicwordcloud'),
            'data-region' => 'input',
        ]);
        $wrapper .= html_writer::tag('button', get_string('submitwords', 'block_topicwordcloud'), [
            'type' => 'submit',
            'class' => 'btn btn-primary block-topicwordcloud__submit',
        ]);
        $wrapper .= html_writer::end_tag('form');

        $wrapper .= html_writer::div('', 'block-topicwordcloud__meta', ['data-region' => 'meta']);
        $wrapper .= html_writer::div('', 'block-topicwordcloud__cloud', ['data-region' => 'cloud']);
        $wrapper .= html_writer::div('', 'block-topicwordcloud__analytics', ['data-region' => 'analytics']);
        $wrapper .= html_writer::div('', 'block-topicwordcloud__manage', ['data-region' => 'manage']);
        $wrapper .= html_writer::tag('script', $this->get_client_config_json(), [
            'type' => 'application/json',
            'data-region' => 'config',
        ]);
        $wrapper .= html_writer::end_div();

        return $wrapper;
    }

    /**
     * Build JSON configuration for the client script.
     *
     * @return string
     */
    private function get_client_config_json(): string {
        $json = json_encode([
            'pollinterval' => 15000,
            'strings' => $this->get_client_strings(),
        ], JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT);

        return $json === false ? '{}' : $json;
    }

    /**
     * Return strings used by the client script.
     *
     * @return array
     */
    private function get_client_strings(): array {
        return [
            'emptycloud' => get_string('emptycloud', 'block_topicwordcloud'),
            'emptyanalytics' => get_string('emptyanalytics', 'block_topicwordcloud'),
            'emptypending' => get_string('emptypending', 'block_topicwordcloud'),
            'analyticsheading' => get_string('analyticsheading', 'block_topicwordcloud'),
            'manageheading' => get_string('manageheading', 'block_topicwordcloud'),
            'responses' => get_string('responses', 'block_topicwordcloud'),
            'responders' => get_string('responders', 'block_topicwordcloud'),
            'uniquewords' => get_string('uniquewords', 'block_topicwordcloud'),
            'pendingcount' => get_string('pendingcount', 'block_topicwordcloud'),
            'wordcolumn' => get_string('wordcolumn', 'block_topicwordcloud'),
            'countcolumn' => get_string('countcolumn', 'block_topicwordcloud'),
            'userscolumn' => get_string('userscolumn', 'block_topicwordcloud'),
            'actioncolumn' => get_string('actioncolumn', 'block_topicwordcloud'),
            'deleteword' => get_string('deleteword', 'block_topicwordcloud'),
            'approveword' => get_string('approveword', 'block_topicwordcloud'),
            'resetcloud' => get_string('resetcloud', 'block_topicwordcloud'),
            'confirmreset' => get_string('confirmreset', 'block_topicwordcloud'),
            'confirmdeleteword' => get_string('confirmdeleteword', 'block_topicwordcloud'),
            'confirmapproveword' => get_string('confirmapproveword', 'block_topicwordcloud'),
            'pendingheading' => get_string('pendingheading', 'block_topicwordcloud'),
            'loading' => get_string('loading', 'block_topicwordcloud'),
            'remainingwords' => get_string('remainingwords', 'block_topicwordcloud'),
        ];
    }
}
