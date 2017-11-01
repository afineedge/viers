<?php

// Return a normalized absolute filepath.  Resolves ./ and ../ references, removes double slashes, replace backslash with forward slash.
// Supports non-existant paths (unlike realpath()), C: style windows drive letters and \\UNC paths.
// Usage: $absPath = absPath($inputPath, $optionalBaseDir);  // returned path will not have a trailing slash
// Usage: $absPath = absPath('myfile.jpg');                     // get path relative to path of current directory (often script's directory, but not always in CLI)
// Usage: $absPath = absPath('myfile.jpg', __DIR__);  // get path relative to path of current php script
// Usage: $absPath = absPath('myfile.jpg', SCRIPT_DIR);         // get path relative to path of cms directory
function absPath($inputPath, $baseDir = '.') {

  // add basedir to inputPath if it's not absolute
  if (!isAbsPath($inputPath)) {
    if (!isAbsPath($baseDir)) { // make basedir absolute if it's not already
      $cwd = getcwd();
      if (!isAbsPath($cwd)) { die("getcwd() didn't return an absulte path '" .htmlencode($cwd). "'!"); }
      $baseDir = absPath($baseDir, $cwd);
    }
    $inputPath = "$baseDir/$inputPath";
  }

  // remove path prefixes: \\UNC-SERVER or C:
  $uncServerPrefix   = '';
  $driveLetterPrefix = '';
  $uncServerRegexp   = "|^\\\\\\\\[^\\\/]+|";   // matches \\SERVER-NAME UNC style prefixs
  $driveLetterRegexp = "|^[a-z]:(?=[\\\/])|i";  // matches W: windows drive letter prefixs
  if (preg_match($uncServerRegexp, $inputPath, $matches)) { // match prefix
    $uncServerPrefix = $matches[0];
    $inputPath       = preg_replace($uncServerRegexp, '', $inputPath, 1); // remove prefix
  }
  elseif (preg_match($driveLetterRegexp, $inputPath, $matches)) { // match prefix
    $driveLetterPrefix = $matches[0];
    $inputPath         = preg_replace($driveLetterRegexp, '', $inputPath, 1); // remove prefix
  }

  // normalize path components (replace backslashes, remove double-slashes, resolve . and ..)
  $inputPathComponents  = preg_split("|[\\\/]|" , $inputPath, null, PREG_SPLIT_NO_EMPTY);
  $outputPathComponents = array();
  foreach ($inputPathComponents as $component) {
    if     ($component == '.')  { /* do nothing */ }                  // skip current dir references
    elseif ($component == '..') { array_pop($outputPathComponents); } // remove last path component for .. parent dir references
    else                        { array_push($outputPathComponents, $component); }
  }
  $outputPath = implode('/', $outputPathComponents);

  // re-add path prefixes and root slash
  $absPath = $uncServerPrefix . $driveLetterPrefix . '/' . $outputPath;

  //
  return $absPath;
}

// return true for absolute paths and false for relative paths
// Returns true on /leadingSlash, \leadingBackslash, \\UNC-SERVERS, and W:\windows\drives
function isAbsPath($path) {
  if (preg_match('|^[\\\/]|', $path))           { return true; } // leading slash or backslash (for windows)
  if (preg_match('|^[a-zA-Z]:[\\\/]|', $path))  { return true; } // windows drive
  if (preg_match('|^\\\\[a-zA-Z]|', $path))     { return true; } // unc path
  return false;
}

//
function getFirstDefinedValue() {

  foreach (func_get_args() as $arg) {
    if (isset($arg)) { return $arg; }
  }
}

// for checkboxes, prints checked="checked" if fieldValue matches testValue
function checkedIf($fieldValue, $testValue, $returnValue = 0) {
  $attr = 'checked="checked"';
  if (strval($fieldValue) == strval($testValue)) { // use strval so: 0 != ''
    if ($returnValue) { return $attr; }
    else              { echo $attr; }
  }
}


// full pulldowns and radios, prints selected="selected" if fieldValue matches testValue
function selectedIf($fieldValue, $testValue, $returnValue = 0) {
  $attr = 'selected="selected"';
  if (strval($fieldValue) == strval($testValue)) { // use strval so: 0 != ''
    if ($returnValue) { return $attr; }
    else              { echo $attr; }
  }
}


function showExecuteSeconds($return = false) {
  $value = sprintf("%0.2f", microtime(true) - $_SERVER['REQUEST_TIME_FLOAT']);
  if ($return) { return $value; }
  else         { print $value; }
}

// show build and license info
function showBuildInfo() {
  global $APP, $SETTINGS;

  # NOTE: Disabling or modifying licensing or registration code violates your license agreement and is willful copyright infringement.
  # NOTE: Copyright infringement can be very expensive: http://en.wikipedia.org/wiki/Statutory_damages_for_copyright_infringement
  # NOTE: Please do not steal our software.

  // display build info
  echo "\n<!--\n";

  if (isset($_SERVER['QUERY_STRING']) && sha1($_SERVER['QUERY_STRING']) == '3831b0376dab413292f03dd30523e749bdd3279e') {
    $buildId = join('.', array($APP['build'], $APP['id'], isValidProductId($SETTINGS['licenseProductId'])));
    echo "{$SETTINGS['programName']} v{$APP['version']} (Build: $buildId)\n";
    echo "Licensed to: {$SETTINGS['licenseCompanyName']} ~ {$SETTINGS['licenseDomainName']}\n";
  }

  echo "Execute time: " . showExecuteSeconds('return') . " seconds\n";
  echo "-->\n";

}


// download a web page
// list($html, $httpStatusCode, $headers, $request) = getPage($url);
function getPage($url, $connectTimeout = 5, $headers = array(), $POST = false, $responseBody = '') {
  global $SETTINGS;

  $html             = null;
  $httpStatusCode   = null;
  $httpHeaders      = null;
  if (!$connectTimeout) { $connectTimeout = 5; }
  if (!$headers)        { $headers = array(); }

  // use an http proxy server?
  $httpProxy = @$SETTINGS['advanced']['httpProxyServer'];
  $targetUrl = '';
  if ($httpProxy) {
    $targetUrl = $url;
    $url       = $httpProxy;
  }

  // is secure connection?
  $parsedUrl = parse_url($url);
  if     (@$parsedUrl['scheme'] == 'http')  { $isSSL = false; }
  elseif (@$parsedUrl['scheme'] == 'https') { $isSSL = true; }
  else { die(__FUNCTION__ . ": Url must start with http:// or https://!\n"); }
  if ($isSSL && !function_exists('openssl_open')) { die(__FUNCTION__ .": You must install the php openssl extension to be able to access https:// urls"); }

  // get port
  if (@$parsedUrl['port']) { $port = $parsedUrl['port']; }
  elseif ($isSSL)          { $port = 443; }
  else                     { $port = 80; }

  // open socket
  $scheme = $isSSL ? 'ssl://' : 'tcp://';

  //$ip  = gethostbyname($parsedUrl['host']); // get around PHP IPv6 bugs by connecting to IPv4 IP directly, Host: header is sent below regardless.
  //$handle = @fsockopen("$scheme$ip", $port, $errno, $errstr, $connectTimeout);
  // v2.64 - We can't do this anymore PHP 5.6+ now fails on invalid certificates: http://php.net/manual/en/migration56.openssl.php
  // ... and sometimes valid certificates: https://bugs.php.net/bug.php?id=68265
  // ... which causes fsockopen to fail if cert is invalid or hostname dosn't match: http://php.net/manual/fr/function.fsockopen.php#115405
  // So while connecting to the IP forces the server to use IPv4 and bypasses any potential IPv6 misconfigurations, it causes fsockopen to
  // fail on SSL sites because the IP won't match the certificate name

  // v2.64 - instead of using @ we disable 'display_errors' to cause errors to get logged because fsockopen can return multiple errors related to SSL certificate issues that aren't all captured in $php_errormsg
  // FUTURE: Consider switching to stream_socket_client from fsockopen so we can specify stream context options related to certificate checking when needed
  $old_display_errors = ini_set('display_errors', '0');
  $handle = fsockopen($scheme . $parsedUrl['host'], $port, $errno, $errstr, $connectTimeout);
  ini_set('display_errors', $old_display_errors);

  if (!$handle) {
    $isSocketsDisabled = ($errno == 0   || $errstr == "The operation completed successfully." ||
                          $errno == 1   || $errstr == "Operation not permitted" ||
                          $errno == 11  || $errstr == "Resource temporarily unavailable" ||
                          $errno == 13  || $errstr == "Permission denied" ||
                          $errno == 22  || $errstr == "Invalid argument" ||
                          $errno == 60  || $errstr == "Operation timed out" ||
                          $errno == 110 || $errstr == "Connection timed out" ||
                          $errno == 111 || $errstr == "Connection refused" ||
                          $errno == 10060 || $errstr == "A connection attempt failed because the connected party did not properly respond after a period of time, or established connection failed because connected host has failed to respond.");
    if ($isSocketsDisabled) { return(array(NULL,NULL,NULL,NULL)); }
    die(__FUNCTION__ . ": Error opening socket: $errstr ($errno)\n");
  }

  // set timeout
  $readWriteTimeout = 15;
  stream_set_timeout($handle, $readWriteTimeout);

  // if we're using an http proxy...
  if ($httpProxy) {
    $url       = $targetUrl;
    $parsedUrl = parse_url($url);
  }

  // determine request path (default to /, add the query string if this is a GET request, or if there was a query string and response body supplied)
  $path = @$parsedUrl['path'] ? $parsedUrl['path'] : '/';
  if (@$parsedUrl['query'] && (!$POST || $responseBody)) { $path .= "?{$parsedUrl['query']}"; }

  // set default headers
  $headers['Connection'] = 'close'; // Force HTTP/1.1 connection to close after request
  if (@$parsedUrl['user'] && @$parsedUrl['pass'] &&
      !@$headers['Authorization'])           { $headers['Authorization']  = "Basic " .base64_encode("{$parsedUrl['user']}:{$parsedUrl['pass']}"); };
  if (!@$headers['User-Agent'])              { $headers['User-Agent']     = "Mozilla/4.0 (compatible; MSIE 8.0; Windows NT 6.1; Trident/4.0)"; } // ref: http://blogs.msdn.com/ie/archive/2009/01/09/the-internet-explorer-8-user-agent-string-updated-edition.aspx
  if (!@$headers['Host'])                    { $headers['Host']           = $parsedUrl['host']; }
  if ($POST && !strlen($responseBody))       { $responseBody = @$parsedUrl['query']; }
  if ($POST && !@$headers['Content-Type'])   { $headers['Content-Type']   = 'application/x-www-form-urlencoded'; }
  if ($POST && !@$headers['Content-Length']) { $headers['Content-Length'] = strlen( $responseBody ); }

  // send request
  $method      = $POST ? 'POST' : 'GET';
  $httpVersion = "HTTP/1.1"; // Note: Paypal requires HTTP/1.1 headers: https://www.paypal-knowledge.com/infocenter/index?page=content&id=FAQ1488&pmv=print&impressions=false&viewlocale=en_US
  $request     = "$method $path $httpVersion\r\n"; 
  foreach ($headers as $name => $value) { $request .= "$name: $value\r\n"; }
  $request .= "\r\n";
  if ($POST) { $request .= $responseBody . "\r\n\r\n"; }
  fwrite($handle, $request);

  // get response
  $header         = null;
  $html           = null;
  $httpStatusCode = null;
  $response       = '';
  socket_set_blocking($handle, false); // use this or fgets() will block forever if no data available - even ignoring set_time_limit(###) limits - this happens when the other side doesn't close the connection or we send HTTP/1.1 by accident
  while (!feof($handle)) {
    $buffer = @fgets($handle, 128); // hide errors from: http://bugs.php.net/23220 and also see "Warning" box on http://php.net/file-get-contents
    if (!isset($buffer)) { break; } // prevent infinite loops on fgets errors
    $response .= $buffer;
  }

  if ($response) {
    list($header, $html) = preg_split("/(\r?\n){2}/", $response, 2);
    if (preg_match("/^HTTP\S+ (\d+) /", $header, $matches)) { $httpStatusCode = $matches[1]; }
  }

  // close socket
  fclose($handle);
  
  // decode chunked response body if Transfer-Encoding = chunked
  if (preg_match("/Transfer-Encoding:(\s*)chunked/i", $header)) {
    $html = _getPage_chunkedDecode($html);
  }

  //
  return(array($html, $httpStatusCode, $header, $request));
}

// decode chunked response: HTTP/1.1 requires support for chunked response: see end of section 3.6.1 of http://www.ietf.org/rfc/rfc2616.txt
// .. reference: http://stackoverflow.com/a/10859409
function _getPage_chunkedDecode($reponseBody) {
  for ($decodedResponse = ''; !empty($reponseBody); $reponseBody = trim($reponseBody)) {
    $lineEndingPosition = strpos($reponseBody, "\r\n");
    $lineLength         = hexdec(substr($reponseBody, 0, $lineEndingPosition));
    $decodedResponse   .= substr($reponseBody, $lineEndingPosition + 2, $lineLength);
    $reponseBody        = substr($reponseBody, $lineEndingPosition + 2 + $lineLength);
  }
  return $decodedResponse;
}

// return HTTP response for GET request using cURL library
// $url          - url to request
// $queryParams  - (optional) lists any query string values to be sent, set to null for none
// $requestInfo  - (optional) gets set by the function and contains info about the request
// $curl_options - (optional) lets you pass or override cURL options
/*
  // Simple Usage
  $response = curl_get("http://www.example.com/", [
    'id'     => $id,
    'token'  => $token,
  ], $requestInfo);
  if ($requestInfo['error'])               { die("Error loading page: {$requestInfo['error']}"); }
  if ($requestInfo['http_code'] != '200')) { die("Unexpected server response (HTTP {$requestInfo['http_code']})"); }

  // Advanced Usage
  $queryParams  = ['id' => $id, 'token' => $token];
  $curl_options = [CURLOPT_USERAGENT => "API v1.00", CURLOPT_TIMEOUT => 10];
  $response     = curl_get("http://www.example.com/", $queryParams, $requestInfo, $curl_options);
  if ($requestInfo['error'])              { die("Error loading page: {$requestInfo['error']}"); }
  if ($requestInfo['http_code'] != '200') { die("Unexpected server response (HTTP {$requestInfo['http_code']})"); }
*/
function curl_get($url, $queryParams = [], &$requestInfo = [], $curl_options = []) {

  // add query string to url
  $queryString = "";
  if (!empty($queryParams)) {
    if (!is_array($queryParams))  { dieAsCaller("queryParams must be empty or an array!"); }
    if (preg_match("/\?/", $url)) { dieAsCaller("URL cannot contain ? if queryParams are specified!"); }
    $queryString = http_build_query($queryParams, null, '&', PHP_QUERY_RFC3986);
    if ($queryString) { $queryString = "?$queryString"; }
  }

  // CURL_OPTIONS -  Reference: http://php.net/manual/en/function.curl-setopt.php
  $curl_defaults = [ // you can override these with the $curl_options argument
    CURLOPT_URL            => "$url$queryString",
    CURLOPT_RETURNTRANSFER => 1,                       // curl_exec should return HTTP body (instead of sending it to STDOUT)
    CURLOPT_HEADER         => 1,                       // curl_exec should return HTTP header
    CURLOPT_CONNECTTIMEOUT => 2,                       // seconds to wait while connecting
    CURLOPT_TIMEOUT        => 4,                       // total max seconds for cURL execution
    CURLINFO_HEADER_OUT    => 1,                       // adds 'request_header' to curl_getinfo() output

    // SSL Settings:
    CURLOPT_SSL_VERIFYPEER => true,                    // verify peer's SSL certificate is valid - set to false if you need to disable this for debugging/development: http://stackoverflow.com/questions/6400300/https-and-ssl3-get-server-certificatecertificate-verify-failed-ca-is-ok
    CURLOPT_SSL_VERIFYHOST => 2,                       // verify peer's hostname is name on certificate - set to false if you need to disable this for debugging/development: http://stackoverflow.com/questions/6400300/https-and-ssl3-get-server-certificatecertificate-verify-failed-ca-is-ok
    CURLOPT_CAINFO         => CMS_ASSETS_DIR . "/3rdParty/cacert.pem", // cURL doesn't include a library of trusted root certification authorities, latest version can be downloaded as cacert.pem here: https://curl.haxx.se/docs/caextract.html

    // Advanced Settings - listed here for reference
    //CURLOPT_USERAGENT      => "API Client v0.01",    // eg: ExampleBot/1.0 (+http://www.example.com/) -OR- Mozilla/5.0 (Windows NT 6.1; WOW64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/43.0.2357.124 Safari/537.36"); // Mozilla/5.0 (Windows NT 6.1; WOW64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/43.0.2357.124 Safari/537.36
    //CURLOPT_USERPWD        => "username:password",
    //CURLOPT_COOKIEFILE     => $cookieJarPath,        // load cookies from this file
    //CURLOPT_COOKIEJAR      => $cookieJarPath,        // save cookies to this file on script end or curl_close().  Setting this to any value resends received cookies
    //CURLOPT_COOKIEJAR      => '-',                   // setting this to '-' curl save cookies internally and resend them until curl_close is called.
    //CURLOPT_ENCODING       => '',                    // Sends Accept-Encoding: gzip AND has CURL automatically ungzip response, blank value defaults to all accepted encodings
    //CURLOPT_HTTPHEADER     => array('Expect:'),      // Disable HTTP 100-Continue responses (not currently needed)
    //CURLOPT_FOLLOWLOCATION => false,                 // Follow any "Location: " header redirects (or false to not follow any)
    //CURLOPT_MAXREDIRS      => 6,                     // max redirects to follow
    //CURLOPT_AUTOREFERER    => true,                  // Automatically set the Referer: field in requests when following a Location: redirect.
  ];
  $curl_options = $curl_defaults + $curl_options; // second array (supplied parameters) overrides first

  // make request
  $ch = curl_init();
  curl_setopt_array($ch, ($curl_defaults + $curl_options));
  $curl_result   = curl_exec($ch);
  $curl_getinfo  = curl_getinfo($ch);
  if ($curl_result === false) { // error checking
    $curl_error = curl_error($ch) . " (cURL error #" .curl_errno($ch). ")";
  }
  curl_close($ch);

  // split headers from body
  $parts   = preg_split("/\r?\n\r?\n/", $curl_result, 2);
  $headers = array_key_exists(0, $parts) ? $parts[0] : '';
  $body    = array_key_exists(1, $parts) ? $parts[1] : '';

  // create requestInfo (add custom fields and re-order for easier debugging)
  $requestInfo = [
    'error'           => isset($curl_error) ? $curl_error : '',
    'http_code'       => $curl_getinfo['http_code'],
    'url'             => $curl_getinfo['url'],
    'request_header'  => isset($curl_getinfo['request_header']) ? $curl_getinfo['request_header'] : '',
    'response_header' => $headers,
  ] + $curl_getinfo;

  //
  return $body;
}

