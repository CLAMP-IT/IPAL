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
 * Internal library of functions for editing an ipal instance
 *
 * @package   mod_ipal
 * @copyright 2011 Eckerd College 
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */


require_once($CFG->dirroot . '/lib/questionlib.php');
define('DEFAULT_QUESTIONS_PER_PAGE', 20);

/**
 * Common setup for all pages for editing questions.
 * @param string $edittab code for this edit tab
 * @param string $baseurl the name of the script calling this funciton. For examle 'qusetion/edit.php'.
 * @param bool $requirecmid require cmid? default false
 * @param bool $requirecourseid require courseid, if cmid is not given? default true
 * @return array $thispageurl, $contexts, $cmid, $cm, $module, $pagevars
 */
function ipal_question_edit_setup($edittab, $baseurl, $requirecmid = false, $requirecourseid = true) {
    global $DB, $PAGE;

    $thispageurl = new moodle_url($baseurl);
    $thispageurl->remove_all_params(); // Explicity add back everything important, avoicing retaining unwanted params.

    if ($requirecmid) {
        $cmid = required_param('cmid', PARAM_INT);
    } else {
        $cmid = optional_param('cmid', 0, PARAM_INT);
    }
    if ($cmid) {
        list($module, $cm) = ipal_get_module_from_cmid($cmid);
        $courseid = $cm->course;
        $thispageurl->params(compact('cmid'));
        require_login($courseid, false, $cm);
        $thiscontext = context_module::instance($cmid);
    } else {
        $module = null;
        $cm = null;
        if ($requirecourseid) {
            $courseid  = required_param('courseid', PARAM_INT);
        } else {
            $courseid  = optional_param('courseid', 0, PARAM_INT);
        }
        if ($courseid) {
            $thispageurl->params(compact('courseid'));
            require_login($courseid, false);
            $thiscontext = context_module::instance($courseid);
        } else {
            $thiscontext = null;
        }
    }

    if ($thiscontext) {
        $contexts = new question_edit_contexts($thiscontext);
        $contexts->require_one_edit_tab_cap($edittab);

    } else {
        $contexts = null;
    }

    $PAGE->set_pagelayout('admin');

    $pagevars['qpage'] = optional_param('qpage', -1, PARAM_INT);

    // Pass 'cat' from page to page and when 'category' comes from a drop down menu.
    // Then we also reset the qpage so we go to page 1 of a new cat.
    $pagevars['cat'] = optional_param('cat', 0, PARAM_SEQUENCE); // If empty will be set up later.
    if ($category = optional_param('category', 0, PARAM_SEQUENCE)) {
        if ($pagevars['cat'] != $category) { // Is this a move to a new category?
            $pagevars['cat'] = $category;
            $pagevars['qpage'] = 0;
        }
    }
    if ($pagevars['cat']) {
        $thispageurl->param('cat', $pagevars['cat']);
    }
    if (strpos($baseurl, '/question/') === 0) {
        navigation_node::override_active_url($thispageurl);
    }

    if ($pagevars['qpage'] > -1) {
        $thispageurl->param('qpage', $pagevars['qpage']);
    } else {
        $pagevars['qpage'] = 0;
    }

    $pagevars['qperpage'] = optional_param('qperpage', -1, PARAM_INT);
    if ($pagevars['qperpage'] > -1) {
        $thispageurl->param('qperpage', $pagevars['qperpage']);
    } else {
        $pagevars['qperpage'] = DEFAULT_QUESTIONS_PER_PAGE;
    }

    for ($i = 1; $i <= ipal_local_question_bank_view::MAX_SORTS; $i++) {
        $param = 'qbs' . $i;
        if (!$sort = optional_param($param, '', PARAM_ALPHAEXT)) {
            break;
        }
        $thispageurl->param($param, $sort);
    }

    $defaultcategory = ipal_question_make_default_categories($contexts->all());

    $contextlistarr = array();
    foreach ($contexts->having_one_edit_tab_cap($edittab) as $context) {
        $contextlistarr[] = "'$context->id'";
    }
    $contextlist = join($contextlistarr, ' ,');
    if (!empty($pagevars['cat'])) {
        $catparts = explode(',', $pagevars['cat']);
        if (!$catparts[0] || (false !== array_search($catparts[1], $contextlistarr)) ||
                !$DB->count_records_select("question_categories", "id = ? AND contextid = ?", array($catparts[0], $catparts[1]))) {
            print_error('invalidcategory', 'question');
        }
    } else {
        $category = $defaultcategory;
        $pagevars['cat'] = "$category->id,$category->contextid";
    }

    if (($recurse = optional_param('recurse', -1, PARAM_BOOL)) != -1) {
        $pagevars['recurse'] = $recurse;
        $thispageurl->param('recurse', $recurse);
    } else {
        $pagevars['recurse'] = 1;
    }

    if (($showhidden = optional_param('showhidden', -1, PARAM_BOOL)) != -1) {
        $pagevars['showhidden'] = $showhidden;
        $thispageurl->param('showhidden', $showhidden);
    } else {
        $pagevars['showhidden'] = 0;
    }

    if (($showquestiontext = optional_param('qbshowtext', -1, PARAM_BOOL)) != -1) {
        $pagevars['qbshowtext'] = $showquestiontext;
        $thispageurl->param('qbshowtext', $showquestiontext);
    } else {
        $pagevars['qbshowtext'] = 0;
    }

    // Category list page.
    $pagevars['cpage'] = optional_param('cpage', 1, PARAM_INT);
    if ($pagevars['cpage'] != 1) {
        $thispageurl->param('cpage', $pagevars['cpage']);
    }

    return array($thispageurl, $contexts, $cmid, $cm, $module, $pagevars);
}


/**
 * Function to get the information about this module.
 *
 * @param int $cmid The id for this module.
 * @return array
 */
