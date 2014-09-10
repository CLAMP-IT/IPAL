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
 * This script echos back a hash to let the client know if the question has changed.
 *
 * @package    mod_ipal
 * @copyright  2012 W. F. Junkin, Eckerd College, http://www.eckerd.edu
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
require_once('../../config.php');
require_once($CFG->dirroot.'/lib/graphlib.php');

/**
 * Return the id for the current question
 *
 * @param int $ipalid The ID for the IPAL instance
 * @return int The ID for the current question.
 */
function ipal_show_current_question_id($ipalid) {
    global $DB;
    if ($DB->record_exists('ipal_active_questions', array('ipal_id' => $ipalid))) {
        $question = $DB->get_record('ipal_active_questions', array('ipal_id' => $ipalid));
        return($question->question_id);
    }
    return(0);
}

/**
 * A function to optain the question type of a given question.
 *
 *
 * Modified by Junkin
 * @param int $questionid The id of hte question
 * @return int question type
 */
function ipal_get_qtype($questionid) {
    global $DB;
    $questiontype = $DB->get_record('question', array('id' => $questionid));
    return($questiontype->qtype);
}


/**
 * Return a string = number of responses to each question and labels for questions.
 *
 * @param int $questionid The question id in the active question table for the active question.
 * @return string The number of responses to each question and labels for questions.
 */
function ipal_count_questions($questionid) {
    global $DB;
    global $ipal;
    $qtype = ipal_get_qtype($questionid);
    if ($qtype == 'essay') {
        $labels[] = 'Responses';
        $answers = $DB->get_records('ipal_answered', array('question_id' => $questionid));
        $sum = 0;// The sum of the id's for all answers. If any student submits a new answer, this sum must change.
        foreach ($answers as $answers) {
            $sum = $sum + $answers->id;
        }
        $data[] = $sum;
    } else {
        $answers = $DB->get_records('question_answers', array('question' => $questionid));
        foreach ($answers as $answers) {
            $labels[] = $answers->answer;
            $data[] = $DB->count_records('ipal_answered', array('ipal_id' => $_GET['ipalid'], 'answer_id' => $answers->id));

        }
    }
    return( "?data=".implode(",", $data)."&labels=".implode(",", $labels)."&total=10");

}

echo md5("graph.php".ipal_count_questions(ipal_show_current_question_id($_GET['ipalid'])));
