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
 * English strings for ipal
 *
 * You can have a rather longer description of the file as well,
 * if you like, and it can span multiple lines.
 *
 * @package   mod_ipal
 * @copyright 2010 Your Name
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

$string['anonymousmess'] = 'Your answer is recorded anonymously';
$string['basicideasofipal'] = 'The basic ideas of selecting questions for in-class polling';
$string['editingipal'] = 'Adding (selecting) ipal questions';
$string['editingipal_ex'] = 'Editing ipal: {$a}';
$string['editingipal_help'] = 'When selecting questions for ipal, the main concepts are:

* Select questions you might want to use from the questions bank, from ComPADRE, or create new questions.
* The order of questions is not important since you can send them in any order.
* When polling you can send the same question more than once.';
$string['eventeditpageviewed'] = 'Ipal edit page viewed';
$string['ipal'] = 'ipal';
$string['ipalfieldset'] = 'Custom example fieldset';
$string['ipalmobile'] = 'Enable clickers';
$string['ipalmobile_error'] = 'You must choose between No and Yes';
$string['ipalmobile_help'] = 'Choose Yes to allow students to respond using clickers. This displays the IPAL access code for the teacher on the IPAL polling page. ';
$string['ipalname'] = 'IPAL Name';
$string['ipalname_help'] = 'This is the content of the help tooltip associated with the ipalname field. Markdown syntax is supported.';
$string['ipaltype'] = 'IPAL Type';
$string['ipaltype_error'] = 'You must choose between anonymous and non-anonymous';
$string['ipaltype_help'] = 'Choose to record student ID in spreadsheet with answers (non-anonymous) or not. Ipal type cannot be modified after an answer is recorded. ';
$string['ipal:addinstance'] = 'Add a new ipal';
$string['ipal:manage'] = 'Manage ipals';
$string['ipal:preview'] = 'Preview ipals';
$string['ipalsettings'] = 'ipal settings';
$string['ipal:view'] = 'View ipal information';
$string['ipal:viewreports'] = 'View ipal reports';
$string['modulename'] = 'ipal';
$string['modulenameplural'] = 'ipals';
$string['modulename_help'] = 'The ipal (In-class Polling for All Learners) activity module enables a teacher to design and set polling sessions consisting of multiple choice, true-false, and essay question types. IPAL questions may be used in a quiz and vice versa. Ready to use questions may also be obtained from the ComPADRE database.

The dynamic graphs displayed below the questions can be toggled from spreadsheet view (including student names) to an anonymous display of the answers for the active question -- a histogram for multiple choice questions and a one-line display for essay questions. While polling is on, a question may be answered multiple times until a second question is sent or polling is turned off.

A question may be sent more than once. The spreadsheet view displays the most recent answer submitted at any time.

Polling may be used
* To increase student involvement and participation
* To gauge student understanding and guide the pace of the class.
* To promote greater verbal classroom discussion.

<a href="http://www.compadre.org/ipal/webdocs/Guide.cfm">more</a>';
$string['nonanonymousmess'] = '';
$string['pluginadministration'] = 'ipal administration';
$string['pluginname'] = 'ipal';
$string['editingipal'] = 'Adding (selecting) ipal questions';
$string['editingipal_help'] = 'When selecting questions for ipal, the main concepts are:

* Select questions you might want to use from the questions bank, from ComPADRE, or create new questions.
* The order of questions is not important since you can send them in any order.
* When polling you can send the same question more than once.';
$string['basicideasofipal'] = 'The basic ideas of selecting questions for in-class polling';

$string['ipal_analytics'] = 'improve analytics';
$string['ipal_analytics_help'] = 'If this is selected the student polling data, identified only by the Moodle student userID, will be sent to
     the ComPADRE site so that an analysis of the data can be done.
     Only authorized people at your institution can access the data and identify the user names or actual names of any student.
     and these authorized people can only view the results of the analysis for their own school.
     <br />If this is not selected, no data is sent to the ComPADRE site.';