function ipal_get_module_from_cmid($cmid) {
    global $CFG, $DB;
    if (!$cmrec = $DB->get_record_sql("SELECT cm.*, md.name as modname
                               FROM {course_modules} cm,
                                    {modules} md
                               WHERE cm.id = ? AND
                                     md.id = cm.module", array($cmid))) {
        print_error('invalidcoursemodule');
    } else if (!$modrec = $DB->get_record($cmrec->modname, array('id' => $cmrec->instance))) {
        print_error('invalidcoursemodule');
    }
    $modrec->instance = $modrec->id;
    $modrec->cmid = $cmrec->id;
    $cmrec->name = $modrec->name;

    return array($modrec, $cmrec);
}

/**
 * Subclass to customise the view of the question bank for the quiz editing screen.
 *
 * @copyright  2009 Tim Hunt
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class ipal_local_question_bank_view extends ipal_original_question_bank_view {
    /** @var bool */
    protected $quizhasattempts = false;
    /** @var object the quiz settings. */
    protected $quiz = false;

    /**
     * Constructor
     * @param question_edit_contexts $contexts
     * @param moodle_url $pageurl
     * @param object $course course settings
     * @param object $cm activity settings.
     * @param object $quiz ipal settings.
     */
    public function __construct($contexts, $pageurl, $course, $cm, $quiz) {
        parent::__construct($contexts, $pageurl, $course, $cm);
        $this->quiz = $quiz;
    }

    /**
     * Function to define fields in the question bank display
     * @return array The array of filed types and their texts.
     */
    protected function known_field_types() {// I may need to edit this to remove unacceptable question types.
        $types = parent::known_field_types();
        $types[] = new question_bank_add_to_ipal_action_column($this);
        $types[] = new ipal_question_bank_question_name_text_column($this);
        return $types;
    }

    /**
     * Function that returns an array of the columns in the question bank display
     * @return array
     */
    protected function wanted_columns() {
        return array('addtoipalaction', 'checkbox', 'qtype', 'questionnametext',
                'editaction', 'previewaction');
    }

    /**
     * Let the question bank display know whether the quiz has been attempted,
     * hence whether some bits of UI, like the add this question to the quiz icon,
     * should be displayed.
     * @param bool $quizhasattempts whether the quiz has attempts.
     */
    public function set_quiz_has_attempts($quizhasattempts) {
        $this->quizhasattempts = $quizhasattempts;
        if ($quizhasattempts && isset($this->visiblecolumns['addtoipalaction'])) {
            unset($this->visiblecolumns['addtoipalaction']);
        }
    }

    /**
     * Provides the URL for priviewing a question
     *
     * @param int $question The id of the question being previewed
     * @return string URL of the preview script
     */
    public function preview_question_url($question) {// Yhis controls the URL of the preview icon.
        return ipal_question_preview_url($this->quiz, $question);
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
        return new moodle_url('/mod/quiz/edit.php', $params);
    }

    /**
     * Added function to send the program back to the correct place when in ipal.
     *
     * @param int $questionid is the id of the question.
     * @return string The URL for the IPAL instance when adding a question.
     */
    public function add_to_ipal_url($questionid) {
        global $CFG;
        $params = $this->baseurl->params();
        $params['addquestion'] = $questionid;
        $params['sesskey'] = sesskey();
        return new moodle_url('/mod/ipal/ipal_quiz_edit.php', $params);
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
        $this->ipal_display_question_list($this->contexts->having_one_edit_tab_cap($tabname),
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
        print_string('selectcategoryabove', 'quiz');
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
        echo '<form method="get" action="ipal_quiz_edit.php" id="displayoptions">';// Modified for ipal.
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

    /**
     * Function to print one row in teh question bank, thus the information for a question.
     *
     * @param stdClass $question INformation about the question.
     * @param int $rowcount
     */
    protected function ipal_print_table_row($question, $rowcount) {
        $rowclasses = implode(' ', $this->get_row_classes($question, $rowcount));
        if ($rowclasses) {
            echo '<tr class="' . $rowclasses . '">' . "\n";
        } else {
            echo "<tr>\n";
        }
        foreach ($this->visiblecolumns as $colkey => $column) {

            if ($colkey == 'addtoipalaction') {
                $column = new question_bank_add_to_ipal_action_column($this);
                $column->display($question, $rowclasses);
            } else {
                $column->display($question, $rowclasses);
            }
        }
        echo "</tr>\n";
        foreach ($this->extrarows as $row) {
            $row->display($question, $rowclasses);
        }
    }

     /**
      * Prints the table of questions in a category with interactions
      *
      * @param object $contexts   The contexts
      * @param string $pageurl
      * @param object $categoryandcontext  The id of the question category to be displayed
      * @param int $cm      The course module record if we are in the context of a particular module, 0 otherwise
      * @param int $recurse     This is 1 if subcategories should be included, 0 otherwise
      * @param int $page        The number of the page to be displayed
      * @param int $perpage     Number of questions to show per page
      * @param bool $showhidden   True if also hidden questions should be displayed
      * @param bool $showquestiontext whether the text of each question should be shown in the list
      * @param array $addcontexts
      */
    protected function ipal_display_question_list($contexts, $pageurl, $categoryandcontext,
            $cm = null, $recurse=1, $page=0, $perpage=100, $showhidden=false,
            $showquestiontext = false, $addcontexts = array()) {
        global $CFG, $DB, $OUTPUT;

        $category = $this->get_current_category($categoryandcontext);

        $cmoptions = new stdClass();
        $cmoptions->hasattempts = !empty($this->quizhasattempts);

        $strselectall = get_string('selectall');
        $strselectnone = get_string('deselectall');
        $strdelete = get_string('delete');

        list($categoryid, $contextid) = explode(',', $categoryandcontext);
        $catcontext = context::instance_by_id($contextid);

        $canadd = has_capability('moodle/question:add', $catcontext);
        $caneditall = has_capability('moodle/question:editall', $catcontext);
        $canuseall = has_capability('moodle/question:useall', $catcontext);
        $canmoveall = has_capability('moodle/question:moveall', $catcontext);

        $this->create_new_question_form($category, $canadd);

        $this->build_query_sql($category, $recurse, $showhidden);
        $totalnumber = $this->get_question_count();
        if ($totalnumber == 0) {
            return;
        }

        $questions = $this->load_page_questions($page, $perpage);

        echo '<div class="categorypagingbarcontainer">';
        $pageingurl = new moodle_url('ipal_quiz_edit.php');
        $r = $pageingurl->params($pageurl->params());
        $pagingbar = new paging_bar($totalnumber, $page, $perpage, $pageingurl);
        $pagingbar->pagevar = 'qpage';
        echo $OUTPUT->render($pagingbar);
        echo '</div>';

        echo '<form method="post" action="ipal_quiz_edit.php">';// Modified for ipal.
        echo '<fieldset class="invisiblefieldset" style="display: block;">';
        echo '<input type="hidden" name="sesskey" value="'.sesskey().'" />';
        echo html_writer::input_hidden_params($pageurl);

        echo '<div class="categoryquestionscontainer">';
        $this->start_table();
        $rowcount = 0;
        foreach ($questions as $question) {
            $this->ipal_print_table_row($question, $rowcount);
            $rowcount += 1;
        }
        $this->end_table();
        echo "</div>\n";

        echo '<div class="categorypagingbarcontainer pagingbottom">';
        echo $OUTPUT->render($pagingbar);
        if ($totalnumber > DEFAULT_QUESTIONS_PER_PAGE) {
            if ($perpage == DEFAULT_QUESTIONS_PER_PAGE) {
                $url = new moodle_url('edit.php', ($pageurl->params() + array('qperpage' => 1000)));
                $showall = '<a href="'.$url.'">'.get_string('showall', 'moodle', $totalnumber).'</a>';
            } else {
                $url = new moodle_url('edit.php', ($pageurl->params() + array('qperpage' => DEFAULT_QUESTIONS_PER_PAGE)));
                $showall = '<a href="'.$url.'">'.get_string('showperpage', 'moodle', DEFAULT_QUESTIONS_PER_PAGE).'</a>';
            }
            echo "<div class='paging'>$showall</div>";
        }
        echo '</div>';

        echo '<div class="modulespecificbuttonscontainer">';
        if ($caneditall || $canmoveall || $canuseall) {
            echo '<strong>&nbsp;'.get_string('withselected', 'question').':</strong><br />';

            if (function_exists('module_specific_buttons')) {
                echo module_specific_buttons($this->cm->id, $cmoptions);
            }

            // Print delete and move selected question.

            if ($canmoveall && count($addcontexts)) {
                echo '<input type="submit" name="move" value="'.get_string('moveto', 'question')."\" />\n";
                question_category_select_menu($addcontexts, false, 0, "$category->id,$category->contextid");
            }

            if (function_exists('module_specific_controls') && $canuseall) {
                $modulespecific = module_specific_controls($totalnumber, $recurse, $category, $this->cm->id, $cmoptions);
                if (!empty($modulespecific)) {
                    echo "<hr />$modulespecific";
                }
            }
        }
        echo "</div>\n";

        echo '</fieldset>';
        echo "</form>\n";
    }

}

/**
 * This class prints a view of the question bank, including
 *
 *  + Some controls to allow users to to select what is displayed.
 *  + A list of questions as a table.
 *  + Further controls to do things with the questions.
 *
 * This class gives a basic view, and provides plenty of hooks where subclasses
 * can override parts of the display.
 *
 * The list of questions presented as a table is generated by creating a list of
 * ipal_question_bank_column objects, one for each 'column' to be displayed. These
 * manage
 *  + outputting the contents of that column, given a $question object, but also
 *  + generating the right fragments of SQL to ensure the necessary data is present,
 *    and sorted in the right order.
 *  + outputting table headers.
 *
 * @copyright  2009 Tim Hunt
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class ipal_original_question_bank_view {
    /** @var int */
    const MAX_SORTS = 3;

    /** @var string */
    protected $baseurl;
    /** @var bool */
    protected $editquestionurl;
    /** @var int */
    protected $quizorcourseid;
    /** @var stdObj */
    protected $contexts;
    /** @var int */
    protected $cm;
    /** @var stdObj */
    protected $course;
    /** @var stdObj */
    protected $knowncolumntypes;
    /** @var stdObj */
    protected $visiblecolumns;
    /** @var stdObj */
    protected $extrarows;
    /** @var stdObj */
    protected $requiredcolumns;
    /** @var bool */
    protected $sort;
    /** @var int */
    protected $lastchangedid;
    /** @var string */
    protected $countsql;
    /** @var string */
    protected $loadsql;
    /** @var string */
    protected $sqlparams;

    /**
     * Constructor
     *
     * @param question_edit_contexts $contexts
     * @param moodle_url $pageurl
     * @param object $course course settings
     * @param object $cm (optional) activity settings.
     */
    public function __construct($contexts, $pageurl, $course, $cm = null) {
        global $CFG, $PAGE;

        $this->contexts = $contexts;
        $this->baseurl = $pageurl;
        $this->course = $course;
        $this->cm = $cm;

        if (!empty($cm) && $cm->modname == 'quiz') {
            $this->quizorcourseid = '&amp;quizid=' . $cm->instance;
        } else {
            $this->quizorcourseid = '&amp;courseid=' .$this->course->id;
        }

        // Create the url of the new question page to forward to.
        $returnurl = str_replace($CFG->wwwroot, '', $pageurl->out(false));
        $this->editquestionurl = new moodle_url('/question/question.php',
                array('returnurl' => $returnurl));
        if ($cm !== null) {
            $this->editquestionurl->param('cmid', $cm->id);
        } else {
            $this->editquestionurl->param('courseid', $this->course->id);
        }

        $this->lastchangedid = optional_param('lastchanged', 0, PARAM_INT);

        $this->init_column_types();
        $this->init_columns($this->wanted_columns());
        $this->init_sort();
    }

    /**
     * Function that returns an array of the columns in the question bank display
     *
     * @return array
     */
    protected function wanted_columns() {
        $columns = array('checkbox', 'qtype', 'questionname', 'editaction',
                'previewaction', 'moveaction', 'deleteaction', 'creatorname',
                'modifiername');
        if (optional_param('qbshowtext', false, PARAM_BOOL)) {
            $columns[] = 'questiontext';
        }
        return $columns;
    }

    /**
     * Function to define fields in the question bank display
     *
     * @return array The array of filed types and their texts.
     */
    protected function known_field_types() {
        return array(
            new ipal_question_bank_checkbox_column($this),
            new ipal_question_bank_question_type_column($this),
            new ipal_question_bank_question_name_column($this),
            new ipal_question_bank_creator_name_column($this),
            new ipal_question_bank_modifier_name_column($this),
            new ipal_question_bank_edit_action_column($this),
            new ipal_question_bank_preview_action_column($this),
            new ipal_question_bank_move_action_column($this),
            new ipal_question_bank_delete_action_column($this),
            new ipal_question_bank_question_text_row($this),
        );
    }

    /**
     * Function to define the column types
     *
     */
    protected function init_column_types() {
        $this->knowncolumntypes = array();
        foreach ($this->known_field_types() as $col) {
            $this->knowncolumntypes[$col->get_name()] = $col;
        }
    }

    /**
     * Function to define the columns in teh question bank table
     *
     * @param stdObj $wanted
     */
    protected function init_columns($wanted) {
        $this->visiblecolumns = array();
        $this->extrarows = array();
        foreach ($wanted as $colname) {
            if (!isset($this->knowncolumntypes[$colname])) {
                throw new coding_exception('Unknown column type ' . $colname . ' requested in init columns.');
            }
            $column = $this->knowncolumntypes[$colname];
            if ($column->is_extra_row()) {
                $this->extrarows[$colname] = $column;
            } else {
                $this->visiblecolumns[$colname] = $column;
            }
        }
        $this->requiredcolumns = array_merge($this->visiblecolumns, $this->extrarows);
    }

    /**
     * Check to see if the column is included in the output
     *
     * @param string $colname a column internal name.
     * @return bool is this column included in the output?
     */
    public function has_column($colname) {
        return isset($this->visiblecolumns[$colname]);
    }

    /**
     * Function to count the number of columns.
     *
     * @return int The number of columns in the table.
     */
    public function get_column_count() {
        return count($this->visiblecolumns);
    }

    /**
     * Function to get the course id
     *
     * @return int The coursre ID.
     */
    public function get_courseid() {
        return $this->course->id;
    }

    /**
     * Function to sort the parameters passed to a URL
     *
     * @return array The sorted paramaters.
     */
    protected function init_sort() {
        $this->init_sort_from_params();
        if (empty($this->sort)) {
            $this->sort = $this->default_sort();
        }
    }

    /**
     * Function to parse column names.
     *
     * Deal with a sort name of the forum columnname, or colname_subsort by
     * breaking it up, validating the bits that are presend, and returning them.
     * If there is no subsort, then $subsort is returned as ''.
     *
     * @param string $sort The string that needs to be parsed.
     * @return array array($colname, $subsort).
     */
    protected function parse_subsort($sort) {
        // Do the parsing.
        if (strpos($sort, '_') !== false) {
            list($colname, $subsort) = explode('_', $sort, 2);
        } else {
            $colname = $sort;
            $subsort = '';
        }
        // Validate the column name.
        if (!isset($this->knowncolumntypes[$colname]) || !$this->knowncolumntypes[$colname]->is_sortable()) {
            for ($i = 1; $i <= ipal_local_question_bank_view::MAX_SORTS; $i++) {
                $this->baseurl->remove_params('qbs' . $i);
            }
            throw new moodle_exception('unknownsortcolumn', '', $link = $this->baseurl->out(), $colname);
        }
        // Validate the subsort, if present.
        if ($subsort) {
            $subsorts = $this->knowncolumntypes[$colname]->is_sortable();
            if (!is_array($subsorts) || !isset($subsorts[$subsort])) {
                throw new moodle_exception('unknownsortcolumn', '', $link = $this->baseurl->out(), $sort);
            }
        }
        return array($colname, $subsort);
    }

    /**
     * Do an initial sort on the paramters passed to a URL
     *
     */
    protected function init_sort_from_params() {
        $this->sort = array();
        for ($i = 1; $i <= ipal_local_question_bank_view::MAX_SORTS; $i++) {
            if (!$sort = optional_param('qbs' . $i, '', PARAM_ALPHAEXT)) {
                break;
            }
            // Work out the appropriate order.
            $order = 1;
            if ($sort[0] == '-') {
                $order = -1;
                $sort = substr($sort, 1);
                if (!$sort) {
                    break;
                }
            }
            // Deal with subsorts.
            list($colname, $subsort) = $this->parse_subsort($sort);
            $this->requiredcolumns[$colname] = $this->knowncolumntypes[$colname];
            $this->sort[$sort] = $order;
        }
    }

    /**
     * Sorting information to be used as parameters.
     *
     * @param stdObj $sorts The information to be sorted.
     */
    protected function sort_to_params($sorts) {
        $params = array();
        $i = 0;
        foreach ($sorts as $sort => $order) {
            $i += 1;
            if ($order < 0) {
                $sort = '-' . $sort;
            }
            $params['qbs' . $i] = $sort;
        }
        return $params;
    }

    /**
     * Doing the default sort on information.
     *
     * @return array
     */
    protected function default_sort() {
        return array('qtype' => 1, 'questionname' => 1);
    }

    /**
     * Function to sort column names.
     *
     * @param string $sort a column or column_subsort name.
     * @return int the current sort order for this column -1, 0, 1
     */
    public function get_primary_sort_order($sort) {
        $order = reset($this->sort);
        $primarysort = key($this->sort);
        if ($sort == $primarysort) {
            return $order;
        } else {
            return 0;
        }
    }

    /**
     * Get a URL to redisplay the page with a new sort for the question bank.
     *
     * @param string $sort the column, or column_subsort to sort on.
     * @param bool $newsortreverse whether to sort in reverse order.
     * @return string The new URL.
     */
    public function new_sort_url($sort, $newsortreverse) {
        if ($newsortreverse) {
            $order = -1;
        } else {
            $order = 1;
        }
        // Tricky code to add the new sort at the start, removing it from where it was before, if it was present.
        $newsort = array_reverse($this->sort);
        if (isset($newsort[$sort])) {
            unset($newsort[$sort]);
        }
        $newsort[$sort] = $order;
        $newsort = array_reverse($newsort);
        if (count($newsort) > ipal_local_question_bank_view::MAX_SORTS) {
            $newsort = array_slice($newsort, 0, ipal_local_question_bank_view::MAX_SORTS, true);
        }
        return $this->baseurl->out(true, $this->sort_to_params($newsort));
    }

    /**
     * Function to build an sql query about a category.
     *
     * @param stdObj $category Information about the category.
     * @param bool $recurse
     * @param bool $showhidden
     */
    protected function build_query_sql($category, $recurse, $showhidden) {
        global $DB;

        // Get the required tables.
        $joins = array();
        foreach ($this->requiredcolumns as $column) {
            $extrajoins = $column->get_extra_joins();
            foreach ($extrajoins as $prefix => $join) {
                if (isset($joins[$prefix]) && $joins[$prefix] != $join) {
                    throw new coding_exception('Join ' . $join . ' conflicts with previous join ' . $joins[$prefix]);
                }
                $joins[$prefix] = $join;
            }
        }

        // Get the required fields.
        $fields = array('q.hidden', 'q.category');
        foreach ($this->visiblecolumns as $column) {
            $fields = array_merge($fields, $column->get_required_fields());
        }
        foreach ($this->extrarows as $row) {
            $fields = array_merge($fields, $row->get_required_fields());
        }
        $fields = array_unique($fields);

        // Build the order by clause.
        $sorts = array();
        foreach ($this->sort as $sort => $order) {
            list($colname, $subsort) = $this->parse_subsort($sort);
            $sorts[] = $this->knowncolumntypes[$colname]->sort_expression($order < 0, $subsort);
        }

        // Build the where clause.
        $tests = array('parent = 0');

        if (!$showhidden) {
            $tests[] = 'hidden = 0';
        }

        if ($recurse) {
            $categoryids = question_categorylist($category->id);
        } else {
            $categoryids = array($category->id);
        }
        list($catidtest, $params) = $DB->get_in_or_equal($categoryids, SQL_PARAMS_NAMED, 'cat');
        $tests[] = 'q.category ' . $catidtest;
        $this->sqlparams = $params;

        // Build the SQL.
        $sql = ' FROM {question} q ' . implode(' ', $joins);
        $sql .= ' WHERE ' . implode(' AND ', $tests);
        $this->countsql = 'SELECT count(1)' . $sql;
        $this->loadsql = 'SELECT ' . implode(', ', $fields) . $sql . ' ORDER BY ' . implode(', ', $sorts);
        $this->sqlparams = $params;
    }

    /**
     * Get the number of questions.
     *
     */
    protected function get_question_count() {
        global $DB;
        return $DB->count_records_sql($this->countsql, $this->sqlparams);
    }

    /**
     * Assign questions to specific pages.
     *
     * @param int $page The numeer of the page.
     * @param int $perpage The number of questions per page.
     * @return stdObj The questions on that page.
     */
    protected function load_page_questions($page, $perpage) {
        global $DB;
        $questions = $DB->get_recordset_sql($this->loadsql, $this->sqlparams, $page * $perpage, $perpage);
        if (!$questions->valid()) {
            // No questions on this page. Reset to page 0.
            $questions = $DB->get_recordset_sql($this->loadsql, $this->sqlparams, 0, $perpage);
        }
        return $questions;
    }

    /**
     * Get the base URL
     *
     * @return string The base URL
     */
    public function base_url() {
        return $this->baseurl;
    }

    /**
     * Edit the question URL
     *
     * @param int $questionid
     */
    public function edit_question_url($questionid) {
        return $this->editquestionurl->out(true, array('id' => $questionid));
    }

    /**
     * Move the question URL
     *
     * @param int $questionid
     */
    public function move_question_url($questionid) {
        return $this->editquestionurl->out(true, array('id' => $questionid, 'movecontext' => 1));
    }

    /**
     * The URL to preview the question
     *
     * @param stdObj $question Information about the question.
     */
    public function preview_question_url($question) {
        return question_preview_url($question->id, null, null, null, null,
                $this->contexts->lowest());
    }

    /**
     * Shows the question bank editing interface.
     *
     * The function also processes a number of actions:
     *
     * Actions affecting the question pool:
     * move           Moves a question to a different category
     * deleteselected Deletes the selected questions from the category
     * Other actions:
     * category      Chooses the category
     * displayoptions Sets display options
     * @param string $tabname
     * @param int $page The page being worked on.
     * @param int $perpage Number of questions per page.
     * @param stdObj $cat Information about the category.
     * @param bool $recurse Is it recursive
     * @param bool $showhidden Is it hidden or shown.
     * @param bool $showquestiontext Show or hide question text.
     */
    public function display($tabname, $page, $perpage, $cat,
            $recurse, $showhidden, $showquestiontext) {
        global $PAGE, $OUTPUT;

        if ($this->process_actions_needing_ui()) {
            return;
        }

        $PAGE->requires->js('/question/qbank.js');

        // Category selection form.
        echo $OUTPUT->heading(get_string('questionbank', 'question'), 2);

        $this->display_category_form($this->contexts->having_one_edit_tab_cap($tabname),
                $this->baseurl, $cat);
        $this->display_options($recurse, $showhidden, $showquestiontext);

        if (!$category = $this->get_current_category($cat)) {
            return;
        }
        $this->print_category_info($category);

        // Continues with list of questions.
        $this->display_question_list($this->contexts->having_one_edit_tab_cap($tabname),
                $this->baseurl, $cat, $this->cm,
                $recurse, $page, $perpage, $showhidden, $showquestiontext,
                $this->contexts->having_cap('moodle/question:add'));
    }

    /**
     * Print the category message.
     *
     * @param stdObj $categoryandcontext
     */
    protected function print_choose_category_message($categoryandcontext) {
        echo "<p style=\"text-align:center;\"><b>";
        print_string("selectcategoryabove", "question");
        echo "</b></p>";
    }

    /**
     * GEt current category
     *
     * @param stdObj $categoryandcontext
     */
    protected function get_current_category($categoryandcontext) {
        global $DB, $OUTPUT;
        list($categoryid, $contextid) = explode(',', $categoryandcontext);
        if (!$categoryid) {
            $this->print_choose_category_message($categoryandcontext);
            return false;
        }

        if (!$category = $DB->get_record('question_categories',
                array('id' => $categoryid, 'contextid' => $contextid))) {
            echo $OUTPUT->box_start('generalbox questionbank');
            echo $OUTPUT->notification('Category not found!');
            echo $OUTPUT->box_end();
            return false;
        }

        return $category;
    }

    /**
     * Print category information.
     *
     * @param stdObj $category
     */
    protected function print_category_info($category) {
        $formatoptions = new stdClass();
        $formatoptions->noclean = true;
        $formatoptions->overflowdiv = true;
        echo '<div class="boxaligncenter">';
        echo format_text($category->info, $category->infoformat, $formatoptions, $this->course->id);
        echo "</div>\n";
    }

    /**
     * prints a form to choose categories
     *
     * @param stdObj $contexts
     * @param strng $pageurl
     * @param stdObj $current
     */
    protected function display_category_form($contexts, $pageurl, $current) {
        global $CFG, $OUTPUT;

        // Get all the existing categories now.
        echo '<div class="choosecategory">';
        $catmenu = question_category_options($contexts, false, 0, true);

        $select = new single_select($this->baseurl, 'category', $catmenu, $current, null, 'catmenu');
        $select->set_label(get_string('selectacategory', 'question'));
        echo $OUTPUT->render($select);
        echo "</div>\n";
    }

    /**
     * Display the options for the question bank.
     *
     * @param bool $recurse Is it recursive.
     * @param bool $showhidden Is it hidden or not
     * @param bool $showquestiontext Is the text of the question to be shown.
     */
    protected function display_options($recurse, $showhidden, $showquestiontext) {
        echo '<form method="get" action="edit.php" id="displayoptions">';
        echo "<fieldset class='invisiblefieldset'>";
        echo html_writer::input_hidden_params($this->baseurl, array('recurse', 'showhidden', 'qbshowtext'));
        $this->display_category_form_checkbox('recurse', $recurse, get_string('includesubcategories', 'question'));
        $this->display_category_form_checkbox('showhidden', $showhidden, get_string('showhidden', 'question'));
        $this->display_category_form_checkbox('qbshowtext', $showquestiontext, get_string('showquestiontext', 'question'));
        echo '<noscript><div class="centerpara"><input type="submit" value="'. get_string('go') .'" />';
        echo '</div></noscript></fieldset></form>';
    }

    /**
     * Print a single option checkbox. Used by the preceeding.
     *
     * @param string $name The name of the checkbox.
     * @param string $value The value of the checkbox.
     * @param string $label The text displayed for the checkbox.
     */
    protected function display_category_form_checkbox($name, $value, $label) {
        echo '<div><input type="hidden" id="' . $name . '_off" name="' . $name . '" value="0" />';
        echo '<input type="checkbox" id="' . $name . '_on" name="' . $name . '" value="1"';
        if ($value) {
            echo ' checked="checked"';
        }
        echo ' onchange="getElementById(\'displayoptions\').submit(); return true;" />';
        echo '<label for="' . $name . '_on">' . $label . '</label>';
        echo "</div>\n";
    }

    /**
     * Print button for creating a new question.
     *
     * @param stdObj $category The category for the question.
     * @param bool $canadd Does the user have permissions to add a new question.
     */
    protected function create_new_question_form($category, $canadd) {
        global $CFG;
        echo '<div class="createnewquestion">';
        if ($canadd) {
            ipal_create_new_question_button($category->id, $this->editquestionurl->params(),
                    get_string('createnewquestion', 'question'));
        } else {
            print_string('nopermissionadd', 'question');
        }
        echo '</div>';
    }

    /**
     * Prints the table of questions in a category with interactions
     *
     * @param object $contexts   The contexts of the questions.
     * @param string $pageurl The URL of the page.
     * @param object $categoryandcontext  The category and context of the question.
     * @param int $cm      The course module record if we are in the context of a particular module, 0 otherwise
     * @param int $recurse     This is 1 if subcategories should be included, 0 otherwise
     * @param int $page        The number of the page to be displayed
     * @param int $perpage     Number of questions to show per page
     * @param bool $showhidden   True if also hidden questions should be displayed
     * @param bool $showquestiontext whether the text of each question should be shown in the list
     * @param array $addcontexts The contexts that are to be added.
     */
    protected function display_question_list($contexts, $pageurl, $categoryandcontext,
            $cm = null, $recurse=1, $page=0, $perpage=100, $showhidden=false,
            $showquestiontext = false, $addcontexts = array()) {
        global $CFG, $DB, $OUTPUT;

        $category = $this->get_current_category($categoryandcontext);

        $cmoptions = new stdClass();
        $cmoptions->hasattempts = !empty($this->quizhasattempts);

        $strselectall = get_string('selectall');
        $strselectnone = get_string('deselectall');
        $strdelete = get_string('delete');

        list($categoryid, $contextid) = explode(',', $categoryandcontext);

        $catcontext = context::instance_by_id($contextid);

        $canadd = has_capability('moodle/question:add', $catcontext);
        $caneditall = has_capability('moodle/question:editall', $catcontext);
        $canuseall = has_capability('moodle/question:useall', $catcontext);
        $canmoveall = has_capability('moodle/question:moveall', $catcontext);

        $this->create_new_question_form($category, $canadd);

        $this->build_query_sql($category, $recurse, $showhidden);
        $totalnumber = $this->get_question_count();
        if ($totalnumber == 0) {
            return;
        }

        $questions = $this->load_page_questions($page, $perpage);

        echo '<div class="categorypagingbarcontainer">';
        $pageingurl = new moodle_url('edit.php');
        $r = $pageingurl->params($pageurl->params());
        $pagingbar = new paging_bar($totalnumber, $page, $perpage, $pageingurl);
        $pagingbar->pagevar = 'qpage';
        echo $OUTPUT->render($pagingbar);
        echo '</div>';

        echo '<form method="post" action="edit.php">';
        echo '<fieldset class="invisiblefieldset" style="display: block;">';
        echo '<input type="hidden" name="sesskey" value="'.sesskey().'" />';
        echo html_writer::input_hidden_params($pageurl);

        echo '<div class="categoryquestionscontainer">';
        $this->start_table();
        $rowcount = 0;
        foreach ($questions as $question) {
            $this->print_table_row($question, $rowcount);
            $rowcount += 1;
        }
        $this->end_table();
        echo "</div>\n";

        echo '<div class="categorypagingbarcontainer pagingbottom">';
        echo $OUTPUT->render($pagingbar);
        if ($totalnumber > DEFAULT_QUESTIONS_PER_PAGE) {
            if ($perpage == DEFAULT_QUESTIONS_PER_PAGE) {
                $url = new moodle_url('edit.php', ($pageurl->params() + array('qperpage' => 1000)));
                $showall = '<a href="'.$url.'">'.get_string('showall', 'moodle', $totalnumber).'</a>';
            } else {
                $url = new moodle_url('edit.php', ($pageurl->params() + array('qperpage' => DEFAULT_QUESTIONS_PER_PAGE)));
                $showall = '<a href="'.$url.'">'.get_string('showperpage', 'moodle', DEFAULT_QUESTIONS_PER_PAGE).'</a>';
            }
            echo "<div class='paging'>$showall</div>";
        }
        echo '</div>';

        echo '<div class="modulespecificbuttonscontainer">';
        if ($caneditall || $canmoveall || $canuseall) {
            echo '<strong>&nbsp;'.get_string('withselected', 'question').':</strong><br />';

            if (function_exists('module_specific_buttons')) {
                echo module_specific_buttons($this->cm->id, $cmoptions);
            }

            // Print delete and move selected question.
            if ($caneditall) {
                echo '<input type="submit" name="deleteselected" value="' . $strdelete . "\" />\n";
            }

            if ($canmoveall && count($addcontexts)) {
                echo '<input type="submit" name="move" value="'.get_string('moveto', 'question')."\" />\n";
                question_category_select_menu($addcontexts, false, 0, "$category->id,$category->contextid");
            }

            if (function_exists('module_specific_controls') && $canuseall) {
                $modulespecific = module_specific_controls($totalnumber, $recurse, $category, $this->cm->id, $cmoptions);
                if (!empty($modulespecific)) {
                    echo "<hr />$modulespecific";
                }
            }
        }
        echo "</div>\n";

        echo '</fieldset>';
        echo "</form>\n";
    }

    /**
     * print start and head of table.
     *
     */
    protected function start_table() {
        echo '<table id="categoryquestions">' . "\n";
        echo "<thead>\n";
        $this->print_table_headers();
        echo "</thead>\n";
        echo "<tbody>\n";
    }

    /**
     * Print end of table.
     *
     */
    protected function end_table() {
        echo "</tbody>\n";
        echo "</table>\n";
    }

    /**
     * Print the headers of the table.
     *
     */
    protected function print_table_headers() {
        echo "<tr>\n";
        foreach ($this->visiblecolumns as $column) {
            $column->display_header();
        }
        echo "</tr>\n";
    }

    /**
     * Get the CSS classes for the row.
     *
     * @param object $question the row from the $question table, augmented with extra information.
     * @param int $rowcount The number of rows in the table.
     */
    protected function get_row_classes($question, $rowcount) {
        $classes = array();
        if ($question->hidden) {
            $classes[] = 'dimmed_text';
        }
        if ($question->id == $this->lastchangedid) {
            $classes[] = 'highlight';
        }
        if (!empty($this->extrarows)) {
            $classes[] = 'r' . ($rowcount % 2);
        }
        return $classes;
    }

    /**
     * Print a row of the table.
     *
     * @param object $question the row from the $question table, augmented with extra information.
     * @param int $rowcount The number of rows in the table.
     */
    protected function print_table_row($question, $rowcount) {
        $rowclasses = implode(' ', $this->get_row_classes($question, $rowcount));
        if ($rowclasses) {
            echo '<tr class="' . $rowclasses . '">' . "\n";
        } else {
            echo "<tr>\n";
        }
        foreach ($this->visiblecolumns as $column) {
            $column->display($question, $rowclasses);
        }
        echo "</tr>\n";
        foreach ($this->extrarows as $row) {
            $row->display($question, $rowclasses);
        }
    }

    /**
     * Taking care of the commands (actions) for this page.
     *
     */
    public function process_actions() {
        global $CFG, $DB;
        // Now, check for commands on this page and modify variables as necessary.
        if (optional_param('move', false, PARAM_BOOL) and confirm_sesskey()) {
            // Move selected questions to new category.
            $category = required_param('category', PARAM_SEQUENCE);
            list($tocategoryid, $contextid) = explode(',', $category);
            if (! $tocategory = $DB->get_record('question_categories', array('id' => $tocategoryid, 'contextid' => $contextid))) {
                print_error('cannotfindcate', 'question');
            }

            $tocontext = context::instance_by_id($contextid);
            require_capability('moodle/question:add', $tocontext);
            $rawdata = (array) data_submitted();
            $questionids = array();
            foreach ($rawdata as $key => $value) {    // Parse input for question ids.
                if (preg_match('!^q([0-9]+)$!', $key, $matches)) {
                    $key = $matches[1];
                    $questionids[] = $key;
                }
            }
            if ($questionids) {
                list($usql, $params) = $DB->get_in_or_equal($questionids);
                $sql = "";
                $questions = $DB->get_records_sql("
                        SELECT q.*, c.contextid
                        FROM {question} q
                        JOIN {question_categories} c ON c.id = q.category
                        WHERE q.id $usql", $params);
                foreach ($questions as $question) {
                    ipal_question_require_capability_on($question, 'move');
                }
                    ipal_question_move_questions_to_category($questionids, $tocategory->id);
                redirect($this->baseurl->out(false,
                        array('category' => "$tocategoryid,$contextid")));
            }
        }

        if (optional_param('deleteselected', false, PARAM_BOOL)) { // Delete selected questions from the category.
            if (($confirm = optional_param('confirm', '', PARAM_ALPHANUM)) and confirm_sesskey()) {
                // Teacher has already confirmed the action.
                $deleteselected = required_param('deleteselected', PARAM_RAW);
                if ($confirm == md5($deleteselected)) {
                    if ($questionlist = explode(',', $deleteselected)) {
                        // For each question either hide it if it is in use or delete it.
                        foreach ($questionlist as $questionid) {
                            $questionid = (int)$questionid;
                            question_require_capability_on($questionid, 'edit');
                            if (questions_in_use(array($questionid))) {
                                $DB->set_field('question', 'hidden', 1, array('id' => $questionid));
                            } else {
                                question_delete_question($questionid);
                            }
                        }
                    }
                    redirect($this->baseurl);
                } else {
                    print_error('invalidconfirm', 'question');
                }
            }
        }

        // Unhide a question.
        if (($unhide = optional_param('unhide', '', PARAM_INT)) and confirm_sesskey()) {
            question_require_capability_on($unhide, 'edit');
            $DB->set_field('question', 'hidden', 0, array('id' => $unhide));
            redirect($this->baseurl);
        }
    }

    /**
     * Take care of actions on questions.
     *
     */
    public function process_actions_needing_ui() {
        global $DB, $OUTPUT;
        if (optional_param('deleteselected', false, PARAM_BOOL)) {
            // Make a list of all the questions that are selected.
            $rawquestions = $_REQUEST; // This code is called by both POST forms and GET links, so cannot use data_submitted.
            $questionlist = '';  // Comma separated list of ids of questions to be deleted.
            $questionnames = ''; // String with names of questions separated by <br />.
                                 // An asterix in front of those that are in use.
            $inuse = false;      // Set to true if at least one of the questions is in use.
            foreach ($rawquestions as $key => $value) {    // Parse input for question ids.
                if (preg_match('!^q([0-9]+)$!', $key, $matches)) {
                    $key = $matches[1];
                    $questionlist .= $key.',';
                    question_require_capability_on($key, 'edit');
                    if (questions_in_use(array($key))) {
                        $questionnames .= '* ';
                        $inuse = true;
                    }
                    $questionnames .= $DB->get_field('question', 'name', array('id' => $key)) . '<br />';
                }
            }
            if (!$questionlist) { // No questions were selected.
                redirect($this->baseurl);
            }
            $questionlist = rtrim($questionlist, ',');

            // Add an explanation about questions in use.
            if ($inuse) {
                $questionnames .= '<br />'.get_string('questionsinuse', 'question');
            }
            $baseurl = new moodle_url('edit.php', $this->baseurl->params());
            $deleteurl = new moodle_url($baseurl, array('deleteselected' => $questionlist,
                'confirm' => md5($questionlist), 'sesskey' => sesskey()));

            echo $OUTPUT->confirm(get_string('deletequestionscheck', 'question', $questionnames), $deleteurl, $baseurl);

            return true;
        }
    }
}

/**
 * Gets the default category in the most specific context.
 * If no categories exist yet then default ones are created in all contexts.
 *
 * @param array $contexts  The context objects for this context and all parent contexts.
 * @return object The default category - the category in the course context
 * Modified by W. F. Junkin 2012.02.14 for IPAL so that version updates won't impact IPAL
 */
function ipal_question_make_default_categories($contexts) {
    global $DB;
    static $preferredlevels = array(
        CONTEXT_COURSE => 4,
        CONTEXT_MODULE => 3,
        CONTEXT_COURSECAT => 2,
        CONTEXT_SYSTEM => 1,
    );

    $toreturn = null;
    $preferredness = 0;
    // If it already exists, just return it.
    foreach ($contexts as $key => $context) {
        if (!$exists = $DB->record_exists("question_categories",
                array('contextid' => $context->id))) {
            // Otherwise, we need to make one.
            $category = new stdClass();
            $contextname = print_context_name($context, false, true);
            $category->name = get_string('defaultfor', 'question', $contextname);
            $category->info = get_string('defaultinfofor', 'question', $contextname);
            $category->contextid = $context->id;
            $category->parent = 0;
            // By default, all categories get this number, and are sorted alphabetically.
            $category->sortorder = 999;
            $category->stamp = make_unique_id_code();
            $category->id = $DB->insert_record('question_categories', $category);
        } else {
            $category = ipal_question_get_default_category($context->id);
        }
        if ($preferredlevels[$context->contextlevel] > $preferredness && has_any_capability(
                array('moodle/question:usemine', 'moodle/question:useall'), $context)) {
            $toreturn = $category;
            $preferredness = $preferredlevels[$context->contextlevel];
        }
    }

    if (!is_null($toreturn)) {
        $toreturn = clone($toreturn);
    }
    return $toreturn;
}


/**
 * Get the default question category for this context.
 *
 * @param integer $contextid a context id.
 * @return object the default question category for that context, or false if none.
 * Modified by W. F. Junkin 2012.02.14 for IPAL to avoid version updates from impacting IPAL
 */
function ipal_question_get_default_category($contextid) {
    global $DB;
    $category = $DB->get_records('question_categories',
            array('contextid' => $contextid), 'id', '*', 0, 1);
    if (!empty($category)) {
        return reset($category);
    } else {
        return false;
    }
}


/**
 * Add a question to an ipal instance  Modified from Add a question to quiz in mod_quiz_editlib.php
 *
 * Adds a question to an ipal by updating $quiz as well as the ipal table.
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
            // Add ending page break - the following logic requires doing.
            // This at this point.
            $questions[] = 0;
            $currentpage = 1;
            $addnow = false;
            foreach ($questions as $question) {
                if ($question == 0) {
                    $currentpage++;
                    // The current page is the one after the one we want to add on.
                    // So we add the question before adding the current page.
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

    // Add the new question instance.// Modified for ipal NOt needed in ipal.
}


/**
 * Remove a question from an ipal instance
 * @param object $quiz the ipal object.
 * @param int $questionid The id of the question to be deleted.
 */
function ipal_remove_question($quiz, $questionid) {
    global $DB;

    $questionids = explode(',', $quiz->questions);
    $key = array_search($questionid, $questionids);
    if ($key === false) {
        return;
    }

    unset($questionids[$key]);
    $quiz->questions = implode(',', $questionids);
    $DB->set_field('ipal', 'questions', $quiz->questions, array('id' => $quiz->id));
}


/**
 * Clean the question layout from various possible anomalies:
 * - Remove consecutive ","'s
 * - Remove duplicate question id's
 * - Remove extra "," from beginning and end
 * - Finally, add a ",0" in the end if there is none
 *
 * @param $string $layout the quiz layout to clean up, usually from $quiz->questions.
 * @param bool $removeemptypages If true, remove empty pages from the quiz. False by default.
 * @return $string the cleaned-up layout
 */
function ipal_clean_layout($layout, $removeemptypages = false) {
    // Remove repeated ','s. This can happen when a restore fails to find the right id to relink to.

    $layout = preg_replace('/,{2,}/', ',', trim($layout, ','));

    // Remove duplicate question ids.
    $layout = explode(',', $layout);
    $cleanerlayout = array();
    $seen = array();
    foreach ($layout as $item) {
        if ($item == 0) {
            $cleanerlayout[] = '0';
        } else if (!in_array($item, $seen)) {
            $cleanerlayout[] = $item;
            $seen[] = $item;
        }
    }

    if ($removeemptypages) {
        // Avoid duplicate page breaks.
        $layout = $cleanerlayout;
        $cleanerlayout = array();
        $stripfollowingbreaks = true; // Ensure breaks are stripped from the start.
        foreach ($layout as $item) {
            if ($stripfollowingbreaks && $item == 0) {
                continue;
            }
            $cleanerlayout[] = $item;
            $stripfollowingbreaks = $item == 0;
        }
    }

    // Add a page break at the end if there is none.
    if (end($cleanerlayout) !== '0') {
        $cleanerlayout[] = '0';
    }

    return implode(',', $cleanerlayout);
}

/**
 * A column with a checkbox for each question with name q{questionid}.
 *
 * @copyright  2009 Tim Hunt
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class ipal_question_bank_checkbox_column extends ipal_question_bank_column_base {
    /** @var tring */
    protected $strselect;
    /** @var bool */
    protected $firstrow = true;

    /**
     * Initalize the select string.
     *
     */
    public function init() {
        $this->strselect = get_string('select');
    }

    /**
     * Get the name of the checkbox column.
     *
     */
    public function get_name() {
        return 'checkbox';
    }

    /**
     * GEt the title of the checkbox column.
     *
     */
    protected function get_title() {
        return '<input type="checkbox" disabled="disabled" id="qbheadercheckbox" />';
    }

    /**
     * Get the tip of the checkbox column.
     *
     */
    protected function get_title_tip() {
        return get_string('selectquestionsforbulk', 'question');
    }

    /**
     * Display the content of the checkbox column.
     *
     * @param object $question the row from the $question table, augmented with extra information.
     * @param string $rowclasses CSS class names that should be applied to this row of output.
     */
    protected function display_content($question, $rowclasses) {
        global $PAGE;
        echo '<input title="' . $this->strselect . '" type="checkbox" name="q' .
                $question->id . '" id="checkq' . $question->id . '" value="1"/>';
    }

    /**
     * GEt the required fields where the information is found.
     *
     */
    public function get_required_fields() {
        return array('q.id');
    }
}


/**
 * Base class for representing a column in a {@link ipal_local_question_bank_view}.
 *
 * @copyright  2009 Tim Hunt
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
abstract class ipal_question_bank_column_base {
    /**
     * @var ipal_local_question_bank_view
     */
    protected $qbank;

    /**
     * Constructor.
     *
     * @param ipal_local_question_bank_view $qbank the ipal_local_question_bank_view we are helping to render.
     */
    public function __construct(ipal_local_question_bank_view $qbank) {
        $this->qbank = $qbank;
        $this->init();
    }

    /**
     * A chance for subclasses to initialise themselves, for example to load lang strings,
     * without having to override the constructor.
     */
    protected function init() {
    }

    /**
     * There is no extra row.
     *
     */
    public function is_extra_row() {
        return false;
    }

    /**
     * Output the column header cell.
     */
    public function display_header() {
        echo '<th class="header ' . $this->get_classes() . '" scope="col">';
        $sortable = $this->is_sortable();
        $name = $this->get_name();
        $title = $this->get_title();
        $tip = $this->get_title_tip();
        if (is_array($sortable)) {
            if ($title) {
                echo '<div class="title">' . $title . '</div>';
            }
            $links = array();
            foreach ($sortable as $subsort => $details) {
                $links[] = $this->make_sort_link($name . '_' . $subsort,
                        $details['title'], '', !empty($details['reverse']));
            }
            echo '<div class="sorters">' . implode(' / ', $links) . '</div>';
        } else if ($sortable) {
            echo $this->make_sort_link($name, $title, $tip);
        } else {
            if ($tip) {
                echo '<span title="' . $tip . '">';
            }
            echo $title;
            if ($tip) {
                echo '</span>';
            }
        }
        echo "</th>\n";
    }

    /**
     * Title for this column. Not used if is_sortable returns an array.
     *
     */
    protected abstract function get_title();

    /**
     * REturn title if it exists.
     *
     * @return string a fuller version of the name. Use this when get_title() returns
     * something very short, and you want a longer version as a tool tip.
     */
    protected function get_title_tip() {
        return '';
    }

    /**
     * Get a link that changes the sort order, and indicates the current sort state.
     *
     * @param string $sort The sort order.
     * @param string $title The title.
     * @param string $tip The tip for the title.
     * @param bool $defaultreverse whether the default sort order for this column is descending, rather than ascending.
     * @return string HTML fragment.
     */
    protected function make_sort_link($sort, $title, $tip, $defaultreverse = false) {
        $currentsort = $this->qbank->get_primary_sort_order($sort);
        $newsortreverse = $defaultreverse;
        if ($currentsort) {
            $newsortreverse = $currentsort > 0;
        }
        if (!$tip) {
            $tip = $title;
        }
        if ($newsortreverse) {
            $tip = get_string('sortbyxreverse', '', $tip);
        } else {
            $tip = get_string('sortbyx', '', $tip);
        }
        $link = '<a href="' . $this->qbank->new_sort_url($sort, $newsortreverse) . '" title="' . $tip . '">';
        $link .= $title;
        if ($currentsort) {
            $link .= $this->get_sort_icon($currentsort < 0);
        }
        $link .= '</a>';
        return $link;
    }

    /**
     * Get an icon representing the corrent sort state.
     *
     * @param bool $reverse sort is descending, not ascending.
     * @return string HTML image tag.
     */
    protected function get_sort_icon($reverse) {
        global $OUTPUT;
        if ($reverse) {
            return ' <img src="' . $OUTPUT->pix_url('t/up') . '" alt="' . get_string('desc') . '" />';
        } else {
            return ' <img src="' . $OUTPUT->pix_url('t/down') . '" alt="' . get_string('asc') . '" />';
        }
    }

    /**
     * Output this column.
     *
     * @param object $question the row from the $question table, augmented with extra information.
     * @param string $rowclasses CSS class names that should be applied to this row of output.
     */
    public function display($question, $rowclasses) {
        $this->display_start($question, $rowclasses);
        $this->display_content($question, $rowclasses);
        $this->display_end($question, $rowclasses);
    }

    /**
     * The is the start tag of the table.
     *
     * @param object $question the row from the $question table, augmented with extra information.
     * @param string $rowclasses CSS class names that should be applied to this row of output.
     */
    protected function display_start($question, $rowclasses) {
        echo '<td class="' . $this->get_classes() . '">';
    }

    /**
     * Get the CSS for the classes in the table.
     *
     * @return string the CSS classes to apply to every cell in this column.
     */
    protected function get_classes() {
        $classes = $this->get_extra_classes();
        $classes[] = $this->get_name();
        return implode(' ', $classes);
    }

    /**
     * REturn the internal class name for this column.
     *
     * @return string internal name for this column. Used as a CSS class name,
     *     and to store information about the current sort. Must match PARAM_ALPHA.
     */
    public abstract function get_name();

    /**
     * Get extra classes (fi there are any).
     *
     * @return array any extra class names you would like applied to every cell in this column.
     */
    public function get_extra_classes() {
        return array();
    }

    /**
     * Output the contents of this column.
     *
     * @param object $question the row from the $question table, augmented with extra information.
     * @param string $rowclasses CSS class names that should be applied to this row of output.
     */
    protected abstract function display_content($question, $rowclasses);

    /**
     * Print the end tag for the table.
     *
     * @param stdObj $question Information about th equestion.
     * @param string $rowclasses CSS class names that should be applied to this row of output.
     */
    protected function display_end($question, $rowclasses) {
        echo "</td>\n";
    }

    /**
     * Return an array 'table_alias' => 'JOIN clause' to bring in any data that
     * this column required.
     *
     * The return values for all the columns will be checked. It is OK if two
     * columns join in the same table with the same alias and identical JOIN clauses.
     * If to columns try to use the same alias with different joins, you get an error.
     * The only table included by default is the question table, which is aliased to 'q'.
     *
     * It is importnat that your join simply adds additional data (or NULLs) to the
     * existing rows of the query. It must not cause additional rows.
     *
     * @return array 'table_alias' => 'JOIN clause'
     */
    public function get_extra_joins() {
        return array();
    }

    /**
     * GEt the required fields for the table information.
     *
     * @return array fields required. use table alias 'q' for the question table, or one of the
     * ones from get_extra_joins. Every field requested must specify a table prefix.
     */
    public function get_required_fields() {
        return array();
    }

    /**
     * Can this column be sorted on? You can return either:
     *  + false for no (the default),
     *  + a field name, if sorting this column corresponds to sorting on that datbase field.
     *  + an array of subnames to sort on as follows
     *  return array(
     *      'firstname' => array('field' => 'uc.firstname', 'title' => get_string('firstname')),
     *      'lastname' => array('field' => 'uc.lastname', 'field' => get_string('lastname')),
     *  );
     * As well as field, and field, you can also add 'revers' => 1 if you want the default sort
     * order to be DESC.
     * @return mixed as above.
     */
    public function is_sortable() {
        return false;
    }

    /**
     * Helper method for building sort clauses.
     *
     * @param bool $reverse whether the normal direction should be reversed.
     * @return string 'ASC' or 'DESC'
     */
    protected function sortorder($reverse) {
        if ($reverse) {
            return ' DESC';
        } else {
            return ' ASC';
        }
    }

    /**
     * Provide the order by part of the sql statement.
     *
     * @param bool $reverse Whether to sort in the reverse of the default sort order.
     * @param array $subsort if is_sortable returns an array of subnames, then this will be
     *      one of those. Otherwise will be empty.
     * @return string some SQL to go in the order by clause.
     */
    public function sort_expression($reverse, $subsort) {
        $sortable = $this->is_sortable();
        if (is_array($sortable)) {
            if (array_key_exists($subsort, $sortable)) {
                return $sortable[$subsort]['field'] . $this->sortorder($reverse, !empty($sortable[$subsort]['reverse']));
            } else {
                throw new coding_exception('Unexpected $subsort type: ' . $subsort);
            }
        } else if ($sortable) {
            return $sortable . $this->sortorder($reverse);
        } else {
            throw new coding_exception('sort_expression called on a non-sortable column.');
        }
    }
}

