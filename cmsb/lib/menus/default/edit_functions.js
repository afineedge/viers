
$(document).ready(function(){ init(); });

//
function init() {
  // ***NOTE: If you update ajaxForm in init() also update it in saveRedirectAndReturn() (and vice-versa) //

  // submit form with ajax
  $('FORM').ajaxForm({
    beforeSubmit:  function() {
      // disable any spellcheckers that are active before submiting otherwise we hit a bug that causes that editor to not actually submit its text (within IE only)
      // Note: workaround removed as of v2.15 as this is now fixed in tinymce - http://www.tinymce.com/develop/bugtracker_view.php?id=3167
    },
    success: function(response) {  // post-submit callback - close window
      var recordNum   = 0;  // the record number is returned on success
      var errors      = ''; // anything else is an error message
      if (parseInt(response) == response)  { recordNum = response; }
      else                                 { errors    = response; }

      // show errors
      if (errors.match(/loginSubmit/gi)) { return self.location = "?"; } // redirect to login screen if session expired
      if (errors != '') {
        errors = errors.replace(/\s+$/, ''); // remove trailing nextlines, Chrome 7 displays then as boxes and/or truncates the error message
        return alert(errors);
      }

      // javascript plugin hook
      if (typeof edit_postSave == 'function') {
        var doReturn = edit_postSave(recordNum); // return false to continue or true to return
        if (doReturn) { return true; }
      }

      // redirect or reload page on success
      var url = '';
      if ($('#returnUrl').val()) { url = $('#returnUrl').val(); }
      else                       { url = '?menu=' + $('#menu').val() + '&saved=' + recordNum; } // display default list page
      self.location = url;
      return true;
    },
    // v2.60 - show ajax errors instead of just no output and a message in the js console
    error:  function(XMLHttpRequest, textStatus, errorThrown){
      var error = '';
      //error += "There was an error sending the request!\n";
      error += XMLHttpRequest['status']+" "+XMLHttpRequest['statusText'] + "\n";
      error += XMLHttpRequest['responseText'];
      alert(error);
    }
  });
  
}

function showModal(iframeUrl) {
  $('#iframeModal iframe').css('height', ''); // reset height if it was set by resizeModal() function
  $('#iframeModal iframe').attr('src', iframeUrl);
  $('#iframeModal iframe').on('load', function() {
    $('#iframeModal').modal('show');
    $(this).off('load'); // removes "on load" events of the "#iframeModal iframe" so that modal doesn't get called again after hideModal() is called
  });
}
function hideModal() {
  $('#iframeModal').modal('hide');
}
function resizeModal(newHeight) {
  if (!newHeight) {
    newHeight = 800;
  }
  
  // set max height
  $('#iframeModal iframe').css('max-height', '800px');
  
  // resize modal when "Modify All" uploads is clicked when modifying an upload record
  $('#iframeModal iframe').animate({height:newHeight}, 300);
}

function editCancel() {
  if ($('#returnUrl').val()) { self.location = $('#returnUrl').val(); }
  else                       { self.location = '?menu=' + $('#menu').val(); }
}

// called when preview button is clicked
function editPreview() {
  
  // force any tinyMCE controls to update their form elements
  if (typeof tinyMCE.triggerSave == "function") { tinyMCE.triggerSave(); }

  // build an object of elements to submit by getting jQuery to serialize the existing form
  var params = [];
  var queryString = $('FORM').serialize();
  queryString = queryString.replace(/\+/g, '%20'); // unescape() doesn't play well with +
  var dataPairs = queryString.split('&');
  for (var i = 0; i < dataPairs.length; i++) {
    var keyAndValue = $.map(dataPairs[i].split('='), decodeURIComponent);
    params.push(['preview:' + keyAndValue[0], keyAndValue[1]]);
  }

  // post to url - construct a form on the fly to submit to the previewUrl
  // ... (window.open() won't work because the query string can get insanely long)
  var form = document.createElement('form');
  form.setAttribute('method', 'POST');
  form.setAttribute('target', '_blank');
  form.setAttribute('action', $('#previewUrl').val()); // note that url includes special number 9999999999 getRecords() uses to know this is a preview request
  for (i = 0; i < params.length; i++) {
    var hiddenField = document.createElement('input');
    hiddenField.setAttribute('type', 'hidden');
    hiddenField.setAttribute('name',  params[i][0]);
    hiddenField.setAttribute('value', params[i][1]);
    form.appendChild(hiddenField);
  }
  document.body.appendChild(form);
  form.submit();
  document.body.removeChild(form);
  // end: post to url
}

