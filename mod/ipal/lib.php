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
 * Library of interface functions and constants for module ipal
 *
 * All the core Moodle functions, neeeded to allow the module to work
 * integrated in Moodle should be placed here.
 * All the ipal specific functions, needed to implement all the module
 * logic, should go to locallib.php. This will help to save some memory when
 * Moodle is performing actions across all modules.
 *
 * @package mod_ipal
 * @copyright 2011 W. F. Junkin Eckerd College (http://www.eckerd.edu)
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

/**
 * Function to indicate the supported features.
 *
 * @param string $feature
 * @return mixed
 */
function ipal_supports($feature) {
    switch($feature) {
        case FEATURE_BACKUP_MOODLE2:
            return true;
        default:
            return null;
    }
}
/**
 * Given an object containing all the necessary data,
 * (defined by the form in mod_form.php) this function
 * will create a new instance and return the id number
 * of the new instance.
 *
 * @param object $ipal An object from the form in mod_form.php
 * @return int The id of the newly inserted ipal record
 */
function ipal_add_instance($ipal) {
    global $DB;

    $ipal->timecreated = time();
    return $DB->insert_record('ipal', $ipal);
}

/**
 * Given an object containing all the necessary data,
 * (defined by the form in mod_form.php) this function
 * will update an existing instance with new data.
 *
 * @param object $ipal An object from the form in mod_form.php
 * @return boolean Success/Fail
 */
function ipal_update_instance($ipal) {
    global $DB;

    $ipal->timemodified = time();
    $ipal->id = $ipal->instance;
    return $DB->update_record('ipal', $ipal);
}

/**
 * Given an ID of an instance of this module,
 * this function will permanently delete the instance
 * and any data that depends on it.
 *
 * @param int $id Id of the module instance
 * @return boolean Success/Failure
 */
function ipal_delete_instance($id) {
    global $DB;

    if (! $ipal = $DB->get_record('ipal', array('id' => $id))) {
        return false;
    }
    $DB->delete_records('ipal', array('id' => $ipal->id));

    return true;
}

/**
 * Return a small object with summary information about what a
 * user has done with a given particular instance of this module
 *
 * Used for user activity reports.
 * $return->time = the time they did it
 * $return->info = a short text description
 *
 * @param stdClass $course info about the course.
 * @param stdClass $user INfo about the user
 * @param stdClass $mod INfo that this mod is IPAL
 * @param stdClass $ipal Info about this IPAL instance.
 * @return stdClass
 */
function ipal_user_outline($course, $user, $mod, $ipal) {
    $return = new stdClass;
    $return->time = 0;
    $return->info = '';
    return $return;
}

/**
 * Print a detailed representation of what a user has done with
 * a given particular instance of this module, for user activity reports.
 *
 * @param stdClass $course
 * @param stdClass $user Info about the user
 * @param stdClass $mod Info that this is the ipal mod
 * @param stdClass $ipal Info about this ipal instance
 * @return boolean
 * @todo Finish documenting this function
 */
function ipal_user_complete($course, $user, $mod, $ipal) {
    return true;
}

/**
 * Given a course and a time, this module should find recent activity
 * that has occurred in ipal activities and print it out.
 * Return true if there was output, or false is there was none.
 *
 * @param stdClass $course Info about the course
 * @param string $viewfullnames Full names.
 * @param time $timestart The time to start the report.
 * @return boolean
 */
function ipal_print_recent_activity($course, $viewfullnames, $timestart) {
    return false;  // True if anything was printed, otherwise false.
}

/**
 * Function to be run periodically according to the moodle cron
 * This function searches for things that need to be done, such
 * as sending out mail, toggling flags etc ...
 *
 * @return boolean
 */
function ipal_cron() {
    mtrace( "Running ipal Cron..." );
    ipal_transfer_archive();
    mtrace( "Ipal Done...." );
    return true;
}

/**
 * Function to prepare and then post to ComPADRE the polling data
 *
 * The posting is done using ipal_post_xml
 * The answers are marked as sent using ipal_update_code
 */
function ipal_transfer_archive() {
    global $DB;
    global $CFG;

    if ($DB->record_exists('ipal_answered_archive', array('sent' => '1'))) {
        $drecords = $DB->get_records('ipal_answered_archive', array('sent' => '1'));

        $version = $DB->get_record('modules', array('name' => 'ipal'));
        $xmlheader = "<?xml version=\"1.0\" standalone=\"yes\"?>\n<ipal>\n<head>\n";
        $xmlheader .= "<host>".$CFG->wwwroot."</host>\n";
        $xmlheader .= "<moodle_version>".$CFG->release."</moodle_version>\n";
        $xmlheader .= "<ipal_version>".$version->version."</ipal_version>\n";
        $xmlheader .= "<rosters>\n";
        $question = '';
        foreach ($drecords as $record) {
            $ids[] = $record->id;
            $question .= "<question>\n";
            $qversion = $DB->get_record('question', array('id' => $record->question_id));
            $question .= "<version>".$qversion->version."</version>\n";
            $question .= "<archive_id>".$record->id."</archive_id>\n";
            $question .= "<user_id>".$record->user_id."</user_id>\n";
            $question .= "<question_id>".$record->question_id."</question_id>\n";
            $question .= "<ipal_id>".$record->quiz_id."</ipal_id>\n";
            $question .= "<answer_id>".$record->answer_id."</answer_id>\n";
            $aversion = $DB->get_record('question_answers', array('id' => $record->answer_id));
            $question .= "<answer_hash>".md5($aversion->answer)."</answer_hash>\n";
            $question .= "<class_id>".$record->class_id."</class_id>\n";
            $courseroster[$record->class_id] = 1;
            $courseinstructor[$record->class_id] = $record->instructor;
            $courseshortname[$record->class_id] = $record->shortname;
            $question .= "<ipal_code>".$record->ipal_code."</ipal_code>\n";
            if ($record->answer_id == "-1") {
                $question .= "<a_text><![CDATA[".$record->a_text."]]></a_text>\n";
            } else {
                $question .= "<a_text><![CDATA[]]></a_text>\n";
            }
            $question .= "<time_created>".$record->time_created."</time_created>\n";
            $question .= "</question>\n";
        }
        // Preparing the roster for the courses.
        $studentrole = $DB->get_record('role', array('shortname' => 'student'));
        $studentroleid = $studentrole->id;

        foreach ($courseroster as $key => $value) {
            $courseid = $key;
            $query = "SELECT ra.userid FROM {role_assignments} ra, {context} cx
                WHERE ra.contextid = cx.id AND cx.instanceid = $courseid AND ra.roleid = $studentroleid AND cx.contextlevel = 50";
            $studentids = $DB->get_records_sql($query);
            foreach ($studentids as $key => $value) {
                $studentid[] = $value->userid;
            }
            $xmlheader .= "<roster classid=\"$courseid\" instructor=\"".$courseinstructor[$courseid].'" shortname="'.
                $courseshortname[$courseid].'">'.join($studentid, ',')."</roster>\n";
            unset($studentid);
        }
        $xmlheader .= "</rosters>\n";// Note: This information will be sent before the question information.
        $xmlheader .= "<local_date>".date('U')."</local_date>\n</head>\n";
        $footer = "</ipal>\n";
        if (ipal_post_xml($xmlheader.$question.$footer) == "1") {
            ipal_update_code($ids);
        }
    }
}

/**
 * This function sets the fields in the database to indicate that the results have been sent to ComPADRE.
 *
 * @param array $ids The ids of the answers that have been sent.
 */
function ipal_update_code($ids) {
    global $DB;
    foreach ($ids as $id) {
        $result = $DB->set_field('ipal_answered_archive', 'sent', '2', array('id' => $id));
        mtrace(".");
    }
}

/**
 * Send sanitized polling data to ComPADRE.
 * 
 * @param string $xml The xml file of the data
 */
function ipal_post_xml($xml) {
    $postdata = array('IPALXMLData' => $xml);
    $streamoptions = array('http' => array('method' => 'POST', 'header' => 'Content-type: application/x-www-form-urlencoded'.
        "\r\n", 'content' => http_build_query($postdata)));
    $context = stream_context_create($streamoptions);
    $response = file_get_contents("http://www.compadre.org/ipal/data/save.cfm", null, $context);
    mtrace("Uploading to Compadre...");
    return($response);
}

/**
 * Returns a participant array but is meaningless in IPAL.
 *
 * Must return an array of users who are participants for a given instance
 * of ipal. Must include every user involved in the instance,
 * independient of his role (student, teacher, admin...). The returned
 * objects must contain at least id property.
 * See other modules as example.
 *
 * @param int $ipalid ID of an instance of this module
 * @return boolean|array false if no participants, array of objects otherwise
 */
function ipal_get_participants($ipalid) {
    return false;
}

/**
 * This function returns if a scale is being used by one ipal
 * if it has support for grading and scales. Commented code should be
 * modified if necessary. See forum, glossary or journal modules
 * as reference.
 *
 * @param int $ipalid ID of an instance of this module
 * @param int $scaleid ID of a scale (scales are not used in IPAL)
 * @return bool
 */
function ipal_scale_used($ipalid, $scaleid) {
    global $DB;

    $return = false;

    return $return;
}

/**
 * Checks if scale is being used by any instance of ipal.
 * This function was added in 1.9
 *
 * This is used to find out if scale used anywhere
 * @param int $scaleid
 * @return boolean True if the scale is used by any ipal
 */
function ipal_scale_used_anywhere($scaleid) {
    global $DB;

    if ($scaleid and $DB->record_exists('ipal', array('grade' => - $scaleid))) {
        return true;
    } else {
        return false;
    }
}

/**
 * Function to correct for changes from Moodle 2.2 to later versions.
 *
 * Since the /lib/questionlib.php script doesn't get the file name correct for the function 
 * (line about 1829) for Moodle 2.2 (and possibly other versions) this function corrects 
 * for having mod_ in front of the function name.
 * @param stdClass $course course settings object
 * @param stdClass $context context object
 * @param string $component the name of the component we are serving files for.
 * @param string $filearea the name of the file area.
 * @param int $qubaid the attempt usage id.
 * @param int $slot the id of a question in this quiz attempt.
 * @param array $args the remaining bits of the file path.
 * @param bool $forcedownload whether the user must be forced to download the file.
 * @param array $options additional options affecting the file serving
 */
function mod_ipal_question_pluginfile($course, $context, $component, $filearea, $qubaid, $slot,
        $args, $forcedownload, array $options=array()) {
    ipal_question_pluginfile($course, $context, $component, $filearea, $qubaid, $slot, $args, $forcedownload);
}


/**
 * Called via pluginfile.php -> question_pluginfile to serve files belonging to
 * a question in a question_attempt when that attempt is a quiz attempt.
 *
 * This function was taken straight from mod_quiz, Moodle verson 2.3 
 * It ws added to /ipal/lib.php to enable images to be used in question text 
 * @param stdClass $course course settings object
 * @param stdClass $context context object
 * @param string $component the name of the component we are serving files for.
 * @param string $filearea the name of the file area.
 * @param int $qubaid the attempt usage id.
 * @param int $slot the id of a question in this quiz attempt.
 * @param array $args the remaining bits of the file path.
 * @param bool $forcedownload whether the user must be forced to download the file.
 * @param array $options additional options affecting the file serving
 * @return bool false if file not found, does not return if found - justsend the file
 */
function ipal_question_pluginfile($course, $context, $component,
        $filearea, $qubaid, $slot, $args, $forcedownload, array $options=array()) {
    global $CFG;

    $fs = get_file_storage();
    $relativepath = implode('/', $args);
    $fullpath = "/$context->id/$component/$filearea/$relativepath";
    if (!$file = $fs->get_file_by_hash(sha1($fullpath)) or $file->is_directory()) {
        send_file_not_found();
    }

    send_stored_file($file, 0, 0, $forcedownload, $options);
}

/**
 * Execute post-uninstall custom actions for the module
 * This function was added in 1.9
 *
 * @return boolean true if success, false on error
 */
function ipal_uninstall() {
    return true;
}