/**
 * A column type for the name of the question type.
 *
 * @copyright  2009 Tim Hunt
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class ipal_question_bank_question_type_column extends ipal_question_bank_column_base {

    /**
     * Get the name for the question type column in the question bank.
     *
     */
    public function get_name() {
        return 'qtype';
    }

    /**
     * Get the title for the column for question type column in the question bank.
     *
     */
    protected function get_title() {
        return get_string('qtypeveryshort', 'question');
    }

    /**
     *  GEt the tip for the question type column in the question bank.
     *
     */
    protected function get_title_tip() {
        return get_string('questiontype', 'question');
    }

    /**
     * Display the correct items in teh question type column in the question bank.
     *
     * @param stdObj $question INformation about the question.
     * @param string $rowclasses CSS class names that should be applied to this row of output.
     */
    protected function display_content($question, $rowclasses) {
        echo print_question_icon($question);
    }

    /**
     * Get the fields that provide the necessary information.
     *
     */
    public function get_required_fields() {
        return array('q.qtype');
    }

    /**
     * Indicate that the questions are sortable by question type.
     *
     */
    public function is_sortable() {
        return 'q.qtype';
    }
}

/**
 * A column type for the name of the question name.
 *
 * @copyright  2009 Tim Hunt
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class ipal_question_bank_question_name_column extends ipal_question_bank_column_base {
    /** @var bool */
    protected $checkboxespresent = null;

    /**
     * Get the name of the column which has the question name.
     *
     */
    public function get_name() {
        return 'questionname';
    }

    /**
     * Get the title of the column that has the question name.
     *
     */
    protected function get_title() {
        return get_string('question');
    }

    /**
     * GEt the type of label to display for the question.
     *
     * @param stdObj $question Information about the question.
     */
    protected function label_for($question) {
        if (is_null($this->checkboxespresent)) {
            $this->checkboxespresent = $this->qbank->has_column('checkbox');
        }
        if ($this->checkboxespresent) {
            return 'checkq' . $question->id;
        } else {
            return '';
        }
    }

    /**
     * Display the actual name of the question.
     *
     * @param stdObj $question Information about the question.
     * @param string $rowclasses CSS class names that should be applied to this row of output.
     */
    protected function display_content($question, $rowclasses) {
        $labelfor = $this->label_for($question);
        if ($labelfor) {
            echo '<label for="' . $labelfor . '">';
        }
        echo format_string($question->name);
        if ($labelfor) {
            echo '</label>';
        }
    }

    /**
     * Get the fields that are required to obtain this information.
     *
     */
    public function get_required_fields() {
        return array('q.id', 'q.name');
    }

    /**
     * Is sortable by question name.
     *
     */
    public function is_sortable() {
        return 'q.name';
    }
}

