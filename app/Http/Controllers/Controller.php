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
}
