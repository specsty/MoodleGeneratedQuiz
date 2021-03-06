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
 * @package    mod_gnrquiz
 * @subpackage backup-moodle2
 * @copyright  2010 onwards Eloy Lafuente (stronk7) {@link http://stronk7.com}
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */


defined('MOODLE_INTERNAL') || die();

require_once($CFG->dirroot . '/mod/gnrquiz/backup/moodle2/restore_gnrquiz_stepslib.php');


/**
 * gnrquiz restore task that provides all the settings and steps to perform one
 * complete restore of the activity
 *
 * @copyright  2010 onwards Eloy Lafuente (stronk7) {@link http://stronk7.com}
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class restore_gnrquiz_activity_task extends restore_activity_task {

    /**
     * Define (add) particular settings this activity can have
     */
    protected function define_my_settings() {
        // No particular settings for this activity.
    }

    /**
     * Define (add) particular steps this activity can have
     */
    protected function define_my_steps() {
        // Quiz only has one structure step.
        $this->add_step(new restore_gnrquiz_activity_structure_step('gnrquiz_structure', 'gnrquiz.xml'));
    }

    /**
     * Define the contents in the activity that must be
     * processed by the link decoder
     */
    public static function define_decode_contents() {
        $contents = array();

        $contents[] = new restore_decode_content('gnrquiz', array('intro'), 'gnrquiz');
        $contents[] = new restore_decode_content('gnrquiz_feedback',
                array('feedbacktext'), 'gnrquiz_feedback');

        return $contents;
    }

    /**
     * Define the decoding rules for links belonging
     * to the activity to be executed by the link decoder
     */
    public static function define_decode_rules() {
        $rules = array();

        $rules[] = new restore_decode_rule('GNRQUIZVIEWBYID',
                '/mod/gnrquiz/view.php?id=$1', 'course_module');
        $rules[] = new restore_decode_rule('GNRQUIZVIEWBYQ',
                '/mod/gnrquiz/view.php?q=$1', 'gnrquiz');
        $rules[] = new restore_decode_rule('GNRQUIZINDEX',
                '/mod/gnrquiz/index.php?id=$1', 'course');

        return $rules;

    }

    /**
     * Define the restore log rules that will be applied
     * by the {@link restore_logs_processor} when restoring
     * gnrquiz logs. It must return one array
     * of {@link restore_log_rule} objects
     */
    public static function define_restore_log_rules() {
        $rules = array();

        $rules[] = new restore_log_rule('gnrquiz', 'add',
                'view.php?id={course_module}', '{gnrquiz}');
        $rules[] = new restore_log_rule('gnrquiz', 'update',
                'view.php?id={course_module}', '{gnrquiz}');
        $rules[] = new restore_log_rule('gnrquiz', 'view',
                'view.php?id={course_module}', '{gnrquiz}');
        $rules[] = new restore_log_rule('gnrquiz', 'preview',
                'view.php?id={course_module}', '{gnrquiz}');
        $rules[] = new restore_log_rule('gnrquiz', 'report',
                'report.php?id={course_module}', '{gnrquiz}');
        $rules[] = new restore_log_rule('gnrquiz', 'editquestions',
                'view.php?id={course_module}', '{gnrquiz}');
        $rules[] = new restore_log_rule('gnrquiz', 'delete attempt',
                'report.php?id={course_module}', '[oldattempt]');
        $rules[] = new restore_log_rule('gnrquiz', 'edit override',
                'overrideedit.php?id={gnrquiz_override}', '{gnrquiz}');
        $rules[] = new restore_log_rule('gnrquiz', 'delete override',
                'overrides.php.php?cmid={course_module}', '{gnrquiz}');
        $rules[] = new restore_log_rule('gnrquiz', 'addcategory',
                'view.php?id={course_module}', '{question_category}');
        $rules[] = new restore_log_rule('gnrquiz', 'view summary',
                'summary.php?attempt={gnrquiz_attempt}', '{gnrquiz}');
        $rules[] = new restore_log_rule('gnrquiz', 'manualgrade',
                'comment.php?attempt={gnrquiz_attempt}&question={question}', '{gnrquiz}');
        $rules[] = new restore_log_rule('gnrquiz', 'manualgrading',
                'report.php?mode=grading&q={gnrquiz}', '{gnrquiz}');
        // All the ones calling to review.php have two rules to handle both old and new urls
        // in any case they are always converted to new urls on restore.
        // TODO: In Moodle 2.x (x >= 5) kill the old rules.
        // Note we are using the 'gnrquiz_attempt' mapping because that is the
        // one containing the gnrquiz_attempt->ids old an new for gnrquiz-attempt.
        $rules[] = new restore_log_rule('gnrquiz', 'attempt',
                'review.php?id={course_module}&attempt={gnrquiz_attempt}', '{gnrquiz}',
                null, null, 'review.php?attempt={gnrquiz_attempt}');
        $rules[] = new restore_log_rule('gnrquiz', 'attempt',
                'review.php?attempt={gnrquiz_attempt}', '{gnrquiz}',
                null, null, 'review.php?attempt={gnrquiz_attempt}');
        // Old an new for gnrquiz-submit.
        $rules[] = new restore_log_rule('gnrquiz', 'submit',
                'review.php?id={course_module}&attempt={gnrquiz_attempt}', '{gnrquiz}',
                null, null, 'review.php?attempt={gnrquiz_attempt}');
        $rules[] = new restore_log_rule('gnrquiz', 'submit',
                'review.php?attempt={gnrquiz_attempt}', '{gnrquiz}');
        // Old an new for gnrquiz-review.
        $rules[] = new restore_log_rule('gnrquiz', 'review',
                'review.php?id={course_module}&attempt={gnrquiz_attempt}', '{gnrquiz}',
                null, null, 'review.php?attempt={gnrquiz_attempt}');
        $rules[] = new restore_log_rule('gnrquiz', 'review',
                'review.php?attempt={gnrquiz_attempt}', '{gnrquiz}');
        // Old an new for gnrquiz-start attemp.
        $rules[] = new restore_log_rule('gnrquiz', 'start attempt',
                'review.php?id={course_module}&attempt={gnrquiz_attempt}', '{gnrquiz}',
                null, null, 'review.php?attempt={gnrquiz_attempt}');
        $rules[] = new restore_log_rule('gnrquiz', 'start attempt',
                'review.php?attempt={gnrquiz_attempt}', '{gnrquiz}');
        // Old an new for gnrquiz-close attemp.
        $rules[] = new restore_log_rule('gnrquiz', 'close attempt',
                'review.php?id={course_module}&attempt={gnrquiz_attempt}', '{gnrquiz}',
                null, null, 'review.php?attempt={gnrquiz_attempt}');
        $rules[] = new restore_log_rule('gnrquiz', 'close attempt',
                'review.php?attempt={gnrquiz_attempt}', '{gnrquiz}');
        // Old an new for gnrquiz-continue attempt.
        $rules[] = new restore_log_rule('gnrquiz', 'continue attempt',
                'review.php?id={course_module}&attempt={gnrquiz_attempt}', '{gnrquiz}',
                null, null, 'review.php?attempt={gnrquiz_attempt}');
        $rules[] = new restore_log_rule('gnrquiz', 'continue attempt',
                'review.php?attempt={gnrquiz_attempt}', '{gnrquiz}');
        // Old an new for gnrquiz-continue attemp.
        $rules[] = new restore_log_rule('gnrquiz', 'continue attemp',
                'review.php?id={course_module}&attempt={gnrquiz_attempt}', '{gnrquiz}',
                null, 'continue attempt', 'review.php?attempt={gnrquiz_attempt}');
        $rules[] = new restore_log_rule('gnrquiz', 'continue attemp',
                'review.php?attempt={gnrquiz_attempt}', '{gnrquiz}',
                null, 'continue attempt');

        return $rules;
    }

    /**
     * Define the restore log rules that will be applied
     * by the {@link restore_logs_processor} when restoring
     * course logs. It must return one array
     * of {@link restore_log_rule} objects
     *
     * Note this rules are applied when restoring course logs
     * by the restore final task, but are defined here at
     * activity level. All them are rules not linked to any module instance (cmid = 0)
     */
    public static function define_restore_log_rules_for_course() {
        $rules = array();

        $rules[] = new restore_log_rule('gnrquiz', 'view all', 'index.php?id={course}', null);

        return $rules;
    }
}
