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
 * @package    mod_ipal
 * @copyright  2012 W. F. Junkin Eckerd College (http://www.eckerd.edu)
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */


require_once('../../config.php');
require_once($CFG->dirroot . '/mod/ipal/quiz/ipal_genericq_create.php');// Creates two generic questions for each IPAL activity.
require_once($CFG->dirroot . '/mod/quiz/editlib.php');
require_once($CFG->dirroot . '/mod/quiz/addrandomform.php');
require_once($CFG->dirroot . '/question/category_class.php');
require_once($CFG->dirroot . '/mod/ipal/ipalcleanlayout.php');// Replaces function quiz_clean_layout deleted from Moodle 2.7.


/**
 * Function to check that the question is a qustion type supported in ipal
 * @param int $questionid is the id of the question in the question table
 */
function ipal_acceptable_qtype($questionid) {
    global $DB;

    // An array of acceptable qutypes supported in ipal.
    $acceptableqtypes = array('multichoice', 'truefalse', 'essay');
    $qtype = $DB->get_field('question', 'qtype', array('id' => $questionid));
    if (in_array($qtype, $acceptableqtypes)) {
        return true;
    } else {
        return $qtype;
    }
}


/**
 * Add a question to a quiz  Modified from Add a question to quiz in mod_quiz_editlib.php
 *
 * Adds a question to a quiz by updating $quiz as well as the
 * quiz and quiz_question_instances tables. It also adds a page break
 * if required.
 * @param int $id The id of the question to be added
 * @param object $quiz The extended quiz object as used by edit.php
 *      This is updated by this function
 * @param int $page Which page in quiz to add the question on. If 0 (default),
 *      add at the end
 * @return bool false if the question was already in the quiz
 */
function ipal_add_quiz_question($id, $quiz, $page = 0) {
    global $DB;
    $questions = explode(',', ipal_clean_layout($quiz->questions));
    if (in_array($id, $questions)) {
        return false;
    }

    if (!(ipal_acceptable_qtype($id) === true)) {
        $alertmessage = "IPAL does not support ".ipal_acceptable_qtype($id)." questions.";
        echo "<script language='javascript'>alert('$alertmessage')</script>";
        return false;
    }
    // Remove ending page break if it is not needed.
    if ($breaks = array_keys($questions, 0)) {
        // Determine location of the last two page breaks.
        $end = end($breaks);
        $last = prev($breaks);
        $last = $last ? $last : -1;
        if (!$quiz->questionsperpage || (($end - $last - 1) < $quiz->questionsperpage)) {
            array_pop($questions);
        }
    }
    if (is_int($page) && $page >= 1) {
        $numofpages = substr_count(',' . $quiz->questions, ',0');
        if ($numofpages < $page) {
            // The page specified does not exist in quiz.
            $page = 0;
        } else {
            // Add ending page break - the following logic requires doing this at this point.
            $questions[] = 0;
            $currentpage = 1;
            $addnow = false;
            foreach ($questions as $question) {
                if ($question == 0) {
                    $currentpage++;
                    // The current page is the one after the one we want to add on.
                    // So, we add the question before adding the current page.
                    if ($currentpage == $page + 1) {
                        $questionsnew[] = $id;
                    }
                }
                $questionsnew[] = $question;
            }
            $questions = $questionsnew;
        }
    }
    if ($page == 0) {
        // Add question.
        $questions[] = $id;
        // Add ending page break.
        $questions[] = 0;
    }

    // Save new questionslist in database.
    $quiz->questions = implode(',', $questions);
    $DB->set_field('ipal', 'questions', $quiz->questions, array('id' => $quiz->id));

}

/**
 * Subclass to customise the view of the question bank for the quiz editing screen.
 *
 * New class required for IPAL because the return URLs are hard coded in this class
 * @copyright  2009 Tim Hunt
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class ipal_question_bank_view extends question_bank_view {
    /** @var bool the quizhas attempts. */
    protected $quizhasattempts = false;
    /** @var object the quiz settings. */
    protected $quiz = false;

    /**
     * Constructor
     * @param question_edit_contexts $contexts
     * @param moodle_url $pageurl
     * @param object $course course settings
     * @param object $cm activity settings.
     * @param object $quiz quiz settings.
     */
    public function __construct($contexts, $pageurl, $course, $cm, $quiz) {
        parent::__construct($contexts, $pageurl, $course, $cm);
        $this->quiz = $quiz;
    }

    /**
     * Function to define fields in the question bank display
     * @return array The array of filed types and their texts.
     */
    protected function known_field_types() {
        $types = parent::known_field_types();
        $types[] = new question_bank_add_to_quiz_action_column($this);
        $types[] = new question_bank_question_name_text_column($this);
        return $types;
    }

    /**
     * Function that returns an array of the columns in the question bank display
     *
     * @return array
     */
    protected function wanted_columns() {
        return array('addtoquizaction', 'checkbox', 'qtype', 'questionnametext',
                'editaction', 'previewaction');
    }

    /**
     * Specify the column heading
     *
     * @return string Column name for the heading
     */
    protected function heading_column() {
        return 'questionnametext';
    }

    /**
     * Specify teh default order of sorting questions.
     *
     * @return array first and second fields for the sorting
     */
    protected function default_sort() {
        $this->requiredcolumns['qtype'] = $this->knowncolumntypes['qtype'];
        $this->requiredcolumns['questionnametext'] = $this->knowncolumntypes['questionnametext'];
        return array('qtype' => 1, 'questionnametext' => 1);
    }

    /**
     * Let the question bank display know whether the quiz has been attempted,
     * hence whether some bits of UI, like the add this question to the quiz icon,
     * should be displayed.
     * @param bool $quizhasattempts whether the quiz has attempts.
     */
    public function set_quiz_has_attempts($quizhasattempts) {
        $this->quizhasattempts = $quizhasattempts;
        if ($quizhasattempts && isset($this->visiblecolumns['addtoquizaction'])) {
            unset($this->visiblecolumns['addtoquizaction']);
        }
    }

    /**
     * Provides the URL for priviewing a question
     *
     * @param int $question The id of the question being previewed
     * @return string URL of the preview script
     */
    public function preview_question_url($question) {
        return quiz_question_preview_url($this->quiz, $question);
    }

    /**
     * Gives the URL of the question being added to the quiz
     *
     * @param int $questionid ID of the question being added
     * @return string URL of the question being added
     */
    public function add_to_quiz_url($questionid) {
        global $CFG;
        $params = $this->baseurl->params();
        $params['addquestion'] = $questionid;
        $params['sesskey'] = sesskey();
        return new moodle_url('/mod/ipal/edit.php', $params);
    }

    /**
     * Display the question box for the quiz.
     *
     * @param string $tabname The name of the tab
     * @param int $page The number of the page
     * @param int $perpage The number of questions per page
     * @param int $cat The id of the catgory for the questions.
     * @param bool $recurse Is it recursive
     * @param bool $showhidden Show or hide the question
     * @param bool $showquestiontext Show or hide question text
     */
    public function display($tabname, $page, $perpage, $cat,
            $recurse, $showhidden, $showquestiontext) {
        global $OUTPUT;
        if ($this->process_actions_needing_ui()) {
            return;
        }

        // Display the current category.
        if (!$category = $this->get_current_category($cat)) {
            return;
        }
        $this->print_category_info($category);

        echo $OUTPUT->box_start('generalbox questionbank');

        $this->display_category_form($this->contexts->having_one_edit_tab_cap($tabname),
                $this->baseurl, $cat);

        // Continues with list of questions.
        $this->display_question_list($this->contexts->having_one_edit_tab_cap($tabname),
                $this->baseurl, $cat, $this->cm, $recurse, $page,
                $perpage, $showhidden, $showquestiontext,
                $this->contexts->having_cap('moodle/question:add'));

        $this->display_options($recurse, $showhidden, $showquestiontext);
        echo $OUTPUT->box_end();
    }

    /**
     * Print message to choose category 
     *
     * @param string $categoryandcontext GET information about teh category and context
     */
    protected function print_choose_category_message($categoryandcontext) {
        global $OUTPUT;
        echo $OUTPUT->box_start('generalbox questionbank');
        $this->display_category_form($this->contexts->having_one_edit_tab_cap('edit'),
                $this->baseurl, $categoryandcontext);
        echo "<p style=\"text-align:center;\"><b>";
        print_string('selectcategoryabove', 'question');
        echo "</b></p>";
        echo $OUTPUT->box_end();
    }

    /**
     * Print information about the cgtegory
     *
     * @param int $category The id of the category
     */
    protected function print_category_info($category) {
        $formatoptions = new stdClass();
        $formatoptions->noclean = true;
        $strcategory = get_string('category', 'quiz');
        echo '<div class="categoryinfo"><div class="categorynamefieldcontainer">' .
                $strcategory;
        echo ': <span class="categorynamefield">';
        echo shorten_text(strip_tags(format_string($category->name)), 60);
        echo '</span></div><div class="categoryinfofieldcontainer">' .
                '<span class="categoryinfofield">';
        echo shorten_text(strip_tags(format_text($category->info, $category->infoformat,
                $formatoptions, $this->course->id)), 200);
        echo '</span></div></div>';
    }

    /**
     * Determining how to display the questions
     *
     * @param bool $recurse Is it recursive
     * @param bool $showhidden Is it hidden
     * @param bool $showquestiontext Show or hide question text
     */
    protected function display_options($recurse, $showhidden, $showquestiontext) {
        echo '<form method="get" action="edit.php" id="displayoptions">';
        echo "<fieldset class='invisiblefieldset'>";
        echo html_writer::input_hidden_params($this->baseurl,
                array('recurse', 'showhidden', 'qbshowtext'));
        $this->display_category_form_checkbox('recurse', $recurse,
                get_string('includesubcategories', 'question'));
        $this->display_category_form_checkbox('showhidden', $showhidden,
                get_string('showhidden', 'question'));
        echo '<noscript><div class="centerpara"><input type="submit" value="' .
                get_string('go') . '" />';
        echo '</div></noscript></fieldset></form>';
    }
}


