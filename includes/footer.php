</main>

<footer class="site-footer mt-5">
    <div class="container py-5">
        <div class="row g-4">
            <div class="col-lg-4">
                <a href="/" class="footer-brand brand-mark mb-3 d-inline-flex">
                    <span class="brand-icon"><i class="bi bi-bag-check-fill"></i></span>
                    <span><?= e(setting('site_name', 'Store')) ?></span>
                </a>
                <p class="text-secondary mb-4"><?= e(setting('meta_description', 'Curated products, secure checkout, and fast service.')) ?></p>
                <div class="d-flex gap-2">
                    <span class="trust-pill"><i class="bi bi-shield-check"></i> Secure</span>
                    <span class="trust-pill"><i class="bi bi-truck"></i> Fast</span>
                </div>
            </div>
            <div class="col-6 col-lg-2">
                <h6 class="footer-heading">Shop</h6>
                <ul class="footer-links">
                    <li><a href="/products.php">All Products</a></li>
                    <li><a href="/cart.php">Cart</a></li>
                    <li><a href="/checkout.php">Checkout</a></li>
                </ul>
            </div>
            <div class="col-6 col-lg-2">
                <h6 class="footer-heading">Account</h6>
                <ul class="footer-links">
                    <li><a href="/login.php">Login</a></li>
                    <li><a href="/register.php">Register</a></li>
                    <li><a href="/account.php">My Account</a></li>
                </ul>
            </div>
            <div class="col-lg-4">
                <h6 class="footer-heading">Need help?</h6>
                <p class="text-secondary mb-2">Contact us for product and order support.</p>
                <a href="mailto:<?= e(setting('admin_email', '')) ?>" class="footer-contact"><?= e(setting('admin_email', 'support@example.com')) ?></a>
            </div>
        </div>
    </div>
    <div class="footer-bottom py-3">
        <div class="container d-flex flex-column flex-md-row justify-content-between gap-2 small text-secondary">
            <span>&copy; <?= date('Y') ?> <?= e(setting('site_name', 'Store')) ?>. <?= e(setting('footer_text', 'All rights reserved.')) ?></span>
            <span><i class="bi bi-lock me-1"></i> Payments and account pages are protected.</span>
        </div>
    </div>
</footer>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script src="<?= rtrim(setting('base_url', ''), '/') ?>/public/assets/js/app.js"></script>
</body>
</html>
