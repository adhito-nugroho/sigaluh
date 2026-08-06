<?php
// includes/footer.php
?>
    <!-- Initialize Lucide Icons -->
    <script>
        lucide.createIcons();
        
        function toggleSidebar() {
            const sidebar = document.getElementById('sidebar');
            const overlay = document.getElementById('mobile-overlay');
            
            sidebar.classList.toggle('-translate-x-full');
            overlay.classList.toggle('hidden');
        }
    </script>
</body>
</html>
