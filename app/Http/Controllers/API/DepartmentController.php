<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\Department;

class DepartmentController extends Controller
{
    //
    public function index()
    {
        $departments = Department::query()
            ->with(['positions:id,department_id,name'])
            ->select('id', 'name', 'parent_id')
            ->get();

        return response()->json($departments);
    }
}
