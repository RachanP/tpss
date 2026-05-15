<?php

namespace App\Http\Controllers;

abstract class Controller
{
    /**
     * Open a CSV file handle with automatic encoding detection.
     * Supports UTF-8 (with/without BOM) and Windows-874 (TIS-620 / Excel Thai default).
     * Returns a seeked php://temp stream ready for fgetcsv().
     */
    protected function openCsvHandle(\Illuminate\Http\UploadedFile $file): mixed
    {
        $content = file_get_contents($file->getPathname());

        // Strip UTF-8 BOM if present
        $content = str_replace("\xEF\xBB\xBF", '', $content);

        // If not valid UTF-8, assume Windows-874 (saved by Excel on Thai Windows)
        if (!mb_check_encoding($content, 'UTF-8')) {
            $content = mb_convert_encoding($content, 'UTF-8', 'Windows-874');
        }

        $handle = fopen('php://temp', 'r+');
        fwrite($handle, $content);
        rewind($handle);
        return $handle;
    }

    protected function normalizeCsvHeader(array $header): array
    {
        return array_map(fn($h) => trim((string) $h), $header);
    }

    protected function missingCsvHeaders(array $header, array $required): array
    {
        return array_values(array_diff($required, $header));
    }

    protected function csvRowHasData(array $data): bool
    {
        return count(array_filter($data, fn($v) => trim((string) $v) !== '')) > 0;
    }

    protected function combineCsvRow(array $header, array $data, int $row, array &$errors): ?array
    {
        $headerCount = count($header);
        $dataCount   = count($data);

        if ($dataCount > $headerCount) {
            $errors[] = "แถว {$row}: จำนวนคอลัมน์ ({$dataCount}) มากกว่าหัวตาราง ({$headerCount})";
            return null;
        }

        // Google Sheets strips trailing empty cells — pad to match header length
        if ($dataCount < $headerCount) {
            $data = array_pad($data, $headerCount, '');
        }

        $combined = array_combine($header, $data);
        if ($combined === false) {
            $errors[] = "แถว {$row}: ไม่สามารถอ่านข้อมูล CSV ได้";
            return null;
        }

        return $combined;
    }
}
