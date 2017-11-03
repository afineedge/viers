<?php

	  // load record from 'donate_bar'
	list($donate_barRecords, $donate_barMetaData) = getRecords(array(
		'tableName'   => 'donate_bar',
		'where'       => '', // load first record
		'loadUploads' => true,
		'allowSearch' => false,
		'limit'       => '1',
	));
	$donate_barRecord = @$donate_barRecords[0]; // get first record
	if (!$donate_barRecord) { dieWith404("Record not found!"); } // show error message if no record found

?>

<div class="callout">
	<div class="row text-left">
		<div class="col-xs-12 col-md-9">
			<h5><?php echo htmlencode($donate_barRecord['title']) ?></h5><br />
			<?php echo htmlencode($donate_barRecord['content']) ?>
		</div>
		<div class="col-xs-12 col-md-3 vertical-center">
			<a href="<?php echo htmlencode($donate_barRecord['button_link']) ?>" class="btn btn-leaf btn-sm btn-block"><?php echo htmlencode($donate_barRecord['button_text']) ?> <i class="fa fa-caret-right"></i></a>
		</div>
	</div>
</div>