/**
 * Callback function called from question_list() function
 * (which is called from showbank())
 * Displays button in form with checkboxes for each question.
 * @param int $cmid The context ID
 * @param object $cmoptions The options for the context
 * @return string HTML code for the button.
 */
function module_specific_buttons($cmid, $cmoptions) {
    global $OUTPUT;
    $params = array(
        'type' => 'submit',
        'name' => 'add',
        'value' => $OUTPUT->larrow() . ' ' . get_string('addtoquiz', 'quiz'),
    );
    $cmoptions->hasattempts = false;
    if ($cmoptions->hasattempts) {// Prior attempts don’t matter with IPAL.
        $params['disabled'] = 'disabled';
    }
    return html_writer::empty_tag('input', $params);
}

/**
 * Callback function called from question_list() function
 * (which is called from showbank())
 * @param int $totalnumber A variable that is used in quiz but not in IPAL
 * @param bool $recurse Is it recursive
 * @param object $category The information about the Category
 * @param int $cmid The context id
 * @param object $cmoptions The options about this quiz context
 */
function module_specific_controls($totalnumber, $recurse, $category, $cmid, $cmoptions) {
    global $OUTPUT;
    $out = '';
    $catcontext = context::instance_by_id($category->contextid);
    if (has_capability('moodle/question:useall', $catcontext)) {
        $cmoptions->hasattempts = false;
        if ($cmoptions->hasattempts) {// Prior attempts don’t matter with IPAL.
            $disabled = ' disabled="disabled"';
        } else {
            $disabled = '';
        }
        $randomusablequestions =
                question_bank::get_qtype('random')->get_available_questions_from_category(
                        $category->id, $recurse);
        $maxrand = count($randomusablequestions);$maxrand = 0;// Adding random questions is not an IPAL option.
        if ($maxrand > 0) {
            for ($i = 1; $i <= min(10, $maxrand); $i++) {
                $randomcount[$i] = $i;
            }
            for ($i = 20; $i <= min(100, $maxrand); $i += 10) {
                $randomcount[$i] = $i;
            }
        } else {
            $randomcount[0] = 0;
            $disabled = ' disabled="disabled"';
        }

        $out = '<strong><label for="menurandomcount">'.get_string('addrandomfromcategory', 'quiz').
                '</label></strong><br />';
        $attributes = array();
        $attributes['disabled'] = $disabled ? 'disabled' : null;
        $select = html_writer::select($randomcount, 'randomcount', '1', null, $attributes);
        $out .= get_string('addrandom', 'quiz', $select);
        $out .= '<input type="hidden" name="recurse" value="'.$recurse.'" />';
        $out .= '<input type="hidden" name="categoryid" value="' . $category->id . '" />';
        $out .= ' <input type="submit" name="addrandom" value="'.
                get_string('addtoquiz', 'quiz').'"' . $disabled . ' />';
        $out .= $OUTPUT->help_icon('addarandomquestion', 'quiz');
    }
    return $out;
}

