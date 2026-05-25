param(
    [string]$OutputDirectory = "",
    [switch]$InPlace
)

Add-Type -AssemblyName System.IO.Compression.FileSystem

$DocRoot = "d:\2023\ฝึกงาน\อัพเดตเอกสารล่าสุด22_05"
$Targets = @(
    @{
        Kind = "WP01"
        Path = Join-Path $DocRoot "WP-01_Agreement_Statement-of-Work_ProjectName_v1.0(กู้คืนอัตโนมัติ) - Copy.docx"
    },
    @{
        Kind = "WP02"
        Path = Join-Path $DocRoot "WP-02_Project-Plan_ProjectName_v1.0 - Copy.docx"
    },
    @{
        Kind = "WP03"
        Path = Join-Path $DocRoot "WP-03_Software-Requirements-Specification_ProjectName_v1.0.docx"
    }
)

$W = "http://schemas.openxmlformats.org/wordprocessingml/2006/main"

function New-NsManager {
    param([xml]$Doc)
    $ns = New-Object System.Xml.XmlNamespaceManager($Doc.NameTable)
    [void]$ns.AddNamespace("w", $W)
    return ,$ns
}

function Get-NodeText {
    param([System.Xml.XmlNode]$Node, [System.Xml.XmlNamespaceManager]$Ns)
    $parts = @()
    foreach ($t in $Node.SelectNodes(".//w:t", $Ns)) {
        $parts += $t.InnerText
    }
    return (($parts -join "") -replace "\s+", " ").Trim()
}

function Set-NodeText {
    param(
        [System.Xml.XmlNode]$Node,
        [System.Xml.XmlNamespaceManager]$Ns,
        [string]$Text
    )

    $texts = @($Node.SelectNodes(".//w:t", $Ns))
    if ($texts.Count -eq 0) {
        $paragraph = $Node.SelectSingleNode(".//w:p", $Ns)
        if ($null -eq $paragraph) { $paragraph = $Node }
        $run = $Node.OwnerDocument.CreateElement("w", "r", $W)
        $textNode = $Node.OwnerDocument.CreateElement("w", "t", $W)
        $run.AppendChild($textNode) | Out-Null
        $paragraph.AppendChild($run) | Out-Null
        $texts = @($textNode)
    }

    $texts[0].InnerText = $Text
    for ($i = 1; $i -lt $texts.Count; $i++) {
        $texts[$i].InnerText = ""
    }
}

function Set-ParagraphStarting {
    param(
        [xml]$Doc,
        [System.Xml.XmlNamespaceManager]$Ns,
        [string]$Prefix,
        [string]$Text,
        [switch]$Optional
    )

    foreach ($p in $Doc.SelectNodes("//w:p", $Ns)) {
        if ((Get-NodeText $p $Ns).StartsWith($Prefix)) {
            Set-NodeText $p $Ns $Text
            return $true
        }
    }

    if (-not $Optional) {
        throw "Paragraph starting with '$Prefix' was not found."
    }
    return $false
}

function Set-BodyParagraphStarting {
    param(
        [xml]$Doc,
        [System.Xml.XmlNamespaceManager]$Ns,
        [string]$Prefix,
        [string]$Text,
        [switch]$Optional
    )

    foreach ($p in $Doc.SelectNodes("/w:document/w:body/w:p", $Ns)) {
        if ((Get-NodeText $p $Ns).StartsWith($Prefix)) {
            Set-NodeText $p $Ns $Text
            return $true
        }
    }

    if (-not $Optional) {
        throw "Body paragraph starting with '$Prefix' was not found."
    }
    return $false
}

function Find-TableContainingAll {
    param(
        [xml]$Doc,
        [System.Xml.XmlNamespaceManager]$Ns,
        [string[]]$Needles
    )

    foreach ($tbl in $Doc.SelectNodes("//w:tbl", $Ns)) {
        $text = Get-NodeText $tbl $Ns
        $ok = $true
        foreach ($needle in $Needles) {
            if (-not $text.Contains($needle)) {
                $ok = $false
                break
            }
        }
        if ($ok) { return $tbl }
    }
    return $null
}

function Set-RowCells {
    param(
        [System.Xml.XmlNode]$Row,
        [System.Xml.XmlNamespaceManager]$Ns,
        [string[]]$Values
    )

    $cells = @($Row.SelectNodes("./w:tc", $Ns))
    for ($i = 0; $i -lt $cells.Count; $i++) {
        $value = if ($i -lt $Values.Count) { $Values[$i] } else { "" }
        Set-NodeText $cells[$i] $Ns $value
    }
}

function Replace-TableRows {
    param(
        [System.Xml.XmlNode]$Table,
        [System.Xml.XmlNamespaceManager]$Ns,
        [array]$Rows
    )

    if ($null -eq $Table) { throw "Required table was not found." }
    $existingRows = @($Table.SelectNodes("./w:tr", $Ns))
    if ($existingRows.Count -lt 2) { throw "Table must contain a header row and a template row." }

    $template = $existingRows[1].CloneNode($true)
    for ($i = $existingRows.Count - 1; $i -ge 1; $i--) {
        $Table.RemoveChild($existingRows[$i]) | Out-Null
    }

    foreach ($rowValues in $Rows) {
        $newRow = $template.CloneNode($true)
        Set-RowCells $newRow $Ns ([string[]]$rowValues)
        $Table.AppendChild($newRow) | Out-Null
    }
}

function Replace-TableAllRows {
    param(
        [System.Xml.XmlNode]$Table,
        [System.Xml.XmlNamespaceManager]$Ns,
        [array]$Rows
    )

    if ($null -eq $Table) { throw "Required table was not found." }
    $existingRows = @($Table.SelectNodes("./w:tr", $Ns))
    if ($existingRows.Count -lt 1) { throw "Table must contain at least one template row." }

    $template = $existingRows[0].CloneNode($true)
    for ($i = $existingRows.Count - 1; $i -ge 0; $i--) {
        $Table.RemoveChild($existingRows[$i]) | Out-Null
    }

    foreach ($rowValues in $Rows) {
        $newRow = $template.CloneNode($true)
        Set-RowCells $newRow $Ns ([string[]]$rowValues)
        $Table.AppendChild($newRow) | Out-Null
    }
}

function Set-ControlTable {
    param(
        [xml]$Doc,
        [System.Xml.XmlNamespaceManager]$Ns,
        [string]$Version
    )

    $table = Find-TableContainingAll $Doc $Ns @("Work Product ID", "Version")
    if ($null -eq $table) { throw "Document Control table was not found." }

    foreach ($row in $table.SelectNodes("./w:tr", $Ns)) {
        $cells = @($row.SelectNodes("./w:tc", $Ns))
        if ($cells.Count -lt 2) { continue }
        $key = Get-NodeText $cells[0] $Ns
        if ($key -eq "Status") {
            Set-NodeText $cells[1] $Ns "Reviewed / Updated"
        } elseif ($key -eq "Version") {
            Set-NodeText $cells[1] $Ns $Version
        }
    }
}

function Set-RevisionHistory {
    param(
        [xml]$Doc,
        [System.Xml.XmlNamespaceManager]$Ns,
        [string]$Kind
    )

    $table = Find-TableContainingAll $Doc $Ns @("Version", "Date", "Description", "Author")
    if ($null -eq $table) { throw "Revision History table was not found." }

    $rows = switch ($Kind) {
        "WP03" {
            @(
                @("0.1", "27 เมษายน 2569", "Initial draft", "นายราชันย์ พิพัฒน์"),
                @("0.2", "6 พฤษภาคม 2569", "Revised after review", "นายภูวดล ทองรอง"),
                @("0.3", "22 พฤษภาคม 2569", "Revised after review", "นายภูวดล ทองรอง"),
                @("0.4", "25 พฤษภาคม 2569", "ปรับปรุงให้สอดคล้องกับโค้ดปัจจุบัน: RBAC, Master Data, Course Offering, Schedule, Conflict Check, Audit Trail, Alerts และ Dashboard", "นายภูวดล ทองรอง"),
                @("1.0", "....................", "Approved version", "....................")
            )
        }
        default {
            @(
                @("0.1", "27 เมษายน 2569", "Initial draft", "นายราชันย์ พิพัฒน์"),
                @("0.2", "30 เมษายน 2569", "Revised after review", "นายราชันย์ พิพัฒน์"),
                @("0.3", "22 พฤษภาคม 2569", "Revised after review", "นายภูวดล ทองรอง"),
                @("0.4", "25 พฤษภาคม 2569", "ปรับปรุงให้สอดคล้องกับสถานะโค้ดและขอบเขต Phase ล่าสุด", "นายภูวดล ทองรอง"),
                @("1.0", "....................", "Approved version", "....................")
            )
        }
    }

    Replace-TableRows $table $Ns $rows
}

