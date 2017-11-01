<?php


// Escape special characters in the mysql queries to prevent SQL injection attacks.
// Doesn't require a database connection like mysql_real_escape string.  The
// following characters are escaped: \x00, \r, \n, \, `, ', ", and \x1a
// ***NOTE*** MySQL values must be wrapped in quotes to be secure: fieldname = "$escapedValue"
function mysql_escape($string, $escapeLikeWildcards = false) {
  // Note that even though this function isn't character-set aware it's a safe alternative
  // to mysql_real_escape string() so long as ascii or utf8 charsets are used (which we use).
  // NOTE: Extended comments are available below function in C-V-S copy.

  $escaped = strtr($string, array(
    "\x00" => '\0',
    "\n"   => '\n',
    "\r"   => '\r',
    '\\'   => '\\\\',
    '`'    => "\`",
    "'"    => "\'",
    '"'    => '\"',
    "\x1a" => '\Z'
  ));

  //
  if ($escapeLikeWildcards) { // added in 2.60
    $escaped = addcslashes($escaped, '%_');
  }
  
  //
  return $escaped;
}

//
function mysql_escapeLikeWildcards($string) { 
  return addcslashes($string, '%_');
}



// Automatically espaces and quotes input values and inserts them into query, kind of like mysqli_prepare()
// Example: mysql_escapef("num = ? AND name = ?", $num, $name),
function mysql_escapef() {
  $args         = func_get_args();
  $queryFormat  = array_shift($args);
  $replacements = $args;

  // make replacements
  $escapedQuery = '';
  $queryParts   = explode('?', $queryFormat);
  $lastPart     = array_pop($queryParts); // don't add escaped value on end of query
  foreach ($queryParts as $part) {
    $escapedQuery .= $part;
    $escapedQuery .= "'" . mysql_escape( array_shift($replacements) ) . "'";
  }
  $escapedQuery .= $lastPart;

  //
  return $escapedQuery;
}

// disable mysql strict mode - prevent errors when user inserts records from front-end forms without setting a value for every field
function mysqlStrictMode($strictMode) {
  $mysqlVersion  = preg_replace("/[^0-9\.]/", '', mysqli()->server_info);

  // For future use (if needed)...
  // MySQL > 5.7.7 - Signifigant changes here: https://dev.mysql.com/doc/refman/5.7/en/sql-mode.html#sql-mode-changes
  //if (version_compare($mysqlVersion, '5.7.7', '>')) {
  //}
  //
  // MySQL 5.7.4 to 5.7.7 - Signifigant changes here: https://dev.mysql.com/doc/refman/5.7/en/sql-mode.html#sql-mode-changes
  //else if (version_compare($mysqlVersion, '5.7.4', '>=') && version_compare($mysqlVersion, '5.7.7', '<=')) {
  //}
  
  // MySQL < 5.7.4 (legacy behaviour) - Reference: http://web.archive.org/web/20160502022553/http://dev.mysql.com/doc/refman/5.0/en/sql-mode.html
  //else {
    if ($strictMode) { $sql_mode = "STRICT_ALL_TABLES,NO_ZERO_IN_DATE,ERROR_FOR_DIVISION_BY_ZERO,NO_AUTO_CREATE_USER"; }
    else             { $sql_mode                   = "NO_ZERO_IN_DATE,ERROR_FOR_DIVISION_BY_ZERO,NO_AUTO_CREATE_USER"; }
    mysqli()->query("SET SESSION sql_mode = '$sql_mode'") or dieAsCaller("MySQL Error: " .mysqli()->error. "\n");
  //}
  
}

//
function setMySqlTimezone($returnError = false) {
  global $SETTINGS;
  $tzOffsetSeconds = date("Z");

  // ignore offsets greater than 12 hours (illegal offset)
  if (abs($tzOffsetSeconds) > 12*60*60) { return; }

  // set mysql timezone
  $offsetString = convertSecondsToTimezoneOffset($tzOffsetSeconds);
  $query        = "SET time_zone = '$offsetString';";
  if (!mysqli()->query($query)) {
    $error = "MySQL Error: " .mysqli()->error. "\n";
    if ($returnError) { return $error; }
    else              { die($error); }
  }
}