//
function convertSecondsToTimezoneOffset($seconds) {

  $offsetAddMinus = ($seconds < 0) ? '-' : '+';
  $offsetHours    = (int) abs($seconds / (60*60));
  $offsetMinutes  = (int) abs(($seconds % (60*60)) / 60);
  $offsetString   = sprintf("%s%02d:%02d", $offsetAddMinus, $offsetHours, $offsetMinutes);

  return $offsetString;
}


//
function mkdir_recursive($dir, $mode = 0777) {
  if (is_dir($dir)) { return true; }

  // create parent dir (if needed)
  $parentDir = dirname($dir);
  if ($parentDir && $parentDir != $dir) {
    mkdir_recursive($parentDir, $mode);
  }

  // create dir
  return @mkdir($dir, $mode);
}

// On windows PHP < 5.2.9 copy fails with "File Exists" if the destination file exists.
// This function tries a rename then unlinks target file on windows if rename fails
// Rename is atomic otherwise (not always on NFS but still faster than writing file direct)
// Bug report: https://bugs.php.net/bug.php?id=41985
// Bug report: https://bugs.php.net/bug.php?id=44805 (last response indicates issue has been fixed in latest PHP)
// Reference: http://php.net/manual/en/function.rename.php#92575 (comment indicates issue fixed in 5.2.9+)
function rename_winsafe($oldfile, $newfile) {
  // try rename as-is first
  $result = @rename ($oldfile,$newfile); // extra space before ( added so this doesn't show up in the code scan on build)
  if ($result === true) { return $result; }

  // on failure, check for windows and unlink target first
  if ($result === false && isWindows() && is_file($oldfile) && is_file($newfile)) {
    unlink($newfile) || die(__FUNCTION__ . ": Error removing $newfile:" . @$php_errormsg);
    $r =  @rename ($oldfile,$newfile); // extra space before ( added so this doesn't show up in the code scan on build)
    return $r;
  }

  // otherwise return result (for: false on non-windows)
  return $result;

}


// atomic file writing (where supported), dies on errors
// Note: Dies on permission errors, disk full, and file-size mismatches
//   ... and uses rename for atomic updates (mostly, not always on NFS or Windows PHP < 5.29)
function file_put_contents_atomic($filepath, $content) {

  // save file to temp filename - this lets us check the write succeeded (fails on disk full, lack of write permissions, etc)
  $tempFilepath = $filepath .uniqid('.', true). '-temp.php';
  $bytesWritten = @file_put_contents($tempFilepath, $content);
  $dataLength   = strlen($content);
  if ($bytesWritten === false) { // Later: There is excess error checking here, we can simplify this if these aren't reported by Aug 2013
    dieAsCaller("Error writing $filepath: " . @$php_errormsg);
  }
  if ($bytesWritten !== $dataLength) {
    unlink($tempFilepath); // if error remove temp file
    dieAsCaller("Error writing $filepath: Only $bytesWritten out of $dataLength bytes written!");
  }
  if ($dataLength !== filesize($tempFilepath)) {
    unlink($tempFilepath); // if error remove temp file
    dieAsCaller("Error writing $filepath: $bytesWritten were written but temp file is " .filesize($tempFilepath). " bytes!");
  }

  // rename temp file over actual file
  if (!@rename_winsafe($tempFilepath, $filepath)) {
    $renameError = @$php_errormsg;
    unlink($tempFilepath); // if error remove temp file
    dieAsCaller(__FUNCTION__. ": Error renaming over $filepath: $renameError");
  };
}

//
function isPathWritable($path) {

  if (!file_exists($path)) { return false; }

  else if (is_dir($path)) { // try and create test file
    $randomFilename = uniqid("temp_", 1) . ".tmp";
    $randomFilepath = "$path/$randomFilename";

    $fh = @fopen($randomFilepath, 'x');
    if (!$fh) { return false; }
    fclose($fh);
    $erased = @unlink($randomFilepath);

    if (!$erased && file_exists($randomFilepath)) {
      $message  = "<b>Error:</b> There was a '<b>$php_errormsg</b>' error when trying to erase this temporary test file:<br/><br/>\n<i>$randomFilepath</i><br/><br/>\n";
      $message .= "This application needs permission to 'create' and 'remove' files on your server.<br/><br/>\n";
      $message .= "Please ask your server administrator to give erase permission (called 'modify' permission on Windows) to the above folder for the web server user account.<br/><br/>\n";
      die("$message");
    }
  }

  else if (is_file($path)) { // try and append to file
    $fh = @fopen($path, 'r+');
    if (!$fh) { return false; }
    else      { fclose($fh); }
  }

  else {
    die(__FUNCTION__ . ": Path isn't a file or a directory! '". htmlencode($path) ."'");
  }

  return true;
}

// if you set $newQueryValues keys to NULL they will removed
function thisPageUrl($newQueryValues = array(), $queryStringOnly = false) { // $queryStringOnly added in v2.15
  $proto  = isHTTPS() ? "https://" : "http://";

  if     (isset($_SERVER['HTTP_HOST']))   { $domain = $_SERVER['HTTP_HOST']; }
  elseif (isset($_SERVER['SERVER_NAME'])) { $domain = $_SERVER['SERVER_NAME']; }
  else                                    { $domain = ''; }

  if (preg_match('|:[0-9]+$|', $domain)) { $port = ''; } // if there is a :port on HTTP_HOST use that, otherwise...
  else                                   { $port   = (@$_SERVER['SERVER_PORT'] && @$_SERVER['SERVER_PORT'] != 80 && @$_SERVER['SERVER_PORT'] != 443) ? ":{$_SERVER['SERVER_PORT']}" : ''; }
  $path   = str_replace(' ', '%20', $_SERVER['SCRIPT_NAME']) . (isset($_SERVER['PATH_INFO']) ? $_SERVER['PATH_INFO'] : '');

  //
  $query = isset($_SERVER['QUERY_STRING']) ? $_SERVER['QUERY_STRING'] : '';
  if ($newQueryValues) { // just use the existing query_string unless we need to modify it
    parse_str($query, $oldRequestKeysToValues);

    // 2.54 - merge manually adding new keys to beginning of query and don't break urls that need trailing numbers such as ?new=value&page-name=123
    //        ... don't use array_merge() as it re-numbers numeric keys which will break urls
    $requestKeysToValues = $newQueryValues;
    foreach ($oldRequestKeysToValues as $key => $value) {
      if (!array_key_exists($key, $requestKeysToValues)) { $requestKeysToValues[$key] = $oldRequestKeysToValues[$key]; }
    }

    $query = http_build_query($requestKeysToValues);
  }
  if ($query != '') { $query = "?$query"; }
  $query = rtrim($query, '='); //v2.51 remove trailing = so urls that start like this stay like this: ?page-name-123 and not ?page-name-123=

  //
  if ($queryStringOnly) { return $query; }
  else                  { return $proto . $domain . $port . $path . $query; } // full url
}

//
function getEvalOutput($string) {
  global $TABLE_PREFIX, $ESCAPED_FILTER_VALUE, $CURRENT_USER, $RECORD;

  //
  $containsNoPHPCode = (strpos($string,"php") === false);
  if($containsNoPHPCode) { return $string; }

  // eval php code
  ob_start();
  eval('?>' . $string);
  $output = ob_get_clean();

  return $output;

}


// return an array or string in a human-readable "example" form
function showme($var, $fancy = false) {

  //
  if ($fancy) {
    $js = <<<__EOD__
   <a href="#" onclick="d=this.nextSibling.style;if(d.display=='none'){d.display='inline';this.innerHTML='-'}else{d.display='none';this.innerHTML='+'}return false">+</a><span style="display:none">
\\1
__EOD__;
    $dump = htmlencode(print_r($var, true));
    $dump = preg_replace('|\n(\s*\(\n)|', $js, $dump);
    $dump = preg_replace('|(\n\s*\))\n|', "\\1</span>", $dump);
    print "<pre>$dump</pre>";
  }

  elseif (is_object($var) && method_exists($var, 'showme')) {
    $var->showme();
  }

  else {
    print "<xmp>" . print_r($var, true) . "</xmp>";
  }

}


// save php data structure to a file
function saveStruct($filepath, $struct, $sortKeys = false, $headerMessage = '') { // $headerMessage added in 2.18
  if (!$filepath)         { die(__FUNCTION__ . ": no filepath specified!"); }
  if (!is_array($struct)) { die(__FUNCTION__ . ": structure isn't array!"); } // to debug add: . print_r($struct, true)

  // sort keys
  if ($sortKeys) { ksort($struct); }

  // create struct file data
  $header  = '<?php /* This is a PHP data file */ if (!@$LOADSTRUCT) { die("This is not a program file."); }'; // DO NOT EDIT THIS - isStructFile() uses it to identify struct files
  if ($headerMessage != '') { $header .= "\n/*\n$headerMessage\n*/"; }
  $phpData = var_export($struct, true);
  $phpData = preg_replace("/=>\s*array\s*/s", "=> array", $phpData); // reformat PHP so arrays start on the same line as keys (easier to read)
  $content = $header ."\nreturn $phpData;\n?>";

  // save file
  file_put_contents_atomic($filepath, $content);

  // opcache - invalidate old versions so opcache.file_update_protection won't prevent updated PHP file from being loaded
  if (function_exists('opcache_invalidate')) {

    // future: check if opcache.restrict_api is set (so we don't get errors)
    // NOTE: This code is untested and intended for future use if we need to further refine how opcache invaldiation works
    //$opcache_api_access   = true;
    //if (ini_get('opcache.restrict_api') && $_SERVER['SCRIPT_FILENAME'] &&                 // if both values set
    //    mb_strpos(ini_get('opcache.restrict_api'), $_SERVER['SCRIPT_FILENAME']) !== 0) {  // and script_filename doesn't start with the path the opcache API is restricted to
    //  $opcache_api_access = false;
    //}
    //if (!$opcache_api_access) { /* invalidate here */ }

    @opcache_invalidate($filepath, true); // @hide warnings: Rackspace Cloud returns: E_WARNING: Zend OPcache API is restricted by "restrict_api" configuration directive.
  }

}

// load php data structure from a file
function loadStruct($filepath) {
  if (!$filepath)              { die(__FUNCTION__ . ": no filepath specified!"); }
  if (!file_exists($filepath)) { die(__FUNCTION__ . ": Couldn't find file '$filepath'"); } // error checking

  // opcache - invalidate cached versions of file so we get the latest version
  if (function_exists('opcache_invalidate')) { @opcache_invalidate($filepath, false); }

  // load file
  $LOADSTRUCT = true; // prevent struct data files from showing "Not a program file" warnings
  $struct     = include($filepath);

  //
  return $struct;
}

// loads data files that are in INI or struct format.  Loaded INI files are resaved as structs.
// now it coverts old files to the new format and calls loadStruct()
function loadStructOrINI($filepath) {

  // convert old files
  if (!isStructFile($filepath)) { saveStruct($filepath, loadINI($filepath)); }

  // load as struct file
  return loadStruct($filepath);
}

// check if a file is in the php "struct" format (otherwise assumes it's in the legacy INI file format)
function isStructFile($filepath) {
  $isStruct       = false;
  $structHeader   = '<?php /* This is a PHP data file */ if (!@$LOADSTRUCT) { die("This is not a program file."); }'; // This should match saveStruct() and shouldn't change (or we won't detect previous struct files
  $fileHandle     = @fopen($filepath, 'r');
  if ($fileHandle) {
    $fileHeader = @fread($fileHandle, strlen($structHeader));
    $isStruct   = ($fileHeader == $structHeader);
    fclose($fileHandle);
  }
  return $isStruct;
}

// This function generates the <option> list HTML for a <select> pulldown list
// Note: labels is options and will default to values
// Note: $selectedValue can be a string or an array (to support multiple selected values for multi-select lists)
// v3.08: You can now specify text for $showEmptyOptionFirst to have that show instead of <select>
function getSelectOptions($selectedValue, $values, $labels = '', $showEmptyOptionFirst = false, $htmlEncodeInput = true) {
  if (!is_array($selectedValue)) { $selectedValue = (array) $selectedValue; } // v2.60 force to array interally for simpler code to test single or multiple selected values

  if (!$labels) { $labels = $values; }
  $output = '';

  // error checking
  if (!count($values) || !count($labels)) { return ''; } // if no values or labels return blank

  //
  if ($showEmptyOptionFirst) {
    $label   = is_string($showEmptyOptionFirst) ? $showEmptyOptionFirst : t('&lt;select&gt;'); // v2.07 Allow specifying of label text
    $output .= "<option value=''>$label</option>\n";
  }

  //
  $valuesAndLabels = array_combine($values, $labels);
  foreach ($valuesAndLabels as $inputValue => $inputLabel) {
    $selectedAttr = (in_array(strval($inputValue), $selectedValue)) ? " selected='selected'" : ''; // use strval or values of 0 will match $selectedValue of array('')

    $outputValue  = $htmlEncodeInput ? htmlencode($inputValue) : $inputValue;
    $outputLabel  = $htmlEncodeInput ? htmlencode($inputLabel) : $inputLabel;
    if ($outputLabel == '') { $outputLabel = '&nbsp;'; } // insert blank value to avoid XHTML validation errors

    $output .= "<option value='$outputValue' $selectedAttr>$outputLabel</option>\n";
  }

  return $output;
}


// <select name="country"><?php echo getSelectOptionsFromTable('countries','num','name', @$_REQUEST['country'], true) ? ></select>
function getSelectOptionsFromTable($tableName, $valueField, $labelField, $selectedValue, $showEmptyOptionFirst) {
  if (!is_array($selectedValue)) { $selectedValue = (array) $selectedValue; } // v2.60 force to array interally for simpler code to test single or multiple selected values

  // load options
  $escapedLabelField = mysql_escape($labelField);
  $escapedValueField = mysql_escape($valueField);
  $escapedTableName  = $GLOBALS['TABLE_PREFIX'] . mysql_escape($tableName);

  // get records
  $schema  = loadSchema($tableName);
  $query   = "SELECT `$escapedLabelField`, `$escapedValueField` FROM `$escapedTableName`";
  if (@$schema['listPageOrder']) { $query .= " ORDER BY {$schema['listPageOrder']}"; } // v2.14 - sort by schema sort order if available
  $records = mysql_select_query($query);

  // create html
  $html = '';
  if ($showEmptyOptionFirst)    { $html .= "<option value=''>" .t('&lt;select&gt;'). "</option>\n"; }
  foreach ($records as $record) {
    $label        = $record[$labelField];
    $value        = $record[$valueField];
    $selectedAttr = (in_array($value, $selectedValue)) ? " selected='selected'" : '';
    $html        .= "<option value='" .htmlencode($value) ."'$selectedAttr>" .htmlencode($label). "</option>\n";
  }

  //
  return $html;
}

