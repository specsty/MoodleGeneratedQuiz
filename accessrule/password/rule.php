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
 * Implementaton of the gnrquizaccess_password plugin.
 *
 * @package    gnrquizaccess
 * @subpackage password
 * @copyright  2011 The Open University
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */


defined('MOODLE_INTERNAL') || die();

require_once($CFG->dirroot . '/mod/gnrquiz/accessrule/accessrulebase.php');


/**
 * A rule implementing the password check.
 *
 * @copyright  2009 Tim Hunt
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class gnrquizaccess_password extends gnrquiz_access_rule_base {

    public static function make(gnrquiz $gnrquizobj, $timenow, $canignoretimelimits) {
        if (empty($gnrquizobj->get_gnrquiz()->password)) {
            return null;
        }

        return new self($gnrquizobj, $timenow);
    }

    public function description() {
        return get_string('requirepasswordmessage', 'gnrquizaccess_password');
    }

    public function is_preflight_check_required($attemptid) {
        global $SESSION;
        return empty($SESSION->passwordcheckedgnrquizzes[$this->gnrquiz->id]);
    }

    public function add_preflight_check_form_fields(mod_gnrquiz_preflight_check_form $gnrquizform,
            MoodleQuickForm $mform, $attemptid) {

        $mform->addElement('header', 'passwordheader', get_string('password'));
        $mform->addElement('static', 'passwordmessage', '',
                get_string('requirepasswordmessage', 'gnrquizaccess_password'));

        // Don't use the 'proper' field name of 'password' since that get's
        // Firefox's password auto-complete over-excited.
        $mform->addElement('password', 'gnrquizpassword',
                get_string('gnrquizpassword', 'gnrquizaccess_password'), array('autofocus' => 'true'));
    }

    public function validate_preflight_check($data, $files, $errors, $attemptid) {

        $enteredpassword = $data['gnrquizpassword'];
        if (strcmp($this->gnrquiz->password, $enteredpassword) === 0) {
            return $errors; // Password is OK.

        } else if (isset($this->gnrquiz->extrapasswords)) {
            // Group overrides may have additional passwords.
            foreach ($this->gnrquiz->extrapasswords as $password) {
                if (strcmp($password, $enteredpassword) === 0) {
                    return $errors; // Password is OK.
                }
            }
        }

        $errors['gnrquizpassword'] = get_string('passworderror', 'gnrquizaccess_password');
        return $errors;
    }

    public function notify_preflight_check_passed($attemptid) {
        global $SESSION;
        $SESSION->passwordcheckedgnrquizzes[$this->gnrquiz->id] = true;
    }

    public function current_attempt_finished() {
        global $SESSION;
        // Clear the flag in the session that says that the user has already
        // entered the password for this gnrquiz.
        if (!empty($SESSION->passwordcheckedgnrquizzes[$this->gnrquiz->id])) {
            unset($SESSION->passwordcheckedgnrquizzes[$this->gnrquiz->id]);
        }
    }
}
