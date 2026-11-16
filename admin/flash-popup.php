<?php
declare(strict_types=1);

/*
 * Success / error popup for the admin panel.
 * Include once at the bottom of an admin page (before </body>):
 *     <?php require __DIR__ . '/flash-popup.php'; ?>
 * It shows the message saved with flash_set() exactly once, then forgets it.
 */
$hpmFlash = flash_pull();
if ($hpmFlash === null) {
    return;
}
$hpmFlashType = $hpmFlash['type'] === 'error' ? 'error' : 'success';
?>
<div class="hpm-popup" id="hpmPopup" data-type="<?= e($hpmFlashType) ?>" role="alertdialog" aria-modal="true" aria-labelledby="hpmPopupTitle" aria-describedby="hpmPopupText">
    <div class="hpm-popup__backdrop" data-popup-close></div>
    <div class="hpm-popup__card">
        <div class="hpm-popup__icon" aria-hidden="true"><?= $hpmFlashType === 'error' ? '!' : '✓' ?></div>
        <h2 id="hpmPopupTitle"><?= $hpmFlashType === 'error' ? 'Something went wrong' : 'Success' ?></h2>
        <p id="hpmPopupText"><?= e($hpmFlash['message']) ?></p>
        <button type="button" class="hpm-popup__btn" data-popup-close>OK</button>
        <span class="hpm-popup__bar" aria-hidden="true"></span>
    </div>
</div>
<script>
(function () {
    var popup = document.getElementById('hpmPopup');
    if (!popup) return;
    var timer;
    function close() {
        clearTimeout(timer);
        popup.classList.add('is-closing');
        setTimeout(function () { popup.remove(); }, 220);
        document.removeEventListener('keydown', onKey);
    }
    function onKey(e) { if (e.key === 'Escape') close(); }
    popup.querySelectorAll('[data-popup-close]').forEach(function (el) { el.addEventListener('click', close); });
    document.addEventListener('keydown', onKey);
    var btn = popup.querySelector('.hpm-popup__btn');
    if (btn) btn.focus();
    // Success messages close by themselves; errors stay until dismissed.
    if (popup.dataset.type === 'success') timer = setTimeout(close, 4500);
})();
</script>
