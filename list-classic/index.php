<?php
// This file provides a page for those in the studio to view the callers.
// 
// See "create table" in index.php for the tv_callers table.
// 
// This software is subject to the GPLv2 or later.  See then enclosed "LICENSE"
// file in the "doc" directory for details.  See the "README" file for 
// contact information.
?>

<html>
<head>
<?php
  // Constants.  
  $target_refresh = 5;

  // Attempt to adjust the refresh rate so that the time displayed is divisible
  // by $target_refresh.
  $now = time();
  $delta = ($target_refresh - ($now % $target_refresh)) % $target_refresh;
  if ($delta > 2)
      $delta = -1;
  $refresh = $target_refresh + $delta;
  $GLOBALS["now_str"] = date("h:i:s"); // Only way it's visible to functions.
?>
  <title>Caller List</title>
  <link rel="stylesheet" type="text/css" href="../style/tv-style-classic.css" />
  <link rel="shortcut icon" href="../style/favicon.png" type="image/x-icon" />

<?php
include "../include/database.php";

$all_callers = false; // Display all callers, not just those currently waiting.
$numlines = 3; // How many phone lines are there?

if ($_POST)
    handleform($_POST, $numlines);
showform($numlines);

function handleform($mode)
{
    global $all_callers;

    if (array_key_exists("all-callers", $mode))
    {
        $all_callers = true;
    }
} // handleform()

function showform()
{
    global $refresh;
    global $target_refresh;
    global $all_callers;

    // Get configuration information that is not per line.
    $dr = mysql_query("select * from tv_config");
    if ($dr && (mysql_num_rows($dr) > 0))
    {
        $rs = mysql_fetch_array($dr);

        $flash       = $rs["flash"];
        $message     = htmlspecialchars($rs["message"]);
    }
    else
    {
        $merr = mysql_error();

        // Drop and recreate the tv_config table to make sure it is ok.
        mysql_query("drop table tv_config");
        mysql_query("create table tv_config
                       (flash tinyint,
                        message text)");
        mysql_query("insert into tv_config
                       (flash, message)
                     values
                       (0, NULL)");

        // Drop and recreate the tv_callers table to make sure it is ok.
        mysql_query("drop table tv_callers");
        mysql_query("create table tv_callers (
                     id int(4) NOT NULL auto_increment,
                     priority int(2) default 5,
                     line tinyint default 1,
                     caller_name char (50) default NULL,
                     topic text default NULL,  
                     called int unsigned,
                     online tinyint default 0,
                     PRIMARY KEY  (id),
                     UNIQUE KEY id (id))");

        die("<span class=error><b>Recreated the database due to 
              the following error:<br>$merr</b>");
    }

    if (!$all_callers)
    {
        echo '<META HTTP-EQUIV="refresh" CONTENT="' . $refresh . "\">\n";
    }

    if ($flash) 
    {
        echo "<body style=\"background-color:#ff0000\">\n";
    }
    else
    {
        echo "<body>\n";
    }

    echo "<form action=\"" . $_SERVER["PHP_SELF"] . "\" method=post>\n";


    // If we want to see all callers then we don't need a where clause.  Also
    // sort strictly by date as it would be confusing otherwise.
    if ($all_callers)
        $sql = "select * from tv_callers
                order by called";
    else
        $sql = "select * from tv_callers
                where online=1
                order by priority,called";
    $dr = mysql_query($sql);

    if ($all_callers)
    {
        echo "The all callers page (this page) does not auto refresh.  When
              you are done either use the back button on your browser or press
              the button at the bottom of this page.<br><br>";
    }

    echo "<table width=100% border=0 cellpadding=5 cellspacing=0>\n";
    echo "<tr>
            <td valign=top><span class=indexlinenumber><b>Line</b></td>
            <td valign=top><span class=called><b>Time</b></td>
            <td valign=top><span class=caller_name><b>Name</b></td>
            <td align=\"right\"><span class=topic><b>Topic &nbsp; &nbsp; 
                &nbsp; &nbsp; " . $GLOBALS["now_str"] . "</b></td></tr>\n";

    while ($rs = mysql_fetch_array($dr))
    {
        if (!$rs["online"])
            $priority_str = " O";
        else if ($rs["priority"] < 5)
            $priority_str = " *";
        else if ($rs["priority"] > 5)
            $priority_str = " ?";
        else
            $priority_str = "  ";

        echo "<tr>
                <td valign=top><span class=indexlinenumber>" .
                  $rs["line"] . $priority_str . "</td>
                <td valign=top><span class=called>" .
                  date("h:i:s", $rs["called"]) . "</td>
                <td valign=top width=250><span class=caller_name>" .
                  htmlspecialchars($rs["caller_name"]) . "</td>
                <td valign=top><span class=topic>" .
                  htmlspecialchars($rs["topic"]) . "</td>
              </tr>\n";                                    
    } // while ...

    echo "</table>\n";
    echo "<p />\n";
    echo "<br><span class=message>
            <b>Message from the studio:</b><br>
            $message
          </span>\n";

    if ($all_callers)
        echo "<br><br><br><input type=submit name=current-callers
                           value='Current Callers'>
                       - All callers currently displayed.  Not refreshing.\n";
    else
        echo "<br><br><br><input type=submit name=all-callers
                           value='All Callers'>
                       - Current callers currently displayed.  
                        Refreshing every $target_refresh seconds.\n";

    echo "<br><br><br>Legend: <br>
                 &nbsp; &nbsp; * - High priority (member, etc.)<br>
                 &nbsp; &nbsp; ? - Low priority (crank, etc.)<br>
                 &nbsp; &nbsp; O - Old caller\n";

    // "flash" is only turned on for one refresh.
    if ($flash)
    {
        $dr = mysql_query("update tv_config
                           set flash=0");
        if (mysql_errno() > 0)
            echo mysql_error() . "<p />\n";
    }

} // showform()


?>

</body>
</html>
