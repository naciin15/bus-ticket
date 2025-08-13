            </main>
            
            <footer class="footer bg-white shadow-sm py-3 px-4">
                <div class="container-fluid">
                    <div class="row">
                        <div class="col-md-6">
                            <span class="text-muted">© <?= date('Y') ?> Bus Booking System</span>
                        </div>
                        <div class="col-md-6 text-end">
                            <span class="text-muted">Server Time: <?= date('Y-m-d H:i:s') ?></span>
                        </div>
                    </div>
                </div>
            </footer>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <script src="/assets/js/admin.js"></script>
    
    <script>
        // Toggle sidebar on mobile
        document.getElementById('sidebarToggle').addEventListener('click', function() {
            document.querySelector('.wrapper').classList.toggle('sidebar-toggled');
        });
        
        // Initialize charts
        document.addEventListener('DOMContentLoaded', function() {
            // Booking chart
            const bookingCtx = document.getElementById('bookingChart');
            if (bookingCtx) {
                new Chart(bookingCtx, {
                    type: 'line',
                    data: {
                        labels: <?= json_encode(range(1, 30)) ?>,
                        datasets: [{
                            label: 'Daily Bookings',
                            data: <?= json_encode(array_map(function() { return rand(5, 50); }, range(1, 30))) ?>,
                            borderColor: 'rgba(75, 192, 192, 1)',
                            backgroundColor: 'rgba(75, 192, 192, 0.2)',
                            tension: 0.1
                        }]
                    },
                    options: {
                        responsive: true,
                        scales: {
                            y: {
                                beginAtZero: true
                            }
                        }
                    }
                });
            }
            
            // Revenue chart
            const revenueCtx = document.getElementById('revenueChart');
            if (revenueCtx) {
                new Chart(revenueCtx, {
                    type: 'bar',
                    data: {
                        labels: <?= json_encode(['Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun', 'Jul', 'Aug', 'Sep', 'Oct', 'Nov', 'Dec']) ?>,
                        datasets: [{
                            label: 'Monthly Revenue',
                            data: <?= json_encode(array_map(function() { return rand(1000, 10000); }, range(1, 12))) ?>,
                            backgroundColor: 'rgba(54, 162, 235, 0.5)',
                            borderColor: 'rgba(54, 162, 235, 1)',
                            borderWidth: 1
                        }]
                    },
                    options: {
                        responsive: true,
                        scales: {
                            y: {
                                beginAtZero: true
                            }
                        }
                    }
                });
            }
        });
    </script>
</body>
</html>