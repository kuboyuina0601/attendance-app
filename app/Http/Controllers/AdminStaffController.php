<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Contracts\View\View;

class AdminStaffController extends Controller
{
    /**
     * 一般ユーザーのスタッフ一覧を表示する
     *
     * @return View スタッフ一覧画面
     */
    public function index(): View
    {
        $users = User::where('admin_status', false)
            ->orderBy('id')
            ->get();

        return view('admin.staff-list', compact('users'));
    }
}
