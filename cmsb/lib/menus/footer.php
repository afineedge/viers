
          <div id="footer" align="center">
            <small>

    <?php
      if (@$GLOBALS['SETTINGS']['footerHTML']) {
        echo getEvalOutput($GLOBALS['SETTINGS']['footerHTML']) . '<br/>';
      }

      $executeSecondsString = sprintf(t("%s seconds"), showExecuteSeconds(true));
      echo applyFilters('execute_seconds', $executeSecondsString);
    ?>

    <?php doAction('admin_footer'); ?>
    <!-- -->

            </small>
          </div>

        </div>

      </div>
      <!-- End #main-content -->
    </div>
    <!-- End #main-container -->

    <?php doAction('admin_footer_final') ?>

  </body>
</html>
