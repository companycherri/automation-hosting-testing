<?php
/**
 * Run once to create minimal valid PDF placeholder files.
 * Visit: http://localhost/mini-automation/samples/sample-files/create_sample_pdfs.php
 */
$dir = __DIR__;
$files = [
    'invoice_001.pdf',
    'invoice_002.pdf',
    'invoice_003.pdf',
    'invoice_004.pdf',
    'invoice_005.pdf',
];

// Minimal valid PDF content
function minimal_pdf(string $title): string {
    return "%PDF-1.4\n1 0 obj\n<< /Type /Catalog /Pages 2 0 R >>\nendobj\n" .
           "2 0 obj\n<< /Type /Pages /Kids [3 0 R] /Count 1 >>\nendobj\n" .
           "3 0 obj\n<< /Type /Page /Parent 2 0 R /MediaBox [0 0 612 792]\n" .
           "   /Contents 4 0 R /Resources << /Font << /F1 5 0 R >> >> >>\nendobj\n" .
           "4 0 obj\n<< /Length 44 >>\nstream\nBT /F1 14 Tf 100 700 Td ($title) Tj ET\nendstream\nendobj\n" .
           "5 0 obj\n<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica >>\nendobj\n" .
           "xref\n0 6\n0000000000 65535 f \ntrailer\n<< /Size 6 /Root 1 0 R >>\nstartxref\n0\n%%EOF\n";
}

$created = [];
foreach ($files as $f) {
    $path = $dir . '/' . $f;
    file_put_contents($path, minimal_pdf(pathinfo($f, PATHINFO_FILENAME)));
    $created[] = $f . ' (' . filesize($path) . ' bytes)';
}

header('Content-Type: text/plain');
echo "Created:\n" . implode("\n", $created) . "\n";
