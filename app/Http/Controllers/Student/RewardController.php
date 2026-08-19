<?php

namespace App\Http\Controllers\Student;

use App\Http\Controllers\Controller;
use App\Services\Rewards\RewardService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class RewardController extends Controller
{
    /** Dismiss the congratulations screen: mark the student's pending rewards as seen. */
    public function seen(Request $request, RewardService $rewards): RedirectResponse
    {
        $rewards->markSeen($request->user());

        return back();
    }
}
