<?php

$dbhost = "localhost";
$dbname = "ae_db";
$dbuser = "ae_db";
$dbpass = "ae_password";

$link_id = mysql_connect($dbhost, $dbuser, $dbpass);
@mysql_select_db($dbname) or die ("Unable to select database $dbname.");

?>
