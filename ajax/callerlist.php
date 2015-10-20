<?php
// This file provides an AJAX interface to caller information.  That is,
// JavaScript clients may post a timestamp and get a list of newer call
// information, if any, in XML.
//
// This software is subject to the GPLv2 or later.  See then enclosed "LICENSE"
// file in the "doc" directory for details.  See the "README" file for
// contact information.

// This code is a bit disorganized.  It should at least be subdivided into
// functions.

// Do this before anything else.
header('Content-type: text/xml');

include_once "../include/common.php";

// Utility functions.

function appendTextNode($respXML, $prnt, $name, $value)
{
    $el = $respXML->createElement($name);
    $prnt->appendChild($el);

    $tn = $respXML->createTextNode($value);
    $el->appendChild($tn);
}

// POST message processing.
$allCallers = isset($_POST["allCallers"]) ? $_POST["allCallers"] : "";
$allCallers = ($allCallers == "true");
$updateXMLText = isset($_POST["updateXML"]) ? $_POST["updateXML"] : null;
$cmod = isset($_POST["modified"]) ? $_POST["modified"] : -1; // Client TS.
$editMode = isset($_POST["editMode"]) ? $_POST["editMode"] : "";
$editMode = ($editMode == "true");

clLog("Start: \$allCallers=$allCallers \$cmod=$cmod \$editMode=$editMode");

$time = getMsec();

$respXML = new DOMDocument( );
$respXML->preserveWhiteSpace = false;
$respXML->formatOutput = true;

$callerlistEl = $respXML->createElement("callerList");
$respXML->appendChild($callerlistEl);

// Append the time before any early returns since this means that the
// connection was successful.
appendTextNode($respXML, $callerlistEl, "time", $time);

// Make sure the user has permission.  If not redirect them.
$requiredAuth = $editMode ? CL_AUTH_SCREENER : CL_AUTH_CREW;
if ((session_id() != $_POST["phpsessid"]) ||
    ($_SESSION["cl_auth"] < $requiredAuth))
{
    if ($editMode)
    {
    	$location = "edit";
        $requiredDesc= "screener";
    }
    else
    {
    	$location = "list";
        $requiredDesc= "crew";
    }
    appendTextNode($respXML, $callerlistEl, "redirect", ".");
    appendTextNode($respXML, $callerlistEl, "message", "You don't have " .
                   "sufficient privileges ($requiredDesc or above) to look " .
                   "at this page.  Avoid bookmarking $location/index.html " .
                   "to prevent this.  Try bookmarking $location/index.php " .
                   "instead.  Redirecting in 5 seconds so you can login " .
                   "again.  If your browser remembers the crew password ".
                   "you won't have to do anything.");
    echo $respXML->saveXML();
    return;
}

if ($dbConn->connect_errno)
{
    // A modified timestamp of zero is sent so the client gets the correct
    // message once the DB is fixed.
    appendTextNode($respXML, $callerlistEl, "modified", 0);
    appendTextNode($respXML, $callerlistEl, "message", "Unable to connect to the " .
            "database.  You may need to check your settings in " .
            "include/settings.php.  connect_error: " . $dbConn->connect_error);
    echo $respXML->saveXML();
    return;
}

