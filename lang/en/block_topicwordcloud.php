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
 * English strings for block_topicwordcloud.
 *
 * @package   block_topicwordcloud
 * @copyright 2026 Sami Simpanen
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

$string['pluginname'] = 'Word cloud';
$string['eventcloudviewed'] = 'Word cloud viewed';
$string['eventsubmissionsubmitted'] = 'Words submitted';
$string['eventcloudreset'] = 'Word cloud reset';
$string['eventworddeleted'] = 'Word deleted';
$string['eventwordapproved'] = 'Word approved';
$string['topicwordcloud:addinstance'] = 'Add a new Word cloud block';
$string['topicwordcloud:myaddinstance'] = 'Add a new Word cloud block to Dashboard';
$string['topicwordcloud:submitwords'] = 'Submit words to the word cloud';
$string['topicwordcloud:viewanalytics'] = 'View word cloud analytics';
$string['topicwordcloud:managewords'] = 'Manage word cloud words';
$string['defaultprompt'] = 'Which words do you associate with this topic?';
$string['prompttext'] = 'Prompt or question';
$string['allowmultiple'] = 'Allow multiple submissions per student';
$string['maxwordsperuser'] = 'Maximum number of words per user';
$string['wordorder'] = 'Word order';
$string['wordorder_chronological'] = 'Chronological, by first added';
$string['wordorder_alphabetical'] = 'Alphabetical';
$string['wordorder_frequency'] = 'Frequency, highest first';
$string['opentime'] = 'Open from';
$string['closetime'] = 'Close at';
$string['moderationrequired'] = 'Require moderation before words become visible';
$string['showusernames'] = 'Show participant names to teachers';
$string['customstopwords'] = 'Additional stop words';
$string['inputlabel'] = 'Words';
$string['inputplaceholder'] = 'Write one or more words separated by spaces, commas or line breaks';
$string['submitwords'] = 'Submit';
$string['loading'] = 'Loading word cloud...';
$string['opennow'] = 'Responses are open.';
$string['openuntil'] = 'Responses are open until {$a}.';
$string['opensat'] = 'Responses open on {$a}.';
$string['closedat'] = 'Responses closed on {$a}.';
$string['submissionsaved'] = 'Your words were saved.';
$string['submissionstoredpending'] = 'Your words were saved and are waiting for approval.';
$string['submissionclosed'] = 'The response window is closed.';
$string['multipleanswersdisabled'] = 'Only one submission per student is allowed in this block.';
$string['maxwordsreached'] = 'You can submit at most {$a} words in this block.';
$string['nowordsdetected'] = 'No valid words were detected. Check stop words and punctuation.';
$string['emptycloud'] = 'The word cloud is still empty.';
$string['emptyanalytics'] = 'No analytics to show yet.';
$string['emptypending'] = 'No pending words.';
$string['analyticsheading'] = 'Analytics';
$string['manageheading'] = 'Teacher controls';
$string['pendingheading'] = 'Pending moderation';
$string['responses'] = 'Responses';
$string['responders'] = 'Unique responders';
$string['uniquewords'] = 'Unique words';
$string['pendingcount'] = 'Pending words';
$string['remainingwords'] = 'Remaining words';
$string['wordcolumn'] = 'Word';
$string['countcolumn'] = 'Count';
$string['userscolumn'] = 'Users';
$string['actioncolumn'] = 'Actions';
$string['deleteword'] = 'Delete';
$string['approveword'] = 'Approve';
$string['resetcloud'] = 'Reset word cloud';
$string['confirmreset'] = 'Reset the word cloud and delete all saved responses?';
$string['confirmdeleteword'] = 'Delete all occurrences of this word from the cloud?';
$string['confirmapproveword'] = 'Approve all pending occurrences of this word?';
$string['cloudresetdone'] = 'The word cloud was reset.';
$string['worddeleted'] = 'Word "{$a}" was removed.';
$string['wordapproved'] = 'Word "{$a}" was approved.';
$string['invalidword'] = 'Invalid word.';
$string['invalidaction'] = 'Invalid action.';
$string['invalidblockinstance'] = 'The word cloud block instance could not be loaded.';
$string['err_maxwords'] = 'Maximum words per user must be at least 1.';
$string['err_wordorder'] = 'Select a valid word order.';
$string['err_closetime'] = 'Closing time must be after opening time.';
$string['privacy:metadata:entries'] = 'Stores raw word cloud submissions.';
$string['privacy:metadata:words'] = 'Stores processed words for the word cloud.';
$string['privacy:metadata:blockinstanceid'] = 'The block instance id.';
$string['privacy:metadata:courseid'] = 'The course id.';
$string['privacy:metadata:contextid'] = 'The block context id.';
$string['privacy:metadata:userid'] = 'The user id of the respondent.';
$string['privacy:metadata:rawtext'] = 'The raw text submitted by the respondent.';
$string['privacy:metadata:timecreated'] = 'The time when the record was created.';
$string['privacy:metadata:timemodified'] = 'The time when the record was last modified.';
$string['privacy:metadata:entryid'] = 'The related submission record.';
$string['privacy:metadata:displayword'] = 'The original visible form of the word.';
$string['privacy:metadata:normalizedword'] = 'The normalised form of the word for frequency counting.';
$string['privacy:metadata:approved'] = 'Whether the word has been approved for display.';
