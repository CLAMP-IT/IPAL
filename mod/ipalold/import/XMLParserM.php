<!DOCTYPE HTML PUBLIC "-//W3C//DTD HTML 4.01 Transitional//EN" "http://www.w3.org/TR/html4/loose.dtd">
<html>
<head>
<meta http-equiv="Content-Type" content="text/html; charset=iso-8859-1">
<title>XML Parser M</title>
</head>

<body>
<?php
    require_once("../../../config.php");
$courseid = $_GET['courseid'];
//The following script works
//	$course = $DB->get_record('course', array('id'=>"$courseid"));
//	echo "\n<br />Here is the vourse name from the courses table ".$course->fullname;
//	foreach($course as $key => $value){
//		echo "\n<br />$key: ".$value;
//	}
//Use the functions at http://docs.moodle.org/dev/DML_functions
?>
<form method="post" enctype="multipart/form-data">
<br />Please post <input type="file" name="Junkinfile">
<br /><input type="submit">

</form>
<?php
if($_FILES['Junkinfile']){
	echo "\n<br />Here is the result\n<br />";
	$fp = fopen($_FILES['Junkinfile']['tmp_name'],'r');
	$data = fread($fp,80000);
	fclose($fp);
	//echo $data;
	echo "<br />Here are teh submitted questions<br />";

function MoodleXML2Array($xml){//This function accepts a Moodle XML question set and returns an arrays for the questions and answers 
	$elem = new SimpleXMLElement($xml);
	$numQuestions = count($elem ->children());
	echo "\n<br />There are $numQuestions";
	$n = 0;
	foreach ($elem as $question) {//Once for each question
		$na = 0;
		$n++;
		foreach ($question->attributes() as $key => $value){//Gets all the attributes of each question
			$label = $question->getName();
			if($label =='question'){//This label at this level for Moodle xml should be question
				$qData[$n] = new stdClass();
				$qData[$n]->$key=$value;//The key should be type
				echo "\n<br />Question ($label) $n has $key= ".$value;
				foreach($question as $questionData){
					$numChild = count($questionData->children());
					$label = $questionData->getName();
					if($numChild == 1){
						foreach($questionData as $q2Data){
							$value = trim((string) $q2Data);
						}
					}
					else
					{
						$value = trim((string) $questionData);
					}
					if($label == 'answer'){
						$aData[$n][$na] = new stdClass();//One per answer
						$aData[$n][$na]->$label = $value;
						foreach($questionData->attributes() as $ky => $vlue){
							$adata[$n][$na]->$ky = $vlue;
						}
						$na++;
					}
					else
					{
						foreach($questionData->attributes() as $ky =>$vlue){
							$qData[$n]->$ky = $vlue;
						}
						$qData[$n]->$label = $value;
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
	for ($k=1;$k<=count($Data);$k++){
		echo "\n<br /><b>Question $k</b> (type=".$Data[$k]->type.")";
		echo "\n<br />Title: <b>".$Data[$k]->name."</b>";
		echo "\n<br />Text: ".$Data[$k]->questiontext;
		$numAnswers = count($Qs[$k]);
		if($numAnswers >0){
			echo "\n<br/>and the possible answers are:";
			echo "\n<ol>";
			if($Data[$k]->answernumbering == 'abc'){
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
</body>
</html>
