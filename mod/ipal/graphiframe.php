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
 * This script displays the iframe on the IPAL teacher view and can only be used within that script.
 *
 * @package    mod_ipal
 * @copyright  2012 W. F. Junkin, Eckerd College, http://www.eckerd.edu
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
$state = $DB->get_record('ipal', array('id' => $ipalid));
if ($state->preferredbehaviour == "Graph") {
    echo "<br>";
    echo "<br>";
    if (ipal_show_current_question() == 1) {
        echo "<iframe id= \"graphIframe\" src=\"graphics.php?ipalid=".$ipalid."\" height=\"535\" width=\"723\"></iframe>";
    }
} else {
    echo "<iframe id= \"graphIframe\" src=\"gridview.php?id=".$ipalid."\" height=\"535\" width=\"723\"></iframe>";
}