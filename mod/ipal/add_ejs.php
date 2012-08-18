<html>
<head>
<title>Adding EJS Activities to IPAL questions</title>
</head>
<body>
<?php
require_once('../../config.php');
foreach($_GET as $key=>$value){
    $$key = $value;
    if($key == 'qid'){
        foreach($_GET['qid'] as $qkey=>$qvalue){
            $qid[$qkey]=$qvalue;
        }
    }
}
echo "Click <a href='".$CFG->wwwroot."/mod/ipal/view.php?id=$cmid'>here</a> to return to IPAL activity.";
if($ejsappid > 0){
    echo "\n<br />An EJS App activity is being added to the selected questions.";
}
else
{
    echo "\n<br />You must select an EJS App activity to be added to the selected questions. Please use the back button and try again.</body></html>";
    exit;
}
$ejs = $DB->get_record('modules',array('name'=>'ejsapp'));
$ejsid=$ejs->id;//The id for all EJS module activities
$ejscourse_module= $DB->get_record('course_modules',array('instance'=>$ejsappid,'course'=>$courseid,'module'=>$ejsid));
$ejscourse_moduleid = $ejscourse_module->id;
echo "\n<br />The following code (put inside the iframe tags) ";
$new_code = "src=\"".$CFG->wwwroot."/mod/ipal/viewejs.php?id=$ejscourse_moduleid\" width='600' height='410'>EJS App";
echo "\n<br />".$new_code;
$new_code = '<iframe '.$new_code.'</iframe>';
echo "\n<be />has been added to the selected question(s). Here are the revised question(s):";
foreach($qid as $qkey=>$qvalue){
    echo "\n<br /> question $qvalue";
    $questiontext = $DB->get_field('question','questiontext',array('id'=>$qvalue));
    $new_value = $new_code.$questiontext;
    $result=$DB->set_field('question','questiontext',$new_code.$questiontext,array('id'=>$qvalue));
    $newquestiontext = $DB->get_field('question','questiontext',array('id'=>$qvalue));
    echo "\n<br />".$newquestiontext;
}
echo "\n</body></html>";
?>