function Set-CommonCoverAndControl {
    param(
        [xml]$Doc,
        [System.Xml.XmlNamespaceManager]$Ns,
        [string]$Kind
    )

    $coverVersion = "0.4"
    $coverText = switch ($Kind) {
        "WP01" { "ชื่อโครงการ: โครงการพัฒนาระบบบริหารตารางสอนและการฝึกปฏิบัติ รหัสโครงการ: ............................................................... ชื่อลูกค้า/หน่วยงานผู้ว่าจ้าง: คณะพยาบาลศาสตร์ มหาวิทยาลัยมหิดล ชื่อผู้รับจ้าง/ทีมพัฒนา: Intelligent Software Solutions วันที่จัดทำ: 25 พฤษภาคม 2569 (Updated from current codebase) เวอร์ชันเอกสาร: $coverVersion" }
        "WP02" { "ชื่อโครงการ: โครงการพัฒนาระบบบริหารตารางสอนและการฝึกปฏิบัติ รหัสโครงการ: ............................................................... ชื่อลูกค้า/หน่วยงานผู้ว่าจ้าง: คณะพยาบาลศาสตร์ มหาวิทยาลัยมหิดล ชื่อผู้รับจ้าง/ทีมพัฒนา: Intelligent Software Solutions วันที่จัดทำ: 25 พฤษภาคม 2569 (Updated from current codebase) เวอร์ชันเอกสาร: $coverVersion" }
        default { "ชื่อโครงการ: โครงการพัฒนาระบบบริหารตารางสอนและการฝึกปฏิบัติ รหัสโครงการ: ............................................................... ชื่อลูกค้า/หน่วยงานผู้ว่าจ้าง: คณะพยาบาลศาสตร์ มหาวิทยาลัยมหิดล ชื่อผู้รับจ้าง/ทีมพัฒนา: Intelligent Software Solutions วันที่จัดทำ: 25 พฤษภาคม 2569 (Updated from current codebase) เวอร์ชันเอกสาร: $coverVersion" }
    }

    Set-ParagraphStarting $Doc $Ns "ชื่อโครงการ:" $coverText | Out-Null
    Set-ControlTable $Doc $Ns $coverVersion
    Set-RevisionHistory $Doc $Ns $Kind
}

