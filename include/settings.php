<?php
// Common configuration variables go in this file.

// Database configuration.  Currently only MySQL is supported.
$dbhost = "cl_domain";
$dbuser = "cl_user";
$dbpass = "cl_password";
$dbname = "cl_db";

// Set this to true for some additional logging in /var/log/httpd/error_log.
$debug = false;

// If true inserts are done when callers are put online.  When false the same
// rows are always reused so that the number of rows in cl_callers is always
// equal to the number of lines.
$keepHistory = true;

// The number of lines.  This determines how many lines the edit page may edit.
// It also determines the maximum line that may be returned by various queries.
$numLines = 3;

// The rate that clients refresh in milliseconds.
$refreshRate = 5000;
?>
