(function () {
    'use strict';

    const HEADERS = [
        'No', 'Tanggal/Waktu', 'Tipe', 'Pasien', 'Baris', 'Model',
        'Kehamilan', 'Glukosa', 'Tekanan Darah', 'Ketebalan Kulit',
        'Insulin', 'BMI', 'DPF', 'Usia', 'Hasil', 'Waktu (ms)'
    ];

    let allRows = [];
    let filteredRows = [];

    function init() {
        const dataEl = document.getElementById('riwayatData');
        if (!dataEl) return;

        allRows = JSON.parse(dataEl.textContent || '[]');
        filteredRows = allRows.slice();

        document.getElementById('btnApplyFilter')?.addEventListener('click', applyFilter);
        document.getElementById('btnResetFilter')?.addEventListener('click', requestResetFilter);
        document.getElementById('btnClearBatchFilter')?.addEventListener('click', clearBatchFilter);
        document.querySelectorAll('.riwayat-dl-btn').forEach(bindDownload);
        document.querySelectorAll('.btn-receipt').forEach(bindReceipt);
        document.querySelectorAll('.btn-view-batch').forEach(function (btn) {
            btn.addEventListener('click', function () {
                viewBatchResults(
                    btn.getAttribute('data-batch-id'),
                    btn.getAttribute('data-batch-file')
                );
            });
        });

        ReceiptUI.bindReceiptModal();
        applyFilter();
    }

    function viewBatchResults(batchId, batchFile) {
        const id = String(batchId || '');
        document.getElementById('filterBatchId').value = id;
        document.getElementById('filterTipe').value = 'Batch';
        document.getElementById('filterPasien').value = '';

        const banner = document.getElementById('batchFilterBanner');
        const text = document.getElementById('batchFilterText');
        if (banner && text) {
            text.textContent = 'Menampilkan hasil batch: ' + (batchFile || ('ID ' + id));
            banner.classList.remove('hidden');
        }

        applyFilter();
        document.getElementById('riwayatTableWrap')?.scrollIntoView({ behavior: 'smooth', block: 'start' });
    }

    function clearBatchFilter() {
        document.getElementById('filterBatchId').value = '';
        document.getElementById('batchFilterBanner')?.classList.add('hidden');
        applyFilter();
    }

    function requestResetFilter() {
        ConfirmUI.show(
            'Reset Filter?',
            'Semua filter akan dikembalikan ke kondisi awal dan seluruh data riwayat akan ditampilkan kembali.',
            function () {
                ['filterTipe', 'filterModel', 'filterPasien', 'filterHasil', 'filterDateFrom', 'filterDateTo', 'filterSearch', 'filterBatchId'].forEach(function (id) {
                    const el = document.getElementById(id);
                    if (el) el.value = '';
                });
                document.getElementById('batchFilterBanner')?.classList.add('hidden');
                filteredRows = allRows.slice();
                renderTable();
            }
        );
    }

    function applyFilter() {
        const tipe = document.getElementById('filterTipe')?.value || '';
        const model = document.getElementById('filterModel')?.value || '';
        const pasienQ = (document.getElementById('filterPasien')?.value || '').toLowerCase().trim();
        const hasil = document.getElementById('filterHasil')?.value || '';
        const dateFrom = document.getElementById('filterDateFrom')?.value || '';
        const dateTo = document.getElementById('filterDateTo')?.value || '';
        const search = (document.getElementById('filterSearch')?.value || '').toLowerCase().trim();
        const batchId = document.getElementById('filterBatchId')?.value || '';

        filteredRows = allRows.filter(function (r) {
            if (batchId && String(r.batch_id) !== batchId) return false;
            if (tipe && r.tipe !== tipe) return false;
            if (model && r.model_key !== model) return false;
            if (pasienQ && (r.pasien || '').toLowerCase().indexOf(pasienQ) === -1) return false;
            if (hasil === '1' && r.hasil_prediksi !== 1) return false;
            if (hasil === '0' && r.hasil_prediksi !== 0) return false;
            if (dateFrom && r.created_at.slice(0, 10) < dateFrom) return false;
            if (dateTo && r.created_at.slice(0, 10) > dateTo) return false;
            if (search) {
                const hay = [
                    r.tipe, r.pasien, r.batch_file, r.model_label, r.hasil_label,
                    r.pregnancies, r.glucose, r.created_at
                ].join(' ').toLowerCase();
                if (hay.indexOf(search) === -1) return false;
            }
            return true;
        });

        renderTable();
    }

    function renderTable() {
        const tbody = document.getElementById('riwayatBody');
        const empty = document.getElementById('riwayatEmpty');
        const wrap = document.getElementById('riwayatTableWrap');
        if (!tbody) return;

        if (filteredRows.length === 0) {
            tbody.innerHTML = '';
            if (wrap) wrap.style.display = 'none';
            if (empty) empty.style.display = 'block';
            return;
        }

        if (wrap) wrap.style.display = '';
        if (empty) empty.style.display = 'none';

        tbody.innerHTML = filteredRows.map(function (r, i) {
            const badgeCls = r.hasil_prediksi ? 'diabetes' : 'normal';
            const tipeCls = r.tipe === 'Batch' ? 'badge-batch' : 'badge-form';
            const dataAttr = escAttr(JSON.stringify(r));
            return '<tr data-batch-id="' + (r.batch_id || '') + '">' +
                '<td>' + (i + 1) + '</td>' +
                '<td>' + esc(r.created_at) + '</td>' +
                '<td><span class="badge ' + tipeCls + '">' + esc(r.tipe) + '</span></td>' +
                '<td class="font-medium text-slate-800">' + esc(r.pasien) + '</td>' +
                '<td>' + (r.baris_no || '-') + '</td>' +
                '<td>' + esc(r.model_label) + '</td>' +
                '<td>' + r.pregnancies + '</td>' +
                '<td>' + r.glucose + '</td>' +
                '<td>' + r.blood_pressure + '</td>' +
                '<td>' + r.skin_thickness + '</td>' +
                '<td>' + r.insulin + '</td>' +
                '<td>' + r.bmi + '</td>' +
                '<td>' + r.diabetes_pedigree + '</td>' +
                '<td>' + r.age + '</td>' +
                '<td><span class="badge ' + badgeCls + '">' + esc(r.hasil_label) + '</span></td>' +
                '<td>' + Number(r.execution_time_ms).toFixed(2) + '</td>' +
                '<td><button type="button" class="btn-detail btn-receipt" data-receipt="' + dataAttr + '">Detail</button></td>' +
                '</tr>';
        }).join('');

        document.querySelectorAll('.btn-receipt').forEach(bindReceipt);
    }

    function bindReceipt(btn) {
        btn.addEventListener('click', function () {
            const raw = btn.getAttribute('data-receipt');
            if (raw) ReceiptUI.showReceipt(JSON.parse(raw));
        });
    }

    function getExportData() {
        const rows = filteredRows.map(function (r, i) {
            return [
                i + 1, r.created_at, r.tipe, r.pasien, r.baris_no || '-', r.model_label,
                r.pregnancies, r.glucose, r.blood_pressure, r.skin_thickness,
                r.insulin, r.bmi, r.diabetes_pedigree, r.age,
                r.hasil_label, Number(r.execution_time_ms).toFixed(2)
            ];
        });
        return { headers: HEADERS, rows: rows };
    }

    function bindDownload(btn) {
        btn.addEventListener('click', function () {
            const fmt = btn.getAttribute('data-format');
            const errEl = document.getElementById('riwayatDlError');
            if (errEl) errEl.textContent = '';
            if (!filteredRows.length) {
                if (errEl) errEl.textContent = 'Tidak ada data untuk diunduh.';
                return;
            }
            if (fmt === 'CSV') downloadCsv();
            else if (fmt === 'XLSX') downloadXlsx();
            else if (fmt === 'PNG') downloadTablePng();
            else if (fmt === 'PDF') downloadTablePdf();
        });
    }

    function downloadCsv() {
        const d = getExportData();
        const lines = [d.headers.join(',')];
        d.rows.forEach(function (row) {
            lines.push(row.map(function (c) { return '"' + String(c).replace(/"/g, '""') + '"'; }).join(','));
        });
        const blob = new Blob(['\ufeff' + lines.join('\n')], { type: 'text/csv;charset=utf-8' });
        triggerDownload(blob, 'riwayat_prediksi.csv');
    }

    function downloadXlsx() {
        const d = getExportData();
        const form = document.createElement('form');
        form.method = 'POST';
        form.action = 'download.php?format=xlsx&source=riwayat';
        const input = document.createElement('input');
        input.type = 'hidden';
        input.name = 'data';
        input.value = JSON.stringify({ headers: d.headers, rows: d.rows, filename: 'riwayat_prediksi' });
        form.appendChild(input);
        document.body.appendChild(form);
        form.submit();
        form.remove();
    }

    function downloadTablePng() {
        const table = document.getElementById('riwayatTable');
        if (!table || typeof html2canvas === 'undefined') return;
        html2canvas(table, { scale: 2, backgroundColor: '#fff', width: table.scrollWidth }).then(function (canvas) {
            canvas.toBlob(function (blob) { triggerDownload(blob, 'riwayat_prediksi.png'); });
        });
    }

    function downloadTablePdf() {
        const table = document.getElementById('riwayatTable');
        if (!table || typeof html2canvas === 'undefined' || typeof window.jspdf === 'undefined') return;
        html2canvas(table, { scale: 1.5, backgroundColor: '#fff', width: table.scrollWidth }).then(function (canvas) {
            const { jsPDF } = window.jspdf;
            const img = canvas.toDataURL('image/png');
            const pdf = new jsPDF({ orientation: 'landscape', unit: 'mm', format: 'a4' });
            const pw = pdf.internal.pageSize.getWidth();
            const ph = pdf.internal.pageSize.getHeight();
            const ratio = Math.min(pw / canvas.width, ph / canvas.height);
            pdf.addImage(img, 'PNG', (pw - canvas.width * ratio) / 2, 10, canvas.width * ratio, canvas.height * ratio);
            pdf.save('riwayat_prediksi.pdf');
        });
    }

    function triggerDownload(blob, name) {
        const a = document.createElement('a');
        a.href = URL.createObjectURL(blob);
        a.download = name;
        a.click();
        URL.revokeObjectURL(a.href);
    }

    function esc(s) {
        const d = document.createElement('div');
        d.textContent = s;
        return d.innerHTML;
    }

    function escAttr(s) {
        return s.replace(/&/g, '&amp;').replace(/'/g, '&#39;').replace(/"/g, '&quot;').replace(/</g, '&lt;');
    }

    document.addEventListener('DOMContentLoaded', init);
})();
