<?php


// return resized width, height, and imageType
function saveResampledImageAs($targetPath, $sourcePath, $maxWidth, $maxHeight, $crop = false) {
  global $SETTINGS;

  // error checking
  if (!$targetPath) { die(__FUNCTION__ . ": No targetPath specified! "); }

  // create target dir
  $dir = dirname($targetPath);
  if (!file_exists($dir)) {
    mkdir_recursive($dir) || die("Error creating dir '" .htmlencode($dir). "'.  Check permissions or try creating directory manually.");
  }

  // open source image
  $sourceImage = null;
  list($sourceWidth, $sourceHeight, $imageType) = getimagesize($sourcePath);

  // get new height/width
  $widthScale   = $maxWidth / $sourceWidth;
  $heightScale  = $maxHeight / $sourceHeight;
  $scaleFactor  = min($widthScale, $heightScale, 1);  # don't scale above 1:1
  $targetHeight = ceil($sourceHeight * $scaleFactor); # round up
  $targetWidth  = ceil($sourceWidth * $scaleFactor);  # round up

  if ($scaleFactor == 1) {
    if ($sourcePath != $targetPath) {
      copy($sourcePath, $targetPath) || die(__FUNCTION__ . ": error copying image '$sourcePath' - " . @$php_errormsg);
    }
    return array($sourceWidth, $sourceHeight, $imageType);
  }

  // create new image
  switch($imageType) {
    case IMAGETYPE_JPEG: $sourceImage = @imagecreatefromjpeg($sourcePath); break; // Use @ and ini_set('gd.jpeg_ignore_warning') in init.php to suppress gd invalid jpeg errors. See: http://bugs.php.net/bug.php?id=39918
    case IMAGETYPE_GIF:  $sourceImage = imagecreatefromgif($sourcePath); break;
    case IMAGETYPE_PNG:  $sourceImage = imagecreatefrompng($sourcePath); break;
    default:             die(__FUNCTION__ . ": Unknown image type for '$sourcePath'!"); break;
  }
  if (!$sourceImage) { die("Error opening image file!"); }
  
  // crop image - set x and y coordinates and override width and height variables
  $dst_x = 0;
  $dst_y = 0;
  if ($crop){
    $ratio = 1; // ratio when cropping the image
    $ow    = imagesx($sourceImage);
    $oh    = imagesy($sourceImage);
    if ($ow < $maxWidth || $oh < $maxHeight) {
      if ($ow < $oh) {
        $cropWidth  = $ow;
        $cropHeight = $ow*$ratio;
      }
      else {
        $cropWidth  = $oh/$ratio;
        $cropHeight = $oh;
      }
    }
    else {
      $cropWidth  = $maxWidth;
      $cropHeight = $maxHeight;
    }
    
    // resize factor
    $rsf = 1;
    $wrs = $ow/$cropWidth;
    $hrs = $oh/$cropHeight;
    if ($wrs > $hrs){
      $rsf = $hrs;
    }else{
      $rsf = $wrs;
    }
    
    // x and y coordinate of destination point
    $dst_x = ($ow/2)-($cropWidth*$rsf/2);
    $dst_y = ($oh/2)-($cropHeight*$rsf/2);
    
    // override width and height variables
    $targetWidth  = $cropWidth;
    $targetHeight = $cropHeight;
    $sourceWidth  = $cropWidth*$rsf;
    $sourceHeight = $cropHeight*$rsf;
  }

  $targetImage = imagecreatetruecolor($targetWidth, $targetHeight);
  _image_saveTransparency($targetImage, $sourceImage, $imageType);
  
  // resample image
  $quality = 4; // v2.60 Speed up resizing (was 5 previously)
  _fastimagecopyresampled($targetImage, $sourceImage, 0, 0, $dst_x,  $dst_y,  $targetWidth, $targetHeight, $sourceWidth, $sourceHeight, $imageType, $quality) || die("There was an error resizing the uploaded image!");

  // enable progressive JPEGs
  imageinterlace($targetImage, true);

  // save target image
  $savedFile = false;
  switch($imageType) {
    case IMAGETYPE_JPEG: $savedFile = imagejpeg($targetImage, $targetPath, $SETTINGS['advanced']['imageResizeQuality']); break;
    case IMAGETYPE_GIF:  $savedFile = imagegif($targetImage, $targetPath); break;
    case IMAGETYPE_PNG:  $savedFile = imagepng($targetImage, $targetPath); break;
    default:             die(__FUNCTION__ . ": Unknown image type for '$targetPath'!"); break;
  }
  if (!$savedFile) { die("Error saving file!"); }
  imagedestroy($sourceImage);
  imagedestroy($targetImage);

  //
  return array($targetWidth, $targetHeight, $imageType);
}

