<?php
// Although headers should be set first they are not sent until the entire 
// file is executed.
header("Location: index.html");

include_once "../include/common.php";

// If they can get here they have reached at least the crew auth level.
if ($_SESSION["cl_auth"] < CL_AUTH_CREW)
{
    $_SESSION["cl_auth"] = CL_AUTH_CREW;
} 
?>
