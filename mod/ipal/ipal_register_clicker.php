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
 * Page to register RF clickers used by students enrolled in the course.
 *
 * @package    mod_ipal
 * @copyright 2011 Eckerd College 
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
?>
<html>
<head>
</head>
<body>
<?php
require_once('../../config.php');
$cmid = $_GET['cmid'];
$ipalid = $_GET['ipal_id'];

if (isset($_POST['SubmitButton']) && ($_POST['SubmitButton'] == 'Register Clicker')&& isset($_POST['clicker_type'])
        && isset($_POST['device_id'])) {
    $clickertype = $_POST['clicker_type'];
    $deviceid = $_POST['device_id'];
    if (strlen($deviceid) == 0) {
        echo "You have to enter a device id. Please use the back button.";
        exit;
    }
    $ipal = $DB->get_record('ipal', array('id' => $ipalid));
    echo "Course is ".$ipal->course;
    echo " User id is ".$USER->id;

    /*
     * We will delete any record with this cliker in this course.
     * If it is already registered to this user, it will be added in later.
     * If this person has a second clicker, we don't want this person to get credit if someone else is using the second clicker
     */
    $deletingduplicates = $DB->delete_records('ipal_mobile', array('device_code' => $deviceid,
        'clicker_type' => $_POST['clicker_type'], 'course_id' => $ipal->course));

    if ($mobile = $DB->get_record('ipal_mobile', array('user_id' => $USER->id, 'course_id' => $ipal->course))) {
        $record = new Stdclass();
         $record->id = $mobile->id;
         $record->device_code = $deviceid;
         $record->clicker_type = $_POST['clicker_type'];
         $updated = $DB->update_record('ipal_mobile', $record);
        if ($updated) {
            echo "\n<br />you have successfully updated the ".$_POST['clicker_type']." clicker with ID = $deviceid";
        } else {
            echo "\n<br />There was a problem registering your clicker. Use the back button and try again or ";
        }
    } else {
        $record = new Stdclass();
        $record->course_id = $ipal->course;
        $record->user_id = $USER->id;
        $record->device_code = $deviceid;
        $record->clicker_type = $_POST['clicker_type'];
        $lastinsertid = $DB->insert_record('ipal_mobile', $record);
        if ($lastinsertid) {
            echo "\n<br />You have successfully registered your ".$_POST['clicker_type']." clicker with ID = $deviceid";
        } else {
            echo "\n<br />There was a problem registering your clicker. Use the back button and try again or ";
        }
    }
    echo "\n<br />Click <a href='../../course/view.php?id=".$ipal->course."'>here</a> to return to the course.";
    echo "\n</body></html>";
    exit;
}
echo "<form method='post'>";
echo "The ID of your clicker <input type='text' name='device_id'>";
echo "\n<br />Which type of clicker? <select name='clicker_type'>";
echo "\n<option></option>";
echo "\n<option name='ResponseCard'>ResponseCard</option>";
echo "\n</select>";
echo "\n<br /><br /><input type='submit' name='SubmitButton' value='Register Clicker'.";

?>
</body>
</html>
