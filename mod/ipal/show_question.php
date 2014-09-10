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
</head>
<body>
<?php
require_once('../../config.php');
require_once("lib.php");
require_once('../../lib/filelib.php');

/**
 * Function to get possible answers to a given question
 *
 * @param int $questionid The id of the question.
 * @return array An array of the possible responses with the answer Id as the key
 */
function ipal_get_answers($questionid) {
    global $DB;
    $answerarray = array();
    $answers = $DB->get_records('question_answers', array('question' => $questionid));
    foreach ($answers as $answers) {
            $answerarray[$answers->id] = $answers->answer;
    }

    return($answerarray);
}


/**
 * Function to get the question text and answers for specific question.
 *
 * @param int $qid The id of the question.
 * @return array An array of the question id, text, and possible responses
 */
function ipal_get_questions($qid) {
    global $DB;
    $pagearray2 = array();

    $aquestions = $DB->get_record('question', array('id' => $qid));
    if ($aquestions->questiontext != "") {

        $pagearray2[] = array('id' => $qid, 'question' => $aquestions->questiontext, 'answers' => ipal_get_answers($qid));

    }
    return($pagearray2);
}

/**
 * Function to display a question 
 *
 */
function ipal_display_standalone_question() {
    global $contextid;
    global $entryid;
    $qid = $_GET['qid'];
    $myformarray = ipal_get_questions($qid);
    echo "<form action=\"\">\n";
    $text = $myformarray[0]['question'];
    $text = file_rewrite_pluginfile_urls($text, 'pluginfile.php', $contextid->id, 'question', 'questiontext/'.$entryid.'/1', $qid);
    echo  $text;
    echo "<br>";
    foreach ($myformarray[0]['answers'] as $k => $v) {
        echo "<input type=\"radio\" name=\"answer_id\" value=\"$k\"/> ".strip_tags($v)."<br />\n";
    }
    echo "</form>";
}

// Add entry to the question_usages table so that pluginfile.php will work.
$id = $_GET['id'];

$ipal = $DB->get_record('ipal', array('id' => $_GET['id']));
$courseid = $ipal->course;
$contextid = $DB->get_record('context', array('instanceid' => $courseid, 'contextlevel' => 50));
    $record = new Stdclass();
    $record->contextid = $contextid->id;
    $record->component = 'mod_ipal';
    $record->preferredbehaviour = 'deferredfeedback';
    $lastinsertid = $DB->insert_record('question_usages', $record);
    $entryid = $lastinsertid;

ipal_display_standalone_question();

?>
</body>
</html>
