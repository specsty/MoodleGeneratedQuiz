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
 * This page is the entry page into the gnrquiz UI. Displays information about the
 * gnrquiz to students and teachers, and lets students see their previous attempts.
 *
 * @package   mod_gnrquiz
 * @category  grade
 * @copyright 1999 onwards Martin Dougiamas  {@link http://moodle.com}
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */


require_once(dirname(__FILE__) . '/../../config.php');
require_once($CFG->dirroot . '/mod/gnrquiz/locallib.php');
require_once($CFG->dirroot . '/mod/gnrquiz/report/reportlib.php');


$id = required_param('id', PARAM_INT);
$userid = optional_param('userid', 0, PARAM_INT);

$cm = get_coursemodule_from_id('gnrquiz', $id, 0, false, MUST_EXIST);
$course = $DB->get_record('course', array('id' => $cm->course), '*', MUST_EXIST);
$gnrquiz = $DB->get_record('gnrquiz', array('id' => $cm->instance), '*', MUST_EXIST);
require_login($course, false, $cm);

$reportlist = gnrquiz_report_list(context_module::instance($cm->id));
if (empty($reportlist) || $userid == $USER->id) {
    // If the user cannot see reports, or can see reports but is looking
    // at their own grades, redirect them to the view.php page.
    // (The looking at their own grades case is unlikely, since users who
    // appear in the gradebook are unlikely to be able to see gnrquiz reports,
    // but it is possible.)
    redirect(new moodle_url('/mod/gnrquiz/view.php', array('id' => $cm->id)));
}

// Now we know the user is interested in reports. If they are interested in a
// specific other user, try to send them to the most appropriate attempt review page.
if ($userid) {

    // Work out which attempt is most significant from a grading point of view.
    $attempts = gnrquiz_get_user_attempts($gnrquiz->id, $userid, 'finished');
    $attempt = null;
    switch ($gnrquiz->grademethod) {
        case GNRQUIZ_ATTEMPTFIRST:
            $attempt = reset($attempts);
            break;

        case GNRQUIZ_ATTEMPTLAST:
        case GNRQUIZ_GRADEAVERAGE:
            $attempt = end($attempts);
            break;

        case GNRQUIZ_GRADEHIGHEST:
            $maxmark = 0;
            foreach ($attempts as $at) {
                // Operator >=, since we want to most recent relevant attempt.
                if ((float) $at->sumgrades >= $maxmark) {
                    $maxmark = $at->sumgrades;
                    $attempt = $at;
                }
            }
            break;
    }

    // If the user can review the relevant attempt, redirect to it.
    if ($attempt) {
        $attemptobj = new gnrquiz_attempt($attempt, $gnrquiz, $cm, $course, false);
        if ($attemptobj->is_review_allowed()) {
            redirect($attemptobj->review_url());
        }
    }

    // Otherwise, fall thorugh to the generic case.
}

// Send the user to the first report they can see.
redirect(new moodle_url('/mod/gnrquiz/report.php', array(
        'id' => $cm->id, 'mode' => reset($reportlist))));