// Act on the caller XML passed in (edit mode) and apply it before the queries
// that are returned.
if ($editMode && $updateXMLText)
{
    $inserted = false;
    $dbChanged = false;
    $updateXML = new DOMDocument();
    $updateXML->loadXML($updateXMLText);

    clLog("updateXML: " . $updateXMLText);

    $updatesEls = $updateXML->getElementsByTagName("updates");
    if ($updatesEls && $updatesEls->length)
    {
        $updatesEl = $updatesEls->item(0);
        $updates = $updatesEl->nodeValue;
    }

    $flashEls = $updateXML->getElementsByTagName("flash");
    if ($flashEls)
    {
        $flashEl = $flashEls->item(0);
        if ($flashEl)
        {
            $flash = $dbConn->real_escape_string($flashEl->nodeValue);
            $sql = "UPDATE cl_config " .
                   "SET flash = '$time'";
            clLog($sql);
            $dbResult = $dbConn->query($sql);
            $dbChanged = true;
        }
    }

    $messageEls = $updateXML->getElementsByTagName("message");
    if ($messageEls)
    {
        $messageEl = $messageEls->item(0);
        if ($messageEl)
        {
            $message = $dbConn->real_escape_string($messageEl->nodeValue);
            $sql = "UPDATE cl_config " .
                   "SET message = '$message'";
            clLog($sql);
            $dbResult = $dbConn->query($sql);
            $dbChanged = true;
        }
    }

    // Here something like XPath or retrieving "caller" directly might be more
    // succinct.  But always traversing from the trunk is straightforward.
    $callersEls = $updateXML->getElementsByTagName("callers");
    foreach ($callersEls as $callersEl)
    {
        foreach ($callersEl->childNodes as $callerEl)
        {
            $itemA = array();
            if (!$callerEl->childNodes)
            {
                continue; // Ignore whitespace.
            }
            foreach ($callerEl->childNodes as $itemEl)
            {
                $itemA[$itemEl->nodeName] = $dbConn->real_escape_string(
                    $itemEl->nodeValue);
            }

            // Note that the time is not sent.  The current time is used for
            // inserts.
            $id = $itemA["id"];
            $line = $itemA["line"];
            $priority = $itemA["priority"];
            $online = $itemA["online"];
            $name = $itemA["name"];
            $topic = $itemA["topic"];

            // Determine if the line specified is currently online
            $sql = "SELECT count(*) " .
                   "FROM   cl_callers " .
                   "WHERE  id     = '$id' " .
                   "AND    online = '1'";
            clLog($sql);
            $dbResult = $dbConn->query($sql);
            if ($dbResult && ($dbResult->num_rows == 1))
            {
                $dbRow = $dbResult->fetch_row();
                $callerOnline = $dbRow[0] > 0;
            }
            clLog("line $line online: $callerOnline");

            if (($online == 1) && !$callerOnline)
            {
                // First make sure that the all instances of the line in
                // question are off.
                $sql = "UPDATE cl_callers " .
                       "SET    online = '0' " .
                       "WHERE  line = '$line'";
                clLog($sql);
                $dbConn->query($sql);

                // The following is an inefficient way of limiting row build
                // up.  What's really needed is a something like a cron job
                // that deletes old rows from the cl_callers table.
                if (!$keepHistory)
                {
                    $sql = "DELETE FROM cl_callers " .
                           "WHERE  line = '$line'";
                    clLog($sql);
                    $dbConn->query($sql);
                }

                // Transitioning from offline to online.  The insert case.
                $sql = "INSERT INTO cl_callers " .
                        "    (online, line, priority, time, name, topic) " .
                        "VALUES ('$online', '$line', '$priority', '$time', " .
                        "        '$name', '$topic')";
                $inserted = true;
            }
            else
            {
                if ($inserted)
                {
                    // Within a single update it's possible that the updateXML
                    // will first insert a line and then later update it.
                    // In this case make sure $id is really the maximum value
                    // for the line specified.
                    $sql = "SELECT max(id) " .
                           "FROM   cl_callers " .
                           "WHERE  line = '$line'";
                    clLog($sql);
                    $dbResult = $dbConn->query($sql);
                    if ($dbResult && ($dbResult->num_rows == 1))
                    {
                        $dbRow = $dbResult->fetch_row();
                        $idOld = $id;
                        $id = $dbRow[0];
                        clLog("After insert id updated from $idOld to $id");
                    }
                }

                // Transitioning from online to offline or editing a
                // caller without changing his status.  The update case.
                $sql = "UPDATE cl_callers " .
                       "SET online   = '$online', " .
                       "    priority = '$priority', " .
                       "    name     = '$name', " .
                       "    topic    = '$topic' " .
                       "WHERE id = '$id'";
            }
            clLog($sql);
            $dbConn->query($sql);
            $dbChanged = true;
        }
    }

    if ($dbChanged)
    {
        // Update the modified timestamp.
        $sql = "UPDATE cl_config " .
               "SET modified = $time";
        clLog($sql);
        $dbConn->query($sql);
    }
}

