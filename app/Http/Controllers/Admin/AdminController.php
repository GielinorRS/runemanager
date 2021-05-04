<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\User;
use Illuminate\Http\Request;

class AdminController extends Controller
{
    public function index() {
    	$users = User::count();

        return view('admin.index', compact('users'));
    }
}