// Generate LIMIT clause for paging from pageNum and perPage
// Usage: $limitClause = mysql_limit($perPage, $pageNum);
function mysql_limit($perPage, $pageNum) {
  $limit   = '';
  $perPage = (int) $perPage;
  $pageNum = (int) $pageNum;

  //
  if ($pageNum == 0) { $pageNum = 1; }

  //
  if ($perPage) {
    $offset = ($pageNum-1) * $perPage;
    $limit  = "LIMIT $perPage OFFSET $offset";
  }

  //
  return $limit;
}


//
function mysql_count($tableName, $whereEtc = 'TRUE') {
  if (!$tableName) { die(__FUNCTION__ . ": No tableName specified!"); }
  $tableNameWithPrefix = getTableNameWithPrefix($tableName);
  $escapedTableName    = mysql_escape( $tableNameWithPrefix );

  //
  if (!$whereEtc) { $whereEtc = 'TRUE'; } // old function took where as optional argument so '' would return all
  if (is_array($whereEtc)) { $whereEtc = mysql_where($whereEtc); }
  $query =  "SELECT COUNT(*) FROM `$escapedTableName` WHERE $whereEtc";

  $result = mysqli()->query($query) or dieAsCaller(__FUNCTION__ . "() MySQL Error: ". htmlencode(mysqli()->error) . "\n");
  list($recordCount) = $result->fetch_row();
  if (is_resource($result)) { mysqli_free_result($result); }

  //
  return intval($recordCount); // v2.52 intval added
}


// Wait up to $timeout seconds to get a lock and then return 0 or 1
// Docs: http://dev.mysql.com/doc/refman/4.1/en/miscellaneous-functions.html#function_get-lock
// Usage: mysql_get_lock(__FUNCTION__, 3) or dieAsCaller("Timed out waiting for mysql lock");
function mysql_get_lock($lockName, $timeout = 0) {
  $lockName = implode('.', [$GLOBALS['SETTINGS']['mysql']['database'], $GLOBALS['SETTINGS']['mysql']['tablePrefix'], $lockName]); // v3.08 - make locks specific to this CMS install instead of server wide, eg: database.tableprefix.lockname
  $query    = mysql_escapef("SELECT GET_LOCK(?, ?)", $lockName, $timeout);
  $result   = mysqli()->query($query) or die("MySQL Error: ". htmlencode(mysqli()->error) . "\n");
  $isLocked = $result->fetch_row()[0];
  if (is_resource($result)) { mysqli_free_result($result); }

  return $isLocked;
}

// Release a previously held lock
// Docs: http://dev.mysql.com/doc/refman/4.1/en/miscellaneous-functions.html#function_release-lock\
// Usage: mysql_release_lock(__FUNCTION__);
function mysql_release_lock($lockName) {
  $lockName   = implode('.', [$GLOBALS['SETTINGS']['mysql']['database'], $GLOBALS['SETTINGS']['mysql']['tablePrefix'], $lockName]); // v3.08 - make locks specific to this CMS install instead of server wide, eg: database.tableprefix.lockname
  $query      = mysql_escapef("SELECT RELEASE_LOCK(?)", $lockName);
  $result     = mysqli()->query($query) or die("MySQL Error: ". htmlencode(mysqli()->error) . "\n");
  $isReleased = $result->fetch_row()[0];
  if (is_resource($result)) { mysqli_free_result($result); }

  return $isReleased;
}

// Format time in MySQL datetime format (default to current server time)
function mysql_datetime($timestamp = null) {
  if ($timestamp === null) { $timestamp = time(); }
  return date('Y-m-d H:i:s', $timestamp); // MySQL format: YYYY-MM-DD HH:MM:SS
}


// return comma separated list of escaped values for construction WHERE ... IN('val1','val2','val3') queries
// if array is empty $defaultValue or 0 is returned so the query will always be value (and not ... IN() which is invalid) MySQL
// Usage: $where = "myfield IN (" .mysql_escapeCSV($values). ")";
function mysql_escapeCSV($valuesArray, $defaultValue = '0') {

  // get CSV values
  $csv = '';
  foreach ($valuesArray as $value) { $csv .= "'" .mysql_escape($value) ."',"; }
  $csv = chop($csv, ',');

  // set default
  if ($csv == '') { $csv = "'" .mysql_escape($defaultValue) ."'"; } // v2.50 quote default value to valid unexpected results comparing a number (0) with a string

  //
  return $csv;
}

