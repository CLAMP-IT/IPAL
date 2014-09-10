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
 * Defines the Moodle forum used to add random questions to the quiz.
 *
 * @package    mod_ipal
 * @copyright  2012 W. F. Junkin, Eckerd College, http://www.eckerd.edu
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */


require_once($CFG->dirroot . '/mod/ipal/backup/moodle2/backup_ipal_stepslib.php');
require_once($CFG->dirroot . '/mod/ipal/backup/moodle2/backup_ipal_settingslib.php');

/**
 * This class has functions to start the backup process.
 * @copyright  2012 W. F. Junkin, Eckerd College, http://www.eckerd.edu
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class backup_ipal_activity_task extends backup_activity_task {

    /**
     * This is a function that is required but does nothing.
     */
    protected function define_my_settings() {
    }

    /**
     * This function is an intermediate function to obtain the ipal structure from the xml code.
     */
    protected function define_my_steps() {
        $this->add_step(new backup_ipal_activity_structure_step('ipal_structure', 'ipal.xml'));
    }

    /**
     * This function allows other code to obtain the content with encoded links.
     * @param string $content
     */
    static public function encode_content_links($content) {
        return $content;
    }
}