function Update-WP03 {
    param([xml]$Doc, [System.Xml.XmlNamespaceManager]$Ns)

    Set-CommonCoverAndControl $Doc $Ns "WP03"

    Set-ParagraphStarting $Doc $Ns "ระบบบริหารตารางสอนและการฝึกปฏิบัติ" "ระบบบริหารตารางสอนและการฝึกปฏิบัติ (Teaching & Practicum Scheduling System: TPSS) เป็น Web Application ภายในสำหรับคณะพยาบาลศาสตร์ มหาวิทยาลัยมหิดล ครอบคลุมการจัดการผู้ใช้และสิทธิ์, ข้อมูลพื้นฐาน, ปีการศึกษาและช่วงจัดตาราง, รายวิชาและรอบเปิดสอน, กลุ่มนักศึกษา, ชุดผู้สอน, การจัดตารางแบบ Block Schedule, การตรวจสอบตารางชน, Dashboard/Alerts และ Audit Trail. ขอบเขต Version 1 ปัจจุบันรองรับการทำงานผ่าน browser โดยผู้ดูแลระบบ, เจ้าหน้าที่, หัวหน้าวิชา, ผู้บริหาร และอาจารย์ผู้สอน ส่วน Approval Workflow เต็มรูปแบบ, รายงานส่งออก PDF/Excel, MU-SSO, Email Gateway และ API ภายนอกยังอยู่ใน Phase 2 หรือรอยืนยันข้อมูลเทคนิค." | Out-Null

    $scopeTable = Find-TableContainingAll $Doc $Ns @("รายการงาน / โมดูล / ฟังก์ชัน", "รายละเอียดเบื้องต้น")
    Replace-TableRows $scopeTable $Ns @(
        @("1", "การจัดการสิทธิ์และตัวตน (Role & Auth)", "เข้าสู่ระบบด้วย username/email และ password, ควบคุมสิทธิ์ด้วย RBAC, รองรับผู้ใช้หลายบทบาทและการสลับบทบาทใน session"),
        @("2", "การจัดการผู้ใช้งาน (User Management)", "เพิ่ม แก้ไข ปิดใช้งาน ลบ และนำเข้าผู้ใช้จาก CSV พร้อมข้อมูลโปรไฟล์อาจารย์และสัดส่วน PA"),
        @("3", "การจัดการข้อมูลพื้นฐาน (Master Data)", "จัดการภาควิชา หลักสูตร รายวิชา ประเภทกิจกรรม ประเภทสถานที่ ห้อง/แหล่งฝึก และข้อมูลอาจารย์"),
        @("4", "ปีการศึกษาและช่วงจัดตาราง (Academic Year / Scheduling Window)", "กำหนดปีการศึกษาปัจจุบัน เปิด/ปิดช่วงจัดตาราง และสร้างหรือซิงก์ Course Offering จากรายวิชาที่เปิดสอน"),
        @("5", "การจัดการรายวิชาที่เปิดสอน (Course Offering)", "หัวหน้าวิชาดูแลรอบเปิดสอน ชุดผู้สอน บทบาทผู้สอน กลุ่มนักศึกษา จำนวนรับ และเงื่อนไข practicum rotation"),
        @("6", "การจัดการตารางสอนและตารางฝึก (Schedule)", "สร้าง แก้ไข ลบรายการสอนแบบวันเดียวหรือช่วงวันที่ ระบุเวลา หัวข้อ ประเภทกิจกรรม ห้อง/แหล่งฝึก ผู้สอนหลัก ผู้สอนร่วม และกลุ่มนักศึกษา"),
        @("7", "ระบบตรวจสอบตารางชน (Conflict Check)", "ตรวจอาจารย์ชน ห้อง/สถานที่ชน และกลุ่มนักศึกษาชนทั้งแบบเวลาซ้อนบางส่วนและช่วงวันที่ซ้อน พร้อมบล็อกการบันทึกเมื่อพบ conflict"),
        @("8", "การแสดงผลตารางและการค้นหา (Views / Search & Filter)", "แสดงตารางแบบ day/week/month และ list view พร้อมตัวกรองรายวิชา วันที่ ช่วงเวลา และข้อมูลพื้นฐาน"),
        @("9", "Dashboard และ Readiness Alerts", "แสดงสถิติผู้ใช้ รายวิชา ห้อง หลักสูตร สถานะ Course Offering, Critical readiness, warnings และ recent activity"),
        @("10", "Audit Trail", "บันทึกเหตุการณ์สำคัญ เช่น login/logout, role switch, user/master data/course offering/student group/schedule/settings/import และดูย้อนหลังพร้อมตัวกรอง"),
        @("11", "การนำเข้าข้อมูล (CSV Import)", "รองรับการนำเข้าผู้ใช้งาน ห้อง/แหล่งฝึก และรายวิชาจาก CSV พร้อม validation และ audit log แบบ aggregate"),
        @("12", "การตั้งค่า Workload/PA พื้นฐาน", "ตั้งค่าจำนวนสัปดาห์ ชั่วโมงต่อสัปดาห์ เกณฑ์ PA และตรวจสัดส่วน PA ของอาจารย์เพื่อเตรียมข้อมูลสำหรับรายงานภาระงาน"),
        @("13", "กระบวนการอนุมัติออนไลน์ (Approval Flow - Phase 2)", "ฐานข้อมูลรองรับสถานะและประวัติ approval แต่ workflow อนุมัติ/ปฏิเสธ/ส่งกลับแก้ยังเป็น Phase 2"),
        @("14", "รายงานและส่งออก (Reporting / Export - Phase 2)", "รายงาน Teaching Load, การใช้สถานที่ และส่งออก PDF/Excel เป็นงาน Phase 2"),
        @("15", "การเชื่อมต่อภายนอก (External Integration - To Confirm)", "MU-SSO, Email Gateway, API สำหรับระบบ PA-QA หรือระบบภายนอกต้องรอยืนยัน interface และสิทธิ์การเชื่อมต่อ")
    )
    Set-ParagraphStarting $Doc $Ns "สำหรับ * โมดูลนี้" "หมายเหตุ: Version 1 ปัจจุบันครอบคลุม M10, M1, M2, M3, M4, M7/M8 บางส่วน, M12 Audit Trail และ Alerts/Dashboard สำหรับความพร้อมข้อมูลแล้ว ส่วน Approval Workflow เต็มรูปแบบ, Reporting/Export, PA Integration เต็มรูปแบบ, MU-SSO/Email/API ภายนอก และ Smart Warning เชิงลึกยังเป็น Phase 2 หรือรอยืนยันรายละเอียดเทคนิค." -Optional | Out-Null

    if (-not (Set-ParagraphStarting $Doc $Ns "ระบบที่ต้องการพัฒนาเป็นระบบประเภท Web Application" "ระบบที่พัฒนาเป็น Web Application ภายใน (Internal Management System) บน Laravel สำหรับบริหารจัดการตารางเรียน ตารางสอนภาคทฤษฎี และตารางฝึกปฏิบัติในรูปแบบ Block Schedule โดยใช้ฐานข้อมูลกลางร่วมกันตั้งแต่ Master Data, Course Offering, Student Groups, Instructor Pool, Schedule, Conflict Check, Alerts, Dashboard และ Audit Trail." -Optional)) {
        Set-ParagraphStarting $Doc $Ns "ระบบที่พัฒนาเป็น Web Application" "ระบบที่พัฒนาเป็น Web Application ภายใน (Internal Management System) บน Laravel สำหรับบริหารจัดการตารางเรียน ตารางสอนภาคทฤษฎี และตารางฝึกปฏิบัติในรูปแบบ Block Schedule โดยใช้ฐานข้อมูลกลางร่วมกันตั้งแต่ Master Data, Course Offering, Student Groups, Instructor Pool, Schedule, Conflict Check, Alerts, Dashboard และ Audit Trail." | Out-Null
    }
    if (-not (Set-ParagraphStarting $Doc $Ns "ระบบควรรองรับการทำงานตั้งแต่การนำเข้าข้อมูลพื้นฐาน" "ระบบรองรับการทำงานตั้งแต่การนำเข้าหรือบันทึกข้อมูลพื้นฐาน, การตั้งค่าปีการศึกษา, การเปิดช่วงจัดตาราง, การสร้าง Course Offering อัตโนมัติจากรายวิชาที่ active, การจัดกลุ่มนักศึกษาและผู้สอน, การบันทึกตาราง, การตรวจ conflict, การติดตามผ่าน dashboard/alerts และการตรวจสอบย้อนหลังผ่าน audit log. ความสามารถด้าน approval/report/export/integration ภายนอกจะต่อยอดใน Phase 2." -Optional)) {
        Set-ParagraphStarting $Doc $Ns "ระบบรองรับการทำงานตั้งแต่การนำเข้าหรือบันทึกข้อมูลพื้นฐาน" "ระบบรองรับการทำงานตั้งแต่การนำเข้าหรือบันทึกข้อมูลพื้นฐาน, การตั้งค่าปีการศึกษา, การเปิดช่วงจัดตาราง, การสร้าง Course Offering อัตโนมัติจากรายวิชาที่ active, การจัดกลุ่มนักศึกษาและผู้สอน, การบันทึกตาราง, การตรวจ conflict, การติดตามผ่าน dashboard/alerts และการตรวจสอบย้อนหลังผ่าน audit log. ความสามารถด้าน approval/report/export/integration ภายนอกจะต่อยอดใน Phase 2." | Out-Null
    }

    $roleTable = Find-TableContainingAll $Doc $Ns @("รหัสบทบาท", "กลุ่มผู้ใช้", "สิทธิ์หลัก")
    Replace-TableRows $roleTable $Ns @(
        @("UR-01", "System Admin", "ผู้ดูแลระบบและข้อมูลตั้งต้น", "จัดการผู้ใช้/สิทธิ์, ตั้งค่าปีการศึกษาและ PA, จัดการ Master Data, เปิด/ปิดช่วงจัดตาราง, ดู Alerts, Dashboard และ Audit Logs"),
        @("UR-02", "Support Staff", "เจ้าหน้าที่สนับสนุนข้อมูลและการจัดตาราง", "จัดการ Master Data ที่ได้รับสิทธิ์, ตั้งค่าปีการศึกษาในขอบเขต staff, ดู dashboard และ recent activity"),
        @("UR-03", "Course Head / Maker", "หัวหน้าวิชาหรือผู้ประสานรายวิชา", "ดู Course Offering ที่ตนรับผิดชอบ, จัดชุดผู้สอน, จัดกลุ่มนักศึกษา, กำหนด rotation และสร้าง/แก้ไข/ลบตารางในช่วง scheduling"),
        @("UR-04", "Executive / Approver", "ผู้บริหารหรือผู้ตรวจสอบ", "ดูภาพรวมและ dashboard ตามสิทธิ์; การอนุมัติออนไลน์และปุ่มแก้ไขอยู่ใน Phase 2"),
        @("UR-05", "Instructor", "อาจารย์ผู้สอน", "ดู dashboard ส่วนตัวและข้อมูลภาระงาน/โควตาสอนของตนเอง; มุมมองตารางส่วนตัวจะต่อยอดจาก schedule view")
    )

    $toBeTable = Find-TableContainingAll $Doc $Ns @("ขั้นตอนใหม่ที่ต้องการ", "ผู้ใช้งานที่เกี่ยวข้อง", "ผลลัพธ์จากขั้นตอน")
    Replace-TableRows $toBeTable $Ns @(
        @("1", "เตรียมข้อมูลพื้นฐานและปีการศึกษา", "System Admin / Staff", "มีปีการศึกษาปัจจุบัน หลักสูตร รายวิชา ภาควิชา ห้อง/แหล่งฝึก ประเภทกิจกรรม และผู้ใช้งานพร้อมใช้งาน"),
        @("2", "ตรวจ readiness และเปิดช่วงจัดตาราง", "System Admin", "ระบบตรวจ Critical/Warning ก่อนเปิดช่วงจัดตาราง และสร้าง/ซิงก์ Course Offering จากรายวิชา active"),
        @("3", "จัดโครงสร้างรายวิชาที่เปิดสอน", "Course Head / Maker", "กำหนดชุดผู้สอน บทบาทผู้สอน กลุ่มนักศึกษา จำนวนรับ และ practicum rotation ของรอบเปิดสอน"),
        @("4", "จัดทำตารางแบบ Block Schedule", "Course Head / Maker", "บันทึกหัวข้อ วัน/ช่วงวันที่ เวลา ประเภทกิจกรรม ห้อง/แหล่งฝึก ผู้สอนหลัก/ร่วม และกลุ่มนักศึกษา"),
        @("5", "ตรวจสอบ conflict ก่อนบันทึก", "System", "ระบบบล็อกการบันทึกเมื่อพบอาจารย์ ห้อง/สถานที่ หรือกลุ่มนักศึกษาชน รวมถึงตรวจ capacity_required กับจำนวนผู้เรียนที่เลือก"),
        @("6", "ติดตามผ่าน Dashboard, Alerts และ Audit Logs", "Admin / Staff / Executive", "เห็นสถานะข้อมูล ความพร้อมระบบ pipeline รอบเปิดสอน และประวัติการแก้ไขย้อนหลัง"),
        @("7", "อนุมัติ เผยแพร่ รายงาน และเชื่อมต่อภายนอก", "Executive / System", "เป็น Phase 2 สำหรับ approval workflow เต็มรูปแบบ, report/export, email notification, MU-SSO และ PA-QA integration")
    )
    if (-not (Set-ParagraphStarting $Doc $Ns "ระบบใหม่ถูกออกแบบมาเพื่อเป็นแพลตฟอร์มดิจิทัล" "ระบบใหม่ถูกออกแบบเป็นแพลตฟอร์มดิจิทัลแบบ Single Data Entry โดยให้ข้อมูล Master Data, Course Offering, Instructor Pool, Student Groups และ Schedule ใช้ฐานข้อมูลเดียวกัน ลดการกรอกซ้ำและลดความผิดพลาดจากไฟล์กระจายหลายชุด ระบบ Version 1 เน้นการเตรียมข้อมูล เปิดช่วงจัดตาราง บันทึกตาราง ตรวจ conflict แสดงผลแบบ calendar/list และตรวจสอบย้อนหลัง ส่วน approval/report/export/integration ภายนอกจะอยู่ใน Phase 2." -Optional)) {
        Set-ParagraphStarting $Doc $Ns "ระบบใหม่ถูกออกแบบเป็นแพลตฟอร์มดิจิทัลแบบ Single Data Entry" "ระบบใหม่ถูกออกแบบเป็นแพลตฟอร์มดิจิทัลแบบ Single Data Entry โดยให้ข้อมูล Master Data, Course Offering, Instructor Pool, Student Groups และ Schedule ใช้ฐานข้อมูลเดียวกัน ลดการกรอกซ้ำและลดความผิดพลาดจากไฟล์กระจายหลายชุด ระบบ Version 1 เน้นการเตรียมข้อมูล เปิดช่วงจัดตาราง บันทึกตาราง ตรวจ conflict แสดงผลแบบ calendar/list และตรวจสอบย้อนหลัง ส่วน approval/report/export/integration ภายนอกจะอยู่ใน Phase 2." | Out-Null
    }

    $useCaseTable = Find-TableContainingAll $Doc $Ns @("Use Case ID", "Use Case Name", "Actor", "Priority")
    Replace-TableRows $useCaseTable $Ns @(
        @("UC-01", "Login to System", "All Users", "ผู้ใช้เข้าสู่ระบบด้วย username/email และ password ตามสิทธิ์; MU-SSO เป็น interface ในอนาคต", "High"),
        @("UC-02", "Manage User Accounts & Permissions", "System Admin", "จัดการบัญชี ผู้ใช้หลายบทบาท บทบาทหลัก สถานะ active และโปรไฟล์อาจารย์", "High"),
        @("UC-03", "Manage Master Data", "System Admin / Staff", "จัดการภาควิชา หลักสูตร รายวิชา ประเภทกิจกรรม ประเภทสถานที่ ห้อง/แหล่งฝึก และข้อมูลอาจารย์", "High"),
        @("UC-04", "Create & Edit Schedule", "Course Head / Maker", "จัดทำตารางสอน/ฝึกปฏิบัติ เลือกผู้สอน กลุ่มนักศึกษา ห้อง/แหล่งฝึก และตรวจ conflict ก่อนบันทึก", "High"),
        @("UC-05", "Review and Approve Schedule", "Executive / Approver", "ตรวจสอบและอนุมัติตารางออนไลน์ เป็น Phase 2", "Medium"),
        @("UC-06", "View Schedule Workspace", "Course Head / Staff / Instructor", "ดูตารางแบบ day/week/month และ list ตามสิทธิ์และรายวิชาที่เกี่ยวข้อง", "High"),
        @("UC-07", "Manage Workload/PA Settings", "System Admin / Manager", "ตั้งค่าเกณฑ์ PA และโควตาสอน; การคำนวณรายงาน Teaching Load เต็มรูปแบบเป็น Phase 2", "Medium"),
        @("UC-08", "Generate Dashboard and Alerts", "Admin / Staff / Executive", "ดู dashboard, readiness alerts, pipeline และ recent activity; report/export เป็น Phase 2", "High"),
        @("UC-09", "Manage Academic Year and Scheduling Window", "System Admin", "ตั้งปีการศึกษาปัจจุบัน เปิด/ปิดช่วงจัดตาราง และซิงก์ Course Offering จากรายวิชา active", "High"),
        @("UC-10", "View Audit Logs", "System Admin", "ค้นหา กรอง และดูรายละเอียดประวัติการดำเนินการย้อนหลัง", "High"),
        @("UC-11", "Import CSV Data", "System Admin / Staff", "นำเข้าผู้ใช้งาน ห้อง/แหล่งฝึก และรายวิชาจาก CSV พร้อม validation", "Medium")
    )

    $phase2Table = Find-TableContainingAll $Doc $Ns @("FR-06", "Approve/Reject/Revise", "FR-11")
    if ($null -eq $phase2Table) {
        $phase2Table = Find-TableContainingAll $Doc $Ns @("FR-13", "FR-17")
    }
    $phase1Table = Find-TableContainingAll $Doc $Ns @("Requirement ID", "รายละเอียดความต้องการ", "ผู้ใช้ที่เกี่ยวข้อง", "Related Use Case")
    Replace-TableRows $phase1Table $Ns @(
        @("FR-01", "ระบบต้องรองรับการเข้าสู่ระบบด้วย username/email และ password และนำผู้ใช้ไปยัง dashboard ตาม active role", "All Users", "High", "UC-01"),
        @("FR-02", "ระบบต้องควบคุมสิทธิ์แบบ Role-based Access Control รองรับหลายบทบาทต่อผู้ใช้ บทบาทหลัก และการสลับบทบาทใน session", "All Users", "High", "UC-01, UC-02"),
        @("FR-03", "System Admin ต้องจัดการผู้ใช้งาน สถานะบัญชี บทบาท โปรไฟล์อาจารย์ สัดส่วน PA และนำเข้าผู้ใช้จาก CSV ได้", "System Admin", "High", "UC-02, UC-11"),
        @("FR-04", "ระบบต้องจัดการ Master Data ได้แก่ ภาควิชา หลักสูตร รายวิชา ประเภทกิจกรรม ประเภทสถานที่ ห้อง/แหล่งฝึก และข้อมูลอาจารย์ รวมถึงนำเข้า rooms/courses CSV", "System Admin / Staff", "High", "UC-03, UC-11"),
        @("FR-05", "ระบบต้องจัดการปีการศึกษา กำหนดปีปัจจุบัน เปิด/ปิด Scheduling Window และสร้าง/ซิงก์ Course Offering จาก active courses", "System Admin", "High", "UC-09"),
        @("FR-06", "หัวหน้าวิชาต้องจัดการ Course Offering, Instructor Pool, บทบาทผู้สอน, Student Groups, จำนวนรับ และ practicum rotation note ได้ในช่วง scheduling", "Course Head / Maker", "High", "UC-04"),
        @("FR-07", "ระบบต้องบันทึก แก้ไข และลบตารางสอนแบบ Block Schedule/ช่วงวันที่ พร้อม activity type, room, topic, capacity_required, lead/co-instructors และ student groups", "Course Head / Maker", "High", "UC-04"),
        @("FR-08", "ระบบต้องตรวจและบล็อก conflict ของอาจารย์ ห้อง/สถานที่ และกลุ่มนักศึกษาเมื่อวัน/ช่วงวันที่และเวลาซ้อนกัน รวมถึงตรวจจำนวนผู้เรียนเกิน capacity_required", "Course Head / Maker", "High", "UC-04"),
        @("FR-09", "ระบบต้องแสดง Schedule Workspace แบบ day/week/month calendar และ list view พร้อมตัวกรองรายวิชา วันที่ และช่วงเวลา", "Course Head / Staff / Instructor", "High", "UC-06"),
        @("FR-10", "ระบบต้องแสดง Dashboard และ Alerts สำหรับข้อมูลสำคัญ เช่น ผู้ใช้ รายวิชา ห้อง หลักสูตร ปีการศึกษาปัจจุบัน pipeline และ readiness warnings", "Admin / Staff / Executive", "High", "UC-08"),
        @("FR-11", "ระบบต้องบันทึก Audit Trail สำหรับ login/logout, role switch, user/master data/course offering/student group/schedule/settings/import และต้องค้นหา/กรองย้อนหลังได้", "System Admin", "High", "UC-10"),
        @("FR-12", "ระบบต้อง validate ข้อมูลสำคัญ เช่น unique username/email/course code, วันที่ พ.ศ./ค.ศ., สัดส่วน PA รวม 100%, capacity และ phase gate ก่อนแก้ข้อมูลรายวิชา/ตาราง", "All Users", "High", "UC-02, UC-03, UC-04")
    )

    Replace-TableAllRows $phase2Table $Ns @(
        @("FR-13", "ระบบต้องรองรับ Approval Workflow ออนไลน์เต็มรูปแบบ (submit/approve/reject/revise) และล็อกข้อมูลหลังอนุมัติ", "Executive / Approver", "High", "UC-05"),
        @("FR-14", "ระบบต้องคำนวณ Teaching Load และเชื่อมโยง PA-QA จากชั่วโมงสอนจริงที่อนุมัติแล้ว", "System / Manager", "High", "UC-07"),
        @("FR-15", "ระบบต้องออกรายงานและส่งออก PDF/Excel เช่น ตารางสอน รายงานภาระงาน และการใช้สถานที่", "Manager / Staff", "High", "UC-08"),
        @("FR-16", "ระบบต้องเชื่อมต่อ MU-SSO, Email Gateway และ API ภายนอกเมื่อได้รับ interface และสิทธิ์การเชื่อมต่อ", "All Users / System", "Medium", "UC-01, UC-08"),
        @("FR-17", "ระบบต้องเพิ่ม Executive Dashboard และ Notification เต็มรูปแบบสำหรับการติดตามและตัดสินใจ", "Executive / Manager", "Medium", "UC-08")
    )

    $nfrTable = Find-TableContainingAll $Doc $Ns @("Requirement ID", "ด้าน", "Priority")
    Replace-TableRows $nfrTable $Ns @(
        @("NFR-01", "Security", "ระบบต้องยืนยันตัวตนก่อนใช้งานภายใน ควบคุมสิทธิ์ด้วย active role ใน session และตรวจสิทธิ์กับฐานข้อมูลทุกครั้ง", "High"),
        @("NFR-02", "Audit Trail", "ระบบต้องบันทึกเหตุการณ์สำคัญพร้อม actor, action, category, table, record, old/new values, IP และ user agent", "High"),
        @("NFR-03", "Usability", "หน้าจอภาษาไทย อ่านง่าย เหมาะกับผู้ใช้งานหลายช่วงวัย และใช้ dashboard/alerts ช่วยชี้งานสำคัญ", "High"),
        @("NFR-04", "Performance", "การค้นหา กรอง dashboard และ audit log ต้องตอบสนองในเวลาที่เหมาะสม โดยใช้ pagination/index/cache ตามความเหมาะสม", "Medium"),
        @("NFR-05", "Availability", "ระบบควรพร้อมใช้งานตามช่วงเวลาที่หน่วยงานกำหนด และต้องมีแนวทางเปิด/ปิดช่วงจัดตารางที่ชัดเจน", "Medium"),
        @("NFR-06", "Backup and Recovery", "ต้องมีแนวทางสำรองฐานข้อมูล เอกสาร และ source code ตามรอบที่กำหนด", "High"),
        @("NFR-07", "Compatibility", "ระบบควรรองรับ web browser หลัก เช่น Chrome, Edge หรือ Safari", "Medium"),
        @("NFR-08", "Maintainability", "โครงสร้างระบบควรแยก controller, model, service, view และ test ให้บำรุงรักษาได้", "Medium"),
        @("NFR-09", "Data Integrity", "ระบบต้องใช้ validation, foreign key, unique constraint, phase gate และ downstream-reference guard เพื่อลดข้อมูลผิดพลาด", "High"),
        @("NFR-10", "Privacy", "ข้อมูลสำคัญ เช่น password, remember token และ token ต่าง ๆ ต้องถูก hash หรือ mask ใน audit/detail payload", "High")
    )

    $dataTable = Find-TableContainingAll $Doc $Ns @("Data ID", "กลุ่มข้อมูล", "ผู้รับผิดชอบข้อมูล")
    Replace-TableRows $dataTable $Ns @(
        @("D-01", "ข้อมูลผู้ใช้งานและสิทธิ์", "users, user_roles, instructor_profiles: username, employee_id, email, password hash, active status, roles, primary role, title, department, employment type, hired date, PA percentages", "บันทึกในระบบ / CSV Import", "System Admin"),
        @("D-02", "ข้อมูลหลักทางวิชาการ", "departments, curriculums, courses, course_roles, activity_types, location_types, rooms: หลักสูตร รายวิชา หน่วยกิต ชั่วโมงเรียน จำนวนรับ ห้อง/แหล่งฝึก ประเภทกิจกรรม", "Master Data / CSV Import", "System Admin / Staff"),
        @("D-03", "ข้อมูลปีการศึกษาและการตั้งค่า", "academic_years, system_settings: ปี/ภาคเรียน วันที่เริ่ม-สิ้นสุด phase, current year, teaching weeks, quota hours, PA criteria, dismissed warnings", "ตั้งค่าในระบบ", "System Admin / Staff"),
        @("D-04", "ข้อมูลรอบเปิดสอนและกลุ่มนักศึกษา", "course_offerings, course_offering_instructors, student_groups: coordinator, approval_status, planned hours, total students, practicum rotation, instructor pool, group code/count/color", "สร้าง/ซิงก์จาก Master Data และแก้ในช่วง scheduling", "Course Head / System Admin"),
        @("D-05", "ข้อมูลตารางสอน", "schedules, schedule_instructors, schedule_student_groups: date range, time, activity type, room, topic, capacity_required, lead/co-instructors, student groups, status, remark", "บันทึกใน Schedule Workspace", "Course Head / Maker"),
        @("D-06", "ข้อมูลตรวจสอบและแจ้งเตือน", "audit_logs, alerts/summary จาก query และ cache: category, action, old/new values, context, critical/warning counts", "สร้างจากระบบอัตโนมัติ", "System"),
        @("D-07", "ข้อมูลอนุมัติ/แจ้งเตือนในอนาคต", "course_offering_approvals, notifications: submit/approve/reject/revise, approval_update, warning types", "Phase 2 / To Confirm", "Executive / System")
    )

    $uiTable = Find-TableContainingAll $Doc $Ns @("Screen ID", "ชื่อหน้าจอ", "รายละเอียดหน้าจอ")
    Replace-TableRows $uiTable $Ns @(
        @("UI-01", "Login Page", "All Users", "หน้าจอเข้าสู่ระบบด้วย username/email และ password"),
        @("UI-02", "Role Dashboard Hub", "All Users", "redirect ไป dashboard ตาม active role และรองรับการสลับบทบาท"),
        @("UI-03", "Admin Dashboard", "System Admin", "แสดง current academic year, phase badge, stats ผู้ใช้/รายวิชา/ห้อง/หลักสูตร, pipeline, alerts และ shortcuts"),
        @("UI-04", "User Management", "System Admin", "จัดการผู้ใช้ บทบาท โปรไฟล์อาจารย์ สถานะบัญชี และ CSV import"),
        @("UI-05", "Settings / Academic Year / PA", "Admin / Staff", "ตั้งค่าปีการศึกษา เปิด/ปิด scheduling window และตั้งค่าเกณฑ์ PA/ชั่วโมงสอน"),
        @("UI-06", "Master Data", "Admin / Staff", "จัดการภาควิชา หลักสูตร รายวิชา ห้อง ประเภทสถานที่ ประเภทกิจกรรม และข้อมูลอาจารย์"),
        @("UI-07", "Alerts", "System Admin", "แสดง critical readiness, warnings, PA violations และ dismissed warnings"),
        @("UI-08", "Audit Logs", "System Admin", "ค้นหา กรอง date/category/action/actor และดูรายละเอียด audit log"),
        @("UI-09", "Course Offerings", "Course Head / Maker", "รายการรอบเปิดสอนที่รับผิดชอบพร้อมสถานะกลุ่มนักศึกษาและจำนวนรับ"),
        @("UI-10", "Course Offering Detail", "Course Head / Maker", "จัดการ practicum rotation, instructor pool, roles, student groups และ bulk groups"),
        @("UI-11", "Schedule Workspace", "Course Head / Maker", "calendar/list view แบบ day/week/month พร้อม modal สร้าง แก้ไข ลบตาราง"),
        @("UI-12", "Staff Dashboard", "Support Staff", "แสดง workload overview และ recent activity"),
        @("UI-13", "Instructor Dashboard", "Instructor", "แสดงข้อมูลอาจารย์และ quota/period ตามเกณฑ์ workload"),
        @("UI-14", "Executive Dashboard", "Executive", "แสดงภาพรวมตามสิทธิ์; approval/action เต็มรูปแบบเป็น Phase 2")
    )

    $reportTable = Find-TableContainingAll $Doc $Ns @("Report ID", "ชื่อรายงาน / Dashboard", "รูปแบบ")
    Replace-TableRows $reportTable $Ns @(
        @("RPT-01", "Admin Dashboard", "System Admin", "จำนวนผู้ใช้ active/total, รายวิชา active/total, ห้องตามประเภท, หลักสูตรตามระดับ, current academic year, course offering pipeline และ alerts", "Dashboard"),
        @("RPT-02", "Readiness Alerts", "System Admin", "รายการ critical เช่น ไม่มีปีการศึกษาปัจจุบัน ไม่มีหลักสูตร/รายวิชา/ประเภทกิจกรรม/ประเภทสถานที่ หรือรายวิชา active ไม่มีหัวหน้าวิชา", "Dashboard / List"),
        @("RPT-03", "Audit Log Report", "System Admin", "ประวัติการดำเนินการพร้อมตัวกรอง actor/category/action/date และรายละเอียด old/new values", "Table"),
        @("RPT-04", "Schedule Workspace View", "Course Head / Maker", "ตารางรายวัน รายสัปดาห์ รายเดือน และรายการทั้งหมดของรายวิชา", "Calendar / List"),
        @("RPT-05", "Instructor Workload Snapshot", "Admin / Staff / Instructor", "แสดงข้อมูล quota/สัดส่วน PA เบื้องต้นจากโปรไฟล์อาจารย์และ system settings", "Dashboard"),
        @("RPT-06", "Teaching Load / Resource Reports", "Manager / Staff", "รายงานภาระงานสอน การใช้สถานที่ และส่งออก PDF/Excel", "Phase 2")
    )
    Set-BodyParagraphStarting $Doc $Ns "Executive / Approver" "หมายเหตุ: รายงานส่งออก PDF/Excel และ Executive Dashboard เต็มรูปแบบอยู่ใน Phase 2." -Optional | Out-Null

    $integrationTable = Find-TableContainingAll $Doc $Ns @("Interface ID", "ระบบที่เชื่อมต่อ", "สถานะข้อมูล")
    Replace-TableRows $integrationTable $Ns @(
        @("INT-01", "Internal Auth", "ยืนยันตัวตนด้วย username/email และ password สำหรับ Version 1", "Laravel Auth / Session", "Implemented"),
        @("INT-02", "MU-SSO", "เชื่อมต่อบัญชีมหาวิทยาลัยมหิดลในอนาคต", "OAuth 2.0 / SSO API", "To Confirm / Phase 2"),
        @("INT-03", "CSV Import", "นำเข้าผู้ใช้งาน ห้อง/แหล่งฝึก และรายวิชาจากไฟล์ CSV", "Manual Import", "Implemented"),
        @("INT-04", "Email Gateway", "ส่งแจ้งเตือนอัตโนมัติเมื่อมี approval/update หรือ notification", "SMTP / API", "To Confirm / Phase 2"),
        @("INT-05", "PA-QA / External Reporting", "ส่งต่อข้อมูลชั่วโมงสอนและภาระงานไปยังระบบ PA-QA หรือรายงานภายนอก", "REST API / Export", "To Confirm / Phase 2")
    )
    Set-ParagraphStarting $Doc $Ns "ตัวอย่างข้อความ" "หมายเหตุ" -Optional | Out-Null
    if (-not (Set-ParagraphStarting $Doc $Ns "รายละเอียดเชิงเทคนิคของการเชื่อมต่อระบบภายนอก" "รายละเอียดเชิงเทคนิคของ MU-SSO, Email Gateway, PA-QA API, endpoint, authentication, data format และรอบการแลกเปลี่ยนข้อมูลต้องได้รับการยืนยันเพิ่มเติมในขั้นออกแบบระบบหรือ Phase 2. ใน Version 1 การเชื่อมต่อที่ใช้งานจริงคือ internal auth และ manual CSV import." -Optional)) {
        Set-ParagraphStarting $Doc $Ns "รายละเอียดเชิงเทคนิคของ MU-SSO" "รายละเอียดเชิงเทคนิคของ MU-SSO, Email Gateway, PA-QA API, endpoint, authentication, data format และรอบการแลกเปลี่ยนข้อมูลต้องได้รับการยืนยันเพิ่มเติมในขั้นออกแบบระบบหรือ Phase 2. ใน Version 1 การเชื่อมต่อที่ใช้งานจริงคือ internal auth และ manual CSV import." | Out-Null
    }

    $securityTable = Find-TableContainingAll $Doc $Ns @("Security ID", "รายละเอียดข้อกำหนด")
    Replace-TableRows $securityTable $Ns @(
        @("SEC-01", "ระบบต้องกำหนดสิทธิ์เข้าถึงตาม role และ active_role ใน session พร้อมตรวจสอบกับฐานข้อมูลก่อนเข้าหน้า protected"),
        @("SEC-02", "ผู้ใช้งานต้องเข้าสู่ระบบก่อนใช้งานฟังก์ชันภายใน และบัญชี inactive ต้องไม่สามารถเข้าสู่ระบบหรือใช้งานต่อได้"),
        @("SEC-03", "รหัสผ่านต้องถูก hash และไม่บันทึก raw password ลง audit log หรือ payload รายละเอียด"),
        @("SEC-04", "ระบบต้องมี CSRF protection และใช้ no-store/no-cache headers เพื่อลดความเสี่ยงจาก back navigation หลัง logout"),
        @("SEC-05", "ระบบต้อง validate ข้อมูลสำคัญทั้งฝั่ง server เช่น unique fields, date, role, capacity, PA percentage และ phase gate"),
        @("SEC-06", "ระบบต้องบันทึก audit log สำหรับเหตุการณ์สำคัญและ mask sensitive fields ก่อนบันทึกหรือแสดงผล"),
        @("SEC-07", "ระบบต้องจำกัดการจัดการตารางและ course offering ให้เฉพาะหัวหน้าวิชาที่เป็น coordinator ของรายวิชานั้น")
    )

    $auditTable = Find-TableContainingAll $Doc $Ns @("Audit ID", "รายการที่ต้องบันทึก")
    Replace-TableRows $auditTable $Ns @(
        @("AUD-01", "การเข้าสู่ระบบและออกจากระบบสำเร็จ", "บันทึก user, วันเวลา, action login/logout, IP และ user agent"),
        @("AUD-02", "การสลับบทบาท", "บันทึก active_role เดิมและใหม่เมื่อผู้ใช้เปลี่ยนบทบาท"),
        @("AUD-03", "การจัดการผู้ใช้และสิทธิ์", "บันทึกการสร้าง แก้ไข ปิด/เปิดใช้งาน ลบผู้ใช้ เปลี่ยน password และ import users โดยไม่เก็บ raw password"),
        @("AUD-04", "การจัดการ Master Data", "บันทึกการสร้าง แก้ไข ลบ ภาควิชา หลักสูตร รายวิชา ห้อง ประเภทสถานที่ ประเภทกิจกรรม ปีการศึกษา และ CSV import"),
        @("AUD-05", "การตั้งค่าและ Scheduling Window", "บันทึกการแก้ค่าคงที่ PA/ชั่วโมงสอน การเปิด/ปิดช่วงจัดตาราง และการ sync course offerings"),
        @("AUD-06", "การจัดการ Course Offering", "บันทึกการแก้ rotation, เพิ่ม/ลบ/เปลี่ยนบทบาทผู้สอน และสร้าง/แก้ไข/ลบ/bulk กลุ่มนักศึกษา"),
        @("AUD-07", "การจัดการตารางสอน", "บันทึกการสร้าง แก้ไข ลบ schedule พร้อม snapshot/diff ของวัน เวลา ห้อง ผู้สอน กลุ่มนักศึกษา และหัวข้อ"),
        @("AUD-08", "การค้นหาและตรวจสอบย้อนหลัง", "Audit Log UI ต้องค้นหา กรอง category/action/actor/date และเปิดดูรายละเอียดได้")
    )

    $acceptTable = Find-TableContainingAll $Doc $Ns @("Requirement ID", "เกณฑ์การยอมรับ", "วิธีตรวจสอบ")
    Replace-TableRows $acceptTable $Ns @(
        @("FR-01", "ผู้ใช้ที่มีบัญชี active สามารถ login ด้วย username/email และผู้ที่กรอกข้อมูลผิดหรือ inactive ไม่สามารถเข้าใช้งานได้", "ทดสอบ login/logout และ inactive user"),
        @("FR-02", "ระบบบังคับ RBAC และผู้ใช้สลับได้เฉพาะบทบาทที่ตนมี", "ทดสอบ role middleware และ role switch"),
        @("FR-03", "Admin สามารถสร้าง แก้ไข ปิดใช้งาน ลบ และนำเข้าผู้ใช้ CSV พร้อม validation ได้", "ทดสอบ User Management และ CSV import"),
        @("FR-04", "Admin/Staff สามารถจัดการ Master Data และ import rooms/courses ได้ตามสิทธิ์", "ทดสอบ Master Data และ CSV import"),
        @("FR-05", "Admin เปิด scheduling window ได้เมื่อไม่มี critical และระบบสร้าง/ซิงก์ course offerings", "ทดสอบ SchedulingPhase"),
        @("FR-06", "Course Head จัดการ instructor pool, student groups และ practicum note ได้เฉพาะช่วง scheduling", "ทดสอบ CourseOffering"),
        @("FR-07", "Course Head สร้าง แก้ไข ลบตารางพร้อมผู้สอนและกลุ่มนักศึกษาได้", "ทดสอบ Schedule Management"),
        @("FR-08", "ระบบบล็อก instructor/room/student group overlap และ capacity over limit", "ทดสอบ Conflict Check"),
        @("FR-10", "Dashboard และ Alerts แสดงข้อมูล readiness และสถิติปัจจุบัน", "ทดสอบ Admin Dashboard / Alert System"),
        @("FR-11", "Audit Logs ถูกสร้างและค้นหา/กรอง/ดูรายละเอียดได้", "ทดสอบ AuditLog และ AuditLogIntegration")
    )

    $rtmTable = Find-TableContainingAll $Doc $Ns @("Requirement ID", "Design Reference", "Test Case ID")
    Replace-TableRows $rtmTable $Ns @(
        @("FR-01", "Login ด้วย username/email/password", "UC-01", "AuthController / auth.login", "AuthTest / E2E auth", "Implemented"),
        @("FR-02", "RBAC และ Role Switch", "UC-01, UC-02", "CheckRole / DashboardController", "RBACTest / RoleSwitchTest", "Implemented"),
        @("FR-03", "User Management และ User CSV", "UC-02, UC-11", "AdminUserController", "AdminUserManagementTest / CsvImportValidationTest", "Implemented"),
        @("FR-04", "Master Data และ CSV rooms/courses", "UC-03, UC-11", "Admin/Staff MasterDataController", "MasterDataRedirectTest / CsvImportValidationTest", "Implemented"),
        @("FR-05", "Academic Year และ Scheduling Window", "UC-09", "AdminSettingController", "SchedulingPhaseTest", "Implemented"),
        @("FR-06", "Course Offering, Instructor Pool, Student Groups", "UC-04", "CourseOfferingController", "CourseOfferingManagementTest / CourseOfferingShowPageTest", "Implemented"),
        @("FR-07", "Schedule CRUD และ Calendar/List View", "UC-04, UC-06", "ScheduleController / schedules.index", "ScheduleManagementTest", "Implemented"),
        @("FR-08", "Conflict Check", "UC-04", "ScheduleConflictChecker", "ScheduleManagementTest conflict cases", "Implemented"),
        @("FR-09", "Search & Filter / Views", "UC-06", "Blade filters / ScheduleController", "SearchM7Test / ScheduleManagementTest", "Implemented / Partial"),
        @("FR-10", "Dashboard และ Alerts", "UC-08", "DashboardController / AlertController", "AdminDashboardTest / AlertSystemTest", "Implemented"),
        @("FR-11", "Audit Trail", "UC-10", "AuditLogger / AuditLogController", "AuditLogTest / AuditLogIntegrationTest", "Implemented"),
        @("FR-13", "Approval Workflow", "UC-05", "CourseOfferingApproval model/table", "Pending", "Phase 2"),
        @("FR-15", "Report/Export PDF/Excel", "UC-08", "Reporting module", "Pending", "Phase 2")
    )

    $openIssueTable = Find-TableContainingAll $Doc $Ns @("Issue ID", "ประเด็นที่ต้องสอบถาม", "สถานะ")
    Replace-TableRows $openIssueTable $Ns @(
        @("OQ-01", "ยืนยันรูปแบบ MU-SSO, token, callback URL และสิทธิ์เชื่อมต่อของมหาวิทยาลัย", "คณะพยาบาลศาสตร์ / IT", "Open", "ก่อน Phase 2 Integration"),
        @("OQ-02", "ยืนยันช่องทาง Email Gateway/SMTP/API สำหรับ notification", "คณะพยาบาลศาสตร์ / IT", "Open", "ก่อน Phase 2 Notification"),
        @("OQ-03", "ยืนยันสูตรคำนวณ Teaching Load/PA และรูปแบบรายงาน PDF/Excel ที่ต้องใช้จริง", "คณะพยาบาลศาสตร์ / ผู้บริหาร", "Open", "ก่อน Phase 2 Reporting"),
        @("OQ-04", "ยืนยัน policy ของ Approval Workflow เช่น ผู้อนุมัติ ลำดับอนุมัติ สถานะหลัง reject/revise และเงื่อนไข lock", "คณะพยาบาลศาสตร์ / ผู้บริหาร", "Open", "ก่อน Phase 2 Approval"),
        @("OQ-05", "ยืนยันสภาพแวดล้อม production, backup, domain/SSL และรอบการ deploy", "คณะพยาบาลศาสตร์ / IT", "Open", "ก่อน Deployment")
    )
}