// Get configuration information that is not per line.
$dbResult = $dbConn->query("SELECT * FROM cl_config");
if ($dbResult && ($dbResult->num_rows == 1))
{
    $dbRow = $dbResult->fetch_assoc();

    $flash  = $dbRow["flash"];
    $message  = $dbRow["message"];
    $modified = $dbRow["modified"];

    $dbResult->close();
}
else
{
    // If we get past this error it's unlikely we'll have additional DB
    // related errors.
    appendTextNode($respXML, $callerlistEl, "modified", -1);
    appendTextNode($respXML, $callerlistEl, "message", "Make sure that the " .
                   "cl_config table exists and that the user specified in " .
                   "include/settings.php has permission to read it.  Also, " .
                   "the cl_config table should have exactly one row.  Maybe " .
                   "you need an admin to setup the database via the admin " .
                   "page.");
    echo $respXML->saveXML();
    return;
}

appendTextNode($respXML, $callerlistEl, "modified", $modified);

// Return early if the client is up-to-date.  When the database is first
// created it has a modified time of 0.  When the client first starts it has
// a time of -1.  A comparison instead of inequality is used so it always
// loads if the database is recreated.
if ($cmod == $modified)
{
    echo $respXML->saveXML();
    return;
}

if (isset($updates))
{
    clLog("updates: " . $updates);
    appendTextNode($respXML, $callerlistEl, "updates", $updates);
}

appendTextNode($respXML, $callerlistEl, "flash", $flash);
appendTextNode($respXML, $callerlistEl, "message", $message);
appendTextNode($respXML, $callerlistEl, "numLines", $numLines);
appendTextNode($respXML, $callerlistEl, "pauseLimit", $pauseLimit);
appendTextNode($respXML, $callerlistEl, "refreshRate", $refreshRate);

$callersEl = $respXML->createElement("callers");
$callerlistEl->appendChild($callersEl);

// If we want to see all callers then we don't need a where clause.  Also
// sort strictly by date as it would be confusing otherwise.
if ($editMode)
{
    $sql = "SELECT * FROM cl_callers " .
           "WHERE id IN (" .
           "  SELECT max(id) " .
           "  FROM cl_callers GROUP BY line) " .
           "AND line <= $numLines " .
           "ORDER BY line";
}
else
{
    if ($allCallers)
    {
        $sql = "SELECT * FROM cl_callers " .
               "WHERE line <= $numLines " .
               "ORDER BY id DESC " .
               "LIMIT 100";
    }
    else
    {
        $sql = "SELECT * FROM cl_callers " .
               "WHERE online = 1 " .
               "  AND line <= $numLines " .
               "ORDER BY priority, id";
    }
}

clLog($sql);
$dbResult = $dbConn->query($sql);
while ($dbRow = $dbResult->fetch_assoc())
{
    $callerEl = $respXML->createElement("caller");
    $callersEl->appendChild($callerEl);

    appendTextNode($respXML, $callerEl, "id", $dbRow["id"]);
    appendTextNode($respXML, $callerEl, "line", $dbRow["line"]);
    appendTextNode($respXML, $callerEl, "time", $dbRow["time"]);
    appendTextNode($respXML, $callerEl, "priority", $dbRow["priority"]);
    appendTextNode($respXML, $callerEl, "online", $dbRow["online"]);
    appendTextNode($respXML, $callerEl, "name", $dbRow["name"]);
    appendTextNode($respXML, $callerEl, "topic", $dbRow["topic"]);
}
$dbResult->close();

echo $respXML->saveXML();
?>
