<?php

namespace App\Http\Controllers\Auth;

use App\Enums\UserRole;
use App\Http\Controllers\Controller;
use App\Http\Requests\PublicRegistrationRequest;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

class PublicRegistrationController extends Controller
{
    public function create(): View
    {
        abort_unless(config('registration.public_enabled'), 404);

        return view('auth.register');
    }

    public function store(PublicRegistrationRequest $request): RedirectResponse
    {
        $data = $request->validated();

        User::query()->create([
            'name' => trim($data['first_name'].' '.$data['last_name']),
            'email' => $data['email'],
            'password' => $data['password'],
            'role' => UserRole::Viewer,
            'is_active' => true,
            'must_change_password' => false,
        ]);

        return redirect()
            ->route('login')
            ->with('status', 'Votre compte a été créé. Vous pouvez maintenant vous connecter.');
    }
}