function Update-WP01 {
    param([xml]$Doc, [System.Xml.XmlNamespaceManager]$Ns)

    Set-CommonCoverAndControl $Doc $Ns "WP01"

    $scopeTable = Find-TableContainingAll $Doc $Ns @("รายการงาน / โมดูล / ฟังก์ชัน", "รายละเอียดเบื้องต้น")
    Replace-TableRows $scopeTable $Ns @(
        @("1", "การจัดการข้อมูลพื้นฐาน (Master Data)", "ตั้งค่าปีการศึกษา ภาควิชา หลักสูตร รายวิชา อาจารย์ ประเภทกิจกรรม ประเภทสถานที่ ห้อง/แหล่งฝึก และรองรับ CSV import บางส่วน"),
        @("2", "การจัดการผู้ใช้งานและสิทธิ์ (User / RBAC)", "จัดการบัญชี ผู้ใช้หลายบทบาท บทบาทหลัก สถานะ active และการสลับบทบาท"),
        @("3", "การจัดการรายวิชาและรอบเปิดสอน (Course / Course Offering)", "กำหนดรายวิชา หัวหน้าวิชา ผู้รับผิดชอบรายวิชา ชุดผู้สอน และกลุ่มนักศึกษา"),
        @("4", "การจัดการตารางสอนและตารางฝึก (Schedule)", "สร้าง แก้ไข ลบรายการตารางแบบ Block Schedule พร้อมผู้สอน ห้อง/แหล่งฝึก และกลุ่มนักศึกษา"),
        @("5", "ระบบตรวจสอบตารางชน (Conflict Engine)", "ตรวจอาจารย์ ห้อง/สถานที่ และกลุ่มนักศึกษาชนก่อนบันทึก"),
        @("6", "Dashboard และ Alerts", "แสดงสถิติหลัก readiness alerts, warning summary และ pipeline ของรอบเปิดสอน"),
        @("7", "การค้นหาและกรองข้อมูล (Search & Filter)", "ค้นหา/กรองข้อมูลผู้ใช้ Master Data Audit Logs และมุมมองตารางตามช่วงเวลา"),
        @("8", "การแสดงผลหลายมุมมอง (Schedule Views)", "แสดงตารางแบบ day/week/month calendar และ list view"),
        @("9", "ประวัติการแก้ไขข้อมูล (Audit Trail)", "บันทึกเหตุการณ์สำคัญย้อนหลังสำหรับ user, master data, course offering, schedule, settings, import และ auth"),
        @("10", "การตั้งค่า Workload/PA พื้นฐาน", "ตั้งค่าเกณฑ์ PA และชั่วโมงสอนเพื่อเตรียมข้อมูลสำหรับภาระงาน"),
        @("11", "กระบวนการอนุมัติตาราง (Approval Flow - Phase 2)", "อนุมัติ/ปฏิเสธ/ส่งกลับแก้ไขและล็อกข้อมูลหลังอนุมัติ"),
        @("12", "ระบบรายงานและส่งออก (Reporting - Phase 2)", "รายงาน Teaching Load, การใช้สถานที่ และส่งออก PDF/Excel"),
        @("13", "การเชื่อมต่อภายนอก (Integration - To Confirm)", "MU-SSO, Email Gateway และ PA-QA/API ภายนอก"),
        @("14", "การทดสอบระบบ (System Testing)", "ทดสอบด้วย Feature/Unit/E2E และ UAT ตามเกณฑ์ที่ตกลง"),
        @("15", "การส่งมอบและติดตั้ง (Deployment)", "ติดตั้งระบบบนสภาพแวดล้อมเป้าหมาย พร้อม Source Code และเอกสารตามมาตรฐานโครงการ")
    )
    Set-ParagraphStarting $Doc $Ns "สำหรับ * โมดูลนี้" "หมายเหตุ: ขอบเขต Version 1 ปรับให้สะท้อนสถานะโค้ดปัจจุบัน โดยรวม Audit Trail, Dashboard/Alerts และ Conflict Check พื้นฐานไว้แล้ว ส่วน Approval Workflow, Reporting/Export, PA Integration เต็มรูปแบบ และ Integration ภายนอกเป็น Phase 2 หรือรอยืนยันข้อมูลเทคนิค." -Optional | Out-Null

    $crTable = Find-TableContainingAll $Doc $Ns @("CR No.", "รายการเปลี่ยนแปลง", "ผลกระทบ")
    Replace-TableRows $crTable $Ns @(,
        @("CR-01", "ปรับขอบเขต Phase: Audit Trail, Dashboard/Alerts และ Conflict Check พื้นฐานรวมใน Version 1; Approval Workflow, Reporting/Export, PA Integration เต็มรูปแบบ และ Integration ภายนอกย้ายไป Phase 2", "Scope Version 1 ชัดเจนขึ้น / งบประมาณ Phase 1 ไม่เปลี่ยน / ลดความเสี่ยงจาก integration ที่ยังไม่ยืนยัน", "Updated", "25 พฤษภาคม 2569")
    )
}

