<?php
// includes/footer.php
?>
    <!-- Initialize Lucide Icons -->
    <script>
        // Initialize icons
        lucide.createIcons();
        
        // Sidebar Toggle
        function toggleSidebar() {
            const sidebar = document.getElementById('sidebar');
            const overlay = document.getElementById('mobile-overlay');
            
            sidebar.classList.toggle('-translate-x-full');
            overlay.classList.toggle('hidden');
        }
        
        // Toast Notification System
        function showToast(message, type = 'success', duration = 3000) {
            const container = document.getElementById('toast-container') || createToastContainer();
            const toast = document.createElement('div');
            
            const icons = {
                success: '<i data-lucide="check-circle" class="w-5 h-5 text-emerald-400 flex-shrink-0"></i>',
                error: '<i data-lucide="alert-circle" class="w-5 h-5 text-rose-400 flex-shrink-0"></i>',
                info: '<i data-lucide="info" class="w-5 h-5 text-primary-400 flex-shrink-0"></i>',
                warning: '<i data-lucide="alert-triangle" class="w-5 h-5 text-warning-400 flex-shrink-0"></i>',
            };
            
            toast.className = 'toast-enter flex items-center gap-3 bg-neutral-900/95 glass text-white px-5 py-3.5 rounded-xl shadow-lg border border-white/10 mb-2 max-w-sm';
            toast.innerHTML = `${icons[type] || icons.info}<span class="text-sm font-medium">${message}</span>`;
            container.appendChild(toast);
            
            lucide.createIcons({node: toast});
            
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
        
        // Intersection Observer for fade-in animations
        if ('IntersectionObserver' in window) {
            const observerOptions = { threshold: 0.1, rootMargin: '0px 0px -40px 0px' };
            const observer = new IntersectionObserver((entries) => {
                entries.forEach(entry => {
                    if (entry.isIntersecting) {
                        entry.target.classList.add('animate-fade-in-up');
                        observer.unobserve(entry.target);
                    }
                });
            }, observerOptions);
            
            document.querySelectorAll('.observe-fade').forEach(el => {
                el.style.opacity = '0';
                observer.observe(el);
            });
        }
    </script>
</body>
</html>
