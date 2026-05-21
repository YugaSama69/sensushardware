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

            const isMonitoringHistoryTable = table.classList.contains('monitoring-history-table');

            $(table).DataTable({
                pageLength: 10,
                order: [],
                responsive: isMonitoringHistoryTable ? false : {
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
        const itemSearch = form.querySelector('[data-item-search]');
        const itemHidden = form.querySelector('[data-item-hidden]');
        const itemDatalist = form.querySelector('[data-item-datalist]');
        const itemLabelFilter = form.querySelector('[data-item-label-filter]');
        const itemConditionFilter = form.querySelector('[data-item-condition-filter]');

        if (!itemSearch || !itemHidden || !itemDatalist) {
            return;
        }

        const datalistOptions = Array.from(itemDatalist.querySelectorAll('option')).map(function (option) {
            return {
                value: option.value,
                itemId: option.getAttribute('data-item-id') || '',
                roomId: option.getAttribute('data-room-id') || '',
                label: option.getAttribute('data-item-label') || '',
                condition: option.getAttribute('data-item-condition') || ''
            };
        });
        const hasRoomBoundOptions = datalistOptions.some(function (option) {
            return option.roomId !== '';
        });

        const normalizeText = function (value) {
            return (value || '').trim().toLowerCase();
        };

        const syncHiddenItemValue = function () {
            const selectedValue = normalizeText(itemSearch.value);

            if (selectedValue === '') {
                itemHidden.value = '';
                itemSearch.setCustomValidity('');
                return;
            }

            const selectedRoom = roomSelect ? roomSelect.value : '';
            const selectedLabel = itemLabelFilter ? normalizeText(itemLabelFilter.value) : '';
            const selectedCondition = itemConditionFilter ? normalizeText(itemConditionFilter.value) : '';
            const matchedOption = datalistOptions.find(function (option) {
                const matchesRoom = selectedRoom === '' || option.roomId === '' || option.roomId === selectedRoom;
                const matchesLabel = selectedLabel === '' || normalizeText(option.label) === selectedLabel;
                const matchesCondition = selectedCondition === '' || normalizeText(option.condition) === selectedCondition;
                return matchesRoom && matchesLabel && matchesCondition && normalizeText(option.value) === selectedValue;
            });

            itemHidden.value = matchedOption ? matchedOption.itemId : '';
            itemSearch.setCustomValidity(matchedOption ? '' : 'Pilih barang dari daftar yang tersedia.');
        };

        const renderDatalistOptions = function (searchTerm, selectedRoom, selectedLabel, selectedCondition) {
            itemDatalist.innerHTML = '';
            let hasVisibleItems = false;

            datalistOptions.forEach(function (option) {
                const matchesRoom = selectedRoom === '' || option.roomId === '' || option.roomId === selectedRoom;
                const matchesLabel = selectedLabel === '' || normalizeText(option.label) === selectedLabel;
                const matchesCondition = selectedCondition === '' || normalizeText(option.condition) === selectedCondition;
                const matchesSearch = searchTerm === '' || normalizeText(option.value).indexOf(searchTerm) !== -1;

                if (!matchesRoom || !matchesLabel || !matchesCondition || !matchesSearch) {
                    return;
                }

                hasVisibleItems = true;
                const optionElement = document.createElement('option');
                optionElement.value = option.value;
                optionElement.setAttribute('data-item-id', option.itemId);
                if (option.roomId !== '') {
                    optionElement.setAttribute('data-room-id', option.roomId);
                }
                if (option.label !== '') {
                    optionElement.setAttribute('data-item-label', option.label);
                }
                if (option.condition !== '') {
                    optionElement.setAttribute('data-item-condition', option.condition);
                }
                itemDatalist.appendChild(optionElement);
            });

            return hasVisibleItems;
        };

        const filterItems = function () {
            const selectedRoom = roomSelect ? roomSelect.value : '';
            const searchTerm = normalizeText(itemSearch.value);
            const selectedLabel = itemLabelFilter ? normalizeText(itemLabelFilter.value) : '';
            const selectedCondition = itemConditionFilter ? normalizeText(itemConditionFilter.value) : '';
            const hasVisibleItems = renderDatalistOptions(searchTerm, selectedRoom, selectedLabel, selectedCondition);
            const shouldDisableSearch = hasRoomBoundOptions && selectedRoom === '';

            itemSearch.disabled = shouldDisableSearch;
            if (shouldDisableSearch) {
                itemSearch.value = '';
                itemHidden.value = '';
                itemSearch.setCustomValidity('');
            }

            if (!hasVisibleItems && !shouldDisableSearch) {
                itemHidden.value = '';
            }

            syncHiddenItemValue();
        };

        if (roomSelect) {
            roomSelect.addEventListener('change', filterItems);
        }

        if (itemLabelFilter) {
            itemLabelFilter.addEventListener('change', filterItems);
        }

        if (itemConditionFilter) {
            itemConditionFilter.addEventListener('change', filterItems);
        }

        if (itemSearch) {
            itemSearch.addEventListener('input', filterItems);
            itemSearch.addEventListener('change', syncHiddenItemValue);
        }

        form.addEventListener('submit', function (event) {
            syncHiddenItemValue();

            if (itemSearch.value.trim() !== '' && itemHidden.value === '') {
                event.preventDefault();
                itemSearch.reportValidity();
            }
        });

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

    const initMonitoringChart = function () {
        const chartCanvas = document.querySelector('[data-monitoring-chart]');

        if (!chartCanvas || typeof window.Chart === 'undefined') {
            return;
        }

        const labels = JSON.parse(chartCanvas.getAttribute('data-chart-labels') || '[]');
        const totals = JSON.parse(chartCanvas.getAttribute('data-chart-totals') || '[]');
        const warnings = JSON.parse(chartCanvas.getAttribute('data-chart-warnings') || '[]');

        new window.Chart(chartCanvas, {
            type: 'line',
            data: {
                labels: labels,
                datasets: [
                    {
                        label: 'Total Monitoring',
                        data: totals,
                        borderColor: '#0b63f6',
                        backgroundColor: 'rgba(11, 99, 246, 0.12)',
                        tension: 0.35,
                        fill: true
                    },
                    {
                        label: 'Total Warning',
                        data: warnings,
                        borderColor: '#f79009',
                        backgroundColor: 'rgba(247, 144, 9, 0.10)',
                        tension: 0.35,
                        fill: true
                    }
                ]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: {
                        position: 'bottom'
                    }
                },
                scales: {
                    y: {
                        beginAtZero: true,
                        ticks: {
                            precision: 0
                        }
                    }
                }
            }
        });
    };

    const initMonitoringForm = function () {
        const form = document.querySelector('[data-monitoring-form]');

        if (!form) {
            return;
        }

        const temperatureAlert = form.querySelector('[data-monitoring-temperature-alert]');
        const accessAlert = form.querySelector('[data-monitoring-access-alert]');
        const noteField = form.querySelector('[data-monitoring-note]');
        const noteHelp = form.querySelector('[data-monitoring-note-help]');
        const signatureModal = document.querySelector('[data-signature-modal]');
        const signatureCanvas = signatureModal ? signatureModal.querySelector('[data-signature-pad]') : null;
        const signatureOutput = form.querySelector('[data-signature-output]');
        const signaturePreview = form.querySelector('[data-signature-preview]');
        const signatureEmptyState = form.querySelector('[data-signature-empty-state]');
        const clearButtons = document.querySelectorAll('[data-signature-clear], [data-signature-clear-trigger], [data-signature-reset]');
        const apiEndpoint = form.getAttribute('data-api-endpoint') || form.getAttribute('action') || window.location.href;
        const redirectUrl = form.getAttribute('data-redirect-url') || window.location.href;
        const toastElement = document.querySelector('[data-monitoring-toast]');
        const toastMessage = document.querySelector('[data-monitoring-toast-message]');

        let submitErrorBox = document.querySelector('[data-monitoring-submit-error]');
        if (!submitErrorBox) {
            submitErrorBox = document.createElement('div');
            submitErrorBox.className = 'alert alert-danger d-none';
            submitErrorBox.setAttribute('data-monitoring-submit-error', 'true');
            form.prepend(submitErrorBox);
        }

        const showSubmitError = function (message) {
            submitErrorBox.textContent = message;
            submitErrorBox.classList.remove('d-none');
        };

        const hideSubmitError = function () {
            submitErrorBox.textContent = '';
            submitErrorBox.classList.add('d-none');
        };

        const getCheckedValue = function (name) {
            const field = form.querySelector('input[name="' + name + '"]:checked');
            return field ? field.value : '';
        };

        const syncWarningState = function () {
            const isTemperatureWarning = getCheckedValue('suhu') === 'gt_20_21';
            const isAccessWarning = getCheckedValue('akses_masuk') === 'tidak_terkunci';
            const noteRequired = isTemperatureWarning || isAccessWarning;

            if (temperatureAlert) {
                temperatureAlert.classList.toggle('d-none', !isTemperatureWarning);
            }

            if (accessAlert) {
                accessAlert.classList.toggle('d-none', !isAccessWarning);
            }

            if (noteField) {
                noteField.required = noteRequired;
                noteField.classList.toggle('is-warning-required', noteRequired);
            }

            if (noteHelp) {
                noteHelp.textContent = noteRequired
                    ? 'Catatan sekarang wajib diisi karena terdeteksi warning pada suhu atau akses masuk.'
                    : 'Catatan opsional jika seluruh kondisi normal.';
            }
        };

        form.querySelectorAll('input[name="suhu"], input[name="akses_masuk"]').forEach(function (input) {
            input.addEventListener('change', syncWarningState);
        });
        syncWarningState();

        let signaturePad = null;

        if (signatureCanvas && typeof window.SignaturePad !== 'undefined') {
            signaturePad = new window.SignaturePad(signatureCanvas, {
                backgroundColor: 'rgb(255,255,255)',
                penColor: '#0f172a',
                minWidth: 0.9,
                maxWidth: 2.2
            });

            const paintCanvasBackground = function () {
                const context = signatureCanvas.getContext('2d');
                if (!context) {
                    return;
                }

                context.save();
                context.setTransform(1, 0, 0, 1, 0, 0);
                context.fillStyle = '#ffffff';
                context.fillRect(0, 0, signatureCanvas.width, signatureCanvas.height);
                context.restore();
            };

            const syncSignaturePreview = function () {
                if (!signatureOutput || !signaturePreview) {
                    return;
                }

                if (!signaturePad || signaturePad.isEmpty()) {
                    signatureOutput.value = '';
                    signaturePreview.classList.add('d-none');
                    signaturePreview.removeAttribute('src');
                    if (signatureEmptyState) {
                        signatureEmptyState.classList.remove('d-none');
                    }
                    return;
                }

                const dataUrl = signaturePad.toDataURL('image/png');
                signatureOutput.value = dataUrl;
                signaturePreview.src = dataUrl;
                signaturePreview.classList.remove('d-none');
                if (signatureEmptyState) {
                    signatureEmptyState.classList.add('d-none');
                }
            };

            const clearSignature = function () {
                if (!signaturePad) {
                    return;
                }

                signaturePad.clear();
                paintCanvasBackground();
                syncSignaturePreview();
                form.querySelectorAll('[data-signature-clear-trigger]').forEach(function (button) {
                    button.disabled = true;
                });
            };

            const resizeSignatureCanvas = function () {
                const ratio = Math.max(window.devicePixelRatio || 1, 1);
                const data = signaturePad && !signaturePad.isEmpty() ? signaturePad.toData() : null;
                signatureCanvas.width = signatureCanvas.offsetWidth * ratio;
                signatureCanvas.height = signatureCanvas.offsetHeight * ratio;
                const context = signatureCanvas.getContext('2d');
                context.scale(ratio, ratio);
                paintCanvasBackground();
                signaturePad.clear();
                if (data && data.length) {
                    signaturePad.fromData(data);
                }
                syncSignaturePreview();
            };

            signaturePad.addEventListener('endStroke', function () {
                syncSignaturePreview();
                form.querySelectorAll('[data-signature-clear-trigger]').forEach(function (button) {
                    button.disabled = false;
                });
            });
            clearButtons.forEach(function (button) {
                button.addEventListener('click', clearSignature);
            });

            window.addEventListener('resize', resizeSignatureCanvas);
            resizeSignatureCanvas();

            if (signatureOutput && signatureOutput.value.trim() !== '') {
                signaturePad.fromDataURL(signatureOutput.value);
                signaturePreview.src = signatureOutput.value;
                signaturePreview.classList.remove('d-none');
                if (signatureEmptyState) {
                    signatureEmptyState.classList.add('d-none');
                }
                form.querySelectorAll('[data-signature-clear-trigger]').forEach(function (button) {
                    button.disabled = false;
                });
            }

            if (signatureModal) {
                signatureModal.addEventListener('shown.bs.modal', function () {
                    window.setTimeout(resizeSignatureCanvas, 60);
                });
            }

            document.querySelectorAll('[data-signature-save]').forEach(function (button) {
                button.addEventListener('click', syncSignaturePreview);
            });

            form.addEventListener('reset', function () {
                window.setTimeout(function () {
                    clearSignature();
                    hideSubmitError();
                    syncWarningState();
                }, 0);
            });

            form.addEventListener('submit', function (event) {
                if (event.defaultPrevented) {
                    return;
                }

                syncWarningState();
                syncSignaturePreview();

                if (!signatureOutput || signatureOutput.value.trim() === '') {
                    event.preventDefault();
                    showSubmitError('Paraf/tanda tangan wajib diisi.');
                    return;
                }

                if (noteField && noteField.required && noteField.value.trim() === '') {
                    event.preventDefault();
                    noteField.reportValidity();
                    return;
                }

                event.preventDefault();
                hideSubmitError();

                const submitButton = form.querySelector('button[type="submit"]');
                if (submitButton) {
                    submitButton.disabled = true;
                    submitButton.textContent = 'Menyimpan...';
                }

                fetch(apiEndpoint, {
                    method: 'POST',
                    body: new FormData(form),
                    headers: {
                        'X-Requested-With': 'XMLHttpRequest'
                    }
                })
                    .then(function (response) {
                        return response.json().then(function (data) {
                            return {
                                ok: response.ok,
                                data: data
                            };
                        });
                    })
                    .then(function (result) {
                        if (!result.ok || !result.data || result.data.success !== true) {
                            throw new Error((result.data && result.data.message) || 'Data monitoring belum berhasil disimpan.');
                        }

                        if (toastMessage) {
                            toastMessage.textContent = result.data.message || 'Monitoring berhasil disimpan.';
                        }

                        if (toastElement && window.bootstrap && window.bootstrap.Toast) {
                            window.bootstrap.Toast.getOrCreateInstance(toastElement, {
                                delay: 900
                            }).show();
                        }

                        window.setTimeout(function () {
                            window.location.href = redirectUrl;
                        }, 700);
                    })
                    .catch(function (error) {
                        showSubmitError(error.message || 'Data monitoring belum berhasil disimpan.');
                    })
                    .finally(function () {
                        if (submitButton) {
                            submitButton.disabled = false;
                            submitButton.textContent = 'Simpan';
                        }
                    });
            });
        }
    };

    const initMonitoringHistorySignatureModal = function () {
        const historyRoot = document.querySelector('[data-monitoring-histori]');
        const modalElement = document.querySelector('[data-monitoring-signature-modal]');

        if (!historyRoot || !modalElement || typeof window.SignaturePad === 'undefined' || !window.bootstrap || !window.bootstrap.Modal) {
            return;
        }

        const apiEndpoint = historyRoot.getAttribute('data-api-endpoint') || '';
        const csrfToken = historyRoot.getAttribute('data-csrf-token') || '';
        const modalInstance = window.bootstrap.Modal.getOrCreateInstance(modalElement);
        const canvas = modalElement.querySelector('[data-monitoring-signature-pad]');
        const output = modalElement.querySelector('[data-monitoring-signature-output]');
        const idInput = modalElement.querySelector('[data-monitoring-signature-id]');
        const preview = modalElement.querySelector('[data-monitoring-signature-preview]');
        const emptyState = modalElement.querySelector('[data-monitoring-signature-empty]');
        const errorBox = modalElement.querySelector('[data-monitoring-signature-error]');
        const title = modalElement.querySelector('[data-monitoring-signature-title]');
        const subtitle = modalElement.querySelector('[data-monitoring-signature-subtitle]');
        const submitButton = modalElement.querySelector('[data-monitoring-signature-submit]');
        const clearButtons = modalElement.querySelectorAll('[data-monitoring-signature-clear], [data-monitoring-signature-reset]');

        if (!canvas || !output || !idInput || !preview || !emptyState || !submitButton) {
            return;
        }

        const signaturePad = new window.SignaturePad(canvas, {
            backgroundColor: 'rgb(255,255,255)',
            penColor: '#0f172a',
            minWidth: 0.9,
            maxWidth: 2.2
        });

        const paintCanvasBackground = function () {
            const context = canvas.getContext('2d');
            if (!context) {
                return;
            }

            context.save();
            context.setTransform(1, 0, 0, 1, 0, 0);
            context.fillStyle = '#ffffff';
            context.fillRect(0, 0, canvas.width, canvas.height);
            context.restore();
        };

        const syncPreview = function () {
            if (signaturePad.isEmpty()) {
                output.value = '';
                preview.classList.add('d-none');
                preview.removeAttribute('src');
                emptyState.classList.remove('d-none');
                return;
            }

            const dataUrl = signaturePad.toDataURL('image/png');
            output.value = dataUrl;
            preview.src = dataUrl;
            preview.classList.remove('d-none');
            emptyState.classList.add('d-none');
        };

        const resizeCanvas = function () {
            const ratio = Math.max(window.devicePixelRatio || 1, 1);
            const existingValue = output.value.trim();
            const data = !signaturePad.isEmpty() ? signaturePad.toData() : null;
            canvas.width = canvas.offsetWidth * ratio;
            canvas.height = canvas.offsetHeight * ratio;
            canvas.getContext('2d').scale(ratio, ratio);
            paintCanvasBackground();
            signaturePad.clear();
            if (data && data.length) {
                signaturePad.fromData(data);
                syncPreview();
                return;
            }

            if (existingValue !== '') {
                signaturePad.fromDataURL(existingValue);
                output.value = existingValue;
                preview.src = existingValue;
                preview.classList.remove('d-none');
                emptyState.classList.add('d-none');
                return;
            }

            syncPreview();
        };

        const clearSignature = function () {
            signaturePad.clear();
            paintCanvasBackground();
            syncPreview();
        };

        signaturePad.addEventListener('endStroke', syncPreview);
        clearButtons.forEach(function (button) {
            button.addEventListener('click', clearSignature);
        });

        modalElement.addEventListener('shown.bs.modal', function () {
            window.setTimeout(resizeCanvas, 60);
        });

        window.addEventListener('resize', function () {
            if (modalElement.classList.contains('show')) {
                resizeCanvas();
            }
        });

        document.addEventListener('click', function (event) {
            const trigger = event.target.closest('[data-monitoring-signature-trigger]');

            if (!trigger) {
                return;
            }

            const monitoringId = trigger.getAttribute('data-monitoring-id') || '';
            const petugas = trigger.getAttribute('data-monitoring-petugas') || 'Petugas';
            const ruangan = trigger.getAttribute('data-monitoring-ruangan') || 'Ruangan';
            const tanggal = trigger.getAttribute('data-monitoring-tanggal') || '';
            const existingSignature = trigger.getAttribute('data-monitoring-signature') || '';

            idInput.value = monitoringId;
            output.value = existingSignature;
            if (title) {
                title.textContent = existingSignature !== '' ? 'Ubah Paraf' : 'Lengkapi Paraf';
            }
            if (subtitle) {
                subtitle.textContent = petugas + ' - ' + ruangan + ' - ' + tanggal;
            }
            if (errorBox) {
                errorBox.classList.add('d-none');
                errorBox.textContent = '';
            }

            signaturePad.clear();
            paintCanvasBackground();

            if (existingSignature !== '') {
                signaturePad.fromDataURL(existingSignature);
                preview.src = existingSignature;
                preview.classList.remove('d-none');
                emptyState.classList.add('d-none');
            } else {
                preview.classList.add('d-none');
                preview.removeAttribute('src');
                emptyState.classList.remove('d-none');
            }

            modalInstance.show();
        });

        submitButton.addEventListener('click', function () {
            if (errorBox) {
                errorBox.classList.add('d-none');
                errorBox.textContent = '';
            }

            syncPreview();
            if (output.value.trim() === '') {
                if (errorBox) {
                    errorBox.textContent = 'Paraf/tanda tangan wajib diisi.';
                    errorBox.classList.remove('d-none');
                }
                return;
            }

            const formData = new FormData();
            formData.append('action', 'update_signature');
            formData.append('id', idInput.value);
            formData.append('signature_base64', output.value);
            formData.append('_csrf_token', csrfToken);

            submitButton.disabled = true;
            submitButton.textContent = 'Menyimpan...';

            fetch(apiEndpoint, {
                method: 'POST',
                body: formData,
                headers: {
                    'X-Requested-With': 'XMLHttpRequest'
                }
            })
                .then(function (response) {
                    return response.json().then(function (data) {
                        return {
                            ok: response.ok,
                            data: data
                        };
                    });
                })
                .then(function (result) {
                    if (!result.ok || !result.data || result.data.success !== true) {
                        throw new Error((result.data && result.data.message) || 'Paraf monitoring belum berhasil disimpan.');
                    }

                    modalInstance.hide();
                    window.setTimeout(function () {
                        window.location.reload();
                    }, 250);
                })
                .catch(function (error) {
                    if (errorBox) {
                        errorBox.textContent = error.message || 'Paraf monitoring belum berhasil disimpan.';
                        errorBox.classList.remove('d-none');
                    }
                })
                .finally(function () {
                    submitButton.disabled = false;
                    submitButton.textContent = 'Simpan Paraf';
                });
        });
    };

    initMonitoringChart();
    initMonitoringForm();
    initMonitoringHistorySignatureModal();
});
