<?php
  require_once "init.php";

  // viewer init
  require_once __DIR__ . "/init.php";
  if (!isInstalled()) { die("Error: You must install the program before you can use the viewers."); }

  //
  doAction('viewer_postinit', '3.0');  // second argument is viewer library version


// getRecordsAPI - See examples and function header in viewerAPI.php
function getRecordsAPI($args) {

  //
  try {
    $options  = _grAPI_options($args);        // get options
    $query    = _grAPI_buildQuery($options);
    list($records, $totalRecords) = _grAPI_loadResults($query);

    _grAPI_addLinkFields($options, $records);
    _grAPI_addPseudoFields($options, $records);
    _grAPI_addUploadFields($options, $records);

    $metadata = _grAPI_getMetadata($options, count($records), $totalRecords);
  }

  // catch errors
  catch (Exception $e) {
    $errorCode = $e->getCode();
    $errorText = htmlencode(nl2br($e->getMessage()));
    $error     = __FUNCTION__ . ": $errorText";
    $error    .= $errorCode ? " ($errorCode)" : "";
    die($error);

    // check for json errors here
    return;
  }

  // plugin filters
  $schema  = $options['_schema'];
  $records = applyFilters('viewer_output_rows', $records, $metadata, $schema);

  // return results
  return array($records, $metadata, $schema);
}


// get options and return error
function _grAPI_options($args) {
  $options = [];

  // check for invalid options
  if (!is_array($args)) { throw new Exception("First option must be an array!"); }
  $validOptions   = ['allowSearch','debugSql','limit','loadPseudoFields','loadUploads','offset','orderBy','pageNum','params','perPage','tableName','where','apiVersion','_REQUEST'];
  $unknownOptions = array_diff(array_keys($args), $validOptions);
  if ($unknownOptions) {
    $validOptionsAsCSV   = implode(', ', $validOptions);
    $unknownOptionsAsCSV = implode(', ', $unknownOptions);
    throw new Exception("Unknown option ($unknownOptionsAsCSV) specified.  Valid option are: ($validOptionsAsCSV)");
  }

  // options to passthru (sets defaults if not specified)
  $options['allowSearch']      = isset($args['allowSearch'])      ? boolval($args['allowSearch'])      : true;
  $options['loadPseudoFields'] = isset($args['loadPseudoFields']) ? boolval($args['loadPseudoFields']) : true;
  $options['loadUploads']      = isset($args['loadUploads'])      ? boolval($args['loadUploads'])      : true;
  $options['debugSql']         = isset($args['debugSql'])         ? boolval($args['debugSql'])            : false;

  // tableName
  if (empty($args['tableName']))         { throw new Exception("No tableName specified!"); }
  if (!schemaExists($args['tableName'])) { throw new Exception("tableName doesn't exist!"); }
  $options['tableName']     = $args['tableName'];
  $options['_schema']       = loadSchema($options['tableName']);
  $options['_schemaFields'] = array_filter($options['_schema'], 'is_array');
  $validColumns = array_keys($options['_schemaFields']); // Future: Do we need exclude non-mysql fields?

  // allow _REQUEST keys to set undefined options
  $args = _grAPI_options_fromRequest($args, $options['_schemaFields']);

  // limit, offset, perPage, pageNum
  foreach (['limit','offset','perPage','pageNum'] as $name) {
    if (!isset($args[$name])) { continue; }
    $isBlankOrInteger = !preg_match("/[^0-9]/", $args[$name]);
    if (!$isBlankOrInteger) { throw new Exception("'$name' must be blank or an integer, not '{$args[$name]}'!"); }
  }
  if (!empty($args['perPage']) && (!empty($args['limit']) || !empty($args['offset']))) {
    throw new Exception("Can't set 'perPage' if 'limit' or 'offset' are set, choose one!");
  }
  if      (!empty($args['perPage'])) { $options['limit']   = intval($args['perPage']); }                 // perPage overrides limit
  else if (!empty($args['limit']))   { $options['limit']   = intval($args['limit']); }                   //
  else                               { $options['limit']   = 100; }                                      // default if not specified
  if      (!empty($args['pageNum'])) { $options['offset']  = ($args['pageNum']-1) * $options['limit']; } // pageNum overrides offset
  else if (!empty($args['offset']))  { $options['offset']  = intval($args['offset']); }                  //
  else                               { $options['offset']  = 0; }                                        // default if not specified
  if      (!empty($args['perPage'])) { $options['perPage'] = intval($args['perPage']); }
  else                               { $options['perPage'] = 10; }                                       // default if not specified
  if      (!empty($args['pageNum'])) { $options['pageNum'] = intval($args['pageNum']); }                 // NOTE: This pageNum can be set by $_REQUEST['page'] above
  else                               { $options['pageNum'] = 1; }                                        // default if not specified

  // params (for where, orderby, etc)
  $options['params'] = [];
  if (!empty($args['params'])) {
    if (!is_array($args['params'])) { throw new Exception("params must be an array!"); }
    foreach ($args['params'] as $param => $value) {
      if (!preg_match("/^:\w+\z/m", $param)) { throw new Exception("Invalid param name '$param'. Parameters start with a colon follow by one or more letters, :likethis"); }
    }
    $options['params'] = $args['params'];
  }

  // where
  $options['where'] = '';
  if (!empty($args['where'])) {
    $sqlErrors  = _grAPI_options_validateSQL($args['where'], $validColumns, $options['params']);
    if ($sqlErrors) { throw new Exception("Option 'where' has errors: $sqlErrors\n"); }
    $options['where'] = $args['where'];
  }

  // orderBy
  $options['orderBy'] = $options['_schema']['listPageOrder']; // default to sort order in the CMS
  if (!empty($args['orderBy'])) {
    $sqlErrors  = _grAPI_options_validateSQL($args['orderBy'], $validColumns, $options['params']);
    if ($sqlErrors) { throw new Exception("Option 'orderBy' has errors: $sqlErrors\n"); }
    $options['orderBy'] = $args['orderBy'];
  }

  //
  return $options;
}

