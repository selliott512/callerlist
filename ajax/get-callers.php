<?php
// This file provides an AJAX interface to caller information.  That is, 
// JavaScript clients may post a timestamp and get a list of newer call 
// isformatino, if any, in XML.
// 
// This software is subject to the GPLv2 or later.  See then enclosed "LICENSE"
// file in the "doc" directory for details.  See the "README" file for 
// contact information.

// Do this before anything else.
header('Content-type: text/xml');

include "../include/database.php";

function getMsec()
{
    list($usec, $sec) = explode(" ", microtime());
    return  $sec . substr($usec, 2, 3);
}

function appendTextNode($dom, $prnt, $name, $value)
{
    $el = $dom->createElement($name);
    $prnt->appendChild($el);

    $tn = $dom->createTextNode($value);
    $el->appendChild($tn);
}

// Clients modifed timestamp.
$cmod = isset($_POST["modified"]) ? $_POST["modified"] : 0; // Client TS.
$allCallers = isset($_POST["allCallers"]) ? $_POST["allCallers"] : "";
$allCallers = ($allCallers == "true");

$time = getMsec();

$dom = new DOMDocument( );
$dom->preserveWhiteSpace = false;
$dom->formatOutput = true;

$cinfoEl = $dom->createElement("cinfo");
$dom->appendChild($cinfoEl);
{
    // Get configuration information that is not per line.
    $dr = mysql_query("select * from tv_config");
    if ($dr && (mysql_num_rows($dr) > 0))
    {
        $rs = mysql_fetch_array($dr);
        
        if (isset($rs["flash"]))
        {
            // This could be done with a single statement, but for my testing
            // I ended up with DBs that had some of the columns, but not all.
            // Also, the following column adds should be the only place that
            // this PHP files alters the DB.
            
            if (!isset($rs["modified"]))
            {
                // Update the database be adding the modified column.
                mysql_query("alter table tv_config 
                             add column (
                                 modified bigint default 0)");
            }
            if (!isset($rs["flash_ts"]))
            {
                // Update the database be adding the flash_ts column.
                mysql_query("alter table tv_config 
                             add column (
                                 flash_ts bigint default 0)");
            }
        }
    
        $flashTs  = $rs["flash_ts"];
        if (!$flashTs)
        {
            $flashTs = 0;
        }
        $message  = $rs["message"];
        $modified = $rs["modified"];
    }

    appendTextNode($dom, $cinfoEl, "modified", $modified);
    appendTextNode($dom, $cinfoEl, "time", $time);

    // It must be up-to-date.
    if ($cmod >= $modified)
    {
        echo $dom->saveXML();
        return;
    }

    appendTextNode($dom, $cinfoEl, "flash", $flashTs);
    appendTextNode($dom, $cinfoEl, "message", $message);
    
    // If we want to see all callers then we don't need a where clause.  Also
    // sort strictly by date as it would be confusing otherwise.
    if ($allCallers)
    {
        $sql = "select * from tv_callers
                order by called desc
                limit 100";
    }
    else
    {
        $sql = "select * from tv_callers
                where online=1
                order by priority,called";
    }
    $dr = mysql_query($sql);

    $linesEl = $dom->createElement("lines");
    $cinfoEl->appendChild($linesEl);
    {
        while ($rs = mysql_fetch_array($dr))
        {
            $lineEl = $dom->createElement("line");
            $linesEl->appendChild($lineEl);
            
            if ($rs["online"])
            {
                $priority = $rs["priority"];
            }
            else
            {
                // Consider all offline callers to be "old".
                $priority = -1;
            }
 
            appendTextNode($dom, $lineEl, "number", $rs["line"]);
            appendTextNode($dom, $lineEl, "priority", $priority);
            appendTextNode($dom, $lineEl, "time", 1000 * $rs["called"]);
            appendTextNode($dom, $lineEl, "name", $rs["caller_name"]);
            appendTextNode($dom, $lineEl, "topic", $rs["topic"]);
        }
    }
}

echo $dom->saveXML();
?>
