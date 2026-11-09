<script>
    let itineraryIndex = (function () {
        const rows = document.querySelectorAll('#itinerary-rows [data-itinerary-row]');
        return rows.length;
    })();

    function addItineraryRow() {
        const template = document.getElementById('itinerary-template');
        if (!template) return;

        const dayNumber = document.querySelectorAll('#itinerary-rows [data-itinerary-row]').length + 1;
        const html = template.innerHTML
            .replaceAll('__INDEX__', String(itineraryIndex))
            .replaceAll('__DAY__', String(dayNumber));

        const wrapper = document.createElement('div');
        wrapper.innerHTML = html.trim();
        const row = wrapper.firstElementChild;
        document.getElementById('itinerary-rows').appendChild(row);
        itineraryIndex++;
    }

    function removeItineraryRow(button) {
        const row = button.closest('[data-itinerary-row]');
        if (row) row.remove();
    }

    function moveItineraryRow(button, direction) {
        const row = button.closest('[data-itinerary-row]');
        if (!row) return;

        if (direction < 0 && row.previousElementSibling) {
            row.parentNode.insertBefore(row, row.previousElementSibling);
        } else if (direction > 0 && row.nextElementSibling) {
            row.parentNode.insertBefore(row.nextElementSibling, row);
        }
    }

    // Show the selected file names so an editor can confirm the upload before
    // submitting a potentially large form.
    document.addEventListener('change', function (event) {
        if (event.target && event.target.matches('input[name="images[]"]')) {
            const list = document.getElementById('gallery-file-list');
            if (!list) return;
            const names = Array.from(event.target.files || []).map(function (file) {
                return file.name + ' (' + Math.round(file.size / 1024) + ' KB)';
            });
            list.textContent = names.length ? names.join(', ') : '';
        }
    });
</script>
