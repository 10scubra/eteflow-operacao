<?php

namespace App\Http\Controllers;

use App\Services\AuditService;
use App\Services\PermissionService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rules\Password;
use Illuminate\View\View;

class PasswordController extends Controller
{
    public function edit(): View
    {
        return view('auth.change-password');
    }

    public function update(Request $request, AuditService $audit, PermissionService $permissions): RedirectResponse
    {
        $data = $request->validate(['password' => ['required', 'confirmed', Password::min(10)->mixedCase()->numbers()]]);
        DB::transaction(function () use ($request, $data, $audit): void {
            $user = $request->user();
            $user->forceFill(['password' => Hash::make($data['password']), 'must_change_password' => false])->save();
            $audit->record($user, 'user.password_changed', $user, ['must_change_password' => true], ['must_change_password' => false]);
        });
        $destination = $permissions->allows($request->user()->fresh(), 'administration.manage_permissions') ? route('master') : route('operation.home');

        return redirect($destination)->with('success', 'Senha definida com sucesso.');
    }
}
