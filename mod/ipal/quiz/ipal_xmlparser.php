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

/**
 * Function to change the xml file for questions with answers to an array.
 *
 * Structure of the XML file:
 * Level 0 = quiz
 * Level 1 = question attribute type
 *    Level 2 = name
 *      Level 3 = text (content)
 *    Level 2 = questiontext attribute format
 *        Level 3 = text (content)
 *    Level 2 = several types of feedback each with attribute format
 *        Level 3 = text (content)
 *    Level 2 = defaultgrade,penalty,hidden, single, shuffleanswers,answernumbering, each with (content)
 *    Level 2 = answer attribute fraction and format
 *        Level 3 = text (content)
 *        Level 3 = feedback attribute format
 *            Level 4 = text (content)
 *    Level 2 = answer (repeat)
 * There is never a value when there are children. Each value takes the key of that level unless the key is text, and then
 *  the key comes from the level above. The keys for the attributes need to be modified as follows:
 * If the key is format the key needs to be changed to add in the key from that level and if the value is html, the value
 * needs to be changed to 1 otherwise zero. If the attribute is fraction, the value needs to be divided by 100
 * If an attribute of a question is type, it needs to be changed to qtype
 * @param string $xml The xml file
 * @return array The question data and answers in an array format.
 */
function moodlexmlquiz2array($xml) {
    global $debug;
    if ($debug) {
        echo "\n<br />Starting MoodleXMLQuizArray";
    }
    $level = 0;
    $elem[0] = new SimpleXMLElement($xml);

    // This is level 0.
    $label[$level] = $elem[$level]->getName();
    if (!($label[$level] == 'quiz')) {
        echo "\n<br />This document is not a valid Moodle XML set of questions.";
        return;
    }
    $numchildren[$level] = count($elem[$level]->children());// This is the number of questions.
    $n = 0;// This indexes the questions.
    foreach ($elem[0] as $elem[1]) {
        // This is level 1. These should be questions.
        $level = 1;
        if ($debug) {
            echo "\n<br />debug98 This is starting level $level";
        }// Level 1.
        $label[$level] = $elem[$level]->getName();
        if (!($label[$level] == 'question')) {
            echo "\n<br />This document has an entry that is not a valid Moodle XML question.";
            return;
        }
        $n++;// The index for the first question is 1.
        $qdata[$n] = new stdClass();
        if ($label[$level] == 'text') {
            $label[$level] = $label[$level - 1];
        }
        $qdata[$n]->$label[$level] = trim((string) $elem[$level]);
        if ($debug) {
            echo "\n<br />at 104 qdata[$n][".$label[$level]."] = ".$qdata[$n]->$label[$level];
        }
        $numchildren[$level] = count($elem[$level]);
        foreach ($elem[$level]->attributes() as $key => $value) {
            if ($key == 'type') {
                $key = 'qtype';
            }
            if ($key == 'format') {
                $key = $label[$level].'format';
                if ($value == 'html') {
                    $value = 0;
                } else {
                    $value = 1;
                }
            }

            $qdata[$n]->$key = $value;
            if ($debug) {
                echo "\n<br />at 113 attribute qdata[$n][".$key."] = ".$qdata[$n]->$key;
            }
        }

        // Starting level 2.
        $adata[0][0] = '';// Answers start at one. This eliminates the lack of answers and the warning this produces.
        $na = 0;// The index for the answers.
        if ($numchildren[$level] > 0) {
            foreach ($elem[1] as $elem[2]) {// This is level 2. These might be questions or answers.
                $level = 2;
                if ($debug) {
                    echo "\n<br />This is level 2";
                }// Level 2.
                $label[$level] = $elem[$level]->getName();
                if ($label[$level] == 'text') {
                    $label[$level]  = $label[$level - 1];
                }// Text labels take their labels from one level up.

                if ($label[$level] == 'answer') {
                    $na++;// The first answer has na=1.
                    $adata[$n][$na] = new stdClass();
                    $adata[$n][$na]->$label[$level] = trim((string) $elem[$level]);
                } else {
                    $qdata[$n]->$label[$level] = trim((string) $elem[$level]);
                    if ($debug) {
                        echo "\n<br />at 133 qdata[$n][".$label[$level]."] = ".$qdata[$n]->$label[$level];
                    }
                }
                $numchildren[$level] = count($elem[$level]);
                foreach ($elem[$level]->attributes() as $key => $value) {
                    if ($key == 'type') {
                        $key = 'qtype';
                    }
                    if ($key == 'format') {
                        $key = $label[$level].'format';
                        if ($value == 'html') {
                            $value = 0;
                        } else {
                            $value = 1;
                        }
                    }
                    if ($label[2] == 'answer') {
                        $adata[$n][$na]->$key = $value;
                    } else {
                        $qdata[$n]->$key = $value;
                        if ($debug) {
                            echo "\n<br />at 147 qdata[$n][".$key."] = ".$qdata[$n]->$key;
                        }
                    }
                }
                // Starting level 3.
                if ($numchildren[$level] > 0) {
                    foreach ($elem[2] as $elem[3]) {// This is level 3.
                        $level = 3;
                        if ($debug) {
                            echo "\n<br />Starting level 3";
                        }// Level 3.
                        $label[$level] = $elem[$level]->getName();
                        if ($label[$level] == 'text') {
                            $label[$level]  = $label[$level - 1];
                        }// Text labels take their labels from one level up.

                        if ($label[2] == 'answer') {
                            $adata[$n][$na]->$label[$level] = trim((string) $elem[$level]);
                        } else {
                            $qdata[$n]->$label[$level] = trim((string) $elem[$level]);
                            if ($debug) {
                                echo "\n<br />at 164 qdata[$n][".$label[$level]."] = ".$qdata[$n]->$label[$level];
                            }
                        }
                        $numchildren[$level] = count($elem[$level]);

                        foreach ($elem[$level]->attributes() as $key => $value) {
                            if ($key == 'type') {
                                $key = 'qtype';
                            }
                            if ($key == 'format') {
                                $key = $label[$level].'format';
                                if ($value == 'html') {
                                    $value = 0;
                                } else {
                                    $value = 1;
                                }
                            }
                            if ($label[2] == 'answer') {
                                $adata[$n][$na]->$key = $value;
                            } else {
                                $qdata[$n]->$key = $value;
                                if ($debug) {
                                    echo "\n<br />at 180 attribute qdata[$n][".$key."] = ".$qdata[$n]->$key;
                                }
                            }

                        }
                        // Starting level 4.
                        if ($numchildren[$level] > 0) {
                            foreach ($elem[3] as $elem[4]) {// This is level 4.
                                $level = 4;
                                if ($debug) {
                                    echo "\n<br />starting level 4";
                                }// Level 4.
                                $label[$level] = $elem[$level]->getName();
                                if ($label[$level] == 'text') {
                                    $label[$level]  = $label[$level - 1];
                                }// Text labels take their labels from one level up.
                                $numchildren[$level] = count($elem[$level]);
                                if ($debug) {
                                    echo "\n<br />debug191 the various labels are 1:";
                                    echo $label[1]."->2:".$label[2]."->3:".$label[3]."->4:".$label[4];
                                }

                                if ($label[2] == 'answer') {
                                    $adata[$n][$na]->$label[$level] = trim((string) $elem[$level]);
                                } else {
                                    $qdata[$n]->$label[$level] = trim((string) $elem[$level]);
                                    if ($debug) {
                                        echo "\n<br />at 198 qdata[$n][".$label[$level]."] = ".$qdata[$n]->$label[$level];
                                    }
                                }

                                foreach ($elem[$level]->attributes() as $key => $value) {
                                    if ($key == 'type') {
                                        $key = 'qtype';
                                    }
                                    if ($key == 'format') {
                                        $key = $label[$level].'format';
                                        if ($value == 'html') {
                                            $value = 0;
                                        } else {
                                            $value = 1;
                                        }
                                    }
                                    if ($label[2] == 'answer') {
                                        $adata[$n][$na]->$key = $value;
                                        if ($debug) {
                                            echo "\n<br />debug207 and answer $n, $na (".$key.") is ".$value;
                                        }
                                    } else {
                                        $qdata[$n]->$key = $value;
                                        if ($debug) {
                                            echo "\n<br />at 212 attribute qdata[$n][".$key."] = ".$qdata[$n]->$key;
                                        }
                                    }
                                }
                            }
                        }// This is the end of the if number of children for level 4 statement.
                    }// This is ending level 4.
                }// End of if numChildres for level 3.
            }// Ending level 3.
        }// Ending if numbCHildren to start level 2.
    }// Ending level 2.
    $return = array($qdata, $adata);
    return $return;

}