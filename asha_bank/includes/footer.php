    </div><!-- /.content -->
  </div><!-- /.main-col -->
</div><!-- /.app-shell -->

<?php
$flashKeys = isset($_SESSION['flash']) ? array_keys($_SESSION['flash']) : [];
foreach ($flashKeys as $k):
    $f = flash($k);
    if ($f):
?>
<div class="js-flash" data-msg="<?= clean($f['msg']) ?>" data-type="<?= clean($f['type']) ?>" style="display:none;"></div>
<?php endif; endforeach; ?>

<script src="<?= BASE_URL ?>/assets/js/main.js"></script>
</body>
</html>