// These params are only passed from page request to request while we stay on this page.
// Otherwise they would go in question_edit_setup.
$quizreordertool = optional_param('reordertool', -1, PARAM_BOOL);
$quizqbanktool = optional_param('qbanktool', -1, PARAM_BOOL);
$scrollpos = optional_param('scrollpos', '', PARAM_INT);

list($thispageurl, $contexts, $cmid, $cm, $quiz, $pagevars) =
        question_edit_setup('editq', '/mod/ipal/edit.php', true);// Modified for IPAL.
$quiz->questions = ipal_clean_layout($quiz->questions);

$defaultcategoryobj = question_make_default_categories($contexts->all());
$defaultcategory = $defaultcategoryobj->id . ',' . $defaultcategoryobj->contextid;

if ($quizqbanktool > -1) {
    $thispageurl->param('qbanktool', $quizqbanktool);
    set_user_preference('quiz_qbanktool_open', $quizqbanktool);
} else {
    $quizqbanktool = get_user_preferences('quiz_qbanktool_open', 0);
}

if ($quizreordertool > -1) {
    $thispageurl->param('reordertool', $quizreordertool);
    set_user_preference('quiz_reordertab', $quizreordertool);
} else {
    $quizreordertool = get_user_preferences('quiz_reordertab', 0);
}