// from: http://ca2.php.net/manual/en/function.imagecopyresampled.php#77679
function _fastimagecopyresampled(&$dst_image, $src_image, $dst_x, $dst_y, $src_x, $src_y, $dst_w, $dst_h, $src_w, $src_h, $imageType, $quality = 3) {
  // Plug-and-Play _fastimagecopyresampled function replaces much slower imagecopyresampled.
  // Just include this function and change all "imagecopyresampled" references to "_fastimagecopyresampled" (and add $imageType argument)
  // Typically from 30 to 60 times faster when reducing high resolution images down to thumbnail size using the default quality setting.
  // Author: Tim Eckel - Date: 09/07/07 - Version: 1.1 - Project: FreeRingers.net - Freely distributable - These comments must remain.
  //
  // Optional "quality" parameter (defaults is 3). Fractional values are allowed, for example 1.5. Must be greater than zero.
  // Between 0 and 1 = Fast, but mosaic results, closer to 0 increases the mosaic effect.
  // 1 = Up to 350 times faster. Poor results, looks very similar to imagecopyresized.
  // 2 = Up to 95 times faster.  Images appear a little sharp, some prefer this over a quality of 3.
  // 3 = Up to 60 times faster.  Will give high quality smooth results very close to imagecopyresampled, just faster.
  // 4 = Up to 25 times faster.  Almost identical to imagecopyresampled for most images.
  // 5 = No speedup. Just uses imagecopyresampled, no advantage over imagecopyresampled.

  if (empty($src_image) || empty($dst_image) || $quality <= 0) { return false; }
  if ($quality < 5 && (($dst_w * $quality) < $src_w || ($dst_h * $quality) < $src_h)) {
    $temp = imagecreatetruecolor ($dst_w * $quality + 1, $dst_h * $quality + 1);
    _image_saveTransparency($temp, $src_image, $imageType);
    imagecopyresized ($temp, $src_image, 0, 0, $src_x, $src_y, $dst_w * $quality + 1, $dst_h * $quality + 1, $src_w, $src_h);
    imagecopyresampled ($dst_image, $temp, $dst_x, $dst_y, 0, 0, $dst_w, $dst_h, $dst_w * $quality, $dst_h * $quality);
    imagedestroy($temp);
  }
  else {
    imagecopyresampled($dst_image, $src_image, $dst_x, $dst_y, $src_x, $src_y, $dst_w, $dst_h, $src_w, $src_h);
  }

  return true;
}

// save transparency - based on code from: http://ca3.php.net/manual/en/function.imagecolortransparent.php#80935
function _image_saveTransparency(&$targetImage, &$sourceImage, $imageType) {
  if($imageType == IMAGETYPE_GIF) {
    $transparentIndex = imagecolortransparent($sourceImage);
    $transparentColor = @imagecolorsforindex($sourceImage, $transparentIndex);
    if ($transparentColor) {
      // Fix in progress: $newTransparentIndex = imagecolorallocatealpha($targetImage, $transparentColor['red'], $transparentColor['green'], $transparentColor['blue'], 127);
      $newTransparentIndex = imagecolorallocate($targetImage, $transparentColor['red'], $transparentColor['green'], $transparentColor['blue']);
      imagefill($targetImage, 0, 0, $newTransparentIndex);
      imagecolortransparent($targetImage, $newTransparentIndex);
    }
  }
  else if ($imageType == IMAGETYPE_PNG) {
    imagealphablending($targetImage, false);
    $transparentColor = imagecolorallocatealpha($targetImage, 0, 0, 0, 127);
    imagefill($targetImage, 0, 0, $transparentColor);
    imagesavealpha($targetImage, true);
  }

}

