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
 * Unit tests for (some of) mod/gnrquiz/locallib.php.
 *
 * @package    mod_gnrquiz
 * @category   test
 * @copyright  2008 The Open University
 * @license    http://www.gnu.org/copyleft/gpl.html GNU Public License
 */


defined('MOODLE_INTERNAL') || die();

global $CFG;
require_once($CFG->dirroot . '/mod/gnrquiz/lib.php');

/**
 * @copyright  2008 The Open University
 * @license    http://www.gnu.org/copyleft/gpl.html GNU Public License
 */
class mod_gnrquiz_lib_testcase extends advanced_testcase {
    public function test_gnrquiz_has_grades() {
        $gnrquiz = new stdClass();
        $gnrquiz->grade = '100.0000';
        $gnrquiz->sumgrades = '100.0000';
        $this->assertTrue(gnrquiz_has_grades($gnrquiz));
        $gnrquiz->sumgrades = '0.0000';
        $this->assertFalse(gnrquiz_has_grades($gnrquiz));
        $gnrquiz->grade = '0.0000';
        $this->assertFalse(gnrquiz_has_grades($gnrquiz));
        $gnrquiz->sumgrades = '100.0000';
        $this->assertFalse(gnrquiz_has_grades($gnrquiz));
    }

    public function test_gnrquiz_format_grade() {
        $gnrquiz = new stdClass();
        $gnrquiz->decimalpoints = 2;
        $this->assertEquals(gnrquiz_format_grade($gnrquiz, 0.12345678), format_float(0.12, 2));
        $this->assertEquals(gnrquiz_format_grade($gnrquiz, 0), format_float(0, 2));
        $this->assertEquals(gnrquiz_format_grade($gnrquiz, 1.000000000000), format_float(1, 2));
        $gnrquiz->decimalpoints = 0;
        $this->assertEquals(gnrquiz_format_grade($gnrquiz, 0.12345678), '0');
    }

    public function test_gnrquiz_get_grade_format() {
        $gnrquiz = new stdClass();
        $gnrquiz->decimalpoints = 2;
        $this->assertEquals(gnrquiz_get_grade_format($gnrquiz), 2);
        $this->assertEquals($gnrquiz->questiondecimalpoints, -1);
        $gnrquiz->questiondecimalpoints = 2;
        $this->assertEquals(gnrquiz_get_grade_format($gnrquiz), 2);
        $gnrquiz->decimalpoints = 3;
        $gnrquiz->questiondecimalpoints = -1;
        $this->assertEquals(gnrquiz_get_grade_format($gnrquiz), 3);
        $gnrquiz->questiondecimalpoints = 4;
        $this->assertEquals(gnrquiz_get_grade_format($gnrquiz), 4);
    }

    public function test_gnrquiz_format_question_grade() {
        $gnrquiz = new stdClass();
        $gnrquiz->decimalpoints = 2;
        $gnrquiz->questiondecimalpoints = 2;
        $this->assertEquals(gnrquiz_format_question_grade($gnrquiz, 0.12345678), format_float(0.12, 2));
        $this->assertEquals(gnrquiz_format_question_grade($gnrquiz, 0), format_float(0, 2));
        $this->assertEquals(gnrquiz_format_question_grade($gnrquiz, 1.000000000000), format_float(1, 2));
        $gnrquiz->decimalpoints = 3;
        $gnrquiz->questiondecimalpoints = -1;
        $this->assertEquals(gnrquiz_format_question_grade($gnrquiz, 0.12345678), format_float(0.123, 3));
        $this->assertEquals(gnrquiz_format_question_grade($gnrquiz, 0), format_float(0, 3));
        $this->assertEquals(gnrquiz_format_question_grade($gnrquiz, 1.000000000000), format_float(1, 3));
        $gnrquiz->questiondecimalpoints = 4;
        $this->assertEquals(gnrquiz_format_question_grade($gnrquiz, 0.12345678), format_float(0.1235, 4));
        $this->assertEquals(gnrquiz_format_question_grade($gnrquiz, 0), format_float(0, 4));
        $this->assertEquals(gnrquiz_format_question_grade($gnrquiz, 1.000000000000), format_float(1, 4));
    }

