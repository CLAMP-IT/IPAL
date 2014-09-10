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
 * This script generates two generic questions for use in any course using IPAL.
 *
 * @package    mod_ipal
 * @copyright  2012 W. F. Junkin, Eckerd College, http://www.eckerd.edu
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

/**
 * Function to generate the random string required to identify questions.
 *
 * @param int $length The length of the string to be generated.
 * @return string The random string.
 */
function ipal_random_string ($length = 15) {
    $pool  = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ';
    $pool .= 'abcdefghijklmnopqrstuvwxyz';
    $pool .= '0123456789';
    $poollen = strlen($pool);
    mt_srand ((double) microtime() * 1000000);
    $string = '';
    for ($i = 0; $i < $length; $i++) {
        $string .= substr($pool, (mt_rand() % ($poollen)), 1);
    }
    return $string;
}

/**
 * Function to filter out bad characters to from a question.
 *
 * @param string $text TExt to be filtered.
 * @return string The filtered string.
 */
function ipal_filter_var($text) {
    if (strlen($text) == 0) {
        return;
    }
    for ($i = 0; $i < strlen($text); $i++) {
        $asc[$i] = ord(substr($text, $i, 1));
        if ($asc[$i] > 127) {
            $ch[$i] = '&#'.$asc[$i];
        } else {
            $ch[$i] = chr($asc[$i]);
        }
    }
    $cleantext = join('', $ch);
    return $cleantext;
}

/**
 * Function to create a generic multichoice question if it does not exist.
 *
 * The question is created in the default category for the course and thename of the question is Generic multichoice (1-8).
 * The function requires the ipal_random_string() function and ipal_filter_var() function.
 * @param int $courseid The id of the course
 * @return bool True if successful.
 */