// set a cookie with a unique prefix string (eg: 4df8fa515a2ad_ ) so cookies don't override/conflict with cookies used by other 3rd party applications on the same site
// Defaults to session cookies that are erased when browser is closed, to expire in one hour use time()+(60*60*1),
// ... or for never-expires use a time in the far future such as 2146176000 (2038, don't set past this to avoid: https://google.com/search?q=2038+cookie+bug )
function setPrefixedCookie($unprefixedName, $cookieValue, $cookieExpires = 0, $allowHttpAccessToHttpsCookies = false, $allowJavascriptAccess = false) {
  if (headers_sent($file, $line)) {
    if (errorlog_inCallerStack()) { return; } // if headers sent and being called from errorlog functions don't log further errors
    $error = __FUNCTION__ . ": Can't set cookie($unprefixedName, $cookieValue), headers already sent!  Output started in $file line $line.\n";
    trigger_error($error, E_USER_ERROR);
  }

  // set cookie
  $cookieName   = cookiePrefix() . $unprefixedName;
  $cookieValue  = $cookieValue;
  $cookiePath   = '/';        // make the cookie available to any path on domain (unique cookie name avoid collisions)
  $cookieDomain = preg_replace('/^www\./i', '', array_first(explode(':', @$_SERVER['HTTP_HOST']))); // get hostname without :port or www. so cookie works on www.example.com and example.com
  if (substr_count($cookieDomain, '.') <= 1) { $cookieDomain = ''; } // many browsers require a minimum of one dots in the domain name or they won't set the cookie (which is a problem for "localhost" and internal domain), setting to blank uses current domain

  // v2.62 - If true, cookies can only be set/read over HTTPS (based on browser implementation)
  // ... so we default this to true if we're on a HTTPS connection and allow an override via function args
  // ... OLD behaviour was to only set 'secure' cookies if isHTTPS() AND $GLOBALS['SETTINGS']['advanced']['requireHTTPS'] was set
  $cookieSecure   = $allowHttpAccessToHttpsCookies ? false : isHTTPS();
  $cookieHttpOnly = $allowJavascriptAccess ? false : true;  // v2.62 - added function option: prevent accessing of the cookie via javascript
  setcookie($cookieName, $cookieValue, $cookieExpires, $cookiePath, null, $cookieSecure, $cookieHttpOnly);
  $_COOKIE[$cookieName] = $cookieValue;
}

//
function removePrefixedCookie($unprefixedName) {
  if (headers_sent($file, $line)) {
    if (errorlog_inCallerStack()) { return; } // if headers sent and being called from errorlog functions don't log further errors
    $error = __FUNCTION__ . ": Can't remove cookie($unprefixedName), headers already sent!  Output started in $file line $line.\n";
    trigger_error($error, E_USER_ERROR);
  }

  setPrefixedCookie($unprefixedName, null, 252720000); // set expire date to the past (1978) so cookie is removed right away
  $cookieName = cookiePrefix() . $unprefixedName;
  unset($_COOKIE[$cookieName]);
}

//
function getPrefixedCookie($unprefixedName) {
  $cookieName = cookiePrefix() . $unprefixedName;
  return @$_COOKIE[$cookieName];
}

// get prefix used by all cookies.  Pass an argument to create a unique cookie space, eg: cookiePrefix('frontend');
// cookiePrefix();          // return cookie prefix, eg: cms_9b4bf_
// cookiePrefix('custom');  // set leading text to create custom cookie-space, eg: custom_9b4bf_
// list($leadingText, $defaultPrefix) = cookiePrefix(false, true); // return prefix parts as array
function cookiePrefix($setLeadingText = '', $returnArray = false) {
  static $leadingText = '';

  // allow override so plugins (membership?) can use their own cookie space
  if ($setLeadingText) { $leadingText = $setLeadingText; }

  // set default
  if (!$leadingText) {
    if (@$GLOBALS['SESSION_NAME_SUFFIX']) {
      // backwards compatability with <= 2.50 and older membership plugins, if code tried to use alternate PHP SESSIONID, use a different cookie space for logins
      $leadingText = 'wsm';
    }
    else {
      // NOTE: Don't change "cms_" default cookie prefix "prefix", as it's referenced in getCurrentUserFromCMS()
      $leadingText  = 'cms';
    }
  }

  //
  if ($returnArray) { return array($leadingText, $GLOBALS['SETTINGS']['cookiePrefix']); }
  else              { return $leadingText .'_'. $GLOBALS['SETTINGS']['cookiePrefix']; }
}

// starts with http, https, etc
function isAbsoluteUrl($url) {
  return preg_match("|^\w+://|", $url);
}

// make relative urls absolute - works with /absolute/filepath.php, ?query=string, and relative/../filepaths.php
// Note: If baseUrl is a directory it must end in a slash (or it will be mistaken for a file and may be trimmed)
function realUrl($targetUrl, $baseUrl = null) {
  if (isAbsoluteUrl($targetUrl)) { return $targetUrl; }

  ### get baseUrl
  if (!$baseUrl) {  // default baseUrl to currently running web script
    $baseUrl  =  isHTTPS() ? 'https://' : 'http://';
    $baseUrl .= coalesce(@$_SERVER['HTTP_HOST'], parse_url(@$GLOBALS['SETTINGS']['adminUrl'], PHP_URL_HOST));
    $baseUrl .= inCLI() ? '/' : $_SERVER['SCRIPT_NAME']; // SCRIPT_NAME has filepath not web path for command line scripts so just set to /
    $baseUrl  = str_replace(' ', '%20', $baseUrl);
  }
  if (!isAbsoluteUrl($baseUrl)) { $baseUrl = realUrl($baseUrl); }  // make sure supplied $baseUrl is absolute. if it's not, make it so, relative to currently running script

  // parse baseurl into parts
  $baseUrlParts  = parse_url($baseUrl);
  $baseUrlDomain = $baseUrlParts['scheme'] . '://';  // figure out $baseUrlDomain (e.g. http://domain)
  if (@$baseUrlParts['user']) {
    $baseUrlDomain .= $baseUrlParts['user'];
    if (@$baseUrlParts['pass']) { $baseUrlDomain .= ':' . $baseUrlParts['pass']; }
    $baseUrlDomain .= '@';
  }
  $baseUrlDomain .= $baseUrlParts['host'];
  if (@$baseUrlParts['port']) { $baseUrlDomain .= ':' . $baseUrlParts['port']; }


  // if no target,  nothing specified for target, use baseUrl
  if ($targetUrl == '') {
    $absoluteUrl = $baseUrl;
  }

   // for absolute filepaths - eg: /file.php
  elseif (strpos($targetUrl, '/') === 0) {
    $absoluteUrl = $baseUrlDomain . $targetUrl;
  }

  // for query strings - eg: ?this=that, etc
  else if (strpos($targetUrl, '?') === 0) {
    $absoluteUrl = $baseUrlDomain . $baseUrlParts['path'] . $targetUrl;
  }

  // for fragments - eg: #fragment, etc
  else if (strpos($targetUrl, '#') === 0) {
    $absoluteUrl = $baseUrlDomain . $baseUrlParts['path'];
    if (@$baseUrlParts['query']) { $absoluteUrl .= '?' . $baseUrlParts['query']; }
    $absoluteUrl = $absoluteUrl . $targetUrl;
  }

  // for relative filepaths - eg: dir/file.php
  else {
    $basePath = $baseUrlParts['path'];
    if (!$basePath) { trigger_error("Error getting path from realUrl('$targetUrl', '$baseUrl')!\n"); }

    // v2.62 - if basepath is webroot ("/") or webroot file ("/example.php") then remote all parent references (../) from targetUrl
    $isPathRootDir = in_array(dirname($baseUrlParts['path']), array('\\','/'));
    if ($isPathRootDir) {
      $targetUrl = preg_replace("|(\.\./)+|", '', $targetUrl);
    }

    // if the baseUrl includes a file (e.g. 'dir/admin.php'), strip it (e.g. 'dir/')
    if (!endsWith('/', $basePath)) {
      $basePath = dirname($basePath) . '/';
      $basePath = fixSlashes($basePath); // root paths return / only, so prevent // (or \/ on windows) from the above code
    }
    $absoluteUrl = $baseUrlDomain . $basePath . $targetUrl;
  }

  // collapse "dir/../"s
  $madeReplacement = true;
  while ($madeReplacement) { // keep making replacements until we can't anymore
    $absoluteUrl = preg_replace('@[^/]+/\.\./@', '', $absoluteUrl, 1, $madeReplacement);
  }

  // collapse "/./"s
  $absoluteUrl = preg_replace('@/\./@', '/', $absoluteUrl, -1);

  // url encode spaces
  $absoluteUrl = str_replace(' ', '%20', $absoluteUrl); // url encoded spaces

  return $absoluteUrl;
}

// redirect the browser using one or more methods.  If all the headers haven't
// been sent yet a HTTP redirect header is sent.  In addition to that, a meta
// refresh tag and javascript redirect are output.
// NOTE: Cookies may be ignored on redirect! http://stackoverflow.com/questions/1621499/why-cant-i-set-a-cookie-and-redirect
function redirectBrowserToURL($url, $queryStringsOnly = false) {
  if (!$url) { die(__FUNCTION__ . ": No url specified!"); }

  // make relative urls absolute - works with /absolute/filepath.php, ?query=string, and relative/filepaths.php
  $url = realUrl($url);

  // force query string only urls (remove anything but query string and then query realUrl from that)
  if ($queryStringsOnly) {
    $url = realUrl( '?'.parse_url($url, PHP_URL_QUERY) );
  }

  //
  $url            = str_replace(' ', '%20', $url); // url encoded spaces
  $htmlEncodedUrl = htmlencode($url);

  ### if content headers haven't been send yet sent http redirect and html content header
  if (!headers_sent()) {

    # Fix IIS/5.0 bug "Set-Cookie Is Ignored in CGI When Combined With Location" described here: http://support.microsoft.com/kb/q176113/
    $isIIS5 = ($_SERVER['SERVER_SOFTWARE'] == 'Microsoft-IIS/5.0');  # IIS 5.1 doesn't seem effected
    if ($isIIS5) {
      print "<meta http-equiv='refresh' content='0;URL=$htmlEncodedUrl'>\n";
      exit;
    }

    header("Location: $url");
    print "<meta http-equiv='refresh' content='0;URL=$htmlEncodedUrl'>\n";
    print "This page has moved. If you aren't automatically forwarded to the new location ";
    print "then click the following link: <a href='$htmlEncodedUrl'>$url</a>.\n";
    exit;
  }

  ### if content headers have already been sent use javascript and/or meta refresh to redirect user

  // use javascript to redirect user
  print "\n\n<script>window.location = '" .addslashes($url). "';</script>\n";

  // use meta refresh to redirect url
  print "<meta http-equiv='refresh' content='0;URL=$htmlEncodedUrl'>\n";

  // print redirect message with link (in case other methods fail)
  print "\n<p>Redirecting to <a href='$htmlEncodedUrl'>$url</a>.</p>\n";
  exit;
}


// we use files with a .default extension and then rename it if a file _doesn't_ already exist
// with the same name to prevent users from accidentally overwriting their data while upgrading
function renameOrRemoveDefaultFiles() {

  $dirs = array();
  $dirs[] = DATA_DIR;
  $dirs[] = DATA_DIR.'/schema';
  $dirs[] = DATA_DIR.'/schemaPresets';

  foreach ($dirs as $dir) {
    foreach (scandir($dir) as $filename) {
      $filepath = "$dir/$filename";
      if (!is_file($filepath))                    { continue; }
      if (!preg_match('/\.default$/', $filename)) { continue; }

      // rename default file if no target file exists
      $defaultFile = $filepath;
      $targetFile  = preg_replace('/\.default$/', '', $defaultFile);

      if (!is_file($targetFile)) {
        @rename_winsafe($defaultFile, $targetFile) || die("Error renaming '$defaultFile'!<br/>Make sure this file and its parent directory are writable!");
      }
      else {
        @unlink($defaultFile) || die("Error deleting '$defaultFile'!<br/> Make sure this file and its parent directory are writable! PHP Error: $php_errormsg");
      }
    }
  }

}

//
function setupDemoIfNeeded() {
  global $SETTINGS, $TABLE_PREFIX;

  // skip if not in demo mode
  if (!inDemoMode()) { return; }

  // error checking
  if (!isInstalled()) { die("You must install the software before you can use demoMode!"); }

  // reset demo if needed
  if (@$_REQUEST['resetDemo']) { unset($_SESSION['demoCreatedTimeAsFloat']); }

  // change tableprefix for active demos
  $isActiveDemo = (@$_SESSION['demoCreatedTimeAsFloat'] && $_SESSION['demoCreatedTimeAsFloat'] >= (time() - MAX_DEMO_TIME));
  if ($isActiveDemo) {
    if (preg_match("/[^\d\.]/", $_SESSION['demoCreatedTimeAsFloat'])) { die("Invalid demo value in session!"); }

    $TABLE_PREFIX  = $SETTINGS['mysql']['tablePrefix'];
    $TABLE_PREFIX .= '(demo' .$_SESSION['demoCreatedTimeAsFloat']. ')_';
    $TABLE_PREFIX  = str_replace('.', '-', $TABLE_PREFIX); // . isn't allowed in tablenames
  }

  // otherwise, create new demo
  else {
    echo t("Creating demo (please wait a moment)...") . "<br/>\n";
    _removeOldDemos();
    $demoNum = _createNewDemo();
    $_SESSION['demoCreatedTimeAsFloat'] = $demoNum;
    $refreshUrl = @$_REQUEST['resetDemo'] ? '?' : thisPageUrl();
    printf(t("Done, <a href='%s'>click here to continue</a> or wait a moment while we redirect you."), $refreshUrl);
    print "<br/>\n<meta http-equiv='refresh' content='1;$refreshUrl' />";

    //
    showBuildInfo();

    exit;
  }
}

// redirect user to new page with "Disabled in demo mode message";
function disableInDemoMode($message = '', $interface = '') {
  if (!inDemoMode()) { return; }

  // display message
  //clearAlertsAndNotices(); // so previous alerts won't display
  if      ($message == '')         { alert(t('This feature is disabled in demo mode.')); }
  else if ($message == 'settings') { alert(t('Changing settings is disabled in demo mode.')); }
  else if ($message == 'plugins')  { alert(t('Plugins are disabled in demo mode.')); }
  else                             { die("Unknown section name '" .htmlencode($section). "'!"); }

  // display interface
  if (!$interface)                   { showInterface('home.php'); }
  else if ($interface == 'ajax')     { die(t('This feature is disabled in demo mode.')); }
  else                               { showInterface($interface); }

  //
  exit;
}

function inDemoMode() {
  return $GLOBALS['SETTINGS']['demoMode'];
}

function inCLI() {
  if (PHP_SAPI == 'cli')                     { return true; }
  if (@$_SERVER['SESSIONNAME'] == 'Console') { return true; }

  return (@$_SERVER['SCRIPT_NAME']) ? false : true; // NOTE: This is sometimes set in CLI, but always set through web
}

// create new demo (copy all CMS tables)
function _createNewDemo() {
  global $TABLE_PREFIX;

  ###
  $maxAttempts  = 12;
  $attempts     = 0;
  $schemaTables = getSchemaTables();
  $demoNum      = sprintf("%.3f", array_sum( explode(' ', microtime()) )); // eg: 1243448178.000 - allows for 999 demos to be created a second
  while (++$attempts <= $maxAttempts) {
    $demoNum    = sprintf("%.3f", $demoNum + 0.001);
    $demoPrefix = "{$TABLE_PREFIX}(demo{$demoNum})_";
    $demoPrefix = str_replace('.', '-', $demoPrefix); // . isn't allowed in tablenames

    foreach ($schemaTables as $tableName) {
      $sourceTable = "{$TABLE_PREFIX}$tableName";
      $targetTable = "{$demoPrefix}$tableName";

      if (strlen($targetTable) > 64) {
        die("Couldn't create demo table ($targetTable) as table name exceeded 64 characters. Try shortening your table prefix or table names.");
      }

      // create table
      if (!mysqli()->query("CREATE TABLE `$targetTable` LIKE `$sourceTable`")) { continue 2; } // skip to next demoNum in while loop

      // copy rows
      mysqli()->query("INSERT INTO `$targetTable` SELECT * FROM `$sourceTable`") or die("MySQL Error: ". htmlencode(mysqli()->error) . "\n");
    }
    break;
  }
  if ($attempts > $maxAttempts) { die("Error: Couldn't create demo after $maxAttempts attempts!  Please contact us and let us know about this error!"); }

  //
  return $demoNum;
}

// remove expired demos
function _removeOldDemos() {
  global $TABLE_PREFIX;

  $rows = mysql_select_query("SHOW TABLES LIKE '{$TABLE_PREFIX}(demo%)_%'", true);
  foreach ($rows as $row) {
    $tableName = $row[0];

    // check table date and expiry
    preg_match("/^{$TABLE_PREFIX}\(demo(\d+).*?\)_/", $tableName, $matches) or die("Error: Table '$tableName' doesn't seem to match naming scheme of demo table!");
    $tableCreatedTime = $matches[1];
    $hasExpired       = ($tableCreatedTime < time() - MAX_DEMO_TIME);

    // drop expired tables
    if ($hasExpired) {
      $query = "DROP TABLE IF EXISTS `$tableName`";
      mysqli()->query($query);
      #print "Debug: $query<br/>";
    }
  }
}

// set new alert and return all alerts
function alert($message = '') {
  global $APP;
  @$APP['alerts'] .= "$message";
  return $APP['alerts'];
}

// set new notice and return all notices
function notice($message = '') {
  global $APP;
  @$APP['notices'] .= "$message";
  return $APP['notices'];
}

//
//
function displayAlertsAndNotices() {
  global $APP;

  //if (@$APP['errors']    != '') { _displayNotificationType('danger',    @$APP['errors']); }
  if (@$APP['alerts']    != '') { _displayNotificationType('warning',   @$APP['alerts']); }
  if (@$APP['notices']   != '') { _displayNotificationType('info',      @$APP['notices']); }
  //if (@$APP['successes'] != '') { _displayNotificationType('success',   @$APP['successes']); }
}


