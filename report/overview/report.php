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
 * This file defines the gnrquiz overview report class.
 *
 * @package   gnrquiz_overview
 * @copyright 1999 onwards Martin Dougiamas and others {@link http://moodle.com}
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */


defined('MOODLE_INTERNAL') || die();

require_once($CFG->dirroot . '/mod/gnrquiz/report/attemptsreport.php');
require_once($CFG->dirroot . '/mod/gnrquiz/report/overview/overview_options.php');
require_once($CFG->dirroot . '/mod/gnrquiz/report/overview/overview_form.php');
require_once($CFG->dirroot . '/mod/gnrquiz/report/overview/overview_table.php');


/**
 * Quiz report subclass for the overview (grades) report.
 *
 * @copyright 1999 onwards Martin Dougiamas and others {@link http://moodle.com}
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class gnrquiz_overview_report extends gnrquiz_attempts_report {

    public function display($gnrquiz, $cm, $course) {
        global $CFG, $DB, $OUTPUT, $PAGE;

        list($currentgroup, $students, $groupstudents, $allowed) =
                $this->init('overview', 'gnrquiz_overview_settings_form', $gnrquiz, $cm, $course);
        $options = new gnrquiz_overview_options('overview', $gnrquiz, $cm, $course);

        if ($fromform = $this->form->get_data()) {
            $options->process_settings_from_form($fromform);

        } else {
            $options->process_settings_from_params();
        }

        $this->form->set_data($options->get_initial_form_data());

        if ($options->attempts == self::ALL_WITH) {
            // This option is only available to users who can access all groups in
            // groups mode, so setting allowed to empty (which means all gnrquiz attempts
            // are accessible, is not a security porblem.
            $allowed = array();
        }

        // Load the required questions.
        $questions = gnrquiz_report_get_significant_questions($gnrquiz);

        // Prepare for downloading, if applicable.
        $courseshortname = format_string($course->shortname, true,
                array('context' => context_course::instance($course->id)));
        $table = new gnrquiz_overview_table($gnrquiz, $this->context, $this->qmsubselect,
                $options, $groupstudents, $students, $questions, $options->get_url());
        $filename = gnrquiz_report_download_filename(get_string('overviewfilename', 'gnrquiz_overview'),
                $courseshortname, $gnrquiz->name);
        $table->is_downloading($options->download, $filename,
                $courseshortname . ' ' . format_string($gnrquiz->name, true));
        if ($table->is_downloading()) {
            raise_memory_limit(MEMORY_EXTRA);
        }

        $this->course = $course; // Hack to make this available in process_actions.
        $this->process_actions($gnrquiz, $cm, $currentgroup, $groupstudents, $allowed, $options->get_url());

        // Start output.
        if (!$table->is_downloading()) {
            // Only print headers if not asked to download data.
            $this->print_header_and_tabs($cm, $course, $gnrquiz, $this->mode);
        }

        if ($groupmode = groups_get_activity_groupmode($cm)) {
            // Groups are being used, so output the group selector if we are not downloading.
            if (!$table->is_downloading()) {
                groups_print_activity_menu($cm, $options->get_url());
            }
        }

        // Print information on the number of existing attempts.
        if (!$table->is_downloading()) {
            // Do not print notices when downloading.
            if ($strattemptnum = gnrquiz_num_attempt_summary($gnrquiz, $cm, true, $currentgroup)) {
                echo '<div class="gnrquizattemptcounts">' . $strattemptnum . '</div>';
            }
        }

        $hasquestions = gnrquiz_has_questions($gnrquiz->id);
        if (!$table->is_downloading()) {
            if (!$hasquestions) {
                echo gnrquiz_no_questions_message($gnrquiz, $cm, $this->context);
            } else if (!$students) {
                echo $OUTPUT->notification(get_string('nostudentsyet'));
            } else if ($currentgroup && !$groupstudents) {
                echo $OUTPUT->notification(get_string('nostudentsingroup'));
            }

            // Print the display options.
            $this->form->display();
        }

        $hasstudents = $students && (!$currentgroup || $groupstudents);
        if ($hasquestions && ($hasstudents || $options->attempts == self::ALL_WITH)) {
            // Construct the SQL.
            $fields = $DB->sql_concat('u.id', "'#'", 'COALESCE(gnrquiza.attempt, 0)') .
                    ' AS uniqueid, ';

            list($fields, $from, $where, $params) = $table->base_sql($allowed);

            $table->set_count_sql("SELECT COUNT(1) FROM $from WHERE $where", $params);

            // Test to see if there are any regraded attempts to be listed.
            $fields .= ", COALESCE((
                                SELECT MAX(qqr.regraded)
                                  FROM {gnrquiz_overview_regrades} qqr
                                 WHERE qqr.questionusageid = gnrquiza.uniqueid
                          ), -1) AS regraded";
            if ($options->onlyregraded) {
                $where .= " AND COALESCE((
                                    SELECT MAX(qqr.regraded)
                                      FROM {gnrquiz_overview_regrades} qqr
                                     WHERE qqr.questionusageid = gnrquiza.uniqueid
                                ), -1) <> -1";
            }
            $table->set_sql($fields, $from, $where, $params);

            if (!$table->is_downloading()) {
                // Output the regrade buttons.
                if (has_capability('mod/gnrquiz:regrade', $this->context)) {
                    $regradesneeded = $this->count_question_attempts_needing_regrade(
                            $gnrquiz, $groupstudents);
                    if ($currentgroup) {
                        $a= new stdClass();
                        $a->groupname = groups_get_group_name($currentgroup);
                        $a->coursestudents = get_string('participants');
                        $a->countregradeneeded = $regradesneeded;
                        $regradealldrydolabel =
                                get_string('regradealldrydogroup', 'gnrquiz_overview', $a);
                        $regradealldrylabel =
                                get_string('regradealldrygroup', 'gnrquiz_overview', $a);
                        $regradealllabel =
                                get_string('regradeallgroup', 'gnrquiz_overview', $a);
                    } else {
                        $regradealldrydolabel =
                                get_string('regradealldrydo', 'gnrquiz_overview', $regradesneeded);
                        $regradealldrylabel =
                                get_string('regradealldry', 'gnrquiz_overview');
                        $regradealllabel =
                                get_string('regradeall', 'gnrquiz_overview');
                    }
                    $displayurl = new moodle_url($options->get_url(), array('sesskey' => sesskey()));
                    echo '<div class="mdl-align">';
                    echo '<form action="'.$displayurl->out_omit_querystring().'">';
                    echo '<div>';
                    echo html_writer::input_hidden_params($displayurl);
                    echo '<input type="submit" name="regradeall" value="'.$regradealllabel.'"/>';
                    echo '<input type="submit" name="regradealldry" value="' .
                            $regradealldrylabel . '"/>';
                    if ($regradesneeded) {
                        echo '<input type="submit" name="regradealldrydo" value="' .
                                $regradealldrydolabel . '"/>';
                    }
                    echo '</div>';
                    echo '</form>';
                    echo '</div>';
                }
                // Print information on the grading method.
                if ($strattempthighlight = gnrquiz_report_highlighting_grading_method(
                        $gnrquiz, $this->qmsubselect, $options->onlygraded)) {
                    echo '<div class="gnrquizattemptcounts">' . $strattempthighlight . '</div>';
                }
            }

            // Define table columns.
            $columns = array();
            $headers = array();

            if (!$table->is_downloading() && $options->checkboxcolumn) {
                $columns[] = 'checkbox';
                $headers[] = null;
            }

            $this->add_user_columns($table, $columns, $headers);
            $this->add_state_column($columns, $headers);
            $this->add_time_columns($columns, $headers);

            $this->add_grade_columns($gnrquiz, $options->usercanseegrades, $columns, $headers, false);

            if (!$table->is_downloading() && has_capability('mod/gnrquiz:regrade', $this->context) &&
                    $this->has_regraded_questions($from, $where, $params)) {
                $columns[] = 'regraded';
                $headers[] = get_string('regrade', 'gnrquiz_overview');
            }

            if ($options->slotmarks) {
                foreach ($questions as $slot => $question) {
                    // Ignore questions of zero length.
                    $columns[] = 'qsgrade' . $slot;
                    $header = get_string('qbrief', 'gnrquiz', $question->number);
                    if (!$table->is_downloading()) {
                        $header .= '<br />';
                    } else {
                        $header .= ' ';
                    }
                    $header .= '/' . gnrquiz_rescale_grade($question->maxmark, $gnrquiz, 'question');
                    $headers[] = $header;
                }
            }

            $this->set_up_table_columns($table, $columns, $headers, $this->get_base_url(), $options, false);
            $table->set_attribute('class', 'generaltable generalbox grades');

            $table->out($options->pagesize, true);
        }

        if (!$table->is_downloading() && $options->usercanseegrades) {
            $output = $PAGE->get_renderer('mod_gnrquiz');
            if ($currentgroup && $groupstudents) {
                list($usql, $params) = $DB->get_in_or_equal($groupstudents);
                $params[] = $gnrquiz->id;
                if ($DB->record_exists_select('gnrquiz_grades', "userid $usql AND gnrquiz = ?",
                        $params)) {
                    $imageurl = new moodle_url('/mod/gnrquiz/report/overview/overviewgraph.php',
                            array('id' => $gnrquiz->id, 'groupid' => $currentgroup));
                    $graphname = get_string('overviewreportgraphgroup', 'gnrquiz_overview',
                            groups_get_group_name($currentgroup));
                    echo $output->graph($imageurl, $graphname);
                }
            }

            if ($DB->record_exists('gnrquiz_grades', array('gnrquiz'=> $gnrquiz->id))) {
                $imageurl = new moodle_url('/mod/gnrquiz/report/overview/overviewgraph.php',
                        array('id' => $gnrquiz->id));
                $graphname = get_string('overviewreportgraph', 'gnrquiz_overview');
                echo $output->graph($imageurl, $graphname);
            }
        }
        return true;
    }

    protected function process_actions($gnrquiz, $cm, $currentgroup, $groupstudents, $allowed, $redirecturl) {
        parent::process_actions($gnrquiz, $cm, $currentgroup, $groupstudents, $allowed, $redirecturl);

        if (empty($currentgroup) || $groupstudents) {
            if (optional_param('regrade', 0, PARAM_BOOL) && confirm_sesskey()) {
                if ($attemptids = optional_param_array('attemptid', array(), PARAM_INT)) {
                    $this->start_regrade($gnrquiz, $cm);
                    $this->regrade_attempts($gnrquiz, false, $groupstudents, $attemptids);
                    $this->finish_regrade($redirecturl);
                }
            }
        }

        if (optional_param('regradeall', 0, PARAM_BOOL) && confirm_sesskey()) {
            $this->start_regrade($gnrquiz, $cm);
            $this->regrade_attempts($gnrquiz, false, $groupstudents);
            $this->finish_regrade($redirecturl);

        } else if (optional_param('regradealldry', 0, PARAM_BOOL) && confirm_sesskey()) {
            $this->start_regrade($gnrquiz, $cm);
            $this->regrade_attempts($gnrquiz, true, $groupstudents);
            $this->finish_regrade($redirecturl);

        } else if (optional_param('regradealldrydo', 0, PARAM_BOOL) && confirm_sesskey()) {
            $this->start_regrade($gnrquiz, $cm);
            $this->regrade_attempts_needing_it($gnrquiz, $groupstudents);
            $this->finish_regrade($redirecturl);
        }
    }

    /**
     * Check necessary capabilities, and start the display of the regrade progress page.
     * @param object $gnrquiz the gnrquiz settings.
     * @param object $cm the cm object for the gnrquiz.
     */
    protected function start_regrade($gnrquiz, $cm) {
        global $OUTPUT, $PAGE;
        require_capability('mod/gnrquiz:regrade', $this->context);
        $this->print_header_and_tabs($cm, $this->course, $gnrquiz, $this->mode);
    }

    /**
     * Finish displaying the regrade progress page.
     * @param moodle_url $nexturl where to send the user after the regrade.
     * @uses exit. This method never returns.
     */
    protected function finish_regrade($nexturl) {
        global $OUTPUT;
        \core\notification::success(get_string('regradecomplete', 'gnrquiz_overview'));
        echo $OUTPUT->continue_button($nexturl);
        echo $OUTPUT->footer();
        die();
    }

    /**
     * Unlock the session and allow the regrading process to run in the background.
     */
    protected function unlock_session() {
        \core\session\manager::write_close();
        ignore_user_abort(true);
    }

    /**
     * Regrade a particular gnrquiz attempt. Either for real ($dryrun = false), or
     * as a pretend regrade to see which fractions would change. The outcome is
     * stored in the gnrquiz_overview_regrades table.
     *
     * Note, $attempt is not upgraded in the database. The caller needs to do that.
     * However, $attempt->sumgrades is updated, if this is not a dry run.
     *
     * @param object $attempt the gnrquiz attempt to regrade.
     * @param bool $dryrun if true, do a pretend regrade, otherwise do it for real.
     * @param array $slots if null, regrade all questions, otherwise, just regrade
     *      the quetsions with those slots.
     */
    protected function regrade_attempt($attempt, $dryrun = false, $slots = null) {
        global $DB;
        // Need more time for a gnrquiz with many questions.
        core_php_time_limit::raise(300);

        $transaction = $DB->start_delegated_transaction();

        $quba = question_engine::load_questions_usage_by_activity($attempt->uniqueid);

        if (is_null($slots)) {
            $slots = $quba->get_slots();
        }

        $finished = $attempt->state == gnrquiz_attempt::FINISHED;
        foreach ($slots as $slot) {
            $qqr = new stdClass();
            $qqr->oldfraction = $quba->get_question_fraction($slot);

            $quba->regrade_question($slot, $finished);

            $qqr->newfraction = $quba->get_question_fraction($slot);

            if (abs($qqr->oldfraction - $qqr->newfraction) > 1e-7) {
                $qqr->questionusageid = $quba->get_id();
                $qqr->slot = $slot;
                $qqr->regraded = empty($dryrun);
                $qqr->timemodified = time();
                $DB->insert_record('gnrquiz_overview_regrades', $qqr, false);
            }
        }

        if (!$dryrun) {
            question_engine::save_questions_usage_by_activity($quba);
        }

        $transaction->allow_commit();

        // Really, PHP should not need this hint, but without this, we just run out of memory.
        $quba = null;
        $transaction = null;
        gc_collect_cycles();
    }

    /**
     * Regrade attempts for this gnrquiz, exactly which attempts are regraded is
     * controlled by the parameters.
     * @param object $gnrquiz the gnrquiz settings.
     * @param bool $dryrun if true, do a pretend regrade, otherwise do it for real.
     * @param array $groupstudents blank for all attempts, otherwise regrade attempts
     * for these users.
     * @param array $attemptids blank for all attempts, otherwise only regrade
     * attempts whose id is in this list.
     */
    protected function regrade_attempts($gnrquiz, $dryrun = false,
            $groupstudents = array(), $attemptids = array()) {
        global $DB;
        $this->unlock_session();

        $where = "gnrquiz = ? AND preview = 0";
        $params = array($gnrquiz->id);

        if ($groupstudents) {
            list($usql, $uparams) = $DB->get_in_or_equal($groupstudents);
            $where .= " AND userid $usql";
            $params = array_merge($params, $uparams);
        }

        if ($attemptids) {
            list($asql, $aparams) = $DB->get_in_or_equal($attemptids);
            $where .= " AND id $asql";
            $params = array_merge($params, $aparams);
        }

        $attempts = $DB->get_records_select('gnrquiz_attempts', $where, $params);
        if (!$attempts) {
            return;
        }

        $this->clear_regrade_table($gnrquiz, $groupstudents);

        $progressbar = new progress_bar('gnrquiz_overview_regrade', 500, true);
        $a = array(
            'count' => count($attempts),
            'done'  => 0,
        );
        foreach ($attempts as $attempt) {
            $this->regrade_attempt($attempt, $dryrun);
            $a['done']++;
            $progressbar->update($a['done'], $a['count'],
                    get_string('regradingattemptxofy', 'gnrquiz_overview', $a));
        }

        if (!$dryrun) {
            $this->update_overall_grades($gnrquiz);
        }
    }

    /**
     * Regrade those questions in those attempts that are marked as needing regrading
     * in the gnrquiz_overview_regrades table.
     * @param object $gnrquiz the gnrquiz settings.
     * @param array $groupstudents blank for all attempts, otherwise regrade attempts
     * for these users.
     */
    protected function regrade_attempts_needing_it($gnrquiz, $groupstudents) {
        global $DB;
        $this->unlock_session();

        $where = "gnrquiza.gnrquiz = ? AND gnrquiza.preview = 0 AND qqr.regraded = 0";
        $params = array($gnrquiz->id);

        // Fetch all attempts that need regrading.
        if ($groupstudents) {
            list($usql, $uparams) = $DB->get_in_or_equal($groupstudents);
            $where .= " AND gnrquiza.userid $usql";
            $params = array_merge($params, $uparams);
        }

        $toregrade = $DB->get_recordset_sql("
                SELECT gnrquiza.uniqueid, qqr.slot
                FROM {gnrquiz_attempts} gnrquiza
                JOIN {gnrquiz_overview_regrades} qqr ON qqr.questionusageid = gnrquiza.uniqueid
                WHERE $where", $params);

        $attemptquestions = array();
        foreach ($toregrade as $row) {
            $attemptquestions[$row->uniqueid][] = $row->slot;
        }
        $toregrade->close();

        if (!$attemptquestions) {
            return;
        }

        $attempts = $DB->get_records_list('gnrquiz_attempts', 'uniqueid',
                array_keys($attemptquestions));

        $this->clear_regrade_table($gnrquiz, $groupstudents);

        $progressbar = new progress_bar('gnrquiz_overview_regrade', 500, true);
        $a = array(
            'count' => count($attempts),
            'done'  => 0,
        );
        foreach ($attempts as $attempt) {
            $this->regrade_attempt($attempt, false, $attemptquestions[$attempt->uniqueid]);
            $a['done']++;
            $progressbar->update($a['done'], $a['count'],
                    get_string('regradingattemptxofy', 'gnrquiz_overview', $a));
        }

        $this->update_overall_grades($gnrquiz);
    }

    /**
     * Count the number of attempts in need of a regrade.
     * @param object $gnrquiz the gnrquiz settings.
     * @param array $groupstudents user ids. If this is given, only data relating
     * to these users is cleared.
     */
    protected function count_question_attempts_needing_regrade($gnrquiz, $groupstudents) {
        global $DB;

        $usertest = '';
        $params = array();
        if ($groupstudents) {
            list($usql, $params) = $DB->get_in_or_equal($groupstudents);
            $usertest = "gnrquiza.userid $usql AND ";
        }

        $params[] = $gnrquiz->id;
        $sql = "SELECT COUNT(DISTINCT gnrquiza.id)
                FROM {gnrquiz_attempts} gnrquiza
                JOIN {gnrquiz_overview_regrades} qqr ON gnrquiza.uniqueid = qqr.questionusageid
                WHERE
                    $usertest
                    gnrquiza.gnrquiz = ? AND
                    gnrquiza.preview = 0 AND
                    qqr.regraded = 0";
        return $DB->count_records_sql($sql, $params);
    }

    /**
     * Are there any pending regrades in the table we are going to show?
     * @param string $from tables used by the main query.
     * @param string $where where clause used by the main query.
     * @param array $params required by the SQL.
     * @return bool whether there are pending regrades.
     */
    protected function has_regraded_questions($from, $where, $params) {
        global $DB;
        return $DB->record_exists_sql("
                SELECT 1
                  FROM {$from}
                  JOIN {gnrquiz_overview_regrades} qor ON qor.questionusageid = gnrquiza.uniqueid
                 WHERE {$where}", $params);
    }

    /**
     * Remove all information about pending/complete regrades from the database.
     * @param object $gnrquiz the gnrquiz settings.
     * @param array $groupstudents user ids. If this is given, only data relating
     * to these users is cleared.
     */
    protected function clear_regrade_table($gnrquiz, $groupstudents) {
        global $DB;

        // Fetch all attempts that need regrading.
        $where = '';
        $params = array();
        if ($groupstudents) {
            list($usql, $params) = $DB->get_in_or_equal($groupstudents);
            $where = "userid $usql AND ";
        }

        $params[] = $gnrquiz->id;
        $DB->delete_records_select('gnrquiz_overview_regrades',
                "questionusageid IN (
                    SELECT uniqueid
                    FROM {gnrquiz_attempts}
                    WHERE $where gnrquiz = ?
                )", $params);
    }

    /**
     * Update the final grades for all attempts. This method is used following
     * a regrade.
     * @param object $gnrquiz the gnrquiz settings.
     * @param array $userids only update scores for these userids.
     * @param array $attemptids attemptids only update scores for these attempt ids.
     */
    protected function update_overall_grades($gnrquiz) {
        gnrquiz_update_all_attempt_sumgrades($gnrquiz);
        gnrquiz_update_all_final_grades($gnrquiz);
        gnrquiz_update_grades($gnrquiz);
    }
}
