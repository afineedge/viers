<?php
	/* STEP 1: LOAD RECORDS - Copy this PHP code block near the TOP of your page */

	// load viewer library
	$libraryPath = '/cmsb/lib/viewer_functions.php';
	$dirsToCheck = array('/home1/ericmedg/public_html/viers/','','../','../../','../../../');
	foreach ($dirsToCheck as $dir) { if (@include_once("$dir$libraryPath")) { break; }}
	if (!function_exists('getRecords')) { die("Couldn't load viewer library, check filepath in sourcecode."); }

	function clean($string) {
	   $string = str_replace(' ', '-', $string); // Replaces all spaces with hyphens.

	   return preg_replace('/[^A-Za-z0-9]/', '', $string); // Removes special chars.
	}
?>