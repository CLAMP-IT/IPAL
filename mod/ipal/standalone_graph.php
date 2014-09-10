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
 *
 * Use this file to display the selected question in an IPAL instance.
 *
 * @package    mod_ipal
 * @copyright  2013 Bill Junkin
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
?>

<html>
<head>
<?php
if (isset($_GET['refresh'])) {
    echo "<meta http-equiv=\"refresh\" content=\"3;url=?refresh=true&ipalid=".$_GET['ipalid']."\">";
}
?>
</head>
<body>
<?php
require_once('../../config.php');
require_once($CFG->dirroot.'/lib/graphlib.php');

/**
 * Return the number of users who have submitted answers to this IPAL instance.
 *
 * @param int $ipalid The ID for the IPAL instance
 * @return int The number of students submitting answers.
 */
function ipal_who_sofar($ipalid) {
    global $DB;
    $records = $DB->get_records('ipal_answered', array('ipal_id' => $ipalid));
    foreach ($records as $records) {
        $answer[] = $records->user_id;
    }
    return(count(@array_unique($answer)));
}



/**
 * Return the id for the current question
 *
 * @param int $ipalid The ID for the IPAL instance
 * @return int The ID for the current question.
 */
function ipal_show_current_question_id($ipalid) {
    if (!isset($_GET['id'])) {
        global $DB;
        if ($DB->record_exists('ipal_active_questions', array('ipal_id' => $ipalid))) {
            $question = $DB->get_record('ipal_active_questions', array('ipal_id' => $ipalid));
            return($question->question_id);
        }
        return(0);
    } else {
        return((int)$_GET['id']);
    }
}

/**
 * Return the ids of all users who have submitted answers to this question.
 *
 * @return int The number of students.
 */
function ipal_count_active_responses() {
    global $DB;
    $questionid = ipal_show_current_question_id((int)$_GET['ipalid']);
    $total = $DB->count_records('ipal_answered', array('question_id' => $questionid, 'ipal_id' => (int)$_GET['ipalid']));
    return((int)$total);
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
    $answers = $DB->get_records('question_answers', array('question' => $questionid));
    foreach ($answers as $answer) {
        $labels[] = preg_replace("/[^A-Za-z0-9 ]/", "", substr(strip_tags($answer->answer), 0, 20));
        $data[] = $DB->count_records('ipal_answered', array('ipal_id' => $_GET['ipalid'], 'answer_id' => $answer->id));

    }

    return( "?data=".implode(",", $data)."&labels=".implode(",", $labels)."&total=10");

}

/**
 *  A function to display answers to essay questions.
 *
 * @param int $questionid The id of question being answered
 * @return array The essay answers to the current question.
 */
function ipal_display_essay_by_id($questionid) {
    global $DB;
    $answerids = $DB->get_records('ipal_answered', array('question_id' => $questionid));
    foreach ($answerids as $id) {
        $answers[] = $id->a_text;
    }
    return($answers);
}

echo "Total Responses --> ".ipal_count_active_responses()."/".ipal_who_sofar($_GET['ipalid']);

/**
 * A function to optain the question type of a given question.
 *
 *
 * Modified by Junkin
 * @param int $questionid The id of hte question
 * @return int question type
 */
function ipal_get_question_type($questionid) {
    global $DB;
    $questiontype = $DB->get_record('question', array('id' => $questionid));
    return($questiontype->qtype);
}

$ipalid = $_GET['ipalid'];
$qtype = ipal_get_question_type(ipal_show_current_question_id($ipalid));
if ($qtype == 'essay') {
    $answers = ipal_display_essay_by_id(ipal_show_current_question_id($_GET['ipalid']));
    foreach ($answers as $answer) {
        echo "\n<br />".strip_tags($answer);
    }
} else {// Only show graph if question is not an essay question.
    echo "<br><img src=\"graph.php".ipal_count_questions(ipal_show_current_question_id($_GET['ipalid']))."\"></img>";
}
?>
</body>
</html>
