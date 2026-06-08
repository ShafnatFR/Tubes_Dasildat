<?php
/** @var string $activePage — form | batch | test | riwayat */
$activePage = $activePage ?? '';
$navItems = [
    'form'    => ['label' => 'Form Prediksi',   'href' => 'form_prediksi.php'],
    'batch'   => ['label' => 'Prediksi Batch',  'href' => 'batch_upload.php'],
    'riwayat' => ['label' => 'Riwayat',         'href' => 'riwayat.php'],
];
?>
<nav class="main-nav">
    <?php foreach ($navItems as $key => $item): ?>
        <a href="<?php echo htmlspecialchars($item['href']); ?>"
           class="<?php echo ($activePage === $key) ? 'active' : ''; ?>">
            <?php echo htmlspecialchars($item['label']); ?>
        </a>
    <?php endforeach; ?>
</nav>
