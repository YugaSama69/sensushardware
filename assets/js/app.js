document.addEventListener('DOMContentLoaded', function () {
    const initDataTables = function (scope) {
        if (!window.jQuery || !$.fn.DataTable) {
            return;
        }

        const root = scope || document;
        const tables = root.querySelectorAll ? root.querySelectorAll('.datatable') : [];

        tables.forEach(function (table) {
            if ($.fn.DataTable.isDataTable(table)) {
                return;
            }

            $(table).DataTable({
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
        });
    };

    const destroyDataTables = function (scope) {
        if (!window.jQuery || !$.fn.DataTable) {
            return;
        }

        const root = scope || document;
        const tables = root.querySelectorAll ? root.querySelectorAll('.datatable') : [];

        tables.forEach(function (table) {
            if ($.fn.DataTable.isDataTable(table)) {
                $(table).DataTable().destroy();
            }
        });
    };

    const getDataTableState = function (scope) {
        if (!window.jQuery || !$.fn.DataTable) {
            return null;
        }

        const table = scope ? scope.querySelector('.datatable') : null;

        if (!table || !$.fn.DataTable.isDataTable(table)) {
            return null;
        }

        const dataTable = $(table).DataTable();

        return {
            page: dataTable.page(),
            search: dataTable.search(),
            order: dataTable.order(),
            length: dataTable.page.len()
        };
    };

    const applyDataTableState = function (scope, state) {
        if (!window.jQuery || !$.fn.DataTable || !state) {
            return;
        }

        const table = scope ? scope.querySelector('.datatable') : null;

        if (!table || !$.fn.DataTable.isDataTable(table)) {
            return;
        }

        const dataTable = $(table).DataTable();
        dataTable.page.len(state.length);
        dataTable.search(state.search);
        dataTable.order(state.order);
        dataTable.draw(false);

        const pageInfo = dataTable.page.info();
        const targetPage = Math.min(state.page, Math.max(pageInfo.pages - 1, 0));
        dataTable.page(targetPage).draw('page');
    };

    const toggleComputerClientLoading = function (scope, isLoading) {
        if (!scope) {
            return;
        }

        const loadingIndicator = scope.querySelector('[data-computer-loading]');
        const tablePanel = scope.querySelector('.computer-client-table-panel');

        if (loadingIndicator) {
            loadingIndicator.classList.toggle('d-none', !isLoading);
        }

        if (tablePanel) {
            tablePanel.classList.toggle('computer-client-table-panel-loading', isLoading);
        }
    };

    initDataTables(document);

    document.addEventListener('click', function (event) {
        const refreshButton = event.target.closest('.js-refresh-computer-table');

        if (!refreshButton) {
            return;
        }

        event.preventDefault();

        const currentRegion = document.querySelector('.computer-client-refresh-region');
        const refreshUrl = refreshButton.getAttribute('data-refresh-url') || refreshButton.getAttribute('href');

        if (!refreshUrl || !currentRegion) {
            return;
        }

        if (refreshButton.dataset.loading === 'true') {
            return;
        }

        const currentScrollY = window.scrollY;
        const currentTableState = getDataTableState(currentRegion);

        refreshButton.dataset.loading = 'true';
        refreshButton.setAttribute('aria-busy', 'true');
        refreshButton.classList.add('disabled');
        refreshButton.textContent = '...';
        toggleComputerClientLoading(currentRegion, true);

        fetch(refreshUrl, {
            headers: {
                'X-Requested-With': 'XMLHttpRequest'
            }
        })
            .then(function (response) {
                if (!response.ok) {
                    throw new Error('Gagal memuat data komputer client.');
                }

                return response.text();
            })
            .then(function (html) {
                const parser = new DOMParser();
                const doc = parser.parseFromString(html, 'text/html');
                const nextRegion = doc.querySelector('.computer-client-refresh-region');

                if (!nextRegion) {
                    throw new Error('Panel data komputer client tidak ditemukan.');
                }

                destroyDataTables(currentRegion);
                currentRegion.replaceWith(nextRegion);
                initDataTables(nextRegion);
                applyDataTableState(nextRegion, currentTableState);
                window.scrollTo(0, currentScrollY);
            })
            .catch(function () {
                window.alert('Data komputer client belum berhasil direfresh. Silakan coba lagi.');
            })
            .finally(function () {
                refreshButton.dataset.loading = 'false';
                refreshButton.removeAttribute('aria-busy');
                refreshButton.classList.remove('disabled');
                refreshButton.innerHTML = '&#x21bb;';
                const activeRegion = document.querySelector('.computer-client-refresh-region');
                toggleComputerClientLoading(activeRegion, false);
            });
    });

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

    const buildBarangCodePreview = function (name) {
        const stopwords = new Set(['DAN', 'TO', 'FOR', 'WITH', 'THE', 'OF', 'A', 'AN', 'DI', 'KE', 'DARI']);
        const tokens = (name || '')
            .toUpperCase()
            .replace(/[^A-Z0-9]+/g, ' ')
            .trim()
            .split(/\s+/)
            .filter(Boolean)
            .filter(function (token) {
                return !stopwords.has(token);
            });

        if (!tokens.length) {
            return '';
        }

        let prefix = '';
        tokens.forEach(function (token) {
            if (prefix.length >= 8) {
                return;
            }

            let segment = '';
            if (/^\d+$/.test(token)) {
                segment = token.slice(0, 2);
            } else if (token.length <= 4) {
                segment = token;
            } else {
                segment = token.slice(0, 3);
            }

            prefix += segment;
        });

        prefix = prefix.slice(0, 8);
        while (prefix.length > 0 && prefix.length < 3) {
            prefix += 'X';
        }

        return prefix ? prefix + '-AUTO' : '';
    };

    document.querySelectorAll('form').forEach(function (form) {
        const nameInput = form.querySelector('[data-barang-name-source]');
        const codeInput = form.querySelector('[data-barang-code-target]');

        if (!nameInput || !codeInput) {
            return;
        }

        const syncBarangCode = function () {
            codeInput.value = buildBarangCodePreview(nameInput.value);
        };

        nameInput.addEventListener('input', syncBarangCode);
        syncBarangCode();
    });

    document.querySelectorAll('form').forEach(function (form) {
        const mutasiTypeSelect = form.querySelector('[data-mutasi-type-select]');
        const mutasiReferenceWrapper = form.querySelector('[data-mutasi-reference-wrapper]');
        const mutasiReferenceInput = form.querySelector('[data-mutasi-reference-input]');
        const mutasiReferenceDetail = form.querySelector('[data-mutasi-reference-detail]');
        const mutasiDetailHostname = form.querySelector('[data-mutasi-detail-hostname]');
        const mutasiDetailRuangan = form.querySelector('[data-mutasi-detail-ruangan]');
        const mutasiDetailKondisi = form.querySelector('[data-mutasi-detail-kondisi]');

        if (!mutasiTypeSelect || !mutasiReferenceWrapper || !mutasiReferenceInput) {
            return;
        }

        const syncMutasiReferenceDetail = function () {
            if (!mutasiReferenceDetail || !mutasiDetailHostname || !mutasiDetailRuangan || !mutasiDetailKondisi) {
                return;
            }

            const selectedOption = mutasiReferenceInput.options[mutasiReferenceInput.selectedIndex];
            const hasSelection = !!mutasiReferenceInput.value;
            mutasiReferenceDetail.classList.toggle('d-none', !hasSelection);

            if (!hasSelection || !selectedOption) {
                mutasiDetailHostname.textContent = '';
                mutasiDetailRuangan.textContent = '';
                mutasiDetailKondisi.textContent = '';
                return;
            }

            mutasiDetailHostname.textContent = selectedOption.getAttribute('data-hostname') || '';
            mutasiDetailRuangan.textContent = selectedOption.getAttribute('data-ruangan') || '';
            mutasiDetailKondisi.textContent = selectedOption.getAttribute('data-kondisi') || '';
        };

        const syncMutasiReferenceVisibility = function () {
            const shouldShowReference = mutasiTypeSelect.value === 'pergantian_komputer_rusak';
            mutasiReferenceWrapper.classList.toggle('d-none', !shouldShowReference);
            mutasiReferenceInput.disabled = !shouldShowReference;

            if (!shouldShowReference) {
                mutasiReferenceInput.value = '';
            }

            syncMutasiReferenceDetail();
        };

        mutasiTypeSelect.addEventListener('change', syncMutasiReferenceVisibility);
        mutasiReferenceInput.addEventListener('change', syncMutasiReferenceDetail);
        syncMutasiReferenceVisibility();
    });
});
