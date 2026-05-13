<?php
// ============================================================
// SimpleXlsxReader — Pure PHP xlsx/csv reader (no Composer)
// Reads the first sheet of an xlsx file using ZipArchive + SimpleXML
// ============================================================

class SimpleXlsxReader {

    /**
     * Read xlsx or csv file — extension detected from filepath.
     * Returns: ['headers' => [...], 'rows' => [[col=>val,...],...]]
     */
    public static function read(string $filepath): array {
        $ext = strtolower(pathinfo($filepath, PATHINFO_EXTENSION));
        return self::readAs($filepath, $ext);
    }

    /**
     * Read file with explicitly provided extension.
     * Use this when the filepath is a temp file without a proper extension.
     */
    public static function readAs(string $filepath, string $ext): array {
        $ext = strtolower(trim($ext, '.'));
        if ($ext === 'csv')           return self::readCsv($filepath);
        if (in_array($ext, ['xlsx', 'xls'])) return self::readXlsx($filepath);
        throw new RuntimeException("Unsupported file type: $ext (use .xlsx or .csv)");
    }

    // ── CSV reader ─────────────────────────────────────────
    private static function readCsv(string $filepath): array {
        $rows    = [];
        $headers = [];
        $fh      = fopen($filepath, 'r');
        if (!$fh) throw new RuntimeException("Cannot open CSV file");

        $line = 0;
        while (($data = fgetcsv($fh, 0, ',')) !== false) {
            if ($line === 0) {
                // First row = headers
                $headers = array_map('trim', $data);
            } else {
                if (empty(array_filter($data))) continue; // skip blank rows
                $row = [];
                foreach ($headers as $i => $h) {
                    $row[$h] = isset($data[$i]) ? trim($data[$i]) : '';
                }
                $rows[] = $row;
            }
            $line++;
        }
        fclose($fh);
        return ['headers' => $headers, 'rows' => $rows];
    }

    // ── XLSX reader ────────────────────────────────────────
    private static function readXlsx(string $filepath): array {
        if (!class_exists('ZipArchive')) {
            throw new RuntimeException("ZipArchive PHP extension is required for xlsx support.");
        }

        $zip = new ZipArchive();
        if ($zip->open($filepath) !== true) {
            throw new RuntimeException("Cannot open xlsx file (may be corrupt).");
        }

        // 1. Read shared strings
        $strings = [];
        $ssXml = $zip->getFromName('xl/sharedStrings.xml');
        if ($ssXml) {
            $xml = @simplexml_load_string($ssXml);
            if ($xml) {
                foreach ($xml->si as $si) {
                    // May be plain text or rich text (r elements)
                    if (isset($si->t)) {
                        $strings[] = (string)$si->t;
                    } elseif (isset($si->r)) {
                        $text = '';
                        foreach ($si->r as $r) {
                            $text .= (string)($r->t ?? '');
                        }
                        $strings[] = $text;
                    } else {
                        $strings[] = '';
                    }
                }
            }
        }

        // 2. Read first worksheet
        // Find sheet1 from workbook relationships
        $sheetPath = 'xl/worksheets/sheet1.xml';
        $wsXml = $zip->getFromName($sheetPath);
        $zip->close();

        if (!$wsXml) {
            throw new RuntimeException("No worksheet found in xlsx file.");
        }

        $xml = @simplexml_load_string($wsXml);
        if (!$xml) throw new RuntimeException("Cannot parse worksheet XML.");

        $allRows = [];
        $maxCol  = 0;

        foreach ($xml->sheetData->row as $row) {
            $rowIndex = (int)$row['r'] - 1;
            $rowData  = [];
            foreach ($row->c as $cell) {
                $ref  = (string)$cell['r'];
                $type = (string)$cell['t'];
                $val  = isset($cell->v) ? (string)$cell->v : '';

                // Shared string
                if ($type === 's') {
                    $val = $strings[(int)$val] ?? '';
                }
                // Inline string
                if ($type === 'inlineStr' && isset($cell->is->t)) {
                    $val = (string)$cell->is->t;
                }
                // Date serial → date string
                if ($type === '' && isset($cell['s']) && is_numeric($val) && (float)$val > 40000) {
                    // Could be a date, leave as-is for now
                }

                $colIndex = self::colToIndex(preg_replace('/[0-9]/', '', $ref));
                $rowData[$colIndex] = $val;
                $maxCol = max($maxCol, $colIndex);
            }
            $allRows[$rowIndex] = $rowData;
        }

        if (empty($allRows)) return ['headers' => [], 'rows' => []];

        ksort($allRows);
        $allRows = array_values($allRows);

        // First row = headers
        $headerRow = $allRows[0];
        $headers   = [];
        for ($i = 0; $i <= $maxCol; $i++) {
            $headers[$i] = trim($headerRow[$i] ?? '');
        }

        $rows = [];
        for ($r = 1; $r < count($allRows); $r++) {
            $rowData = $allRows[$r];
            // Skip blank rows
            $vals = array_filter(array_values($rowData), fn($v) => $v !== '');
            if (empty($vals)) continue;

            $row = [];
            foreach ($headers as $i => $h) {
                if ($h !== '') {
                    $row[$h] = trim($rowData[$i] ?? '');
                }
            }
            $rows[] = $row;
        }

        return [
            'headers' => array_values(array_filter($headers, fn($h) => $h !== '')),
            'rows'    => $rows,
        ];
    }

    // ── Column letter → 0-based index ─────────────────────
    private static function colToIndex(string $col): int {
        $result = 0;
        $col    = strtoupper($col);
        for ($i = 0; $i < strlen($col); $i++) {
            $result = $result * 26 + (ord($col[$i]) - 64);
        }
        return $result - 1;
    }
}