// return first matching record or FALSE
// $record = mysql_get($tableName, 123);                   // get first record where: num = 123
// $record = mysql_get($tableName, null, "name = 'test'"); // get first record where: name = 'test'
// $record = mysql_get($tableName, 123,  "name = 'test'"); // get first record where: num = 123 AND name = 'test'
function mysql_get($tableName, $recordNum, $customWhere = null) {
  if ($recordNum && preg_match("/[^0-9]/", strval($recordNum))) { die(__FUNCTION__ . ": second argument must be numeric or null, not '" .htmlencode(strval($recordNum)). "'!"); }

  $fullTableName = getTableNameWithPrefix($tableName);
  $where         = _mysql_getWhereFromNumAndCustomWhere($recordNum, $customWhere);
  $query         = "SELECT * FROM `$fullTableName` WHERE $where LIMIT 1";
  $record        = mysql_get_query($query);

  // add _tableName key to record
  if ($record) { $record['_tableName'] = $tableName; }

  return $record;
}

// return result, for queries that return boolean values
function &mysql_do($query) {
  $result = mysqli()->query($query);
  return $result;
}


// shortcut functions for mysql_fetch
function &mysql_get_query($query, $indexedArray = false) {
  $result   = mysqli()->query($query) or dieAsCaller("MySQL Error: ". htmlencode(mysqli()->error) . "\n");
  $firstRow = $indexedArray ? $result->fetch_row() : $result->fetch_assoc();
  if (is_resource($result)) { mysqli_free_result($result); }
  return $firstRow;
}

// return array of matching records.  Where can contain LIMIT and other SQL
// $records = mysql_select($tableName, "createdByUserNum = '1'");
// $records = mysql_select($tableName, "createdByUserNum = '1' LIMIT 10");
// $records = mysql_select($tableName); // get all records
function mysql_select($tableName, $whereEtc = 'TRUE') {
  if (is_array($whereEtc)) { $whereEtc = mysql_where($whereEtc); }
  $fullTableName  = getTableNameWithPrefix($tableName);
  $query          = "SELECT * FROM `$fullTableName` WHERE $whereEtc";
  $records        = mysql_select_query($query);

  // add _tableName key to records
  foreach ($records as $key => $record) { $records[$key]['_tableName'] = $tableName; }

  return $records;
}

// shortcut functions for mysql_fetch
function &mysql_select_query($query, $indexedArray = false) {
  // $isTextOutput = preg_match("|\nContent-type: text/plain|i", implode("\n", headers_list())); // future: for not htmlencoding errors if text output only
  $result = mysqli()->query($query) or dieAsCaller("MySQL Error: ". htmlencode(mysqli()->error) . "\n");
  $rows   = array();
  if   (!$indexedArray) { while ($row = $result->fetch_assoc()) { $rows[] = $row; } }
  else                  { while ($row = $result->fetch_row())   { $rows[] = $row; } }
  if (is_resource($result)) { mysqli_free_result($result); }
  return $rows;
}

// erase matching records
// mysql_delete($tableName, 123);                            // erase records where: num = 123
// mysql_delete($tableName, null, "createdByUserNum = '1'"); // erase records where: createdByUserNum = '1'
// mysql_delete($tableName, 123,  "createdByUserNum = '1'"); // erase records where: num = 123 AND createdByUserNum = '1'
// Note: For safety either recordnum or a where needs to be specified to delete ALL records.  No recordNum and no where does nothing
function mysql_delete($tableName, $recordNum, $customWhere = null) {
  if ($recordNum && preg_match("/[^0-9]/", strval($recordNum))) { die(__FUNCTION__ . ": second argument must be numeric or null, not '" .htmlencode(strval($recordNum)). "'!"); }
  $tableName  = getTableNameWithPrefix($tableName);
  $where      = _mysql_getWhereFromNumAndCustomWhere($recordNum, $customWhere);
  $delete     = "DELETE FROM `$tableName` WHERE $where";
  mysqli()->query($delete) or dieAsCaller("MySQL Error: ". htmlencode(mysqli()->error) . "\n");
  return mysqli()->affected_rows; // added in 2.13
}


