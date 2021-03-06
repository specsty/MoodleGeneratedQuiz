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
 * The mod_gnrquiz user override created event.
 *
 * @package    mod_gnrquiz
 * @copyright  2014 Mark Nelson <markn@moodle.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
namespace mod_gnrquiz\event;

defined('MOODLE_INTERNAL') || die();

/**
 * The mod_gnrquiz user override created event class.
 *
 * @property-read array $other {
 *      Extra information about event.
 *
 *      - int gnrquizid: the id of the gnrquiz.
 * }
 *
 * @package    mod_gnrquiz
 * @since      Moodle 2.7
 * @copyright  2014 Mark Nelson <markn@moodle.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class user_override_created extends \core\event\base {

    /**
     * Init method.
     */
    protected function init() {
        $this->data['objecttable'] = 'gnrquiz_overrides';
        $this->data['crud'] = 'c';
        $this->data['edulevel'] = self::LEVEL_TEACHING;
    }

    /**
     * Returns localised general event name.
     *
     * @return string
     */
    public static function get_name() {
        return get_string('eventoverridecreated', 'mod_gnrquiz');
    }

    /**
     * Returns description of what happened.
     *
     * @return string
     */
    public function get_description() {
        return "The user with id '$this->userid' created the override with id '$this->objectid' for the gnrquiz with " .
            "course module id '$this->contextinstanceid' for the user with id '{$this->relateduserid}'.";
    }

    /**
     * Returns relevant URL.
     *
     * @return \moodle_url
     */
    public function get_url() {
        return new \moodle_url('/mod/gnrquiz/overrideedit.php', array('id' => $this->objectid));
    }

    /**
     * Custom validation.
     *
     * @throws \coding_exception
     * @return void
     */
    protected function validate_data() {
        parent::validate_data();

        if (!isset($this->relateduserid)) {
            throw new \coding_exception('The \'relateduserid\' must be set.');
        }

        if (!isset($this->other['gnrquizid'])) {
            throw new \coding_exception('The \'gnrquizid\' value must be set in other.');
        }
    }

    public static function get_objectid_mapping() {
        return array('db' => 'gnrquiz_overrides', 'restore' => 'gnrquiz_override');
    }

    public static function get_other_mapping() {
        $othermapped = array();
        $othermapped['gnrquizid'] = array('db' => 'gnrquiz', 'restore' => 'gnrquiz');

        return $othermapped;
    }
}
