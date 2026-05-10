<?php

namespace App\Services;

use App\Models\User;
use App\Models\WebAuthnCredential;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

class WebAuthnService
{
    private string $rpName = 'KuraDigital';
    private string $rpId;

    public function __construct()
    {
        $this->rpId = parse_url(config('app.url', 'https://kuradigital.co.ke'), PHP_URL_HOST) ?? 'localhost';
    }

    public function generateRegistrationOptions(User $user): array
    {
        $challenge = random_bytes(32);
        $challengeB64 = $this->base64UrlEncode($challenge);

        cache()->put("webauthn_reg_{$user->id}", $challengeB64, 300);

        $existingCredentials = WebAuthnCredential::where('user_id', $user->id)
            ->active()
            ->pluck('credential_id')
            ->map(fn ($id) => ['id' => $id, 'type' => 'public-key'])
            ->toArray();

        return [
            'challenge' => $challengeB64,
            'rp' => [
                'name' => $this->rpName,
                'id' => $this->rpId,
            ],
            'user' => [
                'id' => $this->base64UrlEncode(Str::padLeft((string) $user->id, 8, '0')),
                'name' => $user->email,
                'displayName' => $user->name,
            ],
            'pubKeyCredParams' => [
                ['type' => 'public-key', 'alg' => -7],   // ES256
                ['type' => 'public-key', 'alg' => -257], // RS256
            ],
            'authenticatorSelection' => [
                'authenticatorAttachment' => 'platform',
                'userVerification' => 'required',
                'residentKey' => 'preferred',
            ],
            'timeout' => 60000,
            'attestation' => 'none',
            'excludeCredentials' => $existingCredentials,
        ];
    }

    public function verifyRegistration(User $user, array $response): WebAuthnCredential
    {
        $storedChallenge = cache()->pull("webauthn_reg_{$user->id}");
        if (!$storedChallenge) {
            throw new \RuntimeException('Registration challenge expired or not found.');
        }

        $credentialId = $response['id'] ?? null;
        $clientDataJSON = $response['response']['clientDataJSON'] ?? null;
        $attestationObject = $response['response']['attestationObject'] ?? null;

        if (!$credentialId || !$clientDataJSON || !$attestationObject) {
            throw new \RuntimeException('Invalid registration response — missing fields.');
        }

        // Decode and verify clientDataJSON
        $clientData = json_decode($this->base64UrlDecode($clientDataJSON), true);
        if (($clientData['type'] ?? '') !== 'webauthn.create') {
            throw new \RuntimeException('Invalid client data type.');
        }

        if (($clientData['challenge'] ?? '') !== $storedChallenge) {
            throw new \RuntimeException('Challenge mismatch.');
        }

        // Store the credential (simplified — production would parse attestationObject for public key)
        return WebAuthnCredential::create([
            'user_id' => $user->id,
            'credential_id' => $credentialId,
            'public_key' => $attestationObject,
            'attestation_type' => 'none',
            'sign_count' => 0,
            'device_name' => $response['device_name'] ?? 'Security Key',
        ]);
    }

    public function generateAuthenticationChallenge(User $user): array
    {
        $challenge = random_bytes(32);
        $challengeB64 = $this->base64UrlEncode($challenge);

        cache()->put("webauthn_auth_{$user->id}", $challengeB64, 300);

        $credentials = WebAuthnCredential::where('user_id', $user->id)
            ->active()
            ->get()
            ->map(fn ($cred) => [
                'id' => $cred->credential_id,
                'type' => 'public-key',
            ])
            ->toArray();

        return [
            'challenge' => $challengeB64,
            'rpId' => $this->rpId,
            'allowCredentials' => $credentials,
            'userVerification' => 'required',
            'timeout' => 60000,
        ];
    }

    public function verifyAuthenticationResponse(User $user, array $response): bool
    {
        $storedChallenge = cache()->pull("webauthn_auth_{$user->id}");
        if (!$storedChallenge) {
            throw new \RuntimeException('Authentication challenge expired or not found.');
        }

        $credentialId = $response['id'] ?? null;
        $clientDataJSON = $response['response']['clientDataJSON'] ?? null;
        $authenticatorData = $response['response']['authenticatorData'] ?? null;
        $signature = $response['response']['signature'] ?? null;

        if (!$credentialId || !$clientDataJSON || !$authenticatorData || !$signature) {
            throw new \RuntimeException('Invalid authentication response — missing fields.');
        }

        // Verify credential belongs to user
        $credential = WebAuthnCredential::where('user_id', $user->id)
            ->where('credential_id', $credentialId)
            ->active()
            ->first();

        if (!$credential) {
            throw new \RuntimeException('Credential not found or inactive.');
        }

        // Verify clientDataJSON
        $clientData = json_decode($this->base64UrlDecode($clientDataJSON), true);
        if (($clientData['type'] ?? '') !== 'webauthn.get') {
            throw new \RuntimeException('Invalid client data type.');
        }

        if (($clientData['challenge'] ?? '') !== $storedChallenge) {
            throw new \RuntimeException('Challenge mismatch.');
        }

        // Update sign count and last used
        $credential->incrementSignCount();

        return true;
    }

    public function getUserCredentials(User $user): Collection
    {
        return WebAuthnCredential::where('user_id', $user->id)
            ->orderByDesc('created_at')
            ->get();
    }

    public function revokeCredential(User $user, int $credentialId): void
    {
        WebAuthnCredential::where('user_id', $user->id)
            ->where('id', $credentialId)
            ->update(['is_active' => false]);
    }

    public function renameCredential(User $user, int $credentialId, string $name): void
    {
        WebAuthnCredential::where('user_id', $user->id)
            ->where('id', $credentialId)
            ->update(['device_name' => $name]);
    }

    public function hasCredentials(User $user): bool
    {
        return WebAuthnCredential::where('user_id', $user->id)->active()->exists();
    }

    private function base64UrlEncode(string $data): string
    {
        return rtrim(strtr(base64_encode($data), '+/', '-_'), '=');
    }

    private function base64UrlDecode(string $data): string
    {
        return base64_decode(strtr($data, '-_', '+/'));
    }
}
