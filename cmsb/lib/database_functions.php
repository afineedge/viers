<?php

// check if we've tried to connect to the database yet.
function mysql_isConnected($setValue = null) {
  // NOTE: This doesn't indicate if we successfully connected or are still connected,
  // ... only that we tried and got something back from mysqli.  To check if an existing
  // ... connection is still live try this code:
  // if (mysqli()->ping()) { printf ("Our connection is ok!\n"); }
  // else { printf ("Error: %s\n", mysqli()->error); }

  static $isConnected = false;
  if (!is_null($setValue)) { $isConnected = $setValue; }
  return $isConnected;
}

// set/get mysqli object
function mysqli($setValue = null) {
  // set new value
  static $obj = null;
  if (!is_null($setValue)) { $obj = $setValue; }

  // error checking
  if (!$obj && !mysql_isConnected()) { dieAsCaller("No database connection!"); }

  //
  return $obj;
}

//
function connectIfNeeded() {
  if (!mysql_isConnected()) { connectToMySQL(); }
}

//
function connectToMySQL($returnErrors = false) {
  global $SETTINGS;

  ### Get connection details
  $hostnameAndPort = getFirstDefinedValue(@$SETTINGS["mysql:{$_SERVER['HTTP_HOST']}"]['hostname'], $SETTINGS['mysql']['hostname']);
  $username        = getFirstDefinedValue(@$SETTINGS["mysql:{$_SERVER['HTTP_HOST']}"]['username'], $SETTINGS['mysql']['username']);
  $password        = getFirstDefinedValue(@$SETTINGS["mysql:{$_SERVER['HTTP_HOST']}"]['password'], $SETTINGS['mysql']['password']);
  $database        = getFirstDefinedValue(@$SETTINGS["mysql:{$_SERVER['HTTP_HOST']}"]['database'], $SETTINGS['mysql']['database']);
  $textOnlyErrors  = coalesce(inCLI(), @$SETTINGS["mysql:{$_SERVER['HTTP_HOST']}"]['textOnlyErrors'], $SETTINGS['mysql']['textOnlyErrors']);

  // get port from hostname - mysqli doesn't support host:port passed as one value
  $hostname = $hostnameAndPort;
  $port     = null; // defaults to ini_get("mysqli.default_port") which is usually 3306
  if (contains(':', $hostnameAndPort)) { list($hostname, $port) = explode(':', $hostnameAndPort, 2); }

  ### Connect to database

  try {
    mysqli_report(MYSQLI_REPORT_ALL); // catch connection exceptions instead of outputing PHP Warnings
    $mysqli = mysqli_init();
    $mysqli->options(MYSQLI_OPT_CONNECT_TIMEOUT, 3); // wait up to x seconds to connect to mysql
    $isConnected = $mysqli->real_connect($hostname, $username, $password, '', $port);
    mysqli_report(MYSQLI_REPORT_OFF);
  } catch (Exception $e ) {
    $isConnected = false; // doesn't get defined above if exception is thrown
    //showme($e->getMessage());
  }

  if (!$isConnected || $mysqli->connect_errno) {
    $connectionError = $mysqli->connect_errno ." - ". $mysqli->connect_error;
    if     ($returnErrors)   { return "Error connecting to MySQL:<br/>\n$connectionError"; }
    elseif ($textOnlyErrors) { die("Error connecting to MySQL: $connectionError"); }
    else                     {
      $libDir = pathinfo(__FILE__, PATHINFO_DIRNAME); // viewers may be in different dirs
      include("$libDir/menus/dbConnectionError.php");
    };
    exit();
  }
  mysqli($mysqli); // save object on successful connection

  // select db
  $isDbSelected = mysqli()->select_db($database);
  if (!$isDbSelected) {
    mysqli()->query("CREATE DATABASE `$database`") or die("MySQL Error: ". mysqli()->error. "\n");
    mysqli()->select_db($database) or die("MySQL Error: ". mysqli()->error. "\n");
  }


  ### check for required mysql version
  $currentVersion  = preg_replace("/[^0-9\.]/", '', mysqli()->server_info);

  if (version_compare(REQUIRED_MYSQL_VERSION, $currentVersion, '>')) {
    $error  = "This program requires MySQL v" .REQUIRED_MYSQL_VERSION. " or newer. This server has v$currentVersion installed.<br/>\n";
    $error .= "Please ask your server administrator to install MySQL v" .REQUIRED_MYSQL_VERSION. " or newer.<br/>\n";
    if ($returnErrors) { return $error; }
    die($error);
  }

  ### Set Character Set
  # note: set through PHP 'set_charset' function so mysql_real_escape string() knows what charset to use. setting the charset
  # ... through mysql queries with 'set names' didn't cause mysql_client_encoding() to return a different value
  mysqli()->set_charset("utf8") or die("Error loading character set utf8: " .mysqli()->error. '');

  # set MySQL strict mode - http://dev.mysql.com/doc/refman/5.0/en/server-sql-mode.html
  mysqlStrictMode(true);

  # set MySQL timezone offset
  setMySqlTimezone();

  // check accounts table exists
  if (isInstalled()) {
    $r = mysql_get_query("SHOW TABLES LIKE '{$GLOBALS['TABLE_PREFIX']}accounts'", true);
    if (!$r) { die("Error: No accounts table found.  To re-run install process remove file data/isInstalled.php."); }
  }

  ### Legacy support - connect with mysql library (for any old custom code that directly calls mysql functions)
  if (extension_loaded('mysql') && !empty($SETTINGS['advanced']['legacy_mysql_support'])) {

    ### Connect to database
    $DBH = @mysql_connect($hostnameAndPort, $username, $password);
    if (!$DBH) {
      $connectionError = mysql_error();
      if     ($returnErrors)   { return "Error connecting to legacy MySQL:<br/>\n$connectionError"; }
      elseif ($textOnlyErrors) { die("Error connecting to legacy MySQL: $connectionError"); }
      else                     {
        $libDir = pathinfo(__FILE__, PATHINFO_DIRNAME); // viewers may be in different dirs
        include("$libDir/menus/dbConnectionError.php");
      };
      exit();
    }
  
    // select db
    $isDbSelected = @mysql_select_db($database);
    if (!$isDbSelected) {
      mysql_query("CREATE DATABASE `$database`") or die("MySQL Error: ". mysql_error(). "\n");
      mysql_select_db($database) or die("MySQL Error: ". mysql_error(). "\n");
    }
  }
  ### /Legacy support
  
  // set connected flag
  mysql_isConnected(true);

  //
  return '';
}



