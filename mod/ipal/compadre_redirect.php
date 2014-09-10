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
 * After adding questions from ComPADRE to the ipal instance, this page redirects to the main ipal page.
 *
 * @package    mod_ipal
 * @copyright  2010 onwards W. F. Junkin and Eckerd College {@link http://www.eckerd.edu}
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once('../../config.php');
require_once("locallib.php");
require_once($CFG->dirroot . '/mod/ipal/question/engine/lib.php');// Needed for Class 'question_display_options'.
require_once($CFG->dirroot . '/mod/ipal/question/engine/bank.php');
require_once("ipal_edit_quizlocallib.php");
require_once($CFG->dirroot . '/mod/ipal/quiz/ipal_xmlparser.php');
$cmid = required_param('cmid', PARAM_INT);
$cm = $DB->get_record('course_modules', array('id' => $cmid));
$course = $DB->get_record('course', array('id' => $cm->course), '*', MUST_EXIST);
require_login($course, true, $cm);
$context = $DB->get_record('context', array('contextlevel' => '50', 'instanceid' => $course->id));
$PAGE->set_url('/mod/ipal/view.php', array('id' => $cm->id));
$PAGE->set_title('Edit or add questions for IPAL');
$PAGE->set_heading('My modules page heading');

list($thispageurl, $contexts, $cmid, $cm, $ipal, $pagevars) = ipal_question_edit_setup('editq',
    '/mod/ipal/compadre_redirect.php', true);// Modified for ipal.

$scrollpos = optional_param('scrollpos', '', PARAM_INT);

$defaultcategoryobj = ipal_question_make_default_categories($contexts->all());
$defaultcategory = $defaultcategoryobj->id . ',' . $defaultcategoryobj->contextid;

$PAGE->set_url($thispageurl);

$pagetitle = get_string('editingquiz', 'quiz');
// Get the course object and related bits.
$course = $DB->get_record('course', array('id' => $ipal->course));
if (!$course) {
    print_error('invalidcourseid', 'error');
}

// You need mod/quiz:manage in addition to question capabilities to access this page.
require_capability('mod/quiz:manage', $contexts->lowest());

$params = (array) data_submitted();
foreach ($params as $key => $value) {
    if (preg_match('!^s([0-9]+)$!', $key, $matches)) {
        $selectedquestionids[] = $matches[1];
    }
}

$afteractionurl = new moodle_url($thispageurl);
if ($scrollpos) {
    $afteractionurl->param('scrollpos', $scrollpos);
}
if (($addquestion = optional_param('addquestion', 0, PARAM_INT)) && confirm_sesskey()) {
    // Add a single question to the current quiz.
    $addonpage = optional_param('addonpage', 0, PARAM_INT);
    ipal_add_quiz_question($addquestion, $ipal, $addonpage);
    $thispageurl->param('lastchanged', $addquestion);
    redirect($afteractionurl);
}

// End of process commands =====================================================.

$PAGE->requires->skip_link_to('questionbank',
        get_string('skipto', 'access', get_string('questionbank', 'question')));
$PAGE->requires->skip_link_to('quizcontentsblock',
        get_string('skipto', 'access', get_string('questionsinthisquiz', 'quiz')));
$PAGE->set_title($pagetitle);
$PAGE->set_heading($course->fullname);
$node = $PAGE->settingsnav->find('mod_quiz_edit', navigation_node::TYPE_SETTING);
if ($node) {
    $node->make_active();
}

$repaginatingdisabledhtml = '';
$repaginatingdisabled = false;
$courseid = $course->id;
$contextid = $DB->get_record('context', array('instanceid' => "$courseid", 'contextlevel' => '50'));
$mycontextid = $contextid->id;
$categories = $DB->get_records_menu('question_categories', array('contextid' => "$mycontextid"));
$cats = $DB->get_records('question_categories', array('contextid' => "$mycontextid"));

require_once($CFG->dirroot . '/mod/ipal/quiz/adding_compadre_questions.php');
$ipalinstance = $CFG->wwwroot . '/mod/ipal/view.php?id='.$ipal->cmid;
header("Location: $ipalinstance");
echo "Please click <a href='".$CFG->wwwroot . '/mod/ipal/view.php?id='.$ipal->cmid."'>here.</a>";