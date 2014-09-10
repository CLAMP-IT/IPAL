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
 * Defines the Moodle forum used to add random questions to the quiz.
 *
 * @package    mod_ipal
 * @copyright  2012 W. F. Junkin, Eckerd College, http://www.eckerd.edu
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
echo '<html><head><title>Adding EJS Activities to IPAL questions</title></head><body>';

require_once('../../config.php');
foreach ($_GET as $key => $value) {
    $$key = $value;
    if ($key == 'qid') {
        foreach ($_GET['qid'] as $qkey => $qvalue) {
            $qid[$qkey] = $qvalue;
        }
    }
}
echo "Click <a href='".$CFG->wwwroot."/mod/ipal/ipal_quiz_edit.php?cmid=$cmid'>here</a> to return to IPAL activity.";
if ($ejsappid > 0) {
    echo "\n<br />An EJS App activity is being added to the selected questions.";
} else {
    echo "\n<br />You must select an EJS App activity to be added to the selected questions.";
    echo "\n<br /> Please use the back button and try again.</body></html>";
    exit;
}
$ejs = $DB->get_record('modules', array('name' => 'ejsapp'));
$ejsid = $ejs->id;// The id for all EJS module activities.
$ejscoursemodule = $DB->get_record('course_modules', array('instance' => $ejsappid, 'course' => $courseid, 'module' => $ejsid));
$ejscoursemoduleid = $ejscoursemodule->id;
echo "\n<br />The following code (put inside the iframe tags) ";
$newcode = "src=\"".$CFG->wwwroot."/mod/ipal/viewejs.php?id=$ejscoursemoduleid\" width='600' height='410'>EJS App";
echo "\n<br />".$newcode;
$newcode = '<iframe '.$newcode.'</iframe>';
echo "\n<be />has been added to the selected question(s). Here are the revised question(s):";
foreach ($qid as $qkey => $qvalue) {
    echo "\n<br /> question $qvalue";
    $questiontext = $DB->get_field('question', 'questiontext', array('id' => $qvalue));
    $result = $DB->set_field('question', 'questiontext', $newcode.$questiontext, array('id' => $qvalue));
    $newquestiontext = $DB->get_field('question', 'questiontext', array('id' => $qvalue));
    echo "\n<br />".$newquestiontext;
}
echo "\n</body></html>";