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
 * This file contains tests for the response replay code in the question_usage class.
 *
 * Big thanks to Open University UK of which much of the code from these unit tests
 * are base off of.
 *
 * @package    moodlecore
 * @subpackage questionengine
 * @copyright  2016 Royal Australasian College of Surgeons
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */


defined('MOODLE_INTERNAL') || die();

global $CFG;
require_once(dirname(__FILE__) . '/../lib.php');
require_once(dirname(__FILE__) . '/helpers.php');


/**
 * Unit tests for the response replay parts of the question usage class.
 *
 * @see question_usage_by_activity
 * @copyright 2016 Royal Australasian College of Surgeons
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class question_usage_response_replay_test extends qbehaviour_walkthrough_test_base {
    public function test_response_replay_then_display() {
        $this->resetAfterTest();
        $generator = $this->getDataGenerator()->get_plugin_generator('core_question');
        $cat = $generator->create_question_category();
        $question = $generator->create_question('essay', 'plain', array('category' => $cat->id));

        // Valid user required for file saving in essays.
        $this->setAdminUser();

        // Start attempt at an essay question.
        $q = question_bank::load_question($question->id);
        $this->start_attempt_at_question($q, 'deferredfeedback', 1);

        $this->check_current_state(question_state::$todo);
        $this->check_current_mark(null);
        $this->check_step_count(1);

        // Process a response and check the expected result.
        $this->process_submission(array(
            'answer' => 'first response',
            'answerformat' => FORMAT_PLAIN,
        ));

        $this->check_current_state(question_state::$complete);
        $this->check_current_mark(null);
        $this->check_step_count(2);
        $this->save_quba();

        // Now check how that is re-displayed.
        $this->render();
        $this->assertRegExp('/first response/', $this->currentoutput);
        $this->check_output_contains_hidden_input(':sequencecheck', 2);

        $this->load_quba();
        $this->process_submission(array(
            'answer' => 'second response',
            'answerformat' => FORMAT_PLAIN,
            '-replaysequence' => '1',
        ), true);

        $this->check_current_state(question_state::$complete);
        $this->check_current_mark(null);
        $this->check_step_count(4);
        $this->save_quba();

        // Now check how that is re-displayed.
        $this->render();
        $this->assertRegExp('/first response/', $this->currentoutput);
        $this->check_output_contains_hidden_input(':sequencecheck', 4);
    }

    public function test_essay_with_files_response_replay() {
        global $CFG, $USER, $PAGE;

        $this->resetAfterTest(true);
        $this->setAdminUser();
        // Required to init a text editor.
        $PAGE->set_url('/');
        $usercontextid = context_user::instance($USER->id)->id;
        $fs = get_file_storage();

        // Create an essay question in the DB.
        $generator = $this->getDataGenerator()->get_plugin_generator('core_question');
        $cat = $generator->create_question_category();
        $question = $generator->create_question('essay', 'editorfilepicker', array('category' => $cat->id));

        // Start attempt at the question.
        $q = question_bank::load_question($question->id);
        $this->start_attempt_at_question($q, 'deferredfeedback', 1);

        $this->check_current_state(question_state::$todo);
        $this->check_current_mark(null);
        $this->check_step_count(1);

        // Process a response and check the expected result.
        // First we need to get the draft item ids.
        $this->render();
        if (!preg_match('/env=editor&amp;.*?itemid=(\d+)&amp;/', $this->currentoutput, $matches)) {
            throw new coding_exception('Editor draft item id not found.');
        }
        $editordraftid = $matches[1];
        if (!preg_match('/env=filemanager&amp;action=browse&amp;.*?itemid=(\d+)&amp;/', $this->currentoutput, $matches)) {
            throw new coding_exception('File manager draft item id not found.');
        }
        $attachementsdraftid = $matches[1];

        $this->save_file_to_draft_area($usercontextid, $editordraftid, 'smile.txt', ':-)');
        $this->save_file_to_draft_area($usercontextid, $attachementsdraftid, 'greeting.txt', 'Hello world!');
        $this->process_submission(array(
            'answer' => 'Here is a picture: <img src="' . $CFG->wwwroot .
                "/draftfile.php/{$usercontextid}/user/draft/{$editordraftid}/smile.txt" .
                '" alt="smile">.',
            'answerformat' => FORMAT_HTML,
            'answer:itemid' => $editordraftid,
            'attachments' => $attachementsdraftid));

        $this->check_current_state(question_state::$complete);
        $this->check_current_mark(null);
        $this->check_step_count(2);
        $this->save_quba();

        // Save the same response again, and verify no new step is created.
        $this->load_quba();

        $this->render();
        if (!preg_match('/env=editor&amp;.*?itemid=(\d+)&amp;/', $this->currentoutput, $matches)) {
            throw new coding_exception('Editor draft item id not found.');
        }
        $editordraftid = $matches[1];
        if (!preg_match('/env=filemanager&amp;action=browse&amp;.*?itemid=(\d+)&amp;/', $this->currentoutput, $matches)) {
            throw new coding_exception('File manager draft item id not found.');
        }
        $attachementsdraftid = $matches[1];

        $this->process_submission(array(
            'answer' => 'Here is no picture',
            'answerformat' => FORMAT_HTML,
            'answer:itemid' => $editordraftid,
            'attachments' => $attachementsdraftid,
            '-replaysequence' => '1'), true);

        $this->check_current_state(question_state::$complete);
        $this->check_current_mark(null);
        $this->check_step_count(4);
        $this->save_quba();

        $this->render();
        $this->assertRegExp('/Here is a picture/', $this->currentoutput);
        $this->assertRegExp('/smile.txt/', $this->currentoutput);

        $this->load_quba();

        // Now submit all and finish.
        $this->finish();
        $this->check_current_state(question_state::$needsgrading);
        $this->check_current_mark(null);
        $this->check_step_count(5);
        $this->save_quba();
    }

    /**
     * Helper method: Store a test file with a given name and contents in a
     * draft file area.
     *
     * @param int $usercontextid user context id.
     * @param int $draftitemid draft item id.
     * @param string $filename filename.
     * @param string $contents file contents.
     */
    protected function save_file_to_draft_area($usercontextid, $draftitemid, $filename, $contents) {
        $fs = get_file_storage();

        $filerecord = new stdClass();
        $filerecord->contextid = $usercontextid;
        $filerecord->component = 'user';
        $filerecord->filearea = 'draft';
        $filerecord->itemid = $draftitemid;
        $filerecord->filepath = '/';
        $filerecord->filename = $filename;
        $fs->create_file_from_string($filerecord, $contents);
    }
}