// list($indexName, $indexColList) = getIndexNameAndColumnListForField($fieldName, $columnType);
// generate an indexName and "index column clause" for use by CREATE INDEX or DROP INDEX
function getIndexNameAndColumnListForField($fieldName, $columnType) {
  
  // determine if the column type is a string type (we must supply a key length for BLOB/TEXT, we must not for non-string types)
  $stringTypes = array(
    'CHAR', 'VARCHAR', 'BINARY', 'VARBINARY', 'TINYBLOB', 'BLOB', 'MEDIUMBLOB',
    'LONGBLOB', 'TINYTEXT', 'TEXT', 'MEDIUMTEXT', 'LONGTEXT', 'ENUM', 'SET'
  ); 
  preg_match('/(\w+)/', $columnType, $matches);
  $firstWordInColumnType = @$matches[1];
  $isStringType          = in_array(strtoupper($firstWordInColumnType), $stringTypes);
  
  
  // get index prefix length for strings
  $keyLength = ''; 
  if ($isStringType) { // To speed up ORDER BY on text fields the index prefix length must be as long as the field
    if     (preg_match('/^[\w\s*]+\((\d+)\)/', $columnType, $matches) ) { $keyLength = min(@$matches[1], 255); }
    else                                                                { $keyLength = 16; }
    if ($keyLength) { $keyLength = "($keyLength)"; }
  }
  
  // construct return values: $indexName and $indexColList
  $indexName    = "_auto_".mysql_escape($fieldName);
  $indexColList = "(".mysql_escape($fieldName)."$keyLength)";
  return array($indexName, $indexColList);
}