// allow _REQUEST keys to set undefined options
function _grAPI_options_fromREQUEST($args, $fieldSchemas) {

  // allow overriding _REQUEST (for recreate API request values)
  if (isset($args['_REQUEST'])) { $_REQUEST = $args['_REQUEST']; }

  // pageNum - $_REQUEST['page'] overrides unset 'pageNum' options
  if (!empty($_REQUEST['page']) && empty($args['pageNum'])) {
    $args['pageNum'] = intval($_REQUEST['page']);
  }

  // orderBy - $_REQUEST['orderBy'] overrides any orderBy
  if (!empty($_REQUEST['orderBy'])) {
    foreach ($fieldSchemas as $fieldname => $fieldSchema) {
      // security check: Only return orderby in format of: fieldname, or fieldname DESC
      if ($_REQUEST['orderBy'] == $fieldname)        { $args['orderBy'] = "$fieldname"; }
      if ($_REQUEST['orderBy'] == "$fieldname DESC") { $args['orderBy'] = "$fieldname DESC"; }
    }
  }

  //
  return $args;
}


// Return errors for where or order by clause.  Supports limited subset of MySQL expressions and operators.
// Be sure to htmlencode $parseErrors as they may contain user input
// Supports: column, column ASC, column DESC, and RAND()
// FUTURE: Add support for operators: LIKE and IN
/* Usage:
  $parseErrors = _grAPI_options_validateSQL($where, $validColumns, $params);
  if ($parseErrors) { } // Return error
*/
function _grAPI_options_validateSQL($clause, $validColumns, $params) {
  $parseErrors = null;

  // allowed columns
  $allowedColumnsRegexp = implode('|', array_map('preg_quote', $validColumns));

  // allowed operators
  // Reference: https://dev.mysql.com/doc/refman/5.7/en/func-op-summary-ref.html
  // Reference: https://dev.mysql.com/doc/refman/5.7/en/non-typed-operators.html
  $allowedOperators       = ['!=','&&','(',')','0','1','<','<=','=','>','>=','AND','ASC','DESC','OR','NOW()','RAND()','false','true','||'];
  $allowedOperatorsRegexp = implode('|', array_map('preg_quote', $allowedOperators));

  // allowed parameters
  $allowedParams       = array_keys($params);
  $allowedParamsRegexp = implode('|', array_map('preg_quote', $allowedParams));

  // parse Where clause - remove recognized expressions and operators from the start of the
  // ... string and return errors on any unrecognized content
  $remaining  = $clause;
  $lastString = null;
  while ($remaining != '') {
    $remaining = trim($remaining); // remove leading/trailing whitespace

    // prevent infinite loops
    if ($remaining == $lastString) {
      $parseErrors .= "Nothing removed in last cycle.  Remaining clause: $remaining\n";
      break;
    }
    $lastString = $remaining;

    // parse operators and expressions
    $regexps[] = "/^($allowedOperatorsRegexp)/i";
    if ($allowedColumnsRegexp) { $regexps[] = "/^($allowedColumnsRegexp)/"; }
    if ($allowedParamsRegexp)  { $regexps[] = "/^($allowedParamsRegexp)/"; }

    foreach ($regexps as $regexp) {
      $r = preg_replace($regexp, '', $remaining, 1, $count);
      if ($count) {  // if replacement made
        $remaining = $r;
        continue 2; // continue at while
      }
    }

    // parse errors
    $parseErrors .= "Invalid or unsupported MySQL expression starting at: " . htmlencode($remaining) . "\n\n";
    $parseErrors .= "NOTE: Only the following subset of MySQL is supported:\n";
    $parseErrors .= "MySQL: "   .implode(', ', $allowedOperators). "\n";
    $parseErrors .= "Columns: " .implode(', ', $validColumns).     "\n";
    $parseErrors .= "Params: "  .implode(', ', $allowedParams).    "\n";

    break;
  }

  //
  return $parseErrors;
}


