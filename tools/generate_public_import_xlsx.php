<?php

declare(strict_types=1);

/**
 * Generate the public Excel import template.
 *
 * The application still imports CSV files. Users fill this workbook, then save/export
 * the relevant sheet as CSV UTF-8 before uploading it.
 */

$sheets = [
    [
        'name' => 'คำแนะนำ',
        'rows' => [
            ['วิธีใช้งาน Template นำเข้าข้อมูล TPSS'],
            ['1. เลือกแท็บ Users, Rooms หรือ Courses ตามข้อมูลที่ต้องการนำเข้า'],
            ['2. กรอกข้อมูลใต้แถวหัวตารางภาษาไทย'],
            ['3. อ่านแท็บหมายเหตุของข้อมูลนั้นก่อนกรอก เพื่อดูค่าที่ใส่ได้และผลต่อระบบ'],
            ['4. ก่อนอัปโหลด ให้บันทึก/Export เฉพาะแท็บข้อมูลเป็นไฟล์ CSV UTF-8'],
            ['5. นำไฟล์ CSV UTF-8 ที่ได้ไปอัปโหลดในหน้าระบบ TPSS'],
            [''],
            ['สำคัญ', 'ระบบอัปโหลดรับไฟล์ .csv/.txt เท่านั้น ไม่รับ .xlsx โดยตรง'],
        ],
    ],
    [
        'name' => 'Users',
        'rows' => [
            ['คำนำหน้า', 'ชื่อ-นามสกุล', 'อีเมล', 'ชื่อผู้ใช้', 'รหัสผ่านเริ่มต้น', 'บทบาท (คั่นด้วย |)', 'บทบาทหลัก', 'รหัสพนักงาน', 'ตำแหน่งทางวิชาการ', 'วุฒิการศึกษา', 'ชื่อภาควิชา', 'ประเภทการจ้างงาน', 'วันที่บรรจุ (DD/MM/ปีพ.ศ.)', '% การสอน', '% วิจัย', '% บริการวิชาการ', '% ศิลปวัฒนธรรม', '% งานอื่นๆ'],
            ['นาย', 'ราชันย์ พิพัฒน์', 'rachan@mahidol.ac.th', 'rachan_p', 'password123', 'instructor|course_head|admin', 'admin', 'MU001', 'ผู้ช่วยอาจารย์', 'ปริญญาเอก', 'การพยาบาลรากฐาน', 'พนักงานมหาวิทยาลัย', '10/01/2553', 60, 20, 10, 5, 5],
            ['นาง', 'สมศรี มีสุข', 'somsri@mahidol.ac.th', 'somsri_m', 'password123', 'instructor|course_head', 'course_head', 'MU002', 'อาจารย์', 'ปริญญาโท', 'การพยาบาลจิตเวช', 'พนักงานมหาวิทยาลัย', '15/03/2558', 50, 30, 10, 10, 0],
            ['นาย', 'ธนา รักงาน', 'thana@mahidol.ac.th', 'thana_r', 'password123', 'staff', 'staff', 'MU006', '', '', '', '', '', '', '', '', '', ''],
        ],
    ],
    [
        'name' => 'Users หมายเหตุ',
        'rows' => [
            ['คอลัมน์', 'จำเป็นหรือไม่', 'ใส่อะไรได้บ้าง', 'มีผลอย่างไร'],
            ['คำนำหน้า', 'ไม่บังคับ', 'นาย, นาง, นางสาว', 'ใช้แสดงชื่อแบบเป็นทางการ'],
            ['ชื่อ-นามสกุล', 'บังคับ', 'ชื่อเต็มของผู้ใช้', 'แสดงในทุกหน้าที่อ้างถึงบุคลากร'],
            ['อีเมล', 'บังคับ', 'รูปแบบอีเมลและต้องไม่ซ้ำ', 'ใช้ระบุตัวผู้ใช้และติดต่อในอนาคต'],
            ['ชื่อผู้ใช้', 'บังคับ', 'ตัวอักษร ตัวเลข _ หรือ - และต้องไม่ซ้ำ', 'ใช้เข้าสู่ระบบ'],
            ['รหัสผ่านเริ่มต้น', 'บังคับ', 'อย่างน้อย 8 ตัวอักษร', 'ใช้เป็นรหัสผ่านเริ่มต้นก่อนผู้ใช้เปลี่ยนเอง'],
            ['บทบาท (คั่นด้วย |)', 'บังคับ', 'admin, staff, course_head, executive, instructor คั่นหลายบทบาทด้วย |', 'กำหนดสิทธิ์เข้าถึงระบบ'],
            ['บทบาทหลัก', 'บังคับ', 'ต้องเป็นหนึ่งในบทบาทที่ระบุ', 'เป็นบทบาทเริ่มต้นหลังเข้าสู่ระบบ'],
            ['รหัสพนักงาน', 'บังคับสำหรับ instructor', 'รหัสพนักงาน เช่น MU001', 'ใช้ตรวจผู้สอนและ conflict ข้ามรายวิชา'],
            ['ตำแหน่งทางวิชาการ', 'บังคับสำหรับ instructor/course_head/executive', 'เช่น อาจารย์, ผู้ช่วยศาสตราจารย์', 'ใช้แสดงชื่อและคำนวณกลุ่ม PA'],
            ['วุฒิการศึกษา', 'บังคับสำหรับ instructor/course_head/executive', 'ปริญญาตรี, ปริญญาโท, ปริญญาเอก', 'ใช้ประกอบเกณฑ์ PA'],
            ['ชื่อภาควิชา', 'บังคับสำหรับ instructor/course_head', 'ต้องตรงกับชื่อภาควิชาในระบบ', 'ใช้จำกัดผู้สอนตามภาควิชาของรายวิชา'],
            ['ประเภทการจ้างงาน', 'บังคับสำหรับ instructor', 'พนักงานมหาวิทยาลัย หรือ ข้าราชการ', 'ใช้เป็นข้อมูลบุคลากรและรายงาน'],
            ['วันที่บรรจุ (DD/MM/ปีพ.ศ.)', 'บังคับสำหรับ instructor', 'DD/MM/พ.ศ. หรือ YYYY-MM-DD', 'ใช้กับเงื่อนไข PA และประวัติบุคลากร'],
            ['% การสอน', 'บังคับสำหรับ instructor', '0-100', 'ใช้ตรวจ PA รวมกับสัดส่วนด้านอื่นต้องเท่ากับ 100'],
            ['% วิจัย', 'บังคับสำหรับ instructor', '0-100', 'ใช้ตรวจ PA'],
            ['% บริการวิชาการ', 'บังคับสำหรับ instructor', '0-100', 'ใช้ตรวจ PA'],
            ['% ศิลปวัฒนธรรม', 'บังคับสำหรับ instructor', '0-100', 'ใช้ตรวจ PA'],
            ['% งานอื่นๆ', 'บังคับสำหรับ instructor', '0-100', 'ใช้ตรวจ PA'],
        ],
    ],
    [
        'name' => 'Rooms',
        'rows' => [
            ['รหัสห้อง/สถานที่', 'ชื่อห้องหรือสถานที่', 'ประเภทสถานที่', 'ชื่ออาคาร', 'ความจุ (จำนวนที่นั่ง)', 'ที่อยู่ (สำหรับสถานที่ภายนอก)', 'อุปกรณ์ (คั่นด้วย ,)', 'สถานะ'],
            ['R101', 'ห้องเรียน 101', 'ห้องเรียน', 'อาคารพยาบาลศาสตร์', 50, '', 'โปรเจคเตอร์', 'active'],
            ['LAB-A', 'ห้องปฏิบัติการพยาบาล A', 'ห้องปฏิบัติการ', 'อาคารพยาบาลศาสตร์', 20, '', 'หุ่นฝึก,โปรเจคเตอร์', 'active'],
            ['WARD-MED', 'หอผู้ป่วยอายุรกรรม', 'หอผู้ป่วย', 'โรงพยาบาลศิริราช', 30, 'ชั้น 4 อาคาร 1 โรงพยาบาลศิริราช', '', 'active'],
        ],
    ],
    [
        'name' => 'Rooms หมายเหตุ',
        'rows' => [
            ['คอลัมน์', 'จำเป็นหรือไม่', 'ใส่อะไรได้บ้าง', 'มีผลอย่างไร'],
            ['รหัสห้อง/สถานที่', 'บังคับ', 'รหัสที่ไม่ซ้ำ เช่น R101, WARD-A', 'ใช้เป็นรหัสอ้างอิงและตรวจข้อมูลซ้ำ'],
            ['ชื่อห้องหรือสถานที่', 'บังคับ', 'ชื่อที่ผู้ใช้เห็นในระบบ', 'แสดงในตารางสอนและรายงาน'],
            ['ประเภทสถานที่', 'บังคับ', 'ต้องตรงกับประเภทสถานที่ที่มีในระบบ', 'กำหนดว่าสถานที่ใช้ร่วมกันได้หรือไม่ และมีผลต่อ capacity/conflict'],
            ['ชื่ออาคาร', 'ไม่บังคับ', 'ชื่ออาคารหรือหน่วยงาน', 'ช่วยให้ผู้ใช้ระบุตำแหน่งสถานที่ได้ชัดเจน'],
            ['ความจุ (จำนวนที่นั่ง)', 'ขึ้นกับประเภท', 'ตัวเลข หรือเว้นว่างสำหรับสถานที่ใช้ร่วมกันได้', 'ใช้ตรวจ warning เรื่องจำนวนผู้เรียนเกินความจุ'],
            ['ที่อยู่ (สำหรับสถานที่ภายนอก)', 'ไม่บังคับ', 'ที่อยู่ละเอียดของสถานที่ภายนอก', 'ใช้ประกอบข้อมูลสถานที่ฝึก/ชุมชน'],
            ['อุปกรณ์ (คั่นด้วย ,)', 'ไม่บังคับ', 'รายการคั่นด้วย comma เช่น โปรเจคเตอร์,กระดานขาว', 'ใช้เป็นข้อมูลประกอบการเลือกห้อง'],
            ['สถานะ', 'บังคับ', 'active, inactive, maintenance', 'สถานะ active ใช้งานได้ ส่วน maintenance/inactive ใช้แจ้งข้อจำกัด'],
        ],
    ],
    [
        'name' => 'Courses',
        'rows' => [
            ['รหัสวิชา', 'ชื่อวิชา (ภาษาไทย)', 'ชื่อวิชา (ภาษาอังกฤษ)', 'ชื่อหลักสูตร', 'ชื่อภาควิชา', 'รหัสพนักงานหัวหน้าวิชา', 'ประเภทรายวิชา', 'หน่วยกิต', 'ชั่วโมงบรรยาย/สัปดาห์', 'ชั่วโมงปฏิบัติ/สัปดาห์', 'ชั่วโมงศึกษาด้วยตนเอง/สัปดาห์', 'จำนวนนักศึกษาสูงสุด', 'ชั้นปีตามแผน', 'ภาคเรียนตามแผน', 'หมุนเวียนกลุ่มปฏิบัติ', 'วิชาบังคับ/เลือก', 'รหัสสี HEX', 'สถานะ'],
            ['NSBS 111', 'กระบวนการพยาบาล 1', 'Nursing Process 1', 'หลักสูตรพยาบาลศาสตรบัณฑิต 2565', 'การพยาบาลรากฐาน', 'MU002', 'theory', 2, 2, 0, 4, 252, 1, 1, 0, 1, '#3B82F6', 'active'],
            ['NSBS 221', 'การพยาบาลเด็ก 2', 'Pediatric Nursing 2', 'หลักสูตรพยาบาลศาสตรบัณฑิต 2565', 'การพยาบาลรากฐาน', 'MU002', 'theory_practicum', 2, 0, 2, 4, 240, 2, 2, 1, 1, '#10B981', 'active'],
            ['NSBS 490', 'วิชาเลือก: การดูแลผู้ป่วยวิกฤต', '', 'หลักสูตรพยาบาลศาสตรบัณฑิต 2565', '', 'MU007', 'theory', 2, 2, 0, 4, 80, 4, 1, 0, 0, '#F59E0B', 'active'],
        ],
    ],
    [
        'name' => 'Courses หมายเหตุ',
        'rows' => [
            ['คอลัมน์', 'จำเป็นหรือไม่', 'ใส่อะไรได้บ้าง', 'มีผลอย่างไร'],
            ['รหัสวิชา', 'บังคับ', 'ตัวอักษรอังกฤษ ตัวเลข ช่องว่าง ขีดกลาง หรือขีดล่าง เช่น NSBS 111', 'ใช้เป็นรหัสอ้างอิงรายวิชา และใช้ตรวจข้อมูลซ้ำร่วมกับหลักสูตร'],
            ['ชื่อวิชา (ภาษาไทย)', 'บังคับ', 'ข้อความภาษาไทย', 'แสดงในหน้าจัดตาราง รายงาน และหน้าค้นหา'],
            ['ชื่อวิชา (ภาษาอังกฤษ)', 'ไม่บังคับ', 'ข้อความภาษาอังกฤษ', 'ใช้ประกอบรายงาน/เอกสารที่ต้องมีชื่ออังกฤษ'],
            ['ชื่อหลักสูตร', 'บังคับ', 'ต้องตรงกับชื่อหลักสูตรที่มีในระบบ', 'ผูกรายวิชาเข้ากับหลักสูตร ถ้าไม่ตรงจะนำเข้าไม่ได้'],
            ['ชื่อภาควิชา', 'ไม่บังคับ', 'ต้องตรงกับชื่อภาควิชาที่มีในระบบ', 'ใช้กำหนดเจ้าของรายวิชาและตรวจผู้สอนในภาควิชาเดียวกัน'],
            ['รหัสพนักงานหัวหน้าวิชา', 'บังคับ', 'รหัสพนักงานของผู้ใช้ที่มีในระบบ', 'กำหนดหัวหน้าวิชา/ผู้ประสานรายวิชา'],
            ['ประเภทรายวิชา', 'บังคับ', 'theory, practicum, theory_practicum', 'ช่วยจัดหมวดรายวิชาและค่าเริ่มต้นของตาราง/ฝึกปฏิบัติ'],
            ['หน่วยกิต', 'บังคับ', 'ตัวเลขจำนวนเต็ม', 'ใช้ในข้อมูลหลักและรายงานหลักสูตร'],
            ['ชั่วโมงบรรยาย/สัปดาห์', 'บังคับ', 'ตัวเลขจำนวนเต็ม ใส่ 0 ได้', 'ใช้คำนวณแผนชั่วโมงและภาระงาน'],
            ['ชั่วโมงปฏิบัติ/สัปดาห์', 'บังคับ', 'ตัวเลขจำนวนเต็ม ใส่ 0 ได้', 'ใช้คำนวณแผนชั่วโมงและภาระงานฝึกปฏิบัติ'],
            ['ชั่วโมงศึกษาด้วยตนเอง/สัปดาห์', 'บังคับ', 'ตัวเลขจำนวนเต็ม', 'ใช้เป็นข้อมูลประกอบรายวิชา'],
            ['จำนวนนักศึกษาสูงสุด', 'บังคับ', 'ตัวเลขมากกว่า 0', 'ใช้เป็น capacity รวมของรายวิชา'],
            ['ชั้นปีตามแผน', 'ขึ้นกับหลักสูตร', '1, 2, 3, 4 หรือเว้นว่างเมื่อหลักสูตรไม่ใช้ระบบชั้นปี', 'ใช้จัดกลุ่มรายวิชาตามชั้นปี'],
            ['ภาคเรียนตามแผน', 'บังคับ', '1 หรือ 2', 'ใช้สร้างรอบเปิดสอนเมื่อเปิดช่วงจัดตาราง'],
            ['หมุนเวียนกลุ่มปฏิบัติ', 'บังคับ', '0 = ไม่หมุนเวียน, 1 = หมุนเวียน', 'กำหนดว่ารายวิชาต้องใช้ rotation schedule หรือไม่'],
            ['วิชาบังคับ/เลือก', 'บังคับ', '1 = วิชาบังคับ, 0 = วิชาเลือก', 'ใช้แยกประเภทในข้อมูลหลัก/หลักสูตร'],
            ['รหัสสี HEX', 'ไม่บังคับ', 'HEX เช่น #3B82F6', 'ใช้แสดงสีรายวิชาใน UI ถ้าเว้นว่างระบบเลือกเอง'],
            ['สถานะ', 'บังคับ', 'active หรือ inactive', 'รายวิชา inactive จะไม่ถูกนำไปเปิดจัดตาราง'],
        ],
    ],
];

