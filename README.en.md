# block_topicwordcloud

Topic word cloud is a Moodle 4.5.10 block. The block collects words submitted by students, merges identical words case-insensitively, removes stop words, and displays the results as a word cloud with analytics.

## Requirements

- Moodle 4.5.10
- A PHP environment compatible with Moodle 4.5.10 requirements
- Permission to install new plugin files on the Moodle server

## Installation

1. Copy this project directory to the Moodle server path `blocks/topicwordcloud`.
2. Make sure the plugin directory name is exactly `topicwordcloud`.
3. The final path should look like this: `moodle/blocks/topicwordcloud/version.php`.
4. Open Moodle in a browser as an administrator.
5. Go to `Site administration > Notifications` or directly to `/admin/index.php`.
6. Moodle detects the new block and starts the installation.
7. Confirm the installation. Moodle creates the plugin database tables:
   - `block_topicwordcloud_entry`
   - `block_topicwordcloud_word`
8. If needed, purge caches from `Site administration > Development > Purge caches`.

## Adding The Block To A Course

1. Open the course.
2. Turn editing mode on.
3. Add the block: `Wordcloud`.
4. Open the block settings.
5. Configure the desired settings:
   - prompt or assignment text
   - whether multiple responses are allowed per student
   - maximum number of words per user
   - word ordering: chronological, alphabetical, or frequency descending
   - opening and closing time
   - whether moderation is required before words become visible
   - whether student names are shown to the teacher
   - additional stop words

## What The Block Does

- A student can enter one or more words in a text field.
- Words are stored in the database with user, course, and context metadata.
- Identical words are merged regardless of letter case.
- Common stop words are removed.
- The word cloud updates automatically through AJAX.
- Words can be shown chronologically, alphabetically, or by descending frequency.
- A teacher can reset the word cloud.
- A teacher can delete individual words.
- A teacher can approve pending words when moderation is enabled.
- The block shows analytics:
  - number of responses
  - number of unique respondents
  - number of unique words
  - word frequencies

## Logging

The block writes actual user actions to Moodle logs through the Events API:

- `\block_topicwordcloud\event\cloud_viewed` when the block is rendered on the course page
- `\block_topicwordcloud\event\submission_submitted` when a user submits words
- `\block_topicwordcloud\event\word_approved` when a teacher approves a pending word
- `\block_topicwordcloud\event\word_deleted` when a teacher deletes a word
- `\block_topicwordcloud\event\cloud_reset` when a teacher resets the word cloud

Automatic AJAX refresh polling is not logged, so logs do not fill up with background refresh events.

## Testing After Installation

Recommended minimum testing:

1. Add the block to a course as a teacher.
2. Set a prompt for the block.
3. Log in as a student and submit a few words.
4. Verify that the words appear in the word cloud.
5. Verify that the same word with different casing is merged into one word.
6. Test stop word filtering.
7. Test the maximum words per user setting.
8. Test the word ordering settings.
9. Test the time window.
10. Test moderation:
    - a word is not visible before approval
    - a teacher can approve the word
11. Test the teacher reset action.

## For Developers

Key files:

- `block_topicwordcloud.php` block rendering
- `edit_form.php` block settings
- `ajax.php` AJAX actions
- `classes/local/manager.php` word cloud business logic
- `db/install.xml` database tables
- `db/access.php` capabilities
- `amd/src/cloud.js` browser-side logic
- `styles.css` styling

## Privacy

The plugin stores the raw text submitted by the student, processed words, user id, course id, context id, block instance id, and timestamps. If the setting is enabled, a teacher can see the names of users who submitted each word in analytics.

Data export and deletion are implemented through Moodle's Privacy API. The plugin does not send data to external services and does not store API keys in source code or in the database.
## Notes

- This plugin is built for a Moodle 4.5.10 environment.
- If you use it in production, test it first in a development or test environment.
- If AMD JavaScript is changed, it should be built according to the normal Moodle workflow.