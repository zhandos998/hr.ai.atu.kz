<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\Department;
use Illuminate\Http\Request;

class DepartmentController extends Controller
{
    //
    public function index()
    {
        $departments = Department::query()
            ->with(['positions:id,department_id,name'])
            ->select('id', 'name')
            ->get();

        return response()->json($departments);
    }
}
