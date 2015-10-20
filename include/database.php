<?php

$dbhost = "tv-domain.com";
$dbname = "tv_db";
$dbuser = "tv_user";
$dbpass = "tv_password";

$link_id = mysql_connect($dbhost, $dbuser, $dbpass);
@mysql_select_db($dbname) or die ("Unable to select database $dbname.");

?>
