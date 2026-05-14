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
        return array_map(fn ($value) => trim((string) $value), $header);
    }

    protected function missingCsvHeaders(array $header, array $requiredHeaders): array
    {
        return array_values(array_diff($requiredHeaders, $header));
    }

    protected function csvRowHasData(array $data): bool
    {
        return count(array_filter($data, fn ($value) => trim((string) $value) !== '')) > 0;
    }

    protected function combineCsvRow(array $header, array $data, int $row, array &$errors): ?array
    {
        if (count($data) !== count($header)) {
            $errors[] = "แถว {$row}: จำนวนคอลัมน์ไม่ตรงกับหัวตาราง";
            return null;
        }

        $combined = array_combine($header, $data);
        if ($combined === false) {
            $errors[] = "แถว {$row}: ไม่สามารถอ่านข้อมูล CSV ได้";
            return null;
        }

        return $combined;
    }
}
