<?php header('Content-type: text/html; charset=utf-8'); ?>
<?php
	include $_SERVER['DOCUMENT_ROOT'] . '/includes/tools.php';

	// load record from 'homepage'
	list($homepageRecords, $homepageMetaData) = getRecords(array(
		'tableName'   => 'homepage',
		'where'       => '', // load first record
		'loadUploads' => true,
		'allowSearch' => false,
		'limit'       => '1',
	));
	$homepageRecord = @$homepageRecords[0]; // get first record
	if (!$homepageRecord) { dieWith404("Record not found!"); } // show error message if no record found

	// load records from 'articles'
	list($articlesRecords, $articlesMetaData) = getRecords(array(
		'tableName'   => 'articles',
		'loadUploads' => true,
		'allowSearch' => false,
		'where'		  => 'feature_on_homepage = "1"'
	));

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
					<a href="/" class="btn btn-primary active">
						Home
					</a>
					<a href="blog.php" class="btn btn-primary">
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
				<div class="container content p-3">
					<?php foreach ($homepageRecord['main_image'] as $index => $upload): ?>				
						<div class="hero-image hero-image-lg hero-image-captioned" style="background-image: url('<?php echo htmlencode($upload['urlPath']) ?>');">
							<div class="hero-image-caption">
								<h6><?php echo htmlencode(@$upload['info1']) ?></h6>
								<?php echo htmlencode(@$upload['info2']) ?>
							</div>
						</div>
					<?php endforeach; ?>
					<div class="container">
						<?php echo $homepageRecord['content']; ?>
					</div>
					<div class="row py-3">
						<div class="col-xs-12 col-md-4">
							<div class="text-center"><h5 class="underline"><?php echo htmlencode($homepageRecord['column_1_title']) ?></h5></div>
							<small><?php echo htmlencode($homepageRecord['column_1_content']) ?></small>
						</div>
						<div class="col-xs-12 col-md-4">
							<div class="text-center"><h5 class="underline"><?php echo htmlencode($homepageRecord['column_2_title']) ?></h5></div>
							<small><?php echo htmlencode($homepageRecord['column_2_content']) ?></small>
						</div>
						<div class="col-xs-12 col-md-4">
							<div class="text-center"><h5 class="underline"><?php echo htmlencode($homepageRecord['column_3_title']) ?></h5></div>
							<small><?php echo htmlencode($homepageRecord['column_3_content']) ?></small>
						</div>
					</div>
				</div>
				<!-- <div class="callout">
					<h5>Explore the Island</h5> Explore the Island  Curabitur nec arcu sollicitudin, volutpat leo sed, posuere neque.<br />
					<div class="row">
						<div class="col-xs-12 col-sm-6 col-md-4">
							<a href="#" class="btn btn-leaf btn-sm btn-block">2017 Summer Camps <i class="fa fa-caret-right"></i></a>
						</div>
						<div class="col-xs-12 col-sm-6 col-md-4">
							<a href="#" class="btn btn-leaf btn-sm btn-block">About the Island <i class="fa fa-caret-right"></i></a>
						</div>
						<div class="col-xs-12 col-sm-6 col-md-4">
							<a href="#" class="btn btn-leaf btn-sm btn-block">Map of VIERS <i class="fa fa-caret-right"></i></a>
						</div>
						<div class="col-xs-12 col-sm-6 col-md-4">
							<a href="#" class="btn btn-leaf btn-sm btn-block">Photo Gallery <i class="fa fa-caret-right"></i></a>
						</div>
						<div class="col-xs-12 col-sm-6 col-md-4">
							<a href="#" class="btn btn-leaf btn-sm btn-block">Research Project Archives <i class="fa fa-caret-right"></i></a>
						</div>
						<div class="col-xs-12 col-sm-6 col-md-4">
							<a href="#" class="btn btn-leaf btn-sm btn-block">How to Register <i class="fa fa-caret-right"></i></a>
						</div>
					</div>
				</div> -->
				<?php include $_SERVER['DOCUMENT_ROOT'] . '/includes/donate-bar.php'; ?>
				<div class="container content p-3">
					<div class="row pt-3">
						<?php foreach ($articlesRecords as $record): ?>
							<div class="col-xs-12 col-md-4">
								<div class="caption-box">
									<div class="caption">
										<?php echo htmlencode($record['title']) ?>
									</div>
									<?php foreach ($record['main_photo'] as $index => $upload): ?>
										<div class="caption-image" style="background-image: url('<?php echo htmlencode($upload['urlPath']) ?>');">
										</div>
									<?php endforeach; ?>
									<a href="<?php echo $record['_link'] ?>" class="btn btn-leaf btn-sm">Read More <i class="fa fa-caret-right"></i></a>
								</div>
							</div>
						<?php endforeach; ?>
					</div>
					<hr />
					<?php include $_SERVER['DOCUMENT_ROOT'] . '/includes/contact-information.php'; ?>
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