/**
 * A column type for the name of the question creator.
 *
 * @copyright  2009 Tim Hunt
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class ipal_question_bank_creator_name_column extends ipal_question_bank_column_base {

    /**
     * Get the name of the column for the question creator.
     *
     */
    public function get_name() {
        return 'creatorname';
    }

    /**
     * Get the title for this column.
     *
     */
    protected function get_title() {
        return get_string('createdby', 'question');
    }

    /**
     * Display the first and last name of the question creator.
     *
     * @param stdObj $question Information about the question.
     * @param string $rowclasses CSS class names that should be applied to this row of output.
     */
    protected function display_content($question, $rowclasses) {
        if (!empty($question->creatorfirstname) && !empty($question->creatorlastname)) {
            $u = new stdClass();
            $u->firstname = $question->creatorfirstname;
            $u->lastname = $question->creatorlastname;
            echo fullname($u);
        }
    }

    /**
     * Get and extra joins (if there are any).
     *
     */
    public function get_extra_joins() {
        return array('uc' => 'LEFT JOIN {user} uc ON uc.id = q.createdby');
    }

    /**
     * Get the fields needed for the query.
     *
     */
    public function get_required_fields() {
        return array('uc.firstname AS creatorfirstname', 'uc.lastname AS creatorlastname');
    }

    /**
     * Gives the fields by which things can be sorted.
     *
     */
    public function is_sortable() {
        return array(
            'firstname' => array('field' => 'uc.firstname', 'title' => get_string('firstname')),
            'lastname' => array('field' => 'uc.lastname', 'title' => get_string('lastname')),
        );
    }
}

