<?php

// prepare adminUI() placeholders
$adminUI = [];

// page title
$adminUI['PAGE_TITLE'] = [ t('Admin'), t('Email Settings') => '?menu=admin&action=email' ];

// add extra html before form
$adminUI['PRE_FORM_HTML'] = ob_capture('_getPreFormContent');

// form tag and hidden fields
$adminUI['FORM'] = [ 'autocomplete' => 'off' ];
$adminUI['HIDDEN_FIELDS'] = [
  [ 'name' => 'menu',           'value' => 'admin', ],
  [ 'name' => '_defaultAction', 'value' => 'emailSave', ],
  [ 'name' => 'action',         'value' => 'email', ],
];

// buttons
$adminUI['BUTTONS'] = [];
$adminUI['BUTTONS'][] = [ 'name' => 'action=emailSave', 'label' => t('Save'),   ];
$adminUI['BUTTONS'][] = [ 'name' => 'action=email',   'label' => t('Cancel'), ];

// main content
$adminUI['CONTENT'] = ob_capture('_getContent');

// compose and output the page
adminUI($adminUI);

//
function _getPreFormContent() {
  global $SETTINGS;

  //
  $errorsAndAlerts = '';
  if ($SETTINGS['advanced']['outgoingMail'] == 'logOnly') {
    $errorsAndAlerts .= t("Warning: Outgoing Mail is currently set to 'Log Only'."). "<br>\r\n";
  }
  
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
  global $SETTINGS, $TABLE_PREFIX;

  ?>
    <div class="form-horizontal">
      
      <?php echo adminUI_separator([
          'label' => t('Email Settings'),
          'href'  => "?menu=admin&action=email#email-settings",
          'id'    => "email-settings",
        ]);
      ?>

      <div class="form-group">
        <label class="col-sm-3 control-label" for="adminEmail">
          <?php et('Admin Email');?>
        </label>
        <div class="col-sm-8">
          <input class="form-control" type="text" name="adminEmail" id="adminEmail" value="<?php echo htmlencode(@$SETTINGS['adminEmail']) ?>" />
          <p><?php et('This should be a valid email address that is checked for email.')?></p>
          <p><?php et('This email is used as the "From:" address on password reminder emails.')?></p>
        </div>
      </div>
      <div class="form-group">
        <div class="col-sm-3 control-label">
          <?php et('Outgoing Mail');?>
        </div>
        <div class="col-sm-8">
          <?php $value = coalesce(@$SETTINGS['advanced']['outgoingMail'], 'sendOnly'); // set default ?>
          <div class="radio">
            <label>
              <input class="grey" type="radio" name="outgoingMail" value="sendOnly" <?php checkedIf($value, 'sendOnly'); ?> />
              <?php eht("Send Only - Send mail without keeping a copy (default)"); ?>
            </label>
          </div>
          <div class="radio">
            <label>
              <input class="grey" type="radio" name="outgoingMail" value="sendAndLog" <?php checkedIf($value, 'sendAndLog'); ?> />
              <?php printf(t("Send &amp; Log - Send mail and save copies under <a href='%s'>Outgoing Mail</a>"), "?menu=_outgoing_mail"); ?>
            </label>
          </div>
          <div class="radio">
            <label>
              <input class="grey" type="radio" name="outgoingMail" value="logOnly" <?php checkedIf($value, 'logOnly'); ?> />
              <?php eht("Log Only - Log messages but don't send them (debug mode)"); ?>
            </label>
          </div>
        </div>
      </div>
      <div class="form-group">
        <label class="col-sm-3 control-label" for="smtp_method">
          <?php et('How to send mail');?>
        </label>
        <div class="col-sm-8">
          <?php
            $methodNamesToLabels = array();
            $methodNamesToLabels['php']       = t("Use PHP's built-in mail() function (default)");
            $methodNamesToLabels['unsecured'] = t("SMTP Server - Unsecured connection");
            $methodNamesToLabels['ssl']       = t("SMTP Server - Secured connection using SSL");
            $methodNamesToLabels['tls']       = t("SMTP Server - Secured connection using TLS");
            $selectOptions = getSelectOptions(@$SETTINGS['advanced']['smtp_method'], array_keys($methodNamesToLabels), array_values($methodNamesToLabels), false);
          ?>
          <select name="smtp_method" id="smtp_method" class="form-control"><?php echo $selectOptions; ?></select>
        </div>
      </div>
      <div class="form-group">
        <div class="col-sm-3 control-label">
          <?php et('SMTP Hostname & Port');?>
        </div>
        <div class="col-sm-3 col-xs-8">
          <input class="text-input medium-input setAttr-spellcheck-false form-control" type="text" name="smtp_hostname" value="<?php echo htmlencode(@$SETTINGS['advanced']['smtp_hostname']) ?>"  />
        </div>
        <div class="col-sm-2 col-xs-4">
          <input class="text-input small-input setAttr-spellcheck-false form-control" maxlength="5" type="text" name="smtp_port" value="<?php echo htmlencode(@$SETTINGS['advanced']['smtp_port']) ?>"  />
        </div>
        <div class="col-sm-3">
          <p><?php et('Default:'); ?> <?php echo htmlencode(get_cfg_var('SMTP') .':'. get_cfg_var('smtp_port')); ?></p>
        </div>
      </div>
      <div class="form-group">
        <label class="col-sm-3 control-label" for="smtp_username">
          <?php et('SMTP Username');?>
        </label>
        <div class="col-sm-8">
          <input class="form-control" type="text" name="smtp_username" id="smtp_username" value="<?php echo htmlencode(@$SETTINGS['advanced']['smtp_username']) ?>" />
        </div>
      </div>
      <div class="form-group">
        <label class="col-sm-3 control-label" for="smtp_password">
          <?php et('SMTP Password');?>
        </label>
        <div class="col-sm-8">
          <input class="form-control" type="text" name="smtp_password" id="smtp_password" value="<?php echo htmlencode(@$SETTINGS['advanced']['smtp_password']) ?>" />
          Tip: To test mail settings send yourself an email with the <a href="?menu=forgotPassword">Password Reset</a> form.
        </div>
      </div>
    </div>
  <?php
}
