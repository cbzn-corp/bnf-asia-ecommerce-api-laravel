<?php

namespace App\Http\Controllers\Concerns;

use App\Support\Auth\AuthUser;
use Illuminate\Http\Request;

trait ResolvesAuthUser
{
    protected function authUser(Request $request): ?AuthUser
    {
        return $request->attributes->get('authUser');
    }

    protected function requireAuthUser(Request $request): AuthUser
    {
        $user = $this->authUser($request);
        if (! $user) {
            abort(401, 'Unauthorized.');
        }

        return $user;
    }
}
