<?php //echo "Under construction\n<br />";
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
 * Page to edit questions selected for an ipal instance
 *
 * This page generally has two columns:
 * The right column lists all available questions in a chosen category and
 * allows them to be edited or more to be added. This column is only there if
 * the quiz does not already have student attempts
 * The left column lists all questions that have been added to the current quiz.
 * The lecturer can add questions from the right hand list to the quiz or remove them
 *
 * The script also processes a number of actions:
 * Actions affecting a quiz:
 * up and down  Changes the order of questions and page breaks
 * addquestion  Adds a single question to the quiz
 * add          Adds several selected questions to the quiz
 * addrandom    Adds a certain number of random questions to the quiz
 * repaginate   Re-paginates the quiz
 * delete       Removes a question from the quiz
 * savechanges  Saves the order and grades for questions in the quiz
 *
 * @package    mod
 * @subpackage quiz
 * @copyright  1999 onwards Martin Dougiamas and others {@link http://moodle.com}
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */


require_once('../../config.php');
require_once(dirname(__FILE__).'/lib.php');

require_once("locallib.php");
require_once($CFG->dirroot . '/mod/ipal/quiz/ipal_genericq_create.php');//Creates two generic questions for each IPAL activity
require_once($CFG->dirroot . '/mod/ipal/question/engine/lib.php');//needed for Class 'question_display_options' 
require_once($CFG->dirroot . '/mod/ipal/question/engine/bank.php');

$cmid = required_param('cmid', PARAM_INT);
$cm = $DB->get_record('course_modules',array('id'=>$cmid));
$course = $DB->get_record('course', array('id' => $cm->course), '*', MUST_EXIST);
$context = $DB->get_record('context',array('contextlevel'=>'50','instanceid'=>$course->id));
$PAGE->set_context(get_context_instance(CONTEXT_COURSE, $course->id));
$PAGE->set_url('/mod/ipal/view.php', array('id' => $cm->id));
$PAGE->set_title('Edit or add questions for IPAL');
$PAGE->set_heading('My modules page heading');
require_login($course, true, $cm);

require_once("ipal_edit_quizlocallib.php");
/**
 * Callback function called from question_list() function
 * (which is called from showbank())
 * Displays button in form with checkboxes for each question.
 */
function module_specific_buttons($cmid, $cmoptions) {
    global $OUTPUT;
    if ($cmoptions->hasattempts) {
        $disabled = 'disabled="disabled" ';
    } else {
        $disabled = '';
    }
    $out = '<input type="submit" name="add" value="' . $OUTPUT->larrow() . ' ' .
            get_string('addtoquiz', 'quiz') . '" ' . $disabled . "/>\n";
    return $out;
}

//these params are only passed from page request to request while we stay on
//this page otherwise they would go in question_edit_setup
$quiz_reordertool = optional_param('reordertool', -1, PARAM_BOOL);
$quiz_qbanktool = optional_param('qbanktool', -1, PARAM_BOOL);
$scrollpos = optional_param('scrollpos', '', PARAM_INT);

list($thispageurl, $contexts, $cmid, $cm, $quiz, $pagevars) =
        ipal_question_edit_setup('editq', '/mod/ipal/ipal_quiz_edit.php', true);//Modified for ipal
//function ipal_question_edit_setup() found in /ipal/ipal_edit_quizlocallib.php

$quiz->questions = ipal_clean_layout($quiz->questions);//Modified for ipal

$defaultcategoryobj = ipal_question_make_default_categories($contexts->all());
$defaultcategory = $defaultcategoryobj->id . ',' . $defaultcategoryobj->contextid;


// Process commands ============================================================

if ($quiz_qbanktool > -1) {
    $thispageurl->param('qbanktool', $quiz_qbanktool);
    set_user_preference('quiz_qbanktool_open', $quiz_qbanktool);
} else {
    $quiz_qbanktool = get_user_preferences('quiz_qbanktool_open', 0);
}

if ($quiz_reordertool > -1) {
    $thispageurl->param('reordertool', $quiz_reordertool);
    set_user_preference('quiz_reordertab', $quiz_reordertool);
} else {
    $quiz_reordertool = get_user_preferences('quiz_reordertab', 0);
}

//will be set further down in the code
//$quizhasattempts = quiz_has_attempts($quiz->id);
$quizhasattempts = 0;//Modified for ipal since quiz having attempts is menaingless in ipal
$PAGE->set_url($thispageurl);

$pagetitle = get_string('editingquiz', 'quiz');
if ($quiz_reordertool) {
    $pagetitle = get_string('orderingquiz', 'quiz');
}
// Get the course object and related bits.
$course = $DB->get_record('course', array('id' => $quiz->course));
if (!$course) {
    print_error('invalidcourseid', 'error');
}

$questionbank = new ipal_question_bank_view($contexts, $thispageurl, $course, $cm, $quiz);
$questionbank->set_quiz_has_attempts($quizhasattempts);

// Log this visit.
add_to_log($cm->course, 'ipal', 'editquestions',
            "ipal_quiz_edit.php?id=$cm->id", "$quiz->id", $cm->id);
            
// You need mod/quiz:manage in addition to question capabilities to access this page.
require_capability('mod/quiz:manage', $contexts->lowest());

// Get the list of question ids had their check-boxes ticked.
$selectedquestionids = array(); 
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
if (($up = optional_param('up', false, PARAM_INT)) && confirm_sesskey()) {
    $quiz->questions = ipal_move_question_up($quiz->questions, $up);
    $DB->set_field('ipal', 'questions', $quiz->questions, array('id' => $quiz->id));
    //quiz_delete_previews($quiz);//No preview functionality
    redirect($afteractionurl);//Comes from /question/export.php
}

if (($down = optional_param('down', false, PARAM_INT)) && confirm_sesskey()) {
    $quiz->questions = ipal_move_question_down($quiz->questions, $down);
    $DB->set_field('ipal', 'questions', $quiz->questions, array('id' => $quiz->id));
    //quiz_delete_previews($quiz);//No preview functionality
    redirect($afteractionurl);//Comes from /question/expotr.php
}

if (($addquestion = optional_param('addquestion', 0, PARAM_INT)) && confirm_sesskey()) {
    // Add a single question to the current quiz
    $addonpage = optional_param('addonpage', 0, PARAM_INT);
    ipal_add_quiz_question($addquestion, $quiz, $addonpage);
    //quiz_delete_previews($quiz);
    //quiz_update_sumgrades($quiz);
    $thispageurl->param('lastchanged', $addquestion);
    redirect($afteractionurl);
}

if (optional_param('add', false, PARAM_BOOL) && confirm_sesskey()) {
    // Add selected questions to the current quiz
    $rawdata = (array) data_submitted();
    foreach ($rawdata as $key => $value) { // Parse input for question ids
        if (preg_match('!^q([0-9]+)$!', $key, $matches)) {
            $key = $matches[1];
            ipal_add_quiz_question($key, $quiz);//Modified for ipal
        }
    }
    //quiz_delete_previews($quiz);
    //quiz_update_sumgrades($quiz);
    redirect($afteractionurl);
}


$addpage = false;//In IPAL all qustions are on the same page.
$deleteemptypage = false;


$remove = optional_param('remove', false, PARAM_INT);
if (($remove = optional_param('remove', false, PARAM_INT)) && confirm_sesskey()) {
    ipal_remove_question($quiz, $remove);
    //quiz_update_sumgrades($quiz);//Modified for ipal
    //quiz_delete_previews($quiz);
    redirect($afteractionurl);
}

if (optional_param('quizdeleteselected', false, PARAM_BOOL) &&
        !empty($selectedquestionids) && confirm_sesskey()) {
    foreach ($selectedquestionids as $questionid) {
        quiz_remove_question($quiz, $questionid);
    }
    quiz_delete_previews($quiz);
    quiz_update_sumgrades($quiz);
    redirect($afteractionurl);
}

if (optional_param('savechanges', false, PARAM_BOOL) && confirm_sesskey()) {
    $deletepreviews = false;
    $recomputesummarks = false;

    $oldquestions = explode(',', $quiz->questions); // the questions in the old order
    $questions = array(); // for questions in the new order
    $rawdata = (array) data_submitted();
    $moveonpagequestions = array();
    $moveselectedonpage = optional_param('moveselectedonpagetop', 0, PARAM_INT);
    if (!$moveselectedonpage) {
        $moveselectedonpage = optional_param('moveselectedonpagebottom', 0, PARAM_INT);
    }

    foreach ($rawdata as $key => $value) {
        if (preg_match('!^g([0-9]+)$!', $key, $matches)) {
            // Parse input for question -> grades
            $questionid = $matches[1];
            $quiz->grades[$questionid] = clean_param($value, PARAM_FLOAT);
            quiz_update_question_instance($quiz->grades[$questionid], $questionid, $quiz);
            $deletepreviews = true;
            $recomputesummarks = true;

        } else if (preg_match('!^o(pg)?([0-9]+)$!', $key, $matches)) {
            // Parse input for ordering info
            $questionid = $matches[2];
            // Make sure two questions don't overwrite each other. If we get a second
            // question with the same position, shift the second one along to the next gap.
            $value = clean_param($value, PARAM_INTEGER);
            while (array_key_exists($value, $questions)) {
                $value++;
            }
            if ($matches[1]) {
                // This is a page-break entry.
                $questions[$value] = 0;
            } else {
                $questions[$value] = $questionid;
            }
            $deletepreviews = true;
        }
    }

    // If ordering info was given, reorder the questions
    if ($questions) {
        ksort($questions);
        $questions[] = 0;
        $quiz->questions = implode(',', $questions);
        $DB->set_field('quiz', 'questions', $quiz->questions, array('id' => $quiz->id));
        $deletepreviews = true;
    }

    //get a list of questions to move, later to be added in the appropriate
    //place in the string
    if ($moveselectedonpage) {
        $questions = explode(',', $quiz->questions);
        $newquestions = array();
        //remove the questions from their original positions first
        foreach ($questions as $questionid) {
            if (!in_array($questionid, $selectedquestionids)) {
                $newquestions[] = $questionid;
            }
        }
        $questions = $newquestions;

        //move to the end of the selected page
        $pagebreakpositions = array_keys($questions, 0);
        $numpages = count($pagebreakpositions);
        // Ensure the target page number is in range.
        $moveselectedonpage = max(1, min($moveselectedonpage, $pagebreakpositions));
        $moveselectedpos = $pagebreakpositions[$moveselectedonpage - 1];
        array_splice($questions, $moveselectedpos, 0, $selectedquestionids);
        $quiz->questions = implode(',', $questions);
        $DB->set_field('quiz', 'questions', $quiz->questions, array('id' => $quiz->id));
        $deletepreviews = true;
    }

    // If rescaling is required save the new maximum
    $maxgrade = optional_param('maxgrade', -1, PARAM_FLOAT);
    if ($maxgrade >= 0) {
        quiz_set_grade($maxgrade, $quiz);
    }

    if ($deletepreviews) {
        quiz_delete_previews($quiz);
    }
    if ($recomputesummarks) {
        quiz_update_sumgrades($quiz);
        quiz_update_all_attempt_sumgrades($quiz);
        quiz_update_all_final_grades($quiz);
        quiz_update_grades($quiz, 0, true);
    }
    redirect($afteractionurl);
}


$remove = optional_param('remove', false, PARAM_INT);
if (($remove = optional_param('remove', false, PARAM_INT)) && confirm_sesskey()) {
    ipal_remove_question($quiz, $remove);
    //quiz_update_sumgrades($quiz);//Modified for ipal
    //quiz_delete_previews($quiz);
    redirect($afteractionurl);
}

if (optional_param('quizdeleteselected', false, PARAM_BOOL) &&
        !empty($selectedquestionids) && confirm_sesskey()) {
    foreach ($selectedquestionids as $questionid) {
        quiz_remove_question($quiz, $questionid);
    }
    quiz_delete_previews($quiz);
    quiz_update_sumgrades($quiz);
    redirect($afteractionurl);
}

if (optional_param('savechanges', false, PARAM_BOOL) && confirm_sesskey()) {
    $deletepreviews = false;
    $recomputesummarks = false;

    $oldquestions = explode(',', $quiz->questions); // the questions in the old order
    $questions = array(); // for questions in the new order
    $rawdata = (array) data_submitted();
    $moveonpagequestions = array();
    $moveselectedonpage = optional_param('moveselectedonpagetop', 0, PARAM_INT);
    if (!$moveselectedonpage) {
        $moveselectedonpage = optional_param('moveselectedonpagebottom', 0, PARAM_INT);
    }

    foreach ($rawdata as $key => $value) {
        if (preg_match('!^g([0-9]+)$!', $key, $matches)) {
            // Parse input for question -> grades
            $questionid = $matches[1];
            $quiz->grades[$questionid] = clean_param($value, PARAM_FLOAT);
            quiz_update_question_instance($quiz->grades[$questionid], $questionid, $quiz);
            $deletepreviews = true;
            $recomputesummarks = true;

        } else if (preg_match('!^o(pg)?([0-9]+)$!', $key, $matches)) {
            // Parse input for ordering info
            $questionid = $matches[2];
            // Make sure two questions don't overwrite each other. If we get a second
            // question with the same position, shift the second one along to the next gap.
            $value = clean_param($value, PARAM_INTEGER);
            while (array_key_exists($value, $questions)) {
                $value++;
            }
            if ($matches[1]) {
                // This is a page-break entry.
                $questions[$value] = 0;
            } else {
                $questions[$value] = $questionid;
            }
            $deletepreviews = true;
        }
    }

    // If ordering info was given, reorder the questions
    if ($questions) {
        ksort($questions);
        $questions[] = 0;
        $quiz->questions = implode(',', $questions);
        $DB->set_field('quiz', 'questions', $quiz->questions, array('id' => $quiz->id));
        $deletepreviews = true;
    }

    //get a list of questions to move, later to be added in the appropriate
    //place in the string
    if ($moveselectedonpage) {
        $questions = explode(',', $quiz->questions);
        $newquestions = array();
        //remove the questions from their original positions first
        foreach ($questions as $questionid) {
            if (!in_array($questionid, $selectedquestionids)) {
                $newquestions[] = $questionid;
            }
        }
        $questions = $newquestions;

        //move to the end of the selected page
        $pagebreakpositions = array_keys($questions, 0);
        $numpages = count($pagebreakpositions);
        // Ensure the target page number is in range.
        $moveselectedonpage = max(1, min($moveselectedonpage, $pagebreakpositions));
        $moveselectedpos = $pagebreakpositions[$moveselectedonpage - 1];
        array_splice($questions, $moveselectedpos, 0, $selectedquestionids);
        $quiz->questions = implode(',', $questions);
        $DB->set_field('quiz', 'questions', $quiz->questions, array('id' => $quiz->id));
        $deletepreviews = true;
    }

    // If rescaling is required save the new maximum
    $maxgrade = optional_param('maxgrade', -1, PARAM_FLOAT);
    if ($maxgrade >= 0) {
        quiz_set_grade($maxgrade, $quiz);
    }

    if ($deletepreviews) {
        quiz_delete_previews($quiz);
    }
    if ($recomputesummarks) {
        quiz_update_sumgrades($quiz);
        quiz_update_all_attempt_sumgrades($quiz);
        quiz_update_all_final_grades($quiz);
        quiz_update_grades($quiz, 0, true);
    }
    redirect($afteractionurl);
}


$questionbank->process_actions($thispageurl, $cm);

// End of process commands =====================================================

$PAGE->requires->yui2_lib('container');
$PAGE->requires->yui2_lib('dragdrop');
$PAGE->requires->skip_link_to('questionbank',
        get_string('skipto', 'access', get_string('questionbank', 'question')));
$PAGE->requires->skip_link_to('quizcontentsblock',
        get_string('skipto', 'access', get_string('questionsinthisquiz', 'quiz')));
$PAGE->set_heading($course->fullname);
$node = $PAGE->settingsnav->find('mod_quiz_edit', navigation_node::TYPE_SETTING);
if ($node) {
    $node->make_active();
}
echo $OUTPUT->header();
//================================
// Initialise the JavaScript.
$quizeditconfig = new stdClass();
$quizeditconfig->url = $thispageurl->out(true, array('qbanktool' => '0'));
$quizeditconfig->dialoglisteners = array();
//$numberoflisteners = max(quiz_number_of_pages($quiz->questions), 1);
$numberoflisteners = 1;//Each quiz in IPAL is only on one page
for ($pageiter = 1; $pageiter <= $numberoflisteners; $pageiter++) {
    $quizeditconfig->dialoglisteners[] = 'addrandomdialoglaunch_' . $pageiter;
}

$PAGE->requires->data_for_js('quiz_edit_config', $quizeditconfig);
$PAGE->requires->js('/question/qengine.js');
$PAGE->requires->js('/mod/quiz/edit.js');
$PAGE->requires->js_init_call('quiz_edit_init');

if ($quiz_qbanktool) {
    $bankclass = '';
    $quizcontentsclass = '';
} else {
    $bankclass = 'collapsed ';
    $quizcontentsclass = 'quizwhenbankcollapsed';
}
//Question bank display
//============
echo '<div class="questionbankwindow ' . $bankclass . 'block">';
echo '<div class="header"><div class="title"><h2>';
if($DB->count_records('modules',array('name'=>'ejsapp'))){echo "\n<br />Click <a href='".$CFG->wwwroot."/mod/ipal/ejs_ipal.php?cmid=$cmid'>to add EJS Apps</a>";}
require_once($CFG->dirroot . '/mod/ipal/quiz/compadre_access_form.php');
ipal_create_genericq($quiz->course);
echo get_string('questionbankcontents', 'quiz') .
        ' <a href="' . $thispageurl->out(true, array('qbanktool' => '1')) .
       '" id="showbankcmd">[' . get_string('show').
       ']</a>
       <a href="' . $thispageurl->out(true, array('qbanktool' => '0')) .
       '" id="hidebankcmd">[' . get_string('hide').
       ']</a>';
echo '</h2></div></div><div class="content">';

echo '<span id="questionbank"></span>';
echo '<div class="container">';
echo '<div id="module" class="module">';
echo '<div class="bd">';
$questionbank->display('editq',
        $pagevars['qpage'],
        $pagevars['qperpage'],
        $pagevars['cat'], $pagevars['recurse'], $pagevars['showhidden'],
        $pagevars['qbshowtext']);
echo '</div>';
echo '</div>';
echo '</div>';

echo '</div></div>';
//================
//End of question bank display

echo '<div class="quizcontents ' . $quizcontentsclass . '" id="quizcontentsblock">';
$repaginatingdisabledhtml = '';
$repaginatingdisabled = false;
echo '<a href="view.php?id='.$quiz->cmid.'">Start polling with '.$quiz->name."</a>\n<br />";
echo $OUTPUT->heading('iPollAll Questions for ' . $quiz->name, 2);//Modified for ipal
echo $OUTPUT->help_icon('editingipal', 'ipal', get_string('basicideasofipal', 'ipal'));
$tabindex = 0;
$notifystrings = array();
echo '<div class="editq">';
ipal_print_question_list($quiz, $thispageurl, true,
        $quiz_reordertool, $quiz_qbanktool, $quizhasattempts, $defaultcategoryobj);
echo '</div>';

// Close <div class="quizcontents">:
echo '</div>';


echo $OUTPUT->footer();
