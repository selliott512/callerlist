<?php
// Common configuration variables go in this file.

// Database configuration.  Since PDO is used refer to it's documentation
// for more information.  But basic configuration should be obvious from
// the example provided.
$pdo_dsn = "pgsql:host=cl_domain;dbname=cl_db";
$dbuser = "cl_user";
$dbpass = "cl_password";

// The following only has an effect for databases that support schemas, such
// as PostgreSQL.  It has no effect on other databases.  Leave it set to ""
// for the default behaviour (usually this means using the "public" schema).
$dbschema = "";

// Set this to true for some additional logging in /var/log/httpd/error_log.
$debug = false;

// If true inserts are done when callers are put online.  When false the same
// rows are always reused so that the number of rows in cl_callers is always
// equal to the number of lines.
$keepHistory = true;

// The number of lines.  This determines how many lines the edit page may edit.
// It also determines the maximum line that may be returned by various queries.
$numLines = 3;

// Limit in minutes after which the browser pauses itself.  This is to prevent
// bandwidth from being wasted by forgotten browsers.  Set to 0 to disable.
$pauseLimit = 180;

// The rate that clients refresh in milliseconds.
$refreshRate = 5000;
?>
