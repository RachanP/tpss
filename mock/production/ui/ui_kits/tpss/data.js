// Sample data lifted from ระบบจัดตาราง/mock/maker.html and approver.html
window.TPSS_DATA = (() => {
  const DAYS = [
    { nm: 'จันทร์',  dt: '5 พ.ค.' },
    { nm: 'อังคาร', dt: '6 พ.ค.' },
    { nm: 'พุธ',    dt: '7 พ.ค.' },
    { nm: 'พฤหัสบดี', dt: '8 พ.ค.' },
    { nm: 'ศุกร์',   dt: '9 พ.ค.' },
  ];

  // Activity types — color comes from --act-* in colors_and_type.css
  const TYPES = {
    lecture:    { lbl: 'Lecture',     v: '--act-lecture' },
    lab:        { lbl: 'Lab',         v: '--act-lab' },
    roundward:  { lbl: 'Ward',        v: '--act-ward' },
    bedside:    { lbl: 'Bedside',     v: '--act-ward' },
    preconf:    { lbl: 'Pre-conf',    v: '--act-conference' },
    postconf:   { lbl: 'Post-conf',   v: '--act-conference' },
    reflection: { lbl: 'Reflection',  v: '--act-conference' },
    sdl:        { lbl: 'SDL',         v: '--act-sdl' },
    exam:       { lbl: 'Exam',        v: '--act-exam' },
  };

  const ACTIVITIES = [
    { day: 0, s: '07:00', e: '12:00', type: 'roundward', nm: 'นิเทศ Round Ward + รับเคส', loc: 'Ward 7B, ร.พ.ศิริราช', grps: ['A1','A2','A3'], ins: ['รศ.ดร.มาลี ชาญวิชา'] },
    { day: 0, s: '07:00', e: '12:00', type: 'lab',       nm: 'ปฏิบัติการพยาบาล',          loc: 'ห้อง Lab 3',           grps: ['A7','A8','A9'], ins: ['อ.กนกวรรณ รักษ์ดี'], conflict: true },
    { day: 0, s: '07:00', e: '12:00', type: 'lecture',   nm: 'บรรยาย: การดูแลผู้ป่วยฉุกเฉิน', loc: 'ห้องบรรยาย 201',        grps: ['A4','A5','A6'], ins: ['ผศ.ดร.สมศรี ใจดี'] },
    { day: 0, s: '13:00', e: '15:00', type: 'bedside',   nm: 'Bedside Teaching',           loc: 'Ward 7B ห้อง 703',     grps: ['A4','A5','A6'], ins: ['รศ.ดร.มาลี ชาญวิชา'] },

    { day: 1, s: '07:00', e: '12:00', type: 'roundward', nm: 'นิเทศ Round Ward',          loc: 'Ward 7B, ร.พ.ศิริราช',  grps: ['A4','A5','A6'], ins: ['อ.ดร.วิรัตน์ สุขสวัสดิ์'], conflict: true },
    { day: 1, s: '07:00', e: '12:00', type: 'roundward', nm: 'นิเทศ Round Ward',          loc: 'Ward 5A, ร.พ.ศิริราช',  grps: ['A7','A8','A9'], ins: ['อ.ดร.วิรัตน์ สุขสวัสดิ์'], conflict: true },
    { day: 1, s: '13:00', e: '15:00', type: 'reflection', nm: 'Reflection Group',         loc: 'ห้องประชุมพยาบาล 201',  grps: ['A1','A2','A3'], ins: ['รศ.ดร.มาลี ชาญวิชา'] },

    { day: 2, s: '07:00', e: '12:00', type: 'lecture',   nm: 'บรรยาย: การพยาบาลผู้ป่วยอายุรกรรม', loc: 'ห้องบรรยาย 201',  grps: ['A1','A2','A3'], ins: ['ผศ.ดร.สมศรี ใจดี'] },
    { day: 2, s: '07:00', e: '12:00', type: 'lab',       nm: 'ปฏิบัติการพยาบาล',          loc: 'ห้อง Lab 2',           grps: ['A4','A5','A6'], ins: ['รศ.ดร.มาลี ชาญวิชา'] },
    { day: 2, s: '13:00', e: '14:30', type: 'bedside',   nm: 'Bedside Teaching',           loc: 'Ward 5A ห้อง 504',     grps: ['A7','A8','A9'], ins: ['ผศ.ดร.สมศรี ใจดี'] },

    { day: 3, s: '07:00', e: '12:00', type: 'roundward', nm: 'นิเทศ Round Ward',          loc: 'Ward 7B, ร.พ.ศิริราช',  grps: ['A1','A2','A3'], ins: ['รศ.ดร.มาลี ชาญวิชา'] },
    { day: 3, s: '13:00', e: '14:30', type: 'exam',      nm: 'สอบ Procedure',             loc: 'ห้อง Skills Lab',       grps: ['A4','A5','A6'], ins: ['อ.กนกวรรณ รักษ์ดี'] },
    { day: 3, s: '13:00', e: '15:00', type: 'postconf',  nm: 'Post-conference',           loc: 'ห้องประชุมพยาบาล 201',  grps: ['A1','A2','A3'], ins: ['รศ.ดร.มาลี ชาญวิชา'] },

    { day: 4, s: '07:00', e: '12:00', type: 'lab',       nm: 'ปฏิบัติการพยาบาล',          loc: 'ห้อง Lab 2',           grps: ['A1','A2','A3'], ins: ['ผศ.ดร.สมศรี ใจดี'] },
    { day: 4, s: '13:00', e: '16:00', type: 'sdl',       nm: 'SDL / เตรียมรายงาน',         loc: 'ออนไลน์ (LMS)',         grps: ['A1','A2','A3'], ins: [] },
  ];

  const CONFLICTS = [
    { title: 'ห้อง Lab 3 ถูกจองซ้อน',
      desc: 'วันจันทร์ 07:00–12:00 น. — NURS 3002 กลุ่ม A7-A9 และ NURS 2001 กลุ่ม C2 ต่างจอง Lab 3 พร้อมกัน' },
    { title: 'อาจารย์นิเทศซ้อนเวลา',
      desc: 'วันอังคาร 07:00–12:00 น. — อ.ดร.วิรัตน์ ถูกกำหนดนิเทศกลุ่ม A4-A6 (Ward 7B) และ A7-A9 (Ward 5A) พร้อมกัน' },
  ];

  const SUBMISSIONS = [
    { id:1, course:'การพยาบาลอนามัยชุมชน',          code:'NURS 3002', by:'รศ.ดร.มาลี ชาญวิชา',    at:'6 พ.ค. 2569, 14:32 น.', acts:27, grps:9, wks:3, conflicts:2, status:'pending' },
    { id:2, course:'ปฏิบัติการพยาบาลมารดา-ทารก', code:'NURS 2015', by:'ผศ.ดร.สุภาพ รักดี',       at:'5 พ.ค. 2569, 09:15 น.', acts:34, grps:6, wks:4, conflicts:0, status:'pending' },
    { id:3, course:'การพยาบาลผู้ป่วยสุขภาพจิต',   code:'NURS 3008', by:'รศ.วิไล พงษ์ไทย',         at:'4 พ.ค. 2569, 16:47 น.', acts:18, grps:4, wks:2, conflicts:0, status:'pending' },
    { id:4, course:'ปฏิบัติการพยาบาลผู้สูงอายุ',  code:'NURS 4001', by:'ศ.ดร.พรทิพย์ อุ่นใจ',     at:'1 พ.ค. 2569',           acts:42, grps:9, wks:6, conflicts:0, status:'approved' },
    { id:5, course:'ปฐมนิเทศภาคฝึกปฏิบัติ',       code:'NURS 3000', by:'รศ.ดร.มาลี ชาญวิชา',    at:'30 เม.ย. 2569',         acts:8,  grps:9, wks:1, conflicts:0, status:'approved' },
    { id:6, course:'การพยาบาลเด็กและวัยรุ่น',     code:'NURS 2020', by:'ผศ.ปิยะนุช สวัสดิ์มงคล', at:'3 พ.ค. 2569',           acts:22, grps:5, wks:3, conflicts:1, status:'rejected' },
  ];

  return { DAYS, TYPES, ACTIVITIES, CONFLICTS, SUBMISSIONS };
})();
