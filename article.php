<?php header('Content-type: text/html; charset=utf-8'); ?>
<?php
	include $_SERVER['DOCUMENT_ROOT'] . '/includes/tools.php';

	// load record from 'articles'
	list($articlesRecords, $articlesMetaData) = getRecords(array(
		'tableName'   => 'articles',
		'where'       => whereRecordNumberInUrl(0),
		'loadUploads' => true,
		'allowSearch' => false,
		'limit'       => '1',
	));
	$articlesRecord = @$articlesRecords[0]; // get first record
	if (!$articlesRecord) { dieWith404("Record not found!"); } // show error message if no record found

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
					<a href="blog.php" class="btn btn-primary active">
						Blog
					</a>
					<a href="about.php" class="btn btn-primary">
						About
					</a>
					<a href="contact.php" class="btn btn-primary">
						Contact
					</a>
					<a href="#" class="btn btn-primary">
						Donate
					</a>
				</div>
				<h1>Research Archive</h1>
				<div class="container content p-3">
					<div class="blog-full px-3">
						<small><strong><?php echo date("F j, Y", strtotime($articlesRecord['publishDate'])); ?> | <?php echo $articlesRecord['author'] ?></strong></small>
						<h5><?php echo htmlencode($articlesRecord['title']) ?></h5>
						<?php foreach ($articlesRecord['main_photo'] as $index => $upload): ?>
							<img src="<?php echo htmlencode($upload['urlPath']) ?>" />
						<?php endforeach ?>
						<?php echo $articlesRecord['content']; ?>
						<a href="<?php echo $articlesMetaData['_listPage'] ?>" class="btn btn-leaf btn-sm mt-3"><i class="fa fa-caret-left"></i> Return to Archive</a>
					</div>
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