// check if productId is valid format
function isValidProductId($productId) {
  # NOTE: Disabling or modifying licensing or registration code violates your license agreement.
  # NOTE: Please don't steal our software.

  $productId        = str_split(preg_replace('/[^A-Z0-9]+/', '', strtoupper($productId)));
  $charsetMap       = array_flip(str_split('P0Y5F9G4C8R3L7A2E6U1DHTNQJKXBMWVZ'));
  if(count($productId) != 16) { return ''; }

  foreach (array(265,193) as $primeMultiplier) {
    $keyNumber = 0;
    foreach (range(0,7) as $index) {
      $keyNumber = $keyNumber * 33;
      $keyNumber += @$charsetMap[ array_shift($productId) ];
    }

    $licenseNum = @($keyNumber / ($GLOBALS['APP']['key'] * $primeMultiplier) - 1261);
    if (($licenseNum - (int) $licenseNum) ||                  # a float means invalid
        ($licenseNum < 1) ||                                  # a negative num means invalid
        (@$oldLicenseNum && $licenseNum != $oldLicenseNum)) { # non-match means invalid
       return '';
    }
    else {
      $oldLicenseNum = $licenseNum;
    }
  }

  return $licenseNum;
}

//
function _displayNotificationType($type, $message) {
  $typeToIconClass = [
    'success' => 'fa-check-circle',
    'info'    => 'fa-info-circle',
    'warning' => 'fa-exclamation-triangle',
    'danger'  => 'fa-times-circle',
  ];
  print '<div class="alert alert-'.htmlencode($type).'">';
  print '<button class="close">&times;</button>';
  print '<i class="fa ' . @$typeToIconClass[$type] . '"></i> &nbsp;';
  print '<span>' . $message . '</span>';
  print '</div>';
}

// array_pluck: (aka array_column) utility function which returns specific key/column from an array
// alternate: array_column($recordList, $targetField)
// alternate2: array_column($recordList, $targetField, $keyField)
function array_pluck($recordList, $targetField) {
  if (!is_array($recordList)) { dieAsCaller(__FUNCTION__. ": First argument must be an array!"); }

  $result = array();
  foreach ($recordList as $recordKey => $record) {
    if     (is_array($record)  && array_key_exists($targetField, $record)) { $result[ $recordKey ] = $record[$targetField]; }
    elseif (is_object($record) && isset($record->$targetField))            { $result[ $recordKey ] = $record->$targetField; }
  }
  return $result;
}

// array_groupBy:
//   eg. $recordsByNum = array_groupBy($records, 'num');
//   eg. $recordsByCategory = array_groupBy($records, 'category', true);
//       foreach ($recordsByCategory as $category => $categoryRecords) { ;;; }
function array_groupBy($recordList, $indexField, $resultsAsArray = false) {
  $result = array();
  foreach ($recordList as $recordKey => $record) {

    // get index value or skip this record
    if (!array_key_exists($indexField, $record)) { continue; }
    $indexValue = $record[$indexField];

    // add this record to the result array
    if ($resultsAsArray) {
      if (!@$result[ $indexValue ]) { $result[ $indexValue ] = array(); }
      $result[ $indexValue ][ $recordKey ] = $record;
    }
    else {
      $result[ $indexValue ] = $record;
    }

  }
  return $result;
}

// return the specified array value (this exists for function composition!)
// $num   = array_value($array, 'num'); // @$array['num']
// $width = array_value($record, 'photos', 0, 'width'); // @$record['photos'][0]['width']
function array_value($array, $key) {
  if (func_num_args() == 2) {
    return @$array[$key];
  }
  else {
    $keys = func_get_args();
    array_shift($keys); // get rid of the first element
    foreach ($keys as $key) {
      $array = @$array[$key];
    }
    return $array;
  }
}

// return the first array value, which may not have a key of [0] (this exists for function composition!)
// $firstProduct = array_first($productsByNum);
function array_first($array) {
  if (!is_array($array) || !$array) { return null; }
  return reset($array); // return first element
}

// Returns the "records" in an array where the supplied key(s) match value(s)
// e.g. $blueOnes = array_where($records, array( 'colour' => 'blue' ));
function array_where($records, $conditions) {
  if(!is_array(@$records) || !@$records) { return array(); }

  $matchArray = array();
  foreach ($records as $key => $record) {
    if (!is_array($record)) { continue; }
    $isMatch = TRUE;
    foreach ($conditions as $fieldname => $value) {
      if (@$record[$fieldname] != $value) {
        $isMatch = FALSE;
        break;
      }
    }
    if ($isMatch) {
      $matchArray[$key] = $record;
    }
  }
  return $matchArray;
}

//
function dieAsCaller($message, $depth = 1) {
  $callers = debug_backtrace();

  // special case - remove functions that just call other functions (otherwise the fine/line number indicated isn't actual originating code)
  $skipFunctions = ['mysql_select_query'];
  foreach ($callers as $index => $array) {
    if (in_array($array['function'], $skipFunctions)) {
      array_splice($callers, $index, 1); // remove key (and re-order array indexes for code below)
    }
  }

  //
  $caller       = array_key_exists($depth, $callers) ? $callers[$depth] : array_pop($callers); // if specific frame doesn't exist return last frame
  $locationInfo = '';
  if (@$caller['file'])     { $locationInfo .= "in " . basename($caller['file']). " "; }
  if (@$caller['line'])     { $locationInfo .= "on line " . $caller['line']. " "; }
  if (@$caller['function']) { $locationInfo .= "by " .$caller['function']. "()"; }

  $error = "$message - $locationInfo";
  throw new Exception($error);
  exit;
}

//
function dieWith404($message = "Page not found!") {
  header("HTTP/1.0 404 Not Found");
  print $message;
  exit;
}

// escape javascript code
function jsencode($str) {
    return addcslashes($str,"\\\'\"&\n\r<>");
}

// just like htmlspec ialchars() but encodes ' as well and doesn't double encode <br /> (to make encoding textboxes with auto-formatting easy)
function htmlencode($string, $encodeBr = false) { // added in v2.15
  if (is_array($string) || is_object($string)) { dieAsCaller(__FUNCTION__.": Argument must be a string!"); }
  $encoded = call_user_func_array('htmlspe'.'cialchars', array($string, ENT_QUOTES, 'UTF-8')); // encode & " ' < > and use call_user_func_array to bypass invalid keywords scanner in script builder
  if (!$encodeBr) {
    $encoded = preg_replace("|&lt;br\s*/?&gt;|i", "<br />", $encoded); // don't double encode <br/> tags (to make encoding textboxes with auto-formatting easy)
  }
  return $encoded;
}

// htmlencode multiple values replacing placeholders ('?') with htmlencoded values
// echo htmlencodef("City of ? with a population of ?", $city, $population);
function htmlencodef() {
  $args         = func_get_args();
  $formatString = array_shift($args);
  $replacements = $args;

  // error checking
  $totalReplacements = count($replacements);
  $totalPlaceholders = substr_count($formatString, '?');
  if ($totalPlaceholders > $totalReplacements) { trigger_error("htmlencodef: Not enough arguments!", E_USER_ERROR); }
  if ($totalReplacements > $totalPlaceholders) { trigger_error("htmlencodef: Not enough placeholders!", E_USER_ERROR); }

  // replace placeholders
  $index = 0;
  $encodedString = preg_replace_callback('/\?/', function($match) use($replacements, &$index) {
    return htmlencode($replacements[$index++]);
  }, $formatString);

  //
  return $encodedString;
}

// send an email message with attachments.
// If text _and_ html are specified the email client will decide what to display.
/*
  // Minimal Example:
  $errors = sendMessage(array(
    'from'    => "from@example.com",
    'to'      => "to@example.com",
    'subject' => "Enter subject here, supports utf-8 content",
    'text'    => "Text message content",
  ));
  if ($errors) { die($errors); }


  // Full Featured Example:
  $errors = sendMessage(array(
    'from'    => "from@example.com",
    'to'      => "to@example.com",
    'subject' => "Enter subject here, supports utf-8 content",
    'text'    => "Text message content",
    'html'    => "<b>HTML</b> message content",
   'headers' => array(
      "CC"          => "cc@example.com",
      "BCC"         => "bcc@example.com",
      "Reply-To"    => "rt@example.com",
      "Return-Path" => "rp@example.com",
    ),
    'attachments' => array(
      'simple.txt'  => 'A simple text file',
      'dynamic.csv' => $csvData,
      'archive.zip' => $binaryData,
      'image.jpg'   => file_get_contents($imagePath),
    ),
    //'disabled' => false, // set to true to disable sending of message
    //'logging' => false, // set to false to disable logging (if logging is already enabled) - used for background mailers
  ));
  if ($errors) { die($errors); }

*/
function sendMessage($options = array()) {

  // allow hooks to override
  $eventState = array('cancelEvent' => false, 'returnValue' => null);
  $eventState = applyFilters('sendMessage', $eventState, $options);
  if ($eventState['cancelEvent']) { return $eventState['returnValue']; }

  // allow hooks to override
  $options = applyFilters('sendMessage_options', $options);

  // don't send if 'disabled' option set
  if (@$options['disabled']) { return; }

  ### v2.52 Notes: PHP's mail() function is very broken, on windows it talks directly to a SMTP server, or linux it talks to sendmail/qmail/postfix, etc.
  ### ... The PHP docs and RFCs say to use CRLF as an EOL in messages, but QMail and other Mail Transfer Agents (MTA) assume input is using the
  ### ... OS's default EOL char and converts LF to CRLF so it's standard compliant, which also causes CRLF to be translated to CRCRLF.  So there's
  ### ... no OS-independent way to make it work.  For these reasons, we bypass PHP mail() altogether and use the Swift Mailer library.


  // error checking
  $errors         = '';
  $hasText        = array_key_exists('text', $options);
  $hasHTML        = array_key_exists('html', $options);
  $hasAttachments = array_key_exists('attachments', $options);
  if (!isValidEmail( @$options['to'], true))  { $errors .= "'to' isn't a valid email '" .htmlencode($options['to']). "'!<br/>\n"; }
  if (!isValidEmail( @$options['from'] ))     { $errors .= "'from' isn't a valid email '" .htmlencode($options['from']). "'!<br/>\n"; }
  if (!array_key_exists('subject', $options)) { $errors .= "'subject' must be defined!<br/>\n"; }
  if (!$hasText && !$hasHTML)                 { $errors .= "Either 'text' or 'html' or both must be defined!<br/>\n"; }
  if ($errors) { return $errors; }

  // optionally log message and/or skip sending (log only - if enabled)
  $mode = $GLOBALS['SETTINGS']['advanced']['outgoingMail'];
  if ($mode != 'sendOnly' && @$options['logging'] !== false) {
    $colsToValues = array_slice_keys($options, array('from','to','subject','text','html'));
    $colsToValues['createdDate='] = 'NOW()';
    $colsToValues['sent=']        = ($mode == 'logOnly') ? '""' : 'NOW()';
    $colsToValues['headers']      = '';
    if (@$options['headers']) { // v2.52 log message headers (previously died with "logging message headers not supported"
      foreach ($options['headers'] as $name => $value) { $colsToValues['headers'] .= "$name: $value\n"; }
    }

    $colsToValues['reply-to'] = @$options['headers']['Reply-To'];
    $colsToValues['cc']       = @$options['headers']['CC'];
    $colsToValues['bcc']      = @$options['headers']['BCC'];
    mysql_insert('_outgoing_mail', $colsToValues, true);
  }
  if ($mode == 'logOnly') { return; }  // don't send if "log only" set

  // debug
//  showme($options);

  // send message with swift mailer
  $errors = _sendMessage_swiftMailer($options);
  return $errors;

}


// helper function for sendMessage() - takes $options array from sendMessage() and sends message using swiftMailer
function _sendMessage_swiftMailer($options) {
  $hasText        = array_key_exists('text', $options);
  $hasHTML        = array_key_exists('html', $options);
  $hasAttachments = array_key_exists('attachments', $options);

  ### Load Swift Mailer (overriding encoding if needed)
  require_once(CMS_ASSETS_DIR."/3rdParty/SwiftMailer5/swift_required.php");
  $overrideEncoding = (function_exists('mb_internal_encoding') && ((int) ini_get('mbstring.func_overload')) & 2);
  if ($overrideEncoding) { $oldEncoding = mb_internal_encoding(); mb_internal_encoding('ASCII'); } // workaround for if mbstring.func_overload is enabled - http://swiftmailer.org/docs/installing.html#troubleshooting

  ### Create message - Ref: http://swiftmailer.org/docs/messages.html
  $message = Swift_Message::newInstance();
  $message->setSubject($options['subject']);
  if     ($hasText && $hasHTML) { $message->setBody($options['html'], 'text/html')->addPart($options['text'], 'text/plain'); }
  elseif ($hasHTML)             { $message->setBody($options['html'], 'text/html'); }
  elseif ($hasText)             { $message->setBody($options['text'], 'text/plain'); }

  // Set From: email
  $emailComponents = isValidEmail($options['from'], false); // DO NOT allow multiple - returns array of: array('email@example.com', 'Full Name', "Bob Jones <this@that.com>"
  foreach ($emailComponents as $emailNameArray) { $message->setFrom($emailNameArray[0], nullIfFalse($emailNameArray[1])); }

  // Set To: email(s)
  $emailComponents = isValidEmail($options['to'], true); // allows multiple
  foreach ($emailComponents as $emailNameArray) { $message->addTo($emailNameArray[0], nullIfFalse($emailNameArray[1])); }

  // Set CC: email(s)
  if (@$options['headers']['CC']) {
    $emailComponents = isValidEmail($options['headers']['CC'], true); // allows multiple
    foreach ($emailComponents as $emailNameArray) { $message->addCc($emailNameArray[0], nullIfFalse($emailNameArray[1])); }
  }

  // Set BCC: email(s)
  if (@$options['headers']['BCC']) {
    $emailComponents = isValidEmail(@$options['headers']['BCC'], true); // allows multiple
    foreach ($emailComponents as $emailNameArray) { $message->addBcc($emailNameArray[0], nullIfFalse($emailNameArray[1])); }
  }

  // Set Reply-To: email (default to From: if not defined)
  $emailComponents = isValidEmail(coalesce(@$options['headers']['Reply-To'], $options['from']), false); // DO NOT allow multiple
  foreach ($emailComponents as $emailNameArray) { $message->setReplyTo($emailNameArray[0], nullIfFalse($emailNameArray[1])); }

  // Set Return-Path: email (default to From: if not defined) - ### This is where bounces are sent
  $emailComponents = isValidEmail(coalesce(@$options['headers']['Return-Path'], $options['from']), false); // DO NOT allow multiple
  foreach ($emailComponents as $emailNameArray) { $message->setReturnPath($emailNameArray[0], nullIfFalse($emailNameArray[1])); }

  // Add attachments
  if (@$options['attachments']) {
    foreach ($options['attachments'] as $filename => $filedata) {
      $message->attach( Swift_Attachment::newInstance($filedata, $filename) );
    }
  }

  // debug
  //header("Content-type: text/plain");
  //print $message->toString();
  //exit;

  ### Define transport
  $mailingMethod = coalesce($GLOBALS['SETTINGS']['advanced']['smtp_method'], 'php');
  if ($mailingMethod == 'php') {
    $transport = Swift_MailTransport::newInstance();
  }
  else {
    // get port
    $userDefinedPort = $GLOBALS['SETTINGS']['advanced']['smtp_port'];
    if     ($userDefinedPort)         { $port = $userDefinedPort; }
    elseif ($mailingMethod == 'ssl')  { $port = '587'; }
    elseif ($mailingMethod == 'tls')  { $port = '465'; }
    elseif (get_cfg_var('smtp_port')) { $port = get_cfg_var('smtp_port'); }
    else                              { $port = '25'; }

    // get transport
    $transport = Swift_SmtpTransport::newInstance();
    $transport->setHost( coalesce($GLOBALS['SETTINGS']['advanced']['smtp_hostname'], get_cfg_var('SMTP')) );
    $transport->setPort( coalesce($GLOBALS['SETTINGS']['advanced']['smtp_port'],     get_cfg_var('smtp_port')) );
    if ($mailingMethod == 'ssl') { $transport->setEncryption('ssl'); } // Future: If any servers don't have these add error checking with stream_get_transports()
    if ($mailingMethod == 'tls') { $transport->setEncryption('tls'); } // Future: If any servers don't have these add error checking with stream_get_transports()
    if ($GLOBALS['SETTINGS']['advanced']['smtp_username'] != '') { $transport->setUsername( $GLOBALS['SETTINGS']['advanced']['smtp_username'] ); }
    if ($GLOBALS['SETTINGS']['advanced']['smtp_password'] != '') { $transport->setPassword( $GLOBALS['SETTINGS']['advanced']['smtp_password'] ); }
    $transport->setTimeout(4);
    //ini_set("default_socket_timeout", 3); // not used yet
  }

  ### Send Message
  $messagesSent     = 0;
  $failedRecipients = array();
  $errors           = '';
  $sendStartTime    = microtime(true);
  static $totalMessagesSent = 0;
  static $iterations = 0;
  try {
    $iterations++;
    $transport->start();  // Catch and report authentication errors early
    $mailer       = Swift_Mailer::newInstance($transport);
    $messagesSent = $mailer->send($message, $failedRecipients);  // $messagesSent adds one for each address on TO: line, CC, BCC, address etc
    $totalMessagesSent += $messagesSent;
    //$mailer->getTransport()->stop(); // Possible bug fix?  Stop mailer so it will cleanly restart on next try: http://stackoverflow.com/a/17110512
  }
  catch (Exception $e) { // catch errors
    //$mailer->getTransport()->stop(); // Stop mailer on error so it will cleanly restart on next try: http://stackoverflow.com/a/22629213
    $thisMessageTime   = sprintf("%0.2f", microtime(true) - $sendStartTime);
    $totalScriptTime   = sprintf("%0.2f", microtime(true) - $_SERVER['REQUEST_TIME_FLOAT']);
    $error             = $e->getMessage() . " (total iterations: $iterations, send time: {$thisMessageTime}s, emails sent: {$messagesSent}, total emails sent: {$totalMessagesSent}, total script time: {$totalScriptTime}s)";
    @trigger_error($error, E_USER_NOTICE);   // log error
    $errors .= $e->getMessage() . "\n";      // return error
  }

  // Restore encoding
  if ($overrideEncoding) { mb_internal_encoding($mbEncoding); }

  // return errors
  $finalErrors = '';
  if ($messagesSent == 0) { $finalErrors .= t("No messages were sent, check mail server settings!") ."\n"; }
  if ($failedRecipients) {
    $emailsAsCSV = htmlencode(implode(', ', $failedRecipients));
    $finalErrors .= sprintf(t("Email delivery failed for: %s") . "\n", $emailsAsCSV);
  }
  $finalErrors .= $errors;
  return $finalErrors;

}

