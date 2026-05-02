document.addEventListener('DOMContentLoaded', () => {
    document.querySelectorAll('.toast').forEach((el) => new bootstrap.Toast(el, { delay: 4200 }).show());

    const nav = document.querySelector('.shop-navbar');
    if (nav) {
        const onScroll = () => nav.classList.toggle('is-scrolled', window.scrollY > 20);
        window.addEventListener('scroll', onScroll, { passive: true });
        onScroll();
    }

    document.querySelectorAll('.qty-control').forEach((wrap) => {
        const input = wrap.querySelector('input');
        const minus = wrap.querySelector('[data-action="minus"]');
        const plus = wrap.querySelector('[data-action="plus"]');
        const emit = () => input.dispatchEvent(new Event('change', { bubbles: true }));
        minus?.addEventListener('click', () => { const v = parseInt(input.value, 10) || 1; if (v > parseInt(input.min || '1', 10)) { input.value = v - 1; emit(); } });
        plus?.addEventListener('click', () => { const v = parseInt(input.value, 10) || 1; const max = parseInt(input.max || '999', 10); if (v < max) { input.value = v + 1; emit(); } });
    });

    document.querySelectorAll('form').forEach((form) => {
        form.addEventListener('submit', () => {
            const btn = form.querySelector('button[type="submit"]');
            if (!btn || btn.dataset.loading === 'false') return;
            btn.dataset.originalText = btn.innerHTML;
            btn.classList.add('is-loading');
            btn.disabled = true;
            btn.innerHTML = '<span class="spinner-border spinner-border-sm me-2" aria-hidden="true"></span>Working...';
        });
    });

    const sidebarToggle = document.querySelector('.admin-menu-toggle');
    const sidebar = document.querySelector('.admin-sidebar');
    if (sidebarToggle && sidebar) {
        sidebarToggle.addEventListener('click', () => sidebar.classList.toggle('open'));
        document.addEventListener('click', (event) => {
            if (sidebar.classList.contains('open') && !sidebar.contains(event.target) && !sidebarToggle.contains(event.target)) sidebar.classList.remove('open');
        });
    }

    const imageInput = document.getElementById('productImage');
    const imagePreview = document.getElementById('imagePreview');
    if (imageInput && imagePreview) {
        imageInput.addEventListener('change', () => {
            const file = imageInput.files[0];
            if (!file) return;
            const reader = new FileReader();
            reader.onload = (event) => { imagePreview.src = event.target.result; imagePreview.style.display = 'block'; };
            reader.readAsDataURL(file);
        });
    }

    document.querySelectorAll('[data-confirm]').forEach((el) => {
        el.addEventListener('click', (event) => {
            if (!confirm(el.dataset.confirm || 'Are you sure?')) event.preventDefault();
        });
    });
});
