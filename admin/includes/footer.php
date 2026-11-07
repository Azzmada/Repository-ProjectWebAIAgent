        </main>

        <footer class="px-4 sm:px-6 lg:px-8 py-4 border-t border-gray-200 text-xs text-gray-500 flex flex-col sm:flex-row sm:items-center justify-between gap-1">
            <p>&copy; <?= date('Y') ?> <?= e(APP_NAME) ?> — Admin Panel</p>
            <p>Semua transaksi tercatat otomatis.</p>
        </footer>
    </div>

    <script>
        function toggleAdminSidebar(open) {
            const sidebar = document.getElementById('admin-sidebar');
            const overlay = document.getElementById('admin-overlay');
            if (!sidebar || !overlay) return;
            if (open) {
                sidebar.classList.remove('-translate-x-full');
                overlay.classList.remove('hidden');
            } else {
                sidebar.classList.add('-translate-x-full');
                overlay.classList.add('hidden');
            }
        }

        // Client-side confirmation for destructive actions.
        document.addEventListener('submit', function (event) {
            const form = event.target;
            if (form.matches('[data-confirm]')) {
                if (!window.confirm(form.getAttribute('data-confirm') || 'Yakin melanjutkan?')) {
                    event.preventDefault();
                }
            }
        });
    </script>
</body>
</html>
