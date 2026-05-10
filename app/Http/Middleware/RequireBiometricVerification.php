<?php

namespace App\Http\Middleware;

use App\Models\WebAuthnCredential;
use App\Services\TotpService;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class RequireBiometricVerification
{
    public function handle(Request $request, Closure $next, string $level = 'standard'): Response
    {
        $user = $request->user();
        if (!$user) {
            return response()->json(['message' => 'Unauthenticated.'], 401);
        }

        // Check for biometric session token
        $biometricToken = $request->header('X-Biometric-Token');
        if ($biometricToken && $this->validateBiometricSession($user->id, $biometricToken, $level)) {
            return $next($request);
        }

        // Check for MFA re-auth token (fallback)
        $mfaToken = $request->header('X-MFA-Reauth-Token');
        if ($mfaToken && $this->validateMfaReauth($user, $mfaToken, $level)) {
            return $next($request);
        }

        // Determine which methods are available
        $hasWebAuthn = WebAuthnCredential::where('user_id', $user->id)->active()->exists();
        $hasMfa = $user->mfa_enabled;

        return response()->json([
            'message' => 'Step-up authentication required for this action.',
            'biometric_required' => true,
            'has_webauthn' => $hasWebAuthn,
            'mfa_fallback' => $hasMfa,
            'level' => $level,
            'challenge_url' => $hasWebAuthn ? '/auth/webauthn/authenticate/options' : null,
        ], 428);
    }

    private function validateBiometricSession(int $userId, string $token, string $level): bool
    {
        try {
            $data = json_decode(decrypt($token), true);

            if (($data['user_id'] ?? null) !== $userId) {
                return false;
            }

            $maxAge = match ($level) {
                'critical' => 120,
                'high' => 300,
                'standard' => 900,
                default => 300,
            };

            return (time() - ($data['verified_at'] ?? 0)) < $maxAge;
        } catch (\Exception) {
            return false;
        }
    }

    private function validateMfaReauth($user, string $token, string $level): bool
    {
        if (!$user->mfa_enabled || !$user->mfa_secret) {
            return false;
        }

        // The token is a TOTP code — verify it directly
        $totp = new TotpService();

        return $totp->verify($user->mfa_secret, $token);
    }
}
