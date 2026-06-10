</main>
<footer class="shop-footer">
    <div class="shop-container shop-footer-inner">
        <div class="shop-footer-brand">
            <strong><?= e(COMPANY_NAME) ?></strong>
            <p><?= e(companyTagline()) ?></p>
            <?php if (defined('SHOP_HOURS') && SHOP_HOURS !== ''): ?>
                <p class="shop-footer-meta"><i class="fa-regular fa-clock"></i> <?= e(SHOP_HOURS) ?></p>
            <?php endif; ?>
            <?php if (shopContactPhone() !== ''): ?>
                <p class="shop-footer-meta"><a href="tel:<?= e(preg_replace('/\s+/', '', shopContactPhone())) ?>"><i class="fa-solid fa-phone"></i> <?= e(shopContactPhone()) ?></a></p>
            <?php endif; ?>
        </div>
        <div class="shop-footer-links">
            <a href="<?= shopUrl() ?>"><?= e(__('shop')) ?></a>
            <a href="<?= shopUrl('maintenance.php') ?>"><?= e(__('maintenance')) ?></a>
            <a href="<?= shopUrl('track.php') ?>"><?= e(__('track_order')) ?></a>
            <a href="<?= shopUrl('cart.php') ?>"><?= e(__('cart')) ?></a>
            <?php if (shopContactPhone() !== '' && orderWhatsAppShareUrl(shopContactPhone(), __('shop_contact_whatsapp_msg'))): ?>
                <a href="<?= e(orderWhatsAppShareUrl(shopContactPhone(), __('shop_contact_whatsapp_msg'))) ?>" target="_blank" rel="noopener">WhatsApp</a>
            <?php endif; ?>
        </div>
    </div>
</footer>
</body></html>
