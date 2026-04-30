<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rules\Password;
use Illuminate\Validation\ValidationException;
use App\Services\TotpService;

class AuthController extends Controller
{
    public function register(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'email', 'max:255', 'unique:users'],
            'password' => ['required', 'confirmed', Password::min(8)->mixedCase()->numbers()],
            'phone' => ['nullable', 'string', 'max:20'],
        ]);

        $user = User::create([
            'name' => $validated['name'],
            'email' => $validated['email'],
            'password' => $validated['password'],
            'phone' => $validated['phone'] ?? null,
        ]);

        $token = $user->createToken('auth-token')->plainTextToken;

        return response()->json([
            'message' => 'Registration successful.',
            'user' => $this->formatUser($user),
            'token' => $token,
        ], 201);
    }

    public function login(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'email' => ['required', 'string', 'email'],
            'password' => ['required', 'string'],
        ]);

        $user = User::where('email', $validated['email'])->first();

        if (!$user || !Hash::check($validated['password'], $user->password)) {
            throw ValidationException::withMessages([
                'email' => ['The provided credentials are incorrect.'],
            ]);
        }

        if (!$user->isActive()) {
            return response()->json([
                'message' => 'Your account has been ' . $user->account_status . '.',
            ], 403);
        }

        // If MFA is enabled, return a challenge instead of a token
        if ($user->mfa_enabled) {
            return response()->json([
                'message' => 'MFA verification required.',
                'mfa_required' => true,
                'mfa_token' => encrypt($user->id . '|' . now()->addMinutes(10)->timestamp),
            ]);
        }

        $user->update([
            'last_login_at' => now(),
            'last_login_ip' => $request->ip(),
        ]);

        $token = $user->createToken('auth-token')->plainTextToken;

        return response()->json([
            'message' => 'Login successful.',
            'user' => $this->formatUser($user),
            'token' => $token,
        ]);
    }

    public function verifyMfa(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'mfa_token' => ['required', 'string'],
            'otp' => ['required', 'string', 'size:6'],
        ]);

        try {
            $decrypted = decrypt($validated['mfa_token']);
            [$userId, $expiry] = explode('|', $decrypted);
        } catch (\Exception $e) {
            return response()->json(['message' => 'Invalid MFA token.'], 400);
        }

        if (now()->timestamp > (int) $expiry) {
            return response()->json(['message' => 'MFA token expired.'], 400);
        }

        $user = User::findOrFail($userId);

        $totp = new TotpService();

        if (!$user->mfa_secret || !$totp->verify($user->mfa_secret, $validated['otp'])) {
            return response()->json(['message' => 'Invalid OTP.'], 400);
        }

        $user->update([
            'mfa_verified_at' => now(),
            'last_login_at' => now(),
            'last_login_ip' => $request->ip(),
        ]);

        $token = $user->createToken('auth-token')->plainTextToken;

        return response()->json([
            'message' => 'MFA verified.',
            'user' => $this->formatUser($user),
            'token' => $token,
        ]);
    }

    public function logout(Request $request): JsonResponse
    {
        $request->user()->currentAccessToken()->delete();

        return response()->json(['message' => 'Logged out.']);
    }

    public function me(Request $request): JsonResponse
    {
        $user = $request->user();
        $user->load(['campaignMemberships.campaign', 'roles', 'permissions']);

        return response()->json([
            'user' => $this->formatUser($user),
            'campaigns' => $user->activeMemberships->map(function ($m) {
                $role = \Spatie\Permission\Models\Role::findByName($m->role, 'web');
                return [
                    'id' => $m->campaign->id,
                    'name' => $m->campaign->name,
                    'slug' => $m->campaign->slug,
                    'level' => $m->campaign->level,
                    'role' => $m->role,
                    'permissions' => $role->permissions->pluck('name'),
                    'visibility_scope' => $m->visibility_scope,
                    'assigned_wards' => $m->assigned_wards,
                    'assigned_constituencies' => $m->assigned_constituencies,
                    'assigned_counties' => $m->assigned_counties,
                ];
            }),
            'roles' => $user->getRoleNames(),
            'permissions' => $user->getAllPermissions()->pluck('name'),
        ]);
    }

    public function updateProfile(Request $request): JsonResponse
    {
        $user = $request->user();

        $validated = $request->validate([
            'name' => ['sometimes', 'string', 'max:255'],
            'phone' => ['sometimes', 'nullable', 'string', 'max:20'],
        ]);

        $user->update($validated);

        return response()->json([
            'message' => 'Profile updated.',
            'user' => $this->formatUser($user->fresh()),
        ]);
    }

    public function changePassword(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'current_password' => ['required', 'current_password'],
            'password' => ['required', 'confirmed', Password::min(8)->mixedCase()->numbers()],
        ]);

        $request->user()->update(['password' => $validated['password']]);

        return response()->json(['message' => 'Password changed.']);
    }

    public function setupMfa(Request $request): JsonResponse
    {
        $user = $request->user();
        $totp = new TotpService();

        $secret = $totp->generateSecret();
        $user->update(['mfa_secret' => $secret]);

        return response()->json([
            'message' => 'Scan the QR code with your authenticator app, then confirm with a code.',
            'secret' => $secret,
            'provisioning_uri' => $totp->provisioningUri($secret, $user->email),
        ]);
    }

    public function confirmMfa(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'otp' => ['required', 'string', 'size:6'],
        ]);

        $user = $request->user();
        $totp = new TotpService();

        if (!$user->mfa_secret || !$totp->verify($user->mfa_secret, $validated['otp'])) {
            return response()->json(['message' => 'Invalid code. Please try again.'], 400);
        }

        $user->update(['mfa_enabled' => true]);

        return response()->json([
            'message' => 'MFA enabled successfully.',
            'mfa_enabled' => true,
        ]);
    }

    public function disableMfa(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'otp' => ['required', 'string', 'size:6'],
        ]);

        $user = $request->user();
        $totp = new TotpService();

        if (!$user->mfa_secret || !$totp->verify($user->mfa_secret, $validated['otp'])) {
            return response()->json(['message' => 'Invalid code.'], 400);
        }

        $user->update(['mfa_enabled' => false, 'mfa_secret' => null]);

        return response()->json([
            'message' => 'MFA disabled.',
            'mfa_enabled' => false,
        ]);
    }

    private function formatUser(User $user): array
    {
        return [
            'id' => $user->id,
            'name' => $user->name,
            'email' => $user->email,
            'phone' => $user->phone,
            'clearance_level' => $user->clearance_level,
            'account_status' => $user->account_status,
            'mfa_enabled' => $user->mfa_enabled,
            'last_login_at' => $user->last_login_at?->toIso8601String(),
        ];
    }
}
