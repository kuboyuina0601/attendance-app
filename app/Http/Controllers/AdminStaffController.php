<?php

namespace App\Http\Controllers;

use App\Models\User;

class AdminStaffController extends Controller
{
    public function index()
    {
        $users = User::where('admin_status', false)
            ->orderBy('id')
            ->get();

        return view('admin.staff-list', compact('users'));
    }
}
