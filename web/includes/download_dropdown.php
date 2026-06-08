<?php
/** @var string $tableId */
/** @var string $dlType — session | file */
/** @var string $fileParam */
/** @var string $exportJson */
$tableId = $tableId ?? 'resultTable';
$dlType = $dlType ?? 'session';
$fileParam = $fileParam ?? '';
$exportJson = $exportJson ?? '{}';
?>
<div class="download-wrap"
     data-table-id="<?php echo htmlspecialchars($tableId); ?>"
     data-dl-type="<?php echo htmlspecialchars($dlType); ?>"
     data-file="<?php echo htmlspecialchars($fileParam); ?>"
     data-export="<?php echo htmlspecialchars($exportJson); ?>">
    <button type="button" class="download-toggle">⬇ Unduh Hasil ▾</button>
    <div class="download-menu">
        <button type="button" data-format="PNG">PNG</button>
        <button type="button" data-format="PDF">PDF</button>
        <button type="button" data-format="CSV">CSV</button>
        <button type="button" data-format="XLSX">XLSX</button>
    </div>
    <div class="download-error"></div>
</div>