function Update-WP02 {
    param([xml]$Doc, [System.Xml.XmlNamespaceManager]$Ns)

    Set-CommonCoverAndControl $Doc $Ns "WP02"

    $workPlan = Find-TableContainingAll $Doc $Ns @("Phase", "กิจกรรมหลัก", "ผลส่งมอบ")
    Replace-TableRows $workPlan $Ns @(
        @("Phase 1", "Project Initiation and Planning", "2 วัน", "WP-01, WP-02"),
        @("Phase 2", "Requirements Analysis", "3 วัน", "WP-03 SRS"),
        @("Phase 3", "System Design", "3 วัน", "WP-04 Design Document"),
        @("Phase 4", "Software Development Version 1", "14 วัน", "WP-05, WP-06, WP-07"),
        @("Phase 5", "Testing", "3 วัน", "WP-07"),
        @("Phase 6", "Deployment and Training", "2 วัน", "WP-08, WP-09"),
        @("Phase 7", "Acceptance", "1 วัน", "WP-10 Acceptance Record"),
        @("Future Release", "Approval, Reporting/Export, PA Integration, External Integration", "Phase 2", "WP-05/WP-06/WP-07 ส่วนต่อยอด")
    )

    $schedule = Find-TableContainingAll $Doc $Ns @("ลำดับ", "กิจกรรม", "วันที่เริ่ม", "สถานะ")
    Replace-TableRows $schedule $Ns @(
        @("1", "Kick-off Meeting", "27/4/2026", "27/4/2026", "Project Manager", "Completed"),
        @("2", "เก็บและวิเคราะห์ Requirement", "27/4/2026", "29/4/2026", "Project Manager", "Completed"),
        @("3", "จัดทำ Project Plan", "29/4/2026", "5/5/2026", "System Analyst", "Completed"),
        @("4", "จัดทำและอนุมัติ SRS", "30/4/2026", "5/5/2026", "System Analyst", "Completed"),
        @("5", "ออกแบบ Flowchart ภาพรวมและ UI Mock Up", "6/5/2026", "8/5/2026", "System Analyst / UI/UX Designer", "Completed"),
        @("6.1", "โมดูล 10: Login, RBAC และ Role Switch", "11/5/2026", "12/5/2026", "Developer", "Completed"),
        @("6.2", "โมดูล 1: Master Data", "13/5/2026", "15/5/2026", "Developer", "Completed"),
        @("6.3", "โมดูล 2: Course Management / Course Offering", "18/5/2026", "19/5/2026", "Developer", "Completed"),
        @("6.4", "รวมโมดูล Sprint และ Test", "19/5/2026", "19/5/2026", "Developer / Tester", "Completed"),
        @("6.5", "โมดูล 3: Schedule Management / Block Schedule", "20/5/2026", "22/5/2026", "Developer", "Completed"),
        @("6.6", "โมดูล 4: Conflict Checking", "21/5/2026", "25/5/2026", "Developer", "Completed"),
        @("6.7", "โมดูล 8: Schedule Views / Calendar / List", "22/5/2026", "25/5/2026", "Developer", "Completed"),
        @("6.8", "โมดูล 7: Search & Filter", "20/5/2026", "27/5/2026", "Developer", "In Progress / Partial"),
        @("6.9", "โมดูล 12: Audit Trail และ Audit Log UI", "19/5/2026", "25/5/2026", "Developer", "Completed"),
        @("6.10", "Dashboard และ Alerts", "22/5/2026", "25/5/2026", "Developer", "Completed / Partial"),
        @("7", "ทดสอบระบบภายใน (Feature / Unit / E2E / Manual)", "11/5/2026", "28/5/2026", "Tester / Developer", "In Progress"),
        @("8", "User Acceptance Test (UAT)", "29/5/2026", "2/6/2026", "Customer / Tester", "Not Started"),
        @("9", "อบรมผู้ใช้งาน", "4/6/2026", "5/6/2026", "Project Team", "Not Started"),
        @("10", "ส่งมอบและปิดโครงการ Version 1", "7/6/2026", "7/6/2026", "Project Manager", "Not Started"),
        @("11.1", "Phase 2: Approval Workflow", "Phase 2", "Phase 2", "Developer", "Not Started"),
        @("11.2", "Phase 2: Reporting / Export PDF-Excel", "Phase 2", "Phase 2", "Developer", "Not Started"),
        @("11.3", "Phase 2: Teaching Load / PA Integration เต็มรูปแบบ", "Phase 2", "Phase 2", "Developer", "Not Started"),
        @("11.4", "Phase 2: MU-SSO / Email / External API", "Phase 2", "Phase 2", "Developer", "Not Started")
    )

    $tools = Find-TableContainingAll $Doc $Ns @("Programming Language", "Deployment Environment")
    Replace-TableRows $tools $Ns @(
        @("Programming Language", "PHP, JavaScript"),
        @("Framework", "Laravel, Blade, Alpine.js"),
        @("Database", "MySQL / MariaDB compatible"),
        @("Version Control", "Git, GitHub"),
        @("Project Tracking", "Trello / Issue Log"),
        @("Communication", "LINE, Zoom / Messenger"),
        @("Testing Tool", "PHPUnit / Laravel Feature Tests, Playwright E2E, Manual Test"),
        @("Deployment Environment", "Local / Test Server; Production to confirm")
    )

    $cr = Find-TableContainingAll $Doc $Ns @("CR ID", "รายการเปลี่ยนแปลง", "สถานะ")
    Replace-TableRows $cr $Ns @(,
        @("CR-01", "ปรับสถานะ Phase ตามโค้ดปัจจุบัน: Audit Trail, Dashboard/Alerts และ Conflict Check พื้นฐานอยู่ใน Version 1; Approval/Reporting/PA Integration/External Integration อยู่ Phase 2", "Scope / Time / Risk", "Project Team", "Updated", "25 พฤษภาคม 2569")
    )

    $testPlan = Find-TableContainingAll $Doc $Ns @("ประเภทการทดสอบ", "รายละเอียด", "ผู้รับผิดชอบ")
    Replace-TableRows $testPlan $Ns @(
        @("Unit Testing", "ทดสอบ helper/service เช่น ThaiDate และ logic เฉพาะส่วน", "Developer"),
        @("Feature Testing", "ทดสอบ Laravel controller, route, validation, RBAC, CRUD, scheduling, conflict, audit และ alerts", "Developer / Tester"),
        @("Integration Testing", "ทดสอบการทำงานร่วมกันของ module เช่น scheduling window -> course offering -> schedule -> audit", "Developer / Tester"),
        @("E2E Testing", "ทดสอบ flow สำคัญผ่าน Playwright เช่น login, user management, master data และ course management", "Tester"),
        @("System Testing", "ทดสอบระบบตาม SRS และ acceptance criteria", "Tester"),
        @("User Acceptance Testing", "ทดสอบโดยผู้ใช้งานจริงหรือ key users", "Customer / Key Users"),
        @("Regression Testing", "ทดสอบซ้ำหลังแก้ไขหรือเพิ่มฟังก์ชัน", "Tester / Developer")
    )
}

