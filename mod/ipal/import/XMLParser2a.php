<?php
    require_once("../../../config.php");

?>
<!DOCTYPE HTML PUBLIC "-//W3C//DTD HTML 4.01 Transitional//EN" "http://www.w3.org/TR/html4/loose.dtd">
<html>
<head>
<meta http-equiv="Content-Type" content="text/html; charset=iso-8859-1">
<title>XML Parser l</title>
</head>

<body>
<?php

$record = new stdClass();
$record->name         = 'overview1';
$record->displayorder = '1000';
$lastinsertid = $DB->insert_record('quiz_report', $record);
echo "\n<br />debug335 and lastinsertid = ".$lastinsertid;


?>

<br /><a href="<?php echo $CFG->wwwroot."/question/edit.php?courseid=$courseid"; ?>">Return to Question set page</a>
</body>
</html>
