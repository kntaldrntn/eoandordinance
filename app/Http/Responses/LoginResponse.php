<?php

namespace App\Http\Responses;

use Laravel\Fortify\Contracts\LoginResponse as LoginResponseContract;
use Laravel\Fortify\Fortify;

class LoginResponse implements LoginResponseContract
{
    /**
     * Create an HTTP response that represents the object.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Symfony\Component\HttpFoundation\Response
     */
    public function toResponse($request)
    {
        // --- NEW ROLE CHECK LOGIC ---
        if (auth()->user()->role === 'read_only') {
            return redirect('/membership');
        }

        // Default redirect for Admin/Users (usually goes to /dashboard)
        return redirect()->intended(Fortify::redirects('login'));
    }
}