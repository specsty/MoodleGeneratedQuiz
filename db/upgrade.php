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
 * Upgrade script for the gnrquiz module.
 *
 * @package    mod_gnrquiz
 * @copyright  2006 Eloy Lafuente (stronk7)
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

/**
 * Quiz module upgrade function.
 * @param string $oldversion the version we are upgrading from.
 */
function xmldb_gnrquiz_upgrade($oldversion) {
    global $CFG, $DB;

    $dbman = $DB->get_manager();

    if ($oldversion < 2014052800) {

        // Define field completionattemptsexhausted to be added to gnrquiz.
        $table = new xmldb_table('gnrquiz');
        $field = new xmldb_field('completionattemptsexhausted', XMLDB_TYPE_INTEGER, '1', null, null, null, '0', 'showblocks');

        // Conditionally launch add field completionattemptsexhausted.
        if (!$dbman->field_exists($table, $field)) {
            $dbman->add_field($table, $field);
        }
        // Quiz savepoint reached.
        upgrade_mod_savepoint(true, 2014052800, 'gnrquiz');
    }

    if ($oldversion < 2014052801) {
        // Define field completionpass to be added to gnrquiz.
        $table = new xmldb_table('gnrquiz');
        $field = new xmldb_field('completionpass', XMLDB_TYPE_INTEGER, '1', null, null, null, 0, 'completionattemptsexhausted');

        // Conditionally launch add field completionpass.
        if (!$dbman->field_exists($table, $field)) {
            $dbman->add_field($table, $field);
        }

        // Quiz savepoint reached.
        upgrade_mod_savepoint(true, 2014052801, 'gnrquiz');
    }

    // Moodle v2.8.0 release upgrade line.
    // Put any upgrade step following this.

    if ($oldversion < 2015030500) {
        // Define field requireprevious to be added to gnrquiz_slots.
        $table = new xmldb_table('gnrquiz_slots');
        $field = new xmldb_field('requireprevious', XMLDB_TYPE_INTEGER, '4', null, XMLDB_NOTNULL, null, 0, 'page');

        // Conditionally launch add field page.
        if (!$dbman->field_exists($table, $field)) {
            $dbman->add_field($table, $field);
        }

        // Quiz savepoint reached.
        upgrade_mod_savepoint(true, 2015030500, 'gnrquiz');
    }

    if ($oldversion < 2015030900) {
        // Define field canredoquestions to be added to gnrquiz.
        $table = new xmldb_table('gnrquiz');
        $field = new xmldb_field('canredoquestions', XMLDB_TYPE_INTEGER, '4', null, XMLDB_NOTNULL, null, 0, 'preferredbehaviour');

        // Conditionally launch add field completionpass.
        if (!$dbman->field_exists($table, $field)) {
            $dbman->add_field($table, $field);
        }

        // Quiz savepoint reached.
        upgrade_mod_savepoint(true, 2015030900, 'gnrquiz');
    }

    if ($oldversion < 2015032300) {

        // Define table gnrquiz_sections to be created.
        $table = new xmldb_table('gnrquiz_sections');

        // Adding fields to table gnrquiz_sections.
        $table->add_field('id', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, XMLDB_SEQUENCE, null);
        $table->add_field('gnrquizid', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, null);
        $table->add_field('firstslot', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, null);
        $table->add_field('heading', XMLDB_TYPE_CHAR, '1333', null, null, null, null);
        $table->add_field('shufflequestions', XMLDB_TYPE_INTEGER, '4', null, XMLDB_NOTNULL, null, '0');

        // Adding keys to table gnrquiz_sections.
        $table->add_key('primary', XMLDB_KEY_PRIMARY, array('id'));
        $table->add_key('gnrquizid', XMLDB_KEY_FOREIGN, array('gnrquizid'), 'gnrquiz', array('id'));

        // Adding indexes to table gnrquiz_sections.
        $table->add_index('gnrquizid-firstslot', XMLDB_INDEX_UNIQUE, array('gnrquizid', 'firstslot'));

        // Conditionally launch create table for gnrquiz_sections.
        if (!$dbman->table_exists($table)) {
            $dbman->create_table($table);
        }

        // Quiz savepoint reached.
        upgrade_mod_savepoint(true, 2015032300, 'gnrquiz');
    }

    if ($oldversion < 2015032301) {

        // Create a section for each gnrquiz.
        $DB->execute("
                INSERT INTO {gnrquiz_sections}
                            (gnrquizid, firstslot, heading, shufflequestions)
                     SELECT  id,     1,         ?,       shufflequestions
                       FROM {gnrquiz}
                ", array(''));

        // Quiz savepoint reached.
        upgrade_mod_savepoint(true, 2015032301, 'gnrquiz');
    }

    if ($oldversion < 2015032302) {

        // Define field shufflequestions to be dropped from gnrquiz.
        $table = new xmldb_table('gnrquiz');
        $field = new xmldb_field('shufflequestions');

        // Conditionally launch drop field shufflequestions.
        if ($dbman->field_exists($table, $field)) {
            $dbman->drop_field($table, $field);
        }

        // Quiz savepoint reached.
        upgrade_mod_savepoint(true, 2015032302, 'gnrquiz');
    }

    if ($oldversion < 2015032303) {

        // Drop corresponding admin settings.
        unset_config('shufflequestions', 'gnrquiz');
        unset_config('shufflequestions_adv', 'gnrquiz');

        // Quiz savepoint reached.
        upgrade_mod_savepoint(true, 2015032303, 'gnrquiz');
    }

    // Moodle v2.9.0 release upgrade line.
    // Put any upgrade step following this.

    // Moodle v3.0.0 release upgrade line.
    // Put any upgrade step following this.

    if ($oldversion < 2016032600) {
        // Update gnrquiz_sections to repair gnrquizzes what were broken by MDL-53507.
        $problemgnrquizzes = $DB->get_records_sql("
                SELECT gnrquizid, MIN(firstslot) AS firstsectionfirstslot
                FROM {gnrquiz_sections}
                GROUP BY gnrquizid
                HAVING MIN(firstslot) > 1");

        if ($problemgnrquizzes) {
            $pbar = new progress_bar('upgradegnrquizfirstsection', 500, true);
            $total = count($problemgnrquizzes);
            $done = 0;
            foreach ($problemgnrquizzes as $problemgnrquiz) {
                $DB->set_field('gnrquiz_sections', 'firstslot', 1,
                        array('gnrquizid' => $problemgnrquiz->gnrquizid,
                        'firstslot' => $problemgnrquiz->firstsectionfirstslot));
                $done += 1;
                $pbar->update($done, $total, "Fixing gnrquiz layouts - {$done}/{$total}.");
            }
        }

        // Quiz savepoint reached.
        upgrade_mod_savepoint(true, 2016032600, 'gnrquiz');
    }

    // Moodle v3.1.0 release upgrade line.
    // Put any upgrade step following this.

    if ($oldversion < 2016052301) {
        // Find gnrquizzes with the combination of require passing grade and grade to pass 0.
        $gradeitems = $DB->get_records_sql("
            SELECT gi.id, gi.itemnumber, cm.id AS cmid
              FROM {gnrquiz} q
        INNER JOIN {course_modules} cm ON q.id = cm.instance
        INNER JOIN {grade_items} gi ON q.id = gi.iteminstance
        INNER JOIN {modules} m ON m.id = cm.module
             WHERE q.completionpass = 1
               AND gi.gradepass = 0
               AND cm.completiongradeitemnumber IS NULL
               AND gi.itemmodule = m.name
               AND gi.itemtype = ?
               AND m.name = ?", array('mod', 'gnrquiz'));

        foreach ($gradeitems as $gradeitem) {
            $DB->execute("UPDATE {course_modules}
                             SET completiongradeitemnumber = :itemnumber
                           WHERE id = :cmid",
                array('itemnumber' => $gradeitem->itemnumber, 'cmid' => $gradeitem->cmid));
        }
        // Quiz savepoint reached.
        upgrade_mod_savepoint(true, 2016052301, 'gnrquiz');
    }

    return true;
}
