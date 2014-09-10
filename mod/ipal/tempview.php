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
 * This file is used with IPAL Apps to log in an authenticate students responding with IPAL Apps.
 *
 * @package    mod_ipal
 * @copyright  2013 Bill Junkin, Eckerd College (http://www.eckerd.edu)
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once(dirname(dirname(dirname(__FILE__))).'/config.php');
require_once('./locallib.php');

$username = optional_param('user', '', PARAM_ALPHANUMEXT);
$passcode = optional_param('p', 0, PARAM_INT);
$regid = optional_param('r', '', PARAM_ALPHANUMEXT);
$unreg = optional_param('unreg', 0, PARAM_INT);

$qtypemessage = '';
$setipal = true;
$setuser = true;

$itimecreate = fmod($passcode, 100);
$i = floor($passcode / 100);

if ($i) {
    try {
        $ipal = $DB->get_record('ipal', array('id' => $i), '*', MUST_EXIST);
        $course = $DB->get_record('course', array('id' => $ipal->course), '*', MUST_EXIST);
        $cm = get_coursemodule_from_instance('ipal', $ipal->id, $course->id, false, MUST_EXIST);
    } catch (dml_exception $e) {
        $qtypemessage = 'invalidpasscode';
        $setipal = false;
    }
    if (fmod($ipal->timecreated, 100) != $itimecreate) {
        $qtypemessage = 'invalidpasscode';
        $setipal = false;
    }
} else {
    $qtypemessage = 'invalidpasscode';
    $setipal = false;
}

if ($setipal) {
    $context = context_course::instance($course->id);
    $studentrole = $DB->get_record('role', array('shortname' => 'student'));
    $students = get_role_users($studentrole->id, $context);

    foreach ($students as $s) {
        // Checking the list of student enrolled in the course with id: $s->username.
        if (strcasecmp($username, $s->username) == 0) {
            $founduser = 1;
            if ($unreg == 1) {
                remove_regid($username);
            }
            $userid = $s->id;
        }
    }

    if ($founduser != 1) {
        $qtypemessage = 'invalidusername';
        $setuser = false;
    }
}

echo "<html>\n<head>\n<title>IPAL: ". $ipal->name."</title>\n</head>\n";
echo "<body>\n";

if (!$setipal && !$setuser) {
    echo "<p id=\"questiontype\">".$qtypemessage."<p>";
} else {
    // If providing a right username, right passcode, add the registration ID to the ipal_mobile table.
    if ($regid) {
        add_regid($regid, $username);
    }
    ipal_tempview_display_question($userid, $passcode, $username);
}
echo "</body>\n</html>";