$canaddrandom = $contexts->have_cap('moodle/question:useall');
$canaddquestion = (bool) $contexts->having_add_and_use();

$quizhasattempts = quiz_has_attempts($quiz->id);$quizhasattempts = 0;// Attempts don't matter in IPAL.

$PAGE->set_url($thispageurl);

// Get the course object and related bits.
$course = $DB->get_record('course', array('id' => $quiz->course));
if (!$course) {
    print_error('invalidcourseid', 'error');
}

$questionbank = new quiz_question_bank_view($contexts, $thispageurl, $course, $cm, $quiz);
$questionbank->set_quiz_has_attempts($quizhasattempts);

// Log this visit.
$params = array(
    'courseid' => $course->id,
    'context' => $contexts->lowest(),
    'other' => array(
        'ipalid' => $quiz->id
    )
);
$event = \mod_ipal\event\edit_page_viewed::create($params);
$event->trigger();

// You need mod/quiz:manage in addition to question capabilities to access this page.
require_capability('mod/quiz:manage', $contexts->lowest());


// Process commands ============================================================.
if ($quiz->shufflequestions) {
    // Strip page breaks before processing actions, so that re-ordering works when shuffle questions is on.
    $quiz->questions = quiz_repaginate($quiz->questions, 0);
}

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
    $quiz->questions = quiz_move_question_up($quiz->questions, $up);
    $DB->set_field('ipal', 'questions', $quiz->questions, array('id' => $quiz->id));// Changed for IPAL.
    quiz_delete_previews($quiz);
    redirect($afteractionurl);
}

if (($down = optional_param('down', false, PARAM_INT)) && confirm_sesskey()) {
    $quiz->questions = quiz_move_question_down($quiz->questions, $down);
    $DB->set_field('ipal', 'questions', $quiz->questions, array('id' => $quiz->id));// Changed for IPAL.
    quiz_delete_previews($quiz);
    redirect($afteractionurl);
}

if (optional_param('repaginate', false, PARAM_BOOL) && confirm_sesskey()) {
    // Re-paginate the quiz.
    $questionsperpage = optional_param('questionsperpage', $quiz->questionsperpage, PARAM_INT);
    $quiz->questions = quiz_repaginate($quiz->questions, $questionsperpage );
    $DB->set_field('quiz', 'questions', $quiz->questions, array('id' => $quiz->id));
    quiz_delete_previews($quiz);
    redirect($afteractionurl);
}

if (($addquestion = optional_param('addquestion', 0, PARAM_INT)) && confirm_sesskey()) {
    // Add a single question to the current quiz.
    $addonpage = optional_param('addonpage', 0, PARAM_INT);
    ipal_add_quiz_question($addquestion, $quiz, $addonpage);
    $thispageurl->param('lastchanged', $addquestion);
    redirect($afteractionurl);
}

if (optional_param('add', false, PARAM_BOOL) && confirm_sesskey()) {
    // Add selected questions to the current quiz.
    $rawdata = (array) data_submitted();
    foreach ($rawdata as $key => $value) { // Parse input for question ids.
        if (preg_match('!^q([0-9]+)$!', $key, $matches)) {
            $key = $matches[1];
            ipal_add_quiz_question($key, $quiz);
        }
    }
    quiz_delete_previews($quiz);
    quiz_update_sumgrades($quiz);
    redirect($afteractionurl);
}

if ((optional_param('addrandom', false, PARAM_BOOL)) && confirm_sesskey()) {
    // Add random questions to the quiz.
    $recurse = optional_param('recurse', 0, PARAM_BOOL);
    $addonpage = optional_param('addonpage', 0, PARAM_INT);
    $categoryid = required_param('categoryid', PARAM_INT);
    $randomcount = required_param('randomcount', PARAM_INT);
    quiz_add_random_questions($quiz, $addonpage, $categoryid, $randomcount, $recurse);

    quiz_delete_previews($quiz);
    quiz_update_sumgrades($quiz);
    redirect($afteractionurl);
}

$remove = optional_param('remove', false, PARAM_INT);
if ($remove && confirm_sesskey()) {
    // Remove a question from the quiz.
    // We require the user to have the 'use' capability on the question.
    // So that then can add it back if they remove the wrong one by mistake.
    quiz_require_question_use($remove);
    quiz_remove_question($quiz, $remove);
    $DB->set_field('ipal', 'questions', $quiz->questions, array('id' => $quiz->id));// Added for IPAL.
    redirect($afteractionurl);
}

if (optional_param('quizdeleteselected', false, PARAM_BOOL) &&
        !empty($selectedquestionids) && confirm_sesskey()) {
    foreach ($selectedquestionids as $questionid) {
        if (quiz_has_question_use($questionid)) {
            quiz_remove_question($quiz, $questionid);
        }
    }
    quiz_delete_previews($quiz);
    quiz_update_sumgrades($quiz);
    redirect($afteractionurl);
}

