<?php header('Content-type: text/html; charset=utf-8'); ?>
<? /* php
	 if(isset($_POST['publishDate_min'])){$_POST['publishDate_min'] = date("YYYYMMDDHHMMSS", strtotime($_POST['publishDate_min']))};
	 if(isset($_POST['publishDate_max'])){$_POST['publishDate_max'] = date("YYYYMMDDHHMMSS", strtotime($_POST['publishDate_max']))};
 */ ?>
<?php
	/* STEP 1: LOAD RECORDS - Copy this PHP code block near the TOP of your page */

	// load viewer library
	$libraryPath = 'cmsb/lib/viewer_functions.php';
	$dirsToCheck = array('/home1/ericmedg/public_html/viers/','','../','../../','../../../');
	foreach ($dirsToCheck as $dir) { if (@include_once("$dir$libraryPath")) { break; }}
	if (!function_exists('getRecords')) { die("Couldn't load viewer library, check filepath in sourcecode."); }

	// load records from 'blog_entries'
	list($blog_entriesRecords, $blog_entriesMetaData) = getRecords(array(
		'tableName'   => 'blog_entries',
		'perPage'     => '5',
		'loadUploads' => true,
		'allowSearch' => true,
	));

	// load record from 'organizations'
	list($organizationsRecords, $organizationsMetaData) = getRecords(array(
		'tableName'   => 'organizations',
		'loadUploads' => true,
		'allowSearch' => false
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
						<a href="contact.html">
							Contact
						</a>
						 | 
						<a href="donate.html">
							Donate
						</a>
						 | 
						<a href="volunteer.html">
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
					<a href="blog.html" class="btn btn-primary active">
						Blog
					</a>
					<a href="about.html" class="btn btn-primary">
						About
					</a>
					<a href="contact.html" class="btn btn-primary">
						Contact
					</a>
					<a href="#" class="btn btn-primary">
						Donate
					</a>
				</div>
				<h1>Research Archive</h1>
				<div class="container content p-3">
					<div class="row">
						<div class="col-xs-12 col-md-5 col-lg-3">
							<form action="?" method="POST">
								<div class="input-group input-group-sm">
									<input class="form-control form-control-sm" type="search" name="title_keyword,organization_keyword,content_keyword" placeholder="Search" />
									<span class="input-group-btn">
										<button class="btn btn-leaf" type="submit">Go!</button>
									</span>
								</div>
							</form>
							<form action="?" method="POST">
								<div class="text-center mt-3">Filter Articles</div>
								<div class="form-group">
									<small><label for="exampleInputEmail1">Organization</label></small>
									<select class="form-control form-control-sm" name="organization">
										<option value=""></option>
										<?php foreach ($organizationsRecords as $organization): ?>
											<option value="<?php echo $organization['num'] ?>"><?php echo $organization['organization_name'] ?></option>
										<?php endforeach ?>
									</select>
								</div>
								<div class="form-group">
									<small><label for="exampleInputPassword1">Date Published</label></small>
									<div class="date-range">
										<div class="date-range-input">
											<div class="input-group input-group-sm">
												<span class="input-group-addon">
													<i class="fa fa-calendar"></i>
												</span>
												<input class="form-control form-control-sm" name="publishDate_min" data-provide="datepicker" data-date-format="YYYYMMDDHHMMSS" />
											</div>
										</div>
										<small>to</small>
										<div class="date-range-input">
											<div class="input-group input-group-sm">
												<span class="input-group-addon">
													<i class="fa fa-calendar"></i>
												</span>
												<input class="form-control form-control-sm" name="publishDate_max" data-provide="datepicker" data-date-format="YYYYMMDDHHMMSS" />
											</div>
										</div>
									</div>
								</div>
								<button type="submit" class="btn btn-sm btn-primary btn-block mb-3">Apply Filter</button>
							</form>
						</div>
						<div class="col-xs-12 col-md-7 col-lg-9">
							<?php foreach ($blog_entriesRecords as $record): ?>
								<?php foreach ($organizationsRecords as $organization): ?>
									<?php if ($organization['num'] == $record['organization']){
										$record['organization'] = $organization;
										break;
									} ?>
								<?php endforeach ?>
								<div class="blog-entry">
									<img src="http://placehold.it/200x120/" />
									<small><strong><?php echo date("F j, Y", strtotime($record['publishDate'])); ?><?php if($record['organization'] && $record['organization']['organization_url']){?> | <a href="<?php echo htmlencode($record['organization']['organization_url']) ?>" target="_blank"><?php echo $record['organization']['organization_name'] ?></a><?php } ?></strong></small>
									<h5><?php echo htmlencode($record['title']) ?></h5>
									<?php echo $record['abbreviated_content']; ?>
									<a href="/blog-entry.php?<?php echo htmlencode($record['num']) ?>" class="btn btn-leaf btn-sm mt-2">Read More <i class="fa fa-caret-right"></i></a>
								</div>
							<?php endforeach ?>
							<nav>
								<ul class="pagination pagination-sm justify-content-center">
									<li class="page-item disabled">
										<a class="page-link" href="#" aria-label="Previous">
											<span aria-hidden="true">&laquo;</span>
											<span class="sr-only">Previous</span>
										</a>
									</li>
									<li class="page-item disabled"><a class="page-link" href="#">1</a></li>
									<li class="page-item"><a class="page-link" href="#">2</a></li>
									<li class="page-item"><a class="page-link" href="#">3</a></li>
									<li class="page-item">
										<a class="page-link" href="#" aria-label="Next">
											<span aria-hidden="true">&raquo;</span>
											<span class="sr-only">Next</span>
										</a>
									</li>
								</ul>
							</nav>
						</div>
					</div>
				</div>
			</div>
			<div class="container">
				<div id="page-content-footer">
					VIERS &copy;2017
				</div>
			</div>
		</div>
	</div>
</body>
</html>