document.addEventListener('DOMContentLoaded', function () {
    const searchInput = document.getElementById('participantSearch');
    if (!searchInput) {
        return;
    }

    searchInput.addEventListener('keyup', function () {
        const term = this.value.toLowerCase();
        const rows = document.querySelectorAll('tbody tr');

        rows.forEach(function (row) {
            const nameCell = row.querySelector('td:first-child');
            const name = nameCell ? nameCell.textContent.toLowerCase() : '';
            row.style.display = name.includes(term) ? '' : 'none';
        });
    });
});
