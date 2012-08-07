<?php

define('DEFAULT_QUESTIONS_PER_PAGE', 20);

/**
 * Common setup for all pages for editing questions.
 * @param string $baseurl the name of the script calling this funciton. For examle 'qusetion/edit.php'.
 * @param string $edittab code for this edit tab
 * @param bool $requirecmid require cmid? default false
 * @param bool $requirecourseid require courseid, if cmid is not given? default true
 * @return array $thispageurl, $contexts, $cmid, $cm, $module, $pagevars
 */
function ipal_question_edit_setup($edittab, $baseurl, $requirecmid = false, $requirecourseid = true) {
    global $DB, $PAGE;

    $thispageurl = new moodle_url($baseurl);
    $thispageurl->remove_all_params(); // We are going to explicity add back everything important - this avoids unwanted params from being retained.

    if ($requirecmid){
        $cmid =required_param('cmid', PARAM_INT);
    } else {
        $cmid = optional_param('cmid', 0, PARAM_INT);
    }
    if ($cmid){
        list($module, $cm) = ipal_get_module_from_cmid($cmid);
        $courseid = $cm->course;
        $thispageurl->params(compact('cmid'));
       //require_login($courseid, false, $cm);
        $thiscontext = get_context_instance(CONTEXT_MODULE, $cmid);
    } else {
        $module = null;
        $cm = null;
        if ($requirecourseid){
            $courseid  = required_param('courseid', PARAM_INT);
        } else {
            $courseid  = optional_param('courseid', 0, PARAM_INT);
        }
        if ($courseid){
            $thispageurl->params(compact('courseid'));
            require_login($courseid, false);
            $thiscontext = get_context_instance(CONTEXT_COURSE, $courseid);
        } else {
            $thiscontext = null;
        }
    }

    if ($thiscontext){
        $contexts = new ipal_question_edit_contexts($thiscontext);
        $contexts->require_one_edit_tab_cap($edittab);

    } else {
        $contexts = null;
    }

    $PAGE->set_pagelayout('admin');

    $pagevars['qpage'] = optional_param('qpage', -1, PARAM_INT);

    //pass 'cat' from page to page and when 'category' comes from a drop down menu
    //then we also reset the qpage so we go to page 1 of
    //a new cat.
    $pagevars['cat'] = optional_param('cat', 0, PARAM_SEQUENCE); // if empty will be set up later
    if ($category = optional_param('category', 0, PARAM_SEQUENCE)) {
        if ($pagevars['cat'] != $category) { // is this a move to a new category?
            $pagevars['cat'] = $category;
            $pagevars['qpage'] = 0;
        }
    }
    if ($pagevars['cat']){
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

    for ($i = 1; $i <= ipal_question_bank_view::MAX_SORTS; $i++) {
        $param = 'qbs' . $i;
        if (!$sort = optional_param($param, '', PARAM_ALPHAEXT)) {
            break;
        }
        $thispageurl->param($param, $sort);
    }

    $defaultcategory = ipal_question_make_default_categories($contexts->all());

    $contextlistarr = array();
    foreach ($contexts->having_one_edit_tab_cap($edittab) as $context){
        $contextlistarr[] = "'$context->id'";
    }
    $contextlist = join($contextlistarr, ' ,');
    if (!empty($pagevars['cat'])){
        $catparts = explode(',', $pagevars['cat']);
        if (!$catparts[0] || (false !== array_search($catparts[1], $contextlistarr)) ||
                !$DB->count_records_select("question_categories", "id = ? AND contextid = ?", array($catparts[0], $catparts[1]))) {
            print_error('invalidcategory', 'question');
        }
    } else {
        $category = $defaultcategory;
        $pagevars['cat'] = "$category->id,$category->contextid";
    }

    if(($recurse = optional_param('recurse', -1, PARAM_BOOL)) != -1) {
        $pagevars['recurse'] = $recurse;
        $thispageurl->param('recurse', $recurse);
    } else {
        $pagevars['recurse'] = 1;
    }

    if(($showhidden = optional_param('showhidden', -1, PARAM_BOOL)) != -1) {
        $pagevars['showhidden'] = $showhidden;
        $thispageurl->param('showhidden', $showhidden);
    } else {
        $pagevars['showhidden'] = 0;
    }

    if(($showquestiontext = optional_param('qbshowtext', -1, PARAM_BOOL)) != -1) {
        $pagevars['qbshowtext'] = $showquestiontext;
        $thispageurl->param('qbshowtext', $showquestiontext);
    } else {
        $pagevars['qbshowtext'] = 0;
    }

    //category list page
    $pagevars['cpage'] = optional_param('cpage', 1, PARAM_INT);
    if ($pagevars['cpage'] != 1){
        $thispageurl->param('cpage', $pagevars['cpage']);
    }

    return array($thispageurl, $contexts, $cmid, $cm, $module, $pagevars);
}

function ipal_get_module_from_cmid($cmid) {
    global $CFG, $DB;
    if (!$cmrec = $DB->get_record_sql("SELECT cm.*, md.name as modname
                               FROM {course_modules} cm,
                                    {modules} md
                               WHERE cm.id = ? AND
                                     md.id = cm.module", array($cmid))){
        print_error('invalidcoursemodule');
    } elseif (!$modrec =$DB->get_record($cmrec->modname, array('id' => $cmrec->instance))) {
        print_error('invalidcoursemodule');
    }
    $modrec->instance = $modrec->id;
    $modrec->cmid = $cmrec->id;
    $cmrec->name = $modrec->name;

    return array($modrec, $cmrec);
}

//Modified from /lib/questionlib.php my W. F. Junkin 2012.02.14 for IPAL to avoid version updaes from impacting IPAL
class ipal_question_edit_contexts {

    public static $caps = array(
        'editq' => array('moodle/question:add',
            'moodle/question:editmine',
            'moodle/question:editall',
            'moodle/question:viewmine',
            'moodle/question:viewall',
            'moodle/question:usemine',
            'moodle/question:useall',
            'moodle/question:movemine',
            'moodle/question:moveall'),
        'questions'=>array('moodle/question:add',
            'moodle/question:editmine',
            'moodle/question:editall',
            'moodle/question:viewmine',
            'moodle/question:viewall',
            'moodle/question:movemine',
            'moodle/question:moveall'),
        'categories'=>array('moodle/question:managecategory'),
        'import'=>array('moodle/question:add'),
        'export'=>array('moodle/question:viewall', 'moodle/question:viewmine'));

    protected $allcontexts;

    /**
     * @param current context
     */
    public function __construct($thiscontext) {
        $pcontextids = get_parent_contexts($thiscontext);
        $contexts = array($thiscontext);
        foreach ($pcontextids as $pcontextid) {
            $contexts[] = get_context_instance_by_id($pcontextid);
        }
        $this->allcontexts = $contexts;
    }
    /**
     * @return array all parent contexts
     */
    public function all() {
        return $this->allcontexts;
    }
    /**
     * @return object lowest context which must be either the module or course context
     */
    public function lowest() {
        return $this->allcontexts[0];
    }
    /**
     * @param string $cap capability
     * @return array parent contexts having capability, zero based index
     */
    public function having_cap($cap) {
        $contextswithcap = array();
        foreach ($this->allcontexts as $context) {
            if (has_capability($cap, $context)) {
                $contextswithcap[] = $context;
            }
        }
        return $contextswithcap;
    }
    /**
     * @param array $caps capabilities
     * @return array parent contexts having at least one of $caps, zero based index
     */
    public function having_one_cap($caps) {
        $contextswithacap = array();
        foreach ($this->allcontexts as $context) {
            foreach ($caps as $cap) {
                if (has_capability($cap, $context)) {
                    $contextswithacap[] = $context;
                    break; //done with caps loop
                }
            }
        }
        return $contextswithacap;
    }
    /**
     * @param string $tabname edit tab name
     * @return array parent contexts having at least one of $caps, zero based index
     */
    public function having_one_edit_tab_cap($tabname) {
        return $this->having_one_cap(self::$caps[$tabname]);
    }
    /**
     * Has at least one parent context got the cap $cap?
     *
     * @param string $cap capability
     * @return boolean
     */
    public function have_cap($cap) {
        return (count($this->having_cap($cap)));
    }

    /**
     * Has at least one parent context got one of the caps $caps?
     *
     * @param array $caps capability
     * @return boolean
     */
    public function have_one_cap($caps) {
        foreach ($caps as $cap) {
            if ($this->have_cap($cap)) {
                return true;
            }
        }
        return false;
    }

    /**
     * Has at least one parent context got one of the caps for actions on $tabname
     *
     * @param string $tabname edit tab name
     * @return boolean
     */
    public function have_one_edit_tab_cap($tabname) {
        return $this->have_one_cap(self::$caps[$tabname]);
    }

    /**
     * Throw error if at least one parent context hasn't got the cap $cap
     *
     * @param string $cap capability
     */
    public function require_cap($cap) {
        if (!$this->have_cap($cap)) {
            print_error('nopermissions', '', '', $cap);
        }
    }

    /**
     * Throw error if at least one parent context hasn't got one of the caps $caps
     *
     * @param array $cap capabilities
     */
    public function require_one_cap($caps) {
        if (!$this->have_one_cap($caps)) {
            $capsstring = join($caps, ', ');
            print_error('nopermissions', '', '', $capsstring);
        }
    }

    /**
     * Throw error if at least one parent context hasn't got one of the caps $caps
     *
     * @param string $tabname edit tab name
     */
    public function require_one_edit_tab_cap($tabname) {
        if (!$this->have_one_edit_tab_cap($tabname)) {
            print_error('nopermissions', '', '', 'access question edit tab '.$tabname);
        }
    }
}

/**
 * Subclass to customise the view of the question bank for the quiz editing screen.
 *
 * @copyright  2009 Tim Hunt
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class ipal_question_bank_view extends ipal_original_question_bank_view {
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

    protected function known_field_types() {//I may need to edit this to remove unacceptable question types
        $types = parent::known_field_types();
        $types[] = new question_bank_add_to_ipal_action_column($this);
        $types[] = new ipal_question_bank_question_name_text_column($this);
        return $types;
    }

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

    public function preview_question_url($question) {//Debug this controls the URL of the preview icon.
        return ipal_question_preview_url($this->quiz, $question);
    }

    public function add_to_quiz_url($questionid) {
        global $CFG;
        $params = $this->baseurl->params();
        $params['addquestion'] = $questionid;
        $params['sesskey'] = sesskey();
        return new moodle_url('/mod/quiz/edit.php', $params);
    }

//Added function to send the program back to the correct place when in ipal
    public function add_to_ipal_url($questionid) {
        global $CFG;
        $params = $this->baseurl->params();
        $params['addquestion'] = $questionid;
        $params['sesskey'] = sesskey();
        return new moodle_url('/mod/ipal/ipal_quiz_edit.php', $params);
    }
	
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
				
        // continues with list of questions
        $this->ipal_display_question_list($this->contexts->having_one_edit_tab_cap($tabname),
                $this->baseurl, $cat, $this->cm, $recurse, $page,
                $perpage, $showhidden, $showquestiontext,
                $this->contexts->having_cap('moodle/question:add'));
		        $this->display_options($recurse, $showhidden, $showquestiontext);
        echo $OUTPUT->box_end();
    }

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

    protected function display_options($recurse, $showhidden, $showquestiontext) {
        echo '<form method="get" action="ipal_quiz_edit.php" id="displayoptions">';//Modified for ipal
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

    protected function ipal_print_table_row($question, $rowcount) {
        $rowclasses = implode(' ', $this->get_row_classes($question, $rowcount));
        if ($rowclasses) {
            echo '<tr class="' . $rowclasses . '">' . "\n";
        } else {
            echo "<tr>\n";
        }
        foreach ($this->visiblecolumns as $colkey => $column) {
            //global $CFG;
			if($colkey == 'addtoipalaction'){
			$column = new question_bank_add_to_ipal_action_column($this);
			$column->display($question,$rowclasses);
			}
			else{
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
    * @param object $course   The course object
    * @param int $categoryid  The id of the question category to be displayed
    * @param int $cm      The course module record if we are in the context of a particular module, 0 otherwise
    * @param int $recurse     This is 1 if subcategories should be included, 0 otherwise
    * @param int $page        The number of the page to be displayed
    * @param int $perpage     Number of questions to show per page
    * @param bool $showhidden   True if also hidden questions should be displayed
    * @param bool $showquestiontext whether the text of each question should be shown in the list
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
        $catcontext = get_context_instance_by_id($contextid);

        $canadd = has_capability('moodle/question:add', $catcontext);
        $caneditall =has_capability('moodle/question:editall', $catcontext);
        $canuseall =has_capability('moodle/question:useall', $catcontext);
        $canmoveall =has_capability('moodle/question:moveall', $catcontext);

        $this->create_new_question_form($category, $canadd);

        $this->build_query_sql($category, $recurse, $showhidden);
        $totalnumber = $this->get_question_count();
        if ($totalnumber == 0) {
            return;
        }

        $questions = $this->load_page_questions($page, $perpage);

        echo '<div class="categorypagingbarcontainer">';
        $pageing_url = new moodle_url('ipal_quiz_edit.php');
        $r = $pageing_url->params($pageurl->params());
        $pagingbar = new paging_bar($totalnumber, $page, $perpage, $pageing_url);
        $pagingbar->pagevar = 'qpage';
        echo $OUTPUT->render($pagingbar);
        echo '</div>';

        echo '<form method="post" action="ipal_quiz_edit.php">';//Modified for ipal
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
                $url = new moodle_url('edit.php', ($pageurl->params()+array('qperpage'=>1000)));
                $showall = '<a href="'.$url.'">'.get_string('showall', 'moodle', $totalnumber).'</a>';
            } else {
                $url = new moodle_url('edit.php', ($pageurl->params()+array('qperpage'=>DEFAULT_QUESTIONS_PER_PAGE)));
                $showall = '<a href="'.$url.'">'.get_string('showperpage', 'moodle', DEFAULT_QUESTIONS_PER_PAGE).'</a>';
            }
            echo "<div class='paging'>$showall</div>";
        }
        echo '</div>';

        echo '<div class="modulespecificbuttonscontainer">';
        if ($caneditall || $canmoveall || $canuseall){
            echo '<strong>&nbsp;'.get_string('withselected', 'question').':</strong><br />';

            if (function_exists('module_specific_buttons')) {
                echo module_specific_buttons($this->cm->id,$cmoptions);
            }

            // print delete and move selected question

            if ($canmoveall && count($addcontexts)) {
                echo '<input type="submit" name="move" value="'.get_string('moveto', 'question')."\" />\n";
                question_category_select_menu($addcontexts, false, 0, "$category->id,$category->contextid");
            }

            if (function_exists('module_specific_controls') && $canuseall) {
                $modulespecific = module_specific_controls($totalnumber, $recurse, $category, $this->cm->id,$cmoptions);
                if(!empty($modulespecific)){
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
    const MAX_SORTS = 3;

    protected $baseurl;
    protected $editquestionurl;
    protected $quizorcourseid;
    protected $contexts;
    protected $cm;
    protected $course;
    protected $knowncolumntypes;
    protected $visiblecolumns;
    protected $extrarows;
    protected $requiredcolumns;
    protected $sort;
    protected $lastchangedid;
    protected $countsql;
    protected $loadsql;
    protected $sqlparams;

    /**
     * Constructor
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
        if ($cm !== null){
            $this->editquestionurl->param('cmid', $cm->id);
        } else {
            $this->editquestionurl->param('courseid', $this->course->id);
        }

        $this->lastchangedid = optional_param('lastchanged',0,PARAM_INT);

        $this->init_column_types();
        $this->init_columns($this->wanted_columns());
        $this->init_sort();

        $PAGE->requires->yui2_lib('container');
    }

    protected function wanted_columns() {
        $columns = array('checkbox', 'qtype', 'questionname', 'editaction',
                'previewaction', 'moveaction', 'deleteaction', 'creatorname',
                'modifiername');
        if (optional_param('qbshowtext', false, PARAM_BOOL)) {
            $columns[] = 'questiontext';
        }
        return $columns;
    }

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

    protected function init_column_types() {
        $this->knowncolumntypes = array();
        foreach ($this->known_field_types() as $col) {
            $this->knowncolumntypes[$col->get_name()] = $col;
        }
    }

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
     * @param string $colname a column internal name.
     * @return bool is this column included in the output?
     */
    public function has_column($colname) {
        return isset($this->visiblecolumns[$colname]);
    }

    /**
     * @return int The number of columns in the table.
     */
    public function get_column_count() {
        return count($this->visiblecolumns);
    }

    public function get_courseid() {
        return $this->course->id;
    }

    protected function init_sort() {
        $this->init_sort_from_params();
        if (empty($this->sort)) {
            $this->sort = $this->default_sort();
        }
    }

    /**
     * Deal with a sort name of the forum columnname, or colname_subsort by
     * breaking it up, validating the bits that are presend, and returning them.
     * If there is no subsort, then $subsort is returned as ''.
     * @return array array($colname, $subsort).
     */
    protected function parse_subsort($sort) {
    /// Do the parsing.
        if (strpos($sort, '_') !== false) {
            list($colname, $subsort) = explode('_', $sort, 2);
        } else {
            $colname = $sort;
            $subsort = '';
        }
    /// Validate the column name.
        if (!isset($this->knowncolumntypes[$colname]) || !$this->knowncolumntypes[$colname]->is_sortable()) {
            for ($i = 1; $i <= ipal_question_bank_view::MAX_SORTS; $i++) {
                $this->baseurl->remove_params('qbs' . $i);
            }
            throw new moodle_exception('unknownsortcolumn', '', $link = $this->baseurl->out(), $colname);
        }
    /// Validate the subsort, if present.
        if ($subsort) {
            $subsorts = $this->knowncolumntypes[$colname]->is_sortable();
            if (!is_array($subsorts) || !isset($subsorts[$subsort])) {
                throw new moodle_exception('unknownsortcolumn', '', $link = $this->baseurl->out(), $sort);
            }
        }
        return array($colname, $subsort);
    }

    protected function init_sort_from_params() {
        $this->sort = array();
        for ($i = 1; $i <= ipal_question_bank_view::MAX_SORTS; $i++) {
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

    protected function default_sort() {
        return array('qtype' => 1, 'questionname' => 1);
    }

    /**
     * @param $sort a column or column_subsort name.
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
        if (count($newsort) > ipal_question_bank_view::MAX_SORTS) {
            $newsort = array_slice($newsort, 0, ipal_question_bank_view::MAX_SORTS, true);
        }
        return $this->baseurl->out(true, $this->sort_to_params($newsort));
    }

    protected function build_query_sql($category, $recurse, $showhidden) {
        global $DB;

    /// Get the required tables.
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

    /// Get the required fields.
        $fields = array('q.hidden', 'q.category');
        foreach ($this->visiblecolumns as $column) {
            $fields = array_merge($fields, $column->get_required_fields());
        }
        foreach ($this->extrarows as $row) {
            $fields = array_merge($fields, $row->get_required_fields());
        }
        $fields = array_unique($fields);

    /// Build the order by clause.
        $sorts = array();
        foreach ($this->sort as $sort => $order) {
            list($colname, $subsort) = $this->parse_subsort($sort);
            $sorts[] = $this->knowncolumntypes[$colname]->sort_expression($order < 0, $subsort);
        }

    /// Build the where clause.
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

    /// Build the SQL.
        $sql = ' FROM {question} q ' . implode(' ', $joins);
        $sql .= ' WHERE ' . implode(' AND ', $tests);
        $this->countsql = 'SELECT count(1)' . $sql;
        $this->loadsql = 'SELECT ' . implode(', ', $fields) . $sql . ' ORDER BY ' . implode(', ', $sorts);
        $this->sqlparams = $params;
    }

    protected function get_question_count() {
        global $DB;
        return $DB->count_records_sql($this->countsql, $this->sqlparams);
    }

    protected function load_page_questions($page, $perpage) {
        global $DB;
        $questions = $DB->get_recordset_sql($this->loadsql, $this->sqlparams, $page*$perpage, $perpage);
        if (!$questions->valid()) {
        /// No questions on this page. Reset to page 0.
            $questions = $DB->get_recordset_sql($this->loadsql, $this->sqlparams, 0, $perpage);
        }
        return $questions;
    }

    public function base_url() {
        return $this->baseurl;
    }

    public function edit_question_url($questionid) {
        return $this->editquestionurl->out(true, array('id' => $questionid));
    }

    public function move_question_url($questionid) {
        return $this->editquestionurl->out(true, array('id' => $questionid, 'movecontext' => 1));
    }

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
     */
    public function display($tabname, $page, $perpage, $cat,
            $recurse, $showhidden, $showquestiontext) {
        global $PAGE, $OUTPUT;

        if ($this->process_actions_needing_ui()) {
            return;
        }

        $PAGE->requires->js('/question/qbank.js');

        // Category selection form
        echo $OUTPUT->heading(get_string('questionbank', 'question'), 2);

        $this->display_category_form($this->contexts->having_one_edit_tab_cap($tabname),
                $this->baseurl, $cat);
        $this->display_options($recurse, $showhidden, $showquestiontext);

        if (!$category = $this->get_current_category($cat)) {
            return;
        }
        $this->print_category_info($category);

        // continues with list of questions
        $this->display_question_list($this->contexts->having_one_edit_tab_cap($tabname),
                $this->baseurl, $cat, $this->cm,
                $recurse, $page, $perpage, $showhidden, $showquestiontext,
                $this->contexts->having_cap('moodle/question:add'));
    }

    protected function print_choose_category_message($categoryandcontext) {
        echo "<p style=\"text-align:center;\"><b>";
        print_string("selectcategoryabove", "question");
        echo "</b></p>";
    }

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
     */
    protected function display_category_form($contexts, $pageurl, $current) {
        global $CFG, $OUTPUT;

    /// Get all the existing categories now
        echo '<div class="choosecategory">';
        $catmenu = question_category_options($contexts, false, 0, true);

        $select = new single_select($this->baseurl, 'category', $catmenu, $current, null, 'catmenu');
        $select->set_label(get_string('selectacategory', 'question'));
        echo $OUTPUT->render($select);
        echo "</div>\n";
    }

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
    * @param object $course   The course object
    * @param int $categoryid  The id of the question category to be displayed
    * @param int $cm      The course module record if we are in the context of a particular module, 0 otherwise
    * @param int $recurse     This is 1 if subcategories should be included, 0 otherwise
    * @param int $page        The number of the page to be displayed
    * @param int $perpage     Number of questions to show per page
    * @param bool $showhidden   True if also hidden questions should be displayed
    * @param bool $showquestiontext whether the text of each question should be shown in the list
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
        $catcontext = get_context_instance_by_id($contextid);

        $canadd = has_capability('moodle/question:add', $catcontext);
        $caneditall =has_capability('moodle/question:editall', $catcontext);
        $canuseall =has_capability('moodle/question:useall', $catcontext);
        $canmoveall =has_capability('moodle/question:moveall', $catcontext);

        $this->create_new_question_form($category, $canadd);

        $this->build_query_sql($category, $recurse, $showhidden);
        $totalnumber = $this->get_question_count();
        if ($totalnumber == 0) {
            return;
        }

        $questions = $this->load_page_questions($page, $perpage);

        echo '<div class="categorypagingbarcontainer">';
        $pageing_url = new moodle_url('edit.php');
        $r = $pageing_url->params($pageurl->params());
        $pagingbar = new paging_bar($totalnumber, $page, $perpage, $pageing_url);
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
                $url = new moodle_url('edit.php', ($pageurl->params()+array('qperpage'=>1000)));
                $showall = '<a href="'.$url.'">'.get_string('showall', 'moodle', $totalnumber).'</a>';
            } else {
                $url = new moodle_url('edit.php', ($pageurl->params()+array('qperpage'=>DEFAULT_QUESTIONS_PER_PAGE)));
                $showall = '<a href="'.$url.'">'.get_string('showperpage', 'moodle', DEFAULT_QUESTIONS_PER_PAGE).'</a>';
            }
            echo "<div class='paging'>$showall</div>";
        }
        echo '</div>';

        echo '<div class="modulespecificbuttonscontainer">';
        if ($caneditall || $canmoveall || $canuseall){
            echo '<strong>&nbsp;'.get_string('withselected', 'question').':</strong><br />';

            if (function_exists('module_specific_buttons')) {
                echo module_specific_buttons($this->cm->id,$cmoptions);
            }

            // print delete and move selected question
            if ($caneditall) {
                echo '<input type="submit" name="deleteselected" value="' . $strdelete . "\" />\n";
            }

            if ($canmoveall && count($addcontexts)) {
                echo '<input type="submit" name="move" value="'.get_string('moveto', 'question')."\" />\n";
                question_category_select_menu($addcontexts, false, 0, "$category->id,$category->contextid");
            }

            if (function_exists('module_specific_controls') && $canuseall) {
                $modulespecific = module_specific_controls($totalnumber, $recurse, $category, $this->cm->id,$cmoptions);
                if(!empty($modulespecific)){
                    echo "<hr />$modulespecific";
                }
            }
        }
        echo "</div>\n";

        echo '</fieldset>';
        echo "</form>\n";
    }

    protected function start_table() {
        echo '<table id="categoryquestions">' . "\n";
        echo "<thead>\n";
        $this->print_table_headers();
        echo "</thead>\n";
        echo "<tbody>\n";
    }

    protected function end_table() {
        echo "</tbody>\n";
        echo "</table>\n";
    }

    protected function print_table_headers() {
        echo "<tr>\n";
        foreach ($this->visiblecolumns as $column) {
            $column->display_header();
        }
        echo "</tr>\n";
    }

    protected function get_row_classes($question, $rowcount) {
        $classes = array();
        if ($question->hidden) {
            $classes[] = 'dimmed_text';
        }
        if ($question->id == $this->lastchangedid) {
            $classes[] ='highlight';
        }
        if (!empty($this->extrarows)) {
            $classes[] = 'r' . ($rowcount % 2);
        }
        return $classes;
    }

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

    public function process_actions() {
        global $CFG, $DB;
        /// Now, check for commands on this page and modify variables as necessary
        if (optional_param('move', false, PARAM_BOOL) and confirm_sesskey()) {
            // Move selected questions to new category
            $category = required_param('category', PARAM_SEQUENCE);
            list($tocategoryid, $contextid) = explode(',', $category);
            if (! $tocategory = $DB->get_record('question_categories', array('id' => $tocategoryid, 'contextid' => $contextid))) {
                print_error('cannotfindcate', 'question');
            }
            $tocontext = get_context_instance_by_id($contextid);
            require_capability('moodle/question:add', $tocontext);
            $rawdata = (array) data_submitted();
            $questionids = array();
            foreach ($rawdata as $key => $value) {    // Parse input for question ids
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
                foreach ($questions as $question){
                    ipal_question_require_capability_on($question, 'move');
                }
				ipal_question_move_questions_to_category($questionids, $tocategory->id);
                redirect($this->baseurl->out(false,
                        array('category' => "$tocategoryid,$contextid")));
            }
        }

        if (optional_param('deleteselected', false, PARAM_BOOL)) { // delete selected questions from the category
            if (($confirm = optional_param('confirm', '', PARAM_ALPHANUM)) and confirm_sesskey()) { // teacher has already confirmed the action
                $deleteselected = required_param('deleteselected', PARAM_RAW);
                if ($confirm == md5($deleteselected)) {
                    if ($questionlist = explode(',', $deleteselected)) {
                        // for each question either hide it if it is in use or delete it
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

        // Unhide a question
        if(($unhide = optional_param('unhide', '', PARAM_INT)) and confirm_sesskey()) {
            question_require_capability_on($unhide, 'edit');
            $DB->set_field('question', 'hidden', 0, array('id' => $unhide));
            redirect($this->baseurl);
        }
    }

    public function process_actions_needing_ui() {
        global $DB, $OUTPUT;
        if (optional_param('deleteselected', false, PARAM_BOOL)) {
            // make a list of all the questions that are selected
            $rawquestions = $_REQUEST; // This code is called by both POST forms and GET links, so cannot use data_submitted.
            $questionlist = '';  // comma separated list of ids of questions to be deleted
            $questionnames = ''; // string with names of questions separated by <br /> with
                                 // an asterix in front of those that are in use
            $inuse = false;      // set to true if at least one of the questions is in use
            foreach ($rawquestions as $key => $value) {    // Parse input for question ids
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
            if (!$questionlist) { // no questions were selected
                redirect($this->baseurl);
            }
            $questionlist = rtrim($questionlist, ',');

            // Add an explanation about questions in use
            if ($inuse) {
                $questionnames .= '<br />'.get_string('questionsinuse', 'question');
            }
            $baseurl = new moodle_url('edit.php', $this->baseurl->params());
            $deleteurl = new moodle_url($baseurl, array('deleteselected'=>$questionlist, 'confirm'=>md5($questionlist), 'sesskey'=>sesskey()));

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
            // Otherwise, we need to make one
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
	
    if(ipal_acceptable_qtype($id) === true){
	    //echo "\n<br />IPAL does support ".ipal_acceptable_qtype($id)." questions.";	    
	} else
	{
	    $alertmessage = "IPAL does not support ".ipal_acceptable_qtype($id)." questions.";
		echo "<script language='javascript'>alert('$alertmessage')</script>";
		return false;
	}
    // remove ending page break if it is not needed
    if ($breaks = array_keys($questions, 0)) {
        // determine location of the last two page breaks
        $end = end($breaks);
        $last = prev($breaks);
        $last = $last ? $last : -1;
        if (!$quiz->questionsperpage || (($end - $last - 1) < $quiz->questionsperpage)) {
            array_pop($questions);
        }
    }
    if (is_int($page) && $page >= 1) {
//        $numofpages = quiz_number_of_pages($quiz->questions);
		$numofpages = substr_count(',' . $quiz->questions, ',0');
        if ($numofpages<$page) {
            //the page specified does not exist in quiz
            $page = 0;
        } else {
            // add ending page break - the following logic requires doing
            //this at this point
            $questions[] = 0;
            $currentpage = 1;
            $addnow = false;
            foreach ($questions as $question) {
                if ($question == 0) {
                    $currentpage++;
                    //The current page is the one after the one we want to add on,
                    //so we add the question before adding the current page.
                    if ($currentpage == $page + 1) {
                        $questions_new[] = $id;
                    }
                }
                $questions_new[] = $question;
            }
            $questions = $questions_new;
        }
    }
    if ($page == 0) {
        // add question
        $questions[] = $id;
        // add ending page break
        $questions[] = 0;
    }

    // Save new questionslist in database
    $quiz->questions = implode(',', $questions);
    $DB->set_field('ipal', 'questions', $quiz->questions, array('id' => $quiz->id));

    // Add the new question instance.//Modified for ipal NOt needed in ipal
    //$instance = new stdClass();
    //$instance->quiz = $quiz->id;
    //$instance->question = $id;
    //$instance->grade = $DB->get_field('question', 'defaultmark', array('id' => $id));
    //$DB->insert_record('quiz_question_instances', $instance);
}


/**
 * Remove a question from a quiz
 * @param object $quiz the quiz object.
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
    //$DB->delete_records('quiz_question_instances',
      //      array('quiz' => $quiz->instance, 'question' => $questionid));//Modified for ipal
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
    // Remove repeated ','s. This can happen when a restore fails to find the right
    // id to relink to.
    $layout = preg_replace('/,{2,}/', ',', trim($layout, ','));

    // Remove duplicate question ids
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
        // Avoid duplicate page breaks
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

    // Add a page break at the end if there is none
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
    protected $strselect;
    protected $firstrow = true;

    public function init() {
        $this->strselect = get_string('select');
    }

    public function get_name() {
        return 'checkbox';
    }

    protected function get_title() {
        return '<input type="checkbox" disabled="disabled" id="qbheadercheckbox" />';
    }

    protected function get_title_tip() {
        return get_string('selectquestionsforbulk', 'question');
    }

    protected function display_content($question, $rowclasses) {
        global $PAGE;
        echo '<input title="' . $this->strselect . '" type="checkbox" name="q' .
                $question->id . '" id="checkq' . $question->id . '" value="1"/>';
        if ($this->firstrow) {
            $PAGE->requires->js_function_call('question_bank.init_checkbox_column', array(get_string('selectall'),
                    get_string('deselectall'), 'checkq' . $question->id));
            $this->firstrow = false;
        }
    }

    public function get_required_fields() {
        return array('q.id');
    }
}


/**
 * Base class for representing a column in a {@link ipal_question_bank_view}.
 *
 * @copyright  2009 Tim Hunt
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
abstract class ipal_question_bank_column_base {
    /**
     * @var ipal_question_bank_view
     */
    protected $qbank;

    /**
     * Constructor.
     * @param $qbank the ipal_question_bank_view we are helping to render.
     */
    public function __construct(ipal_question_bank_view $qbank) {
        $this->qbank = $qbank;
        $this->init();
    }

    /**
     * A chance for subclasses to initialise themselves, for example to load lang strings,
     * without having to override the constructor.
     */
    protected function init() {
    }

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
     * @param object $question the row from the $question table, augmented with extra information.
     * @param string $rowclasses CSS class names that should be applied to this row of output.
     */
    protected abstract function get_title();

    /**
     * @return string a fuller version of the name. Use this when get_title() returns
     * something very short, and you want a longer version as a tool tip.
     */
    protected function get_title_tip() {
        return '';
    }

    /**
     * Get a link that changes the sort order, and indicates the current sort state.
     * @param $name internal name used for this type of sorting.
     * @param $currentsort the current sort order -1, 0, 1 for descending, none, ascending.
     * @param $title the link text.
     * @param $defaultreverse whether the default sort order for this column is descending, rather than ascending.
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
     * @param $reverse sort is descending, not ascending.
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
     * @param object $question the row from the $question table, augmented with extra information.
     * @param string $rowclasses CSS class names that should be applied to this row of output.
     */
    public function display($question, $rowclasses) {
        $this->display_start($question, $rowclasses);
        $this->display_content($question, $rowclasses);
        $this->display_end($question, $rowclasses);
    }

    protected function display_start($question, $rowclasses) {
        echo '<td class="' . $this->get_classes() . '">';
    }

    /**
     * @return string the CSS classes to apply to every cell in this column.
     */
    protected function get_classes() {
        $classes = $this->get_extra_classes();
        $classes[] = $this->get_name();
        return implode(' ', $classes);
    }

    /**
     * @param object $question the row from the $question table, augmented with extra information.
     * @return string internal name for this column. Used as a CSS class name,
     *     and to store information about the current sort. Must match PARAM_ALPHA.
     */
    public abstract function get_name();

    /**
     * @return array any extra class names you would like applied to every cell in this column.
     */
    public function get_extra_classes() {
        return array();
    }

    /**
     * Output the contents of this column.
     * @param object $question the row from the $question table, augmented with extra information.
     * @param string $rowclasses CSS class names that should be applied to this row of output.
     */
    protected abstract function display_content($question, $rowclasses);

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
     * @param bool $reverse whether the normal direction should be reversed.
     * @param string $normaldir 'ASC' or 'DESC'
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
     * @param $reverse Whether to sort in the reverse of the default sort order.
     * @param $subsort if is_sortable returns an array of subnames, then this will be
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
    public function get_name() {
        return 'qtype';
    }

    protected function get_title() {
        return get_string('qtypeveryshort', 'question');
    }

    protected function get_title_tip() {
        return get_string('questiontype', 'question');
    }

    protected function display_content($question, $rowclasses) {
        echo print_question_icon($question);
    }

    public function get_required_fields() {
        return array('q.qtype');
    }

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
    protected $checkboxespresent = null;

    public function get_name() {
        return 'questionname';
    }

    protected function get_title() {
        return get_string('question');
    }

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

    public function get_required_fields() {
        return array('q.id', 'q.name');
    }

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
    public function get_name() {
        return 'creatorname';
    }

    protected function get_title() {
        return get_string('createdby', 'question');
    }

    protected function display_content($question, $rowclasses) {
        if (!empty($question->creatorfirstname) && !empty($question->creatorlastname)) {
            $u = new stdClass();
            $u->firstname = $question->creatorfirstname;
            $u->lastname = $question->creatorlastname;
            echo fullname($u);
        }
    }

    public function get_extra_joins() {
        return array('uc' => 'LEFT JOIN {user} uc ON uc.id = q.createdby');
    }

    public function get_required_fields() {
        return array('uc.firstname AS creatorfirstname', 'uc.lastname AS creatorlastname');
    }

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
    public function get_name() {
        return 'modifiername';
    }

    protected function get_title() {
        return get_string('lastmodifiedby', 'question');
    }

    protected function display_content($question, $rowclasses) {
        if (!empty($question->modifierfirstname) && !empty($question->modifierlastname)) {
            $u = new stdClass();
            $u->firstname = $question->modifierfirstname;
            $u->lastname = $question->modifierlastname;
            echo fullname($u);
        }
    }

    public function get_extra_joins() {
        return array('um' => 'LEFT JOIN {user} um ON um.id = q.modifiedby');
    }

    public function get_required_fields() {
        return array('um.firstname AS modifierfirstname', 'um.lastname AS modifierlastname');
    }

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
    protected $stredit;
    protected $strview;

    public function init() {
        parent::init();
        $this->stredit = get_string('edit');
        $this->strview = get_string('view');
    }

    public function get_name() {
        return 'editaction';
    }

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
    protected $strpreview;

    public function init() {
        parent::init();
        $this->strpreview = get_string('preview');
    }

    public function get_name() {
        return 'previewaction';
    }

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
    protected $strmove;

    public function init() {
        parent::init();
        $this->strmove = get_string('move');
    }

    public function get_name() {
        return 'moveaction';
    }

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
    protected $strdelete;
    protected $strrestore;

    public function init() {
        parent::init();
        $this->strdelete = get_string('delete');
        $this->strrestore = get_string('restore');
    }

    public function get_name() {
        return 'deleteaction';
    }

    protected function display_content($question, $rowclasses) {
        if (question_has_capability_on($question, 'edit')) {
            if ($question->hidden) {
                $url = new moodle_url($this->qbank->base_url(), array('unhide' => $question->id, 'sesskey'=>sesskey()));
                $this->print_icon('t/restore', $this->strrestore, $url);
            } else {
                $url = new moodle_url($this->qbank->base_url(), array('deleteselected' => $question->id, 'q' . $question->id => 1, 'sesskey'=>sesskey()));
                $this->print_icon('t/delete', $this->strdelete, $url);
            }
        }
    }

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

    protected function get_title() {
        return '&#160;';
    }

    public function get_extra_classes() {
        return array('iconcol');
    }

    protected function print_icon($icon, $title, $url) {
        global $OUTPUT;
        echo '<a title="' . $title . '" href="' . $url . '">
                <img src="' . $OUTPUT->pix_url($icon) . '" class="iconsmall" alt="' . $title . '" /></a>';
    }

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
    protected $formatoptions;

    protected function init() {
        $this->formatoptions = new stdClass();
        $this->formatoptions->noclean = true;
        $this->formatoptions->para = false;
    }

    public function get_name() {
        return 'questiontext';
    }

    protected function get_title() {
        return get_string('questiontext', 'question');
    }

    protected function display_content($question, $rowclasses) {
        $text = format_text($question->questiontext, $question->questiontextformat,
                $this->formatoptions, $this->qbank->get_courseid());
        if ($text == '') {
            $text = '&#160;';
        }
        echo $text;
    }

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
    public function is_extra_row() {
        return true;
    }

    protected function display_start($question, $rowclasses) {
        if ($rowclasses) {
            echo '<tr class="' . $rowclasses . '">' . "\n";
        } else {
            echo "<tr>\n";
        }
        echo '<td colspan="' . $this->qbank->get_column_count() . '" class="' . $this->get_name() . '">';
    }

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
    protected $stradd;

    public function init() {
        parent::init();
        $this->stradd = get_string('addtoquiz', 'quiz');
    }

    public function get_name() {
        return 'addtoipalaction';
    }

    protected function display_content($question, $rowclasses) {
        // for RTL languages: switch right and left arrows
        if (right_to_left()) {
            $movearrow = 't/removeright';
        } else {
            $movearrow = 't/moveleft';
        }
		$this->print_icon($movearrow, $this->stradd, $this->qbank->add_to_ipal_url($question->id));
    }

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
    public function get_name() {
        return 'questionnametext';
    }

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
    echo $OUTPUT->single_button($url, $caption, 'get', array('disabled'=>$disabled, 'title'=>$tooltip));

    $PAGE->requires->yui2_lib('dragdrop');
    $PAGE->requires->yui2_lib('container');
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
 * @param $hiddenparams hidden parameters to add to the form, in addition to
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
 * @param $qtype the question type.
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
 * @param object $quiz the quiz settings
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
 * @param object $quiz the quiz settings
 * @param object $question the question
 * @return moodle_url to preview this question with the options from this quiz.
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
    return question_preview_url($question->id, NULL,
            $maxmark, $displayoptions);
}

/**
 * An extension of question_display_options that includes the extra options used
 * by the quiz.
 *
 * @copyright  2010 The Open University
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class mod_ipal_display_options extends ipal_question_display_options {
    /**#@+
     * @var integer bits used to indicate various times in relation to a
     * quiz attempt.
     */
    const DURING =            0x10000;
    const IMMEDIATELY_AFTER = 0x01000;
    const LATER_WHILE_OPEN =  0x00100;
    const AFTER_CLOSE =       0x00010;
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
     * @param int $one of the {@link DURING}, {@link IMMEDIATELY_AFTER},
     * {@link LATER_WHILE_OPEN} or {@link AFTER_CLOSE} constants.
     * @return mod_quiz_display_options set up appropriately.
     */
    public static function make_from_quiz($quiz, $when) {
        $options = new self();

//        $options->attempt = self::extract($quiz->reviewattempt, $when, true, false);
//        $options->correctness = self::extract($quiz->reviewcorrectness, $when);
//        $options->marks = self::extract($quiz->reviewmarks, $when,
//                self::MARK_AND_MAX, self::MAX_ONLY);
//        $options->feedback = self::extract($quiz->reviewspecificfeedback, $when);
//        $options->generalfeedback = self::extract($quiz->reviewgeneralfeedback, $when);
//        $options->rightanswer = self::extract($quiz->reviewrightanswer, $when);
//        $options->overallfeedback = self::extract($quiz->reviewoverallfeedback, $when);

//        $options->numpartscorrect = $options->feedback;

//        if ($quiz->questiondecimalpoints != -1) {
//            $options->markdp = $quiz->questiondecimalpoints;
//        } else {
//            $options->markdp = $quiz->decimalpoints;
//        }
			$options->markdp = 2;
        return $options;
    }

    protected static function extract($bitmask, $bit,
            $whenset = self::VISIBLE, $whennotset = self::HIDDEN) {
        if ($bitmask & $bit) {
            return $whenset;
        } else {
            return $whennotset;
        }
    }
}

function ipal_print_question_list($quiz, $pageurl, $allowdelete, $reordertool,
        $quiz_qbanktool, $hasattempts, $defaultcategoryobj) {
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
	$hasattempts = 0;//modified for ipal this is added because attempts don't mean anything with ipal
    if ($quiz->questions) {
        list($usql, $params) = $DB->get_in_or_equal(explode(',', $quiz->questions));
		$qcount = 0;
		foreach($params as $key => $value){
		if($value > 0){
		$questions = $DB->get_records_sql("SELECT q.* FROM {question} q WHERE q.id = $value",$params);
		$ipal_questions[$value] = $questions[$value];
		$ipal_name[$qcount] = trim($questions[$value]->name);
		$ipal_questiontext[$qcount] = trim($questions[$value]->questiontext);
		$ipal_id[$qcount] = $value;
		$ipal_qtype[$qcount] = $questions[$value]->qtype;
		$qcount++;
		}
		else{
		$ipal_id[$qcount] = 0;
		$qcount++;
		}
		}
			
    } else {
        $questions = array();
    }


	if(isset($ipal_id)){
		$order = $ipal_id;
	}
	else
	{
		$order = NULL;
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
    $reordercontrols3.=    ' <a href="javascript:deselect_all_in(\'FORM\', ' .
            'null, \'quizquestions\');">' .
            $strselectnone . '</a>';

    $reordercontrolstop = '<div class="reordercontrols">' .
            $reordercontrolssetdefaultsubmit .
            $reordercontrols1 . $reordercontrols2top . $reordercontrols3 . "</div>";
    $reordercontrolsbottom = '<div class="reordercontrols">' .
            $reordercontrolssetdefaultsubmit .
            $reordercontrols2bottom . $reordercontrols1 . $reordercontrols3 . "</div>";

    //the current question ordinal (no descriptions)
    $qno = 1;
    //the current question (includes questions and descriptions)
    $questioncount = 0;
    //the current page number in iteration
    $pagecount = 0;

    $pageopen = false;

    $returnurl = str_replace($CFG->wwwroot, '', $pageurl->out(false));
    $questiontotalcount = count($order);
	
    if(isset($order)){
	foreach ($order as $count => $qnum) {

        $reordercheckbox = '';
        $reordercheckboxlabel = '';
        $reordercheckboxlabelclose = '';

        if ($qnum && strlen($ipal_name[$count])==0 && strlen($ipal_text[$count])==0){//if ($qnum && empty($questions[$qnum])) {
            continue;
        }

        if ($qnum != 0 || ($qnum == 0 && !$pageopen)) {
            //this is either a question or a page break after another
            //        (no page is currently open)
            if (!$pageopen) {
                //if no page is open, start display of a page
                $pagecount++;
                echo  '<div class="quizpage">';
                echo        '<div class="pagecontent">';
                $pageopen = true;
            }

            if ($qnum != 0) {
                $question = $ipal_questions[$qnum];//echo "\n<br />debug180 and qnum is $qnum";
                $questionparams = array(
                        'returnurl' => $returnurl,
                        'cmid' => $quiz->cmid,
                        'id' => $ipal_id[$count]);
                $questionurl = new moodle_url('/question/question.php',
                        $questionparams);
                $questioncount++;
                //this is an actual question

                /* Display question start */
                ?>
<div class="question">
    <div class="questioncontainer <?php echo $ipal_qtype[$count]; ?>">
        <div class="qnum">
                <?php
                $reordercheckbox = '';
                $reordercheckboxlabel = '';
                $reordercheckboxlabelclose = '';

                if ((strlen($ipal_name[$count]) + strlen($ipal_questiontext[$count])) == 0) {
                    $qnodisplay = get_string('infoshort', 'quiz');
                    $qnodisplay = '?';
                } else {

                    if ($qno > 999){// || ($reordertool && $qno > 99)) {
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
                                array('up' => $ipal_id[$count], 'sesskey'=>sesskey())),
                                new pix_icon('t/up', $strmoveup),
                                new component_action('click',
                                        'M.core_scroll_manager.save_scroll_action'),
                                array('title' => $strmoveup));
                    }

                }
                if ($count < $lastindex - 1) {
                    if (!$hasattempts) {
                        echo $OUTPUT->action_icon($pageurl->out(true,
                                array('down' => $ipal_id[$count], 'sesskey'=>sesskey())),
                                new pix_icon('t/down', $strmovedown),
                                new component_action('click',
                                        'M.core_scroll_manager.save_scroll_action'),
                                array('title' => $strmovedown));
                    }
                }
                if ($allowdelete){// && (empty($ipal_id[$count]) ||
                        //question_has_capability_on($question, 'use', $question->category))) {
                    // remove from quiz, not question delete.
                    if (!$hasattempts) {
                        echo $OUTPUT->action_icon($pageurl->out(true,
                                array('remove' => $ipal_id[$count], 'sesskey'=>sesskey())),
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
        //a page break: end the existing page.
        if ($qnum == 0) {//This should never happen
            if ($pageopen) {
                if (!$reordertool && !($quiz->shufflequestions &&
                        $count < $questiontotalcount - 1)) {
                    ipal_print_pagecontrols($quiz, $pageurl, $pagecount,
                            $hasattempts, $defaultcategoryobj);
                } else if ($count < $questiontotalcount - 1) {
                    //do not include the last page break for reordering
                    //to avoid creating a new extra page in the end
                    echo '<input type="hidden" name="opg' . $pagecount . '" size="2" value="' .
                            (10*$count + 10) . '" />';
                }
                echo "</div></div>";

                $pageopen = false;
                $count++;
            }
        }

    }
	}//End of if(isset($order)
}

/**
 *Function to check that the question is a qustion type supported in ipal
 * @questionid is the id of the question in the question table
  */

function ipal_acceptable_qtype($questionid){
    global $DB;
	/**
	 *An array of acceptable qutypes supported in ipal
	 */
    $acceptableqtypes = array('multichoice','truefalse','essay');
	$qtype = $DB->get_field('question','qtype',array('id'=>$questionid));
	if(in_array($qtype,$acceptableqtypes)){
	    return true;
	} else
	{
	    return $qtype;
	}
}


/**
 * Print a given single question in quiz for the edit tab of edit.php.
 * Meant to be used from quiz_print_question_list()
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
 * @param int $cmid the course_module.id for this quiz.
 * @param object $question the question.
 * @param string $returnurl url to return to after action is done.
 * @param string $contentbeforeicon some HTML content to be added inside the link, before the icon.
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
 */
function ipal_print_pagecontrols($quiz, $pageurl, $page, $hasattempts, $defaultcategoryobj) {
    global $CFG, $OUTPUT;
    static $randombuttoncount = 0;
    $randombuttoncount++;
    echo '<div class="pagecontrols">';
	$hasattempts = 0;//modified for ipal this is added because attempts don't mean anything with ipal
    // Get the current context
    $thiscontext = get_context_instance(CONTEXT_COURSE, $quiz->course);
    $contexts = new question_edit_contexts($thiscontext);

    // Get the default category.
    list($defaultcategoryid) = explode(',', $pageurl->param('cat'));
    if (empty($defaultcategoryid)) {
        $defaultcategoryid = $defaultcategoryobj->id;
    }

    // Create the url the question page will return to
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
    }//I removed lines 424-442  because they added a random button
    echo "\n</div>";
}


// Private function used by the following two.
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
    return _ipal_move_question($layout, $questionid, +1);
}
