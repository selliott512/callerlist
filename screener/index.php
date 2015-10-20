<?php
// This file provides a form for those in the control room to enter and
// manipulate callers.  Any "save" button saves the state of the form.  The
// "drop" button drops the caller.  The "-" and "+" buttons adjust the callers
// priority.
// 
// See "create table" in index.php for the tv_callers table.
// 
// This software is subject to the GPLv2 or later.  See then enclosed "LICENSE"
// file in the "doc" directory for details.  Also, see the "README" file for
// contact and other information.
?>

<html>
<head>
  <title>Call Screener Data Entry</title>
  <link rel="shortcut icon" href="../style/favicon.png" type="image/x-icon" />
  <link rel="stylesheet" type="text/css" href="../style/tv-style-classic.css" />

<?php

include "../include/database.php";

$numlines = 3; // How many phone lines are there?
$edit_line = 0;  // The line that is being edited.

if ($_POST)
    handleform($_POST, $numlines);
showform($numlines);

function getMsec()
{
    list($usec, $sec) = explode(" ", microtime());
    return  $sec . substr($usec, 2, 3);
}

function handleform($calls, $numlines)
{
    global $edit_line;

    $edit_line = 0; // Line being edited.
    for ($i=1; $i<=$numlines; $i++)
    {
        if (array_key_exists("drop-$i", $calls) ||
            array_key_exists("edit-$i", $calls))
        {
            if (array_key_exists("edit-$i", $calls))
            {
                $edit_line = $i;
                $online = 2; // 2 means to be edited.
            }
            else
            {
                $edit_line = 0;
                $online = 0;
            }

            // Drop or edit the specified caller.
            $sql = "update tv_callers
                    set online=$online
                    where online!=0 and line=$i";
            mysql_query($sql);
            if (mysql_errno() > 0) echo mysql_error() . "<p />\n";
        }

        if (array_key_exists("lower-$i", $calls))
        {
            $sql = "update tv_callers
                    set priority=priority+1
                    where line=$i";
            mysql_query($sql);
            if (mysql_errno() > 0) echo mysql_error() . "<p />\n";
        }

        if (array_key_exists("raise-$i", $calls))
        {
            $sql = "update tv_callers
                    set priority=priority-1
                    where line=$i";
            mysql_query($sql);
            if (mysql_errno() > 0) echo mysql_error() . "<p />\n";
        }

        // For the following we assume the line to be entered as online is
        // the line that has a non-blank caller name.
        if (array_key_exists("caller_name-$i", $calls) &&
            $calls["caller_name-$i"])
        {
            $key = "topic-$i";
            $topic = mysql_real_escape_string(trim($calls[$key]));
            $caller_name = mysql_real_escape_string(
                trim($calls["caller_name-$i"]));

            $called = $calls["called-$i"];
            if ($called)
            {
                $called = mysql_real_escape_string(trim($called));
            }
            else
            {
                $called = time();
            }

            mysql_query("insert into tv_callers
                           (caller_name, line, topic, online, called)
                         values
                           ('$caller_name', $i, '$topic', 1, $called)");
        }
    }

    if (array_key_exists("clear", $calls))
    {
        $do_clear = 1;
        $new_message = "";
    }
    else
    {
        $do_clear = 0;
        $new_message = $calls["message"];
    }

    if ($new_message || $do_clear)
    {
        $msg = mysql_real_escape_string(trim($new_message));

        // Attempt to update the message.
        mysql_query("update tv_config
                     set message='$msg'");
        if (mysql_errno() > 0)
            echo mysql_error() . "<p />\n";
    }

    $time = getMsec();
    
    if (array_key_exists("flash", $calls))
    {
        // Attempt to update the message.
        mysql_query("update tv_config
                     set flash=1");
        if (mysql_errno() > 0)
            echo mysql_error() . "<p />\n";
            
        // Update the flash TS for the new flash system.
	    mysql_query("update tv_config
    			     set flash_ts=$time");
    }
    
    // Update the modified timestamp.
    mysql_query("update tv_config
    		     set modified=$time");
} // handleform()

function showform($numlines)
{
    global $edit_line;

    // It's hard to support both magic quoting and not magic quoting.  The 
    // following are deprecated anyway.

    if (get_magic_quotes_gpc())
    {
        echo "<h2>WARNING: Magic Quotes are not supported.  Turn " . 
            "<code>magic_quotes_gpc</code> off.</h2><br>\n";
    }

    if (get_magic_quotes_runtime())
    {
        echo "<h2>WARNING: Runtime Quotes are not supported.  Turn " . 
            "<code>magic_quotes_runtime</code> off.</h2><br>\n";
    }

    // Get configuration information that is not per line.
    $dr = mysql_query("select * from tv_config");
    if ($dr && (mysql_num_rows($dr) > 0))
    {
        $rs = mysql_fetch_array($dr);

        $all_callers = $rs["all_callers"];
        $flash       = $rs["flash"];
        $message     = htmlspecialchars($rs["message"]);
    }

    echo "<form action=\"" . $_SERVER["PHP_SELF"] . "\" method=post>\n";
    echo "<table border=0 cellpadding=5 cellspacing=0>\n
            <tr align=center>
              <td><b>Line</b></td>
              <td><b>Caller Name</b></td>
              <td><b>Topic</b></td>
            </tr>\n";
    for ($i=1; $i <= $numlines; $i++)
    {
        echo "<tr>\n";
        $sql = "select caller_name, priority, called, topic, called
                from tv_callers
                where online=1 and line=$i";
        $dr = mysql_query($sql);
        if (mysql_errno() > 0) echo mysql_error() . "<p />\n";
        if ($dr && mysql_num_rows($dr)>0)
        {
            echo "<td valign=top>
                      <span class=callerslinenumber>$i</span> 
                      <input type=submit name=drop-$i value=\"Drop\">
                      <input type=submit name=edit-$i value=\"Edit\">
                      <input type=submit name=lower-$i value=\"-\">
                      <input type=submit name=raise-$i value=\"+\">
                  </td>\n";

            $rs = mysql_fetch_array($dr);

            $priority = $rs["priority"];
            if ($priority < 5)
                $priority_str = $priority . " - high";
            else if ($priority > 5)
                $priority_str = $priority . " - low";
            else
                $priority_str = $priority . "  - normal";

            echo "<td colspan=2 valign=top><font size=-1>" .
                "Current caller: " . htmlspecialchars($rs["caller_name"]) . 
                    "<br>" .
                "Priority: " . $priority_str . "<br>" .
                "Called at: " . date("h:i:s", $rs["called"]) . "<br>" .
                "Topic: " . htmlspecialchars($rs["topic"]) .
                 "</font></td>\n";
        }
        else
        {
            $caller_name = "";
            $called      = 0;
            $topic       = "";
            if ($i == $edit_line)
            {
                $sql = "select caller_name, called, topic
                        from tv_callers
                        where online=2 and line=$i";
                $dr = mysql_query($sql);

                if (mysql_errno() > 0) echo mysql_error() . "<p />\n";
                if ($dr && (mysql_num_rows($dr) > 0))
                {
                    $rs = mysql_fetch_array($dr);

                    $caller_name = htmlspecialchars($rs["caller_name"]);
                    $called      = $rs["called"];
                    $topic       = htmlspecialchars($rs["topic"]);

                     $sql = "update tv_callers
                             set online=0
                             where online=2";
                     mysql_query($sql);
                }
            }

            echo "<td valign=top>
                      <span class=callerslinenumber>$i</span> 
                  </td>\n";

            echo "<td valign=top>Caller name:<br>
                    <input type=text name=caller_name-$i size=30 value=\"$caller_name\">
                  </td>
                  <td valign=top>Topic:<br>
                    <input type=text name=topic-$i size=50 value=\"$topic\" accesskey=\"$i\"></textarea>
                    <input type=submit value=Save>
                    <input type=hidden name=called-$i value=\"$called\">
                  </td>";
        }
        echo "</tr>\n";
    }

    echo "<tr><td colspan=3>&nbsp;</td></tr>
          <tr><td valign=top>Message for cast:</td>
             <td valign=top colspan=2>
               <textarea wrap=virtual name=message cols=60 rows=6></textarea>
               <input type=submit value=Save>
               <input type=submit name=flash value=Flash>
               <input type=submit name=clear value=Clear>
             </td>
          </tr>\n";

    echo "<tr><td colspan=2 valign=top>Current message:
                   $message</td></tr>\n";
} // showform()

// See "create table" in index.php for the tv_callers table.

?>

</body>
</html>