// Alernate to sendMessage(), puts message in _outgoing_mail with 'backgroundSend' set to 1.
// Usage: $mailErrors = sendBackground($options);
// if ($mailErrors) { die($mailErrors); }
// NOTE: Doesn't support headers or attachments yet and 'logging' setting is ignored.
// NOTE: Requires a background-mailer script that is running on a cron-job
// NOTE: For large volumes of email do a mail-merge via MySQL "INSERT FROM ... SELECT ... JOIN" to avoid timeouts
function sendBackground($options = array()) {
  if (@$options['disabled']) { return; }   // don't send if 'disabled' option set
  $hasText        = array_key_exists('text', $options);
  $hasHTML        = array_key_exists('html', $options);

  // error checking
  $errors = '';
  if (!isValidEmail( @$options['to'], true))     { $errors .= "'to' isn't a valid email '" .htmlencode($options['to']). "'!<br/>\n"; }
  if (!isValidEmail( @$options['from'] ))        { $errors .= "'from' isn't a valid email '" .htmlencode($options['from']). "'!<br/>\n"; }
  if (!array_key_exists('subject', $options))    { $errors .= "'subject' must be defined!<br/>\n"; }
  if (!$hasText && !$hasHTML)                    { $errors .= "Either 'text' or 'html' or both must be defined!<br/>\n"; }
  if (!isValidEmail( @$options['from'] ))        { $errors .= "'from' isn't a valid email '" .htmlencode($options['from']). "'!<br/>\n"; }
  if (!array_key_exists('subject', $options))    { $errors .= "'subject' must be defined!<br/>\n"; }
  if (array_key_exists('headers',     $options)) { $errors .= "'headers' not supported by sendBackground yet.<br/>\n"; }
  if (array_key_exists('attachments', $options)) { $errors .= "'attachments' not supported by sendBackground yet.<br/>\n"; }
  if ($errors) { return $errors; }

  // save message
  $colsToValues = array_slice_keys($options, array('from','to','subject','text','html'));
  $colsToValues['createdDate=']   = 'NOW()';
  $colsToValues['sent']           = '';
  $colsToValues['backgroundSend'] = '1';
  $colsToValues['reply-to']       = @$colsToValues['headers']['Reply-To'];
  $colsToValues['cc']             = @$colsToValues['headers']['CC'];
  $colsToValues['bcc']            = @$colsToValues['headers']['BCC'];
  mysql_insert('_outgoing_mail', $colsToValues, true);
}



// $isPreviewMode = isBeingRunDirectly();
// returns true if caller's file is the URL being executed (false if it's being included)
// note: this is useful for templates to determine if they should preview themselves
function isBeingRunDirectly() {
  global $CURRENT_USER;

  // if there's only one unique file in the backtrace, then it's being run directly
  // (this also handles cases where the script being executed calls a function in the same file)
  $callerFiles   = array_unique( array_pluck( debug_backtrace(), 'file' ) );
  $isPreviewMode = count($callerFiles) == 1;

  // error checking
  // 2.16 - not needed? - if ($isPreviewMode && !$CURRENT_USER['isAdmin']) { die("Preview Mode: You must be logged in as a CMS Admin user to preview email templates!"); }

  //
  return $isPreviewMode;
}

/*
  $emailHeaders = emailTemplate_loadFromDB(array(
    'template_id'        => 'USER-ACTION-NOTIFICATION',
    'placeholders'       => $placeholders,
  ));
 $mailErrors = sendMessage($emailHeaders);
 if ($mailErrors) { alert("Mail Error: $mailErrors"); }

  // optional options - you can also add these if needed
  'template_table'     => '_email_templates', // or leave blank for default: _email_templates
  'addHeaderAndFooter' => true,               // (default is true) set to false to disable automatic adding of HTML header and footer to email
*/
// v2.16 added 'logging' as a pass-thru field.
// v2.50 template_table now defaults to _email_templates
// v2.50 ID with language code is checked first if language set, eg: CMS-PASSWORD-RESET-EN then CMS-PASSWORD-RESET
function emailTemplate_loadFromDB($options) {

  // set defaults
  if (!@$options['template_table']) { $options['template_table'] = '_email_templates'; } // v2.50

  // error checking
  if (!$options['template_id']) { dieAsCaller(__FUNCTION__.": No 'template_id' set in options"); }
  if (!$options['placeholders']) { dieAsCaller(__FUNCTION__.": No 'placeholders' set in options"); }

  // load template
  $template = array();
  if (!$template) { // try and load custom translated TEMPLATE-ID with language suffix first, eg: MY-TEMPLATE-FR
    $template = mysql_get($options['template_table'], null, array('template_id' => $options['template_id'] .'-'. strtoupper($GLOBALS['SETTINGS']['language'])));
  }
  if (!$template) { // if not found, try loading default template next
    $template = mysql_get($options['template_table'], null, array('template_id' => $options['template_id']));
  }
  if (!$template) { // if not found, re-add default templates and try again unless template wasn't added or was removed
    emailTemplate_addDefaults();
    $template = mysql_get($options['template_table'], null, array('template_id' => $options['template_id']));
  }
  if (!$template) { // otherwise, die with error
    dieAsCaller(__FUNCTION__.": Couldn't find email template_id '" .htmlencode($options['template_id']). "'");
  }

  // get email values
  $emailHeaders = array();
  $emailHeaders['from']     = coalesce( @$options['override-from'],     $template['from'] );
  $emailHeaders['to']       = coalesce( @$options['override-to'],       $template['to'] );

  if ($template['reply-to'] || @$options['override-reply-to']) {
    $emailHeaders['headers']['Reply-To'] = coalesce( @$options['override-reply-to'], $template['reply-to'] );
  }
  if ($template['cc'] || @$options['override-cc']) {
    $emailHeaders['headers']['CC'] = coalesce( @$options['override-cc'], $template['cc'] );
  }
  if ($template['bcc'] || @$options['override-bcc']) {
    $emailHeaders['headers']['BCC'] = coalesce( @$options['override-bcc'], $template['bcc'] );
  }

  $emailHeaders['subject']  = coalesce( @$options['override-subject'],  $template['subject'] );
  $emailHeaders['disabled'] = coalesce( @$options['override-disabled'], @$template['disabled'] );
  $emailHeaders['html']     = coalesce( @$options['override-html'],     $template['html'] ); // v2.51
  $passThruFields = array('attachments','headers','logging');
  foreach ($passThruFields as $field) {
    if (!array_key_exists($field, $options)) { continue; }
    $emailHeaders[$field] = $options[$field];
  }

  // replace placeholders
  list($emailHeaders, $textPlaceholderList) = emailTemplate_replacePlaceholders($emailHeaders, @$options['placeholders']);

  // update template placeholder list
  if ($template['placeholders'] != $textPlaceholderList) {
    mysql_update($options['template_table'], $template['num'], null, array('placeholders' => $textPlaceholderList));
  }

  // error checking
  if (!$emailHeaders['from'])    { die(__FUNCTION__ . ": No 'From' set by program or email template id '" .htmlencode($options['template_id']). "'"); }
  if (!$emailHeaders['to'])      { die(__FUNCTION__ . ": No 'To' set by program or email template id '" .htmlencode($options['template_id']). "'"); }
  if (!$emailHeaders['subject']) { die(__FUNCTION__ . ": No 'Subject' set by program or email template id '" .htmlencode($options['template_id']). "'"); }
  if (!$emailHeaders['html'])    { die(__FUNCTION__ . ": No 'Message HTML' found by program or email template id '" .htmlencode($options['template_id']). "'"); }

  // add html header/footer
  if (@$options['addHeaderAndFooter'] !== false) { // added in 2.50
    $htmlTitle  = htmlencode($emailHeaders['subject']);
    $header = <<<__HTML__
<!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.0 Transitional//EN" "http://www.w3.org/TR/xhtml1/DTD/xhtml1-transitional.dtd">
<html xmlns="http://www.w3.org/1999/xhtml">
<head>
<meta http-equiv="Content-Type" content="text/html; charset=utf-8" />
<title>$htmlTitle</title>
</head>
<body>

<style type="text/css">
  p { margin-bottom: 1em; }
</style>


__HTML__;
// ***NOTE*** style tag is for Yahoo Mail which otherwise drops paragraph spacing - http://www.email-standards.org/blog/entry/yahoo-drops-paragraph-spacing/
// ... having a defined <title></title> helps get by spam filters

    $footer = <<<__HTML__
</body>
</html>
__HTML__;
  $emailHeaders['html'] = $header . $emailHeaders['html'] . $footer;
 }

  //
  return $emailHeaders;
}

// replace placeholders on specific email template fields
// Usage: list($emailHeaders, $textPlaceholderList) = emailTemplate_replacePlaceholders($emailHeaders, $placeholders);
function emailTemplate_replacePlaceholders($emailHeaders, $customPlaceholders) {
  $customPlaceholders = coalesce($customPlaceholders, array());
  $fieldnames = array('from','reply-to','to','cc','bcc','subject','html','headers');  // email header fields to replace placeholders in

  // set default placeholders (always available)
  $defaultPlaceholders = array();
  $defaultPlaceholders['server.http_host']     = @$_SERVER['HTTP_HOST'];
  $defaultPlaceholders['server.remote_addr']   = @$_SERVER['REMOTE_ADDR'];
  $defaultPlaceholders['settings.adminEmail']  = @$GLOBALS['SETTINGS']['adminEmail'];
  $defaultPlaceholders['settings.adminUrl']    = @$GLOBALS['SETTINGS']['adminUrl'];
  $defaultPlaceholders['settings.programName'] = @$GLOBALS['SETTINGS']['programName']; // added in 2.50

  // create text and html placeholders
  $textPlaceholders = $customPlaceholders + $defaultPlaceholders;
  $htmlPlaceholders = $customPlaceholders + array_map('nl2br', array_map('htmlencode', $defaultPlaceholders));

  // replace placeholders
  $searchPlaceholders = array();
  foreach (array_keys($textPlaceholders) as $key) { $searchPlaceholders[] = "#$key#"; }
  $replacementText    = array_values($textPlaceholders);
  $replacementHtml    = array_values($htmlPlaceholders);
  foreach ($fieldnames as $fieldname) {
    if (!array_key_exists($fieldname, $emailHeaders)) { continue; }
    if ($fieldname == 'html') { $emailHeaders[$fieldname] = str_replace($searchPlaceholders, $replacementHtml, $emailHeaders[$fieldname]); }
    else                      { $emailHeaders[$fieldname] = str_replace($searchPlaceholders, $replacementText, $emailHeaders[$fieldname]); }
  }

  // update text placeholder list
  $textPlaceholderList = '';
  foreach (array_keys($customPlaceholders)  as $placeholder) { $textPlaceholderList .= "#$placeholder#\n"; }
  foreach (array_keys($defaultPlaceholders) as $placeholder) { $textPlaceholderList .= "\n#$placeholder#"; }

  //
  return array($emailHeaders, $textPlaceholderList);
}

// Add a new template into the _email_templates table if template_id doesn't already exist
/* Usage:

  emailTemplate_addToDB(array(
    'template_id'  => 'EMAIL-TEMPLATE-NAME',
    'description'  => 'Description of what email template is used for',
    'from'         => '#settings.adminEmail#',
    'to'           => '#user.email#',
    'subject'      => '#server.http_host# Application Alert',
    'html'         => "<b>Hello World!</b>",
    'placeholders' => array('user.email', 'user.fullname'), // array of placeholder names
  ));

  // Note: Placeholders can also be text

*/
function emailTemplate_addToDB($record) {
  if (!$record['template_id']) { dieAsCaller(__FUNCTION__.": No 'template_id' set in options"); }

  // check if template id exists
  $templateExists = mysql_count('_email_templates', array('template_id' => $record['template_id']));
  if ($templateExists) { return false; }

  // get placeholder text
  $placeholderText = '';
  if (is_array($record['placeholders'])) {
    if ($record['placeholders']) { // if array isn't empty
      // hijack emailTemplate_replacePlaceholders() get us a placeholder list (including server placeholders) from placeholder array
      $placeholderText = array_value(emailTemplate_replacePlaceholders(array(), array_combine($record['placeholders'], $record['placeholders'])), 1);
    }
  }
  else {
    $placeholderText = $record['placeholders'];
  }

  // add template
  $colsToValues = array(
    'createdDate='     => 'NOW()',
    'createdByUserNum' => '0',
    'updatedDate='     => 'NOW()',
    'updatedByUserNum' => '0',
    'template_id'      => $record['template_id'],
    'description'      => $record['description'],
    'from'             => $record['from'],
    'reply-to'         => @$record['from'],
    'to'               => $record['to'],
    'cc'               => @$record['cc'],
    'bcc'              => @$record['bcc'],
    'subject'          => $record['subject'],
    'html'             => $record['html'],
    'placeholders'     => $placeholderText,
  );
  mysql_insert('_email_templates', $colsToValues, true);

  // set notice
  //if ($showNotice) {
  //  notice(t("Adding email template:"). htmlencode($colsToValues['template_id']). "<br/>\n");
  //}
}


// Add CMS email templates -AND- add email templates used by plugins as well
// Note: Email templates are only created if they don't already exist.
// Note: This function is called on the email templates (list menu), and when sending an email
// ... and the template-id so the templates will be created just before you need them. (easier to implement that then checkin in CMS/Plugin install/upgrade/etc)
function emailTemplate_addDefaults() {

  ### Add Plugin Templates
  doAction('emailTemplate_addDefaults');

  ### NOTE: Make sure this file (admin_functions.php) is saved as UTF-8 or chars with accents may not get saved to MySQL on insert

  ### Note: If you need to update a template that already exists, have your upgrade code either:
  ###         - backup the existing template as "template-id (backup-YYYYMMDD-HHMMSS)
  ###         - or create/overwrite a new template as "template-id (ORIGINAL)"

  // debug - output current templates
  //showme(mysql_select('_email_templates')); exit;

  // CMS-PASSWORD-RESET
  emailTemplate_addToDB(array(
    'template_id'  => "CMS-PASSWORD-RESET",
    'description'  => "Sent to users when they request to reset their password",
    'placeholders' => array('user.num','user.email','user.username','user.fullname','resetUrl'), // v3.06 - added username and fullname
    'from'         => "#settings.adminEmail#",
    'to'           => "#user.email#",
    'subject'      => "#settings.programName# Password Reset",
    'html'         => <<<__HTML__
<p>Hi #user.email#,</p>
<p>You requested a password reset for #settings.programName#.</p>
<p>To reset your password click this link:<br /><a href="#resetUrl#">#resetUrl#</a></p>
<p>This request was made from IP address: #server.remote_addr#</p>
__HTML__
  ));


  // CMS-PASSWORD-RESET-FR
  emailTemplate_addToDB(array(
    'template_id'  => "CMS-PASSWORD-RESET-FR",
    'description'  => "Sent to users when they request to reset their password (French)",
    'placeholders' => array('user.num','user.email','user.username','user.fullname','resetUrl'), // v3.06 - added username and fullname
    'from'         => "#settings.adminEmail#",
    'to'           => "#user.email#",
    'subject'      => "#settings.programName# Réinitialisation de votre mot de passe",
    'html'         => <<<__HTML__
<p>Bonjour #user.email#,</p>
<p>Vous avez demandé la réinitialisation de votre mot de passe.</p>
<p>Pour réinitialiser votre mot de passe cliquez sur le lien ci-dessous:<br /><a href="#resetUrl#">#resetUrl#</a></p>
<p></p>
<p>Cette demande a été faite à partir de l'adresse d'IP : #server.remote_addr#</p>
<p>Ne soyez pas inquiet si vous n'êtes pas à l'origine de cette demande, ces informations sont envoyées uniquement à votre adresse e-mail.</p>
<p>L'administrateur</p>
<p>#settings.programName#</p>
__HTML__
  ));

  // CMS-BGTASK-ERROR
  emailTemplate_addToDB(array(
    'template_id'  => "CMS-BGTASK-ERROR",
    'description'  => "Sent to admin when a background task fails",
    'placeholders' => array('bgtask.date','bgtask.activity','bgtask.summary','bgtask.completed','bgtask.function','bgtask.output','bgtask.runtime','bgtask.settingsUrl','bgtask.logsUrl'), // array of placeholder names
    'from'         => "#settings.adminEmail#",
    'to'           => "#settings.adminEmail#",
    'subject'      => "Background tasks did not complete",
    'html'         => <<<__HTML__
<p>The following Background Task did not complete successfully: </p>
<table border="0">
<tbody>
<tr><td>Date/Time</td><td> : </td><td>#bgtask.date#</td></tr>
<tr><td>Activity</td><td> : </td><td>#bgtask.activity#</td></tr>
<tr><td>Summary</td><td> : </td><td>#bgtask.summary#</td></tr>
<tr><td>Completed</td><td> : </td><td>#bgtask.completed#</td></tr>
<tr><td>Function</td><td> : </td><td>#bgtask.function#</td></tr>
<tr><td>Output</td><td> : </td><td>#bgtask.output#</td></tr>
<tr><td>Runtime</td><td> : </td><td>#bgtask.runtime# seconds</td></tr>
</tbody>
</table>
<p>Please check the Background Tasks logs here and check for additional errors:<br />#bgtasks.logsUrl#</p>
<p>You can review the Background Tasks status &amp; settings here: <br />#bgtasks.settingsUrl#</p>
<p>*Please note, incomplete background task alerts are only sent once an hour.</p>
__HTML__
  ));

  // CMS-ERRORLOG-ALERT
  emailTemplate_addToDB(array(
    'template_id'  => "CMS-ERRORLOG-ALERT",
    'description'  => "Sent to admin when a php error or warning is reported",
    'placeholders' => array('error.hostname','error.latestErrorsList','error.errorLogUrl'), // array of placeholder names
    'from'         => "#settings.adminEmail#",
    'reply-to'     => "#settings.adminEmail#",
    'to'           => "#settings.adminEmail#",
    'cc'           => "",
    'bcc'          => "",
    'subject'      => "Errors reported on: #error.hostname#",
    'html'         => <<<__HTML__
<p>One or more php errors have been reported on:<strong> #error.hostname#</strong></p>
<p>Check the error log for complete list and more details:<br /><a href="#error.errorLogUrl#">#error.errorLogUrl#</a></p>
<p>Latest errors: </p>
<p style="padding-left: 30px;"><span style="color: #808080;">#error.latestErrorsList#</span></p>
<p><strong>*Note: Email notifications of new errors are only sent once an hour.</strong></p>
__HTML__
  ));

}

