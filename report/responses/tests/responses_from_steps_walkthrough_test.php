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
 * Quiz attempt walk through using data from csv file.
 *
 * @package    gnrquiz_statistics
 * @category   phpunit
 * @copyright  2013 The Open University
 * @author     Jamie Pratt <me@jamiep.org>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

global $CFG;
require_once($CFG->dirroot . '/mod/gnrquiz/tests/attempt_walkthrough_from_csv_test.php');
require_once($CFG->dirroot . '/mod/gnrquiz/report/default.php');
require_once($CFG->dirroot . '/mod/gnrquiz/report/statistics/report.php');
require_once($CFG->dirroot . '/mod/gnrquiz/report/reportlib.php');

/**
 * Quiz attempt walk through using data from csv file.
 *
 * @package    gnrquiz_statistics
 * @category   phpunit
 * @copyright  2013 The Open University
 * @author     Jamie Pratt <me@jamiep.org>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class gnrquiz_report_responses_from_steps_testcase extends mod_gnrquiz_attempt_walkthrough_from_csv_testcase {
    protected function get_full_path_of_csv_file($setname, $test) {
        // Overridden here so that __DIR__ points to the path of this file.
        return  __DIR__."/fixtures/{$setname}{$test}.csv";
    }

    protected $files = array('questions', 'steps', 'responses');

    /**
     * Create a gnrquiz add questions to it, walk through gnrquiz attempts and then check results.
     *
     * @param array $gnrquizsettings settings to override default settings for gnrquiz created by generator. Taken from gnrquizzes.csv.
     * @param PHPUnit_Extensions_Database_DataSet_ITable[] $csvdata of data read from csv file "questionsXX.csv",
     *                                                                                  "stepsXX.csv" and "responsesXX.csv".
     * @dataProvider get_data_for_walkthrough
     */
    public function test_walkthrough_from_csv($gnrquizsettings, $csvdata) {

        $this->resetAfterTest(true);
        question_bank::get_qtype('random')->clear_caches_before_testing();

        $this->create_gnrquiz($gnrquizsettings, $csvdata['questions']);

        $gnrquizattemptids = $this->walkthrough_attempts($csvdata['steps']);

        for ($rowno = 0; $rowno < $csvdata['responses']->getRowCount(); $rowno++) {
            $responsesfromcsv = $csvdata['responses']->getRow($rowno);
            $responses = $this->explode_dot_separated_keys_to_make_subindexs($responsesfromcsv);

            if (!isset($gnrquizattemptids[$responses['gnrquizattempt']])) {
                throw new coding_exception("There is no gnrquizattempt {$responses['gnrquizattempt']}!");
            }
            $this->assert_response_test($gnrquizattemptids[$responses['gnrquizattempt']], $responses);
        }
    }

    protected function assert_response_test($gnrquizattemptid, $responses) {
        $gnrquizattempt = gnrquiz_attempt::create($gnrquizattemptid);

        foreach ($responses['slot'] as $slot => $tests) {
            $slothastests = false;
            foreach ($tests as $test) {
                if ('' !== $test) {
                    $slothastests = true;
                }
            }
            if (!$slothastests) {
                continue;
            }
            $qa = $gnrquizattempt->get_question_attempt($slot);
            $stepswithsubmit = $qa->get_steps_with_submitted_response_iterator();
            $step = $stepswithsubmit[$responses['submittedstepno']];
            if (null === $step) {
                throw new coding_exception("There is no step no {$responses['submittedstepno']} ".
                                           "for slot $slot in gnrquizattempt {$responses['gnrquizattempt']}!");
            }
            foreach (array('responsesummary', 'fraction', 'state') as $column) {
                if (isset($tests[$column]) && $tests[$column] != '') {
                    switch($column) {
                        case 'responsesummary' :
                            $actual = $qa->get_question()->summarise_response($step->get_qt_data());
                            break;
                        case 'fraction' :
                            if (count($stepswithsubmit) == $responses['submittedstepno']) {
                                // If this is the last step then we need to look at the fraction after the question has been
                                // finished.
                                $actual = $qa->get_fraction();
                            } else {
                                $actual = $step->get_fraction();
                            }
                           break;
                        case 'state' :
                            if (count($stepswithsubmit) == $responses['submittedstepno']) {
                                // If this is the last step then we need to look at the state after the question has been
                                // finished.
                                $state = $qa->get_state();
                            } else {
                                $state = $step->get_state();
                            }
                            $actual = substr(get_class($state), strlen('question_state_'));
                    }
                    $expected = $tests[$column];
                    $failuremessage = "Error in  gnrquizattempt {$responses['gnrquizattempt']} in $column, slot $slot, ".
                    "submittedstepno {$responses['submittedstepno']}";
                    $this->assertEquals($expected, $actual, $failuremessage);
                }
            }
        }
    }
}
