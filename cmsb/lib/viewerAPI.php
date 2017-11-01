<?php
  // Viewer Functions v3.0

/* Examples: 

  // load records
  require_once "cmsb/lib/viewerAPI.php";
  list($tablename_records, $tablename_metadata) = getRecordsAPI([
    'tableName' => 'every_field_multi',

    'limit'     => '',         // optional, defaults to 10, blank
    'offset'    => '',         // optional, defaults to 0,  (if set but no limit then limit is set to high number as per mysql docs)
    'perPage'   => '',         // optional, default 10, number of records to show per page, default to 10
    'pageNum'   => '',         // optional, default 1, page number to display defaults to $_REQUEST['page'] or 1

    'where'     => 'num = :num',
    'params'    => [
      ':num' => whereRecordNumberInUrl(1),
    ],
    'orderBy'             => '',         // optional, defaults to $_REQUEST['orderBy'], or table sort order
    
    // -----------------------------------------------------------------------
    // pending options: 

    // advanced options
    'debugSQL' => true,
    'debugAPI' => true,
    'api_json_response' => '', // alt names: useJson, jsonErrors
      
    'allowSearch'             => '',         // optional, defaults to yes, adds search info from query string
    'loadUploads'             => '',         // optional, defaults to yes, loads upload array into upload field
    'loadCreatedBy'           => '',         // optional, defaults to yes, adds createdBy. fields for created user
    'loadPseudoFields'        => false,      // optional, defaults to yes, adds additional fields for :text, :label, :values, etc

    'ignoreHidden'            => false,  // don't hide records with hidden flag set
    'ignorePublishDate'       => false,  // don't hide records with publishDate > now
    'ignoreRemoveDate'        => false,  // don't hide records with removeDate < now
    'includeDisabledAccounts' => true,   // include records that were created by disabled accounts.  See: Admin > Section Editors > Advanced > Disabled Accounts
    
    'columns'                 => '',         // optional, default to * (all)
    'requireSearchMatch'      => '',         // optional, don't show any results unless search keyword submitted and matched
    'requireSearchSuffix'     => '',         // optional, search fields must end in a suffix such as _match or _keyword, original field=value match search is ignored
    'loadListDetails'         => '',         // optional, defaults to yes, adds $details with prev/next page, etc info

    'useCustomPatch' => "d41d8cd98f00b204e9800998ecf8427e"; // override
  ));
*/

// load viewerAPI library
if     (file_exists(__DIR__."/viewerAPI_client.php"))    { require_once(__DIR__."/viewerAPI_client.php"); }
elseif (file_exists(__DIR__."/viewerAPI_functions.php")) { require_once(__DIR__."/viewerAPI_functions.php"); }
else                                                     { die("Couldn't find viewerAPI library!"); }

// eof