// Create MySQL query from options
function _grAPI_buildQuery($options) {

  // build query
  $tableName           = $options['tableName'];
  $tableNameWithPrefix = getTableNameWithPrefix($options['tableName']);
  $schema              = $options['_schemaFields'];

  // select
  $selectList = "`$tableName`.*";

  // where
  $whereConditions   = [];
  if ($options['where'] != '')                                                { $whereConditions[] = $options['where']; }
  if (isset($schema['hidden'])      && !empty($options['ignoreHidden']))      { $whereConditions[] = "`$tableName`.hidden = 0"; }
  if (isset($schema['publishDate']) && !empty($options['ignorePublishDate'])) { $whereConditions[] = "`$tableName`.publishDate <= NOW()"; }
  if (isset($schema['removeDate'])  && !empty($options['ignoreRemoveDate']))  {
    $thisCondition = "`{$tableName}`.removeDate >= NOW()";
    if (!empty($schema['neverRemove'])) { $thisCondition .= " OR `$tableName`.neverRemove = 1"; } // never remove checked
    array_push($whereConditions, "($thisCondition)");
  }

  // order by
  $orderList = $options['orderBy'];
  $orderByClause = "";

  // create query
  $query  = "SELECT SQL_CALC_FOUND_ROWS $selectList\n";
  $query .= "  FROM `$tableNameWithPrefix` AS `$tableName`\n";
  //$query .= $LEFT_JOIN; // not yet implemented
  if ($whereConditions)    { $query .= " WHERE "    .implode(" AND ", $whereConditions). "\n"; }
  if ($options['orderBy']) { $query .= " ORDER BY " .$options['orderBy']. "\n"; }
  if ($options['limit'])   { $query .= " LIMIT "    .intval($options['limit']); }
  if ($options['offset'])  { $query .= " OFFSET "   .intval($options['offset']); }

  // replace parameters (emulate prepared statements)
  $params = $options['params'];
  $query  = preg_replace_callback("/:\w+\b/",
                                  function ($matches) use ($params) {
                                    $paramName  = $matches[0];
                                    $paramValue = "'" .mysql_escape($params[$paramName]). "'";
                                    return $paramValue;
                                  },
                                  $query);

  // debugSql
  if ($options['debugSql']) { print "<xmp>$query</xmp>"; }

  //
  return $query;
}