//
function wysiwygUploadBrowser(callback, value, meta) {
  
  // get editorId
  var editorId  = tinyMCE.activeEditor.id;
  var editorObj = tinyMCE.editors[editorId];
  if (editorObj.settings['fullscreen_is_enabled']) {
    editorId = editorObj.settings['fullscreen_editor_id'];
  }
  var fieldname = editorId.replace(/^field_/, '');

  // get uploadBrowser url
  var uploadBrowserUrl = "?menu=" + escape( $('#menu').val() )
                       + "&action=wysiwygUploads"
                       + "&fieldName="     + escape(fieldname)
                       + "&num="           + escape( $('#num').val() )
                       + "&preSaveTempId=" + escape( $('#preSaveTempId').val() )

  tinymce.activeEditor.windowManager.open({
    title: 'Upload Files',
    url: uploadBrowserUrl,
    width : 590,
    height : 435
  },
  {
    metaFileType: meta.filetype,
    oninsert: function (url) {
      callback(url);
      }
  });
  
  //
  return false;
}

//
function showCreatedByUserPulldown() {

  // get pulldown options
  var options = "1";
  $.ajax({
    url: '?',
    type: "POST",
    data: {
      menu:   $('#menu').val(),
      action: 'ajaxGetUsersAsPulldown'
    },
    error:  function(XMLHttpRequest, textStatus, errorThrown){
      alert("There was an error sending the request! (" +XMLHttpRequest['status']+" "+XMLHttpRequest['statusText'] +")");
    },
    success: function(pulldownHTML){
      $('#createdByUserNumHTML').html(pulldownHTML);
      $('#createdByUserNumChangeLink').hide();
    }
  });
}

//
function updateListFieldOptions(fieldname, newFilterValue) {

  // show "loading..." in the pulldown we're going to update
  $("[name='" +fieldname+ "']").html("<option value=''>Loading...</option>\n");

  // update pulldown options

  // Get the current value of the pulldown we're about to update
  var selectedValue = $("[name='" +fieldname+ "']").val();

  // Uncomment to see what the values discovered/passed are
  //alert("update '" +fieldname+ "' with new filter value '" +newFilterValue+ "' and selected Value: '" +selectedValue+ "'");



  $.ajax({
    // Added false on sync to allow each ajax request to complete before the next one fires off this prevents interference if more than one pulldown depends on a select.change event.
    async: false, // v2.52 - multiple pulldowns updates being triggered by one field sometimes fail with when this is true.
    url: '?',
    type: "POST",
    data: {
      menu:           $('#menu').val(),
      fieldname:      fieldname,
      newFilterValue: newFilterValue,
      selectedValue:  selectedValue,
      action:         'ajaxUpdateListFieldOptions'
    },
    error:  function(XMLHttpRequest, textStatus, errorThrown){
      alert("There was an error sending the request! (" +XMLHttpRequest['status']+" "+XMLHttpRequest['statusText'] +")");
    },
    success: function(response){
      var html = response;
      //window.console && console.log("updateListFieldOptions() ajax success: "+  html);

      // show errors
      if (!html.match(/^<option/)) { alert("Error: " + html); }

      // update single pulldowns
      $("select[name='" +fieldname+ "']").html(html);
      $("select[name='" +fieldname+ "']").change();  // fire change event on this field so any child chained selects will also update

      // update multi-pulldowns
      $("select[name='" +fieldname+ "\\[\\]']").html(html); // for multi-pulldowns
    }
  });




}

