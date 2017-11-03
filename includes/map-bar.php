<?php

	  // load record from 'donate_bar'
	list($map_barRecords, $map_barMetaData) = getRecords(array(
		'tableName'   => 'map_bar',
		'where'       => '', // load first record
		'loadUploads' => true,
		'allowSearch' => false,
		'limit'       => '1',
	));
	$map_barRecord = @$map_barRecords[0]; // get first record
	if (!$map_barRecord) { dieWith404("Record not found!"); } // show error message if no record found

?>

<div class="callout">
	<h5><?php echo htmlencode($map_barRecord['title']) ?></h5> <?php echo htmlencode($map_barRecord['content']) ?><br />
	<a href="<?php echo htmlencode($map_barRecord['button_link']) ?>" class="btn btn-leaf btn-sm"><?php echo htmlencode($map_barRecord['button_text']) ?> <i class="fa fa-caret-right"></i></a>
</div>