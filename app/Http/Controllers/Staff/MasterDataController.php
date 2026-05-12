<?php

namespace App\Http\Controllers\Staff;

use App\Http\Controllers\Admin\MasterDataController as AdminMasterDataController;

class MasterDataController extends AdminMasterDataController
{
    // Staff sees the same view but $isAdmin = false (derived from session in parent)
    // Write access limited to: location_types, rooms, courses, student_groups
    // Routes for admin-only sections (departments, instructors, curriculums, activity_types)
    // are not registered for staff, so those actions are blocked at the routing layer.
}