    /**
     * Test deleting a gnrquiz instance.
     */
    public function test_gnrquiz_delete_instance() {
        global $SITE, $DB;
        $this->resetAfterTest(true);
        $this->setAdminUser();

        // Setup a gnrquiz with 1 standard and 1 random question.
        $gnrquizgenerator = $this->getDataGenerator()->get_plugin_generator('mod_gnrquiz');
        $gnrquiz = $gnrquizgenerator->create_instance(array('course' => $SITE->id, 'questionsperpage' => 3, 'grade' => 100.0));

        $questiongenerator = $this->getDataGenerator()->get_plugin_generator('core_question');
        $cat = $questiongenerator->create_question_category();
        $standardq = $questiongenerator->create_question('shortanswer', null, array('category' => $cat->id));

        gnrquiz_add_gnrquiz_question($standardq->id, $gnrquiz);
        gnrquiz_add_random_questions($gnrquiz, 0, $cat->id, 1, false);

        // Get the random question.
        $randomq = $DB->get_record('question', array('qtype' => 'random'));

        gnrquiz_delete_instance($gnrquiz->id);

        // Check that the random question was deleted.
        $count = $DB->count_records('question', array('id' => $randomq->id));
        $this->assertEquals(0, $count);
        // Check that the standard question was not deleted.
        $count = $DB->count_records('question', array('id' => $standardq->id));
        $this->assertEquals(1, $count);

        // Check that all the slots were removed.
        $count = $DB->count_records('gnrquiz_slots', array('gnrquizid' => $gnrquiz->id));
        $this->assertEquals(0, $count);

        // Check that the gnrquiz was removed.
        $count = $DB->count_records('gnrquiz', array('id' => $gnrquiz->id));
        $this->assertEquals(0, $count);
    }