/**
 * A column type for the name of the question last modifier.
 *
 * @copyright  2009 Tim Hunt
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class ipal_question_bank_modifier_name_column extends ipal_question_bank_column_base {
    /**
     * Return the name of the modifier column.
     *
     */
    public function get_name() {
        return 'modifiername';
    }

    /**
     * Get the title string associated with last modifying a question.
     *
     */
    protected function get_title() {
        return get_string('lastmodifiedby', 'question');
    }

    /**
     * Display the first and last name of the person who last modified the question.
     *
     * @param stdObj $question Information about the question.
     * @param string $rowclasses CSS class names that should be applied to this row of output.
     */
    protected function display_content($question, $rowclasses) {
        if (!empty($question->modifierfirstname) && !empty($question->modifierlastname)) {
            $u = new stdClass();
            $u->firstname = $question->modifierfirstname;
            $u->lastname = $question->modifierlastname;
            echo fullname($u);
        }
    }

    /**
     * Get extra joins (fi there are any).
     *
     */
    public function get_extra_joins() {
        return array('um' => 'LEFT JOIN {user} um ON um.id = q.modifiedby');
    }

    /**
     * Get the fields required for the query.
     *
     */
    public function get_required_fields() {
        return array('um.firstname AS modifierfirstname', 'um.lastname AS modifierlastname');
    }

    /**
     * Give the fields that are sortable.
     *
     */
    public function is_sortable() {
        return array(
            'firstname' => array('field' => 'um.firstname', 'title' => get_string('firstname')),
            'lastname' => array('field' => 'um.lastname', 'title' => get_string('lastname')),
        );
    }
}

