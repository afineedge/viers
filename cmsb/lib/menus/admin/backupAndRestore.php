<?php

// prepare adminUI() placeholders
$adminUI = [];

// page title
$adminUI['PAGE_TITLE'] = [ t('Admin'), t('Backup & Restore') => '?menu=admin&action=backuprestore' ];

// form tag and hidden fields
$adminUI['FORM'] = [ 'autocomplete' => 'off' ];
$adminUI['HIDDEN_FIELDS'] = [
  [ 'name' => 'menu',           'value' => 'admin', ],
  [ 'name' => '_defaultAction', 'value' => 'adminSave', ],
];

// main content
$adminUI['CONTENT'] = ob_capture('_getContent');

// add extra html after the form
$adminUI['POST_FORM_HTML'] = ob_capture('_getPostFormContent');

// compose and output the page
adminUI($adminUI);

function _getPostFormContent() {
  ?>
    <script>
      function confirmRestoreDatabase() {
        var backupFile = $('#restore').val();

        // error checking
        if (backupFile == '') { return alert('<?php et('No backup file selected!'); ?>'); }

        // request confirmation
        if (!confirm("<?php et('Restore data from this backup file?')?>\n" +backupFile+ "\n\n<?php et('WARNING: BACKUP DATA WILL OVERWRITE EXISTING DATA!')?>")) { return; }

        //
        redirectWithPost('?', {
          'menu':       'admin',
          'action':     'restore',
          'file':       backupFile,
          '_CSRFToken': $('[name=_CSRFToken]').val()
        });
        
      }
    </script>
  <?php
}

function _getContent() {
  global $SETTINGS, $TABLE_PREFIX;

  ?>
    <div class="form-horizontal">

      <div class="form-group">
        <div class="col-sm-3 control-label">
          <label for="backupTable">
            <?php et('Database Backup');?>
          </label>
        </div>
        <div class="col-sm-6">
          <select name="backupTable" id="backupTable" class="form-control">
          <option value=''><?php et('all database tables'); ?></option>
          <?php
            $schemaTables = getSchemaTables();
            sort($schemaTables);
            echo getSelectOptions(@$_REQUEST['backupTable'], $schemaTables);
          ?>
          </select>
          <p class="help-block">
          <?php print sprintf(t('Backup file will be stored in %s'),$GLOBALS['BACKUP_DIR']) ?>
          </p>
        </div>
        <div class="col-sm-3">
          <?php
            echo adminUI_button([
              'label'   => t('Backup'),
              'type'    => 'button',
              'name'    => 'null',
              'value'   => '1',
              'onclick' => "return redirectWithPost('?', {menu:'admin', action:'backup', 'backupTable':$('#backupTable').val(), '_CSRFToken': $('[name=_CSRFToken]').val()});",
            ]);
          ?>
        </div>
      </div>
      <div class="form-group">
        <div class="col-sm-3 control-label">
          <label for="restore">
            <?php et('Database Restore');?>
          </label>
        </div>
        <div class="col-sm-6">
          <?php $options = getBackupFiles_asOptions(); ?>
          <select name="restore" id="restore" class="form-control">
            <?php echo $options ?>
          </select>
        </div>
        <div class="col-sm-3">
          <?php
            echo adminUI_button([
              'label'   => t('Restore'),
              'type'    => 'button',
              'name'    => 'null',
              'value'   => '1',
              'onclick' => 'confirmRestoreDatabase()',
            ]);

            echo adminUI_button([
              'label'    => t('Download'),
              'type'     => 'button',
              'btn-type' => 'default',
              'name'     => 'null',
              'value'    => '1',
              'onclick'  => "return redirectWithPost('?', {menu:'admin', action:'backupDownload', 'file':$('#restore').val(), '_CSRFToken': $('[name=_CSRFToken]').val()});",
            ]);
          ?>
        </div>

      </div>
    </div>
  <?php
}
