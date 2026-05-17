<?php

namespace App\Http\Controllers\Staff;

use App\Http\Controllers\Admin\CoursePoolController as AdminCoursePoolController;

class CoursePoolController extends AdminCoursePoolController
{
    // Inherits all CRUD from Admin\CoursePoolController.
    // Route prefix is resolved via session('active_role') in the parent.
}
