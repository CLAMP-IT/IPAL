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
 * This script provides the code so that EJS activities can be added to questions in IPAL.
 *
 *
 * @package   mod_ipal
 * @copyright 2011 w. F. Junkin, Eckerd College (http://www.eckerd.edu)
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once('../../config.php');
require_once('../../mod/ejsapp/generate_applet_embedding_code.php');


$id = optional_param('id', 0, PARAM_INT); // Course_module ID.
$n  = optional_param('n', 0, PARAM_INT);  // Ejsapp instance ID.
if ($id) {
    $cm         = get_coursemodule_from_id('ejsapp', $id, 0, false, MUST_EXIST);
    $course     = $DB->get_record('course', array('id' => $cm->course), '*', MUST_EXIST);
    $ejsapp  = $DB->get_record('ejsapp', array('id' => $cm->instance), '*', MUST_EXIST);
} else {
    if ($n) {
        $ejsapp  = $DB->get_record('ejsapp', array('id' => $n), '*', MUST_EXIST);
        $course     = $DB->get_record('course', array('id' => $ejsapp->course), '*', MUST_EXIST);
        $cm         = get_coursemodule_from_instance('ejsapp', $ejsapp->id, $course->id, false, MUST_EXIST);
    } else {
        error('You must specify a course_module ID or an instance ID');
    }
}

// Require_login($course, true, $cm);/Find out why this causes an error.
$context = context_module::instance($cm->id);

add_to_log($course->id, 'ejsapp', 'view', "view.php?id=$cm->id", $ejsapp->name, $cm->id);
// Setting some, but not all, of the PAGE values.
$PAGE->set_url('/mod/ejsapp/view.php', array('id' => $cm->id));
$PAGE->set_context($context);
$PAGE->set_button(update_module_button($cm->id, $course->id, get_string('modulename', 'ejsapp')));

// Output starts here.
echo "<html><head></head><body>";
echo "<div align='center'>\n";
$columnswidth = 50;
if (isset($CFG->columns_width)) {
    $originalcolumnswidth = $CFG->columns_width;
}
$CFG->columns_width = 50;
$externalsize = new stdClass;
$externalsize->width = 570;
$externalsize->height = 380;
echo $OUTPUT->heading(generate_applet_embedding_code($ejsapp, null, null, null, null, null, $externalsize));
if (isset($originalcolumnswidth)) {
    $CFG->columns_width = $originalcolumnswidth;
}
echo "\n</div></body></html>";
// Finish the page.
// Echo $OUTPUT->footer().