function ipal_create_genericq($courseid) {
    global $DB;
    global $USER;
    global $COURSE;
    global $CFG;
    $contextid = $DB->get_record('context', array('instanceid' => "$courseid", 'contextlevel' => '50'));
    $mycontextid = $contextid->id;
    $categories = $DB->get_records_menu('question_categories', array('contextid' => "$mycontextid"));
    $categoryid = 0;
    foreach ($categories as $key => $value) {
        if (preg_match("/Default\ for/", $value)) {
            if (($value == "Default for ".$COURSE->shortname) or ($categoryid == 0)) {
                $categoryid = $key;
            }
        }
    }
    if (!($categoryid > 0)) {
        echo "\n<br />Error obtaining categoryid\n<br />";
        return false;
    }
    $qmultichoicecheckid = $DB->count_records('question', array('category' => "$categoryid",
        'name' => 'Generic multichoice question (1-8)'));
    $qessaycheckid = $DB->count_records('question', array('category' => "$categoryid", 'name' => 'Generic essay question'));
    if (($qmultichoicecheckid > 0) and ($qessaycheckid > 0)) {
        return true;
    }
    $hostname = 'unknownhost';
    if (!empty($_SERVER['HTTP_HOST'])) {
        $hostname = $_SERVER['HTTP_HOST'];
    } else if (!empty($_ENV['HTTP_HOST'])) {
        $hostname = $_ENV['HTTP_HOST'];
    } else if (!empty($_SERVER['SERVER_NAME'])) {
        $hostname = $_SERVER['SERVER_NAME'];
    } else if (!empty($_ENV['SERVER_NAME'])) {
        $hostname = $_ENV['SERVER_NAME'];
    }
    $date = gmdate("ymdHis");
    $stamp = $hostname .'+'. $date .'+'.ipal_random_string(6);
    $version = $hostname .'+'. $date .'+'.ipal_random_string(6);
    $questionfieldarray = array('category', 'parent', 'name', 'questiontext', 'questiontextformat', 'generalfeedback',
        'generalfeedbackformat', 'defaultgrade', 'penalty', 'qtype', 'length', 'stamp', 'version', 'hidden',
        'timecreated', 'timemodified', 'createdby', 'modifiedby');
    $questionnotnullarray = array('name', 'questiontext', 'generalfeedback');
    $questioninsert = new stdClass();
    $date = gmdate("ymdHis");
    $stamp = $hostname .'+'. $date .'+'.ipal_random_string(6);
    $version = $hostname .'+'. $date .'+'.ipal_random_string(6);
    $questioninsert->category = $categoryid;
    $questioninsert->parent = 0;
    $questioninsert->questiontextformat = 1;
    $questioninsert->generalfeedback = ' ';
    $questioninsert->generalfeedbackformat = 1;
    $questioninsert->defaultgrade = 1;
    $questioninsert->penalty = 0;
    $questioninsert->length = 1;
    $questioninsert->stamp = $stamp;
    $questioninsert->version = $version;
    $questioninsert->hidden = 0;
    $questioninsert->timecreated = time();
    $questioninsert->timemodified = time();
    $questioninsert->createdby = $USER->id;
    $questioninsert->modifiedby = $USER->id;
    if ($qmultichoicecheckid == 0) {
        $questioninsert->name = 'Generic multichoice question (1-8)';// This is the title.
        $questioninsert->questiontext = 'Please select an answer.';// This is the text.
        $questioninsert->qtype = 'multichoice';
        $lastinsertid = $DB->insert_record('question', $questioninsert);
        $answeraieldarray = array('answer', 'answerformat', 'fraction', 'feedback', 'feedbackformat');
        $answernotnullarray = array('answer', 'feedback');
        if (!($lastinsertid > 0)) {
            echo "\n<br />Error creating Generic multichoice question\n<br />";
            return false;
        }
        for ($n = 1; $n < 9; $n++) {
            $answerinsert = new stdClass();
            $answerinsert->answer = $n;
            $answerinsert->question = $lastinsertid;
            $answerinsert->format = 1;
            $answerinsert->fraction = 1;
            $answerinsert->feedback = ' ';
            $answerinsert->feedbackformat = 1;
            $lastanswerinsertid[$n] = $DB->insert_record('question_answers', $answerinsert);
            if (!($lastanswerinsertid[$n] > 0)) {
                echo "\n<br />Error inserting answer $n for Generic multichoice question (1-8)\n<br />";
                return false;
            }
        }
        $multichoicefieldarray = array('question', 'layout', 'answers', 'single', 'shuffleanswers', 'correctfeedback',
            'correctfeedbackformat', 'partiallycorrectfeedback', 'partiallycorrectfeedbackformat', 'incorrectfeedback',
            'incorrectfeedbackformat', 'answernumbering');
        $multichoicenotnullarray = array('correctfeedback', 'partiallycorrectfeedback', 'incorrectfeedback');
        $qtypeinsert = new stdClass();
        $qtypeinsert->answers = join(',', $lastanswerinsertid);
        $qtypeinsert->single = 1;
        $qtypeinsert->correctfeedbackformat = 1;
        $qtypeinsert->partiallycorrectfeedbackformat = 1;
        $qtypeinsert->incorrectfeedbackformat = 1;
        $qtypeinsert->correctfeedback = ' ';
        $qtypeinsert->partiallycorrectfeedback = ' ';
        $qtypeinsert->incorrectfeedback = ' ';
        $qtypeinsert->answernumbering = '123';
        if ($questioninsert->qtype == 'multichoice') {
            if ($CFG->version > '2013111799') {
                $qtypetable = 'qtype_'.$questioninsert->qtype."_options";
                $qtypeinsert->questionid = $lastinsertid;
            } else {
                $qtypetable = 'question_'.$questioninsert->qtype;
                $qtypeinsert->question = $lastinsertid;
            }
        }
        $qtypeinsert->shuffleanswers = '0';
        $qtypeinsertid = $DB->insert_record($qtypetable, $qtypeinsert);
    }
    if ($qessaycheckid == 0) {
        $questioninsert->name = 'Generic essay question';// Title.
        $questioninsert->questiontext = 'Please answer the question in the space provided.';// Text.
        $questioninsert->qtype = 'essay';
        $lastinsertid = $DB->insert_record('question', $questioninsert);
        $essayoptions = new stdClass;
        $essayoptions->questionid = $lastinsertid;
        $essayoptions->responsefieldlines = 3;
        $essayoptionsid = $DB->insert_record('qtype_essay_options', $essayoptions);
    }
    return true;
}