if (optional_param('savechanges', false, PARAM_BOOL) && confirm_sesskey()) {
    $deletepreviews = false;
    $recomputesummarks = false;

    $oldquestions = explode(',', $quiz->questions); // The questions in the old order.
    $questions = array(); // For questions in the new order.
    $rawdata = (array) data_submitted();
    $moveonpagequestions = array();
    $moveselectedonpage = optional_param('moveselectedonpagetop', 0, PARAM_INT);
    if (!$moveselectedonpage) {
        $moveselectedonpage = optional_param('moveselectedonpagebottom', 0, PARAM_INT);
    }

    foreach ($rawdata as $key => $value) {
        if (preg_match('!^g([0-9]+)$!', $key, $matches)) {
            // Parse input for question -> grades.
            $questionid = $matches[1];
            $quiz->grades[$questionid] = unformat_float($value);
            quiz_update_question_instance($quiz->grades[$questionid], $questionid, $quiz);
            $deletepreviews = true;
            $recomputesummarks = true;

        } else if (preg_match('!^o(pg)?([0-9]+)$!', $key, $matches)) {
            // Parse input for ordering info.
            $questionid = $matches[2];
            // Make sure two questions don't overwrite each other. If we get a second
            // question with the same position, shift the second one along to the next gap.
            $value = clean_param($value, PARAM_INT);
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

    // If ordering info was given, reorder the questions.
    if ($questions) {
        ksort($questions);
        $questions[] = 0;
        $quiz->questions = implode(',', $questions);
        $DB->set_field('quiz', 'questions', $quiz->questions, array('id' => $quiz->id));
        $deletepreviews = true;
    }

    // Get a list of questions to move, later to be added in the appropriate
    // place in the string.
    if ($moveselectedonpage) {
        $questions = explode(',', $quiz->questions);
        $newquestions = array();
        // Remove the questions from their original positions first.
        foreach ($questions as $questionid) {
            if (!in_array($questionid, $selectedquestionids)) {
                $newquestions[] = $questionid;
            }
        }
        $questions = $newquestions;

        // Move to the end of the selected page.
        $pagebreakpositions = array_keys($questions, 0);
        $numpages = count($pagebreakpositions);

        // Ensure the target page number is in range.
        for ($i = $moveselectedonpage; $i > $numpages; $i--) {
            $questions[] = 0;
            $pagebreakpositions[] = count($questions) - 1;
        }
        $moveselectedpos = $pagebreakpositions[$moveselectedonpage - 1];

        // Do the move.
        array_splice($questions, $moveselectedpos, 0, $selectedquestionids);
        $quiz->questions = implode(',', $questions);

        // Update the database.
        $DB->set_field('quiz', 'questions', $quiz->questions, array('id' => $quiz->id));
        $deletepreviews = true;
    }

    // If rescaling is required save the new maximum.
    $maxgrade = unformat_float(optional_param('maxgrade', -1, PARAM_RAW));
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

// End of process commands =====================================================.

$PAGE->requires->skip_link_to('questionbank',
        get_string('skipto', 'access', get_string('questionbank', 'question')));
$PAGE->requires->skip_link_to('quizcontentsblock',
        get_string('skipto', 'access', get_string('questionsinthisquiz', 'quiz')));
$PAGE->set_title(get_string('editingquizx', 'quiz', format_string($quiz->name)));
$PAGE->set_heading($course->fullname);
$node = $PAGE->settingsnav->find('mod_quiz_edit', navigation_node::TYPE_SETTING);
if ($node) {
    $node->make_active();
}
echo $OUTPUT->header();
// Needed so that the preview buttons won't throw an error if preferredbehaviour='Grid'.
$quiz->preferredbehaviour = 'deferredfeedback';
// Initialise the JavaScript.
$quizeditconfig = new stdClass();
$quizeditconfig->url = $thispageurl->out(true, array('qbanktool' => '0'));
$quizeditconfig->dialoglisteners = array();
$numberoflisteners = 1;// Each quiz in IPAL is only on one page.
for ($pageiter = 1; $pageiter <= $numberoflisteners; $pageiter++) {
    $quizeditconfig->dialoglisteners[] = 'addrandomdialoglaunch_' . $pageiter;
}
$PAGE->requires->data_for_js('quiz_edit_config', $quizeditconfig);
$PAGE->requires->js('/question/qengine.js');
$module = array(
    'name'      => 'mod_quiz_edit',
    'fullpath'  => '/mod/quiz/edit.js',
    'requires'  => array('yui2-dom', 'yui2-event', 'yui2-container'),
    'strings'   => array(),
    'async'     => false,
);
$PAGE->requires->js_init_call('quiz_edit_init', null, false, $module);


if ($quizqbanktool) {
    $bankclass = '';
    $quizcontentsclass = '';
} else {
    $bankclass = 'collapsed ';
    $quizcontentsclass = 'quizwhenbankcollapsed';
}

// Question bank display.
// ============.
echo '<div class="questionbankwindow ' . $bankclass . 'block">';
echo '<div class="header"><div class="title"><h2>';
// Offer the opportunity to add an EJS simulation to a question.
if ($DB->count_records('modules', array('name' => 'ejsapp'))) {
    echo "\n<br />Click <a href='".$CFG->wwwroot."/mod/ipal/ejs_ipal.php?cmid=$cmid'>to add EJS Apps</a>";
}
require_once($CFG->dirroot . '/mod/ipal/quiz/compadre_access_form.php');
// Create a generic multichoice question and a generic essay question if they don't exist in this course.
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
// ...================.
// End of question bank display.

echo '<div class="quizcontents ' . $quizcontentsclass . '" id="quizcontentsblock">';


$repaginatingdisabledhtml = '';
$repaginatingdisabled = false;

echo '<a href="view.php?id='.$quiz->cmid.'">Start polling with '.$quiz->name."</a>\n<br />";
echo $OUTPUT->heading('iPollAll Questions for ' . $quiz->name, 2);// Modified for ipal.
echo $OUTPUT->help_icon('editingipal', 'ipal', get_string('basicideasofipal', 'ipal'));

$tabindex = 0;

$notifystrings = array();

echo '<div class="editq">';

/**
 * Print all the controls for adding questions directly into the specific page in the edit tab of edit.php
 *
 * @param object $quiz Information about the iPAL instance
 * @param string $pageurl The URL of the page
 * @param object $page Information about the current page
 * @param bool $hasattempts Does this quiz have attempts (not used in IPAL)
 * @param object $defaultcategoryobj Information about the category
 */
function ipal_print_pagecontrols($quiz, $pageurl, $page, $hasattempts, $defaultcategoryobj) {
    global $CFG, $OUTPUT;
    static $randombuttoncount = 0;
    $randombuttoncount++;
    echo '<div class="pagecontrols">';
    $hasattempts = 0;// Modified for ipal this is added because attempts don't mean anything with ipal.
    // Get the current context.
    $thiscontext = context_module::instance($quiz->course);
    $contexts = new question_edit_contexts($thiscontext);

    // Get the default category.
    list($defaultcategoryid) = explode(',', $pageurl->param('cat'));
    if (empty($defaultcategoryid)) {
        $defaultcategoryid = $defaultcategoryobj->id;
    }

    // Create the url the question page will return to.
    $returnurladdtoquiz = new moodle_url($pageurl, array('addonpage' => $page));

    // Print a button linking to the choose question type page.
    $returnurladdtoquiz = str_replace($CFG->wwwroot, '', $returnurladdtoquiz->out(false));
    $newquestionparams = array('returnurl' => $returnurladdtoquiz,
            'cmid' => $quiz->cmid, 'appendqnumstring' => 'addquestion');
    create_new_question_button($defaultcategoryid, $newquestionparams,
            get_string('addaquestion', 'quiz'),
            get_string('createquestionandadd', 'quiz'), $hasattempts);

    if ($hasattempts) {
        $disabled = 'disabled="disabled"';
    } else {
        $disabled = '';
    }// IPAL removed lines that added a random button.
    echo "\n</div>";
}

/**
 * Print all the controls for adding questions directly into the specific page in the edit tab of edit.php
 *
 * @param object $quiz Information about this IPAL instance
 * @param string $pageurl The URL for this page
 * @param bool $allowdelete Can the user delete things
 * @param object $reordertool Object for reordering
 * @param object $quizqbanktool Object to handle the question bank
 * @param bool $hasattempts Has the quiz been attempted (not used in IPAL).
 * @param object $defaultcategoryobj Object giving information about the category.
 */
function ipal_print_question_list($quiz, $pageurl, $allowdelete, $reordertool,
        $quizqbanktool, $hasattempts, $defaultcategoryobj) {
    global $USER, $CFG, $DB, $OUTPUT;
    $strorder = get_string('order');
    $strquestionname = get_string('questionname', 'quiz');
    $strgrade = get_string('grade');
    $strremove = get_string('remove', 'quiz');
    $stredit = get_string('edit');
    $strview = get_string('view');
    $straction = get_string('action');
    $strmove = get_string('move');
    $strmoveup = get_string('moveup');
    $strmovedown = get_string('movedown');
    $strsave = get_string('save', 'quiz');
    $strreorderquestions = get_string('reorderquestions', 'quiz');

    $strselectall = get_string('selectall', 'quiz');
    $strselectnone = get_string('selectnone', 'quiz');
    $strtype = get_string('type', 'quiz');
    $strpreview = get_string('preview', 'quiz');
    $hasattempts = 0;// Modified for ipal this is added because attempts don't mean anything with ipal.
    if ($quiz->questions) {
        list($usql, $params) = $DB->get_in_or_equal(explode(',', $quiz->questions));
        $qcount = 0;
        foreach ($params as $key => $value) {
            if ($value > 0) {
                $questions = $DB->get_records_sql("SELECT q.* FROM {question} q WHERE q.id = $value", $params);
                $ipalquestions[$value] = $questions[$value];
                $ipalname[$qcount] = trim($questions[$value]->name);
                $ipalquestiontext[$qcount] = trim($questions[$value]->questiontext);
                $ipalid[$qcount] = $value;
                $ipalqtype[$qcount] = $questions[$value]->qtype;
                $qcount++;
            } else {
                $ipalid[$qcount] = 0;
                $qcount++;
            }
        }

    } else {
        $questions = array();
    }

    if (isset($ipalid)) {
        $order = $ipalid;
    } else {
        $order = null;
    }
    $lastindex = count($order) - 1;
    $disabled = '';
    $pagingdisabled = '';

    $reordercontrolssetdefaultsubmit = '<div style="display:none;">' .
        '<input type="submit" name="savechanges" value="' .
        $strreorderquestions . '" ' . $pagingdisabled . ' /></div>';
    $reordercontrols1 = '<div class="addnewpagesafterselected">' .
        '<input type="submit" name="addnewpagesafterselected" value="' .
        get_string('addnewpagesafterselected', 'quiz') . '"  ' .
        $pagingdisabled . ' /></div>';
    $reordercontrols1 .= '<div class="quizdeleteselected">' .
        '<input type="submit" name="quizdeleteselected" ' .
        'onclick="return confirm(\'' .
        get_string('areyousureremoveselected', 'quiz') . '\');" value="' .
        get_string('removeselected', 'quiz') . '"  ' . $disabled . ' /></div>';

    $a = '<input name="moveselectedonpagetop" type="text" size="2" ' .
        $pagingdisabled . ' />';
    $b = '<input name="moveselectedonpagebottom" type="text" size="2" ' .
        $pagingdisabled . ' />';

    $reordercontrols2top = '<div class="moveselectedonpage">' .
        get_string('moveselectedonpage', 'quiz', $a) .
        '<input type="submit" name="savechanges" value="' .
        $strmove . '"  ' . $pagingdisabled . ' />' . '
        <br /><input type="submit" name="savechanges" value="' .
        $strreorderquestions . '" /></div>';
    $reordercontrols2bottom = '<div class="moveselectedonpage">' .
        '<input type="submit" name="savechanges" value="' .
        $strreorderquestions . '" /><br />' .
        get_string('moveselectedonpage', 'quiz', $b) .
        '<input type="submit" name="savechanges" value="' .
        $strmove . '"  ' . $pagingdisabled . ' /> ' . '</div>';

    $reordercontrols3 = '<a href="javascript:select_all_in(\'FORM\', null, ' .
            '\'quizquestions\');">' .
            $strselectall . '</a> /';
    $reordercontrols3 .= ' <a href="javascript:deselect_all_in(\'FORM\', ' .
            'null, \'quizquestions\');">' .
            $strselectnone . '</a>';

    $reordercontrolstop = '<div class="reordercontrols">' .
            $reordercontrolssetdefaultsubmit .
            $reordercontrols1 . $reordercontrols2top . $reordercontrols3 . "</div>";
    $reordercontrolsbottom = '<div class="reordercontrols">' .
            $reordercontrolssetdefaultsubmit .
            $reordercontrols2bottom . $reordercontrols1 . $reordercontrols3 . "</div>";

    // The current question ordinal (no descriptions).
    $qno = 1;
    // The current question (includes questions and descriptions).
    $questioncount = 0;
    // The current page number in iteration.
    $pagecount = 0;

    $pageopen = false;

    $returnurl = str_replace($CFG->wwwroot, '', $pageurl->out(false));
    $questiontotalcount = count($order);

    if (isset($order)) {
        foreach ($order as $count => $qnum) {

            $reordercheckbox = '';
            $reordercheckboxlabel = '';
            $reordercheckboxlabelclose = '';

            if ($qnum && strlen($ipalname[$count]) == 0 && strlen($ipaltext[$count]) == 0) {
                continue;
            }

            if ($qnum != 0 || ($qnum == 0 && !$pageopen)) {
                // This is either a question or a page break after another (no page is currently open).
                if (!$pageopen) {
                    // If no page is open, start display of a page.
                    $pagecount++;
                    echo  '<div class="quizpage">';
                    echo        '<div class="pagecontent">';
                    $pageopen = true;
                }

                if ($qnum != 0) {
                    $question = $ipalquestions[$qnum];
                    $questionparams = array(
                            'returnurl' => $returnurl,
                            'cmid' => $quiz->cmid,
                            'id' => $ipalid[$count]);
                    $questionurl = new moodle_url('/question/question.php',
                            $questionparams);
                    $questioncount++;
                    // This is an actual question.

                    /* Display question start */
                    ?>
<div class="question">
    <div class="questioncontainer <?php echo $ipalqtype[$count]; ?>">
        <div class="qnum">
                    <?php
                    $reordercheckbox = '';
                    $reordercheckboxlabel = '';
                    $reordercheckboxlabelclose = '';

                    if ((strlen($ipalname[$count]) + strlen($ipalquestiontext[$count])) == 0) {
                        $qnodisplay = get_string('infoshort', 'quiz');
                        $qnodisplay = '?';
                    } else {

                        if ($qno > 999) {
                            $qnodisplay = html_writer::tag('small', $qno);
                        } else {
                            $qnodisplay = $qno;
                        }
                        $qno ++;
                    }
                    echo $reordercheckboxlabel . $qnodisplay . $reordercheckboxlabelclose .
                            $reordercheckbox;

                    ?>
            </div>
            <div class="content">
                <div class="questioncontrols">
                    <?php
                    if ($count != 0) {
                        if (!$hasattempts) {
                            $upbuttonclass = '';
                            if ($count >= $lastindex - 1) {
                                $upbuttonclass = 'upwithoutdown';
                            }
                            echo $OUTPUT->action_icon($pageurl->out(true,
                                    array('up' => $ipalid[$count], 'sesskey' => sesskey())),
                                    new pix_icon('t/up', $strmoveup),
                                    new component_action('click',
                                            'M.core_scroll_manager.save_scroll_action'),
                                    array('title' => $strmoveup));
                        }

                    }
                    if ($count < $lastindex - 1) {
                        if (!$hasattempts) {
                            echo $OUTPUT->action_icon($pageurl->out(true,
                                    array('down' => $ipalid[$count], 'sesskey' => sesskey())),
                                    new pix_icon('t/down', $strmovedown),
                                    new component_action('click',
                                            'M.core_scroll_manager.save_scroll_action'),
                                    array('title' => $strmovedown));
                        }
                    }
                    if ($allowdelete) {
                        if (!$hasattempts) {
                            echo $OUTPUT->action_icon($pageurl->out(true,
                                    array('remove' => $ipalid[$count], 'sesskey' => sesskey())),
                                    new pix_icon('t/delete', $strremove),
                                    new component_action('click',
                                            'M.core_scroll_manager.save_scroll_action'),
                                    array('title' => $strremove));
                        }
                    }
                    ?>
            </div>
            
            <div class="questioncontentcontainer">
                    <?php
                            quiz_print_singlequestion($question, $returnurl, $quiz);
                    ?>
            </div>
        </div>
    </div>
</div>

                    <?php
                }
            }
            // A page break: end the existing page.
            if ($qnum == 0) {// This should never happen.
                if ($pageopen) {
                    if (!$reordertool && !($quiz->shufflequestions &&
                            $count < $questiontotalcount - 1)) {
                        ipal_print_pagecontrols($quiz, $pageurl, $pagecount,
                                $hasattempts, $defaultcategoryobj);
                    } else if ($count < $questiontotalcount - 1) {
                        // Do not include the last page break for reordering.
                        // To avoid creating a new extra page in the end.
                        echo '<input type="hidden" name="opg' . $pagecount . '" size="2" value="' .
                                (10 * $count + 10) . '" />';
                    }
                    echo "</div></div>";

                    $pageopen = false;
                    $count++;
                }
            }

        }
    }// End of if(isset($order).
}

ipal_print_question_list($quiz, $thispageurl, true, $quizreordertool, $quizqbanktool,
        $quizhasattempts, $defaultcategoryobj, $canaddquestion, $canaddrandom);// Modified for IPAL.
echo '</div>';

// Close <div class="quizcontents">.
echo '</div>';

if (!$quizreordertool && $canaddrandom) {
    $randomform = new quiz_add_random_form(new moodle_url('/mod/quiz/addrandom.php'), $contexts);
    $randomform->set_data(array(
        'category' => $pagevars['cat'],
        'returnurl' => $thispageurl->out_as_local_url(false),
        'cmid' => $cm->id,
    ));
    ?>
    <div id="randomquestiondialog">
    <div class="hd"><?php print_string('addrandomquestiontoquiz', 'quiz', $quiz->name); ?>
    <span id="pagenumber"><!-- JavaScript will insert the page number here. -->
    </span>
    </div>
    <div class="bd"><?php
    $randomform->display();
    ?></div>
    </div>
    <?php
}
echo $OUTPUT->footer();