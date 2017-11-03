<?php header('Content-type: text/html; charset=utf-8'); ?>
<?php
	if (@$_REQUEST['publishDate_min']){
		$publishDate_min = $_REQUEST['publishDate_min'];
		$_REQUEST['publishDate_min'] = date("Y-m-d", strtotime(@$_REQUEST['publishDate_min']));
	}

	if (@$_REQUEST['publishDate_max']){
		$publishDate_max = $_REQUEST['publishDate_max'];
		$_REQUEST['publishDate_max'] = date("Y-m-d", strtotime(@$_REQUEST['publishDate_max']));
	}
	
	include $_SERVER['DOCUMENT_ROOT'] . '/includes/tools.php';

	// load records from 'articles'
	list($articlesRecords, $articlesMetaData) = getRecords(array(
		'tableName'   => 'articles',
		'perPage'     => '5',
		'loadUploads' => true,
		'allowSearch' => true
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
					<div class="row">
						<div class="col-xs-12 col-md-5 col-lg-3">
							<form action="?" method="POST">
								<div class="input-group input-group-sm">
									<input class="form-control form-control-sm" type="search" name="title,organization,content_keyword" placeholder="Search" />
									<span class="input-group-btn">
										<button class="btn btn-leaf" type="submit">Go!</button>
									</span>
								</div>
							</form>
							<form action="?" method="POST">
								<div class="text-center mt-3">Filter Articles</div>
								<div class="form-group">
									<small><label for="exampleInputPassword1">Date Published</label></small>
									<div class="input-group input-group-sm">
										<span class="input-group-addon">
											<i class="fa fa-calendar"></i>
										</span>
										<input class="form-control form-control-sm" name="publishDate_min" data-provide="datepicker" data-date-format="mm-dd-yyyy" value="<?php echo @$publishDate_min; ?>" />
									</div>
									<div class="text-center">
										<small>to</small>
									</div>
									<div class="input-group input-group-sm">
										<span class="input-group-addon">
											<i class="fa fa-calendar"></i>
										</span>
										<input class="form-control form-control-sm" name="publishDate_max" data-provide="datepicker" data-date-format="mm-dd-yyyy" value="<?php echo @$publishDate_max; ?>" />
									</div>
								</div>
								<button type="submit" class="btn btn-sm btn-primary btn-block mb-3">Apply Filter</button>
							</form>
						</div>
						<div class="col-xs-12 col-md-7 col-lg-9">
							<?php foreach ($articlesRecords as $record): ?>
								<div class="blog-entry">
									<img src="http://placehold.it/200x120/" />
									<small><strong><?php echo date("F j, Y", strtotime($record['publishDate'])); ?><?php echo $record['author'] ?></strong></small>
									<h5><?php echo htmlencode($record['title']) ?></h5>
									<?php echo $record['abbreviated_content']; ?>
									<a href="<?php echo $record['_link'] ?>" class="btn btn-leaf btn-sm mt-2">Read More <i class="fa fa-caret-right"></i></a>
								</div>
							<?php endforeach ?>
							<nav>
								<ul class="pagination pagination-sm justify-content-center">

									<?php if ($articlesMetaData['prevPage']): ?>
										<li class="page-item">
											<a class="page-link" href="<?php echo $articlesMetaData['prevPageLink'] ?>" aria-label="Previous">
												<span aria-hidden="true">&laquo;</span>
												<span class="sr-only">Previous</span>
											</a>
										</li>
									<?php else: ?>
										<li class="page-item disabled">
											<a class="page-link" href="#" aria-label="Previous">
												<span aria-hidden="true">&laquo;</span>
												<span class="sr-only">Previous</span>
											</a>
										</li>
									<?php endif ?>

									<li class="page-item disabled"><a class="page-link" href="#">Page <?php echo $articlesMetaData['page'] ?> of <?php echo $articlesMetaData['totalPages'] ?></a></li>

									<?php if ($articlesMetaData['nextPage']): ?>
										<li class="page-item">
											<a class="page-link" href="<?php echo $articlesMetaData['nextPageLink'] ?>" aria-label="Next">
												<span aria-hidden="true">&raquo;</span>
												<span class="sr-only">Next</span>
											</a>
										</li>
									<?php else: ?>
										<li class="page-item disabled">
											<a class="page-link" href="#" aria-label="Next">
												<span aria-hidden="true">&raquo;</span>
												<span class="sr-only">Next</span>
											</a>
										</li>
									<?php endif ?>
								</ul>
							</nav>
						</div>
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
