<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Support\ManagedAsset;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;

class ProfileController extends Controller
{
    public function index()
    {
        $admin = Auth::guard('admin')->user()?->refresh();
        return view('admin.profile.index', compact('admin'));
    }

    public function update(Request $request): JsonResponse
    {
        $admin = Auth::guard('admin')->user();
        $currentAvatar = $admin->avatar_url;

        $data = $request->validate([
            'name'  => 'required|string|max:120',
            'email' => 'required|email|max:255|unique:admin_users,email,' . $admin->id,
            'phone' => 'nullable|string|max:20',
            'avatar_url' => 'nullable|string|max:500',
        ]);

        ManagedAsset::replaceIfChanged($currentAvatar, $data['avatar_url'] ?? null);
        $admin->update($data);

        return response()->json(['ok' => true]);
    }

    public function updatePassword(Request $request): JsonResponse
    {
        $admin = Auth::guard('admin')->user();

        $data = $request->validate([
            'current_password' => 'required|string',
            'password'         => 'required|string|min:8|confirmed',
        ]);

        if (! Hash::check($data['current_password'], $admin->password)) {
            return response()->json(['ok' => false, 'message' => 'Senha atual incorreta.'], 422);
        }

        $admin->update(['password' => Hash::make($data['password'])]);

        return response()->json(['ok' => true]);
    }
}