function Update-DocxContent {
    param([xml]$Doc, [string]$Kind)
    $ns = New-NsManager $Doc
    switch ($Kind) {
        "WP01" { Update-WP01 $Doc $ns }
        "WP02" { Update-WP02 $Doc $ns }
        "WP03" { Update-WP03 $Doc $ns }
        default { throw "Unknown document kind: $Kind" }
    }
}

function Write-UpdatedDocx {
    param(
        [string]$InputPath,
        [string]$OutputPath,
        [string]$Kind
    )

    if (-not (Test-Path -LiteralPath $InputPath)) {
        throw "Input file was not found: $InputPath"
    }

    $bytesByEntry = @{}
    $zip = [System.IO.Compression.ZipFile]::OpenRead($InputPath)
    try {
        foreach ($entry in $zip.Entries) {
            $stream = $entry.Open()
            try {
                $ms = New-Object System.IO.MemoryStream
                $stream.CopyTo($ms)
                $bytesByEntry[$entry.FullName] = $ms.ToArray()
                $ms.Dispose()
            } finally {
                $stream.Dispose()
            }
        }
    } finally {
        $zip.Dispose()
    }

    $xmlBytes = $bytesByEntry["word/document.xml"]
    if ($null -eq $xmlBytes) { throw "word/document.xml was not found in $InputPath" }

    $xmlText = [System.Text.Encoding]::UTF8.GetString($xmlBytes)
    $doc = New-Object System.Xml.XmlDocument
    $doc.PreserveWhitespace = $true
    $doc.LoadXml($xmlText)
    Update-DocxContent $doc $Kind

    $settings = New-Object System.Xml.XmlWriterSettings
    $settings.Encoding = New-Object System.Text.UTF8Encoding($false)
    $settings.Indent = $false
    $msOut = New-Object System.IO.MemoryStream
    $writer = [System.Xml.XmlWriter]::Create($msOut, $settings)
    $doc.Save($writer)
    $writer.Close()
    $bytesByEntry["word/document.xml"] = $msOut.ToArray()
    $msOut.Dispose()

    $outputParent = Split-Path -Parent $OutputPath
    if ($outputParent -and -not (Test-Path -LiteralPath $outputParent)) {
        New-Item -ItemType Directory -Path $outputParent | Out-Null
    }

    if (Test-Path -LiteralPath $OutputPath) {
        Remove-Item -LiteralPath $OutputPath -Force
    }

    $outZip = [System.IO.Compression.ZipFile]::Open($OutputPath, [System.IO.Compression.ZipArchiveMode]::Create)
    try {
        foreach ($name in $bytesByEntry.Keys) {
            $entry = $outZip.CreateEntry($name)
            $stream = $entry.Open()
            try {
                $data = $bytesByEntry[$name]
                $stream.Write($data, 0, $data.Length)
            } finally {
                $stream.Dispose()
            }
        }
    } finally {
        $outZip.Dispose()
    }
}

if (-not $InPlace -and [string]::IsNullOrWhiteSpace($OutputDirectory)) {
    $OutputDirectory = Join-Path (Get-Location) "docs_updated"
}

foreach ($target in $Targets) {
    $inputPath = $target.Path
    $kind = $target.Kind

    if ($InPlace) {
        $timestamp = Get-Date -Format "yyyyMMdd_HHmmss"
        $backupPath = "$inputPath.bak_$timestamp"
        Copy-Item -LiteralPath $inputPath -Destination $backupPath -Force
        $tmpPath = "$inputPath.tmp_$timestamp.docx"
        Write-UpdatedDocx -InputPath $inputPath -OutputPath $tmpPath -Kind $kind
        Move-Item -LiteralPath $tmpPath -Destination $inputPath -Force
        Write-Output "$kind updated in place: $inputPath"
        Write-Output "$kind backup: $backupPath"
    } else {
        $outputPath = Join-Path $OutputDirectory (Split-Path -Leaf $inputPath)
        Write-UpdatedDocx -InputPath $inputPath -OutputPath $outputPath -Kind $kind
        Write-Output "$kind updated copy: $outputPath"
    }
}
