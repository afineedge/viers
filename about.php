<?php header('Content-type: text/html; charset=utf-8'); ?>
<?php
	include $_SERVER['DOCUMENT_ROOT'] . '/includes/tools.php';

	// load record from 'about_page'
	list($about_pageRecords, $about_pageMetaData) = getRecords(array(
		'tableName'   => 'about_page',
		'where'       => '', // load first record
		'loadUploads' => true,
		'allowSearch' => false,
		'limit'       => '1',
	));
	$about_pageRecord = @$about_pageRecords[0]; // get first record
	if (!$about_pageRecord) { dieWith404("Record not found!"); } // show error message if no record found

	  // load record from 'map_bar'
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
<!DOCTYPE HTML>
<html>
<head>
<meta charset="utf-8">
<title>Virgin Islands Environmental Research Center</title>
<meta name="viewport" content="width=device-width,initial-scale=1,maximum-scale=1">
<script src="http://code.jquery.com/jquery-3.0.0.min.js" type="text/javascript"></script>
<!-- Latest compiled and minified CSS -->
<link rel="stylesheet" href="media/css/styles.css" crossorigin="anonymous">
<!-- FontAwesome -->
<link rel="stylesheet" href="media/libs/font-awesome/css/font-awesome.min.css" crossorigin="anonymous">
<!-- FontAwesome -->
<link rel="stylesheet" href="media/libs/font-awesome/css/font-awesome.min.css" crossorigin="anonymous">
<!-- Bootstrap Datepicker -->
<link rel="stylesheet" href="media/libs/bootstrap-datepicker/css/bootstrap-datepicker.min.css" crossorigin="anonymous">
<script src="media/libs/bootstrap-datepicker/js/bootstrap-datepicker.min.js"></script>

<!-- Latest compiled and minified JavaScript -->
<script src="https://maxcdn.bootstrapcdn.com/bootstrap/3.3.7/js/bootstrap.min.js" integrity="sha384-Tc5IQib027qvyjSMfHjOMaLkfuWVxZxUPnCJA7l2mCWNIpG9mGCD8wGNIcPD7Txa" crossorigin="anonymous"></script>
 
<!-- FONTS -->
<!-- <link href='http://fonts.googleapis.com/css?family=Montserrat:400,700' rel='stylesheet' type='text/css'>
<link href='http://fonts.googleapis.com/css?family=Arvo' rel='stylesheet' type='text/css'> -->

<!-- APP METHODS -->
<script src="/media/js/tools.js" type="text/javascript"></script>
</head>

<body>
	<div id="wrapper">
		<div id="page-header">
			<div class="container">
				<div class="page-header-right">
					<div class="page-header-links">
						<a href="contact.php">
							Contact
						</a>
						 | 
						<a href="donate.php">
							Donate
						</a>
						 | 
						<a href="volunteer.php">
							Become a Volunteer
						</a>
					</div>
					<div class="page-icon page-icon-uvi">
						<strong>In Association With</strong><br/>
						<img src="/media/images/logo-uvi.jpg" />
					</div>
				</div>
				<div class="page-icon">
					<a href="/">
						<img src="/media/images/logo-white.png" />
					</a>
				</div>
			</div>
		</div>
		<div id="page-content">
			<div class="container mt-3">
				<div class="btn-group btn-group-justified" role="group" aria-label="Basic example">
					<a href="/" class="btn btn-primary">
						Home
					</a>
					<a href="blog.php" class="btn btn-primary">
						Blog
					</a>
					<a href="about.php" class="btn btn-primary active">
						About
					</a>
					<a href="contact.php" class="btn btn-primary">
						Contact
					</a>
					<a href="#" class="btn btn-primary">
						Donate
					</a>
				</div>
				<h1><?php echo htmlencode($about_pageRecord['title']) ?></h1>
				<div class="container content p-3">
					<?php foreach ($about_pageRecord['main_image'] as $index => $upload): ?>
						<div class="hero-image hero-image-lg" style="background-image: url('<?php echo htmlencode($upload['urlPath']) ?>');">
						</div>
					<?php endforeach; ?>
					<?php echo $about_pageRecord['content_1']; ?>
					<div class="image-grid row pb-3">
						<?php foreach ($about_pageRecord['image_grid'] as $index => $upload): ?>
							<div class="col-xs-6 col-sm-4">
								<img src="<?php echo htmlencode($upload['urlPath']) ?>" />
							</div>
						<?php endforeach; ?>
					</div>
					<?php echo $about_pageRecord['content_2']; ?>
				</div>
				<?php include $_SERVER['DOCUMENT_ROOT'] . '/includes/map-bar.php'; ?>
				<div class="container content p-3">
					<?php echo $about_pageRecord['content_3']; ?>
				</div>
			</div>
			<div class="container">
				<div id="page-content-footer">
					VIERS &copy;<?php echo date("Y"); ?>
				</div>
			</div>
		</div>
	</div>
</body>
</html>