//
function _sortMenusByOrder($fieldA, $fieldB) {

  // sort field meta data below sorted by "order" value
  $orderA = array_key_exists('menuOrder', $fieldA) ? $fieldA['menuOrder'] : 1000000000;
  $orderB = array_key_exists('menuOrder', $fieldB) ? $fieldB['menuOrder'] : 1000000000;
  if ($orderA < $orderB) { return -1; }
  if ($orderA > $orderB) { return 1; }
  return 0;
}


//
function getTableNameWithoutPrefix($tableName) {  // add $TABLE_PREFIX to table if it isn't there already
  $regexp = "/^" .preg_quote($GLOBALS['TABLE_PREFIX']). '/';
  return preg_replace($regexp, '', $tableName); // remove prefix
}


//
function getTableNameWithPrefix($tableName) { // add $TABLE_PREFIX to table if it isn't there already
  return $GLOBALS['TABLE_PREFIX'] . getTableNameWithoutPrefix($tableName);
}


//
function getColumnTypeFor($fieldName, $fieldType, $customColumnType = '') {
  $columnType = '';

  // special case: default column type specified
  if      ($customColumnType)        { $columnType = $customColumnType; }

  // Special Fieldnames
  elseif  ($fieldName == 'num')              { $columnType = 'int(10) unsigned NOT NULL auto_increment'; }
  elseif  ($fieldName == 'createdDate')      { $columnType = 'datetime NOT NULL DEFAULT "0000-00-00 00:00:00"'; }
  elseif  ($fieldName == 'createdByUserNum') { $columnType = 'int(10) unsigned NOT NULL'; }
  elseif  ($fieldName == 'updatedDate')      { $columnType = 'datetime NOT NULL DEFAULT "0000-00-00 00:00:00"'; }
  elseif  ($fieldName == 'updatedByUserNum') { $columnType = 'int(10) unsigned NOT NULL'; }
  elseif  ($fieldName == 'dragSortOrder')    { $columnType = 'int(10) unsigned NOT NULL'; }
  // NOTE:  Other special field types don't need to be specified here because they have required
  //        ... field types in /lib/menus/default/editField_functions.php that map to the column
  //        ... types below.  We only need to specify the column types above because they are
  //        ... not available with any predefined field type.

  // otherwise return columnType for fieldType
  elseif ($fieldType == '')               { $columnType = ''; }
  elseif ($fieldType == 'none')           { $columnType = ''; }
  elseif ($fieldType == 'textfield')      { $columnType = 'mediumtext'; }
  elseif ($fieldType == 'textbox')        { $columnType = 'mediumtext'; }
  elseif ($fieldType == 'wysiwyg')        { $columnType = 'mediumtext'; }
  elseif ($fieldType == 'date')           { $columnType = 'datetime NOT NULL DEFAULT "0000-00-00 00:00:00"'; } // v3.08 - Default value is required for MySQL 5.7.x or we get an error.  See "...report an error when adding a DATE or DATETIME column..." here (but occurs even before this version): https://bugs.launchpad.net/ubuntu/+source/mysql-5.7/+bug/1657989
  elseif ($fieldType == 'list')           { $columnType = 'mediumtext'; }
  elseif ($fieldType == 'checkbox')       { $columnType = 'tinyint(1) unsigned NOT NULL'; }
  elseif ($fieldType == 'upload')         { $columnType = ''; }
  elseif ($fieldType == 'separator')      { $columnType = ''; }
  elseif ($fieldType == 'relatedRecords') { $columnType = ''; }

  // special fields types
  elseif ($fieldType == 'accessList')   { $columnType = ''; }
  elseif ($fieldType == 'dateCalendar') { $columnType = ''; }

  else {
    die(__FUNCTION__ . ": Field '" .htmlencode($fieldName). "' has unknown fieldType '" .htmlencode($fieldType). "'.");
  }

  return $columnType;
}


