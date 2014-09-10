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
 * The main ipal configuration form
 *
 * It uses the standard core Moodle formslib. For more info about them, please
 * visit: http://docs.moodle.org/en/Development:lib/formslib.php
 *
 * @package   mod_ipal
 * @copyright 2011 W. F. Junkin, Eckerd College (http://www.eckerd.edu)
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

require_once($CFG->dirroot.'/course/moodleform_mod.php');
require_once($CFG->dirroot.'/mod/ipal/locallib.php');

/**
 * This class is used to display the form that is used when a new IPAL instance is created.
 *
 * @copyright 2011 W. F. Junkin, Eckerd College (http://www.eckerd.edu)
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class mod_ipal_mod_form extends moodleform_mod {

    /**
     * The function to define the elements of the form.
     */
    protected function definition() {

        global $COURSE;
        global $DB;
        $mform =& $this->_form;

        // ...============================================================================.
        // Adding the "general" fieldset, where all the common settings are showed.
        $mform->addElement('header', 'general', get_string('general', 'form'));

        // Adding the standard "name" field.
        $mform->addElement('text', 'name', get_string('ipalname', 'ipal'), array('size' => '64'));
        if (!empty($CFG->formatstringstriptags)) {
            $mform->setType('name', PARAM_TEXT);
        } else {
            $mform->setType('name', PARAM_CLEAN);
        }
        $mform->addRule('name', null, 'required', null, 'client');
        $mform->addRule('name', get_string('maximumchars', '', 255), 'maxlength', 255, 'client');
        $mform->addHelpButton('name', 'ipalname', 'ipal');

        // Add options to choose "anonymous" and "non-anonymous".
        $mform->addElement('select', 'anonymous', get_string('ipaltype', 'ipal'), array(0 => 'non-Anonymous', 1 => 'Anonymous'));
        $mform->addHelpButton('anonymous', 'ipaltype', 'ipal');
        // Disable the IPAL Type Select as long as there is an answer in the IPAL instance.
        $cmid = optional_param('update', 0, PARAM_INT);
        if ($cmid) {
            $cm = $DB->get_record('course_modules', array('id' => $cmid), '*', MUST_EXIST);
            if (ipal_check_answered($cm->instance)) {
                $mform->disabledIf('anonymous', 'name');
            }
        }
        // Add options to enable mobile (app or clicker) polling.
        $mform->addElement('select', 'mobile', get_string('ipalmobile', 'ipal'),
            array(0 => 'No', 1 => 'Mobile App', 2 => 'Clickers', 3 => 'Both'));
        $mform->addHelpButton('mobile', 'ipalmobile', 'ipal');

        // Adding the standard "intro" and "introformat" fields.
        $this->add_intro_editor();
        // This is to fix a few weird insert issues.
        $mform->addElement('hidden', 'questions', 'required', null, 'client');
        $mform->setType('questions', PARAM_RAW);
        $mform->addElement('hidden', 'preferredbehaviour', 'deferredfeedback');
        $mform->setType('preferredbehaviour', PARAM_RAW);

        // ...==========================================================================.
        // Adding the rest of ipal settings, spreeading all them into this fieldset.
        // Or adding more fieldsets ('header' elements) if needed for better logic.
        // ...===========================================================================.
        // Add standard elements, common to all modules.
        $this->standard_coursemodule_elements();
        // ...============================================================================.
        // Add standard buttons, common to all modules.
        $this->add_action_buttons();

    }
}