$templateDir = dirname(__DIR__) . DIRECTORY_SEPARATOR . 'public' . DIRECTORY_SEPARATOR . 'templates';
$workbooks = [
    'users_import.xlsx' => [$sheets[0], $sheets[1], $sheets[2]],
    'rooms_import.xlsx' => [$sheets[0], $sheets[3], $sheets[4]],
    'courses_import.xlsx' => [$sheets[0], $sheets[5], $sheets[6]],
];

foreach ($workbooks as $filename => $workbookSheets) {
    writeWorkbook($templateDir . DIRECTORY_SEPARATOR . $filename, $workbookSheets);
}

function writeWorkbook(string $path, array $sheets): void
{
    $zip = new ZipArchive();
    if ($zip->open($path, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
        throw new RuntimeException("Cannot write {$path}");
    }

    $zip->addFromString('[Content_Types].xml', contentTypesXml(count($sheets)));
    $zip->addFromString('_rels/.rels', rootRelsXml());
    $zip->addFromString('xl/workbook.xml', workbookXml($sheets));
    $zip->addFromString('xl/_rels/workbook.xml.rels', workbookRelsXml(count($sheets)));
    $zip->addFromString('xl/styles.xml', stylesXml());

    foreach ($sheets as $index => $sheet) {
        $zip->addFromString('xl/worksheets/sheet' . ($index + 1) . '.xml', worksheetXml($sheet['rows']));
    }

    $zip->close();
}

function worksheetXml(array $rows): string
{
    $xml = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
        . '<worksheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main">'
        . '<sheetViews><sheetView workbookViewId="0"><pane ySplit="1" topLeftCell="A2" activePane="bottomLeft" state="frozen"/></sheetView></sheetViews>'
        . '<cols>';

    $maxCols = max(array_map('count', $rows));
    for ($i = 1; $i <= $maxCols; $i++) {
        $width = $i === 1 ? 28 : 24;
        $xml .= '<col min="' . $i . '" max="' . $i . '" width="' . $width . '" customWidth="1"/>';
    }

    $xml .= '</cols><sheetData>';
    foreach ($rows as $rowIndex => $row) {
        $r = $rowIndex + 1;
        $xml .= '<row r="' . $r . '">';
        foreach ($row as $colIndex => $value) {
            $cell = columnName($colIndex + 1) . $r;
            $style = $rowIndex === 0 ? ' s="1"' : '';
            $xml .= '<c r="' . $cell . '" t="inlineStr"' . $style . '><is><t>' . xml((string) $value) . '</t></is></c>';
        }
        $xml .= '</row>';
    }

    return $xml . '</sheetData></worksheet>';
}

function columnName(int $index): string
{
    $name = '';
    while ($index > 0) {
        $index--;
        $name = chr(65 + ($index % 26)) . $name;
        $index = intdiv($index, 26);
    }
    return $name;
}

function xml(string $value): string
{
    return htmlspecialchars($value, ENT_XML1 | ENT_COMPAT, 'UTF-8');
}

function contentTypesXml(int $sheetCount): string
{
    $xml = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
        . '<Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types">'
        . '<Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/>'
        . '<Default Extension="xml" ContentType="application/xml"/>'
        . '<Override PartName="/xl/workbook.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.sheet.main+xml"/>'
        . '<Override PartName="/xl/styles.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.styles+xml"/>';

    for ($i = 1; $i <= $sheetCount; $i++) {
        $xml .= '<Override PartName="/xl/worksheets/sheet' . $i . '.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.worksheet+xml"/>';
    }

    return $xml . '</Types>';
}

function rootRelsXml(): string
{
    return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
        . '<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">'
        . '<Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument" Target="xl/workbook.xml"/>'
        . '</Relationships>';
}

function workbookXml(array $sheets): string
{
    $xml = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
        . '<workbook xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main" xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships"><sheets>';

    foreach ($sheets as $index => $sheet) {
        $id = $index + 1;
        $xml .= '<sheet name="' . xml($sheet['name']) . '" sheetId="' . $id . '" r:id="rId' . $id . '"/>';
    }

    return $xml . '</sheets></workbook>';
}

function workbookRelsXml(int $sheetCount): string
{
    $xml = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
        . '<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">';

    for ($i = 1; $i <= $sheetCount; $i++) {
        $xml .= '<Relationship Id="rId' . $i . '" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/worksheet" Target="worksheets/sheet' . $i . '.xml"/>';
    }

    $xml .= '<Relationship Id="rId' . ($sheetCount + 1) . '" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/styles" Target="styles.xml"/>';

    return $xml . '</Relationships>';
}

function stylesXml(): string
{
    return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
        . '<styleSheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main">'
        . '<fonts count="2"><font><sz val="11"/><name val="Calibri"/></font><font><b/><sz val="11"/><name val="Calibri"/></font></fonts>'
        . '<fills count="1"><fill><patternFill patternType="none"/></fill></fills>'
        . '<borders count="1"><border><left/><right/><top/><bottom/><diagonal/></border></borders>'
        . '<cellStyleXfs count="1"><xf numFmtId="0" fontId="0" fillId="0" borderId="0"/></cellStyleXfs>'
        . '<cellXfs count="2"><xf numFmtId="0" fontId="0" fillId="0" borderId="0" xfId="0"/><xf numFmtId="0" fontId="1" fillId="0" borderId="0" xfId="0" applyFont="1"/></cellXfs>'
        . '<cellStyles count="1"><cellStyle name="Normal" xfId="0" builtinId="0"/></cellStyles>'
        . '</styleSheet>';
}
