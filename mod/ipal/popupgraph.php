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
 * Page to display student answers to IPAL questions
 *
 * @package    mod_ipal
 * @copyright 2011 W. F. Junkin, Eckerd College (http://www.eckerd.edu) 
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
?>
<html>
<head>
<?php
echo "<meta http-equiv=\"refresh\" content=\"3;url=?ipalid=".$_GET['ipalid']."\">";
?>
</head>
<body>
<?php
require_once(dirname(dirname(dirname(__FILE__))).'/config.php');

/**
 * Return the id for the current question
 *
 * @return int The ID for the current question.
 */
function ipal_show_current_question() {
    global $DB;
    global $state;
    $ipal = $state;
    if ($DB->record_exists('ipal_active_questions', array('ipal_id' => $ipal->id))) {
        $question = $DB->get_record('ipal_active_questions', array('ipal_id' => $ipal->id));
        $questiontext = $DB->get_record('question', array('id' => $question->question_id));
        echo "The current question is -> ".strip_tags($questiontext->questiontext);
        return(1);
    } else {
        return(0);
    }
}
$ipalid = $_GET['ipalid'];
require_once('graphiframe.php');
?>
</body>
</html>