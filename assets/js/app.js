document.addEventListener('DOMContentLoaded', function () {
    if (window.jQuery && $.fn.DataTable) {
        $('.datatable').DataTable({
            pageLength: 10,
            order: [],
            responsive: {
                details: {
                    type: 'inline'
                }
            },
            autoWidth: false,
            language: {
                search: 'Cari:',
                lengthMenu: 'Tampilkan _MENU_ data',
                info: 'Menampilkan _START_ sampai _END_ dari _TOTAL_ data',
                infoEmpty: 'Belum ada data',
                zeroRecords: 'Data tidak ditemukan',
                paginate: {
                    previous: 'Sebelumnya',
                    next: 'Berikutnya'
                }
            }
        });
    }

    document.querySelectorAll('.room-filter-form').forEach(function (form) {
        const roomSelect = form.querySelector('[data-room-filter]');
        const itemSelect = form.querySelector('[data-item-select]');

        if (!roomSelect || !itemSelect) {
            return;
        }

        const options = Array.from(itemSelect.querySelectorAll('option'));
        const hasRoomBoundOptions = options.some(function (option, index) {
            return index > 0 && option.hasAttribute('data-room-id');
        });

        if (!hasRoomBoundOptions) {
            itemSelect.disabled = false;
            return;
        }

        const filterItems = function () {
            const selectedRoom = roomSelect.value;
            let hasVisibleItems = false;

            options.forEach(function (option, index) {
                if (index === 0) {
                    option.hidden = false;
                    return;
                }

                const roomId = option.getAttribute('data-room-id');
                const shouldShow = selectedRoom === '' || roomId === selectedRoom;
                option.hidden = !shouldShow;

                if (!shouldShow && option.selected) {
                    itemSelect.value = '';
                }

                if (shouldShow) {
                    hasVisibleItems = true;
                }
            });

            itemSelect.disabled = selectedRoom === '' || !hasVisibleItems;
        };

        roomSelect.addEventListener('change', filterItems);
        filterItems();
    });
});
