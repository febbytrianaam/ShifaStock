<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;

class OnboardingController extends Controller
{
    public function finish(Request $request)
    {
        $request->validate(['outcome' => ['required', 'in:completed,skipped']]);
        // The account, not the browser, owns the first-login preference.
        $request->user()->newQuery()->whereKey($request->user()->id)->whereNull('onboarding_seen_at')
            ->update(['onboarding_seen_at' => now()]);

        return response()->json(['saved' => true]);
    }
}