/**
 * Base class for question bank columns that just contain an action icon.
 *
 * @copyright  2009 Tim Hunt
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class ipal_question_bank_edit_action_column extends ipal_question_bank_action_column_base {
    /** @var string */
    protected $stredit;
    /** @var string */
    protected $strview;

    /**
     * Initialize the strings for the edit action column.
     *
     */
    public function init() {
        parent::init();
        $this->stredit = get_string('edit');
        $this->strview = get_string('view');
    }

    /**
     * Get the name for the editaction column.
     *
     */
    public function get_name() {
        return 'editaction';
    }

    /**
     * Display the information in the editaction column.
     *
     * @param stdObj $question Information about the question.
     * @param string $rowclasses CSS class names that should be applied to this row of output.
     */
    protected function display_content($question, $rowclasses) {
        if (question_has_capability_on($question, 'edit') ||
                question_has_capability_on($question, 'move')) {
            $this->print_icon('t/edit', $this->stredit, $this->qbank->edit_question_url($question->id));
        } else {
            $this->print_icon('i/info', $this->strview, $this->qbank->edit_question_url($question->id));
        }
    }
}

/**
 * Question bank columns for the preview action icon.
 *
 * @copyright  2009 Tim Hunt
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class ipal_question_bank_preview_action_column extends ipal_question_bank_action_column_base {
    /** @var string */
    protected $strpreview;

    /**
     * Get the string for the preview column.
     *
     */
    public function init() {
        parent::init();
        $this->strpreview = get_string('preview');
    }

    /**
     * Get the name of the preview column.
     *
     */
    public function get_name() {
        return 'previewaction';
    }

    /**
     * Display the correct information on the question in the preview column.
     *
     * @param stdObj $question Information about the question.
     * @param string $rowclasses CSS class names that should be applied to this row of output.
     */
    protected function display_content($question, $rowclasses) {
        global $OUTPUT;
        if (question_has_capability_on($question, 'use')) {
            // Build the icon.
            $image = $OUTPUT->pix_icon('t/preview', $this->strpreview);

            $link = $this->qbank->preview_question_url($question);
            $action = new popup_action('click', $link, 'questionpreview',
                    question_preview_popup_params());

            echo $OUTPUT->action_link($link, $image, $action, array('title' => $this->strpreview));
        }
    }

    /**
     * Get require fields for the query.
     *
     */
    public function get_required_fields() {
        return array('q.id');
    }
}

/**
 * Question bank columns for the move action icon.
 *
 * @copyright  2009 Tim Hunt
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class ipal_question_bank_move_action_column extends ipal_question_bank_action_column_base {
    /** @var string */
    protected $strmove;

    /**
     * Initialize the string for the move column
     *
     */
    public function init() {
        parent::init();
        $this->strmove = get_string('move');
    }

    /**
     * Get the name for the move column
     *
     */
    public function get_name() {
        return 'moveaction';
    }

    /**
     * Display the content for the move column.
     *
     * @param stdObj $question Information about the question in the row
     * @param string $rowclasses CSS class names that should be applied to this row of output.
     */
    protected function display_content($question, $rowclasses) {
        if (question_has_capability_on($question, 'move')) {
            $this->print_icon('t/move', $this->strmove, $this->qbank->move_question_url($question->id));
        }
    }
}