//
function _grAPI_loadResults($query) {
  $records      = mysql_select_query($query);
  $totalRecords = mysql_get_query("SELECT FOUND_ROWS()", true)[0];
  return [$records, $totalRecords];
}

//
function _grAPI_addLinkFields($options, &$records) {
  $schema = $options['_schema'];

  // records - add pseudo fields
  foreach ($records as &$record) {
    $record['_filename'] = _grAPI_addLinkFields_queryStringText($options, $record);
    $record['_link']     = PREFIX_URL . $schema['_detailPage'] .'?'. $record['_filename'];
    if   (empty($schema['_detailPage'])) { $record['_link'] = "javascript:alert('Set Detail Page Url for this section in: Admin > Section Editors > Viewer Urls')"; }
  }
  unset($record);
}


// Generates _filename field that is added to viewer links to create more descriptive urls for users and search engines.
// The first field value that isn't blank is used.
// Example Url: viewer.php?record_title_goes_here-123
function _grAPI_addLinkFields_queryStringText($options, $record) {
  $filenameFieldsAsCSV = $options['_schema']['_filenameFields'];
  $filenameFields      =  preg_split("/\s*,\s*/", $filenameFieldsAsCSV);

  // get first defined field value
  $queryStringText    = '';
  $originalFieldValue = '';
  foreach ($filenameFields as $fieldname) {
    if (empty($record[$fieldname])) { continue; } // skips non-existant fields as well
    $originalFieldValue = $record[$fieldname];
    $queryStringText    = $record[$fieldname];
    $queryStringText    = preg_replace('/[^a-z0-9\.\-\_]+/i', '-', $queryStringText);
    $queryStringText    = preg_replace("/(^-+|-+$)/", '', $queryStringText); # remove leading and trailing underscores
    if ($queryStringText) { $queryStringText .= "-"; }
    break;
  }

  //

  $queryStringText    = applyFilters('viewer_link_field_content', $queryStringText, $originalFieldValue, $record);

  // add record number
  $queryStringText .= $record['num'];

  //
  return $queryStringText;
}



//
function _grAPI_addPseudoFields($options, &$records) {
  if (empty($options['loadPseudoFields'])) { return; }
  if (!$records) { return; }
  $schema = $options['_schema'];

  // get source fields
  $sourceFields = array();
  $sourceFieldTypes = array('checkbox','list', 'date');
  foreach ($schema as $fieldname => $fieldSchema) {
    if (!is_array($fieldSchema) || !@$fieldSchema['type']) { continue; }
    if (!in_array($fieldSchema['type'], $sourceFieldTypes)) { continue; }
    $sourceFields[$fieldname] = $fieldSchema;
  }
  if (!$sourceFields) { return; }

  // add pseudo-fields
  foreach ($sourceFields as $fieldname => $fieldSchema) {
    $isDate       = ($fieldSchema['type'] == 'date');
    $isCheckbox   = ($fieldSchema['type'] == 'checkbox');
    $_isList      = ($fieldSchema['type'] == 'list');
    $isSingleList = $_isList && !in_array($fieldSchema['listType'], array('pulldownMulti', 'checkboxes'));
    $isMultiList  = $_isList && !$isSingleList;
    if (!$isDate && !$isCheckbox && !$isSingleList && !$isMultiList) { die(__FUNCTION__ . ": field '$fieldname' of type '{$fieldSchema['type']}' isn't a known source field!"); }

    // List Fields that "Get Options from Database" - only lookup labels for values in record-set.
    $selectedValues = array();
    if ($_isList && @$fieldSchema['optionsType'] == 'table') {
      foreach ($records as $record) {
        foreach (getListValues(null, null, $record[$fieldname]) as $value) {
          $selectedValues[$value] = 1;
        }
      }
      $selectedValues = array_keys($selectedValues);
      $selectedValues = array_filter($selectedValues); // remove blank entries
    }

    // get values to labels for list fields
    if ($_isList) {
      /*  Special handling for list/query with a filter field because the available values/labels can change based on other fields.
          We need to check the possible options for each record instead of pulling the options for the table as a whole.
          Since this is a different process than any other field, we get the list options and assign the labels here then continue the main loop.
      */
      if(@$fieldSchema['optionsType'] == 'query' && @$fieldSchema['filterField']) {
        $recordListOptions = array();
        foreach (array_keys($records) as $index) {
          $record = &$records[$index]; // PHP4 safe references
          $recordListOptions = getListOptionsFromSchema($fieldSchema, $record);
          $values         = array_pluck($recordListOptions, '0');
          $labels         = array_pluck($recordListOptions, '1');
          $valuesToLabels = $recordListOptions ? @array_combine($values, $labels) : array();
          $label = @$valuesToLabels[ $record[$fieldname] ];
          $record["$fieldname:label"] = $label;
        }
        unset($record);
        continue;
      }
      else {
        $listOptions    = getListOptionsFromSchema($fieldSchema, null, false, $selectedValues);
        $values         = array_pluck($listOptions, '0');
        $labels         = array_pluck($listOptions, '1');
        $valuesToLabels = $listOptions ? array_combine($values, $labels) : array();
      }
    }

    // add pseudo-fields
    foreach (array_keys($records) as $index) {
      $record = &$records[$index]; // PHP4 safe references
      if ($isDate) {
        $time = @strtotime( $record[$fieldname] );
        $record["$fieldname:unixtime"] = $time;
      }
      elseif ($isCheckbox) {
        $text = ($record[$fieldname]) ? @$fieldSchema['checkedValue'] : @$fieldSchema['uncheckedValue'];
        $record["$fieldname:text"] = $text;
      }
      elseif ($isSingleList) {
        $label = @$valuesToLabels[ $record[$fieldname] ];
        $record["$fieldname:label"] = $label;
      }
      elseif ($isMultiList) {
        $values = getListValues($options['tableName'], $fieldname, $record[$fieldname]);
        $labels = array();
        foreach ($values as $value) { $labels[] = @$valuesToLabels[ $value ]; }
        $record["$fieldname:values"] = $values;
        $record["$fieldname:labels"] = $labels;
      }

      // sort keys so related fields are grouped together
      ksort($record);
      unset($record);
    }
  }
}


