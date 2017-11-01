<?php

// prepare adminUI() placeholders
$adminUI = [];

// page title
$adminUI['PAGE_TITLE'] = [ t('Admin'), t('Background Tasks') => '?menu=admin&action=bgtasks' ];

// add extra html before form
$adminUI['PRE_FORM_HTML'] = ob_capture('_getPreFormContent');

// form tag and hidden fields
$adminUI['FORM'] = [ 'autocomplete' => 'off' ];
$adminUI['HIDDEN_FIELDS'] = [
  [ 'name' => 'menu',           'value' => 'admin', ],
  [ 'name' => '_defaultAction', 'value' => 'bgtasksSave', ],
  [ 'name' => 'action',         'value' => 'bgtasks', ],
];

// buttons
$adminUI['BUTTONS'] = [];
$adminUI['BUTTONS'][] = [ 'name' => 'action=bgtasksSave', 'label' => t('Save'),   ];
$adminUI['BUTTONS'][] = [ 'name' => 'action=bgtasks', 'label' => t('Cancel'), ];

// main content
$adminUI['CONTENT'] = ob_capture('_getContent');

// compose and output the page
adminUI($adminUI);


//
function _getPreFormContent() {
  global $SETTINGS;

  // Check if the Background tasks ran over 24 hours ago.
  $errorsAndAlerts        = "";

  $secondsSinceLastRun = time() - intval($SETTINGS['bgtasks_lastRun']);
  $hasRunInLastHour    = $secondsSinceLastRun <= (60*60*1);

  if ($SETTINGS['bgtasks_disabled']) { $errorsAndAlerts .= t("Warning: Background tasks are currently disabled."). "<br>\r\n"; }
  elseif (!$hasRunInLastHour)        { $errorsAndAlerts .= t("Warning: Background tasks have not run in the last hour, please follow the instructions below to enable background tasks.") . "<br>\r\n"; }
  // Display errors
  if ($errorsAndAlerts) {
    ?>
    <div class="alert alert-danger">
      <button class="close">×</button>
      <i class="fa fa-exclamation-triangle"></i> &nbsp;
      <span>
        <?php echo $errorsAndAlerts; ?>
      </span>
    </div>
    <?php
  }

}

