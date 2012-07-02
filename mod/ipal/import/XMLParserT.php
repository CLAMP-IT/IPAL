<!DOCTYPE HTML PUBLIC "-//W3C//DTD HTML 4.01 Transitional//EN" "http://www.w3.org/TR/html4/loose.dtd">
<html>
<head>
<meta http-equiv="Content-Type" content="text/html; charset=iso-8859-1">
<title>XML Parser Q</title>
</head>

<body>
<?php
    require_once("../../../config.php");
$courseid = $_GET['courseid'];
$debug = $_GET['debug'];
//The following script works
//	$course = $DB->get_record('course', array('id'=>"$courseid"));
//	echo "\n<br />Here is the vourse name from the courses table ".$course->fullname;
//	foreach($course as $key => $value){
//		echo "\n<br />$key: ".$value;
//	}
//Use the functions at http://docs.moodle.org/dev/DML_functions
?>
Page to Import Questions from a Moodle xml question set
<form method="post" enctype="multipart/form-data">
<br />Please select the category for these questions:
<?php
$contextid = $DB->get_record('context', array('instanceid'=>"$courseid",'contextlevel'=>'50'));
	$contextID = $contextid->id;
	echo "\n<br />The context Id for the course is $contextID";
$categories = $DB->get_records_menu('question_categories',array('contextid'=>"$contextID"));
$cats = $DB->get_records('question_categories',array('contextid'=>"$contextID"));
if($debug){
	foreach($categories as $key =>$value){echo "\n<br />category id =$key\n<br />";
		foreach($cats[$key] as $ky => $vlue){
			echo ", $ky=".$vlue;
		}
	}
}
?>
<br /><select name="categoryid">
<option value="0">Please choose a category</option>
<?php
foreach ($categories as $key => $value){
	echo "\n<option value='$key'>".$value."</option>";
}

?>
</select>
<br />
<br />Please post <input type="file" name="Junkinfile">
<br /><input type="submit">

</form>
<?php
if($_FILES['Junkinfile']){
	if(!($_POST['categoryid'] > 0)){
		echo "\n<br />You need to select a category.</body></html>";
		exit;
	}
	if($debug){echo "\n<br />Here is the result\n<br />";}
	$fp = fopen($_FILES['Junkinfile']['tmp_name'],'r');
	$data = fread($fp,80000);
	fclose($fp);
	//echo $data;
	echo "<br />Here are the submitted questions<br />";

//Structure of the XML file:
//Level 0 = quiz
//	Level 1 = question attribute type
//		Level 2 = name 
//			Level 3 = text (content)
//		Level 2 = questiontext attribute format
//			Level 3 = text (content)
//		Level 2 = several types of feedback each with attribute format
//			Level 3 = text (content)
//		Level 2 = defaultgrade,penalty,hidden, single, shuffleanswers,answernumbering, each with (content)
//		Level 2 = answer attribute fraction and format
//			Level 3 = text (content)
//			Level 3 = feedback attribute format
//				Level 4 = text (content)
//		Level 2 = answer (repeat)


function MoodleXML2Array($xml){//This function accepts a Moodle XML question set and returns an arrays for the questions and answers 
	$elem = new SimpleXMLElement($xml);
	$numQuestions = count($elem ->children());
	//echo "\n<br />There are $numQuestions";
	$n = 0;
	foreach ($elem as $question) {//Once for each question $question is Level 1
		$na = 0;
		$n++;
		foreach ($question->attributes() as $key => $value){//Gets all the attributes of each question or answer
			$label = $question->getName();
			if($label =='question'){//This label at this level for Moodle xml should be question
				$qData[$n] = new stdClass();
				if($key == 'type'){$key = 'qtype';}//Correcting to correspond to field name
				$qData[$n]->$key=$value;//The key should be type
				echo "\n<br />Question ($label) $n has $key= ".$value;
				foreach($question as $questionData){//$questionData is level 2
					$numChild = count($questionData->children());
					$label2 = $questionData->getName();//This is a level 2 label
					if($label2 == 'answer'){
						$aData[$n][$na] = new stdClass();//One per answer
						$aData[$n][$na]->$label2 = $value;echo "\n<br />debug93 and value for question $n and answer $na with label $label is ".$value;
						
						foreach($questionData->attributes() as $ky => $vlue){
							if($ky=='format'){
								$ky = $lable2.$ky;
								if($vlue =='html'){$vlue = 1;}else{$vlue = 0;}
								$aData[$n][$na]->$ky = $vlue;
							}
							
						}
						if($numChild > 0){
							foreach($questionData as $q2Data){//q2Data is level 3
								$numChild3 = count($q2Data);
								if($numChild3 == 0){
									$value3 = trim((string) $q2Data);
									$label3 = $q2Data->getName();
									if(!($label3 == 'text'))
									{
										$aData[$n][$na]->$label3=$value3;
									}
								}elseif($numDhild3 > 0){
									foreach($q2Data->attributes() as $ky3 =>$value3){
										if($ky3 == 'format'){
											$ky3=$label3.$ky3;
											if($value3=='html'){$value3=1;}else{$value3 = 0;}
										}
										$aData[$n][$na]->$ky3 = $value3;
									}
									foreach($s2Data as $q4Data){
										$aData[$n][$na]->$label3=trim((string) $q4Data);
									}
								}
								

							}							
						}
						$na++;
					}
					else
					{
						//It isn not an answer
					}
				}
			}
			else
			{
				echo "\n<br />Question $n is not in the Moodle xml format";
			}
		}
	}
	$return = array($qData,$aData);
	return $return;
}
	list($Data,$Qs) = MoodleXML2Array($data);
if($debug){
	echo "\n<br /><br />Printing out the array";
	echo "\n<br />the count of Data is ".count($Data);
	for ($k=1;$k<3;$k++){
		echo "\n<br />Question $k \n<br />";
		foreach($Data[$k] as $key => $value){
			echo ", $key=".$value;
		}
		echo "\n<br /> and the answers for question $k are \n<br />";
		$numAnswers = count($Qs[$k]);
		for ($j=1;$j<=$numAnswers;$j++){
			echo "\n<br />  $j of $numAnswers";
			foreach ($Qs[$k][$j-1] as $key => $value){
				echo ", $key=".$value;
			}
		}
		
	}
}//End of debug print
	for ($k=1;$k<=count($Data);$k++){
		echo "\n<br /><b>Question $k</b> (type=".$Data[$k]->type.")";
		echo "\n<br />Title: <b>".$Data[$k]->name."</b>";
		echo "\n<br />Text: ".$Data[$k]->questiontext;
		$numAnswers = count($Qs[$k]);
		if($numAnswers >0){
			echo "\n<br/>and the possible answers are:";
			echo "\n<ol>";
			if($Data[$k]->answernumbering == 'abc'){
				$value = 'a';
			}
			elseif ($Data[$k]->answernumbering == 'ABCD'){
				$value = 'A';
			}
			else
			{
				$value = 1;
			}
			for ($j=1;$j<=$numAnswers;$j++){
				echo "\n\t<li value='$value'>".$Qs[$k][$j-1]->answer."</li>";
				$value++;
			}
			echo "</ol>";
		}
	
	}
	
	
}
?>

<br /><a href="<?php echo $CFG->wwwroot."/question/edit.php?courseid=$courseid"; ?>">Return to Question set page</a>
</body>
</html>