//
function formatBytes($bytes, $precision = 2) {
  $units = array('B', 'KB', 'MB', 'GB', 'TB', 'PB');

  $bytes  = max($bytes, 0);
  $pow    = floor(($bytes ? log($bytes) : 0) / log(1024));
  $pow    = min($pow, count($units) - 1);
  $bytes /= pow(1024, $pow);

  return round($bytes, $precision) . ' ' . $units[$pow];
}


// for displaying urls in html, encode spaces (which aren't valid xhtml in URIs), and html encode &
function urlencodeSpaces($value) {
  return str_replace(' ', '%20', htmlencode($value));
}

//
function isAjaxRequest() {
  $isAjaxRequest = strtolower(@$_SERVER['HTTP_X_REQUESTED_WITH']) == 'xmlhttprequest';
  return $isAjaxRequest;
}

// Detect if this submissions is from the Flash Uploader, dies on invalid (forged?) or incomplete flash uploader requests
// Note: We can't use 'HTTP_USER_AGENT' to test for "Shockwave Flash" anymore because Chrome sets it to Chrome's User-Agent
// Note: We can't rely on 'HTTP_REFERER' because Firefox 33 sometimes sets it as blank
function isFlashUploader() {
  if (!@$_REQUEST['_FLASH_UPLOADER_'] && !@$_REQUEST['_FLASH_COOKIE_BUG_FIX_']) { return false; } // test for both so cookie bug fix can only be used by flash uploader and upload submissions
  // Past this line and we're dealing with the flash uploader (or someone pretending to be the flash uploader)

  // debug - log flash uploader requests
  $logRequests = false; // for debugging
  if ($logRequests) {
    $log  = "HTTP_USER_AGENT: ". $_SERVER['HTTP_USER_AGENT']. "\n";
    if ($_POST)   { $log .= "_POST: " .print_r($_POST, true). "\n"; }
    if ($_FILES)  { $log .= "_FILES: " .print_r($_FILES, true). "\n"; }
    //if ($_SERVER) { $log .= "_SERVER: " .print_r($_SERVER, true). "\n"; }
    if ($_COOKIE)  { $log .= "_COOKIE: " .print_r($_COOKIE, true). "\n"; }
    $log .= "\n";
    file_put_contents(SCRIPT_DIR. "/data/debug_flash_uploader.log", $log, FILE_APPEND);
  }

  // error checking: test for required and unique flash uploader fields
  $errors = '';
  if ($_SERVER['REQUEST_METHOD'] != 'POST') { die("REQUEST_METHOD must be POST\n"); }
  else {
    if (!@$_POST['_FLASH_COOKIE_BUG_FIX_'])        { die("No _FLASH_COOKIE_BUG_FIX_ value submitted!\n"); }
    if (!@$_POST['_FLASH_UPLOADER_'])              { die("No _FLASH_UPLOADER_ value submitted!\n"); }
    if (getRequestedAction() != 'uploadForm')      { die("getRequestedAction() must be 'uploadForm'!\n"); } // SECURITY: this prevents _FLASH_COOKIE_BUG_FIX_ flash login from being used for anything but uploads
    if (!@$_POST['submitUploads'])                 { die("No submitUploads value submitted!\n"); } // SECURITY: This prevents anything but saving of uploads (won't even allow displaying upload form)
    //if (!@$_FILES)                                 { die("Nothing is in _FILES array!\n"); }
    //elseif (!@$_FILES['Filedata'])              { die("Nothing is in _FILES['Filedata'] array!\n"); }
  }
  if ($errors) { die(__FUNCTION__. ": Flash Uploader Errors\n$errors!\n"); }

  //
  return true;



}

//
function isWindows() {
  return PHP_OS == 'WINNT';

  // other possible values: WIN32, WINNT, WIN64, Windows
  // http://stackoverflow.com/questions/738823/possible-values-for-php-os

  // detecting process bits: http://blogs.msdn.com/b/david.wang/archive/2006/03/26/howto-detect-process-bitness.aspx
  // and http://ss64.com/nt/syntax-64bit.html

}

// depth of 0 gets just files in current directory
// $files = scandir_recursive($dir, '/\.php$/', 2);
// foreach ($files as $index => $file) { $myfiles[$index] = str_replace("$dir/", '', $file); } // remove basedir
function scandir_recursive($dir, $regexp = '', $depth = 99) {
  $matchingFiles = array();

  if ($dirHandle = @opendir($dir)) {
    while (($file = readdir($dirHandle)) !== false) {
      if ($file == '.' || $file == '..') { continue; }
      $filepath = "$dir/$file";

      if (is_file($filepath)) {   // skip symolic links, etc
        if ($regexp && !preg_match($regexp, $filepath)) { continue; }
        $matchingFiles[] = $filepath;
      }
      elseif ($depth && is_dir($filepath)) {
        $newFiles      = scandir_recursive($filepath, $regexp, $depth - 1);
        $matchingFiles = array_merge($matchingFiles, $newFiles);
        continue;
      }
    }
    closedir($dirHandle);
  }

  return $matchingFiles;
}

// turn off output buffering so output is returned and displayed in real-time.
// Attempts to disable or bypass php, and browser output buffering.
function ob_disable($forHTML = false) {
  // Note: PHP only returns output in blocks of bytes whose size is defined by php.ini 'output_buffering', see: http://www.php.net/manual/en/outcontrol.configuration.php#ini.output-buffering
  // Note: Web Servers can also buffer the results for encoding (gzip, deflate)
  // Note: Browsers also buffer content and don't display anything until they've received x bytes, the entire page, or an html tag

  // Web Server Buffering: turn off output compression which requires all output be buffered in advance before being compressed
  // v2.50 uncommented these features
  ini_set('zlib.output_compression', 0); // if this was previously turned on, it's output buffer is disabled below
  if (function_exists('apache_setenv')) {
    apache_setenv('no-gzip', 1);
    apache_setenv('no-mod_gzip_on', 'No');
  }

  // PHP Buffering: turn off _all_ PHP output-buffering, including user defined and internal PHP output buffering, see ob_get_status(true) for details
  while (@ob_end_flush()); // disable any and all output buffers (so they don't interfere)

  // Browser Buffering: bypass browser buffering by overflowing browser output buffer.
  if ($forHTML) { echo "<!--"; } // for html pages, hide whitespace in comment
  echo str_repeat(' ',1024) . "\n";
  if ($forHTML) { echo "-->\n"; }
}

/*
  Wrap a call to a function in ob_start/ob_get_clean so that a function designed to output html can return html instead.
  Intended for use with functions written in the outputting html style -- i.e. with ?>...<?php
  $buttonsHtml = ob_capture('generateSomeHtml');                     // calls generateSomeHtml() and returns its captured output
  $buttonsHtml = ob_capture(function() { generateSomeHtml(); });     // same thing
  $buttonsHtml = ob_capture(function() { generateSomeHtml(123); });  // if the function needs to be called with arguments, this form is required
  $buttonsHtml = ob_capture(function() { ?>Hello world!<?php });     // silly example, never do this!
  function generateSomeHtml() {                                      // sample function written in the outputting html style
    ?>
      <input name="<?php echo $GLOBALS['foo'] ?>" value="<?php echo htmlencode(@$_REQUEST['foo']) ?>">
      <input name="<?php echo $GLOBALS['bar'] ?>" value="<?php echo htmlencode(@$_REQUEST['bar']) ?>">
    <?php
  }
*/
function ob_capture($function) {
  $args = func_get_args();
  array_shift($args);
  
  ob_start();
  
  call_user_func_array($function, $args);
  
  return ob_get_clean();
}


//
function _getRecordValuesFromFormInput($fieldPrefix = '') {
  global $schema, $CURRENT_USER, $tableName, $isMyAccountMenu;

  $recordValues = array();
  $specialFields = array('num', 'createdDate', 'createdByUserNum', 'updatedDate', 'updatedByUserNum');

  // load schema columns
  foreach (getSchemaFields($schema) as $fieldname => $fieldSchema) {
    if (!userHasFieldAccess($fieldSchema)) { continue; } // skip fields that the user has no access to
    if ($tableName == 'accounts' && $fieldname == 'isAdmin' && !$CURRENT_USER['isAdmin']) { continue; } // skip admin only fields

    // special cases: don't let user set values for:
    if (in_array($fieldname, $specialFields)) { continue; }
    if ($isMyAccountMenu) {
      if (@!$fieldSchema['myAccountField'])                                 { continue; } // my account - skip fields not displayed or allowed to be edited in "my account"
      if ($fieldname == 'password' && !@$_REQUEST[$fieldPrefix.'password']) { continue; } // my account - skip password field if no value submitted
    }

    //
    switch (@$fieldSchema['type']) {
      case 'textfield':
      case 'wysiwyg':
      case 'checkbox':
      case 'parentCategory':
        $recordValues[$fieldname] = isset($_REQUEST[$fieldPrefix.$fieldname]) ? $_REQUEST[$fieldPrefix.$fieldname] : '';
        break;

      case 'textbox':
        $fieldValue = $_REQUEST[$fieldPrefix.$fieldname];
        if ($fieldSchema['autoFormat']) {
          $fieldValue = preg_replace("/\r\n|\n/", "<br/>\n", $fieldValue); // add break tags
        }
        $recordValues[$fieldname] = $fieldValue;
        break;

      case 'date':
        $recordValues[$fieldname] = sprintf("%04d-%02d-%02d %02d:%02d:%02d", $_REQUEST["$fieldPrefix$fieldname:year"], $_REQUEST["$fieldPrefix$fieldname:mon"], $_REQUEST["$fieldPrefix$fieldname:day"], _getHour24ValueFromDateInput($fieldPrefix.$fieldname), (int) @$_REQUEST["$fieldPrefix$fieldname:min"], (int) @$_REQUEST["$fieldPrefix$fieldname:sec"]);
        break;

      case 'list':
        if (is_array(@$_REQUEST[$fieldPrefix.$fieldname]) && @$_REQUEST[$fieldPrefix.$fieldname]) {
          // store multi-value fields as tab delimited with leading/trailing tabs
          // for easy matching of single values - LIKE "%\tvalue\t%"
          $recordValues[$fieldname] = "\t" . join("\t", $_REQUEST[$fieldPrefix.$fieldname]) . "\t";
        }
        else {
          $recordValues[$fieldname] = @$_REQUEST[$fieldPrefix.$fieldname];
        }
        break;

      case 'upload':
        // images need to be loaded with seperate function call.
        break;

      case 'dateCalendar':
        _updateDateCalendar($fieldname);
        break;

      // ignored fields
      case '':               // ignore these fields when saving user input
      case 'none':           // ...
      case 'separator':      // ...
      case 'relatedRecords': // ...
      case 'accessList':     // ...
        break;

      default:
        die(__FUNCTION__ . ": field '$fieldname' has unknown field type '" .@$fieldSchema['type']. "'");
        break;
    }
  }

  return $recordValues;
}

//
function _getHour24ValueFromDateInput($fieldname) {
  $hour24 = 0;

  // convert 12hour format to 24hour format
  if (array_key_exists("$fieldname:hour12", $_REQUEST)) {
    $hour12 = $_REQUEST["$fieldname:hour12"];
    $isPM   = $_REQUEST["$fieldname:isPM"];
    $isAM   = !$isPM;
    if      ($isAM && $hour12 == 12) { $hour24 = 0; }
    else if ($isAM)                  { $hour24 = $hour12; }
    else if ($isPM && $hour12 == 12) { $hour24 = 12; }
    else if ($isPM)                  { $hour24 = $hour12 + 12; }
  }
  elseif (array_key_exists("$fieldname:hour24", $_REQUEST)) {
    $hour24 = $_REQUEST["$fieldname:hour24"];
  }
  else {
    $hour24 = '0';
  }


  return $hour24;
}

//
function _updateDateCalendar($fieldname) {
  global $TABLE_PREFIX, $tableName;
  $calendarTable = $TABLE_PREFIX . "_datecalendar";

  // call ONCE per field
  static $calledFor = array();
  if (@$calledFor[$fieldname]++) { return; }

  // check if table exists
  static $tableExists = false;
  if (!$tableExists) {
    $result      = mysqli()->query("SHOW TABLES LIKE '$calendarTable'") or die("MySQL Error: ". htmlencode(mysqli()->error) . "\n");
    $tableExists = $result->fetch_row()[0];
    if (is_resource($result)) { mysqli_free_result($result); }
  }

  // create table if it doesn't exists
  if (!$tableExists) {
    $createSql = "CREATE TABLE  `$calendarTable` (
                  `num` int(10) unsigned NOT NULL auto_increment,
                  `tableName` varchar(255) NOT NULL,
                  `fieldName` varchar(255) NOT NULL,
                  `recordNum` varchar(255) NOT NULL,
                  `date`      date,
                  PRIMARY KEY  (`num`)
                ) ENGINE=MyISAM DEFAULT CHARSET=utf8;";
    mysqli()->query($createSql) or die("MySQL Error: ". htmlencode(mysqli()->error) . "\n");
  }

  // build queries
  $eraseDatesAsCSV = "0";
  $insertValues    = '';
  $recordNum       = (int) $_REQUEST['num'];
  foreach (array_keys($_REQUEST) as $formFieldname) {
    if (!preg_match("/^$fieldname:/", $formFieldname)) { continue; }
    list(,$dateString) = explode(":", $formFieldname);
    if (!$dateString) { continue; }

    if ($_REQUEST[$formFieldname]) {
      if ($insertValues) { $insertValues .= ",\n"; }
      $insertValues .= "('$tableName','$fieldname','$recordNum','$dateString')";
    }
    else {
      $eraseDatesAsCSV .= ",'" . ((int) $dateString) . "'";
    }
  }

  // remove dates
  $deleteQuery  = "DELETE FROM `$calendarTable` ";
  $deleteQuery .= "WHERE `tablename` = '$tableName' ";
  $deleteQuery .= "  AND `fieldname` = '$fieldname' ";
  $deleteQuery .= "  AND `recordNum` = '".mysql_escape($_REQUEST['num'])."' ";
  $deleteQuery .= "  AND `date` IN ($eraseDatesAsCSV)";
  mysqli()->query($deleteQuery) or die("MySQL Error: ". htmlencode(mysqli()->error) . "\n");

  // add dates
  if ($insertValues) {
    $insertQuery  = "INSERT INTO `$calendarTable` (`tableName`,`fieldName`,`recordNum`,`date`) VALUES $insertValues";
    mysqli()->query($insertQuery) or die("MySQL Error: ". htmlencode(mysqli()->error) . "\n");
  }

}

