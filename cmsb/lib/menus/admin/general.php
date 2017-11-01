<?php

// prepare adminUI() placeholders
$adminUI = [];

// page title
$adminUI['PAGE_TITLE'] = [ t('Admin'), t('General Settings') => '?menu=admin&action=general' ];

// buttons
$adminUI['BUTTONS'] = [];
$adminUI['BUTTONS'][] = [ 'name' => 'action=adminSave', 'label' => t('Save'),   ];
$adminUI['BUTTONS'][] = [ 'name' => 'action=general',   'label' => t('Cancel'), ];

// form tag and hidden fields
$adminUI['FORM'] = [ 'autocomplete' => 'off' ];
$adminUI['HIDDEN_FIELDS'] = [
  [ 'name' => 'menu',           'value' => 'admin', ],
  [ 'name' => '_defaultAction', 'value' => 'adminSave', ],
];

// main content
$adminUI['CONTENT'] = ob_capture('_getContent');

// add extra html before form
$adminUI['PRE_FORM_HTML'] = ob_capture('_getPreFormContent');

// add extra html after the form
$adminUI['POST_FORM_HTML'] = ob_capture('_getPostFormContent');

// compose and output the page
adminUI($adminUI);

// check is Suhosin is detected on server.  Returns version found or false
function _suhosinVersionDetected() {
  static $suhosin_detected, $suhosin_detected_version, $isCached;
  if (isset($isCached)) { return $suhosin_detected_version; } // caching
  $isCached = true;

  // get phpinfo() content
  ob_start(); @phpinfo(INFO_GENERAL); $phpinfo_general = ob_get_contents(); ob_end_clean();
  ob_start(); @phpinfo(INFO_MODULES); $phpinfo_modules = ob_get_contents(); ob_end_clean();
  $phpinfo_general      = preg_replace("/&nbsp;/i", " ", $phpinfo_general);
  $phpinfo_modules_text = strip_tags($phpinfo_modules);

  // suhosin detection
  $suhosin_in_phpinfo_general = (preg_match("/(Suhosin( Patch)? [\d\.]+)/i", $phpinfo_general, $matches) ? $matches[0] : '');
  $suhosin_in_phpinfo_modules = (preg_match("/(Suhosin.*?[0-9][\d\.]+)/i", $phpinfo_modules_text, $matches) ? $matches[0] : '');
  $suhosin_ini                = @ini_get_all('suhosin');
  $suhosin_ini_get_all_count  = $suhosin_ini ? count($suhosin_ini) : 0;
  $suhosin_funcs_as_csv       = @implode(', ', get_extension_funcs('suhosin'));
  $suhosin_extension_loaded   = extension_loaded('suhosin');   // http://stackoverflow.com/questions/3383916/how-to-check-whether-suhosin-is-installed
  $suhosin_patch_constant     = @constant("SUHOSIN_PATCH");    // http://stackoverflow.com/questions/3383916/how-to-check-whether-suhosin-is-installed
  // future: Check for Suhosin easter egg image: any_php_file.php?=SUHO8567F54-D428-14d2-A769-00DA302A5F18
  $suhosin_detected           = $suhosin_in_phpinfo_general || $suhosin_in_phpinfo_modules || $suhosin_ini_get_all_count || $suhosin_extension_loaded || $suhosin_patch_constant;
  $suhosin_detected_version  = coalesce($suhosin_in_phpinfo_general, $suhosin_in_phpinfo_modules, 'Suhosin');

  // print suhosin debug data
  $debug = false;
  if ($debug) {
    print "phpinfo(INFO_GENERAL) found string: $suhosin_in_phpinfo_general\n";
    print "phpinfo(INFO_MODULES) found string: $suhosin_in_phpinfo_modules\n";
    print "ini_get_all('suhosin'): $suhosin_ini_get_all_count values\n";
    print "get_extension_funcs('suhosin'): $suhosin_funcs_as_csv\n";
    print "extension_loaded('suhosin'): $suhosin_extension_loaded\n";
    print "defined('SUHOSIN_PATCH'): " . defined('SUHOSIN_PATCH') . "\n";
    print "constant('SUHOSIN_PATCH'): $suhosin_patch_constant\n";
  }

  return $suhosin_detected ? $suhosin_detected_version : false;
}

//
function _getPreFormContent() {
  
  ### SHOW OLD PHP/MYSQL WARNINGS
  $currentPhpVersion   = phpversion();
  $currentMySqlVersion = preg_replace("/[^0-9\.]/", '', mysqli()->server_info);
 
  // Reference - PHP Installed Versions: https://wordpress.org/about/stats/
  // Reference - PHP Installed Versions: https://w3techs.com/technologies/details/pl-php/all/all
  if     (time() > strtotime('2018-12-31')) { $nextPhpRequired = '7.0'; } // PHP 5.6 Security Support ends on 31 Dec 2018: http://php.net/supported-versions.php
  else                                      { $nextPhpRequired = '5.6'; } // Default to minimum version required, PHP v5.6

  $nextMySqlRequired   = '5.5'; // to support utf8mb4 : http://dev.mysql.com/doc/refman/5.5/en/charset-unicode-utf8mb4.html
  $isPhpUnsupported    = version_compare($currentPhpVersion, $nextPhpRequired) < 0;
  $isMySqlUnsupported  = version_compare($currentMySqlVersion, $nextMySqlRequired) < 0;
  $isSecurityIssue     = ($isPhpUnsupported || $isMySqlUnsupported);

  // Check for missing or soon to be required extensions
  $missingExtensions   = array();
  foreach (array('mysqli','openssl','curl') as $extension) {
    if (!extension_loaded($extension)) { $missingExtensions[] = $extension; }
  }
  
  if ($isSecurityIssue || $missingExtensions) {
    ?>
    <div style='color: #C00; border: solid 2px #C00; padding: 8px; background: #FFF; font-size: 14px; line-height: 1.3'>
      <?php if ($isSecurityIssue): ?>
        <b>Security Notice:</b>
        You are currently running old and unsupported server software that <b>no longer receives security updates</b>.
        To avoid being exposed to unpatched security vulnerabilities and to ensure compatibility with future CMS releases, please upgrade at your earliest convenience.<br/>
      <?php else: ?>
        <b>Upgrade Warning:</b>
        You are currently missing some required PHP extensions.
        To ensure compatibility with future CMS releases, please have these extensions installed at your earliest convenience.<br/>
      <?php endif ?>

      <div style="padding: 5px 5px 5px 25px;">
        <?php if ($isPhpUnsupported): ?>
          <li>Upgrade to <b>PHP v<?php echo $nextPhpRequired ?></b> or newer (Your server is running PHP v<?php echo $currentPhpVersion ?>)
        <?php endif ?>
        <?php if ($isMySqlUnsupported): ?>
          <li>Upgrade to <b>MySQL v<?php echo $nextMySqlRequired ?></b> or newer (Your server is running MySQL v<?php echo $currentMySqlVersion ?>)
        <?php endif ?>
        <?php foreach ($missingExtensions as $extension): ?>
          <li>Install missing PHP extension: <b><?php echo htmlencode($extension); ?></b> (required for future updates)
        <?php endforeach ?>
      </div>

      <?php if ($isSecurityIssue): ?>
        More information:
        <a href="http://php.net/supported-versions.php" target="_blank">PHP Supported Versions</a>,
        <a href="http://en.wikipedia.org/wiki/MySQL#Versions" target="_blank">MySQL Supported Versions</a>
      <?php endif ?>
    </div><br/>
    <?php
  }

}

