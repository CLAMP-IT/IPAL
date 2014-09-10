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
 * The class to restore an ipal instance from a backup file
 *
 * @package mod_ipal
 * @subpackage backup-moodle2
 * @copyright 2012 onwards Eckerd College {@http://www.eckerd.edu}
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

/**
 * The class with the functions to do the actual restore of an ipal instance
 *
 * @copyright 2012 onwards Eckerd College {@http://www.eckerd.edu}
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class restore_ipal_activity_structure_step extends restore_activity_structure_step {

    /**
     * Given a list of question->ids, separated by commas, returns the
     * recoded list, with all the restore question mappings applied.
     * Note: Used by quiz->questions and quiz_attempts->layout
     * Note: 0 = page break (unconverted)
     * @param string $layout
     */
    protected function questions_recode_layout($layout) {
        // Extracts question id from sequence.
        if ($questionids = explode(',', $layout)) {
            foreach ($questionids as $id => $questionid) {
                if ($questionid) { // If it is zero then this is a pagebreak, don't translate.
                    $newquestionid = $this->get_mappingid('question', $questionid);
                    $questionids[$id] = $newquestionid;
                }
            }
        }
        return implode(',', $questionids);
    }
    /**
     * Function to define the structure
     */
    protected function define_structure() {

        $paths = array();
        $userinfo = $this->get_setting_value('userinfo');

        $paths[] = new restore_path_element('ipal', '/activity/ipal');
        $paths[] = new restore_path_element('answer', '/activity/ipal/answered/answer');
        if ($userinfo) {
            $paths[] = new restore_path_element('answered_archive_element',
                '/activity/ipal/answered_archive/answered_archive_element');
        }

        return $this->prepare_activity_structure($paths);
    }

    /**
     * Prepare the data for the new instance
     * @param object $data
     */
    protected function process_ipal($data) {
        global $DB;

        $data = (object)$data;
        $oldid = $data->id;
        $data->course = $this->get_courseid();

        $data->timecreated = $this->apply_date_offset($data->timecreated);

        $data->questions = $this->questions_recode_layout($data->questions);

        $newitemid = $DB->insert_record('ipal', $data);
        $this->apply_activity_instance($newitemid);
    }

    /**
     * Convert the answer data for the new ipal instance
     * @param object $data
     */
    protected function process_answer($data) {
        global $DB;

        $data = (object)$data;
        $oldid = $data->id;

        $data->ipalid = $this->get_new_parentid('ipal');
        $data->time_created = $this->apply_date_offset($data->time_created);

        $data->ipal_id = $this->get_new_parentid('ipal');
        $data->quiz_id = $this->get_new_parentid('ipal');
        $data->class_id = $this->get_courseid();
        $data->ipal_code = "0";

        $newitemid = $DB->insert_record('ipal_answered', $data);
        $this->set_mapping('answers', $oldid, $newitemid);
    }

    /**
     * Convert the archived data for this new ipal instance
     * @param object $data
     */
    protected function process_answered_archive_element($data) {
        global $DB;

        $data = (object)$data;
        $oldid = $data->id;

        $data->ipalid = $this->get_new_parentid('ipal');
        $data->time_created = $this->apply_date_offset($data->time_created);
        $data->class_id = $this->get_courseid();

        $data->ipal_id = $this->get_new_parentid('ipal');
        $data->quiz_id = $this->get_new_parentid('ipal');

        $newitemid = $DB->insert_record('ipal_answered_archive', $data);
        $this->set_mapping('answered_archive_element', $oldid, $newitemid);
    }

    /**
     * Function to finish up the job
     */
    protected function after_execute() {
        $this->add_related_files('mod_ipal', 'intro', null);
    }
}
