<?php
  global $tableName, $errors, $schema, $isUploadLimit, $uploadsRemaining, $maxUploads;
  require_once "lib/menus/default/uploadForm_functions.php";

  $errors .= alert();
?>
<!DOCTYPE html
          PUBLIC "-//W3C//DTD XHTML 1.0 Transitional//EN"
          "http://www.w3.org/TR/xhtml1/DTD/xhtml1-transitional.dtd">

<html xmlns="http://www.w3.org/1999/xhtml">
 <head>
  <title></title>
  <meta http-equiv="Content-Type" content="text/html;charset=UTF-8" />
  <link rel="stylesheet" href="<?php echo CMS_ASSETS_URL ?>/3rdParty/clipone/plugins/bootstrap/css/bootstrap.min.css">
  <link rel="stylesheet" href="https://maxcdn.bootstrapcdn.com/font-awesome/4.7.0/css/font-awesome.min.css">

  <base target="_self" />
  <style type="text/css">
    .photobox{
      height: 200px;
    }
    .photobox .thumbnail{
      min-height: 150px;
    }
  </style>
 </head>

<body>

<form method="post" action="?" enctype="multipart/form-data" autocomplete="off" class="form-inline">
<input type="hidden" name="_defaultAction" value="wysiwygUploads" />
<input type="hidden" name="menu" id="menu" value="<?php echo htmlencode($menu) ?>" />
<input type="hidden" name="tableName" id="tableName" value="<?php echo htmlencode($tableName) ?>" />
<input type="hidden" name="fieldName" id="fieldName" value="<?php echo htmlencode(@$_REQUEST['fieldName']) ?>" />
<input type="hidden" name="num"  id="num" value="<?php echo htmlencode(@$_REQUEST['num']) ?>" />
<input type="hidden" name="preSaveTempId"  id="preSaveTempId" value="<?php echo htmlencode(@$_REQUEST['preSaveTempId']) ?>" />
<input type="hidden" name="submitUploads" value="1" />
<input type="hidden" name="wysiwygForm"   value="1" />
<?php echo security_getHiddenCsrfTokenField(); ?>

<div class="container">
  <div class="row" style="margin-top: 20px">
    <div class="col-xs-10">
      <input type="file" name="upload1" value='' class="form-control" />
    </div>
    <div class="col-xs-2">
      <?php echo adminUI_button(['label' => t('Upload'), 'btn-type' => 'primary' ]); ?>
    </div>
  </div>
  
  <?php if (@$errors): ?>
  <div class="row" style="margin-top: 10px">
    <div class="col-xs-12">
     <div class="alert alert-warning" style="margin-bottom: 0px">
      <?php echo @$errors ?>
     </div>
    </div>
  </div>
  <?php endif ?>
  
  <hr style="margin-bottom: 10px">
  
  <?php
  $fieldSchema = $schema[$_REQUEST['fieldName']];
  $records = getUploadRecords($tableName, $_REQUEST['fieldName'], @$_REQUEST['num'], @$_REQUEST['preSaveTempId'], null);
  if ($records):
  $counter = 0;
  ?>
    <div class="row" style="margin-top: 10px">
      <?php foreach ($records as $row): ?>
        <div class="col-xs-4 photobox">
          <div class="thumbnail text-center">
            <?php _showWysiwygUploadPreview($row); ?>
            <div class="small">
            <?php _showLinks($row); ?>
            </div>
          </div>
        </div>
      <?php endforeach ?>
    </div>
  <?php endif; ?>
  <div style="padding: 50px 0px; display: none;" class="noUploads text-center">
    There are no uploads for this field yet.
  </div>
</div>


<script src="//ajax.googleapis.com/ajax/libs/jquery/1.11.3/jquery.min.js"></script><script>window.jQuery || document.write('<script src="<?php echo CMS_ASSETS_URL ?>/3rdParty/jquery-1.11.0.min.js"><\/script>')</script>
<script src="<?php echo CMS_ASSETS_URL ?>/3rdParty/jquery-migrate-1.2.1.min.js"></script>
<script><!--

$(document).ready(function(){
  showHideNoUploadsMessage();
});

function showHideNoUploadsMessage() {

  if ($(".photobox").size() == 0) { $(".noUploads").show(); }
  else                             { $(".noUploads").hide(); }

}

function removeUpload(uploadNum, filename, rowChildEl) {

  // confirm erase
  var confirmMessage = "<?php printf(t("Remove file: %s"), '" +filename+ "') ?>";
  if (!confirm(confirmMessage)) { return; }

  // erase record
  $.ajax({
    url: '?',
    type: "POST",
    data: {
      menu:              $('#menu').val(),
      action:            'uploadErase',
      fieldName:         $('#fieldName').val(),
      uploadNum:         uploadNum,
      num:               $('#num').val(),
      preSaveTempId:     $('#preSaveTempId').val(),
      _CSRFToken:        $('[name=_CSRFToken]').val()
    },
    error:  function(msg){ alert("There was an error sending the request!"); },
    success: function(msg){
      if (msg) { return alert("Error: " + msg); };

      // erase row html
      $(rowChildEl).parents(".photobox").remove();

      showHideNoUploadsMessage();
    }
  });

  return false;
}



function insertUpload(newValue, isImage) {
  var metaFileType = top.tinymce.activeEditor.windowManager.getParams().metaFileType;
  
  // error checking
  if (metaFileType == 'image' && !isImage) {
    alert("You can only insert images in this popup!");
    return false;
  }
  if (metaFileType == 'media' && isImage) {
    alert("You can not insert images in this popup!");
    return false;
  }
  
  // inser image url
  top.tinymce.activeEditor.windowManager.getParams().oninsert(newValue);
  
  // close window
  top.tinymce.activeEditor.windowManager.close();
}

//--></script>


</form>
</body>
</html>
