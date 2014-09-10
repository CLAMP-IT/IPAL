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
$compadreurl = 'http://www.compadre.org/ipal/';
$compadrexmlurl = $compadreurl.$_POST['xmlurl'];
$ch = curl_init();
curl_setopt($ch, CURLOPT_URL, $compadrexmlurl);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
$contents = curl_exec($ch);
curl_close($ch);
$categoryid = $_POST['categoryid'];
$lenxml = strlen($contents);// The is the length of the questions in XMLencode form.

$xmldata = $contents;// Thie is the questions in rawurldecode($questionXMLencode).

// The fields in the question, answer, and multichoice tables in Moodle 2.1 Revise if needed for other versions.
$questionfieldarray = array('category', 'parent', 'name', 'questiontext', 'questiontextformat', 'generalfeedback',
    'generalfeedbackformat', 'defaultgrade', 'penalty', 'qtype', 'length', 'stamp', 'version',
    'hidden', 'timecreated', 'timemodified', 'createdby', 'modifiedby');
$questionnotnullarray = array('name', 'questiontext', 'generalfeedback');
$answerfieldarray = array('answer', 'answerformat', 'fraction', 'feedback', 'feedbackformat');
$answernotnullarray = array('answer', 'feedback');
$multichoicefieldarray = array('question', 'layout', 'answers', 'single', 'shuffleanswers', 'correctfeedback',
    'correctfeedbackformat', 'partiallycorrectfeedback', 'partiallycorrectfeedbackformat',
    'incorrectfeedback', 'incorrectfeedbackformat', 'answernumbering');
$multichoicenotnullarray = array('correctfeedback', 'partiallycorrectfeedback', 'incorrectfeedback');
$hostname = 'unknownhost';
if (!empty($_SERVER['HTTP_HOST'])) {
    $hostname = $_SERVER['HTTP_HOST'];
} else if (!empty($_ENV['HTTP_HOST'])) {
    $hostname = $_ENV['HTTP_HOST'];
} else if (!empty($_SERVER['SERVER_NAME'])) {
    $hostname = $_SERVER['SERVER_NAME'];
} else if (!empty($_ENV['SERVER_NAME'])) {
    $hostname = $_ENV['SERVER_NAME'];
}

/**
 * Function to generate the random string required to identify questions.
 *
 * @param int $length The length of the string to be generated.
 * @return string The random string.
 */
function ipal_random_string($length=15) {
    $pool  = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ';
    $pool .= 'abcdefghijklmnopqrstuvwxyz';
    $pool .= '0123456789';
    $poollen = strlen($pool);
    mt_srand ((double) microtime() * 1000000);
    $string = '';
    for ($i = 0; $i < $length; $i++) {
        $string .= substr($pool, (mt_rand() % ($poollen)), 1);
    }
    return $string;
}

list($data, $qs) = moodlexmlquiz2array($xmldata);

// The questions start numbering with #1. data is the data for each question.
for ($k = 1; $k <= count($data); $k++) {

    if (isset($data[$k]->name)) {
        $questioninsert = new stdClass();
        $date = gmdate("ymdHis");
        $stamp = $hostname .'+'. $date .'+'.ipal_random_string(6);
        $data[$k]->version = $data[$k]->hashtext;

        foreach ($data[$k] as $qkey => $qvalue) {
            $q = trim($qkey);
            if (in_array($q, $questionfieldarray)) {
                $questioninsert->$q = strval($qvalue);
            }

        }

        $questioninsert->category = $categoryid;
        $questioninsert->parent = 0;
        $questioninsert->createdby = $USER->id;
        $questioninsert->modifiedby = $USER->id;
        $questioninsert->timecreated = time();
        $questioninsert->timemodified = time();
        $questioninsert->stamp = $stamp;

        if (strlen($questioninsert->qtype) > 0) {
            foreach ($questionnotnullarray as $ky => $vle) {
                if (!(strlen($questioninsert->$vle) > 0)) {
                    $questioninsert->$vle = "\n<br />";
                }
            }
            $lastinsertid = $DB->insert_record('question', $questioninsert);
            $questionidlist[$k] = $lastinsertid;

        } else {
            echo "\n<br />Error: There must be a qtype (question type).";
        }

        $qtypeinsert = new stdClass();
        for ($a = 1; $a <= count($qs[$k]); $a++) {
            $answerinsert = new stdClass();
            foreach ($qs[$k][$a] as $akey => $avalue) {
                $ak = trim($akey);
                if (in_array($ak, $answerfieldarray)) {
                    $answerinsert->$ak = $avalue;
                }
            }
            $answerinsert->question = $lastinsertid;
            foreach ($answernotnullarray as $aky => $avle) {
                if (!(strlen($answerinsert->$avle) > 0)) {
                    $answerinsert->$avle = "\n<br/>";
                }
            }
            $answerinsert->fraction = ($answerinsert->fraction) / 100;// The values from ComPADRE are percentages, not fractions.
            $lastanswerinsertid[$a] = $DB->insert_record('question_answers', $answerinsert);

            if ($questioninsert->qtype == 'truefalse') {
                if ($answerinsert->fraction > 0) {
                    $qtypeinsert->trueanswer = $lastanswerinsertid[$a];
                } else {
                    $qtypeinsert->falseanswer = $lastanswerinsertid[$a];
                }

            }
        }
        if ($questioninsert->qtype == 'truefalse') {
            $qtypeinsert->question = $lastinsertid;
        }
        if ($questioninsert->qtype == 'multichoice') {
            $qtypeinsert->answers = join(',', $lastanswerinsertid);
            foreach ($multichoicenotnullarray as $ky => $vle) {
                $qtypeinsert->$vle = "\n<br />";
            }
            $qtypeinsert->single = 1;
            $qtypeinsert->correctfeedbackformat = 0;
            $qtypeinsert->partiallycorrectfeedbackformat = 0;
            $qtypeinsert->incorrectfeedbackformat = 0;
            foreach ($data[$k] as $key => $value) {
                $ky = trim($key);
                if ($ky == 'single') {
                    $value = 1;
                }
                if ($ky == 'shuffleanswers') {
                    $value = 0;
                }
                if (in_array($ky, $multichoicefieldarray)) {
                    $qtypeinsert->$ky = $value;
                }
            }

        }
        if ($questioninsert->qtype == 'truefalse') {
            $qtypetable = 'question_'.$questioninsert->qtype;
            $qtypeinsertid = $DB->insert_record($qtypetable, $qtypeinsert);
        }
        if ($questioninsert->qtype == 'multichoice') {
            if ($CFG->version > '2013111799') {
                $qtypetable = 'qtype_'.$questioninsert->qtype."_options";
                $qtypeinsert->questionid = $lastinsertid;
            } else {
                $qtypetable = 'question_'.$questioninsert->qtype;
                $qtypeinsert->question = $lastinsertid;
            }
            $qtypeinsertid = $DB->insert_record($qtypetable, $qtypeinsert);
        }

    }
}

        $priorqids = $ipal->questions;
        $addedqids = implode(",", $questionidlist);

        $ipal->questions = $addedqids.','.$priorqids;

        $DB->set_field('ipal', 'questions', $ipal->questions, array('id' => $ipal->id));