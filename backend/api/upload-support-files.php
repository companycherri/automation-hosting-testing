<?php
// ============================================================
// POST /api/upload-support-files.php
// Upload supporting documents for a job import batch.
//
// Accepts: multipart/form-data with files[] (multi-file)
//   - Allowed types : PDF, JPG, JPEG, PNG, ZIP
//   - ZIP files     : extracted, inner PDF/JPG/PNG kept
//   - Saves to      : backend/uploads/job-files/batch_{id}/
//
// Returns: { success, batch_id, files: [{name, path}], errors }
// ============================================================

require_once __DIR__ . '/../config/cors.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Method not allowed.']);
    exit;
}

// ── Create batch folder ────────────────────────────────────
$uploadsRoot = __DIR__ . '/../uploads/job-files';
if (!is_dir($uploadsRoot)) mkdir($uploadsRoot, 0755, true);

$batchId  = 'batch_' . date('YmdHis') . '_' . rand(100, 999);
$batchDir = $uploadsRoot . DIRECTORY_SEPARATOR . $batchId . DIRECTORY_SEPARATOR;
mkdir($batchDir, 0755, true);

$allowedExts   = ['pdf', 'jpg', 'jpeg', 'png', 'zip'];
$allowedInner  = ['pdf', 'jpg', 'jpeg', 'png'];   // inside ZIP
$savedFiles    = [];
$errors        = [];
$maxFileSize   = 20 * 1024 * 1024; // 20 MB per file

// ── If no files sent, still return a valid empty batch ─────
if (empty($_FILES['files']['name']) ||
    (is_array($_FILES['files']['name']) && empty($_FILES['files']['name'][0]))) {
    echo json_encode([
        'success'  => true,
        'batch_id' => $batchId,
        'files'    => [],
        'errors'   => [],
        'message'  => 'No files uploaded — empty batch created.',
    ]);
    exit;
}

// ── Normalise $_FILES['files'] to always be an array ──────
$f = $_FILES['files'];
if (!is_array($f['name'])) {
    $f = [
        'name'     => [$f['name']],
        'tmp_name' => [$f['tmp_name']],
        'error'    => [$f['error']],
        'size'     => [$f['size']],
        'type'     => [$f['type']],
    ];
}

// ── Process each file ──────────────────────────────────────
foreach ($f['name'] as $i => $origName) {
    if (empty($origName) || $f['error'][$i] !== UPLOAD_ERR_OK) continue;

    if ($f['size'][$i] > $maxFileSize) {
        $errors[] = "Skipped '{$origName}': file too large (max 20 MB).";
        continue;
    }

    $ext  = strtolower(pathinfo($origName, PATHINFO_EXTENSION));
    $safe = preg_replace('/[^a-zA-Z0-9._-]/', '_', $origName);

    if (!in_array($ext, $allowedExts)) {
        $errors[] = "Skipped '{$origName}': type '.{$ext}' not allowed.";
        continue;
    }

    if ($ext === 'zip') {
        // ── Extract ZIP ────────────────────────────────────
        if (!class_exists('ZipArchive')) {
            $errors[] = "Cannot extract '{$origName}': ZipArchive extension not enabled.";
            continue;
        }
        $zip = new ZipArchive();
        if ($zip->open($f['tmp_name'][$i]) !== true) {
            $errors[] = "Could not open ZIP file: '{$origName}'.";
            continue;
        }
        for ($j = 0; $j < $zip->numFiles; $j++) {
            $entry    = $zip->getNameIndex($j);
            if (substr($entry, -1) === '/') continue; // skip dirs
            $entryExt = strtolower(pathinfo($entry, PATHINFO_EXTENSION));
            if (!in_array($entryExt, $allowedInner)) continue;
            $entrySafe = preg_replace('/[^a-zA-Z0-9._-]/', '_', basename($entry));
            $entryDest = $batchDir . $entrySafe;
            file_put_contents($entryDest, $zip->getFromIndex($j));
            $savedFiles[] = [
                'name'   => $entrySafe,
                'path'   => str_replace('\\', '/', realpath($entryDest)),
                'source' => "zip:{$safe}",
            ];
        }
        $zip->close();
    } else {
        // ── Direct file save ───────────────────────────────
        $dest = $batchDir . $safe;
        if (move_uploaded_file($f['tmp_name'][$i], $dest)) {
            $savedFiles[] = [
                'name'   => $safe,
                'path'   => str_replace('\\', '/', realpath($dest)),
                'source' => 'direct',
            ];
        } else {
            $errors[] = "Failed to save '{$origName}'.";
        }
    }
}

echo json_encode([
    'success'  => true,
    'batch_id' => $batchId,
    'files'    => $savedFiles,
    'errors'   => $errors,
    'message'  => count($savedFiles) . ' file(s) saved to ' . $batchId,
]);
