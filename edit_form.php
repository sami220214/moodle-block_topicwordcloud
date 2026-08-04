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
 * Edit form for the Topic word cloud block.
 *
 * @package   block_topicwordcloud
 * @copyright 2026 Sami Simpanen
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class block_topicwordcloud_edit_form extends block_edit_form {
    /**
     * Define block instance settings.
     *
     * @param MoodleQuickForm $mform
     * @return void
     */
    protected function specific_definition($mform): void {
        $mform->addElement('header', 'configheader', get_string('blocksettings', 'block'));

        $mform->addElement('textarea', 'config_prompttext', get_string('prompttext', 'block_topicwordcloud'), [
            'rows' => 4,
            'cols' => 50,
        ]);
        $mform->setType('config_prompttext', PARAM_TEXT);
        $mform->setDefault('config_prompttext', get_string('defaultprompt', 'block_topicwordcloud'));

        $mform->addElement('advcheckbox', 'config_allowmultiple', get_string('allowmultiple', 'block_topicwordcloud'));
        $mform->setDefault('config_allowmultiple', 1);

        $mform->addElement('text', 'config_maxwordsperuser', get_string('maxwordsperuser', 'block_topicwordcloud'));
        $mform->setType('config_maxwordsperuser', PARAM_INT);
        $mform->setDefault('config_maxwordsperuser', 10);

        $wordorders = [
            'chronological' => get_string('wordorder_chronological', 'block_topicwordcloud'),
            'alphabetical' => get_string('wordorder_alphabetical', 'block_topicwordcloud'),
            'frequency' => get_string('wordorder_frequency', 'block_topicwordcloud'),
        ];
        $mform->addElement('select', 'config_wordorder', get_string('wordorder', 'block_topicwordcloud'), $wordorders);
        $mform->setDefault('config_wordorder', 'chronological');

        $mform->addElement('date_time_selector', 'config_opentime', get_string('opentime', 'block_topicwordcloud'), [
            'optional' => true,
        ]);
        $mform->setDefault('config_opentime', 0);

        $mform->addElement('date_time_selector', 'config_closetime', get_string('closetime', 'block_topicwordcloud'), [
            'optional' => true,
        ]);
        $mform->setDefault('config_closetime', 0);

        $mform->addElement('advcheckbox', 'config_moderationrequired', get_string('moderationrequired', 'block_topicwordcloud'));
        $mform->setDefault('config_moderationrequired', 0);

        $mform->addElement('advcheckbox', 'config_showusernames', get_string('showusernames', 'block_topicwordcloud'));
        $mform->setDefault('config_showusernames', 0);

        $mform->addElement('textarea', 'config_stopwords', get_string('customstopwords', 'block_topicwordcloud'), [
            'rows' => 5,
            'cols' => 50,
        ]);
        $mform->setType('config_stopwords', PARAM_TEXT);
    }

    /**
     * Validate block instance settings.
     *
     * @param array $data
     * @param array $files
     * @return array
     */
    public function validation($data, $files): array {
        $errors = parent::validation($data, $files);

        if (!empty($data['config_maxwordsperuser']) && (int) $data['config_maxwordsperuser'] < 1) {
            $errors['config_maxwordsperuser'] = get_string('err_maxwords', 'block_topicwordcloud');
        }

        $validorders = ['chronological', 'alphabetical', 'frequency'];
        if (!empty($data['config_wordorder']) && !in_array($data['config_wordorder'], $validorders, true)) {
            $errors['config_wordorder'] = get_string('err_wordorder', 'block_topicwordcloud');
        }

        if (
            !empty($data['config_opentime']) &&
            !empty($data['config_closetime']) &&
            $data['config_closetime'] <= $data['config_opentime']
        ) {
            $errors['config_closetime'] = get_string('err_closetime', 'block_topicwordcloud');
        }

        return $errors;
    }
}
