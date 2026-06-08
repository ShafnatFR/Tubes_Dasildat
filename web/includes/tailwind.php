<?php /** Tailwind CSS — fase migrasi dari common.css */ ?>
<script src="https://cdn.tailwindcss.com"></script>
<script>
tailwind.config = {
    theme: {
        extend: {
            colors: {
                primary: { DEFAULT: '#3498db', dark: '#2980b9' },
                success: { DEFAULT: '#27ae60', dark: '#219a52' },
                danger:  { DEFAULT: '#e74c3c', dark: '#c0392b' },
            }
        }
    }
};
</script>
<link rel="stylesheet" href="includes/common.css">
