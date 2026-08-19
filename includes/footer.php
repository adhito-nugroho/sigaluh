<?php
// includes/footer.php
?>
    <script>
    // Mobile sidebar toggle
    document.addEventListener('DOMContentLoaded', function() {
        const toggleBtn = document.getElementById('sidebarToggle');
        const sidebar = document.getElementById('sidebar');
        const backdrop = document.getElementById('sidebarBackdrop');
        const closeSidebar = function() {
            if (!sidebar) return;
            sidebar.classList.remove('show');
            backdrop?.classList.remove('show');
            toggleBtn?.setAttribute('aria-expanded', 'false');
        };
        if (toggleBtn) {
            toggleBtn.addEventListener('click', function() {
                const isOpen = sidebar.classList.toggle('show');
                backdrop?.classList.toggle('show', isOpen);
                toggleBtn.setAttribute('aria-expanded', isOpen ? 'true' : 'false');
            });
        }
        backdrop?.addEventListener('click', closeSidebar);
        document.querySelectorAll('#sidebar .nav-link').forEach(function(link) {
            link.addEventListener('click', closeSidebar);
        });
    });

    // Toast Notification System
    function showToast(message, type = 'success', duration = 3000) {
        const container = document.getElementById('toast-container') || createToastContainer();
        const toast = document.createElement('div');

        const icons = {
            success: 'check_circle',
            error: 'error',
            info: 'info',
            warning: 'warning',
        };

        toast.className = 'toast-enter flex items-center gap-3 bg-neutral-900/95 text-white px-5 py-3.5 rounded-xl shadow-lg border border-white/10 mb-2 max-w-sm';
        toast.innerHTML = `<span class="material-symbols-outlined flex-shrink-0" style="font-size:20px;color:var(--md-sys-color-${type === 'error' ? 'error' : type === 'success' ? 'tertiary' : type === 'warning' ? 'secondary' : 'primary'}-container);">${icons[type] || icons.info}</span><span class="text-sm font-medium">${message}</span>`;
        container.appendChild(toast);

        setTimeout(() => {
            toast.classList.remove('toast-enter');
            toast.classList.add('toast-exit');
            setTimeout(() => toast.remove(), 200);
        }, duration);
    }

    function createToastContainer() {
        const container = document.createElement('div');
        container.id = 'toast-container';
        container.className = 'fixed top-6 right-6 z-[9999] flex flex-col items-end';
        document.body.appendChild(container);
        return container;
    }

    // Animate numbers (count up)
    function animateCount(el, target, duration = 800) {
        let start = 0;
        const step = target / (duration / 16);
        const timer = setInterval(() => {
            start += step;
            if (start >= target) {
                el.textContent = target.toLocaleString('id-ID');
                clearInterval(timer);
            } else {
                el.textContent = Math.floor(start).toLocaleString('id-ID');
            }
        }, 16);
    }

    // Init count-up for elements with data-count attribute
    document.addEventListener('DOMContentLoaded', function() {
        document.querySelectorAll('[data-count]').forEach(el => {
            const target = parseInt(el.getAttribute('data-count'));
            if (!isNaN(target)) {
                animateCount(el, target);
            }
        });
    });
    </script>
</body>
</html>