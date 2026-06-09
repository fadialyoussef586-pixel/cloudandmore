</main>
<footer class="shop-footer">
    <div class="shop-container shop-footer-inner">
        <div>
            <strong><?= e(COMPANY_NAME) ?></strong>
            <p><?= e(__('company_tagline')) ?></p>
        </div>
        <div class="shop-footer-links">
            <a href="<?= shopUrl() ?>"><?= e(__('shop')) ?></a>
            <a href="<?= shopUrl('maintenance.php') ?>"><?= e(__('maintenance')) ?></a>
            <a href="<?= shopUrl('cart.php') ?>"><?= e(__('cart')) ?></a>
        </div>
    </div>
</footer>
</body></html>
