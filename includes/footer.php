<?php
// Footer include — closes the main-content and app-layout divs
?>
</div><!-- .main-content -->
</div><!-- .app-layout -->

<script src="<?= $rootUrl ?>assets/js/main.js"></script>
<?php if (isset($extraJS)): ?>
<script><?= $extraJS ?></script>
<?php endif; ?>
</body>
</html>
