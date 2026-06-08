(function () {
    'use strict';

    const MODEL_COL_MAP = {
        'SVM': 'col-svm',
        'K-NN': 'col-knn',
        'Decision Tree': 'col-dt',
        'Neural Network': 'col-nn'
    };

    function formatTimeWarn(ms) {
        const el = document.createElement('span');
        el.className = 'time-value';
        el.textContent = Number(ms).toFixed(2) + ' ms';
        if (Number(ms) > 10000) {
            const w = document.createElement('span');
            w.className = 'time-warn';
            w.title = 'Proses lambat (>10 detik)';
            w.textContent = '⚠ Lambat';
            el.appendChild(w);
        }
        return el;
    }

    function initTimeWarnings() {
        document.querySelectorAll('[data-exec-ms]').forEach(function (node) {
            const ms = parseFloat(node.getAttribute('data-exec-ms'));
            if (!isNaN(ms) && ms > 10000 && !node.querySelector('.time-warn')) {
                const w = document.createElement('span');
                w.className = 'time-warn';
                w.textContent = '⚠ Lambat';
                node.appendChild(w);
            }
        });
    }

    function initModal() {
        const overlay = document.getElementById('detailModal');
        if (!overlay) return;

        overlay.addEventListener('click', function (e) {
            if (e.target === overlay) closeModal();
        });

        const closeBtn = overlay.querySelector('.modal-close');
        if (closeBtn) closeBtn.addEventListener('click', closeModal);

        document.querySelectorAll('.btn-detail').forEach(function (btn) {
            btn.addEventListener('click', function () {
                const data = btn.getAttribute('data-detail');
                if (data) {
                    try { showDetail(JSON.parse(data)); } catch (err) { showDetail(null); }
                }
            });
        });
    }

    function valOrNA(v) {
        return (v === null || v === undefined || v === '') ? 'Data tidak tersedia' : String(v);
    }

    function showDetail(data) {
        const overlay = document.getElementById('detailModal');
        const body = document.getElementById('detailModalBody');
        if (!overlay || !body) return;

        if (!data) {
            body.innerHTML = '<p>Data tidak tersedia</p>';
            overlay.classList.add('open');
            return;
        }

        const fields = [
            ['Kehamilan (Pregnancies)', data.features?.[0]],
            ['Glukosa (Glucose)', data.features?.[1]],
            ['Tekanan Darah (BloodPressure)', data.features?.[2]],
            ['Ketebalan Kulit (SkinThickness)', data.features?.[3]],
            ['Insulin', data.features?.[4]],
            ['BMI', data.features?.[5]],
            ['Diabetes Pedigree', data.features?.[6]],
            ['Usia (Age)', data.features?.[7]]
        ];

        let html = '<div class="modal-grid">';
        fields.forEach(function (f) {
            html += '<div class="label">' + f[0] + '</div><div>' + valOrNA(f[1]) + '</div>';
        });
        html += '</div><div class="modal-preds"><strong>Hasil Prediksi</strong><table><tbody>';

        const preds = data.predictions || {};
        ['SVM', 'K-NN', 'Decision Tree', 'Neural Network'].forEach(function (m) {
            html += '<tr><td>' + m + '</td><td>' + valOrNA(preds[m]) + '</td></tr>';
        });
        html += '</tbody></table></div>';
        body.innerHTML = html;
        overlay.classList.add('open');
    }

    function closeModal() {
        const overlay = document.getElementById('detailModal');
        if (overlay) overlay.classList.remove('open');
    }

    function initFilter() {
        const boxes = document.querySelectorAll('.filter-model input[type="checkbox"]');
        if (!boxes.length) return;

        boxes.forEach(function (cb) {
            cb.addEventListener('change', function () {
                const checked = Array.from(boxes).filter(function (b) { return b.checked; });
                const msg = document.getElementById('filterMsg');
                if (checked.length === 0) {
                    cb.checked = true;
                    if (msg) {
                        msg.textContent = 'Setidaknya satu model harus ditampilkan';
                        setTimeout(function () { msg.textContent = ''; }, 2500);
                    }
                    return;
                }
                applyFilter();
            });
        });
        applyFilter();
    }

    function applyFilter() {
        const active = {};
        document.querySelectorAll('.filter-model input[type="checkbox"]').forEach(function (cb) {
            active[cb.value] = cb.checked;
        });
        Object.keys(MODEL_COL_MAP).forEach(function (model) {
            const cls = MODEL_COL_MAP[model];
            const show = active[model] !== false;
            document.querySelectorAll('.' + cls).forEach(function (el) {
                el.style.display = show ? '' : 'none';
            });
        });
    }

    function initDownload() {
        document.querySelectorAll('.download-wrap').forEach(function (wrap) {
            const toggle = wrap.querySelector('.download-toggle');
            const menu = wrap.querySelector('.download-menu');
            const errEl = wrap.querySelector('.download-error');
            if (!toggle || !menu) return;

            toggle.addEventListener('click', function (e) {
                e.stopPropagation();
                document.querySelectorAll('.download-menu.open').forEach(function (m) {
                    if (m !== menu) m.classList.remove('open');
                });
                menu.classList.toggle('open');
            });

            menu.querySelectorAll('button[data-format]').forEach(function (btn) {
                btn.addEventListener('click', function () {
                    const fmt = btn.getAttribute('data-format');
                    menu.classList.remove('open');
                    handleDownload(fmt, wrap, errEl);
                });
            });
        });

        document.addEventListener('click', function () {
            document.querySelectorAll('.download-menu.open').forEach(function (m) {
                m.classList.remove('open');
            });
        });
    }

    function showDownloadError(errEl, format) {
        if (errEl) {
            errEl.textContent = 'Gagal mengunduh format ' + format + '. Coba format lain atau ulangi proses.';
        }
    }

    function handleDownload(format, wrap, errEl) {
        if (errEl) errEl.textContent = '';
        const tableId = wrap.getAttribute('data-table-id');
        const dlType = wrap.getAttribute('data-dl-type') || 'session';
        const fileParam = wrap.getAttribute('data-file') || '';

        if (format === 'PNG') {
            const table = document.getElementById(tableId);
            if (!table || typeof html2canvas === 'undefined') {
                showDownloadError(errEl, 'PNG');
                return;
            }
            html2canvas(table, { scale: 2, backgroundColor: '#ffffff' }).then(function (canvas) {
                const link = document.createElement('a');
                link.download = 'hasil_prediksi.png';
                link.href = canvas.toDataURL('image/png');
                link.click();
            }).catch(function () { showDownloadError(errEl, 'PNG'); });
            return;
        }

        if (format === 'PDF') {
            try {
                const meta = wrap.getAttribute('data-export');
                if (!meta || typeof window.jspdf === 'undefined') {
                    showDownloadError(errEl, 'PDF');
                    return;
                }
                const data = JSON.parse(meta);
                const { jsPDF } = window.jspdf;
                const doc = new jsPDF({ orientation: 'landscape' });
                let y = 12;
                doc.setFontSize(11);
                doc.text('Hasil Prediksi Diabetes', 10, y);
                y += 8;
                if (data.headers && data.rows) {
                    doc.setFontSize(8);
                    doc.text(data.headers.join(' | '), 10, y);
                    y += 6;
                    data.rows.forEach(function (row) {
                        doc.text(row.join(' | '), 10, y);
                        y += 5;
                        if (y > 190) { doc.addPage(); y = 12; }
                    });
                }
                doc.save('hasil_prediksi.pdf');
            } catch (e) {
                showDownloadError(errEl, 'PDF');
            }
            return;
        }

        let url = 'download.php?format=' + encodeURIComponent(format);
        if (dlType === 'file' && fileParam) {
            url += '&file=' + encodeURIComponent(fileParam);
        } else if (dlType === 'session') {
            url += '&source=session';
        }
        window.location.href = url;
    }

    function initPrediksiUlang() {
        const btn = document.getElementById('btnPrediksiUlang');
        if (!btn) return;
        btn.addEventListener('click', function () {
            document.getElementById('sectionForm')?.classList.remove('section-hidden');
            document.getElementById('sectionResult')?.classList.add('section-hidden');
        });
    }

    document.addEventListener('DOMContentLoaded', function () {
        initModal();
        initFilter();
        initDownload();
        initTimeWarnings();
        initPrediksiUlang();

        const loader = document.getElementById('pageLoader');
        if (loader) loader.classList.add('hidden');
    });

    window.DiabetesScan = { showDetail: showDetail, closeModal: closeModal, formatTimeWarn: formatTimeWarn };
})();