function _getPostFormContent() {
  ?>
    <script>
    
      // 
      function updateDatePreviews() {
        var url = "?menu=admin&action=updateDate";
        url    += "&timezone=" + escape( $('#timezone').val() );

        $.ajax({
          url: url,
          dataType: 'json',
          error:   function(XMLHttpRequest, textStatus, errorThrown){
            alert("There was an error sending the request! (" +XMLHttpRequest['status']+" "+XMLHttpRequest['statusText'] + ")\n" + errorThrown);
          },
          success: function(json){
            var error = json[2];
            if (error) { return alert(error); }
            $('#localDate').html(json[0]);
            $('#mysqlDate').html(json[1]);
            //$('#localDate, #mysqlDate').attr('style', 'background-color: #FFFFCC');
          }
        });
      }

    </script>
  <?php
}


function _getContent() {
  global $SETTINGS, $APP, $CURRENT_USER, $TABLE_PREFIX;
  
  // get ulimit limits
  list($maxCpuSeconds, $memoryLimitKbytes, $maxProcessLimit, $ulimitOutput) = getUlimitValues('soft');
  if     ($maxCpuSeconds == '')          { $maxCpuSeconds_formatted = t('none'); }
  elseif ($maxCpuSeconds == 'unlimited') { $maxCpuSeconds_formatted = t('unlimited'); }
  else                                   { $maxCpuSeconds_formatted = "$maxCpuSeconds " . t('seconds'); }
  if     ($memoryLimitKbytes == '')          { $memoryLimit_formatted = t('none'); }
  elseif ($memoryLimitKbytes == 'unlimited') { $memoryLimit_formatted = t('unlimited'); }
  else                                       { $memoryLimit_formatted = formatBytes($memoryLimitKbytes*1024); }
  $ulimitLink = "?menu=admin&amp;action=ulimit"; 
  
  // calculate disk space
  $thisDir    = __DIR__;
  $totalBytes = @disk_total_space($thisDir);
  $freeBytes  = @disk_free_space($thisDir);

  ?>
  <script>
    // redirect old links to sections that have moved elsewhere
    if (location.hash == '#background-tasks') { window.location = '?menu=admin&action=bgtasks'; }       // redirect ?menu=admin&action=general#background-tasks
    if (location.hash == '#email-settings')   { window.location = '?menu=admin&action=email'; }          // redirect ?menu=admin&action=general#email-settings
    if (location.hash == '#backup-restore')   { window.location = '?menu=admin&action=backuprestore'; }  // redirect ?menu=admin&action=general#backup-restore
  </script>



      <?php echo adminUI_separator([
          'label' => t('License Information'),
          'href'  => "?menu=admin&action=general#license-info",
          'id'    => "license-info",
        ]);
      ?>

    <div class="form-horizontal">

      <div class="form-group">
        <div class="col-sm-3 control-label"><?php et('Program Name');?></div>
        <div class="col-sm-8 form-control-static">
          <?php echo htmlencode($SETTINGS['programName']) ?>
          v<?php echo htmlencode($APP['version']) ?> (Build <?php echo htmlencode($APP['build']) ?>)
        </div>
      </div>

      <div class="form-group">
        <div class="col-sm-3 control-label"><?php et('Vendor'); ?></div>
        <div class="col-sm-8 form-control-static">
          <a href="<?php echo htmlencode($SETTINGS['vendorUrl']) ?>"><?php echo htmlencode($SETTINGS['vendorName']) ?></a>
        </div>
      </div>

      <div class="form-group">
        <label class="col-sm-3 control-label"><?php et('License Agreement');?></label>
        <div class="col-sm-8 form-control-static">
            <a href="?menu=license"><?php et('License Agreement');?> &gt;&gt;</a>
        </div>
      </div>

      <div class="form-group">
        <label class="col-sm-3 control-label" for="licenseCompanyName">
          <?php et('License Company Name');?>
        </label>
        <div class="col-sm-8">
          <input class="form-control" type="text" name="licenseCompanyName" id="licenseCompanyName" value="<?php echo htmlencode($SETTINGS['licenseCompanyName']) ?>" />
        </div>
      </div>
      <div class="form-group">
        <label class="col-sm-3 control-label" for="licenseDomainName">
          <?php et('License Domain Name');?>
        </label>
        <div class="col-sm-8">
            <input class="form-control text-input medium-input setAttr-spellcheck-false" type="text" name="licenseDomainName" id="licenseDomainName" value="<?php echo htmlencode(@$SETTINGS['licenseDomainName']); ?>" spellcheck="false" />
        </div>
      </div>




      <?php echo adminUI_separator([
          'label' => t('Directories & URLs'),
          'href'  => "?menu=admin&action=general#dirs-urls",
          'id'    => "dirs-urls",
        ]);
      ?>

      <div class="form-group">
        <label class="col-sm-3 control-label" for="null">
          <?php et('Program Directory');?>
        </label>
        <div class="col-sm-8">
          <input class="form-control" type="text" name="null" id="null" value="<?php echo htmlencode($GLOBALS['PROGRAM_DIR']) ?>/" />
        </div>
      </div>
      <div class="form-group">
        <label class="col-sm-3 control-label" for="adminUrl">
          <?php et('Program Url');?>
        </label>
        <div class="col-sm-8">
            <input class="form-control" type="text" name="adminUrl" id="adminUrl" value="<?php echo htmlencode(@$SETTINGS['adminUrl']) ?>" />
        </div>
      </div>
      <div class="form-group">
        <label class="col-sm-3 control-label" for="webRootDir">
          <?php et('Website Root Directory');?>
        </label>
        <div class="col-sm-8">
          <input class="form-control" type="text" name="webRootDir" id="webRootDir" value="<?php echo htmlencode(@$SETTINGS['webRootDir']) ?>" />
        </div>
      </div>
      <div class="form-group">
        <label class="col-sm-3 control-label" for="uploadDir">
          <?php et('Upload Directory');?>
        </label>
        <div class="col-sm-8">
          <input class="form-control" type="text" name="uploadDir" id="uploadDir" value="<?php echo htmlencode(@$SETTINGS['uploadDir']) ?>" onkeyup="updateUploadPathPreviews('dir', this.value, 0)" onchange="updateUploadPathPreviews('dir', this.value, 0)" />
          <p><?php et('Preview:'); ?> <code id="uploadDirPreview"><?php echo htmlencode(getUploadPathPreview('dir', $SETTINGS['uploadDir'], false, false)); ?></code></p>
          <p><?php et('Example:'); ?> <code>uploads</code> or <code>../uploads</code> (relative to program directory)</p>
        </div>
      </div>
      <div class="form-group">
        <label class="col-sm-3 control-label" for="uploadUrl">
          <?php et('Upload Folder URL');?>
        </label>
        <div class="col-sm-8">
          <input class="form-control" type="text" name="uploadUrl" id="uploadUrl" value="<?php echo htmlencode(@$SETTINGS['uploadUrl']) ?>" onkeyup="updateUploadPathPreviews('url', this.value, 0)" onchange="updateUploadPathPreviews('url', this.value, 0)" />
          <p><?php et('Preview:'); ?> <code id="uploadUrlPreview"><?php echo htmlencode(getUploadPathPreview('url', $SETTINGS['uploadUrl'], false, false)); ?></code></p>
          <p><?php et('Example:'); ?> <code>uploads</code> or <code>../uploads</code> (relative to current URL)</p>
        </div>
      </div>
      <div class="form-group">
        <div class="col-sm-3 control-label">
          Server Upload Settings
        </div>
        <div class="col-sm-8">
          <div class="table-wrap">
          <table class="table table-bordered" id="sample-table-1">
            <thead>
              <tr>
                <th><?php et("Upload settings"); ?></th>
                <th><?php et("Upload time limits"); ?></th>
                <th><?php et("File size limits") ?></th>
              </tr>
            </thead>
            <tbody>
              <tr>
                <td><a href="http://php.net/manual/en/ini.core.php#ini.file-uploads" target="_blank">file_uploads</a>: <?php echo ini_get('file_uploads') ? t('enabled') : t('disabled'); ?></td>
                <td><a href="http://php.net/manual/en/info.configuration.php#ini.max-input-time" target="_blank">max_input_time</a>: <?php echo ini_get('max_input_time') ?></td>
                <td><a href="http://php.net/manual/en/function.disk-free-space.php" target="_blank">free disk space</a>: <?php echo $freeBytes ? formatBytes($freeBytes, 0) : t("Unavailable"); ?></td>
              </tr>
              <tr>
                <td><a href="http://php.net/manual/en/ini.core.php#ini.max-file-uploads" target="_blank">max_file_uploads</a>: <?php echo ini_get('max_file_uploads') ?></td>
                <td><a href="http://php.net/manual/en/info.configuration.php#ini.max-execution-time" target="_blank">max_execution_time</a>: <?php echo ini_get('max_execution_time') ?></td>
                <td><a href="http://php.net/manual/en/ini.core.php#ini.post-max-size" target="_blank">post_max_size</a>: <?php echo ini_get('post_max_size') ?></td>
              </tr>
              <tr>
                <td><a href="http://php.net/manual/en/ini.core.php#ini.upload-tmp-dir" target="_blank">upload_tmp_dir</a>: <?php echo ini_get('upload_tmp_dir'); ?></td>
                <td><a href="<?php echo $ulimitLink ?>" target="_blank">ulimit max cpu seconds</a>: <?php echo $maxCpuSeconds_formatted; ?></td>
                <td><a href="http://php.net/manual/en/ini.core.php#ini.upload-max-filesize" target="_blank">upload_max_filesize</a>: <?php echo ini_get('upload_max_filesize') ?></td>
              </tr>
              <tr>
                <td></td>
                <td></td>
                <td><a href="http://php.net/manual/en/ini.core.php#ini.memory-limit" target="_blank">memory_limit</a>: <?php echo ini_get('memory_limit') ?></td>
              </tr>
              <tr>
                <td></td>
                <td></td>
                <td><a href="<?php echo $ulimitLink ?>" target="_blank">ulimit memory limit</a>: <?php echo $memoryLimit_formatted; ?></td>
              </tr>
            </tbody>
            <tfoot>
              <tr>
                <th colspan="3">
                  <a href="http://www.php.net/manual/en/features.file-upload.php" target="_blank"><?php et('How to configure PHP uploads')?></a>
                  (<?php et('for server admins')?>)
                </th>
              </tr>
            </tfoot>
          </table>
          </div>
        </div>
      </div>
      <div class="form-group">
        <label class="col-sm-3 control-label" for="webPrefixUrl">
          <?php et('Website Prefix URL (optional)');?>
        </label>
        <div class="col-sm-8">
          <input class="form-control" type="text" name="webPrefixUrl" id="webPrefixUrl" value="<?php echo htmlencode(@$SETTINGS['webPrefixUrl']) ?>" />
          eg: <code><?php eht("eg: /~username or /development/client-name"); ?></code>
          <p>If your development server uses a different URL prefix than your live server you can specify it here. This prefix will be automatically added to Viewer URLs and can be displayed with <code>&lt;?php echo PREFIX_URL ?&gt;</code> for other urls. This will allow you to easily move files between a development and live server, even if they have different URL prefixes.</p>
        </div>
      </div>


      <div class="form-group">
        <label class="col-sm-3 control-label" for="helpUrl">
          <?php et('Help (?) URL') ?>
        </label>
        <div class="col-sm-8">
          <input name="helpUrl" type="text" id="helpUrl" class="form-control" value="<?php echo htmlencode($SETTINGS['helpUrl']); ?>" />
        </div>
      </div>
      <div class="form-group">
        <label class="col-sm-3 control-label" for="websiteUrl">
          <?php et("'View Website' URL") ?>
        </label>
        <div class="col-sm-8">
          <input name="websiteUrl" type="text" id="websiteUrl" class="form-control" value="<?php echo htmlencode($SETTINGS['websiteUrl']) ?>" />
        </div>
      </div>



      <?php echo adminUI_separator([
          'label' => t('Regional Settings'),
          'href'  => "?menu=admin&action=general#regional-settings",
          'id'    => "regional-settings",
        ]);
      ?>

      <div class="form-group">
        <label class="col-sm-3 control-label" for="timezone">
          <?php et('Timezone Name');?>
        </label>
        <div class="col-sm-8">
          <?php $timeZoneOptions = getTimeZoneOptions($SETTINGS['timezone']); ?>
          <select name="timezone" id="timezone" class="form-control" onchange="updateDatePreviews();">
            <?php echo $timeZoneOptions; ?>
          </select>
        </div>
      </div>
      <div class="form-group">
        <div class="col-sm-3 control-label">
          <?php et('Local Time');?>
        </div>
        <div class="col-sm-8">
          <div class="form-control">
            <?php
            $offsetSeconds = date("Z");
            $offsetString  = convertSecondsToTimezoneOffset($offsetSeconds);
            $localDate = date("D, M j, Y - g:i:s A") . " ($offsetString)";
            echo $localDate;
            ?>
          </div>
        </div>
      </div>
      <div class="form-group">
        <div class="col-sm-3 control-label">
          <?php et('MySQL Time');?>
        </div>
        <div class="col-sm-8">
          <div class="form-control">
            <?php
            list($mySqlDate, $mySqlOffset) = mysql_get_query("SELECT NOW(), @@session.time_zone", true);
            echo date("D, M j, Y - g:i:s A", strtotime($mySqlDate)) . " ($mySqlOffset)";
            ?>
          </div>
        </div>
      </div>
      <?php if (!@$SETTINGS['advanced']['hideLanguageSettings']): ?>
      <div class="form-group">
        <label class="col-sm-3 control-label" for="language">
          <?php et('Program Language');?>
        </label>
        <div class="col-sm-8">
          <?php // load language file names - do this here so errors are visible and not hidden in select tags
            $programLange   = array(); // key = filename without ext, value = selected boolean
            $programLangDir = "{$GLOBALS['PROGRAM_DIR']}/lib/languages/";
            foreach (scandir($programLangDir) as $filename) {
              @list($basename, $ext) = explode(".", $filename, 2);
              if ($ext != 'php') { continue; }
              if (preg_match("/^_/", $basename)) { continue; } // skip internal scripts
              $programLangs[$basename] = 1;
            }
          ?>
          <select name="language" id="language" class="form-control"><?php // 2.50 the ID is used for direct a-name links ?>
          <option value=''>&lt;select&gt;</option>
          <option value='' <?php selectedIf($SETTINGS['language'], ''); ?>>default</option>
            <?php
              foreach (array_keys($programLangs) as $lang) {
                $selectedAttr = $lang == $SETTINGS['language'] ? 'selected="selected"' : '';
                print "<option value=\"$lang\" $selectedAttr>$lang</option>\n";
              }
            ?>
          </select>
          <?php print sprintf(t('Languages are in %s'),'<code>/lib/languages/</code> or <code>/plugins/.../languages/</code>') ?>
        </div>
      </div>
      <div class="form-group">
        <label class="col-sm-3 control-label" for="wysiwygLang">
          <?php et('WYSIWYG Language');?>
        </label>
        <div class="col-sm-8">
          <?php // load language file names - do this here so errors are visible and not hidden in select tags
            $wysiwygLangs   = array(); // key = filename without ext, value = selected boolean
            $wysiwygLangDir = "{$GLOBALS['CMS_ASSETS_DIR']}/3rdParty/TinyMCE4/langs/";
            foreach (scandir($wysiwygLangDir) as $filename) {
              @list($basename, $ext) = explode(".", $filename, 2);
              if ($ext != 'js') { continue; }
              $wysiwygLangs[$basename] = 1;
            }
          ?>
          <select name="wysiwygLang" id="wysiwygLang" class="form-control">
          <option value="en">&lt;select&gt;</option>
            <?php
              foreach (array_keys($wysiwygLangs) as $lang) {
                $selectedAttr = $lang == $SETTINGS['wysiwyg']['wysiwygLang'] ? 'selected="selected"' : '';
                print "<option value=\"$lang\" $selectedAttr>$lang</option>\n";
              }
            ?>
          </select>
          <a href="http://tinymce.moxiecode.com/download_i18n.php" target="_BLANK"><?php eht("Download more languages..."); ?></a>
        </div>
      </div>
      <div class="form-group">
        <div class="col-sm-3 control-label">
          <?php et('Developer Mode');?>
        </div>
        <div class="col-sm-8">
          <?php echo adminUI_checkbox([
            'name'    => 'languageDeveloperMode',
            'label'   => t("Automatically add new language strings to language files"),
            'checked' => $SETTINGS['advanced']['languageDeveloperMode'],
          ]) ?>
        </div>
      </div>
      <?php endif ?>
      <div class="form-group">
        <label class="col-sm-3 control-label" for="dateFormat">
          <?php et('Date Field Format');?>
        </label>
        <div class="col-sm-8">
          <select name="dateFormat" id="dateFormat" class="form-control">
            <option value=''>&lt;select&gt;</option>
            <option value='' <?php selectedIf($SETTINGS['dateFormat'], '') ?>>default</option>
            <option value="dmy" <?php selectedIf($SETTINGS['dateFormat'], 'dmy') ?>>Day Month Year</option>
            <option value="mdy" <?php selectedIf($SETTINGS['dateFormat'], 'mdy') ?>>Month Day Year</option>
          </select>
        </div>
      </div>

      <?php echo adminUI_separator([
          'label' => t('Advanced Settings'),
          'href'  => "?menu=admin&action=general#advanced-settings",
          'id'    => "advanced-settings",
        ]);
      ?>

      <div class="form-group">
        <label class="col-sm-3 control-label" for="imageResizeQuality">
          <?php et('Image Resizing Quality');?>
        </label>
        <div class="col-sm-8">
          <select name="imageResizeQuality" id="imageResizeQuality" class="form-control">
            <option value="65" <?php selectedIf($SETTINGS['advanced']['imageResizeQuality'], '65'); ?>><?php et('Minimum - Smallest file size, some quality loss')?></option>
            <option value="80" <?php selectedIf($SETTINGS['advanced']['imageResizeQuality'], '80'); ?>><?php et('Normal - Good balance of quality and file size')?></option>
            <option value="90" <?php selectedIf($SETTINGS['advanced']['imageResizeQuality'], '90'); ?>><?php et('High - Larger file size, high quality')?></option>
            <option value="100" <?php selectedIf($SETTINGS['advanced']['imageResizeQuality'], '100'); ?>><?php et('Maximum - Very large file size, best quality')?></option>
          </select>
        </div>
      </div>
      <div class="form-group">
        <div class="col-sm-3 control-label">
          <?php et('WYSIWYG Options');?>
        </div>
        <div class="col-sm-8">
          <?php echo adminUI_checkbox([
            'name'    => 'includeDomainInLinks',
            'label'   => t('Save full URL for local links and images (for viewers on other domains)'),
            'checked' => $SETTINGS['wysiwyg']['includeDomainInLinks'],
          ]) ?>
        </div>

        <div class="col-sm-3 control-label">
          <?php et('Code Generator');?>
        </div>
        <div class="col-sm-8">
          <?php echo adminUI_checkbox([
            'name'    => 'codeGeneratorExpertMode',
            'label'   => t('Expert mode - don\'t show instructions or extra html in Code Generator output'),
            'checked' => @$SETTINGS['advanced']['codeGeneratorExpertMode'],
          ]) ?>
        </div>

        <div class="col-sm-3 control-label">
          <?php et('File Uploads');?>
        </div>
        <div class="col-sm-8">
          <?php echo adminUI_checkbox([
            'name'    => 'disableFlashUploader',
            'label'   => t('Disable Flash Uploader - attach one file at a time (doesn\'t require flash) - <a href="http://helpx.adobe.com/flash-player.html" target="_blank">Check if Flash is installed</a>'),
            'checked' => @$SETTINGS['advanced']['disableFlashUploader'],
          ]) ?>
        </div>

        <div class="col-sm-3 control-label">
          <?php et('Menu Options');?>
        </div>
        <div class="col-sm-8">
          <?php echo adminUI_checkbox([
            'name'    => 'showExpandedMenu',
            'label'   => t("Always show expanded menu (don't hide unselected menu groups)"),
            'checked' => $SETTINGS['advanced']['showExpandedMenu'],
          ]) ?>
        </div>

        <?php if (array_key_exists('showExpandedMenu', $CURRENT_USER)): ?>
          <div class="col-sm-3 control-label">
            <?php et('Updated');?>
          </div>
          <div class="col-sm-8">
            <?php et("This option is now being ignored and being set on a per user basis with the 'showExpandedMenu' field in")?> <a href="?menu=accounts"><?php et('User Accounts')?></a>.
          </div>
        <?php endif ?>

        <div class="col-sm-3 control-label">
          <?php et('Use Datepicker');?>
        </div>
        <div class="col-sm-8">
          <?php echo adminUI_checkbox([
            'name'    => 'useDatepicker',
            'label'   => t("Display datepicker icon and popup calendar beside date fields"),
            'checked' => $SETTINGS['advanced']['useDatepicker'],
          ]) ?>
        </div>


        <?php
          $isLegacyMysqlAvailable = extension_loaded('mysql');
          $legacyMySQLClass       = $isLegacyMysqlAvailable ? '' : 'text-muted form-control-static';
        ?>

        <div class="col-sm-3 control-label <?php echo $legacyMySQLClass ?>">
          <?php et('Legacy MySQL Support');?>
        </div>
        <div class="col-sm-8  <?php echo $legacyMySQLClass ?>">
          <?php if ($isLegacyMysqlAvailable): ?>
            <?php echo adminUI_checkbox([
              'name'    => 'legacy_mysql_support',
              'label'   => t("Connect to legacy PHP MySQL library to support old code (doubles required MySQL connections)"),
              'checked' => $SETTINGS['advanced']['legacy_mysql_support'],
            ]); ?>
          <?php else: ?>
            <?php echo t("This feature is not available because your server doesn't have the PHP MySQL extension loaded."); ?>
          <?php endif ?>
        </div>

      </div>
      <div class="form-group">
        <label class="col-sm-3 control-label" for="session_save_path">
          <?php et('session.save_path');?>
        </label>
        <div class="col-sm-8">
          <input class="text-input wide-input form-control" type="text" name="session_save_path" id="session_save_path" value="<?php echo htmlencode(@$SETTINGS['advanced']['session_save_path']) ?>" size="60" />
          <?php et("If your server is expiring login sessions too quickly set this to a new directory outside of your web root or leave blank for default value of:"); ?> <code><?php echo htmlencode(get_cfg_var('session.save_path')); ?></code>
        </div>
      </div>
      <div class="form-group">
        <label class="col-sm-3 control-label" for="session_cookie_domain">
          <?php et('session.cookie_domain');?>
        </label>
        <div class="col-sm-8">
          <input class="text-input wide-input form-control" type="text" name="session_cookie_domain" id="session_cookie_domain" value="<?php echo htmlencode(@$SETTINGS['advanced']['session_cookie_domain']) ?>" size="60" />
          <?php et("To support multiple subdomains set to parent domain (eg: example.com), or leave blank to default to current domain."); ?>
        </div>
      </div>

      <?php echo adminUI_separator([
          'label' => t('Security Settings'),
          'href'  => "?menu=admin&action=general#security-settings",
          'id'    => "security-settings",
        ]);
      ?>

      <div class="form-group">
        <div class="col-sm-3 control-label">
          <?php eht('Login Timeouts'); ?>
        </div>
        <div class="col-sm-8">
          <div class="form-inline">
            <div class="form-group">
              <?php et("Automatically expire login sessions after"); ?>
              <input type="text" class="form-control" name="login_expiry_limit" value="<?php echo htmlencode(@$SETTINGS['advanced']['login_expiry_limit']) ?>" maxlength="4" />
              <select name="login_expiry_unit" class="form-control"><?php echo getSelectOptions(@$SETTINGS['advanced']['login_expiry_unit'], array('minutes','hours','days','months'), array(t('minutes'),t('hours'),t('days'),t('months'))); ?></select>
            </div>
          </div>
        </div>

        <div class="col-sm-3 control-label">
          <?php eht('Hide PHP Errors'); ?>
        </div>
        <div class="col-sm-8">
          <?php echo adminUI_checkbox([
            'name'    => 'phpHideErrors',
            'label'   => t("Hide all PHP errors and warnings (still logged to <a href='?menu=_error_log'>developer log</a>)"),
            'checked' => $SETTINGS['advanced']['phpHideErrors'],
          ]) ?>
        </div>

        <div class="col-sm-3 control-label">
          <?php eht('Email PHP Errors'); ?>
        </div>
        <div class="col-sm-8">
          <?php echo adminUI_checkbox([
            'name'    => 'phpEmailErrors',
            'label'   => t("When <a href='?menu=_error_log'>php errors</a> are detected send admin a <a href='?menu=_email_templates'>notification email</a>"),
            'checked' => $SETTINGS['advanced']['phpEmailErrors'],
          ]) ?>
        </div>

        <div class="col-sm-3 control-label">
          <?php eht('Check Referer'); ?>
        </div>
        <div class="col-sm-8">
          <?php echo adminUI_checkbox([
            'name'    => 'checkReferer',
            'label'   => t("Warn on external referers/links and require internal referer to submit data to CMS."),
            'checked' => $SETTINGS['advanced']['checkReferer'],
          ]) ?>
        </div>

        <div class="col-sm-3 control-label">
          <?php eht('Disable Autocomplete'); ?>
        </div>
        <div class="col-sm-8">
          <?php echo adminUI_checkbox([
            'name'    => 'disableAutocomplete',
            'label'   => t("Attempt to disable autocomplete functionality in browsers to prevent storing of usernames and passwords."),
            'checked' => $SETTINGS['advanced']['disableAutocomplete'],
          ]) ?>
        </div>

        <div class="col-sm-3 control-label">
          <?php eht('Require HTTPS'); ?>
        </div>
        <div class="col-sm-8">
          <?php echo adminUI_checkbox([
            'name'    => 'requireHTTPS',
            'label'   => t("Only allow users to login via secure HTTPS connections"),
            'checked' => $SETTINGS['advanced']['requireHTTPS'],
          ]) ?>
        </div>

        <div class="col-sm-3 control-label">
          <?php eht('Restrict IP Access'); ?>
        </div>
        <div class="col-sm-8">
          <?php echo adminUI_checkbox([
            'name'    => 'restrictByIP',
            'label'   => sprintf(t("Only allow users to login from these IP addresses.  eg: 1.2.3.4, 4.4.4.4 (Your IP is: %s)"), $_SERVER['REMOTE_ADDR']),
            'checked' => $SETTINGS['advanced']['restrictByIP'],
          ]) ?>
          <div style="padding-left: 25px">
            <input class="text-input form-control" type="text" name="restrictByIP_allowed" value="<?php echo htmlencode(@$SETTINGS['advanced']['restrictByIP_allowed']) ?>" size="30" />
          </div>
        </div>
      </div>

      <div class="form-group">
        <div class="col-sm-3 control-label">
          <?php eht('Security Tips'); ?>
        </div>
        <div class="col-sm-8 form-control-static">
          <div class="text-left">
            <?php
              $tips = array();
              $errorLogCount = mysql_count('_error_log');
              if (!isHTTPS())                                       { $tips[] = t("Use a secure https:// url to access this program.  You are currently using an insecure connection."); }
              if (!$SETTINGS['advanced']['requireHTTPS'])           { $tips[] = t("Enable 'Require HTTPS' above to disallow insecure connections."); }
              if (ini_get('display_errors'))                        { $tips[] = t("Hide PHP Errors (for production and live web servers)."); }
              if (!$SETTINGS['advanced']['phpEmailErrors'])         { $tips[] = t("Enable 'Email PHP Errors' to be notified of PHP errors on website."); }
              if (ini_get('expose_php'))                            { $tips[] = t(sprintf("%s is currently enabled, disable it in php.ini.", '<a href="http://www.php.net/manual/en/ini.core.php#ini.expose-php">expose_php</a>')); }
              if ($errorLogCount)                                   { $tips[] = t("There are PHP errors in the <a href='?menu=_error_log'>developer log</a>.  Review them and then clear the developer log."); }
              if (loginExpirySeconds() > (60*30))                   { $tips[] = t("Set login timeout to 30 minutes or less."); }
              if (!array_key_exists('CMSB_MOD_SECURITY2', $_SERVER)) { // mod_security2 reports false positives that are excluded for scripts named admin.php, so don't recommend this setting for hosts mod_security2 hosts
                if (basename($_SERVER['SCRIPT_NAME']) == 'admin.php') { $tips[] = t(sprintf("Rename admin.php to something unique such as admin_%s.php", substr(sha1(uniqid(null, true)), 0, 20) )); }
              }
              $oldFilesAndDirs = array(); // ask user to remove outdated files
              $oldFilesAndDirs[] = '/3rdParty/SwiftMailer';
              $oldFilesAndDirs[] = '/3rdParty/SimplaAdmin';
              $oldFilesAndDirs[] = '/3rdParty/thickbox';
              $oldFilesAndDirs[] = '/3rdParty/tiny_mce';
              $oldFilesAndDirs[] = '/css';
              $oldFilesAndDirs[] = '/images';
              $oldFilesAndDirs[] = '/js';
              $oldFilesAndDirs[] = '/lib/compat';
              $oldFilesAndDirs[] = '/lib/images/loadingBar.gif';
              $oldFilesAndDirs[] = '/lib/jquery.js';
              $oldFilesAndDirs[] = '/lib/jquery.tablednd.js';
              $oldFilesAndDirs[] = '/lib/jquery1.2.js';
              $oldFilesAndDirs[] = '/lib/jquery1.3.2.js';
              $oldFilesAndDirs[] = '/lib/jqueryForm.js';
              $oldFilesAndDirs[] = '/lib/jqueryInterfaceSortables.js';
              $oldFilesAndDirs[] = '/lib/jqueryThickbox.js';
              $oldFilesAndDirs[] = '/lib/tinyMCE';
              $oldFilesAndDirs[] = '/lib/viewer_turboCache.php';
              $oldFilesAndDirs[] = '/lib/website_functions.php';
              $oldFilesAndDirs[] = '/lib/website_functions2.js';
              $oldFilesAndDirs[] = '/lib/website_functions2.php';
              $oldFilesAndDirs[] = '/lib/website_functions_notes.txt';
              $oldFilesAndDirs[] = '/tinyMCE';
              $oldFilesAndDirs[] = '/tinymce3';
              $oldFilesAndDirs[] = '/style.css';
              $oldFilesAndDirs[] = '/style_ie6.css';
              foreach ($oldFilesAndDirs as $relativePath) {
                if     (is_dir(SCRIPT_DIR.'/'.$relativePath))        { $tips[] = t(sprintf("Remove old folder: %s", SCRIPT_DIR . $relativePath )); }
                elseif (is_file(SCRIPT_DIR.'/'.$relativePath))       { $tips[] = t(sprintf("Remove old file: %s", SCRIPT_DIR . $relativePath )); }
              }
              if ($tips) {
                echo "<div class='text-danger'>";
                echo "  <b>" .t('These tips are custom generated and apply to the current server and connection:'). "</b>";
                echo "<ul>";
                foreach ($tips as $tip) { print "<li>$tip</li>\n"; }
                echo "</ul>";
                echo "</div>";
              }
              if (!$tips) {
                print t('None');
              }
            ?>
          </div>
        </div>
      </div>

      <?php echo adminUI_separator([
          'label' => t('Server Info'),
          'href'  => "?menu=admin&action=general#server-info",
          'id'    => "server-info",
        ]);
      ?>

      <div class="form-group">
        <div class="col-sm-3 control-label">
          <?php eht('Operating System'); ?>
        </div>
        <div class="col-sm-8 form-control-static">
          <div class="text-left">
            <?php
              $server  = @php_uname('s'); // Operating system name, eg:
              $release = @php_uname('r'); // Release name,          eg:
              //$version = @php_uname('v'); // Version info (varies),
              $machine = @php_uname('m'); // Machine type. eg. i386, x86_64
              print "$server $release ($machine)";

							//
							if (isWindows()) {
							  print " (<a href='?menu=admin&action=ver'>ver</a>, <a href='?menu=admin&action=systeminfo'>systeminfo</a>)";
							}
							if (!isWindows()) {
							  print " (<a href='?menu=admin&action=releases'>release</a>)";
							}
            ?>
            <!--
              php_uname('s'): <?php echo @php_uname('s'); ?> - Operating system name. eg. Windows NT, Linux, FreeBSD.
              php_uname('n'): <?php echo @php_uname('n'); ?> - Host name. eg. localhost.example.com.
              php_uname('r'): <?php echo @php_uname('r'); ?> - Release name. eg. 5.1, 2.6.18-164.11.1.el5, 5.1.2-RELEASE.
              php_uname('v'): <?php echo @php_uname('v'); ?> - Version information. Varies a lot between operating systems, eg: build 2600 (Windows XP Professional Service Pack 3), #1 SMP Wed Dec 17 11:42:39 EST 2008 i686
              php_uname('m'): <?php echo @php_uname('m'); ?> - Machine type. eg. i386, x86_64
            -->
          </div>
        </div>


        <div class="col-sm-3 control-label">
          <?php eht('Web Server'); ?>
        </div>
        <div class="col-sm-8 form-control-static">
          <div class="text-left">
            <span title="<?php et("Reported by"); ?> $_SERVER['SERVER_SOFTWARE']"><?php echo $_SERVER['SERVER_SOFTWARE']; ?></span><br/>
            <?php if (function_exists('apache_get_version')): ?>
						  <span title="<?php et("Reported by"); ?> apache_get_version()"><?php echo htmlencode(apache_get_version()); ?></span><br/>
						<?php endif ?>
          </div>
        </div>

        <div class="col-sm-3 control-label">
          <?php eht('PHP Version'); ?>
        </div>
        <div class="col-sm-8 form-control-static">
          <div class="text-left">
            PHP v<?php echo phpversion() ?> - <a href="?menu=admin&amp;action=phpinfo">phpinfo &gt;&gt;</a><br />
            <?php
              $disabledFunctions = str_replace(',', ', ', ini_get('disable_functions'));
              $suhosinDisabled   = str_replace(',', ', ', ini_get('suhosin.executor.func.blacklist'));
              if ($suhosinDisabled) { $disabledFunctions .= " - " . t("Suhosin disabled") . ": $suhosinDisabled"; }
            ?>
            <div style="padding: 5px 20px 0px; line-height: 1.5em">
              <ul>
                <li><a href="?menu=_error_log"><?php echo t('View PHP Errors and Warnings'); ?> &gt;&gt;</a></li>
                <li>php executable: <?php echo htmlencode(_getPhpExecutablePath()); ?><br/>
                <li>php.ini path: <?php echo get_cfg_var('cfg_file_path'); ?><br/>
                <?php // future, show additional .php files load with? php_ini_scanned_files, or php_ini_loaded_file ?>
                <li><?php echo t('PHP is running as user'); ?>: <?php echo htmlencode(get_current_user()); ?></li>
                <li>
                  <?php echo t('PHP disabled functions'); ?>:
                  <span style='color: #C00;'><?php echo $disabledFunctions; ?></span>
                  <?php echo $disabledFunctions ? '' : t('none'); ?>
                </li>
                <?php
                  // Check for common security modules that interfere
                  $securityModulesAsArray = [];
                  $suhosinNameAndVersion = _suhosinVersionDetected();
                  if ($suhosinNameAndVersion)                           { $securityModulesAsArray[] = $suhosinNameAndVersion; }
                  if (array_key_exists('CMSB_MOD_SECURITY1', $_SERVER)) { $securityModulesAsArray[] = "ModSecurity 1"; }
                  if (array_key_exists('CMSB_MOD_SECURITY2', $_SERVER)) { $securityModulesAsArray[] = "ModSecurity 2"; }
                  $securityModules     = implode(', ', $securityModulesAsArray);
                  $securityModulesNone = $securityModules ? '' : t("None detected");
                ?>
                <li>
                  <?php eht('open_basedir restrictions'); ?>:
                  <?php
                    $open_basedir = ini_get('open_basedir');
                    if ($open_basedir) { echo "<span style='color: #C00;'>" .htmlencode($open_basedir). "</span>"; }
                    else               { echo t('none'); }
                  ?>
                </li>
                <li><?php eht("Security Modules"); ?>: <span style='color: #C00;'><?php echo $securityModules; ?></span><?php echo $securityModulesNone; ?></li>

              </ul>
            </div>
          </div>
        </div>

        <label class="col-sm-3 control-label">
          <?php eht('Database Server'); ?>
        </label>
        <div class="col-sm-8 form-control-static">
          <div class="text-left">
            <?php print sprintf(t('MySQL v%s'),preg_replace("/[^0-9\.]/", '', mysqli()->server_info)); ?>
            <?php
              list($maxConnections, $maxUserConnections) = mysql_get_query("SELECT @@max_connections, @@max_user_connections", true); // returns the session value if it exists and the global value otherwise
              if ($maxUserConnections && $maxUserConnections < $maxConnections) { $maxConnections = $maxUserConnections; }
              echo " (" . t('Max Connections') . ": $maxConnections)";
            ?>
            <ul>
              <li><?php echo t('Hostname'); ?>: <?php echo inDemoMode() ? 'demo' : htmlencode($SETTINGS['mysql']['hostname']) ?> -
              <?php echo t('Database'); ?>: <?php echo inDemoMode() ? 'demo' : htmlencode($SETTINGS['mysql']['database']) ?> -
              <?php echo t('Username'); ?>: <?php echo inDemoMode() ? 'demo' : htmlencode($SETTINGS['mysql']['username']) ?> -
              <?php echo t('Table Prefix'); ?>: <?php echo htmlencode($TABLE_PREFIX) ?></li>
              <li><?php printf(t('To change %1$s settings edit %2$s'), 'MySQL', '/data/'.SETTINGS_FILENAME); ?></li>
            </ul>
          </div>
        </div>

        <div class="col-sm-3 control-label">
          <?php eht('Disk Space'); ?>
        </div>
        <div class="col-sm-8 form-control-static">
          <div class="text-left">
            <?php
              if ($totalBytes) {
                printf(t('Free: %1$s, Total: %2$s'), formatBytes($freeBytes), formatBytes($totalBytes));
              }
              else {  // for servers that return 0 and "Warning: Value too large for defined data type" on big ints
                et("Unavailable");
              }
            ?>
          </div>
        </div>

        <div class="col-sm-3 control-label">
          <?php eht('Server Resource Limits'); ?>
        </div>
        <div class="col-sm-8 form-control-static">
          <div class="text-left">
            <?php
            if ($maxCpuSeconds || $memoryLimitKbytes || $maxProcessLimit) {
              print "CPU Time: $maxCpuSeconds_formatted, Memory Limit: $memoryLimit_formatted, Processes: $maxProcessLimit - <a href='$ulimitLink'>ulimit &gt;&gt;</a>";
            }
            else {
              et("Unavailable");
            }
            ?>
            <?php
            /*
            <table>
              <tr><td colspan="2">&nbsp;</td></tr>
               <tr>
                <td><?php et('Outgoing Mail Server IP') ?>&nbsp;</td>
                <td><?php
                  $smtp = ini_get('SMTP');
                  if (!$smtp) { $smtp = $_SERVER['SERVER_ADDR']; }
                  if (!$smtp) { $smtp = $_SERVER['HTTP_HOST'];   }
                  $smtp_ip = @gethostbyname($smtp);
                  if ($smtp_ip)                         { $smtp = $smtp_ip;    }
                  if (!$smtp || $smtp == '127.0.0.1')   { $smtp = '(unknown)'; }
                  ?>
                  <input type="text" readonly="readonly" value="<?php echo $smtp ?>" onclick="this.focus(); this.select();" />
                  -
                  <a href="http://www.google.com/search?q=blacklist+ip+check" target="_blank">check blacklists &gt;&gt;</a>
                </td>
              </tr>
               <tr><td colspan="2">&nbsp;</td></tr>
               <tr>
                <td>Max Concurrent Users&nbsp;</td>
                <td>
                <?php
                  if ($maxProcessLimit && $maxConnections) {
                    print min($maxProcessLimit, $maxConnections);
                    print " - Based on Max MySQL Connections and Max Processes (other limits may affect total as well)<br/>\n";
                  }
                  else {
                    et("Unavailable");
                  }
                 ?>
                </td>
              </tr>
            </table>
            */
            ?>
          </div>
        </div>
      </div>
    </div>
  <?php
}