//
function _addUndefinedDefaultsToNewRecord($colsToValues, $mySqlColsAndTypes) {
  global $schema;
  if (!$schema) { die("No \$schema defined!"); }



  $currentDate  = date("Y-m-d H:i:s"); // set default to required Format: YYYY-MM-DD HH:MM:SS
  $dateFieldDefault = $currentDate;

  foreach ($mySqlColsAndTypes as $colName => $colType) {

    // set special field values
    if      ($colName == 'createdDate')      { $colsToValues[$colName] = $currentDate;  }
    else if ($colName == 'createdByUserNum') { $colsToValues[$colName] = $GLOBALS['CURRENT_USER']['num']; }
    else if ($colName == 'dragSortOrder')    { $colsToValues[$colName] = @$_REQUEST['dragSortOrder'] ? $_REQUEST['dragSortOrder'] : time(); }
    else if ($colName == 'siblingOrder')     { $colsToValues[$colName] = time(); } // sort to bottom

    // skip fields with a value already
    if (array_key_exists($colName, $colsToValues))      { continue; } // skip if already defined

    //Pick a default date to use for date fields
    if ((@$schema[$colName]['type'] == 'date')) {
      if ((@$schema[$colName]['defaultDate']=='custom') && (@$schema[$colName]['defaultDateString'])) {
        $dateFieldDefault = date("Y-m-d H:i:s", strtotime($schema[$colName]['defaultDateString']));
      }
      elseif ($schema[$colName]['defaultDate']=='none') {
        $dateFieldDefault = "0000-00-00 00:00:00";
      }
      else {
        $dateFieldDefault = $currentDate;
      }
    }

    // set adminOnly fields to default value (they'd have a value assigned already if user was admin)
    $isAdminOnly    = @$schema[$colName]['adminOnly'];
    $fieldType      = @$schema[$colName]['type'];
    if ($isAdminOnly && $fieldType == 'textfield')      { $colsToValues[$colName] = getEvalOutput(@$schema[$colName]['defaultValue']); }
    else if ($isAdminOnly && $fieldType == 'list')      { $colsToValues[$colName] = getEvalOutput(@$schema[$colName]['defaultValue']); }
    else if ($isAdminOnly && $fieldType == 'textbox')   { $colsToValues[$colName] = getEvalOutput(@$schema[$colName]['defaultContent']); }
    else if ($isAdminOnly && $fieldType == 'wysiwyg')   { $colsToValues[$colName] = getEvalOutput(@$schema[$colName]['defaultContent']); }
    else if ($isAdminOnly && $fieldType == 'checkbox')  { $colsToValues[$colName] = (int) @$schema[$colName]['checkedByDefault']; }

    // set default values for insert
    else if (@$schema[$colName]['type'] == 'date')      { $colsToValues[$colName] = $dateFieldDefault; } // default date fields to default date
    else if (preg_match("/^\w*datetime/i", $colType))   { $colsToValues[$colName] = $currentDate; } // default date fields to current date
    else if (preg_match("/^\w*int/i", $colType))        { $colsToValues[$colName] = '0'; }          // default numeric fields to 0
    else                                                { $colsToValues[$colName] = ''; }           // default all other field to blank
  }
  return $colsToValues;
}

// returns the first argument which evaluates to true (similar to MySQL's COALESCE() which returns the first non-null argument) or the last argument
// PHP Alternative: $val = $expr1 ?: $expr2 ?: $expr3  // Ref: http://php.net/manual/en/language.operators.comparison.php#language.operators.comparison.ternary
function coalesce() {
  $lastArg = null;
  foreach (func_get_args() as $arg) {
    if ($arg) { return $arg; }
    $lastArg = $arg;
  }
  return $lastArg;
}

//
function isHTTPS() {
  if (isset($_SERVER['HTTPS'])       && $_SERVER['HTTPS']        == 'on') { return true; }
  if (isset($_SERVER['SERVER_PORT']) && @$_SERVER['SERVER_PORT'] == 443)  { return true; }
  return false;
}

// Check if current users IP is in the list of allowed IPs for CMS access
function isIpAllowed($forceCheck = false, $allowedIPsAsCSV = '') {
  if (!$GLOBALS['SETTINGS']['advanced']['restrictByIP'] && !$forceCheck) { return true; } // if not restricted, always allow

  if (!$allowedIPsAsCSV) { $allowedIPsAsCSV = $GLOBALS['SETTINGS']['advanced']['restrictByIP_allowed']; }
  $allowedIPs = preg_split("/[\s,]+/", $allowedIPsAsCSV);
  $isAllowed  = in_array($_SERVER['REMOTE_ADDR'], $allowedIPs);

  return $isAllowed;
}


// removes and returns a suffix character from a string if present
// list($field, $suffixChar) = extractSuffixChar($field, '=');
function extractSuffixChar($string, $acceptableSuffixChars) {
  if (!strlen($string)) { return array($string, null); }
  $suffixChar = substr($string, -1);
  if (strpos($acceptableSuffixChars, $suffixChar) === FALSE) {
    return array($string, null);
  }
  $newString = substr($string, 0, -1);
  return array($newString, $suffixChar);
}


//
function saveSettings( $forceDevFile = false ) { // added arg in 2.53
  if (inCLI() && !isInstalled()) { return; }    // prevent cron.php from saving auto-generated settings until software has been installed (not all setting defaults can be set from CLI as $_SERVER values aren't available).

  $settingsPath = $forceDevFile ? SETTINGS_DEV_FILEPATH : SETTINGS_FILEPATH;
  saveStruct($settingsPath, $GLOBALS['SETTINGS'], true);
}

// Display human readable date relative to current time, eg: a few seconds ago, about a minute ago, 3 hours ago, etc
// Doesn't yet handle future dates (just shows "in the future");
// Usage: echo prettyDate( 1307483052 );          // unixtime or PHP time()
// Usage: echo prettyDate('2011-06-07 16:04:23'); // MySQL date string
function prettyDate($dateStringOrUnixTime) {

  // 2.50 check for 0000-00-00 00:00:00
  // 2.51 check for '' and 0
  if ($dateStringOrUnixTime == '0000-00-00 00:00:00' || !$dateStringOrUnixTime) { return 'never'; }

  // get unixTime and secondsOld
  $isUnixTime = (strval($dateStringOrUnixTime) === strval(intval($dateStringOrUnixTime)));
  $time       = $isUnixTime ? $dateStringOrUnixTime : @strtotime($dateStringOrUnixTime); // strtotime returns false or -1 on failure
  if ($time <= 0) { dieAsCaller(__FUNCTION__ .": Unrecognized date string '" .htmlencode($dateStringOrUnixTime). "'"); }

  //
  $secondsOld   = time() - $time;
  $secondsOldSM = strtotime('00:00') - $time; // seconds old since midnight of current day
  $minutesOld   = intval($secondsOld / 60);
  $hoursOld     = intval($secondsOld / (60*60));
  $daysOld      = ceil($secondsOldSM / (60*60*24));   // since midnight, rounded up
  $monthsOld    = intval($daysOld    / 30);           // approx months (assumes 30 days/month)

  #print " - $secondsOld seconds old, $minutesOld min old, $hoursOld hours old, $daysOld daysOld, $monthsOld months Old - ";

  //
  if ($monthsOld  >= 6)   { return date('F j, Y', $time); }                         // March 9, 2010
  if ($daysOld    >= 5)   { return date('F j', $time); }                            // June 2
  if ($daysOld    >= 2)   { return date('l', $time) .' at '. date('g:ia', $time); } // Tuesday at 1:26pm
  if ($daysOld    >= 1)   { return 'Yesterday at ' . date('g:ia', $time); }         // Yesterday at 1:26pm
  if ($hoursOld   >= 2)   { return "$hoursOld hours ago"; }                         // 4 hours ago
  if ($minutesOld >= 46)  { return "about an hour ago"; }                           // about an hour ago
  if ($minutesOld >= 2)   { return "$minutesOld minutes ago"; }                     // 38 minutes ago
  if ($secondsOld >= 60)  { return "about a minute ago"; }                          // about a minute ago
  if ($secondsOld >= 3)   { return "$secondsOld seconds ago"; }                     // 47 seconds ago
  if ($secondsOld >= 0)   { return "a few seconds ago"; }                           // a few seconds ago
  if ($secondsOld <  0)   { return 'In the future'; }                               // in the future
}

// echo xxx_pluralize($count, '%d cow', '%d cows');
function xxx_pluralize($quantity, $singular, $plural) {
  return sprintf($quantity == 1 ? $singular : $plural, $quantity);
}

// replace and collapse slashes in filepaths
function fixSlashes($path) {
  $isUncPath  = substr($path, 0, 2) == '\\\\';
  $path = preg_replace('/[\\\\\/]+/', '/', $path); // replace and collapse slashes
  if ($isUncPath) { $path = substr_replace($path, '\\\\', 0, 1); } // replace UNC prefix

  return $path;
}

// perl-style hash slicing
// $inventory = array('apple' => 14, 'orange' => 27, 'salmon' => 6);
// $fruitCounts = array_slice_keys($inventory, array('apple', 'orange')); // returns array('apple' => 14, 'orange' => 27)
// $fruitCounts = array_slice_keys($inventory, 'apple', 'orange'); // alternately
// NOTE: This function will create keys in returned array even if they don't exist in source array
// NOTE: Alternate PHP-only version that doesn't do that: array_intersect_key($sourceArray, array_flip($keysArray));
function array_slice_keys($array, $keys) {
  if (!is_array($keys)) {
    $keys = func_get_args();
    array_shift($keys); // get rid of the first element
  }
  $results = array();
  foreach ($keys as $key) {
    $results[$key] = @$array[$key];
  }
  return $results;
}

// add a prefix to the keys in an array
// $array = array_keys_prefix('billing_', array('city' => 'Austin', 'state' => 'TX')); // returns array('billing_city' => 'Austin', 'billing_state' => 'TX')
function array_keys_prefix($prefix, $array) {
  if (is_array($prefix)) { die(__FUNCTION__ . ": the first argument should be a string"); }
  if (!$array) { return array(); }
  $newArray = array();
  foreach (array_keys($array) as $key) {
    $newArray["$prefix$key"] = &$array[$key];
    unset($array[$key]);
  }
  return $newArray;
}

// functional interface for preg_match: works exactly like preg_match EXCEPT returns array with ($success, $brackets1, $brackets2, ...
// note that you can still use the $matches array, and if you need the entire matches string just surround your pattern in quotes.
// list($success, $brackets1, $brackets2, $brackets3) = preg_match_get('/(pa)(tt)(ern)?/', $subject);
// list(,$brackets1) = preg_match_get('/(pa)(tt)(ern)?/', $subject);
// $brackets1 = preg_match_get('/(pa)(tt)(ern)?/', $subject)[1];
function preg_match_get($pattern, $subject, &$matches = [], $flags = 0, $offset = 0) {
  $return = preg_match($pattern, $subject, $matches, $flags, $offset);
  array_shift($matches); // remove $matchedString since we want $brackets1 to correspond to array index[1]
  return array_merge(array($return), $matches, array_fill(0,99,null));
  // pad with nulls to avoid "Undefined offset:" errors if we return less than the user is expecting when we don't get a match
}


// get the currently logged in CMS user, even if user is logged into a session with a different plugin or application
// closes original session, starts CMS session, loads current user record, closes CMS session, then restores original session
// Usage: $CMS_USER = getCurrentUserFromCMS();
function getCurrentUserFromCMS() {
  // NOTE: Keep this in /lib/common.php, not login_functions.php or user_functions.php so no extra libraries need to be loaded to call it
  require_once SCRIPT_DIR . "/lib/login_functions.php";  // if not already loaded by a plugin - loads getCurrentUser() and accountsTable();

  // save old cookiespace and accounts table
  $oldCookiePrefix  = array_first(cookiePrefix(false, true)); // save old cookiespace
  $oldAccountsTable = accountsTable();                        // save old accounts table

  // switch to cms admin cookiespace and accounts table and load current CMS user
  cookiePrefix('cms');                                        // switch to CMS Admin cookiespace
  accountsTable('accounts');                                  // switch to CMS Admin accounts table

  //
  $cookieLoginDataEncoded = getPrefixedCookie( loginCookie_name() );
  if ($cookieLoginDataEncoded) { $cmsUser = getCurrentUser($loginExpired); }
  else { $cmsUser = array(); }

  // 2.52 - load cms users accessList (needed by viewer_functions.php for previewing)
  if ($cmsUser && $cmsUser['num']) { // 2.64 - only add if user found
    $records = mysql_select('_accesslist', array('userNum' => $cmsUser['num']));
    foreach ($records as $record) {
      $cmsUser['accessList'][ $record['tableName'] ]['accessLevel'] = $record['accessLevel'];
      $cmsUser['accessList'][ $record['tableName'] ]['maxRecords']  = $record['maxRecords'];
    }
  }

  // switch back to previoius cookiespace and accounts table
  cookiePrefix($oldCookiePrefix);
  accountsTable($oldAccountsTable);

  //
  return $cmsUser;
}

// Strip tags/attrs out of html using 3rd party HTMLPurifier (http://htmlpurifier.org) - added in 2.51
function htmlPurify($html, $config = array()) {
  // load library if it's not already loaded
  require_once(CMS_ASSETS_DIR."/3rdParty/HTMLPurifier/HTMLPurifier.standalone.php");

  // default config
  if (!$config) {  //
    $config['HTML.Allowed'] = 'a[href],b,strong,i,em,u,strike,br,p';  // Strip everything except listed attributes out of HTML
  }

  // set cache settings
  $config['Cache.DefinitionImpl'] = null;                            // disable - http://htmlpurifier.org/live/configdoc/plain.html#Cache.DefinitionImpl
  $config['Cache.SerializerPath'] = DATA_DIR . "/cache/htmlPurify";  // http://htmlpurifier.org/live/configdoc/plain.html#Cache.SerializerPath

  // configure HTMLPurifier
  $configObject = HTMLPurifier_Config::createDefault();
  foreach ($config as $key => $value) {
    $configObject->set($key, $value);
  }

  // purify and return the result
  $purifier = new HTMLPurifier($configObject);
  return $purifier->purify($html);
}

// return null if argument is boolean false - v2.52
function nullIfFalse($var) {
  return $var ? $var : null;
}

// For resizing images, specify the original image h/w and the target h/w and function will return a h/w
// ... that fits within target and still maintains aspect ratio. By default it will not scale up images.
// Examples:
// image_resizeCalc(32, 32, 50, 50)      --> array(32, 32, 1)        // image was not scaled up
// image_resizeCalc(64, 64, 50, 50)      --> array(50, 50, 0.78125)  // image was scaled down to fit
// image_resizeCalc(1920, 1080, 100, 50) --> array(89, 50, 0.046...) // image was height-bound
// image_resizeCalc(1920, 1080, 50, 100) --> array(50, 28, 0.026...) // image was width-bound
// list($finalWidth, $finalHeight, $scaledBy) = image_resizeCalc($upload['width'], $upload['height'], 50, 50);
function image_resizeCalc($sourceWidth, $sourceHeight, $targetWidthMax, $targetHeightMax, $allowScaleUp = false) {

  // catch divide by zero errors
  if ($sourceHeight == 0 || $sourceWidth == 0 || $targetHeightMax == 0) { return array(0, 0, 0); }

  $sourceAspectRatio    = $sourceWidth    / $sourceHeight;
  $targetAspectRatioMax = $targetWidthMax / $targetHeightMax;

  if ($sourceAspectRatio < $targetAspectRatioMax) { $scale = $targetHeightMax / $sourceHeight; }
  else                                            { $scale = $targetWidthMax  / $sourceWidth; }

  if ($allowScaleUp == false && $scale > 1) {
    $scale = 1;
  }

  //
  $finalWidth  = max(1, round($sourceWidth  * $scale));
  $finalHeight = max(1, round($sourceHeight * $scale));
  return array($finalWidth, $finalHeight, $scale);
}



// security: reduce xsrf attack vectors by denying non-POST forms
function security_dieUnlessPostForm() {
  if ($_SERVER['REQUEST_METHOD'] != 'POST') {
    $error = t("Error: Form method must be POST!");
    die($error);
  }
}

// Only allow POST requests if an internal referer is sent. On error die's with error message
// Usage: alert(security_dieUnlessInternalReferer())
function security_dieUnlessInternalReferer() {
  if (!@$GLOBALS['SETTINGS']['advanced']['checkReferer']) { return; }

  $error             = '';
  $programBaseUrl    = _security_getProgramBaseRefererUrl();
  $isInternalReferer = startsWith($programBaseUrl, $_SERVER['HTTP_REFERER']);
  if (!$isInternalReferer) {
    $format  = "Security Warning: A form submission from an external source has been detected and automatically disabled.\n";
    $format .= "For security form posts are only accepted from: %1\$s\n";
    $format .= "Your browser indicated the form was sent from: %2\$s\n";
    $error   = nl2br(sprintf(t($format), htmlencode($programBaseUrl), htmlencode($_SERVER['HTTP_REFERER'])));
    if (isAjaxRequest()) { $error = strip_tags(html_entity_decode($error)); }
    die($error);

  }
}


