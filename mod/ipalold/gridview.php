<?php
include '../../config.php';
global $DB;

//
//Who has answered questions so far?
//
function ipal_who_sofar($ipal_id){
 global $DB;

 $records=$DB->get_records('ipal_answered',array('ipal_id'=>$ipal_id));

 foreach($records as $records){
     $answer[]=$records->user_id;
 }
 return(array_unique($answer));
}



//
//find student name
//
function ipal_find_student($userid){
 global $DB;
 $user=$DB->get_record('user',array('id'=>$userid));
 $name=$user->lastname.", ".$user->firstname;
 return($name);
}

 $ipal=$DB->get_record('ipal',array('id'=>(int)$_GET['id']));
 $questions=explode(",",$ipal->questions);
 echo "<link rel=\"stylesheet\" type=\"text/css\" href=\"gridviewstyle.css\" />";
 echo "<table border=\"1\" width=\"100%\">\n";
 echo "<thead><tr>";

//If anonymous, exclude the column "name" from the table
 if(!$ipal->anonymous) {
	echo "<th>Name</th>\n";
 }

foreach($questions as $question){
	if($question_data=$DB->get_record('question',array('id'=>$question))){
		echo "<th style=\"word-wrap: break-word;\">".substr(trim(strip_tags($question_data->name)),0,80)."</th>\n";
	}
}
 echo "</tr>\n</thead>\n";

 foreach(ipal_who_sofar($ipal->id) as $user){
	echo "<tbody><tr>";
	
	//if anonymous, exlude the student name data from the table
	if(!$ipal->anonymous) {
		echo "<td>".ipal_find_student($user)."</td>\n";
	}
	foreach($questions as $question){
		if(($question != "") and ($question!=0)){
			$answer=$DB->get_record('ipal_answered',array('ipal_id'=>$ipal->id,'user_id'=>$user, 'question_id'=>$question));
			if(!$answer){
				echo "<td>&nbsp;</td>\n";
			}
			else{
				if($answer->answer_id < 0){
					$display_data = $answer->a_text;
				}
				else
				{
					$answer_data=$DB->get_record('question_answers',array('id'=>$answer->answer_id));
					$display_data = $answer_data->answer;
				}
				echo "<td style=\"word-wrap: break-word;\">".substr(trim(strip_tags($display_data)),0,40)."</td>\n";
			}
		}
	}
	echo "</tr></tbody>\n";
}
echo "</table>\n";

?>

