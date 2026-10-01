<?php

namespace App\Http\Controllers\User\ClerkLoginController;

use App\Http\Controllers\Controller;
use App\Models\User\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Laravel\Sanctum\PersonalAccessToken;

class ClerkLoginController extends Controller
{
    public function login(Request $request): RedirectResponse
    {
        $token = $request->query('token');

        if (! $token) {
            return redirect('/admin/login')->with('error', 'Token tidak ditemukan');
        }

        $accessToken = PersonalAccessToken::findToken($token);

        if (! $accessToken) {
            return redirect('/admin/login')->with('error', 'Token tidak valid');
        }

        $user = $accessToken->tokenable;

        if (! $user || ! $user instanceof User) {
            return redirect('/admin/login')->with('error', 'Pengguna tidak ditemukan');
        }

        Auth::login($user);

        $panel = $user->hasRole('super_admin') ? 'admin' : 'user';

        return redirect("/{$panel}");
    }
}