    /**
     * Test checking the completion state of a gnrquiz.
     */
    public function test_gnrquiz_get_completion_state() {
        global $CFG, $DB;
        $this->resetAfterTest(true);

        // Enable completion before creating modules, otherwise the completion data is not written in DB.
        $CFG->enablecompletion = true;

        // Create a course and student.
        $course = $this->getDataGenerator()->create_course(array('enablecompletion' => true));
        $passstudent = $this->getDataGenerator()->create_user();
        $failstudent = $this->getDataGenerator()->create_user();
        $studentrole = $DB->get_record('role', array('shortname' => 'student'));
        $this->assertNotEmpty($studentrole);

        // Enrol students.
        $this->assertTrue($this->getDataGenerator()->enrol_user($passstudent->id, $course->id, $studentrole->id));
        $this->assertTrue($this->getDataGenerator()->enrol_user($failstudent->id, $course->id, $studentrole->id));

        // Make a scale and an outcome.
        $scale = $this->getDataGenerator()->create_scale();
        $data = array('courseid' => $course->id,
                      'fullname' => 'Team work',
                      'shortname' => 'Team work',
                      'scaleid' => $scale->id);
        $outcome = $this->getDataGenerator()->create_grade_outcome($data);

        // Make a gnrquiz with the outcome on.
        $gnrquizgenerator = $this->getDataGenerator()->get_plugin_generator('mod_gnrquiz');
        $data = array('course' => $course->id,
                      'outcome_'.$outcome->id => 1,
                      'grade' => 100.0,
                      'questionsperpage' => 0,
                      'sumgrades' => 1,
                      'completion' => COMPLETION_TRACKING_AUTOMATIC,
                      'completionpass' => 1);
        $gnrquiz = $gnrquizgenerator->create_instance($data);
        $cm = get_coursemodule_from_id('gnrquiz', $gnrquiz->cmid);

        // Create a couple of questions.
        $questiongenerator = $this->getDataGenerator()->get_plugin_generator('core_question');

        $cat = $questiongenerator->create_question_category();
        $question = $questiongenerator->create_question('numerical', null, array('category' => $cat->id));
        gnrquiz_add_gnrquiz_question($question->id, $gnrquiz);

        $gnrquizobj = gnrquiz::create($gnrquiz->id, $passstudent->id);

        // Set grade to pass.
        $item = grade_item::fetch(array('courseid' => $course->id, 'itemtype' => 'mod',
                                        'itemmodule' => 'gnrquiz', 'iteminstance' => $gnrquiz->id, 'outcomeid' => null));
        $item->gradepass = 80;
        $item->update();

        // Start the passing attempt.
        $quba = question_engine::make_questions_usage_by_activity('mod_gnrquiz', $gnrquizobj->get_context());
        $quba->set_preferred_behaviour($gnrquizobj->get_gnrquiz()->preferredbehaviour);

        $timenow = time();
        $attempt = gnrquiz_create_attempt($gnrquizobj, 1, false, $timenow, false, $passstudent->id);
        gnrquiz_start_new_attempt($gnrquizobj, $quba, $attempt, 1, $timenow);
        gnrquiz_attempt_save_started($gnrquizobj, $quba, $attempt);

        // Process some responses from the student.
        $attemptobj = gnrquiz_attempt::create($attempt->id);
        $tosubmit = array(1 => array('answer' => '3.14'));
        $attemptobj->process_submitted_actions($timenow, false, $tosubmit);

        // Finish the attempt.
        $attemptobj = gnrquiz_attempt::create($attempt->id);
        $this->assertTrue($attemptobj->has_response_to_at_least_one_graded_question());
        $attemptobj->process_finish($timenow, false);

        // Start the failing attempt.
        $quba = question_engine::make_questions_usage_by_activity('mod_gnrquiz', $gnrquizobj->get_context());
        $quba->set_preferred_behaviour($gnrquizobj->get_gnrquiz()->preferredbehaviour);

        $timenow = time();
        $attempt = gnrquiz_create_attempt($gnrquizobj, 1, false, $timenow, false, $failstudent->id);
        gnrquiz_start_new_attempt($gnrquizobj, $quba, $attempt, 1, $timenow);
        gnrquiz_attempt_save_started($gnrquizobj, $quba, $attempt);

        // Process some responses from the student.
        $attemptobj = gnrquiz_attempt::create($attempt->id);
        $tosubmit = array(1 => array('answer' => '0'));
        $attemptobj->process_submitted_actions($timenow, false, $tosubmit);

        // Finish the attempt.
        $attemptobj = gnrquiz_attempt::create($attempt->id);
        $this->assertTrue($attemptobj->has_response_to_at_least_one_graded_question());
        $attemptobj->process_finish($timenow, false);

        // Check the results.
        $this->assertTrue(gnrquiz_get_completion_state($course, $cm, $passstudent->id, 'return'));
        $this->assertFalse(gnrquiz_get_completion_state($course, $cm, $failstudent->id, 'return'));
    }

