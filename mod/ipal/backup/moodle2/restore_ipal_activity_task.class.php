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
 * The class with functions to start the restore of an ipal instance
 *
 * @package mod_ipal
 * @subpackage backup-moodle2
 * @copyright 2012 onwards Eckerd College {@http://www.eckerd.edu}
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

require_once($CFG->dirroot . '/mod/ipal/backup/moodle2/restore_ipal_stepslib.php');

/**
 * This class has functions to start the restore process.
 * @copyright  2012 W. F. Junkin, Eckerd College, http://www.eckerd.edu
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class restore_ipal_activity_task extends restore_activity_task {


    /**
     * A function required for this class but not used here.
     */
    protected function define_my_settings() {
    }


    /**
     * A function to define the steps for the restore process.
     */
    protected function define_my_steps() {
        $this->add_step(new restore_ipal_activity_structure_step('ipal_structure', 'ipal.xml'));
    }


    /**
     * A function to decode some of the ipal information during the restore process.
     */
    static public function define_decode_contents() {
        $contents = array();

        $contents[] = new restore_decode_content('ipal', array('intro'), 'ipal');

        return $contents;
    }


    /**
     * A function to get the restore rules for an ipal instance
     */
    static public function define_decode_rules() {
        $rules = array();

        $rules[] = new restore_decode_rule('IPALVIEWBYID', '/mod/ipal/view.php?id=$1', 'course_module');
        $rules[] = new restore_decode_rule('IPALINDEX', '/mod/ipal/index.php?id=$1', 'course');

        return $rules;

    }


    /**
     * Function to restore the log rules.
     */
    static public function define_restore_log_rules() {
        $rules = array();

        $rules[] = new restore_log_rule('ipal', 'add', 'view.php?id={course_module}', '{ipal}');
        $rules[] = new restore_log_rule('ipal', 'update', 'view.php?id={course_module}', '{ipal}');
        $rules[] = new restore_log_rule('ipal', 'view', 'view.php?id={course_module}', '{ipal}');
        $rules[] = new restore_log_rule('ipal', 'choose', 'view.php?id={course_module}', '{ipal}');
        $rules[] = new restore_log_rule('ipal', 'choose again', 'view.php?id={course_module}', '{ipal}');
        $rules[] = new restore_log_rule('ipal', 'report', 'report.php?id={course_module}', '{ipal}');

        return $rules;
    }


    /**
     * Another function related to the log.
     */
    static public function define_restore_log_rules_for_course() {
        $rules = array();

        $rules[] = new restore_log_rule('ipal', 'view all', 'index?id={course}', null,
                                        null, null, 'index.php?id={course}');
        $rules[] = new restore_log_rule('ipal', 'view all', 'index.php?id={course}', null);

        return $rules;
    }
}
