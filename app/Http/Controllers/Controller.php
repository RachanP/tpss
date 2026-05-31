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
        return array_map(function ($h) {
            $normalized = trim(rtrim(trim((string) $h), '*'));
            $normalized = ltrim($normalized, "\xEF\xBB\xBF");

            return $this->csvHeaderAliases()[$normalized] ?? $normalized;
        }, $header);
    }

    /**
     * Thai template headers are user-facing; importers keep using stable English field names internally.
     *
     * @return array<string, string>
     */
    protected function csvHeaderAliases(): array
    {
        return [
            // Users
            'คำนำหน้า' => 'prefix',
            'ชื่อ-นามสกุล' => 'name',
            'อีเมล' => 'email',
            'ชื่อผู้ใช้' => 'username',
            'รหัสผ่านเริ่มต้น' => 'password',
            'บทบาท' => 'roles',
            'บทบาทหลัก' => 'primary_role',
            'รหัสพนักงาน' => 'employee_id',
            'ตำแหน่งทางวิชาการ' => 'title',
            'วุฒิการศึกษา' => 'academic_degree',
            'ภาควิชา' => 'department_name',
            'ประเภทการจ้างงาน' => 'employment_type',
            'วันที่บรรจุ' => 'hired_date',
            'สัดส่วนการสอน' => 'teaching_pct',
            'สัดส่วนวิจัย' => 'research_pct',
            'สัดส่วนบริการวิชาการ' => 'service_pct',
            'สัดส่วนศิลปวัฒนธรรม' => 'culture_pct',
            'สัดส่วนงานอื่นๆ' => 'other_pct',
            'บทบาท (คั่นด้วย |)' => 'roles',
            'ชื่อภาควิชา' => 'department_name',
            'วันที่บรรจุ (DD/MM/ปีพ.ศ.)' => 'hired_date',
            '% การสอน' => 'teaching_pct',
            '% วิจัย' => 'research_pct',
            '% บริการวิชาการ' => 'service_pct',
            '% ศิลปวัฒนธรรม' => 'culture_pct',
            '% งานอื่นๆ' => 'other_pct',

            // Rooms
            'รหัสห้อง/สถานที่' => 'room_code',
            'ชื่อห้อง/สถานที่' => 'room_name',
            'ชื่อห้องหรือสถานที่' => 'room_name',
            'ประเภทสถานที่' => 'location_type_name',
            'อาคาร' => 'building',
            'ชื่ออาคาร' => 'building',
            'ความจุ' => 'capacity',
            'ความจุ (จำนวนที่นั่ง)' => 'capacity',
            'ที่อยู่' => 'address',
            'ที่อยู่ (สำหรับสถานที่ภายนอก)' => 'address',
            'อุปกรณ์' => 'equipment_type',
            'อุปกรณ์ (คั่นด้วย ,)' => 'equipment_type',
            'สถานะ' => 'status',

            // Courses
            'รหัสวิชา' => 'course_code',
            'ชื่อรายวิชาภาษาไทย' => 'name_th',
            'ชื่อวิชา (ภาษาไทย)' => 'name_th',
            'ชื่อรายวิชาภาษาอังกฤษ' => 'name_en',
            'ชื่อวิชา (ภาษาอังกฤษ)' => 'name_en',
            'หลักสูตร' => 'curriculum_name',
            'ชื่อหลักสูตร' => 'curriculum_name',
            'รหัสพนักงานหัวหน้าวิชา' => 'head_instructor_employee_id',
            'ประเภทรายวิชา' => 'course_type',
            'หน่วยกิต' => 'credits',
            'ชั่วโมงบรรยาย' => 'lecture_hours',
            'ชั่วโมงบรรยาย/สัปดาห์' => 'lecture_hours',
            'ชั่วโมงปฏิบัติ' => 'lab_hours',
            'ชั่วโมงปฏิบัติ/สัปดาห์' => 'lab_hours',
            'ชั่วโมงศึกษาด้วยตนเอง' => 'self_study_hours',
            'ชั่วโมงศึกษาด้วยตนเอง/สัปดาห์' => 'self_study_hours',
            'จำนวนนักศึกษาสูงสุด' => 'capacity',
            'ชั้นปีตามแผน' => 'default_year_level',
            'หมุนเวียนฝึกปฏิบัติ' => 'requires_practicum_rotation',
            'หมุนเวียนกลุ่มปฏิบัติ' => 'requires_practicum_rotation',
            'วิชาบังคับ' => 'is_required',
            'วิชาบังคับ/เลือก' => 'is_required',
            'สีรายวิชา' => 'color_code',
            'รหัสสี HEX' => 'color_code',
        ];
    }

    protected function missingCsvHeaders(array $header, array $required): array
    {
        return array_values(array_diff($required, $header));
    }

    protected function csvRowHasData(array $data): bool
    {
        return count(array_filter($data, fn($v) => trim((string) $v) !== '')) > 0;
    }

    /**
     * Returns true if the CSV row is a comment/instruction line (first cell starts with '#').
     * Template files use # comment rows to describe columns; parsers should skip them.
     */
    protected function isCsvCommentRow(array $data): bool
    {
        return isset($data[0]) && str_starts_with(trim((string) $data[0]), '#');
    }

    /**
     * Read the first plausible non-comment, non-empty row from a CSV handle as the header row.
     * Skips any lines starting with '#' (template instruction comments) and blank lines.
     * Excel-exported templates may include title rows and a hint row before the header.
     * Returns the header array, or false if EOF is reached without finding one.
     */
    protected function readCsvHeader(mixed $handle, array $requiredHeaders = []): array|false
    {
        while (($row = fgetcsv($handle)) !== false) {
            if (!$this->csvRowHasData($row)) continue;
            if ($this->isCsvCommentRow($row)) continue;
            if (trim((string) ($row[0] ?? '')) === '') continue;
            if ($requiredHeaders) {
                $normalized = $this->normalizeCsvHeader($row);
                if (count(array_intersect($requiredHeaders, $normalized)) === 0) continue;
            }
            return $row;
        }
        return false;
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
