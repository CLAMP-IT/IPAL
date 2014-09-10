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
 * Returns the structure of ipal for doing a backup.
 *
 * @package    mod_ipal
 * @copyright  2012 W. F. Junkin, Eckerd College, http://www.eckerd.edu
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */


/**
 * This class has functions to do the backup process.
 * @copyright  2012 W. F. Junkin, Eckerd College, http://www.eckerd.edu
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class backup_ipal_activity_structure_step extends backup_activity_structure_step {

    /**
     * A function to return an opbject with all the ipal structure
     **/
    protected function define_structure() {

        $userinfo = $this->get_setting_value('userinfo');

        $ipal = new backup_nested_element('ipal', array('id'), array('course',
            'name', 'intro', 'introformat', 'timeopen',
            'timeclose', 'preferredbehaviour', 'attempts',
            'attemptonlast', 'grademethod', 'decimalpoints', 'questiondecimalpoints',
            'reviewattempt', 'reviewcorrectness', 'reviewmarks',
            'reviewspecificfeedback', 'reviewgeneralfeedback',
            'reviewrightanswer', 'reviewoverallfeedback',
            'questionsperpage', 'shufflequestions', 'shuffleanswers',
            'questions', 'sumgrades', 'grade', 'timecreated',
            'timemodified', 'timelimit', 'password', 'subnet', 'popup',
            'delay1', 'delay2', 'showuserpicture',
            'showblocks'));

        $answered = new backup_nested_element('answered');

        $answer = new backup_nested_element('answer', array('id'), array(
            'user_id', 'question_id', 'quiz_id', 'answer_id', 'class_id', 'ipal_id', 'ipal_code', 'a_text', 'time_created'));

        $answeredarchive = new backup_nested_element('answeredarchive');

        $answeredarchiveelement = new backup_nested_element('answeredarchiveelement', array('id'), array(
            'user_id', 'question_id', 'quiz_id', 'answer_id', 'class_id', 'ipal_id',
            'ipal_code', 'a_text', 'shortname', 'instructor', 'time_created', 'sent'));

        $ipal->add_child($answered);
        $answered->add_child($answer);

        $ipal->add_child($answeredarchive);
        $answeredarchive->add_child($answeredarchiveelement);

        $ipal->set_source_table('ipal', array('id' => backup::VAR_ACTIVITYID));

        if ($userinfo) {
            $answer->set_source_sql('
                SELECT *
                    FROM {ipal_answered}
                    WHERE ipal_id = ?',
                array(backup::VAR_ACTIVITYID));

            $answeredarchiveelement->set_source_sql('
                SELECT *
                    FROM {ipal_answeredarchive}
                    WHERE ipal_id = ?',
                array(backup::VAR_ACTIVITYID));

        }

        return $this->prepare_activity_structure($ipal);
    }
}