function _getContent() {
  global $SETTINGS;

  $prettyDate = prettyDate($SETTINGS['bgtasks_lastRun']);
  $dateString = $SETTINGS['bgtasks_lastRun'] ? date("D, M j, Y - g:i:s A", $SETTINGS['bgtasks_lastRun']) : $prettyDate;
  $logCount   = mysql_count('_cron_log');
  $failCount  = mysql_count('_cron_log', array('completed' => '0'));

?>
    <div class="form-horizontal">

      <?php echo adminUI_separator([
          'label' => t('Background Tasks'),
          'href'  => "?menu=admin&action=bgtasks#background-tasks",
          'id'    => "background-tasks",
        ]);
      ?>

      <div class="form-group">
        <div class="col-sm-3 control-label">
          <?php eht('Overview & Setup'); ?>
        </div>
        <div class="col-sm-8 control-label">
          <div class="text-left">
            <?php echo nl2br(t("Background tasks allow programs to run in the background at specific times for tasks such as maintenance, email alerts, etc.\n"."You don't need to enable this feature unless you have a plugin that requires it.")); ?><br /><br />
            <?php et("To setup Background Tasks, add a server cronjob or 'scheduled task' to execute the following command every minute:"); ?><br />
            <pre><?php echo htmlencode(_getPhpExecutablePath()); ?> <!-- -q --><?php echo absPath($GLOBALS['PROGRAM_DIR'] ."/cron.php"); ?><!--

                ini_get('extension_dir'): <?php echo ini_get('extension_dir') ."\n"; ?>
                PHP_BINDIR: <?php echo PHP_BINDIR ."\n" ?>
                PHP_BINARY: <?php echo PHP_BINARY ."\n"; ?>
                PHP_SAPI: <?php echo PHP_SAPI ."\n"; ?>
              --></pre>
          </div>
        </div>
      </div>
      <div class="form-group">
        <div class="col-sm-3 control-label">
          <?php eht('Status'); ?>
        </div>
        <div class="col-sm-8 control-label">
          <div class="text-left">
            <?php et('Last Run'); ?>:
            <span style='text-decoration: underline' title='<?php echo $dateString; ?>'><?php echo htmlencode($prettyDate); ?></span>
            - <a href="cron.php"><?php eht("run now >>"); ?></a><br />

            <?php et("Email Alerts: If tasks fail an email alert will be sent to admin (max once an hour)."); ?><br />

            <?php et("Log Summary: "); ?>
            <a href="?menu=_cron_log&amp;completed_match=&amp;showAdvancedSearch=1&amp;_ignoreSavedSearch=1"><?php echo $logCount ?> <?php et("entries"); ?></a>,
            <a href="?menu=_cron_log&amp;completed_match=0&amp;showAdvancedSearch=1&amp;_ignoreSavedSearch=1"><?php echo $failCount ?> <?php et("errors"); ?></a>
            - <a href="#" onclick="return redirectWithPost('?', {menu:'admin', action:'bgtasksLogsClear', '_CSRFToken': $('[name=_CSRFToken]').val()});"><?php et("clear all"); ?></a>
          </div>
        </div>
      </div>
      <div class="form-group">
        <div class="col-sm-3 control-label">
          <?php eht('Recent Activity'); ?>
        </div>
        <div class="col-sm-8 control-label">
          <div class="text-left">
            <div class="table-wrap">
            <div align="center" style="padding-bottom: 5px"><a href="?menu=_cron_log"><?php eht("Background Tasks Log >>"); ?></a></div>
            <table class="data table table-striped table-hover">
              <thead>
                <tr>
                  <th><?php et('Date'); ?></th>
                  <th><?php et('Activity'); ?></th>
                  <th><?php et('Summary'); ?></th>
                  <th><?php et('Completed'); ?></th>
                </tr>
              </thead>
              <tbody>
              <?php
                $recentRecords = mysql_select('_cron_log', "true ORDER BY num DESC LIMIT 5");
                if ($recentRecords):
              ?>
              <?php foreach ($recentRecords as $record): ?>
                <tr class="listRow">
                  <td><?php echo htmlencode($record['createdDate']); ?></td>
                  <td>
                    <a href="?menu=_cron_log&amp;action=edit&amp;num=<?php echo $record['num'] ?>"><?php echo htmlencode($record['activity']); ?></a><br/>
                    <?php /* <small><?php echo htmlencode($record['runtime']); ?> seconds</small> */ ?>
                  </td>
                  <td><?php echo htmlencode($record['summary']); ?></td>
                  <td><?php echo $record['completed'] ? t('Yes') : t('No'); ?></td>
                </tr>
              <?php endforeach ?>
              <?php else: ?>
                <tr>
                  <td colspan="4"><?php et('None'); ?></td>
                </tr>
              <?php endif ?>
              </tbody>
              </table>
              </div>
            </div>
        </div>
      </div>
      <div class="form-group">
        <div class="col-sm-3 control-label">
          <?php eht('Scheduled Tasks'); ?>
        </div>
        <div class="col-sm-8 control-label">
          <div class="text-left">
            <div class="table-wrap">
            <table class="data table table-striped table-hover">
              <thead>
                <tr style="text-align: left;">
                  <th><?php et('Function'); ?></th>
                  <th><?php et('Activity'); ?></th>
                  <th><?php et('Last Run'); ?></th>
                  <th><?php et('Frequency'); ?> (<a href="http://en.wikipedia.org/wiki/Cron#CRON_expression" target="_blank">?</a>)</th>
                </tr>
              </thead>
              <tbody>
              <?php
                $cronRecords = getCronList();
                if ($cronRecords):
              ?>
              <?php foreach ($cronRecords as $record): ?>
                <tr class="listRow">
                  <td><?php echo htmlencode($record['function']); ?></td>
                  <td><?php echo htmlencode($record['activity']); ?></td>
                  <td><?php
                      $latestLog = mysql_get('_cron_log', null, ' function = "' .mysql_escape($record['function']). '" ORDER BY num DESC');
                      echo prettyDate( $latestLog['createdDate'] );
                    ?></td>
                  <td><?php echo htmlencode($record['expression']); ?></td>
                </tr>
              <?php endforeach ?>
            <?php else: ?>
              <tr>
                <td colspan="4"><?php et('None'); ?></td>
              </tr>
            <?php endif ?>
              </tbody>
            </table>
            </div>
          </div>
        </div>
      </div>
      
      <div class="form-group <?php $SETTINGS['bgtasks_disabled'] ? print('text-danger') : ''; ?>">
        <div class="col-sm-3 control-label">
          <?php et('Disable Background Tasks');?>
        </div>
        <div class="col-sm-8">
           <div class="checkbox">
            <label >
              <input type="hidden" name="bgtasks_disabled" value="0" />
              <input name="bgtasks_disabled" <?php checkedIf($SETTINGS['bgtasks_disabled'], '1'); ?> value="1" type="checkbox">
              <?php et('Temporarily disable background tasks (for debugging or maintenance)'); ?>
            </label>
          </div>
        </div>
      </div>

    </div>
  <?php
}

// eof