/**
 * action to delete (or hide) a question, or restore a previously hidden question.
 *
 * @copyright  2009 Tim Hunt
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class ipal_question_bank_delete_action_column extends ipal_question_bank_action_column_base {
    /** @var string */
    protected $strdelete;
    /** @var string */
    protected $strrestore;

    /**
     * Initializing the strings for 'delete' and 'restore'.
     *
     */
    public function init() {
        parent::init();
        $this->strdelete = get_string('delete');
        $this->strrestore = get_string('restore');
    }

    /**
     * Return the string 'deleteaction'.
     *
     */
    public function get_name() {
        return 'deleteaction';
    }

    /**
     * Display the content of the question
     * 
     * @param object $question the row from the $question table, augmented with extra information.
     * @param string $rowclasses CSS class names that should be applied to this row of output.
     */
    protected function display_content($question, $rowclasses) {
        if (question_has_capability_on($question, 'edit')) {
            if ($question->hidden) {
                $url = new moodle_url($this->qbank->base_url(), array('unhide' => $question->id, 'sesskey' => sesskey()));
                $this->print_icon('t/restore', $this->strrestore, $url);
            } else {
                $url = new moodle_url($this->qbank->base_url(), array('deleteselected' => $question->id,
                    'q' . $question->id => 1, 'sesskey' => sesskey()));
                $this->print_icon('t/delete', $this->strdelete, $url);
            }
        }
    }
    /**
     * Get required fields.
     *
     */
    public function get_required_fields() {
        return array('q.id', 'q.hidden');
    }
}

/**
 * A base class for actions that are an icon that lets you manipulate the question in some way.
 *
 * @copyright  2009 Tim Hunt
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
abstract class ipal_question_bank_action_column_base extends ipal_question_bank_column_base {

    /**
     * Get the title.
     *
     */
    protected function get_title() {
        return '&#160;';
    }

    /**
     * Get extra classes.
     *
     */
    public function get_extra_classes() {
        return array('iconcol');
    }

    /**
     * Print the HTML for an icon
     *
     * @param string $icon The name of the icon image that is a link.
     * @param string $title The alr text for the icon.
     * @param string $url The URL to which a click on the icon will send the user.
     */
    protected function print_icon($icon, $title, $url) {
        global $OUTPUT;
        echo '<a title="' . $title . '" href="' . $url . '">
                <img src="' . $OUTPUT->pix_url($icon) . '" class="iconsmall" alt="' . $title . '" /></a>';
    }

    /**
     * Return the required fields array
     *
     */
    public function get_required_fields() {
        return array('q.id');
    }
}

/**
 * A column type for the name of the question name.
 *
 * @copyright  2009 Tim Hunt
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class ipal_question_bank_question_text_row extends ipal_question_bank_row_base {
    /** @var stdObj */
    protected $formatoptions;

    /**
     * Initializing components of the formatoptions class.
     *
     */
    protected function init() {
        $this->formatoptions = new stdClass();
        $this->formatoptions->noclean = true;
        $this->formatoptions->para = false;
    }

    /**
     * Provide the name for this row.
     *
     */
    public function get_name() {
        return 'questiontext';
    }

    /**
     * Get the title strings.
     *
     */
    protected function get_title() {
        return get_string('questiontext', 'question');
    }

    /**
     * Display the content for this row.
     *
     * @param stdObj $question Information about the question.
     * @param string $rowclasses CSS class names that should be applied to this row of output.
     */
    protected function display_content($question, $rowclasses) {
        $text = format_text($question->questiontext, $question->questiontextformat,
                $this->formatoptions, $this->qbank->get_courseid());
        if ($text == '') {
            $text = '&#160;';
        }
        echo $text;
    }

    /**
     * Function to get the required fields for the question bank display.
     *
     */
    public function get_required_fields() {
        return array('q.questiontext', 'q.questiontextformat');
    }
}

/**
 * Base class for 'columns' that are actually displayed as a row following the main question row.
 *
 * @copyright  2009 Tim Hunt
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
abstract class ipal_question_bank_row_base extends ipal_question_bank_column_base {

    /**
     * REturning true for an extra row.
     *
     */
    public function is_extra_row() {
        return true;
    }

    /**
     * Display the HTML for the table beginning of the row with the question data in it.
     *
     * @param stdObj $question Information about the question in this row.
     * @param string $rowclasses CSS class names that should be applied to this row of output.
     */
    protected function display_start($question, $rowclasses) {
        if ($rowclasses) {
            echo '<tr class="' . $rowclasses . '">' . "\n";
        } else {
            echo "<tr>\n";
        }
        echo '<td colspan="' . $this->qbank->get_column_count() . '" class="' . $this->get_name() . '">';
    }

    /**
     * Display the HTML for the table end of the row with the question data in it.
     *
     * @param stdObj $question Information about the question in this row.
     * @param string $rowclasses CSS class names that should be applied to this row of output.
     */
    protected function display_end($question, $rowclasses) {
        echo "</td></tr>\n";
    }
}

/**
 * A column type for the add this question to the quiz. from /mod/quiz/editlib.php
 *
 * @copyright  2009 Tim Hunt
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class question_bank_add_to_ipal_action_column extends ipal_question_bank_action_column_base {
    /** @var string */
    protected $stradd;

    /**
     * Initializing the action column string.
     *
     */
    public function init() {
        parent::init();
        $this->stradd = get_string('addtoquiz', 'quiz');
    }

    /**
     * Provide the text for the action column.
     *
     */
    public function get_name() {
        return 'addtoipalaction';
    }

    /**
     * Function to display the icons in the action column.
     *
     * @param stdObj $question Information about the question.
     * @param string $rowclasses CSS class names that should be applied to this row of output.
     */
    protected function display_content($question, $rowclasses) {
        // For RTL languages: switch right and left arrows.
        if (right_to_left()) {
            $movearrow = 't/removeright';
        } else {
            $movearrow = 't/moveleft';
        }
          $this->print_icon($movearrow, $this->stradd, $this->qbank->add_to_ipal_url($question->id));
    }

    /**
     * Function to get the required fields for the action column.
     *
     */
    public function get_required_fields() {
        return array('q.id');
    }
}

/**
 * A column type for the name followed by the start of the question text.
 *
 * @copyright  2009 Tim Hunt
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class ipal_question_bank_question_name_text_column extends ipal_question_bank_question_name_column {

    /**
     * Provide the text.
     *
     */
    public function get_name() {
        return 'questionnametext';
    }

    /**
     * Display the title and text of a question
     *
     * @param stdObj $question Information about the question
     * @param string $rowclasses CSS class names that should be applied to this row of output.
     */
    protected function display_content($question, $rowclasses) {
        echo '<div>';
        $labelfor = $this->label_for($question);
        if ($labelfor) {
            echo '<label for="' . $labelfor . '">';
        }
        echo ipal_question_tostring($question, false, true, true);
        if ($labelfor) {
            echo '</label>';
        }
        echo '</div>';
    }

    /**
     * function to get the required fields.
     *
     */
    public function get_required_fields() {
        $fields = parent::get_required_fields();
        $fields[] = 'q.questiontext';
        $fields[] = 'q.questiontextformat';
        return $fields;
    }
}

/**
 * Print a button for creating a new question. This will open question/addquestion.php,
 * which in turn goes to question/question.php before getting back to $params['returnurl']
 * (by default the question bank screen).
 *
 * @param int $categoryid The id of the category that the new question should be added to.
 * @param array $params Other paramters to add to the URL. You need either $params['cmid'] or
 *      $params['courseid'], and you should probably set $params['returnurl']
 * @param string $caption the text to display on the button.
 * @param string $tooltip a tooltip to add to the button (optional).
 * @param bool $disabled if true, the button will be disabled.
 */
function ipal_create_new_question_button($categoryid, $params, $caption, $tooltip = '', $disabled = false) {
    global $CFG, $PAGE, $OUTPUT;
    static $choiceformprinted = false;
    $params['category'] = $categoryid;
    $url = new moodle_url('/question/addquestion.php', $params);
    echo $OUTPUT->single_button($url, $caption, 'get', array('disabled' => $disabled, 'title' => $tooltip));

    if (!$choiceformprinted) {
        echo '<div id="qtypechoicecontainer">';
        ipal_print_choose_qtype_to_add_form(array());
        echo "</div>\n";
        $choiceformprinted = true;
    }
}

/**
 * Print a form to let the user choose which question type to add.
 * When the form is submitted, it goes to the question.php script.
 * @param stdObj $hiddenparams hidden parameters to add to the form, in addition to
 * the qtype radio buttons.
 */
function ipal_print_choose_qtype_to_add_form($hiddenparams) {
    global $CFG, $PAGE, $OUTPUT;
    $PAGE->requires->js('/question/qbank.js');
    echo '<div id="chooseqtypehead" class="hd">' . "\n";
    echo $OUTPUT->heading(get_string('chooseqtypetoadd', 'question'), 3);
    echo "</div>\n";
    echo '<div id="chooseqtype">' . "\n";
    echo '<form action="' . $CFG->wwwroot . '/question/question.php" method="get"><div id="qtypeformdiv">' . "\n";
    foreach ($hiddenparams as $name => $value) {
        echo '<input type="hidden" name="' . s($name) . '" value="' . s($value) . '" />' . "\n";
    }
    echo "</div>\n";
    echo '<div class="qtypes">' . "\n";
    echo '<div class="instruction">' . get_string('selectaqtypefordescription', 'question') . "</div>\n";
    echo '<div class="realqtypes">' . "\n";
    $fakeqtypes = array();
    foreach (ipal_question_bank::get_creatable_qtypes() as $qtype) {
        if ($qtype->is_real_question_type()) {
            ipal_print_qtype_to_add_option($qtype);
        } else {
            $fakeqtypes[] = $qtype;
        }
    }
    echo "</div>\n";
    echo '<div class="fakeqtypes">' . "\n";
    foreach ($fakeqtypes as $qtype) {
        ipal_print_qtype_to_add_option($qtype);
    }
    echo "</div>\n";
    echo "</div>\n";
    echo '<div class="submitbuttons">' . "\n";
    echo '<input type="submit" value="' . get_string('next') . '" id="chooseqtype_submit" />' . "\n";
    echo '<input type="submit" id="chooseqtypecancel" name="addcancel" value="' . get_string('cancel') . '" />' . "\n";
    echo "</div></form>\n";
    echo "</div>\n";
    $PAGE->requires->js_init_call('qtype_chooser.init', array('chooseqtype'));
}


/**
 * Private function used by the preceding one.
 * @param stdObj $qtype the question type.
 */
function ipal_print_qtype_to_add_option($qtype) {
    echo '<div class="qtypeoption">' . "\n";
    echo '<label for="qtype_' . $qtype->name() . '">';
    echo '<input type="radio" name="qtype" id="qtype_' . $qtype->name() . '" value="' . $qtype->name() . '" />';
    echo '<span class="qtypename">';
    $fakequestion = new stdClass();
    $fakequestion->qtype = $qtype->name();
    print_question_icon($fakequestion);
    echo $qtype->menu_name() . '</span><span class="qtypesummary">' .
            get_string($qtype->name() . 'summary', 'qtype_' . $qtype->name());
    echo "</span></label>\n";
    echo "</div>\n";
}


/**
 * Creates a textual representation of a question for display.
 *
 * @param object $question A question object from the database questions table
 * @param bool $showicon If true, show the question's icon with the question. False by default.
 * @param bool $showquestiontext If true (default), show question text after question name.
 *       If false, show only question name.
 * @param bool $return If true (default), return the output. If false, print it.
 */