//
function _grAPI_addUploadFields($options, &$records) {
  if (empty($options['loadUploads'])) { return; }

  $tableName     = $options['tableName'];
  $debugSql      = $options['debugSql'];
  $preSaveTempId = '';
  addUploadsToRecords($records, $tableName, $debugSql, $preSaveTempId);
}



//
function _grAPI_getMetaData($options, $rowCount, $totalRecords) {
  $schema = $options['_schema'];
  $details = array();

  ### get list details
  $details = array();
  $details['invalidPageNum']   = !$rowCount && $options['pageNum'] > 1;
  $details['noRecordsFound']   = !$rowCount && $options['pageNum'] == 1;
  $details['page']             = $options['pageNum'];
  $details['perPage']          = $options['perPage'];

  $details['totalPages']       = 1;
  if (@$options['perPage'] && $totalRecords > $options['perPage']) {
    $details['totalPages'] = ceil($totalRecords / $options['perPage']);
  }

  $details['totalRecords']     = $totalRecords;
  $details['pageResultsStart'] = min($totalRecords, $options['offset'] + 1);
  $details['pageResultsEnd']   = min($totalRecords, $options['offset'] + $options['limit']);

  # get page nums
  $_minOfPageNumAndTotalPages    = min($options['pageNum'], $details['totalPages']);
  $details['prevPage']       = ($_minOfPageNumAndTotalPages > 1) ? $_minOfPageNumAndTotalPages-1 : '';
  $details['nextPage']       = ($_minOfPageNumAndTotalPages < $details['totalPages']) ? $_minOfPageNumAndTotalPages+1 : '';
  if ($details['invalidPageNum']) {
    $details['prevPage'] = $details['totalPages'];
  }

  // pass query arguments forward in page links - use http_build_query to support multi-value fields, like this: ?colors[]=red&colors[]=blue&etc...
  $filteredRequest = isset($_REQUEST) ? $_REQUEST : array(); // ensure we get an array, some user scripts unset($_REQUEST); on form submit to clear values
  unset( $filteredRequest['page'] );
  $extraQueryArgs    = http_build_query($filteredRequest, null, '&amp;');
  if ($extraQueryArgs) { $extraQueryArgs .= '&amp;'; }
  $extraQueryArgs    = preg_replace('/=&amp;/i', '&amp;', $extraQueryArgs); // v2.50 for query keys with no value remove trailing =, eg: ?record-title-123 instead of ?record-title-123=
  $extraQueryArgs    = preg_replace('/(%5B|\[)\d+(\]|%5D)/i', '[]', $extraQueryArgs); // square brackets get escaped as of PHP 5.1.3 - replace colors[0], colors[1] with colors[], see: http://php.net/manual/en/function.http-build-query.php#77377
  $extraPathInfoArgs = str_replace(array('=','&amp;'), array('-','/'), $extraQueryArgs);

  # get page links
  $listViewer = ''; // @$_SERVER['SCRIPT_NAME']; // Doesn't work with API 
  $listViewer = str_replace(' ', '%20', $listViewer); // v2.50 : url encoded spaces
  $details['prevPageLink']  = $listViewer;
  $details['nextPageLink']  = $listViewer;
  $details['firstPageLink'] = $listViewer;
  $details['lastPageLink']  = $listViewer;

  // use the same url for page 1 urls if possible, not viewer.php and viewer.php?page=1
  // see: http://www.google.com/support/webmasters/bin/answer.py?hl=en&answer=66359
  $details['firstPageLink'] .= ($extraQueryArgs) ? "?{$extraQueryArgs}page=1" : '';
  $details['prevPageLink']  .= ($details['prevPage'] != 1 || $extraQueryArgs) ? "?{$extraQueryArgs}page={$details['prevPage']}" : '';
  $details['nextPageLink']  .= "?{$extraQueryArgs}page={$details['nextPage']}";
  $details['lastPageLink']  .= ($details['totalPages'] != 1 || $extraQueryArgs) ? "?{$extraQueryArgs}page=" . $details['totalPages'] : '';

  //
  $details['_detailPage'] = @$schema['_detailPage'] ? PREFIX_URL.$schema['_detailPage'] : '';
  $details['_listPage']   = @$schema['_listPage']   ? PREFIX_URL.$schema['_listPage'] : "javascript:alert('Set List Page Url for this section in: Admin &gt; Section Editors &gt; " .jsEncode($schema['menuName']). " &gt; Viewer Urls')"; ;
  $details['_listPage']   = str_replace(' ', '%20', $details['_listPage']); // v2.60 : urlencode spaces so they validate
  return $details;
}


