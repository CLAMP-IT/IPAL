<?php
require_once('../../config.php');
require_once('../../mod/ejsapp/generate_applet_embedding_code.php');


$id = optional_param('id', 0, PARAM_INT); // course_module ID, or
$n  = optional_param('n', 0, PARAM_INT);  // ejsapp instance ID - it should be named as the first character of the module
if ($id) {
    $cm         = get_coursemodule_from_id('ejsapp', $id, 0, false, MUST_EXIST);
    $course     = $DB->get_record('course', array('id' => $cm->course), '*', MUST_EXIST);
    $ejsapp  = $DB->get_record('ejsapp', array('id' => $cm->instance), '*', MUST_EXIST);
} elseif ($n) {
    $ejsapp  = $DB->get_record('ejsapp', array('id' => $n), '*', MUST_EXIST);
    $course     = $DB->get_record('course', array('id' => $ejsapp->course), '*', MUST_EXIST);
    $cm         = get_coursemodule_from_instance('ejsapp', $ejsapp->id, $course->id, false, MUST_EXIST);
} else {
    error('You must specify a course_module ID or an instance ID');
}

//require_login($course, true, $cm);/Find out why this causes an error
$context = get_context_instance(CONTEXT_MODULE, $cm->id);

add_to_log($course->id, 'ejsapp', 'view', "view.php?id=$cm->id", $ejsapp->name, $cm->id);
// Setting some, but not all, of the PAGE values
$PAGE->set_url('/mod/ejsapp/view.php', array('id' => $cm->id));
$PAGE->set_context($context);
$PAGE->set_button(update_module_button($cm->id, $course->id, get_string('modulename', 'ejsapp')));

// Output starts here
echo "<html><head></head><body>";
echo "<div align='center'>\n";
$COLUMNS_WIDTH = 50;
echo $OUTPUT->heading(generate_applet_embedding_code($ejsapp, null, null, null));
//$ejstext = preg_replace("/w\ \-\ 480/",'w - 50',$ejstext);
//echo $ejstext;
echo "\n</div></body></html>";
// Finish the page
//echo $OUTPUT->footer();