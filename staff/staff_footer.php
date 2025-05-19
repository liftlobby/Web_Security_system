            </main>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Common JavaScript functions
        function showAlert(message, type = 'success') {
            const alertDiv = document.createElement('div');
            alertDiv.className = `alert alert-${type} alert-dismissible fade show`;
            alertDiv.role = 'alert';
            alertDiv.innerHTML = `
                ${message}
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
            `;
            document.querySelector('.content').insertAdjacentElement('afterbegin', alertDiv);
            
            // Auto dismiss after 5 seconds
            setTimeout(() => {
                alertDiv.remove();
            }, 5000);
        }

        // Add any other common JavaScript functions here
    </script>
</body>
</html>
