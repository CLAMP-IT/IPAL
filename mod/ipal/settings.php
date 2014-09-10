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
 * Use this file to configure the IPAL module
 *
 * This sets the ipal_analytics
 * An analysis of the IPAL student responses may be able to identify at-risk students and assist in early intervention.
 * If this is selected the student polling data, identified only by the Moodle student userID, will be sent to
 * the ComPADRE site so that an analysis of the data can be done.
 * This analysis can only be used by authorized people at your institution to identify the usr names or actual names of any student.
 * and these authorized people can only view the results of the analysis for their own school.
 * If this is not selected, no data is sent to the ComPADRE site.
 *
 * @package    mod_ipal
 * @copyright  2013 Bill Junkin
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die;

$name = new lang_string('ipal_analytics', 'mod_ipal');
$description = new lang_string('ipal_analytics_help', 'mod_ipal');
$settings->add(new admin_setting_configcheckbox('ipal_analytics',
                                                $name,
                                                $description,
                                                1));
