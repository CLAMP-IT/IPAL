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
?>
<html>
<head>
<meta http-equiv="refresh" content="2;url=?ipalid=<?php echo $_GET['ipalid']; ?>">
</head>
<body>
<?php
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
 * Return a string = number of responses to each question and labels for questions.
 *
 * @param int $questionid The question id in the active question table for the active question.
 * @return string The number of responses to each question and labels for questions.
 */
function ipal_count_questions($questionid) {
    global $DB;
    global $ipal;
    $answers = $DB->get_records('question_answers', array('question' => $questionid));
    foreach ($answers as $answers) {
        $labels[] = strip_tags($answers->answer);
        $data[] = $DB->count_records('ipal_answered', array('ipal_id' => $_GET['ipalid'], 'answer_id' => $answers->id));

    }

    return( "?data=".implode(",", $data)."&labels=".implode(",", $labels)."&total=10");

}
echo "<img src=\"graph.php".ipal_count_questions(ipal_show_current_question_id($_GET['ipalid']))."\"></img>";
echo "\n</body>\n</html>";