// update matching records
// mysql_update($tableName, $recordNum, null,                     array('set' => '1234'));
// mysql_update($tableName, null,       "createdByUserNum = '1'", array('set' => '1234'));
// mysql_update($tableName, $recordNum, "createdByUserNum = '1'", array('set' => '1234', 'updatedDate=' => 'NOW()'));
function mysql_update($tableName, $recordNum, $customWhere, $columnsToValues) {
  $tableName  = getTableNameWithPrefix($tableName);
  $where      = _mysql_getWhereFromNumAndCustomWhere($recordNum, $customWhere);
  $set        = mysql_set($columnsToValues);
  $update     = "UPDATE `$tableName` SET $set WHERE $where";
  mysqli()->query($update) or dieAsCaller("MySQL Error: ". mysqli()->error. "\n");
}


// insert a record
// $newRecordNum = mysql_insert($tableName, $colsToValues);
// $newRecordNum = mysql_insert($tableName, array('name' => 'asdf', 'createdDate=' => 'NOW()'));
// v2.16 - added $tempDisableMysqlStrictMode option
function mysql_insert($tableName, $colsToValues, $tempDisableMysqlStrictMode = false) {

  //
  $tableName = getTableNameWithPrefix($tableName);
  $set       = mysql_set($colsToValues);
  $insert    = "INSERT INTO `$tableName` SET $set";

  //
  if ($tempDisableMysqlStrictMode) { mysqlStrictMode(false); }
  mysqli()->query($insert) or dieAsCaller("MySQL Error: ". mysqli()->error. "\n");
  $recordNum = mysqli()->insert_id;
  if ($tempDisableMysqlStrictMode) { mysqlStrictMode(true); }

  return $recordNum;
}


// $where = _mysql_getWhereFromNumAndCustomWhere($recordNum, $customWhere);
// v2.50 - $recordNum now accepts zero "0" and doesn't treat it as undefined
function _mysql_getWhereFromNumAndCustomWhere($recordNum, $customWhere) {
  if (is_array($customWhere)) { $customWhere = mysql_where($customWhere); }
  $where      = '';
  if ($recordNum != '') { $where .= "`num` = " .intval($recordNum); }
  if ($customWhere)     { $where .= ($where) ? " AND ($customWhere)" : $customWhere; }
  if ($where == '')     { $where  = 'FALSE'; } // match nothing if no where specified
  return $where;
}


// Internal function for INSERT and UPDATE queries.  Creates quotes and mysql escaped
// ... SET clause from $colsToValues array
/*
  $colsToValues = [
    'message'      => "User 'input' gets quoted and escaped!",
    'userNum'      => 1234,
    'updatedDate=' => 'NOW()', // trailing = doesn't escape or quote value
  ];
  $setClause = mysql_set($colsToValues);
  // returns: `message` = 'User \'input\' gets quoted and escaped!', `userNum` = '1234', `updatedDate` = NOW()
  
*/
function mysql_set($columnsToValues) {
  $mysqlSet = '';

  if (is_array($columnsToValues)) {
    foreach ($columnsToValues as $column => $value) {
      list($column, $dontEscapeValue) = extractSuffixChar($column, '=');

      // error checking: whitelist column chars to prevent sql injection
      if (!preg_match('/^([\w\-]+)$/i', $column)) {
        dieAsCaller(__FUNCTION__. ": Invalid column name '" .htmlencode($column). "', contains disallowed chars!");
      } 

      if ($dontEscapeValue) { $mysqlSet .= "`$column` = $value, "; }
      else                  { $mysqlSet .= "`$column` = '" . mysql_escape($value) . "', "; }
    }
  }

  //
  $mysqlSet = chop($mysqlSet, ', ');

  return $mysqlSet;
}

// convenience function for turning an array into a WHERE clause
function mysql_where($criteriaArray = null, $extraWhere = 'TRUE') {
  $where = '';
  if ($criteriaArray) {
    foreach ($criteriaArray as $fieldName => $value) {
      if (!preg_match('/^(\w+)$/', $fieldName)) { die(__FUNCTION__. ": Invalid column name '" .htmlencode($fieldName). "'!"); } // error checking: whitelist column chars to prevent sql injection
      
      // if $value is an array, use the IN operator
      if (is_array($value)) {
        $where .= "`$fieldName` IN (" . mysql_escapeCSV($value) . ") AND ";
      }
      
      // otherwise, test for equality
      else {
        $where .= mysql_escapef("`$fieldName` = ? AND ", $value);
      }
    }
  }
  $where .= $extraWhere;
  return $where;
}


//eof