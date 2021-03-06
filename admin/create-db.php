<?php
// This script recreates the database.  It depends on CREATE TABLE failing
// in the case where the table already exist.

header("Content-Type: text/html")
?>
<!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.0 Strict//EN" "http://www.w3.org/TR/xhtml1/DTD/xhtml1-strict.dtd">
<html xmlns="http://www.w3.org/1999/xhtml" xml:lang="en" lang="en" dir="ltr">
  <head>
    <meta http-equiv="Content-Type" content="text/html; charset=utf-8" />
    <title>Create CallerList DB</title>
    <link rel="stylesheet" type="text/css" href="../style/callerlist.css" />
    <link rel="shortcut icon" href="../style/favicon.png" type="image/x-icon" />
  </head>
  <body class="background">
    <p>Creating the CallerList database ...</p>
    <p><br/></p>
<?php

include_once "../include/common.php";

$errors = 0;

if (strlen($dbschema) && ($dbType == CL_DB_PGSQL))
{
	$dbschemaQ = quoteMySQLStyle($dbschema);
    $dbConn->query("CREATE SCHEMA \"$dbschemaQ\"");
    $dbResult = $dbConn->query("SET search_path = '$dbschemaQ'");
    if ($dbResult)
    {
?>
        <p>Successfully created the schema <?php echo "\"$dbschemaQ\"" ?>.</p>
<?php
    }
    else
    {
    	$errors++;
?>
        <p>Failed to create the schema: <?php echo getLastError() ?></p>
<?php
    }
}

switch ($dbType)
{
    case CL_DB_MYSQL:
        $dbResult = $dbConn->query(
            "CREATE TABLE cl_callers ( " .
            "id int(4) NOT NULL AUTO_INCREMENT, " .
            "line tinyint(2), " .
            "time bigint(14), " .
            "priority tinyint(2) DEFAULT 5, " .
            "online tinyint(2) DEFAULT 1, " .
            "name char(50), " .
            "topic text, " .
            "PRIMARY KEY (id), " .
            "KEY (line), " .
            "KEY (online))");
        break;
    case CL_DB_PGSQL:
        $dbResult = $dbConn->query(
            "CREATE TABLE cl_callers ( " .
            "id serial PRIMARY KEY, " .
            "line smallint, " .
            "time bigint, " .
            "priority smallint DEFAULT 5, " .
            "online smallint DEFAULT 1, " .
            "name varchar(50), " .
            "topic text)");
        if ($dbResult)
        {
        	$dbResult = $dbConn->query(
                "CREATE INDEX cl_callers_line_idx " .
                "ON           cl_callers (line)");
        }
        if ($dbResult)
        {
        	$dbResult = $dbConn->query(
                "CREATE INDEX cl_callers_online_idx " .
                "ON           cl_callers (online)");
        }
        break;
}

if ($dbResult)
{
?>
    <p>Successfully created the cl_callers table.</p>
<?php
}
else
{
	$errors++;
?>
    <p>Failed to create the cl_callers table: <?php echo getLastError() ?></p>
<?php
}

switch ($dbType)
{
    case CL_DB_MYSQL:
        $dbResult = $dbConn->query(
            "CREATE TABLE cl_config ( " .
            "flash bigint(14) DEFAULT 0, " .
            "modified bigint(14) DEFAULT 0, " .
            "message text)");
        break;
    case CL_DB_PGSQL:
        $dbResult = $dbConn->query(
            "CREATE TABLE cl_config ( " .
            "flash bigint DEFAULT 0, " .
            "modified bigint DEFAULT 0, " .
            "message text)");
        break;
}
if ($dbResult)
{
?>
    <p>Successfully created the cl_config table.</p>
<?php
}
else
{
    $errors++;
?>
    <p>Failed to create the cl_config table: <?php echo getLastError() ?></p>
<?php
}

if (!$errors)
{
    $dbResult = $dbConn->query(
        "INSERT INTO cl_config (message) VALUES " .
        "   ('This is the initial message after creating the database.')");
    if ($dbResult)
    {
?>
    <p><br/>Successfully inserted into the cl_config table.<br/><br/></p>
<?php
    }
    else
    {
        $errors++;
?>
    <p><br/>Failed to insert into the cl_config table:
        <?php echo getLastError() ?><br/><br/></p>
<?php
    }

?>
<?php

    if (!$errors)
    {
        for ($line = 1; $line <= $numLines; $line++)
        {
            $dbResult = $dbConn->query(
                "INSERT INTO cl_callers (line, online) " .
                "VALUES ('$line', '0')");
            if ($dbResult)
            {
?>
    <p>Successfully inserted line <?php echo $line ?> into the cl_config table.</p>
<?php
            }
            else
            {
                $errors++;
?>
    <p>Failed to insert <?php echo $line ?> line into the cl_callers table:
        <?php echo getLastError() ?></p>
<?php
            }
        }
    }
}
    $resultStr = $errors ? "failed" : "successful";
?>
    <p><br/></p>
    <p>CallerList database creation result: <?php echo $resultStr ?></p>
    <p><br/></p>
    <p>Click <a href="create-db.php">here</a> to start over.</p>
    <p>Click <a href="..">here</a> to return to the top level page.</p>
  </body>
</html>
