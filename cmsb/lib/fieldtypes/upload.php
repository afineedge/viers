<?php

class UploadField extends Field {

function __construct($fieldSchema) {
  parent::__construct($fieldSchema);
}


//
function getDisplayValue($record) { // override me in derived classes
  $value = $this->getDatabaseValue($record);
  if (is_array($value)) { return 'array'; } // for debugging
  return htmlencode( $value );
}


//
function getTableRow($record, $value, $formType) {
  global $preSaveTempId, $SETTINGS, $menu;

  $prefixText  = @$this->fieldPrefix;
  $description = @$this->description;
  if ($prefixText) { $prefixText .= "<br/>"; }

  // create uploadList url
  $uploadList = "?menu=" . urlencode($menu)
              . "&amp;action=uploadList"
              . "&amp;fieldName=" . urlencode($this->name)
              . "&amp;num=" . urlencode(@$_REQUEST['num'])
              . "&amp;preSaveTempId=" . urlencode($preSaveTempId)
              . "&amp;formType=" . urlencode($formType);

  // create uploadLink url
  $uploadLink = "?menu=" . urlencode($menu)
              . "&amp;action=uploadForm"
              . "&amp;fieldName=" . urlencode($this->name)
              . "&amp;num=" . urlencode(@$_REQUEST['num'])
              . "&amp;preSaveTempId=" . urlencode($preSaveTempId);

  // error checking
  $errors = '';
  $uploadDir = @$this->useCustomUploadDir ? $this->customUploadDir : $SETTINGS['uploadDir'];
  if     (!file_exists($uploadDir)) { mkdir_recursive($uploadDir, 0755); }  // create upload dir (if not possible, dir not exists error will show below)
  if     (!file_exists($uploadDir)) { $errors .= "Upload directory '" .htmlencode($uploadDir). "' doesn't exist!.<br/>\n"; }
  elseif (!is_writable($uploadDir)) { $errors .= "Upload directory '" .htmlencode($uploadDir). "' isn't writable!.<br/>\n"; }

  // display errors
  if ($errors) { $html = <<<__HTML__
    <div class="form-group">
      <div class="col-sm-2 control-label">
        {$this->label}
      </div>
      <div class="col-sm-10">
        <div id='alert'><span>$errors</span></div>
      </div>
    </div>
__HTML__;
  return $html;
  }

  // display field
  $html  = '';
  $html .= "<div class='form-group'>\n";
  $html .= "  <div class='col-sm-2 control-label'>{$this->label}</div>\n";
  $html .= "  <div class='col-sm-10'>\n";
  
  $html .= "    <p class='help-block'>$prefixText</p>\n";
  $html .= "    <iframe id='{$this->name}_iframe' src='$uploadList' height='100' width='100%' frameborder='0' class='uploadIframe'></iframe><br/>\n";

  // show upload buttons
  if ($formType == 'edit') {
    $html .= "    <div style='position: relative; height: 24px;'>\n";
    $html .= "      <div style='position: absolute; top: 6px; width: 100%; text-align: center;'>\n";
    $html .= "        <a href='$uploadLink'><b>" .t('Add or Upload File(s)'). "</b></a>\n";
    $html .= "      </div>\n";
    $html .= "      <div style='position: absolute; z-index: 1; width: 100%; text-align: center;'>\n";
    $html .= "        <div id='{$this->name}_uploadButton'></div>\n";
    $html .= "      </div>\n";
    $html .= "    </div>\n";
    $html .= $this->_getFlashUploaderHTML();
  }
  else {
    $html .= "<br/>";
  }
  
  $html .= "    <p class='help-block'>$description</p>\n";
  $html .= "  </div>\n";
  $html .= "</div>\n";

  return $html;

}


//
function _getFlashUploaderHTML() {
  global $SETTINGS;

  if (@$SETTINGS['advanced']['disableFlashUploader']) { return; }

  //
  $fileExtCSV = implode(',', preg_split("/\s*\,\s*/", strtolower($this->allowedExtensions)));
  $isMac      = (preg_match('/macintosh|mac os x/i', @$_SERVER['HTTP_USER_AGENT']));
  $key        = $isMac ? '<Command>' : '<Ctrl>';
  $tip        = htmlencode( t("Tip: hold $key to select multiple files") );

  //
  $html  = '';
  $html .= "<div id='{$this->name}_uploadTips' style='display: none; text-align: center; font-size: xx-small; margin-top: 2px;'>";
  $html .= "  $tip<br/>$description<br/>";
  $html .= "</div>";
  $html .= "<div class='uploadifyQueue' id='{$this->name}_uploadQueue'></div>";
  $html .= "<script type='text/javascript'>// <![CDATA[\n";
  $html .= "\$(document).ready(function() { \n";
  $html .= "  \$('#{$this->name}_uploadButton').uploadify(generateUploadifyOptions({ \n";
  $html .= "    'script'           : " .json_encode( basename(@$_SERVER['SCRIPT_NAME']) ). ",\n";
  $html .= "    'modifyAfterSave'  : " .count(getUploadInfoFields($this->name)). ",\n";
  $html .= "    'menu'             : " .json_encode($menu). ",\n";
  $html .= "    'fieldName'        : " .json_encode($this->name). ",\n";
  $html .= "    'num'              : " .json_encode(@$_REQUEST['num'] ? $_REQUEST['num'] : ''). ",\n";
  $html .= "    'preSaveTempId'    : " .json_encode($preSaveTempId). ",\n";
  $html .= "    'buttonText'       : " .json_encode(t('Upload File(s)')). ",\n";
  $html .= "    'fileExtCSV'       : " .json_encode($fileExtCSV). ",\n";
  $html .= "    'maxUploadSizeKB'  : " .json_encode($this->checkMaxUploadSize ? $this->maxUploadSizeKB : 0). ",\n";
  $html .= "    'loginDataEncoded' : " .json_encode( @$_COOKIE[loginCookie_name(true)] ). ",\n";
  $html .= "    'queueID'          : " .json_encode($this->name . "_uploadQueue"). ",\n";
  $html .= "  }));\n";
  $html .= "});\n";
  $html .= "// ]]></script>\n";

  return $html;
}


} // end of class


?>
