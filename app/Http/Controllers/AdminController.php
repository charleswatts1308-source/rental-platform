<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Models\PageView;
use App\Models\Rental;

class AdminController extends Controller
{
    public function users()
    {
        $users = User::orderBy('created_at', 'desc')
            ->limit(100)
            ->get();

        return view('admin.users', compact('users'));
    }

    public function pageViews()
    {
        $pageViews = PageView::orderBy('view_date_time', 'desc')
            ->limit(100)
            ->get();

        return view('admin.page-views', compact('pageViews'));
    }

    public function rentals()
    {
        $rentals = Rental::select('rentals.*', 'users.name as user_name')
            ->join('users', 'rentals.user_id', '=', 'users.id')
            ->orderBy('date_created', 'desc')
            ->limit(100)
            ->get();

        return view('admin.rentals', compact('rentals'));
    }
}