//
function getMysqlTablesWithPrefix() {
  global $TABLE_PREFIX;

  $tableNames = array();
  $escapedTablePrefix = mysql_escape($TABLE_PREFIX);
  $escapedTablePrefix = preg_replace("/([_%])/", '\\\$1', $escapedTablePrefix);  // escape mysql wildcard chars
  $result    = mysqli()->query("SHOW TABLES LIKE '$escapedTablePrefix%'") or die("MySQL Error: ". htmlencode(mysqli()->error) . "\n");
  while ($row = $result->fetch_row()) {
    array_push($tableNames, $row[0]);
  }
  if (is_resource($result)) { mysqli_free_result($result); }

  return $tableNames;
}

// get mysql column names/types
function getMySqlColsAndType($escapedTableName) {

  $columns = array();
  $result  = mysqli()->query("SHOW COLUMNS FROM `$escapedTableName`") or die("MySQL Error: ". htmlencode(mysqli()->error) . "\n");
  while ($row = $result->fetch_assoc()) {
    $columns[ $row['Field'] ] = $row['Type'];
  }
  if (is_resource($result)) { mysqli_free_result($result); }

  return $columns;
}

//
function getMysqlColumnType($tableName, $fieldname) {
  if ($fieldname == '') { return ''; }

  $escapedTableName = mysql_escape($tableName);
  $escapedFieldName = mysql_escape($fieldname);
  $escapedFieldName = preg_replace("/([_%])/", '\\\$1', $escapedFieldName); // escape mysql wildcard chars
  $result           = mysqli()->query("SHOW COLUMNS FROM `$escapedTableName` LIKE '$escapedFieldName'") or die("MySQL Error: ". htmlencode(mysqli()->error) ."\n");
  $row              = $result->fetch_assoc();
  if (is_resource($result)) { mysqli_free_result($result); }

  $columnType       = $row['Type'];
  if ($row['Type'] && $row['Null'] != 'YES') { $columnType .= " NOT NULL"; }
  if ($row['Extra'])                         { $columnType .= " {$row['Extra']}"; }

  return $columnType;
}


//
function getTablenameErrors($tablename) {

  // get used tablenames
  static $usedTableNamesLc = array();
  static $loadedTables;
  if (!$loadedTables++) {
    foreach (getMysqlTablesWithPrefix() as $usedTablename) {
      $withoutPrefixLc = strtolower(getTablenameWithoutPrefix($usedTablename));
      array_push($usedTableNamesLc, $withoutPrefixLc);
    }
    foreach (getSchemaTables() as $usedTableName) {
      $withoutPrefixLc = strtolower($usedTablename);
      array_push($usedTableNamesLc, $withoutPrefixLc);
    }
  }


  // get reserved tablenames
  $reservedTableNamesLc = array();
  array_push($reservedTableNamesLc, 'home', 'admin', 'database', 'accounts', 'license'); // the are hard coded menu names
  array_push($reservedTableNamesLc, 'default');  // can't be used because menu folder exists with default menu files
  array_push($reservedTableNamesLc, 'all');      // can't be used because the "all" keyword gives access to all menus in user accounts

  // get error
  $error       = null;
  $tablenameLc = strtolower(getTableNameWithoutPrefix($tablename));
  if      ($tablenameLc == '')                          { $error = "No table name specified!\n"; }
  else if (!preg_match("/^[a-z]/", $tablenameLc))       { $error = "Table name must start with a letter!\n"; }
  else if (preg_match("/[A-Z]/", $tablename))           { $error = "Table name must be lowercase!\n"; }
  else if (preg_match("/[^a-z0-9\-\_]/", $tablename))   { $error = "Table name can only contain these characters (\"a-z, 0-9, - and _\")!\n"; }
  if (in_array($tablenameLc, $usedTableNamesLc))        { $error = "That table name is already in use, please choose another.\n"; }
  if (in_array($tablenameLc, $reservedTableNamesLc))    { $error = "That table name is not allowed, please choose another.\n"; }
  //
  return $error;
}


