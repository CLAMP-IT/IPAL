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
 * Provides utilities and interface for transferring questions from ComPADRE.
 *
 * @package    mod_ipal
 * @copyright  2012 W. F. Junkin, Eckerd College, http://www.eckerd.edu
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

if (!isset($module->version)) {
    $module = new stdClass;
    require($CFG->dirroot . '/mod/ipal/version.php');// Getting IPAL version to send to ComPADRE.
}
$ipalversion = $module->version;
$compadreurl = 'http://www.compadre.org/ipal/index.cfm?ipalversion='.$ipalversion;
// The compadreurl will need to be put in here or have this obtained from somewhere in the moodle code.
echo "<form method='POST' action='$compadreurl'>";
echo "\n<input type='hidden' name='email' value='".$USER->email."'>";
echo "\n<input type='hidden' name='emailhash' value='".sha1($USER->email)."'>";
echo "\n<input type='hidden' name='firstName' value='".$USER->firstname."'>";
echo "\n<input type='hidden' name='lastName' value='".$USER->lastname."'>";
echo "\n<input type='hidden' name='courseName' value='".$course->shortname."'>";
echo "\n<input type='hidden' name='moodleURL' value='".$CFG->wwwroot."'>";
echo "\n<input type='hidden' name='cmid' value='".$quiz->cmid."'>";
echo "\n<input type='hidden' name='sessKeyHash' value='We will supply this'>";
echo "\n<input type='submit' name='submit' value='Get questions from ComPADRE'>";
echo "\n</form>";
// The return url will be $_POST['moodleURL'].'/ipal/compadre_questions.php?cmid='.$_POST['cmid'].
// Compadre will return the sessKeyValue back to the Moodle site.