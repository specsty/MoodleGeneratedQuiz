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
 * This file defines the setting form for the gnrquiz overview report.
 *
 * @package   gnrquiz_overview
 * @copyright 2008 Jamie Pratt
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */


defined('MOODLE_INTERNAL') || die();

require_once($CFG->dirroot . '/mod/gnrquiz/report/attemptsreport_form.php');


/**
 * Quiz overview report settings form.
 *
 * @copyright 2008 Jamie Pratt
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class gnrquiz_overview_settings_form extends mod_gnrquiz_attempts_report_form {

    protected function other_attempt_fields(MoodleQuickForm $mform) {
        if (has_capability('mod/gnrquiz:regrade', $this->_customdata['context'])) {
            $mform->addElement('advcheckbox', 'onlyregraded', get_string('reportshowonly', 'gnrquiz'),
                    get_string('optonlyregradedattempts', 'gnrquiz_overview'));
            $mform->disabledIf('onlyregraded', 'attempts', 'eq', gnrquiz_attempts_report::ENROLLED_WITHOUT);
        }
    }

    protected function other_preference_fields(MoodleQuickForm $mform) {
        if (gnrquiz_has_grades($this->_customdata['gnrquiz'])) {
            $mform->addElement('selectyesno', 'slotmarks',
                    get_string('showdetailedmarks', 'gnrquiz_overview'));
        } else {
            $mform->addElement('hidden', 'slotmarks', 0);
            $mform->setType('slotmarks', PARAM_INT);
        }
    }
}