// foreach (getListOptions('tableName', 'fieldName') as $value => $label):
function getListOptions($tablename, $fieldname, $useCache = false) {

  $valuesToLabels = array();

  $schema       = loadSchema($tablename);
  $fieldSchema  = $schema[$fieldname];
  $fieldOptions = getListOptionsFromSchema($fieldSchema, null, $useCache);
  foreach ($fieldOptions as $valueAndLabel) {
    list($value, $label) = $valueAndLabel;
    $valuesToLabels[$value] = $label;
  }

  return $valuesToLabels;
}

// return MySQL WHERE clause for google style query: +word -word "multi word phrase"
// ... function always returns a value (or 1) so output can be AND'ed to existing WHERE
// $where = getWhereForKeywords($keywordString, $fieldnames);
// list($where, $andTerms, $notTerms) = getWhereForKeywords($keywordString, $fieldnames, true);
function getWhereForKeywords($keywordString, $fieldnames, $wantArray = false) {
  if (!is_array($fieldnames)) { die(__FUNCTION__ . ": fieldnames must be an array!"); }

  // parse out "quoted strings"
  $searchTerms = array();
  $quotedStringRegexp = "/([+-]?)(['\"])(.*?)\\2/";
  preg_match_all($quotedStringRegexp, $keywordString, $matches, PREG_SET_ORDER);
  foreach ($matches as $match) {
    list(,$plusOrMinus,,$phrase) = $match;
    $phrase = trim($phrase);
    $searchTerms[$phrase] = $plusOrMinus;
  }
  $keywordString = preg_replace($quotedStringRegexp, '', $keywordString); // remove quoted strings

  // parse out keywords
  $keywords = preg_split('/[\\s,;]+/', $keywordString);
  foreach ($keywords as $keyword) {
    $plusOrMinus = '';
    if (preg_match("/^([+-])/", $keyword, $matches)) {
      $keyword = preg_replace("/^([+-])/", '', $keyword, 1);
      $plusOrMinus = $matches[1];
    }

    $searchTerms[$keyword] = $plusOrMinus;
  }

  // create query
  $where = '';
  $conditions = array();
  $andTerms   = array();
  $notTerms   = array();
  foreach ($searchTerms as $term => $plusOrMinus) {
    if ($term == '') { continue; }

    $likeOrNotLike  = ($plusOrMinus == '-') ? "NOT LIKE" : "LIKE";
    $andOrOr        = ($plusOrMinus == '-') ? " AND " : " OR ";
    $termConditions = array();

    if ($plusOrMinus == '-') { $notTerms[] = $term; }
    else                     { $andTerms[] = $term; }

    foreach ($fieldnames as $fieldname) {
      $fieldname = trim($fieldname);
      $escapedKeyword = mysql_escape($term, true);
      $termConditions[] = "`" .mysql_escape($fieldname). "` $likeOrNotLike '%$escapedKeyword%'";
    }

    if ($termConditions) {
      $conditions[] = "(" . join($andOrOr, $termConditions) . ")\n";
    }

  }

  //
  $where = join(" AND ", $conditions);
  if (!$where) { $where = 1; }

  //
  if ($wantArray) { return array($where, $andTerms, $notTerms); }
  else            { return $where; }
}



