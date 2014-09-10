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
 * This script receives responses from clickers, enters them in the database, returns responses.
 *
 * This script receives responses from clickers and returns values as follows:
 * If there are no GET values, returns 6 to confirm that the URL is correct
 * If so ipal instance, returns 5
 * If ipal exists, but no active question returns 4
 * If ipal exists and active question but access code is not correct returns a 3
 * If access code is correct and there is an active queston but no answer given returns username=$username
 * If answer is submitted, returns a 1
 * If the device is in the table for this device, clicker_type, and course use the user_id
 * If the device is not in the table, look for the user in the mdl_user table. IF found, use that user_id
 * If the device is not in the table and not in the mdl_user table, provide a new user in the table
 * New user firstname=device_code, last name=clicker_type, password=strtolower(deice_code.clickertype).
 *
 * @package    mod_ipal
 * @copyright 2011 Eckerd College
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

if (!isset($_GET['ac'])) {
    // No access code, maybe just checking that URL is correct.
    echo 6;// Response of 6 = no ac.
    exit;
}
$ac = $_GET['ac'];// The access code for the IPAL activity = ipal_id + last two digits of ipal creation time.
require_once("../../config.php");
$ipalid = substr($ac, 0, strlen($ac) - 2);
if (!($ipal = $DB->get_record('ipal', array('id' => $ipalid)))) {
    echo 5;// No ipal=5.
    exit;
}
if (!($activequestion = $DB->get_record('ipal_active_questions', array('ipal_id' => $ipalid)))) {
    echo 4;
    exit;
}// No active question=4.

$tmcreated = substr($ac, strlen($ac) - 2, 2);
$ipalcrtd = $ipal->timecreated;
if ($tmcreated <> substr($ipalcrtd, strlen($ipalcrtd) - 2, 2)) {
    echo 3;
    exit;
}// Wrong ipal access code gives a 3.

if (!(isset($_GET['instructor']))) {
    $teacherrole = $DB->get_record('role', array('shortname' => 'editingteacher'));
    $teacherroleid = $teacherrole->id;
    $coursecontext = $DB->get_record('context', array('instanceid' => $ipal->course, 'contextlevel' => 50));
    $coursecontextid = $coursecontext->id;
    if ($roleassign = $DB->get_records('role_assignments', array('roleid' => $teacherroleid, 'contextid' => $coursecontextid))) {
        foreach ($roleassign as $key => $value) {
            $teacherid = $value->userid;

        }
    } else {
        $teacherid = 0;// No teacher in course.
    }
    $_GET['instructor'] = $teacherid;
} else {
    if (!($_GET['instructor'] > 0)) {
        $_GET['instructor'] = 0;
    }
}
if (!(isset($_GET['answer']))) {
    echo "2instructor=$teacherid";
    exit;// No answer gives instructor_id.
} else {
    $answer = $_GET['answer'];
}
$answers = explode(":", $answer);
if (count($answers) <> 3) {
    echo 2;
    exit;// Incorrect answer format gives a 2.
}

$device = $answers[1];
$response = $answers[2];
if (isset($_GET['clicker'])) {
    $clickertype = $_GET['clicker'];
} else {
    $clickertype = "ResponseCard";
}

// Look for a user. If there is no usre, add one to the database.
// First see if thi user has responded to an ipal question in this course.
if ($mobileentry = $DB->get_record('ipal_mobile', array('clicker_type' => $clickertype,
        'device_code' => $device, 'course_id' => $ipal->course))) {
    $userid = $mobileentry->user_id;
} else {
    if ($registereduser = $DB->get_record('user', array('firstname' => $device, 'lastname' => $clickertype))) {
        $userid = $registereduser->id;
    } else {
        $record = new stdClass();
        $record->firstname = $device;
        $record->lastname = $clickertype;
        $record->username = strtolower($device.$clickertype);
        $userid = $DB->insert_record('user', $record);
    }
    $mobilerecord = new stdClass();
    $mobilerecord->clicker_type = "$clickertype";
    $mobilerecord->device_code = "$device";
    $mobilerecord->course_id = $ipal->course;
    $mobilerecord->user_id = $userid;
    $mobilerecord->time_created = time();
    $ipalmobileid = $DB->insert_record('ipal_mobile', $mobilerecord);

}
$instructor = $_GET['instructor'];
$USER->id = $userid;

$questionid = $activequestion->question_id;
$activequestionid = $activequestion->id;
$question = $DB->get_record('question', array('id' => $questionid));
$qtype = $question->qtype;
$answerid = 0;
if ($qtype == 'multichoice') {

    $atext = "";
    $answers = $DB->get_records('question_answers', array('question' => $questionid));
    $n = 1;
    foreach ($answers as $answer) {
        if ($n == $response) {
            $answerid = $answer->id;
        }
        $n++;
    }
} else {
    if ($qtype == 'essay') {
        $questionid = -1;
        $atext = $response;
    } else {
        echo "\n<br />Invalid qtype";
    }
}

$course = $DB->get_record('course', array('id' => $ipal->course));
ipal_save_student_response($questionid, $answerid, $activequestionid, $atext, $instructor);
echo 1;

/**
 * This is the code to insert the student responses into the database.
 *
 * @param int $questionid The ID of the question being answered.
 * @param int $answerid The id of the answer for multiple choice questions.
 * @param int $activequestionid The ID of the active question.
 * @param string $atext The text of the response from essay questions.
 * @param int $instructor The ID in the used table for the instructor.
 */
function ipal_save_student_response($questionid, $answerid, $activequestionid, $atext, $instructor) {
    global $ipal;
    global $DB;
    global $CFG;
    global $USER;
    global $course;

    // Create insert for archive.
    $recordarc = new stdClass();
    $recordarc->id = '';
    $recordarc->user_id = $USER->id;
    $recordarc->question_id = $questionid;
    $recordarc->quiz_id = $ipal->id;
    $recordarc->answer_id = $answerid;
    $recordarc->a_text = $atext;
    $recordarc->class_id = $course->id;
    $recordarc->ipal_id = $ipal->id;
    $recordarc->ipal_code = $activequestionid;
    $recordarc->shortname = $course->shortname;
    $recordarc->instructor = $instructor;
    $recordarc->time_created = time();
    $recordarc->sent = '1';
    $lastinsertid = $DB->insert_record('ipal_answered_archive', $recordarc);

    // Create insert for current question.
    $record = new stdClass();
    $record->id = '';
    $record->user_id = $USER->id;
    $record->question_id = $questionid;
    $record->quiz_id = $ipal->id;
    $record->answer_id = $answerid;
    $record->a_text = $atext;
    $record->class_id = $course->id;
    $record->ipal_id = $ipal->id;
    $record->ipal_code = $activequestionid;
    $record->time_created = time();

    if ($DB->record_exists('ipal_answered', array('user_id' => $USER->id, 'question_id' => $questionid, 'ipal_id' => $ipal->id ))) {
        $mybool = $DB->delete_records('ipal_answered', array('user_id' => $USER->id,
            'question_id' => $questionid, 'ipal_id' => $ipal->id ));
    }
    $lastinsertid = $DB->insert_record('ipal_answered', $record);
}