// Security measures to prevent Cross-Site_Request_Forgery -
// Note: We implement both recommended and non-recommended practices as the combination further reduces possible attack vectors
// Ref: https://www.owasp.org/index.php/Cross-Site_Request_Forgery_(CSRF)_Prevention_Cheat_Sheet
function security_dieOnInvalidCsrfToken() {

  ### Validate for CSRF Token
  $errors = '';
  $token = @$_POST['_CSRFToken'];
  if     (array_key_exists('_CSRFToken', $_GET))      { $errors .= t("Security Error: _CSRFToken is not allow in GET urls, try using POST instead.") . "\n"; }
  elseif (!array_key_exists('_CSRFToken', $_SESSION)) { $errors .= t("Security Error: No _CSRFToken exists in session.  Try reloading or going back to previous page.") . "\n"; }
  elseif ($token == '')                               { $errors .= t("Security Error: No _CSRFToken value was submitted.  Try reloading or going back to previous page.") . "\n";  }
  elseif ($token != $_SESSION['_CSRFToken'])          { $errors .= t("Security Error: Invalid _CSRFToken.  Try reloading or going back to previous page.") . "\n"; }
  //
  if ($errors) {
    @trigger_error($errors, E_USER_NOTICE);
    die($errors);
  }
}



// Only allow requests with no referer or referers from within program. On error returns error message and and clears all input variables
// This can be called at the start of your script
// Usage: alert(security_disableExternalReferers())
function security_disableExternalReferers() {
  if (!@$GLOBALS['SETTINGS']['advanced']['checkReferer']) { return; }
  if (!@$_SERVER['HTTP_REFERER']) { return; }

  // allowed link combinations
  if ($_SERVER['REQUEST_METHOD'] == 'GET') {
    if (!array_diff(array_keys($_REQUEST), array('menu','userNum','resetCode'))) { return; } // skip if nothing but password-reset form keys
  }

  $error             = '';
  $programBaseUrl    = _security_getProgramBaseRefererUrl();
  $isInternalReferer = startsWith($programBaseUrl, $_SERVER['HTTP_REFERER']);
  if (!$isInternalReferer) {
    $format  = "Security Warning: A link from an external source has been detected and automatically disabled.\n";
    $format .= "For security links are only accepted from: %1\$s\n";
    $format .= "Your browser indicated that it linked from: %2\$s\n";
    $error   = nl2br(sprintf(t($format), htmlencode($programBaseUrl), htmlencode($_SERVER['HTTP_REFERER'])));
    if (isAjaxRequest()) { $error = strip_tags(html_entity_decode($error)); }
    _security_clearAllUserInput();
  }

  return $error;
}

// Only allow POST requests if an internal referer is sent. On error returns error message and and clears all input variables
// This can be called at the start of your script
// Usage: alert(security_disablePostWithoutInternalReferer())
function security_disablePostWithoutInternalReferer() {
  if (!@$GLOBALS['SETTINGS']['advanced']['checkReferer']) { return; }
  if ($_SERVER['REQUEST_METHOD'] != 'POST' && !$_POST) { return; }
  if (isFlashUploader()) { return; } // skip for flash uploader (flash doesn't always send referer)

  $error             = '';
  $programBaseUrl    = _security_getProgramBaseRefererUrl();
  $isInternalReferer = startsWith($programBaseUrl, $_SERVER['HTTP_REFERER']);
  if (!$isInternalReferer) {
    $format  = "Security Warning: A form submission from an external source has been detected and automatically disabled.\n";
    $format .= "For security form posts are only accepted from: %1\$s\n";
    $format .= "Your browser indicated the form was sent from: %2\$s\n";
    $error   = nl2br(sprintf(t($format), htmlencode($programBaseUrl), htmlencode($_SERVER['HTTP_REFERER'])));
    if (isAjaxRequest()) { $error = strip_tags(html_entity_decode($error)); }

    _security_clearAllUserInput();
  }

  return $error;
}

// Warn if no URL is specified but input is - returns warning
// This can be called at the start of your script
function security_warnOnInputWithNoReferer() {
  if (!@$GLOBALS['SETTINGS']['advanced']['checkReferer']) { return; }
  if (@$_SERVER['HTTP_REFERER'])                          { return; }
  if (isFlashUploader())                                  { return; } // skip for flash uploader (flash doesn't always send referer)

  // allowed link combinations
  if ($_SERVER['REQUEST_METHOD'] == 'GET') {
    if (!array_diff(array_keys($_REQUEST), array('menu','userNum','resetCode'))) { return; } // skip if nothing but password-reset form keys
  }

  //
  $error     = '';
  $userInput = @$_REQUEST || @$_POST || @$_GET || @$_SERVER['QUERY_STRING'] || @$_SERVER['PATH_INFO'];
  if ($userInput) {
    $format   = "Security Warning: A manually entered link with user input was detected.\n";
    $format  .= "If you didn't type this url in yourself, please close this browser window.\n";
    $error   = nl2br(t($format));
    if (isAjaxRequest()) { $error = strip_tags(html_entity_decode($error)); }
  }

  return $error;

}



// display a hidden CSRF token field for use in validating form submissions
// is session key doesn't exist it will be created on first usage
function security_getHiddenCsrfTokenField() {

  // create token if it doesn't already exist
  if (!array_key_exists('_CSRFToken', $_SESSION)) {
    $_SESSION['_CSRFToken'] = sha1(uniqid(null, true));
  }

  //
  $html = '<input type="hidden" name="_CSRFToken" value="' .$_SESSION['_CSRFToken']. '" />';
  return $html;
}

// All referers must match this program base url to be accepted
// eg: http://www.example.com/cms/admin.php
function _security_getProgramBaseRefererUrl() {

  // Get current page URL without query string (originally based off thisPageUrl() code)
  static $programBaseUrl;
  if (!isset($programBaseUrl)) {
    $proto  = isHTTPS() ? "https://" : "http://";
    $domain = @$_SERVER['HTTP_HOST'] ? $_SERVER['HTTP_HOST'] : @$_SERVER['SERVER_NAME'];
    if (preg_match('|:[0-9]+$|', $domain)) { $port = ''; } // if there is a :port on HTTP_HOST use that, otherwise...
    else                                   { $port   = (@$_SERVER['SERVER_PORT'] && @$_SERVER['SERVER_PORT'] != 80 && @$_SERVER['SERVER_PORT'] != 443) ? ":{$_SERVER['SERVER_PORT']}" : ''; }
    $path = str_replace(' ', '%20', $_SERVER['SCRIPT_NAME']); // exclude PATH_INFO
    $programBaseUrl = $proto . $domain . $port . $path;
  }

  return $programBaseUrl;
}


// clear all user input variables (except cookies)
function _security_clearAllUserInput() {
  $_REQUEST = array();
  $_POST = array();
  $_GET = array();
  $_SERVER['QUERY_STRING'] = '';
  $_SERVER['PATH_INFO']    = '';
  $_SERVER['HTTP_REFERER'] = '';
}

//
function getRequestedAction($defaultAction = '') {
  
  //
  doAction('admin_getRequestedAction');

  # parse action out of key format: name="action=sampleList" value="List"
  # (the submit button value is often used for display purposes and can't be used to specify an action value)
  foreach (array_keys($_REQUEST) as $key) {
    if (strpos($key, 'action=') === 0 || strpos($key, '_action=') === 0) {
      list($stringActionEquals, $actionValue) = explode("=", $key, 2);
      $_REQUEST['_action'] = $actionValue;
    }
  }

  # get actions
  $action = '';
  if     (@$_REQUEST['_advancedActionSubmit'] && @$_REQUEST['_advancedAction']) { // advanced commands can be urls or action values
    if (startsWith('?', $_REQUEST['_advancedAction'])) { redirectBrowserToURL($_REQUEST['_advancedAction']); } // added in v2.15, previously support through javascript on edit/view but not list
    else                                               { $action = $_REQUEST['_advancedAction']; }
  }
  elseif (@$_REQUEST['_action'])                 { $action = $_REQUEST['_action']; }          # explicit action (move towards _action)
  elseif (@$_REQUEST['action'])                  { $action = $_REQUEST['action']; }           # explicit action (deprecate this one)
  elseif (@$_REQUEST['_defaultAction'])          { $action = $_REQUEST['_defaultAction']; }   # default action
  else                                           { $action = $defaultAction; }

  #
  return $action;
}

// output pretty JSON, we can improve this later
// Note that this is for human display only and may corrupt data that looks like JSON itself.
function json_encode_pretty($struct) {
  $json = json_encode($struct);
  $json = preg_replace('/","/', "\",\n  \"", $json); //
  $json = preg_replace("|\\\\/|", "/", $json); // Unescape escaped forward-slashes: http://stackoverflow.com/questions/1580647/json-why-are-forward-slashes-escaped
  $json = preg_replace('/},"/', "\n},\n\"", $json); //
  $json = preg_replace('/^{"/', "{\n\"", $json); //
  $json = preg_replace('/{"/', "{\n  \"", $json); //
  $json = preg_replace('/}(?=[}\s]*$)/', "\n}", $json); //
  return $json;
}



/*
  utf8_force() - Force a string or array to be valid UTF-8 and:
    - automatically correct invalid UTF-8 (replacing invalid chars or sequences with ?), this helps when
      ... your otherwise valid UTF-8 data can get easily "corrupted" when you add data from executable programs
      ... output or other sources that isn't UTF-8!  A single high-ascii byte can render your content invalid to
      ... MySQL - CAUSING YOUR INSERT OR UPDATE TO FAIL!  So watch out for that.
    - supports taking arrays OR strings as input
    - optionally removes/replaces 4-byte UTF-8 chars (MySQL only supports 3-byte UTF-8 chars and fails on 4-byte UTF-8 chars)
    - optionally converts from alternate character encodings to UTF-8 (on invalid charset assumes UTF-8)
    *** NOTE: Binary data should not be encoded as UTF-8 or stored UTF-8 charset columns in MySQL as it
        ... can easily get corrupted if you don't encode and decode it in the exact same way.  Use a
        ... binary or varbinary column type instead.

  References:
    - UTF-8 Brief Overview: http://php.net/utf8_encode
    - UTF-8 Detailed Overview: http://en.wikipedia.org/wiki/UTF-8
    - Ascii table with binary to hex: http://www.ascii-code.com/
    - Emjoi table with examples of 3 and 4 byte UTF-8 sequences: http://apps.timwhitlock.info/emoji/tables/unicode
    - UTF-8 Examples page: http://www.columbia.edu/~kermit/utf8.html
    - Mysql docs on not supporting 4 bytes chars without special character set: http://dev.mysql.com/doc/refman/5.5/en/charset-unicode-utf8mb4.html

  Example:
      $string = utf8_force($string);                        // output valid utf-8
      $string = utf8_force($string, true);                  // output valid utf-8 that is mysql safe with 4-byte chars replaced with <?> replacement char
      $string = utf8_force($string, true, 'ISO-8859-1');    // convert from another character set.  Valid values: http://php.net/manual/en/mbstring.supported-encodings.php
      $string = utf8_force($string, true, 'Windows-1252');  // convert from another character set.  Valid values: http://php.net/manual/en/mbstring.supported-encodings.php
*/
function utf8_force($stringOrArray, $replace4ByteUTF8 = false, $convertFromEncoding = '') {

  // error checking
  if (mb_internal_encoding() != 'UTF-8') {
    // we default to assuming an incoming encoding of utf-8, so we want to check that's the case
    // we generally don't want to support scripts that run with some other internal encoding
    die(__FUNCTION__ . ": mb_internal_encoding() must be UTF-8, not '" .htmlencode(mb_internal_encoding()). "'");
  }
  if ($convertFromEncoding && !@mb_convert_encoding(1, $convertFromEncoding)) { // mb_convert_encoding() returns false if encoding isn't recognized
    die(__FUNCTION__ . ": unknown character set specified '" .htmlencode($convertFromEncoding). "'");
  }

  // support recursively converting arrays
  if (is_array($stringOrArray)) {
    foreach ($stringOrArray as $index => $string) {
      $stringOrArray[$index] = utf8_force($string, $replace4ByteUTF8, $convertFromEncoding);
    }
    return $stringOrArray;
  }

  // convert string to UTF-8
  $string     = $stringOrArray;
  $encoding   = $convertFromEncoding ? $convertFromEncoding : 'UTF-8'; // Note: encoding from utf-8 to utf-8 "fixes" invalid utf-8 (replaces invalid sequences with a replacement char ?)
  $utf8String = mb_convert_encoding($string, 'UTF-8', $encoding); // mb_convert_encodin() replaces unknown/invalid sequences with ascii "?"

  // replace 4-byte utf-8 for mysql compatability, see: http://dev.mysql.com/doc/refman/5.5/en/charset-unicode-utf8mb4.html
  // for security replace chars don't remove, see: http://unicode.org/reports/tr36/#Deletion_of_Noncharacters
  if ($replace4ByteUTF8) {
    $replacementChar = "\xEF\xBF\xBD"; /* For security replace don't remove, official replacement char looks like this: <?> see: http://unicode.org/reports/tr36/#Deletion_of_Noncharacters */
    $utf8String = preg_replace('/[\xF0-\xF7].../s', $replacementChar, $utf8String);  // replace 4-byte utf-8 sequences that start with binary: 11110xxx
  }

  //
  return $utf8String;
}

// attempt to disable browser autocomplete functionality.  Set $force to true to always output regardless of setting
/*
  <form method="post" <?php disableAutocomplete(); ?>>         // return: autocomplete='off'
  <input type="password" <?php disableAutocomplete(); ?>>      // return: autocomplete='off'
  <form ... ><?php disableAutocomplete('form-headers'); ?>     // return headers to interfere with browsers the ignore autocomplete="off"
*/
function disableAutocomplete($arg1 = '', $force = false) {
  // skip if disabled by Admin Settings (meaning autocomplete is ENABLED now)
  if (!$force && !$GLOBALS['SETTINGS']['advanced']['disableAutocomplete']) { return ''; }

  //
  $html = '';
  if     ($arg1 == '')     { return "autocomplete='off'"; }
  elseif ($arg1 == 'form-headers') {
    $html .= "\n<!-- Attempt to disable autocomplete for browsers that ignore HTML5 autocomplete standard -->";
    $html .= "<input type='password' style='display:none'/>"; // originally from: http://crbug.com/352347#c11
    $html .= "<input type='password' style='display:none'/>"; // 3 password fields seems to prevent built in password functionality from activating (in Chrome)
    $html .= "<input type='password' style='display:none'/>";
    $html .= "\n";
  }
  else { dieAsCaller(__FUNCTION__. ": Unknown argument '" .htmlencode($arg1). "'!"); }

  echo $html;
}


// returns bootstrap width class, ie: "col-xs-12 col-sm-6 col-md-4 col-lg-3"
// $sizeName: "tiny", "small", "medium", "large", or "full"
function getBootstrapFieldWidthClass($sizeName = null){
  
  $styleClassWidth = '';
  if ($sizeName == 'tiny'){
    $styleClassWidth = 'col-xs-12 col-sm-4 col-md-2 col-lg-1';
  }
  elseif ($sizeName == 'small'){
    $styleClassWidth = 'col-xs-12 col-sm-6 col-md-4 col-lg-3';
  }
  elseif ($sizeName == 'medium'){
    $styleClassWidth = 'col-xs-12 col-sm-8 col-md-6 col-lg-6';
  }
  elseif ($sizeName == 'large'){
    $styleClassWidth = 'col-xs-12 col-sm-10 col-md-8 col-lg-9';
  }
  elseif ($sizeName == 'full'){
    $styleClassWidth = 'col-xs-12 col-sm-12';
  }
  else{
    $styleClassWidth = 'col-xs-12 col-sm-6 col-md-4 col-lg-3'; // default to small
  }
  
  return $styleClassWidth;
}

// Programmatically output an HTML tag and attributes.  Attributes values will be html encoded.
// Attributes with null values will always be skipped, and those with blank values will be
// skipped by default unless $skipEmptyAttr is set to false
// $html = tag('img', ['src' => 'http://example.com/']);
// $html = tag('div', ['id' => 'mydiv'], "Hello World"); // Outputs: <div id="mydiv">Hello World</div>
// $html = tag('div', ['class' => ''], null, false); // Outputs: <div class="">
// FUTURE: Support $tagName suffix-char of / to create empty element tags for XML, eg: tag('element/', ['selected'=>'true']) outputs <element selected='true'/>
function tag($tagName, $attrs = [], $content = null, $skipEmptyAttr = true) {
  $html = '<' . $tagName;
  foreach ($attrs as $key => $value) {
    if (is_null($value)) { continue; } // pass null to skip attr, pass empty string to output attribute with empty string value, eg: attr=''
    if ($skipEmptyAttr && $value == '') { continue; }
    $html .= htmlencodef(' ?="?"', $key, $value);
  }
  $html .= '>';

  // add content and closing tag
  if (!is_null($content)) {
    $html .= $content;
    $html .= "</$tagName>";
  }

  return $html;
}

// ensure a (temp) file gets unlinked after the current script execution, whether or not the script dies
// $tmpFile = tempnam($workingDir, 'temp_');
// unlink_on_shutdown($tmpFile);
function unlink_on_shutdown($filepath) {
  register_shutdown_function(function($filepath) {
    if (file_exists($filepath)) { @unlink($filepath); } 
  }, $filepath);
}

//
function poweredByHTML() {
  # NOTE: Disabling or modifying "Powered By HTML" footer code violates your license agreement and is willful copyright infringement.
  # NOTE: Copyright infringement can be very expensive: http://en.wikipedia.org/wiki/Statutory_damages_for_copyright_infringement
  # NOTE: Please do not steal our software.
  
  return $GLOBALS['SETTINGS']['poweredByHTML'];
}


// eof