// potentially rotate a JPEG file with an EXIF Orientation flag so that future image processing doesn't inadvertently save it without the flag and without rotating it
function _image_fixJpegExifOrientation($imageFilePath) {
  
  // get image dimensions and type
  list($width, $height, $imageType) = getimagesize($imageFilePath);
  
  // if exif_read_data() isn't available, do nothing
  if (!extension_loaded('exif')) { return; }
  
  // if this isn't a JPEG, do nothing
  if ($imageType !== IMAGETYPE_JPEG) { return; }
  
  // read EXIF data
  $exif = exif_read_data($imageFilePath);
  
  // determine rotation required based on EXIF Orientation flag
  $rotationRequiredByOrientation = [ // https://secure.php.net/manual/en/function.exif-read-data.php#110894
    8 => 90,
    3 => 180,
    6 => -90,
  ];
  $rotationRequired = @$rotationRequiredByOrientation[$exif['Orientation']];
  
  // if we need to rotate by 0 degrees, do nothing
  if (!$rotationRequired) { return; }

  // determine final width and height
  $targetWidth  = ($rotationRequired === 180) ? $width  : $height;
  $targetHeight = ($rotationRequired === 180) ? $height : $width;
  
  // load and create image objects
  $sourceImage = @imagecreatefromjpeg($imageFilePath); // Use @ and ini_set('gd.jpeg_ignore_warning') in init.php to suppress gd invalid jpeg errors. See: http://bugs.php.net/bug.php?id=39918
  $targetImage = imagecreatetruecolor($targetWidth, $targetHeight);
  
  // rotate
  $targetImage = imagerotate($sourceImage, $rotationRequired, 0);

  // enable progressive JPEGs
  imageinterlace($targetImage, true);
  
  // save
  $savedFile = imagejpeg($targetImage, $imageFilePath, $GLOBALS['SETTINGS']['advanced']['imageResizeQuality']);
  if (!$savedFile) { die("Error saving file!"); }
  
  // clean up image objects
  imagedestroy($sourceImage);
  imagedestroy($targetImage);
}

// returns true if the file path or the file extension is an image
// ... set $testAsExt to true if we're checking a file extension
// ... it checks for 'gif', 'jpg', 'png', 'svg' image files
function isImage($filePathOrFileExtension, $testAsExt = false) {
  
  $isImage             = false;
  $imageFileExtensions = ['gif', 'jpg', 'png', 'svg'];
  $filePathOrExt       = strtolower($filePathOrFileExtension);
  
  if ($testAsExt && in_array($filePathOrExt, $imageFileExtensions)) { // if this is a file extension
    $isImage = true;
  }
  elseif (preg_match("/\.(gif|jpg|jpeg|png|svg)$/i", $filePathOrExt)){ // if this is a file name/path
    $isImage = true;
  }
  
  return $isImage;
}

// returns true if the image type is SVG
function isImage_SVG($filePath) {
  
  // get the file extension
  $fileName      = basename($filePath);
  $fileExtension = _saveUpload_getExtensionFromFileName($fileName);
  
  // return false if the file extension is neither svg nor tmp
  // note: we're allowing 'tmp' extension so we can also check the file that's converted into a temp file during the upload process
  if (!in_array($fileExtension, ['svg', 'tmp'])) { return false; }
  
  // return true if the <svg tag is found in the file contents
  $svgXML = @file_get_contents($filePath);
  if (preg_match("/<svg/i", $svgXML)) { return true; }
  
  // note: we're using the method above because getimagesize() can't be used to determine the image type as it returns blank
  // ... also, checking for mime type returns "image/svg+xml", but will also return "text/plain" if the doctype is not declared
  // ... DOCTYPE in SVG files isn't always declared
  
  return false;
}


//eof