// configure uploadify
function generateUploadifyOptions( config ) {
  var fileExt = '*.' + (config['fileExtCSV'].split(',').join(';*.'));
  var newUploadNumsAsCSV = '';
  var errors             = '';
  var onAllCompleteAlreadyFired = false; // workaround a bug where onAllComplete gets called twice if there was an error?!
  var options = {
    'uploader'    : phpConstant('CMS_ASSETS_URL') + '/3rdParty/jqueryPlugins/uploadify/uploadify.swf',
    'script'      : config['script'],
    'cancelImg'   : phpConstant('CMS_ASSETS_URL') + '/3rdParty/jqueryPlugins/uploadify/cancel.png',
    'auto'        : true,
    'multi'       : true,
    'fileExt'     : fileExt,
    'fileDesc'    : config['fileExtCSV'], // this is required for fileExt to work
    'width'       : 400,
    'height'      : 24,
    'hideButton'  : true,
    'wmode'       : 'transparent',
    'queueID'     : config['queueID'],
    'scriptData'  : {
      '_defaultAction'         : 'uploadForm',
      'menu'                   : config['menu'],
      'fieldName'              : config['fieldName'],
      'num'                    : config['num'],
      'preSaveTempId'          : config['preSaveTempId'],
      'submitUploads'          : '1',
      '_action'                : 'uploadForm',
      '_FLASH_UPLOADER_'       : 1,
      '_FLASH_COOKIE_BUG_FIX_' : config['loginDataEncoded'] // pass logindata in _POST to circumvent of flash/session bug
    },
    'onInit' : function() {
      $('#'+config['fieldName']+'_uploadTips').show();
    },
    'onSelect' : function() {
      // now is as good a time as any to reset this
      onAllCompleteAlreadyFired = false;
    },
    'onComplete' : function( event, queueID, fileObj, response, data ) {
    
      // get uploadNums, if present
      var matches = response.match(/&uploadNums=([\d,]+)/);
      if (matches) {
        if (newUploadNumsAsCSV.length > 0) { newUploadNumsAsCSV += ','; }
        newUploadNumsAsCSV += matches[1];
      }
    
      // detect login page (if user was logged out or flash couldn't log in)
      if (response.match(/<h3>Login<\/h3>/)) {
        // get login errors or alerts (if any)
        var matches = response.match(/^<div class="notification.*?<\/a>\s*<div>(.*)<\/div>/m); // match output from _displayNotificationType()
        if (matches) { errors += matches[1]; }
        
        // show message to login again
        return alert("Please login again. (or 'Disable Flash Uploader' under: Admin > General > Advanced Settings).\n\n" + errors);
      }

      // report anything else but <script tags (expected response from ajax) as an error
      else if (!response.match(/^<script/i)) {
        errors += response;
      }

    },
    'onError' : function( event, queueID, fileObj, errorObj ) {
      var filename  = htmlspecialchars(fileObj['name']);
      var errorType = htmlspecialchars(errorObj.type);   // Either: HTTP, IO, Security, File Size
      var errorInfo = htmlspecialchars(errorObj.info);   // An error message describing the type of error returned
      errors += "Error uploading '" +filename+ "', ";
      if      (errorType == 'HTTP')      { errors += "server returned: HTTP " +errorInfo+ "\n"; }
      else if (errorType == 'File Size') { errors += "max filesize of " +errorInfo+ " bytes exceeded\n"; }
      else                               { errors += errorType+" error: '" +errorInfo+ "\n"; }
    },
    'onAllComplete' : function( event, data ) {
      if (onAllCompleteAlreadyFired) { return false; }
      onAllCompleteAlreadyFired = true;

      // reload uploadlist
      if (config['modifyAfterSave']) {
        reloadIframe(config['fieldName']+'_iframe');          // reload uploadlist, don't show errors
      } else {
        reloadIframe(config['fieldName']+'_iframe', errors);  // reload uploadlist, SHOW errors
      }

      if (config['modifyAfterSave']) {
        // v2.51 - get recordNum and preSaveTempId dynamically from page to support Save & Copy
        var recordNum     = $("input[name='num']").val();
        var preSaveTempId = $("input[name='preSaveTempId']").val();
        var targetUrl  = "?action=uploadModify";
        targetUrl     += "&menu="          + config['menu'];
        targetUrl     += "&fieldName="     + config['fieldName'];
        targetUrl     += "&num="           + recordNum;
        targetUrl     += "&preSaveTempId=" + preSaveTempId;
        targetUrl     += "&uploadNums="    + newUploadNumsAsCSV;
        targetUrl     += '&errors='        + escape(errors);

        // modal to add captions, etc.
        showModal(targetUrl);
        //setTimeout(function() { showModal(targetUrl); }, 1000); // use this line instead if your server has timeout issues (and comment above line)
      }

      // reset variables for reuse
      newUploadNumsAsCSV = '';
      errors             = '';

      // finally, clear any errors out of the queue
      $('#' + config['fieldName'] + '_uploadButton').uploadifyClearQueue();
    }
  };
  if (config['maxUploadSizeKB'] > 0) {
    options['sizeLimit'] = config['maxUploadSizeKB'] * 1024;
  }
  return options;
}

// save record (displaying any ajax errors), then redirect to url setting returnUrl so subsequent save redirects back to edit page.
// Note: Replaces returnUrl automatically replaces ### in both urls with current record number (or record number of newly saved record).
// Note: This function is useful for buttons/links below "Related Records" list which need to refer to the current record number, which
//   ... we may not have yet because we haven't yet saved the record
function saveRedirectAndReturn(redirectUrl) {
// ***NOTE: If you update ajaxForm in init() also update it in saveRedirectAndReturn() (and vice-versa) //

  // submit form with ajax
  // tinyMCE.triggerSave(); // uncomment if this is needed in future - force any tinyMCE controls to update their form elements
  $('FORM').ajaxSubmit({
    success: function(response) {  // post-submit callback - close window
      var recordNum   = 0;  // the record number is returned on success
      var errors      = ''; // anything else is an error message
      if (parseInt(response) == response)  { recordNum = response; }
      else                                 { errors    = response; }

      // show errors
      if (errors.match(/loginSubmit/gi)) { return self.location = "?"; } // redirect to login screen if session expired
      if (errors != '') {
        errors = errors.replace(/\s+$/, ''); // remove trailing nextlines, Chrome 7 displays then as boxes and/or truncates the error message
        return alert(errors);
      }

      // redirect on success
      redirectUrl   = redirectUrl.replace('###', recordNum);                 // insert record number into 'redirectUrl' (tableNameNum isn't encoded)
      redirectUrl   = redirectUrl.replace(escape('###'), recordNum);         // insert record number into 'redirectUrl' (this is just to catch all cases)
      redirectUrl   = redirectUrl.replace(escape(escape('###')), recordNum); // insert record number into 'redirectUrl' (returnUrl is double encoded)
      self.location = redirectUrl;
      return true;
    }
  });

}

