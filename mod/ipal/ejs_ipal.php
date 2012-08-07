<html>
<head>
<title>Adding EJS Activities to IPAL questions</title>
</head>
<body>
<?php
require_once('../../config.php');
$cmid = optional_param('cmid', 0, PARAM_INT); // course_module ID, or
if ($cmid) {
    $module = $DB->get_record('modules',array('name'=>'ipal'));
    $course_modules = $DB->get_record('course_modules', array('id' => $cmid, 'module'=>$module->id));
}
else
{
    echo "You must supply the cmid value for the IPAL activity.";
    exit;
}
$ipalid = $course_modules->instance;
echo "\n<br />Information: IPAL id is $ipalid.";
$ipal = $DB->get_record('ipal',array('id'=>$ipalid));
$ipalquestions = explode(",",$ipal->questions);
echo " There are ".count($ipalquestions)." ipal questions in this IPAL activity.";
$courseid = $course_modules->course;
if(!$ejsapps = $DB->get_records('ejsapp',array('course'=>$courseid))){
    echo "\n<br />You must create at least one EJS App activity in this course before you can add it to questions.";
    exit;
}
$context = $DB->get_record('context',array('instanceid'=>$courseid,'contextlevel'=>'50'));
$contextid = $context->id;
$q_categories = $DB->get_records('question_categories',array('contextid'=>$contextid));
//echo "\n<br />The courseid is ".$courseid." and contextid is $contextid";
$hasejs[0]='';
$thisipal[0]='';
$otherquestions[0]='';
foreach($q_categories as $categoryid => $value){
    //echo "\n<br />For category $categoryid";
    $questions = $DB->get_records('question',array('category'=>$categoryid));
    foreach($questions as $qid=>$question ){
        //echo "\n<br />$qid: ".$question->name;
        if(preg_match("/viewejs/",$question->questiontext)){
            //echo " already has EJS App";
            $hasejs[$qid] = $question->name;
        }
        elseif(in_array($qid,$ipalquestions))
        {
           // echo " is in the ipal collection ";
            $thisipal[$qid] = $question->name;
        }
        else
        {
             //echo " not in the ipal collection";
            $otherquestions[$qid] = $question->name;
        }
    }
    //echo "\n<br />".$question->id.": ".$question->name;
}
echo "\n<form action='add_ejs.php'>";
echo "\n<br />Which EJS App Activity do you want to add to a question or several questions?";
foreach($ejsapps as $ejsappid=>$ejsapp){
    echo "\n<br /><input type='radio' name='ejsappid' value='$ejsappid'>".$ejsapp->name;
}
echo "\n<br /><br />Check every question where you want to insert the selected EJS Activity into the text of a question.";
$qcount=0;
if(count($thisipal) > 1){
    echo "\n<br />Here are questions in this IPAL activity.";
    foreach($thisipal as $qipalid=>$qipaltitle){
        if($qipalid){echo "\n<br /><input type='checkbox' value='$qipalid' name='qid[$qcount]'>".$qipaltitle;$qcount++;}
    }
}
if(count($otherquestions) > 1){
    echo "\n<br /><br />Here are other questions which are not in this IPAL activity. You may add the selected EJS App activity to these questions as well.";
    foreach($otherquestions as $qotherid=>$qothertitle){
        if($qotherid){echo "\n<br /><input type='checkbox' value='$qotherid' name='qid[$qcount]'>".$qothertitle;$qcount++;}
    }
}
echo "\n<input type='hidden' name='cmid' value='$cmid'><input type='hidden' name='ipalid' value='$ipalid'>";
echo "\n<input type='hidden' name='courseid' value='$courseid'>";
echo "\n<br /><input type='submit'></form>";
if(count($hasejs)> 1){
    echo "\n<br /><br />These questions already have an EJS App activity in the question text.";
    foreach($hasejs as $qhasej =>$qhasejtitle){
        echo "\n<br />".$qhasejtitle;
    }
}
?>
</body>
</html>