// returns page number from url.  matches: view.php/anythinghere-####.html
function getLastNumberInUrl($defaultNum = null) {
  $recordNum = 0;
  $urlDataFields = array(@$_SERVER["PATH_INFO"], @$_SERVER["QUERY_STRING"]);
  foreach ($urlDataFields as $urlData) {

    // 2.52 - 3rd party websites sometimes add their own field-value pairs to the query string.  We remove them here in case they contain trailing numbers
    $removeFields  = array(
      'utm_source','utm_medium','utm_term','utm_content','utm_campaign',          // google utm names: http://www.google.com/support/googleanalytics/bin/answer.py?answer=55578
      'fb_source','fb_action_ids','fb_action_types','fb_ref','fb_aggregation_id', // facebook parameter names: https://developers.facebook.com/docs/technical-guides/opengraph/link-parameters/
      'action_object_map','action_type_map','action_ref_map',                     // additional facebook parameters
      'gclid',                                                                    // used by Google AdWords auto-tagging: https://support.google.com/analytics/answer/1033981
    );
    $removeFields = applyFilters('getLastNumberInUrl_removeFields', $removeFields);
    foreach ($removeFields as $removeField) { $urlData = preg_replace("/&$removeField=[^&]*/", '', $urlData); }

    // remove page=# so we don't get that by accident
    $urlData = preg_replace("/\bpage=\d+\b/", '', $urlData);

    //
    if (preg_match("/\D*(\d+)(\D+)?\z/", $urlData, $matches)) {
      $recordNum = $matches[1];
      break;
    }
  }

  //
  if (!$recordNum && $defaultNum) { $recordNum = $defaultNum; }

  //
  return $recordNum;
}



// eof