function ipal_question_tostring($question, $showicon = false,
        $showquestiontext = true, $return = true) {
    global $COURSE;
    $result = '';
    $result .= '<span class="questionname">';
    if ($showicon) {
        $result .= print_question_icon($question, true);
        echo ' ';
    }
    $result .= shorten_text(format_string($question->name), 200) . '</span>';
    if ($showquestiontext) {
        $formatoptions = new stdClass();
        $formatoptions->noclean = true;
        $formatoptions->para = false;
        $questiontext = strip_tags(format_text($question->questiontext,
                $question->questiontextformat,
                $formatoptions, $COURSE->id));
        $questiontext = shorten_text($questiontext, 200);
        $result .= '<span class="questiontext">';
        if (!empty($questiontext)) {
            $result .= $questiontext;
        } else {
            $result .= '<span class="error">';
            $result .= get_string('questiontextisempty', 'quiz');
            $result .= '</span>';
        }
        $result .= '</span>';
    }
    if ($return) {
        return $result;
    } else {
        echo $result;
    }
}

/**
 * Return the preview question icon.
 *
 * @param object $quiz the ipal settings
 * @param object $question the question
 * @param bool $label if true, show the preview question label after the icon
 * @return the HTML for a preview question icon.
 */
function ipal_question_preview_button($quiz, $question, $label = false) {
    global $CFG, $OUTPUT;
    if (!question_has_capability_on($question, 'use', $question->category)) {
        return '';
    }

    $url = ipal_question_preview_url($quiz, $question);

    // Do we want a label?
    $strpreviewlabel = '';
    if ($label) {
        $strpreviewlabel = get_string('preview', 'quiz');
    }

    // Build the icon.
    $strpreviewquestion = get_string('previewquestion', 'quiz');
    $image = $OUTPUT->pix_icon('t/preview', $strpreviewquestion);

    $action = new popup_action('click', $url, 'questionpreview',
            question_preview_popup_params());

    return $OUTPUT->action_link($url, $image, $action, array('title' => $strpreviewquestion));
}

/**
 * Return the URL to preview the question.
 *
 * @param object $quiz the ipal settings
 * @param object $question the question
 * @return moodle_url to preview this question with the options from this ipal.
 */
function ipal_question_preview_url($quiz, $question) {
    // Get the appropriate display options.
    $displayoptions = mod_ipal_display_options::make_from_quiz($quiz,
            mod_ipal_display_options::DURING);

    $maxmark = null;
    if (isset($question->maxmark)) {
        $maxmark = $question->maxmark;
    }

    // Work out the correcte preview URL.
    return question_preview_url($question->id, null,
            $maxmark, $displayoptions);
}

/**
 * An extension of question_display_options that includes the extra options used by the ipal.
 *
 * @copyright  2010 The Open University
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class mod_ipal_display_options extends ipal_question_display_options {
    /**#@+
     * @var integer bits used to indicate various times in relation to a
     * quiz attempt.
     */
    /** @var int bits */
    const DURING = 0x10000;
    /** @var int bits */
    const IMMEDIATELY_AFTER = 0x01000;
    /** @var int bits */
    const LATER_WHILE_OPEN = 0x00100;
    /** @var int bits */
    const AFTER_CLOSE = 0x00010;
    /**#@-*/

    /**
     * @var boolean if this is false, then the student is not allowed to review
     * anything about the attempt.
     */
    public $attempt = true;

    /**
     * @var boolean if this is false, then the student is not allowed to review
     * anything about the attempt.
     */
    public $overallfeedback = self::VISIBLE;

    /**
     * Set up the various options from the quiz settings, and a time constant.
     * @param object $quiz the quiz settings.
     * @param int $when One of the {@link DURING}, {@link IMMEDIATELY_AFTER},
     * {@link LATER_WHILE_OPEN} or {@link AFTER_CLOSE} constants.
     * @return mod_quiz_display_options set up appropriately.
     */
    public static function make_from_quiz($quiz, $when) {
        $options = new self();

        $options->markdp = 2;
        return $options;
    }

    /**
     * A function that is not used in IPAL
     *
     * @param bit $bitmask
     * @param bit $bit
     * @param bool $whenset
     * @param bool $whennotset
     */
    protected static function extract($bitmask, $bit,
            $whenset = self::VISIBLE, $whennotset = self::HIDDEN) {
        if ($bitmask & $bit) {
            return $whenset;
        } else {
            return $whennotset;
        }
    }
}

/**
 * Function to print the list of questions.
 *
 * @param stdObj $quiz Information about the IPAL object.
 * @param string $pageurl The URL of this page.
 * @param bool $allowdelete Can this user delete questions.
 * @param stdObj $reordertool Information about reordering.
 * @param stdObj $quizqbanktool Information about the quiz bank.
 * @param bool $hasattempts Has the quiz been attempted (not needed in IPAL).
 * @param stdObj $defaultcategoryobj Information about the default category.
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
                $ipalnames[$qcount] = trim($questions[$value]->name);
                $ipalquestiontexts[$qcount] = trim($questions[$value]->questiontext);
                $ipalids[$qcount] = $value;
                $ipalqtypes[$qcount] = $questions[$value]->qtype;
                $qcount++;
            } else {
                $ipalids[$qcount] = 0;
                $qcount++;
            }
        }
    } else {
        $questions = array();
    }

    if (isset($ipalids)) {
        $order = $ipalids;
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

            if ($qnum && strlen($ipalnames[$count]) == 0 && strlen($ipaltext[$count]) == 0) {
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
                            'id' => $ipalids[$count]);
                    $questionurl = new moodle_url('/question/question.php',
                            $questionparams);
                    $questioncount++;
                    // This is an actual question.

                    /* Display question start */
                    ?>
<div class="question">
    <div class="questioncontainer <?php echo $ipalqtypes[$count]; ?>">
        <div class="qnum">
                    <?php
                    $reordercheckbox = '';
                    $reordercheckboxlabel = '';
                    $reordercheckboxlabelclose = '';

                    if ((strlen($ipalnames[$count]) + strlen($ipalquestiontexts[$count])) == 0) {
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
                                    array('up' => $ipalids[$count], 'sesskey' => sesskey())),
                                    new pix_icon('t/up', $strmoveup),
                                    new component_action('click',
                                            'M.core_scroll_manager.save_scroll_action'),
                                    array('title' => $strmoveup));
                        }

                    }
                    if ($count < $lastindex - 1) {
                        if (!$hasattempts) {
                            echo $OUTPUT->action_icon($pageurl->out(true,
                                    array('down' => $ipalids[$count], 'sesskey' => sesskey())),
                                    new pix_icon('t/down', $strmovedown),
                                    new component_action('click',
                                            'M.core_scroll_manager.save_scroll_action'),
                                    array('title' => $strmovedown));
                        }
                    }
                    if ($allowdelete) {

                        if (!$hasattempts) {
                            echo $OUTPUT->action_icon($pageurl->out(true,
                                    array('remove' => $ipalids[$count], 'sesskey' => sesskey())),
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
                            ipal_print_singlequestion($question, $returnurl, $quiz);
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
                        // Do not include the last page break for reordering to avoid creating a new extra page in the end.

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

/**
 * Function to check that the question is a qustion type supported in ipal
 *
 * Currently ipal only supports multichoice, truefalse, and essay question types
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
 * Print a given single question in quiz for the edit tab of edit.php.
 *
 * Meant to be used from ipal_print_question_list()
 *
 * @param object $question A question object from the database questions table
 * @param object $returnurl The url to get back to this page, for example after editing.
 * @param object $quiz The quiz in the context of which the question is being displayed
 */
function ipal_print_singlequestion($question, $returnurl, $quiz) {
    echo '<div class="singlequestion">';
    echo ipal_question_edit_button($quiz->cmid, $question, $returnurl,
            ipal_question_tostring($question) . ' ');
    echo '<span class="questiontype">';
    print_question_icon($question);
    echo ' ' . question_bank::get_qtype_name($question->qtype) . '</span>';
    echo '<span class="questionpreview">' .
            ipal_question_preview_button($quiz, $question, true) . '</span>';
    echo "</div>\n";
}

/**
 * Print an edit or view button for a question.
 *
 * @param int $cmid the course_module.id for this ipal.
 * @param object $question the question.
 * @param string $returnurl url to return to after action is done.
 * @param string $contentaftericon some HTML content to be added inside the link, before the icon.
 * @return the HTML for an edit icon, view icon, or nothing for a question
 *      (depending on permissions).
 */
function ipal_question_edit_button($cmid, $question, $returnurl, $contentaftericon = '') {
    global $CFG, $OUTPUT;

    // Minor efficiency saving. Only get strings once, even if there are a lot of icons on one page.
    static $stredit = null;
    static $strview = null;
    if ($stredit === null) {
        $stredit = get_string('edit');
        $strview = get_string('view');
    }

    // What sort of icon should we show?
    $action = '';
    if (!empty($question->id) &&
            (question_has_capability_on($question, 'edit', $question->category) ||
                    question_has_capability_on($question, 'move', $question->category))) {
        $action = $stredit;
        $icon = '/t/edit';
    } else if (!empty($question->id) &&
            question_has_capability_on($question, 'view', $question->category)) {
        $action = $strview;
        $icon = '/i/info';
    }

    // Build the icon.
    if ($action) {
        if ($returnurl instanceof moodle_url) {
            $returnurl = str_replace($CFG->wwwroot, '', $returnurl->out(false));
        }
        $questionparams = array('returnurl' => $returnurl, 'cmid' => $cmid, 'id' => $question->id);
        $questionurl = new moodle_url("$CFG->wwwroot/question/question.php", $questionparams);
        return '<a title="' . $action . '" href="' . $questionurl->out() . '"><img src="' .
                $OUTPUT->pix_url($icon) . '" alt="' . $action . '" />' . $contentaftericon .
                '</a>';
    } else {
        return $contentaftericon;
    }
}


/**
 * Print all the controls for adding questions directly into the
 * specific page in the edit tab of edit.php
 *
 * @param unknown_type $quiz
 * @param unknown_type $pageurl
 * @param unknown_type $page
 * @param unknown_type $hasattempts
 * @param stdObj $defaultcategoryobj
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
    ipal_create_new_question_button($defaultcategoryid, $newquestionparams,
            get_string('addaquestion', 'quiz'),
            get_string('createquestionandadd', 'quiz'), $hasattempts);

    if ($hasattempts) {
        $disabled = 'disabled="disabled"';
    } else {
        $disabled = '';
    }// I removed lines 424-442  because they added a random button.
    echo "\n</div>";
}


/**
 * Private function used by the following two.
 *
 * @param string $layout The comma separated string giving the current order of the questions.
 * @param int $questionid The ID of the question to be moved.
 * @param int $shift How far to move the question.
 */
function _ipal_move_question($layout, $questionid, $shift) {
    if (!$questionid || !($shift == 1 || $shift == -1)) {
        return $layout;
    }

    $questionids = explode(',', $layout);
    $key = array_search($questionid, $questionids);
    if ($key === false) {
        return $layout;
    }

    $otherkey = $key + $shift;
    if ($otherkey < 0 || $otherkey >= count($questionids) - 1) {
        return $layout;
    }

    $temp = $questionids[$otherkey];
    $questionids[$otherkey] = $questionids[$key];
    $questionids[$key] = $temp;

    return implode(',', $questionids);
}

/**
 * Move a particular question one space earlier in the $quiz->questions list.
 * If that is not possible, do nothing.
 * @param string $layout the existinng layout, $quiz->questions.
 * @param int $questionid the id of a question.
 * @return the updated layout
 */
function ipal_move_question_up($layout, $questionid) {
    return _ipal_move_question($layout, $questionid, -1);
}

/**
 * Move a particular question one space later in the $quiz->questions list.
 * If that is not possible, do nothing.
 * @param string $layout the existinng layout, $quiz->questions.
 * @param int $questionid the id of a question.
 * @return the updated layout
 */
function ipal_move_question_down($layout, $questionid) {
    return _ipal_move_question($layout, $questionid, + 1);
}