// leave tablename blank for all tables
function backupDatabase($filenameOrPath = '', $selectedTable = '') {
  global $TABLE_PREFIX;
  $prefixPlaceholder = '#TABLE_PREFIX#_';

  set_time_limit(60*5);  // v2.51 - allow up to 5 minutes to backup/restore database
  if (!inCLI()) { session_write_close(); } // v2.51 - End the current session and store session data so locked session data doesn't prevent concurrent access to CMS by user while backup in progress

  // error checking
  if ($selectedTable != '') {
    $schemaTables = getSchemaTables();
    if (preg_match("/[^\w\d\-\.]/", $selectedTable)) { die(__FUNCTION__ ." : \$selectedTable contains invalid chars! " . htmlencode($selectedTable)); }
    if (!in_array($selectedTable, $schemaTables)) { die("Unknown table selected '" .htmlencode($selectedTable). "'!"); }
  }

  // open backup file
  $hostname         = preg_replace('/[^\w\d\-\.]/', '', @$_SERVER['HTTP_HOST']);
  if (!$filenameOrPath) {
    $filenameOrPath  = "$hostname-v{$GLOBALS['APP']['version']}-".date('Ymd-His');
    if ($selectedTable) { $filenameOrPath .= "-$selectedTable"; }
    $filenameOrPath .= ".sql.php";
  }
  $outputFilepath = isAbsPath($filenameOrPath) ? $filenameOrPath : $GLOBALS['BACKUP_DIR'] . $filenameOrPath; // v2.60 if only filename provided, use /data/backup/ as the basedir 
  $fp         = @fopen($outputFilepath, 'x');
  if (!$fp) {  // file already exists - avoid race condition
    if (!inCLI()) { session_start(); }
    return false;
  }  

  // create no execute php header
  fwrite($fp, "-- <?php die('This is not a program file.'); exit; ?>\n\n");  # prevent file from being executed

  // get tablenames to backup
  if ($selectedTable) {
    $tablenames = array( getTableNameWithPrefix($selectedTable) );
  }
  else {
    $skippedTables = array('_cron_log','_error_log','_outgoing_mail','_nlb_log'); // don't backup these table names
    $skippedTables = applyFilters('backupDatabase_skippedTables', $skippedTables);  // let users skip tables via plugins
    $skippedTables = array_map('getTableNameWithPrefix', $skippedTables);           // add table_prefix to all table names (if needed)
    $allTables     = getMysqlTablesWithPrefix();
    $tablenames    = array_diff($allTables, $skippedTables);                        // remove skipped tables from list
  }

  // backup database
  foreach ($tablenames as $unescapedTablename) {
    $escapedTablename        = mysql_escape($unescapedTablename);
    $tablenameWithFakePrefix = $prefixPlaceholder . getTableNameWithoutPrefix($escapedTablename);

    // create table
    fwrite($fp, "\n--\n");
    fwrite($fp, "-- Table structure for table `$tablenameWithFakePrefix`\n");
    fwrite($fp, "--\n\n");

    fwrite($fp, "DROP TABLE IF EXISTS `$tablenameWithFakePrefix`;\n\n");

    $result = mysqli()->query("SHOW CREATE TABLE `$escapedTablename`");
    list(,$createStatement) = $result->fetch_row() or die("MySQL Error: ".htmlencode(mysqli()->error));
    $createStatement = str_replace("TABLE `$TABLE_PREFIX", "TABLE `$prefixPlaceholder", $createStatement);
    fwrite($fp, "$createStatement;\n\n");
    if (is_resource($result)) { mysqli_free_result($result); }

    // create rows
    fwrite($fp, "\n--\n");
    fwrite($fp, "-- Dumping data for table `$tablenameWithFakePrefix`\n");
    fwrite($fp, "--\n\n");

    $result = mysqli()->query("SELECT * FROM `$escapedTablename`") or die("MySQL Error: ".htmlencode(mysqli()->error));
    while ($row = $result->fetch_row()) {
      $values = '';
      foreach ($row as $value) {
        if (is_null($value)) { $values .= 'NULL,'; }
        else                 { $values .= '"' .mysql_escape($value). '",'; }
      }
      $values = chop($values, ','); // remove trailing comma

      fwrite($fp, "INSERT INTO `$tablenameWithFakePrefix` VALUES($values);\n");
    }
    if (is_resource($result)) { mysqli_free_result($result); }
  }

  //
  fwrite($fp, "\n");
  $result = fwrite($fp, "-- Dump completed on " .date('Y-m-d H:i:s O'). "\n\n");
  if ($result === false) { die(__FUNCTION__ . ": Error writing backup file! $php_errormsg"); }
  fclose($fp) || die(__FUNCTION__ . ": Error closing backup file! $php_errormsg");

  //
  if (!inCLI()) { @session_start(); } // hide error: E_WARNING: session_start(): Cannot send session cache limiter - headers already sent
  return $outputFilepath;
}

