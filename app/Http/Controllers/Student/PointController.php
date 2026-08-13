<?php

namespace App\Http\Controllers\Student;

use App\Http\Controllers\Controller;
use Illuminate\View\View;

class PointController extends Controller
{
    public function index(): View
    {
        $user = auth()->user();

        return view('student.points', [
            'user' => $user,
            'transactions' => $user->pointTransactions()
                ->with('performedBy')
                ->latest('id')
                ->paginate(20),
        ]);
    }
}
