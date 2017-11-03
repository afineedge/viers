<?php

	// load record from 'contact_information'
	list($contact_informationRecords, $contact_informationMetaData) = getRecords(array(
		'tableName'   => 'contact_information',
		'where'       => '', // load first record
		'loadUploads' => true,
		'allowSearch' => false,
		'limit'       => '1',
	));
	$contact_informationRecord = @$contact_informationRecords[0]; // get first record
	if (!$contact_informationRecord) { dieWith404("Record not found!"); } // show error message if no record found

?>

<div class="row">
	<div class="col-xs-12 col-md-6 pb-3">
		<h5>Contact Us On Site</h5>
		<?php echo htmlencode($contact_informationRecord['on_site_contact_name']) ?><br/ >
		<?php echo htmlencode($contact_informationRecord['on_site_contact_title']) ?><br/ >
		<a href="mailto:<?php echo htmlencode($contact_informationRecord['on_site_contact_email']) ?>?subject=VIERS"><?php echo htmlencode($contact_informationRecord['on_site_contact_email']) ?></a><br/>
		<a href="tel:<?php echo clean(htmlencode($contact_informationRecord['on_site_contact_phone'])) ?>"><?php echo htmlencode($contact_informationRecord['on_site_contact_phone']) ?></a><br /><br/>

		<strong>Mailing Address:</strong><br />
		<?php echo $contact_informationRecord['on_site_mailing_address'] ?>
	</div>
	<div class="col-xs-12 col-md-6">
		<h5>Contact Us Stateside</h5>
		<?php echo htmlencode($contact_informationRecord['stateside_contact_name']) ?><br/ >
		<?php echo htmlencode($contact_informationRecord['stateside_contact_title']) ?><br/ >
		<a href="mailto:<?php echo htmlencode($contact_informationRecord['stateside_contact_email']) ?>?subject=VIERS"><?php echo htmlencode($contact_informationRecord['stateside_contact_email']) ?></a><br/>
		<a href="tel:<?php echo clean(htmlencode($contact_informationRecord['stateside_contact_phone'])) ?>"><?php echo htmlencode($contact_informationRecord['stateside_contact_phone']) ?></a><br /><br/>

		<strong>Mailing Address:</strong><br />
		<?php echo $contact_informationRecord['stateside_mailing_address'] ?>
	</div>
</div>