// $backupFiles = getBackupFiles_asArray();
function getBackupFiles_asArray() {
  $backupDir   = $GLOBALS['BACKUP_DIR'];
  $allFiles    = scandir($backupDir);
  $backupFiles = array();
  foreach ($allFiles as $filename) {
    if (!preg_match("/\.sql(\.php)?$/", $filename)) { continue; }
    $backupFiles[] = $filename;
  }

  return $backupFiles;
}

//
function getBackupFiles_asOptions($defaultValue = '') {

  //
  $backupFiles = getBackupFiles_asArray();
  
  // sort recently modified files first
  array_multisort(
    // @ for: Strict Standards: Only variables should be passed by reference
    @array_map(create_function('$x', 'return filemtime($GLOBALS[\'BACKUP_DIR\'].$x);'), $backupFiles), SORT_DESC,
    $backupFiles
  );

  //
  if (!$backupFiles) { $labelsToValues = array(t('There are no backups available') => ''); }
  else               {
                        $labelsToValues = array(t('Select version to restore')      => '');
                        $labelsToValues = $labelsToValues + array_combine($backupFiles, $backupFiles);
                     }

  //
  $values      = array_values($labelsToValues);
  $labels      = array_keys($labelsToValues);
  $htmlOptions = getSelectOptions($defaultValue, $values, $labels, false);

  //
  return $htmlOptions;
}

//
function restoreDatabase($filepath, $tablename = '') {
  global $TABLE_PREFIX;
  $prefixPlaceholder = '#TABLE_PREFIX#_';

  set_time_limit(60*5);  // allow up to 5 minutes to backup/restore database
  if (!inCLI()) { session_write_close(); } // v2.51 - End the current session and store session data so locked session data doesn't prevent concurrent access to CMS by user while backup in progress

  // error checking
  if (!$filepath)                      { die("No backup file specified!"); }
  if (preg_match("/\.\./", $filepath)) { die("Backup filename contains invalid characters."); }

  ### restore backup

  // get file contents
  if (!file_exists($filepath)) { die("Backup file '$filepath' doesn't exist!"); }
  $data = file_get_contents($filepath);
  $data = preg_replace('/\r\n/s', "\n", $data);

  // remove comments
  $data = preg_replace('|/\*.*?\*/|', '', $data); // remove /* comment */ style comments
  $data = preg_replace('|^--.*?$|m', '', $data);  // remove -- single line comments

  // insert table prefix
  $data = preg_replace("/^([^`]+`)$prefixPlaceholder/m", "\\1$TABLE_PREFIX", $data);

  // insert table name (used for restoring defaultSqlData files)
  if ($tablename) {
    $data = preg_replace("/^([^`]+`[^`]+)#TABLE_NAME#(`)/m", "\\1$tablename\\2", $data);
  }

  // execute statements
  $queries = preg_split("/;\n\s*/", $data);       // nextlines are always encoded in SQL content so we don't need to worry about accidentally matching them
  foreach ($queries as $query) {
    if (!$query) { continue; } // skip blank queries
    mysqli()->query($query) or die("MySQL Error: ". htmlencode(mysqli()->error) . "\n");
  }

  // restore session
  if (!inCLI()) { session_start(); }
}

// In some database fields we store multiple values in a packed string format.  This
// function returns an array of list values stored in this format: "\tVALUE1\tVALUE2\tVALUE3\t"
// FUTURE: Rename function and write function to pack array into this format
function getListValues($tableName, $fieldName, $fieldValue) {
  $array = explode("\t", $fieldValue);
  if (count($array) == 1) { return $array; } // not a multi-select field

  $array = array_slice($array, 1, -1); // remove blanks from leading/trailing tabs
  return $array;
}

//eof