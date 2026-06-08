(function (global) {
    'use strict';

    function esc(s) {
        const d = document.createElement('div');
        d.textContent = s == null ? '' : String(s);
        return d.innerHTML;
    }

    function rowItem(label, val) {
        return '<tr><td>' + label + '</td><td><strong>' + esc(val) + '</strong></td></tr>';
    }

    function buildReceiptHtml(r) {
        const isDiabetes = r.hasil_prediksi === 1;
        const resultCls = isDiabetes ? 'receipt-result-warn' : 'receipt-result-ok';
        const resultText = isDiabetes ? 'POSITIF DIABETES' : 'NEGATIF / NORMAL';
        const resultSub = isDiabetes ? 'Risiko Diabetes Terdeteksi' : 'Tidak Terdeteksi Diabetes';
        const refId = r.id ? String(r.id).padStart(6, '0') : 'BARU';
        const pasien = r.pasien || r.nama_pasien || '-';

        let modelsBlock = '';
        if (r.all_results && Object.keys(r.all_results).length) {
            modelsBlock = '<div class="receipt-section-title">Hasil Semua Model</div><table class="receipt-table">';
            Object.keys(r.all_results).forEach(function (m) {
                modelsBlock += '<tr><td>' + esc(m) + '</td><td><strong>' + esc(r.all_results[m]) + '</strong></td></tr>';
            });
            modelsBlock += '</table><div class="receipt-divider"></div>';
        }

        return '<div class="receipt-paper" id="receiptPaper">' +
            '<div class="receipt-header">' +
            '<div class="receipt-logo">🩺</div>' +
            '<div class="receipt-clinic"><h2>DiabetesScan Online</h2>' +
            '<p>Hasil Pemeriksaan Risiko Diabetes</p></div></div>' +
            '<div class="receipt-meta">' +
            '<div><span>No. Ref</span><strong>DS-' + refId + '</strong></div>' +
            '<div><span>Tanggal</span><strong>' + esc(r.created_at || new Date().toLocaleString('id-ID')) + '</strong></div>' +
            '<div><span>Pasien</span><strong>' + esc(pasien) + '</strong></div>' +
            '<div><span>Model</span><strong>' + esc(r.model_label || '-') + '</strong></div>' +
            '</div><div class="receipt-divider"></div>' +
            '<div class="receipt-section-title">Data Pemeriksaan Pasien</div>' +
            '<table class="receipt-table">' +
            rowItem('Kehamilan (Pregnancies)', r.pregnancies) +
            rowItem('Kadar Glukosa (mg/dL)', r.glucose) +
            rowItem('Tekanan Darah (mmHg)', r.blood_pressure) +
            rowItem('Ketebalan Kulit (mm)', r.skin_thickness) +
            rowItem('Insulin (mu U/ml)', r.insulin) +
            rowItem('BMI (kg/m²)', r.bmi) +
            rowItem('Diabetes Pedigree', r.diabetes_pedigree) +
            rowItem('Usia (tahun)', r.age) +
            '</table><div class="receipt-divider"></div>' +
            modelsBlock +
            '<div class="receipt-result ' + resultCls + '">' +
            '<div class="receipt-result-label">HASIL PEMERIKSAAN</div>' +
            '<div class="receipt-result-value">' + resultText + '</div>' +
            '<div class="receipt-result-sub">' + resultSub + '</div></div>' +
            '<div class="receipt-footer-meta">Waktu eksekusi: ' + Number(r.execution_time_ms || 0).toFixed(2) + ' ms</div>' +
            '<div class="receipt-disclaimer">Dokumen ini dihasilkan secara otomatis oleh sistem prediksi ML. ' +
            'Bukan diagnosis medis resmi — konsultasikan dengan tenaga kesehatan profesional.</div>' +
            '<div class="receipt-footer">*** Terima Kasih ***</div></div>';
    }

    function buildBatchReceiptHtml(b) {
        return '<div class="receipt-paper" id="receiptPaper">' +
            '<div class="receipt-header">' +
            '<div class="receipt-logo">📂</div>' +
            '<div class="receipt-clinic"><h2>DiabetesScan Online</h2>' +
            '<p>Laporan Prediksi Batch</p></div></div>' +
            '<div class="receipt-meta">' +
            '<div><span>No. Ref</span><strong>BATCH-' + String(b.id).padStart(6, '0') + '</strong></div>' +
            '<div><span>Tanggal</span><strong>' + esc(b.created_at) + '</strong></div>' +
            '<div><span>Nama File</span><strong>' + esc(b.nama_file) + '</strong></div>' +
            '<div><span>Model</span><strong>' + esc(b.model_label) + '</strong></div>' +
            '</div><div class="receipt-divider"></div>' +
            '<div class="receipt-section-title">Ringkasan Proses</div>' +
            '<table class="receipt-table">' +
            rowItem('Jumlah Data Diproses', b.jumlah_baris + ' baris') +
            rowItem('Waktu Upload', Number(b.upload_time_s).toFixed(2) + ' detik') +
            rowItem('Waktu Eksekusi', Number(b.execution_time_s).toFixed(2) + ' detik') +
            rowItem('Total Waktu', (Number(b.upload_time_s) + Number(b.execution_time_s)).toFixed(2) + ' detik') +
            '</table>' +
            (b.preview_note ? '<div class="receipt-footer-meta">' + esc(b.preview_note) + '</div>' : '') +
            '<div class="receipt-disclaimer">Laporan batch ini mencatat metadata unggahan CSV. ' +
            'Unduh file hasil prediksi dari halaman Prediksi Batch jika diperlukan.</div>' +
            '<div class="receipt-footer">*** Terima Kasih ***</div></div>';
    }

    function openReceipt(html) {
        const body = document.getElementById('receiptBody');
        const overlay = document.getElementById('receiptModal');
        if (!body || !overlay) return;
        body.innerHTML = html;
        overlay.classList.add('open');
    }

    function closeReceipt() {
        document.getElementById('receiptModal')?.classList.remove('open');
    }

    function saveReceiptPng(filename) {
        const paper = document.getElementById('receiptPaper');
        if (!paper || typeof html2canvas === 'undefined') return;
        html2canvas(paper, { scale: 2, backgroundColor: '#fff' }).then(function (canvas) {
            canvas.toBlob(function (blob) {
                const a = document.createElement('a');
                a.href = URL.createObjectURL(blob);
                a.download = filename || 'struk_pemeriksaan.png';
                a.click();
                URL.revokeObjectURL(a.href);
            });
        });
    }

    function saveReceiptPdf(filename) {
        const paper = document.getElementById('receiptPaper');
        if (!paper || typeof html2canvas === 'undefined' || typeof window.jspdf === 'undefined') return;
        html2canvas(paper, { scale: 2, backgroundColor: '#fff' }).then(function (canvas) {
            const { jsPDF } = window.jspdf;
            const img = canvas.toDataURL('image/png');
            const pdf = new jsPDF({ orientation: 'portrait', unit: 'mm', format: [80, Math.max(160, canvas.height * 0.35)] });
            const pw = pdf.internal.pageSize.getWidth();
            const ratio = pw / canvas.width;
            pdf.addImage(img, 'PNG', 0, 0, pw, canvas.height * ratio);
            pdf.save(filename || 'struk_pemeriksaan.pdf');
        });
    }

    function bindReceiptModal() {
        const overlay = document.getElementById('receiptModal');
        overlay?.addEventListener('click', function (e) {
            if (e.target === overlay) closeReceipt();
        });
        document.getElementById('receiptClose')?.addEventListener('click', closeReceipt);
        document.getElementById('receiptPrint')?.addEventListener('click', function () { window.print(); });
        document.getElementById('receiptSavePng')?.addEventListener('click', function () { saveReceiptPng(); });
        document.getElementById('receiptSavePdf')?.addEventListener('click', function () { saveReceiptPdf(); });
    }

    global.ReceiptUI = {
        buildReceiptHtml: buildReceiptHtml,
        buildBatchReceiptHtml: buildBatchReceiptHtml,
        openReceipt: openReceipt,
        closeReceipt: closeReceipt,
        saveReceiptPng: saveReceiptPng,
        saveReceiptPdf: saveReceiptPdf,
        bindReceiptModal: bindReceiptModal,
        showReceipt: function (r) { openReceipt(buildReceiptHtml(r)); },
        showBatchReceipt: function (b) { openReceipt(buildBatchReceiptHtml(b)); }
    };
})(window);
