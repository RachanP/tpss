<?php

namespace Database\Seeders;

use App\Models\Room;
use App\Models\LocationType;
use Illuminate\Database\Seeder;

class RoomSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Get some location types
        $lectureRoomType = LocationType::where('name', 'ห้องเรียนทั่วไป')->first();
        $labRoomType = LocationType::where('name', 'ห้องปฏิบัติการ')->first();
        $wardType = LocationType::where('name', 'หอผู้ป่วย')->first();
        $hospitalType = LocationType::where('name', 'โรงพยาบาล')->first();

        $rooms = [
            [
                'room_code' => 'R-301',
                'room_name' => 'ห้องบรรยาย 301',
                'building' => 'อาคารเฉลิมพระเกียรติ',
                'capacity' => 120,
                'location_type_id' => $lectureRoomType->id ?? 1,
                'equipment_type' => ['โปรเจคเตอร์', 'ไมโครโฟน', 'คอมพิวเตอร์'],
                'status' => 'active',
                'address' => null,
            ],
            [
                'room_code' => 'R-302',
                'room_name' => 'ห้องบรรยาย 302',
                'building' => 'อาคารเฉลิมพระเกียรติ',
                'capacity' => 100,
                'location_type_id' => $lectureRoomType->id ?? 1,
                'equipment_type' => ['โปรเจคเตอร์', 'ไมโครโฟน', 'ทีวี'],
                'status' => 'active',
                'address' => null,
            ],
            [
                'room_code' => 'LAB-401',
                'room_name' => 'ห้องปฏิบัติการพยาบาล 1',
                'building' => 'อาคารพระศรีนครินทร์',
                'capacity' => 50,
                'location_type_id' => $labRoomType->id ?? 2,
                'equipment_type' => ['หุ่นจำลอง', 'เตียงพยาบาล', 'อุปกรณ์ทางการแพทย์'],
                'status' => 'active',
                'address' => null,
            ],
            [
                'room_code' => 'WARD-A',
                'room_name' => 'หอผู้ป่วยอายุรกรรมหญิง',
                'building' => 'โรงพยาบาลศิริราช',
                'capacity' => null,
                'location_type_id' => $wardType->id ?? 3,
                'equipment_type' => [],
                'status' => 'active',
                'address' => 'ตึก 84 ปี ชั้น 4 โรงพยาบาลศิริราช'
            ],
            [
                'room_code' => 'HOSP-RAMA',
                'room_name' => 'โรงพยาบาลรามาธิบดี',
                'building' => '',
                'capacity' => null,
                'location_type_id' => $hospitalType->id ?? 4,
                'equipment_type' => [],
                'status' => 'active',
                'address' => '270 ถนนพระรามที่ 6 แขวงทุ่งพญาไท เขตราชเทวี กรุงเทพมหานคร 10400'
            ]
        ];

        foreach ($rooms as $roomData) {
            // Manually encode JSON to be absolutely sure
            if (isset($roomData['equipment_type'])) {
                $roomData['equipment_type'] = json_encode($roomData['equipment_type'], JSON_UNESCAPED_UNICODE);
            }

            Room::create($roomData);
        }
    }
}
