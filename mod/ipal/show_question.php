<html>
<head>
</head>
<body>
<?php
include '../../config.php';
 require_once("lib.php");
 require_once('../../lib/filelib.php');

function ipal_get_answers($question_id){
        global $DB;
        $answerarray=array();
        $answers=$DB->get_records('question_answers',array('question'=>$question_id));
                        foreach($answers as $answers){
                                $answerarray[$answers->id]=$answers->answer;
                        }

        return($answerarray);
}


function ipal_get_questions($qid){
        global $DB;
                $pagearray2=array();

        $aquestions=$DB->get_record('question',array('id'=>$qid));
                        if($aquestions->questiontext != ""){

        $pagearray2[]=array('id'=>$qid, 'question'=>$aquestions->questiontext, 'answers'=>ipal_get_answers($qid));
                                                        }
        return($pagearray2);
}


function ipal_display_standalone_question(){
                global $contextid;
                global $entryid;
                $qid=$_GET['qid'];
                $myFormArray= ipal_get_questions($qid);
                echo "<form action=\"\">\n";
                $text = $myFormArray[0]['question'];
                $text = file_rewrite_pluginfile_urls($text,'pluginfile.php',$contextid->id,'question','questiontext/'.$entryid.'/1',$qid);
                echo  $text;
                echo "<br>";
                        foreach($myFormArray[0]['answers'] as $k=>$v){
                        echo "<input type=\"radio\" name=\"answer_id\" value=\"$k\"/> ".strip_tags($v)."<br />\n";
                        }
        echo "</form>";
}

//Add entry to the question_usages table so that pluginfile.php will work
$id = $_GET['id'];

$ipal = $DB->get_record('ipal',array('id'=>$_GET['id']));
$courseid=$ipal->course;
$contextid = $DB->get_record('context',array('instanceid'=>$courseid,'contextlevel'=>50));
     $record = new Stdclass();
     $record->contextid=$contextid->id;
     $record->component='mod_ipal';
     $record->preferredbehaviour='deferredfeedback';
     $last_insert_id = $DB->insert_record('question_usages',$record);
     $entryid = $last_insert_id;

ipal_display_standalone_question();

?>
</body>
</html>