    public function test_gnrquiz_get_user_attempts() {
        global $DB;
        $this->resetAfterTest();

        $dg = $this->getDataGenerator();
        $gnrquizgen = $dg->get_plugin_generator('mod_gnrquiz');
        $course = $dg->create_course();
        $u1 = $dg->create_user();
        $u2 = $dg->create_user();
        $u3 = $dg->create_user();
        $u4 = $dg->create_user();
        $role = $DB->get_record('role', ['shortname' => 'student']);

        $dg->enrol_user($u1->id, $course->id, $role->id);
        $dg->enrol_user($u2->id, $course->id, $role->id);
        $dg->enrol_user($u3->id, $course->id, $role->id);
        $dg->enrol_user($u4->id, $course->id, $role->id);

        $gnrquiz1 = $gnrquizgen->create_instance(['course' => $course->id, 'sumgrades' => 2]);
        $gnrquiz2 = $gnrquizgen->create_instance(['course' => $course->id, 'sumgrades' => 2]);

        // Questions.
        $questgen = $dg->get_plugin_generator('core_question');
        $gnrquizcat = $questgen->create_question_category();
        $question = $questgen->create_question('numerical', null, ['category' => $gnrquizcat->id]);
        gnrquiz_add_gnrquiz_question($question->id, $gnrquiz1);
        gnrquiz_add_gnrquiz_question($question->id, $gnrquiz2);

        $gnrquizobj1a = gnrquiz::create($gnrquiz1->id, $u1->id);
        $gnrquizobj1b = gnrquiz::create($gnrquiz1->id, $u2->id);
        $gnrquizobj1c = gnrquiz::create($gnrquiz1->id, $u3->id);
        $gnrquizobj1d = gnrquiz::create($gnrquiz1->id, $u4->id);
        $gnrquizobj2a = gnrquiz::create($gnrquiz2->id, $u1->id);

        // Set attempts.
        $quba1a = question_engine::make_questions_usage_by_activity('mod_gnrquiz', $gnrquizobj1a->get_context());
        $quba1a->set_preferred_behaviour($gnrquizobj1a->get_gnrquiz()->preferredbehaviour);
        $quba1b = question_engine::make_questions_usage_by_activity('mod_gnrquiz', $gnrquizobj1b->get_context());
        $quba1b->set_preferred_behaviour($gnrquizobj1b->get_gnrquiz()->preferredbehaviour);
        $quba1c = question_engine::make_questions_usage_by_activity('mod_gnrquiz', $gnrquizobj1c->get_context());
        $quba1c->set_preferred_behaviour($gnrquizobj1c->get_gnrquiz()->preferredbehaviour);
        $quba1d = question_engine::make_questions_usage_by_activity('mod_gnrquiz', $gnrquizobj1d->get_context());
        $quba1d->set_preferred_behaviour($gnrquizobj1d->get_gnrquiz()->preferredbehaviour);
        $quba2a = question_engine::make_questions_usage_by_activity('mod_gnrquiz', $gnrquizobj2a->get_context());
        $quba2a->set_preferred_behaviour($gnrquizobj2a->get_gnrquiz()->preferredbehaviour);

        $timenow = time();

        // User 1 passes gnrquiz 1.
        $attempt = gnrquiz_create_attempt($gnrquizobj1a, 1, false, $timenow, false, $u1->id);
        gnrquiz_start_new_attempt($gnrquizobj1a, $quba1a, $attempt, 1, $timenow);
        gnrquiz_attempt_save_started($gnrquizobj1a, $quba1a, $attempt);
        $attemptobj = gnrquiz_attempt::create($attempt->id);
        $attemptobj->process_submitted_actions($timenow, false, [1 => ['answer' => '3.14']]);
        $attemptobj->process_finish($timenow, false);

        // User 2 goes overdue in gnrquiz 1.
        $attempt = gnrquiz_create_attempt($gnrquizobj1b, 1, false, $timenow, false, $u2->id);
        gnrquiz_start_new_attempt($gnrquizobj1b, $quba1b, $attempt, 1, $timenow);
        gnrquiz_attempt_save_started($gnrquizobj1b, $quba1b, $attempt);
        $attemptobj = gnrquiz_attempt::create($attempt->id);
        $attemptobj->process_going_overdue($timenow, true);

        // User 3 does not finish gnrquiz 1.
        $attempt = gnrquiz_create_attempt($gnrquizobj1c, 1, false, $timenow, false, $u3->id);
        gnrquiz_start_new_attempt($gnrquizobj1c, $quba1c, $attempt, 1, $timenow);
        gnrquiz_attempt_save_started($gnrquizobj1c, $quba1c, $attempt);

        // User 4 abandons the gnrquiz 1.
        $attempt = gnrquiz_create_attempt($gnrquizobj1d, 1, false, $timenow, false, $u4->id);
        gnrquiz_start_new_attempt($gnrquizobj1d, $quba1d, $attempt, 1, $timenow);
        gnrquiz_attempt_save_started($gnrquizobj1d, $quba1d, $attempt);
        $attemptobj = gnrquiz_attempt::create($attempt->id);
        $attemptobj->process_abandon($timenow, true);

        // User 1 attempts the gnrquiz three times (abandon, finish, in progress).
        $quba2a = question_engine::make_questions_usage_by_activity('mod_gnrquiz', $gnrquizobj2a->get_context());
        $quba2a->set_preferred_behaviour($gnrquizobj2a->get_gnrquiz()->preferredbehaviour);

        $attempt = gnrquiz_create_attempt($gnrquizobj2a, 1, false, $timenow, false, $u1->id);
        gnrquiz_start_new_attempt($gnrquizobj2a, $quba2a, $attempt, 1, $timenow);
        gnrquiz_attempt_save_started($gnrquizobj2a, $quba2a, $attempt);
        $attemptobj = gnrquiz_attempt::create($attempt->id);
        $attemptobj->process_abandon($timenow, true);

        $quba2a = question_engine::make_questions_usage_by_activity('mod_gnrquiz', $gnrquizobj2a->get_context());
        $quba2a->set_preferred_behaviour($gnrquizobj2a->get_gnrquiz()->preferredbehaviour);

        $attempt = gnrquiz_create_attempt($gnrquizobj2a, 2, false, $timenow, false, $u1->id);
        gnrquiz_start_new_attempt($gnrquizobj2a, $quba2a, $attempt, 2, $timenow);
        gnrquiz_attempt_save_started($gnrquizobj2a, $quba2a, $attempt);
        $attemptobj = gnrquiz_attempt::create($attempt->id);
        $attemptobj->process_finish($timenow, false);

        $quba2a = question_engine::make_questions_usage_by_activity('mod_gnrquiz', $gnrquizobj2a->get_context());
        $quba2a->set_preferred_behaviour($gnrquizobj2a->get_gnrquiz()->preferredbehaviour);

        $attempt = gnrquiz_create_attempt($gnrquizobj2a, 3, false, $timenow, false, $u1->id);
        gnrquiz_start_new_attempt($gnrquizobj2a, $quba2a, $attempt, 3, $timenow);
        gnrquiz_attempt_save_started($gnrquizobj2a, $quba2a, $attempt);

        // Check for user 1.
        $attempts = gnrquiz_get_user_attempts($gnrquiz1->id, $u1->id, 'all');
        $this->assertCount(1, $attempts);
        $attempt = array_shift($attempts);
        $this->assertEquals(gnrquiz_attempt::FINISHED, $attempt->state);
        $this->assertEquals($u1->id, $attempt->userid);
        $this->assertEquals($gnrquiz1->id, $attempt->gnrquiz);

        $attempts = gnrquiz_get_user_attempts($gnrquiz1->id, $u1->id, 'finished');
        $this->assertCount(1, $attempts);
        $attempt = array_shift($attempts);
        $this->assertEquals(gnrquiz_attempt::FINISHED, $attempt->state);
        $this->assertEquals($u1->id, $attempt->userid);
        $this->assertEquals($gnrquiz1->id, $attempt->gnrquiz);

        $attempts = gnrquiz_get_user_attempts($gnrquiz1->id, $u1->id, 'unfinished');
        $this->assertCount(0, $attempts);

        // Check for user 2.
        $attempts = gnrquiz_get_user_attempts($gnrquiz1->id, $u2->id, 'all');
        $this->assertCount(1, $attempts);
        $attempt = array_shift($attempts);
        $this->assertEquals(gnrquiz_attempt::OVERDUE, $attempt->state);
        $this->assertEquals($u2->id, $attempt->userid);
        $this->assertEquals($gnrquiz1->id, $attempt->gnrquiz);

        $attempts = gnrquiz_get_user_attempts($gnrquiz1->id, $u2->id, 'finished');
        $this->assertCount(0, $attempts);

        $attempts = gnrquiz_get_user_attempts($gnrquiz1->id, $u2->id, 'unfinished');
        $this->assertCount(1, $attempts);
        $attempt = array_shift($attempts);
        $this->assertEquals(gnrquiz_attempt::OVERDUE, $attempt->state);
        $this->assertEquals($u2->id, $attempt->userid);
        $this->assertEquals($gnrquiz1->id, $attempt->gnrquiz);

        // Check for user 3.
        $attempts = gnrquiz_get_user_attempts($gnrquiz1->id, $u3->id, 'all');
        $this->assertCount(1, $attempts);
        $attempt = array_shift($attempts);
        $this->assertEquals(gnrquiz_attempt::IN_PROGRESS, $attempt->state);
        $this->assertEquals($u3->id, $attempt->userid);
        $this->assertEquals($gnrquiz1->id, $attempt->gnrquiz);

        $attempts = gnrquiz_get_user_attempts($gnrquiz1->id, $u3->id, 'finished');
        $this->assertCount(0, $attempts);

        $attempts = gnrquiz_get_user_attempts($gnrquiz1->id, $u3->id, 'unfinished');
        $this->assertCount(1, $attempts);
        $attempt = array_shift($attempts);
        $this->assertEquals(gnrquiz_attempt::IN_PROGRESS, $attempt->state);
        $this->assertEquals($u3->id, $attempt->userid);
        $this->assertEquals($gnrquiz1->id, $attempt->gnrquiz);

        // Check for user 4.
        $attempts = gnrquiz_get_user_attempts($gnrquiz1->id, $u4->id, 'all');
        $this->assertCount(1, $attempts);
        $attempt = array_shift($attempts);
        $this->assertEquals(gnrquiz_attempt::ABANDONED, $attempt->state);
        $this->assertEquals($u4->id, $attempt->userid);
        $this->assertEquals($gnrquiz1->id, $attempt->gnrquiz);

        $attempts = gnrquiz_get_user_attempts($gnrquiz1->id, $u4->id, 'finished');
        $this->assertCount(1, $attempts);
        $attempt = array_shift($attempts);
        $this->assertEquals(gnrquiz_attempt::ABANDONED, $attempt->state);
        $this->assertEquals($u4->id, $attempt->userid);
        $this->assertEquals($gnrquiz1->id, $attempt->gnrquiz);

        $attempts = gnrquiz_get_user_attempts($gnrquiz1->id, $u4->id, 'unfinished');
        $this->assertCount(0, $attempts);

        // Multiple attempts for user 1 in gnrquiz 2.
        $attempts = gnrquiz_get_user_attempts($gnrquiz2->id, $u1->id, 'all');
        $this->assertCount(3, $attempts);
        $attempt = array_shift($attempts);
        $this->assertEquals(gnrquiz_attempt::ABANDONED, $attempt->state);
        $this->assertEquals($u1->id, $attempt->userid);
        $this->assertEquals($gnrquiz2->id, $attempt->gnrquiz);
        $attempt = array_shift($attempts);
        $this->assertEquals(gnrquiz_attempt::FINISHED, $attempt->state);
        $this->assertEquals($u1->id, $attempt->userid);
        $this->assertEquals($gnrquiz2->id, $attempt->gnrquiz);
        $attempt = array_shift($attempts);
        $this->assertEquals(gnrquiz_attempt::IN_PROGRESS, $attempt->state);
        $this->assertEquals($u1->id, $attempt->userid);
        $this->assertEquals($gnrquiz2->id, $attempt->gnrquiz);

        $attempts = gnrquiz_get_user_attempts($gnrquiz2->id, $u1->id, 'finished');
        $this->assertCount(2, $attempts);
        $attempt = array_shift($attempts);
        $this->assertEquals(gnrquiz_attempt::ABANDONED, $attempt->state);
        $attempt = array_shift($attempts);
        $this->assertEquals(gnrquiz_attempt::FINISHED, $attempt->state);

        $attempts = gnrquiz_get_user_attempts($gnrquiz2->id, $u1->id, 'unfinished');
        $this->assertCount(1, $attempts);
        $attempt = array_shift($attempts);

        // Multiple gnrquiz attempts fetched at once.
        $attempts = gnrquiz_get_user_attempts([$gnrquiz1->id, $gnrquiz2->id], $u1->id, 'all');
        $this->assertCount(4, $attempts);
        $attempt = array_shift($attempts);
        $this->assertEquals(gnrquiz_attempt::FINISHED, $attempt->state);
        $this->assertEquals($u1->id, $attempt->userid);
        $this->assertEquals($gnrquiz1->id, $attempt->gnrquiz);
        $attempt = array_shift($attempts);
        $this->assertEquals(gnrquiz_attempt::ABANDONED, $attempt->state);
        $this->assertEquals($u1->id, $attempt->userid);
        $this->assertEquals($gnrquiz2->id, $attempt->gnrquiz);
        $attempt = array_shift($attempts);
        $this->assertEquals(gnrquiz_attempt::FINISHED, $attempt->state);
        $this->assertEquals($u1->id, $attempt->userid);
        $this->assertEquals($gnrquiz2->id, $attempt->gnrquiz);
        $attempt = array_shift($attempts);
        $this->assertEquals(gnrquiz_attempt::IN_PROGRESS, $attempt->state);
        $this->assertEquals($u1->id, $attempt->userid);
        $this->assertEquals($gnrquiz2->id, $attempt->gnrquiz);
    }

}
