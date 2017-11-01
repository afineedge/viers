<?php

// prepare adminUI() placeholders
$adminUI = [];

// page title
$adminUI['PAGE_TITLE'] = [ t('Admin'), t('Branding') => '?menu=admin&action=branding' ];

// form tag and hidden fields
$adminUI['FORM'] = [ 'autocomplete' => 'off' ];
$adminUI['HIDDEN_FIELDS'] = [
  [ 'name' => 'menu',           'value' => 'admin', ],
  [ 'name' => '_defaultAction', 'value' => 'brandingSave', ],
  [ 'name' => 'action',         'value' => 'branding', ],
];

// buttons
$adminUI['BUTTONS'] = [];
$adminUI['BUTTONS'][] = [ 'name' => 'action=brandingSave', 'label' => t('Save'),   ];
$adminUI['BUTTONS'][] = [ 'name' => 'action=branding', 'label' => t('Cancel'), ];

// main content
$adminUI['CONTENT'] = ob_capture('_getContent');

// compose and output the page
adminUI($adminUI);

function _getContent() {
  global $SETTINGS, $APP;

  //
  $brandingLink = $SETTINGS['adminUrl'] . "?menu=admin&action=branding";

?>
    <div class="form-horizontal">

      <div class="form-group">
        <label for="vendorUrl" class="col-sm-3 control-label"><?php echo t('Instructions'); ?></label>
        <div class="col-sm-7 control-label">
          <div class="text-left">

            If you purchase a license you can: 
            <ul>
              <li>Customize the Program Name displayed in the CMS.</li>
              <li>Customize or remove the footer text displayed on website pages.</li>
              <li>Completely private label the software as your own.</li>
              <li>Sublicense the software to your customers.</li>
              <li>Support the development of the software.</li>
              <li>... and edit all the fields below!</li>
            </ul>

            <p><b>To get started, purchase a license, and enter your License Product ID below:</b></p>

                <div class="input-group">
                  <span class="input-group-addon"> <i class="fa fa-key"></i> </span>
                  <input class="form-control text-input small-input setAttr-spellcheck-false" type="text" name="licenseProductId" id="licenseProductId" value="<?php echo inDemoMode() ? 'XXXX-XXXX-XXXX-XXXX' : htmlencode($SETTINGS['licenseProductId']) ?>" style="<?php echo inDemoMode() ? 'color: #999999' : '' ?>"  onfocus="clearDefaultProductId(this)" />
                </div><br/>

  <script>

    var DEFAULT_PRODUCT_ID = "XXXX-XXXX-XXXX-XXXX";

    function setDefaultProductId() {
      var elProductId = document.getElementById('licenseProductId');
      if (elProductId.value == '' || elProductId.value == DEFAULT_PRODUCT_ID) {
        elProductId.value = DEFAULT_PRODUCT_ID;
        elProductId.style.color = "#999999";
      }
    }

    function clearDefaultProductId() {
      var elProductId = document.getElementById('licenseProductId');
      if (elProductId.value == DEFAULT_PRODUCT_ID) {
        elProductId.value = '';
        elProductId.style.color = "#000000";
      }
    }

    document.getElementById('licenseProductId').focus();

    setDefaultProductId();

  </script>


            <p><b>Then click save</b>, once your Product ID has been saved you will be able to edit the fields below.</o>

          </div>
        </div>
      </div>

      <div class="form-group">
        <div class="col-sm-3 control-label"><?php eht('Private Labeling'); ?></div>
        <div class="col-sm-8 form-control-static">
          <strong>
            <?php if (isValidProductId($SETTINGS['licenseProductId'])): ?>
              <p class="text-success">Enabled - Update fields below.</p>
            <?php else: ?>
              <p class="text-danger">Disabled - Enter license key above and save to continue.</p>
            <?php endif ?>
          </strong>
        </div>
      </div>



      <?php echo adminUI_separator([
          'label' => t('Private Label Branding'),
          'href'  => "?menu=admin&action=branding#privateLabelBranding",
          'id'    => "privateLabelBranding",
        ]);
      ?>

      <div class="form-group">
        <div class="col-sm-3 control-label">
          <?php et('Branding Menu');?>
        </div>
        <div class="col-sm-8 form-control-static text-danger">
          <strong>NOTE:</strong> Once you change the "Vendor Name" field below, the "Branding" link will no longer be displayed on the menu when it's not selected so be sure to bookmark this URL:
          <a href="<?php echo $brandingLink ?>"><?php echo htmlencode($brandingLink); ?></a>
        </div>
      </div>

      <div class="form-group">
        <label class="col-sm-3 control-label">&nbsp;</label>
        <div class="col-sm-8"><strong>These fields are displayed in the CMS.</strong></div>
      </div>

      <div class="form-group">
        <label class="col-sm-3 control-label" for="programName">
          <?php et('Program Name / Titlebar');?>
        </label>
        <div class="col-sm-8">
          <input class="form-control" type="text" name="programName" id="programName" value="<?php echo htmlencode($SETTINGS['programName']) ?>" />
        </div>
      </div>

      <div class="form-group">
        <label class="col-sm-3 control-label" for="headerImageUrl">
          <?php et('Header Image URL');?>
        </label>
        <div class="col-sm-8">
          <input class="form-control" type="text" name="headerImageUrl" id="headerImageUrl" value="<?php echo htmlencode($SETTINGS['headerImageUrl']) ?>" />
        </div>
      </div>

      <div class="form-group">
        <label for="helpUrl" class="col-sm-3 control-label"><?php echo t('Help URL'); ?></label>
        <div class="col-sm-8">
          <input class="form-control" type="text" name="helpUrl" id="helpUrl" value="<?php echo htmlencode($SETTINGS['helpUrl']) ?>" />
        </div>
      </div>

      <div class="form-group">
        <label class="col-sm-3 control-label" for="footerHTML">
          <?php et('Footer HTML');?>
        </label>
        <div class="col-sm-8">
          <input class="form-control" type="text" name="footerHTML" id="footerHTML" value="<?php echo htmlencode($SETTINGS['footerHTML']) ?>" />
        </div>
      </div>

      <div class="form-group">
        <label class="col-sm-3 control-label" for="cssTheme">
          <?php et('Color / Theme');?>
        </label>
        <div class="col-sm-8">
          <?php // get CSS files
            $cssDirRelative = "/3rdParty/clipone/css/";
            $cssDirPath     = $GLOBALS['CMS_ASSETS_DIR'] . $cssDirRelative;
            $cssDirUrl      = $GLOBALS['CMS_ASSETS_URL'] . $cssDirRelative;
            foreach (scandir($cssDirPath) as $filename) {
              if (preg_match("|^theme\w+\.css(?:\.php)?$|i", $filename)) { $cssFiles[] = $filename; }
            }
            $optionsHTML = getSelectOptions($SETTINGS['cssTheme'], $cssFiles, $cssFiles, true);
          ?>
          <select name="cssTheme" id="cssTheme" class="form-control"><?php echo $optionsHTML; ?></select>
          <script>
            $(document).ready(function() {
              $('#cssTheme').on('change', function() {
                var cssFile = $(this).val();
                if (cssFile) {
                  var cssFileURL = '<?php echo escapejs("$cssDirUrl"); ?>' + cssFile;
                  $('#skin_color').attr("href", cssFileURL);
                }
              });
            });
          </script>

          <?php print sprintf(t('You can add CSS themes in <code>%s</code>'), $cssDirUrl); ?>
        </div>
      </div>


      <br/><br/>
      <div class="form-group">
        <label class="col-sm-3 control-label">&nbsp;</label>
        <div class="col-sm-8">
          <strong>
            These fields are displayed in the <a href="?menu=license">License Agreement</a> and in <a href="?menu=admin&action=general#license-info">General Settings</a>.
          </strong>
        </div>
      </div>

      <div class="form-group">
        <label for="vendorName" class="col-sm-3 control-label"><?php echo t('Vendor Name'); ?></label>
        <div class="col-sm-7">
          <input class="form-control" type="text" name="vendorName" id="vendorName" value="<?php echo htmlencode($SETTINGS['vendorName']) ?>" />
          <p class="text-danger"><strong>NOTE:</strong> Changing this hides the <a href="<?php echo $brandingLink; ?>">Branding</a> link from the menu, and
          removes <a href="?menu=license#sublicensing">sublicensing</a> permission from license agreement that has your company name on it.</p>
        </div>
      </div>

      <div class="form-group">
        <div class="col-sm-3 control-label"><?php eht('Sublicensing'); ?></div>
        <?php if (allowSublicensing()): ?>
          <div class="col-sm-8 form-control-static text-success">Licensees may sublicense the software licensed to them from: <?php echo htmlencode($SETTINGS['vendorName']) ?></div>
        <?php else: ?>
          <div class="col-sm-8 form-control-static text-danger">Licencees may not sublicense the software licensed to them from: <?php echo htmlencode($SETTINGS['vendorName']) ?>.</div>
        <?php endif ?>
      </div>
      
      <div class="form-group">
        <label for="vendorLocation" class="col-sm-3 control-label"><?php echo t('Vendor Location'); ?></label>
        <div class="col-sm-7">
          <input class="form-control" type="text" name="vendorLocation" id="vendorLocation" value="<?php echo htmlencode($SETTINGS['vendorLocation']) ?>" />
          Make sure Vendor Location is in this format 'State or Province, Country' because it's listed as the jurisdiction in the license agreement.
        </div>
      </div>
      <div class="form-group">
        <label for="vendorUrl" class="col-sm-3 control-label"><?php echo t('Vendor Url'); ?></label>
        <div class="col-sm-7">
          <input class="form-control" type="text" name="vendorUrl" id="vendorUrl" value="<?php echo htmlencode($SETTINGS['vendorUrl']) ?>" />
        </div>
      </div>



      <br/><br/>
      <div class="form-group">
        <label class="col-sm-3 control-label">&nbsp;</label>
        <div class="col-sm-8"><strong>This field is displayed at the bottom of all HTML viewer pages.</strong></div>
      </div>

      <div class="form-group">
        <label for="vendorUrl" class="col-sm-3 control-label"><?php echo t('Powered By HTML'); ?></label>
        <div class="col-sm-7">
          <input class="form-control" type="text" name="poweredByHTML" value="<?php echo htmlencode($SETTINGS['poweredByHTML']) ?>" />
          <ul>
            <li>Displaying the default "Powered By" link is required unless you purchase a license.
            <li>You can display the "Powered By" anywhere you want on the page with this PHP tag: &lt;?php echo poweredByHTML(); ?&gt;</li>
            <li>If the "Powered By" HTML is not found anywhere in the page, it will automatically be added to the bottom of the page.</li>
            <li>Example output: <?php echo poweredByHTML(); ?></li>
          </ul>
        </div>
      </div>


    </div>
  <?php
}
