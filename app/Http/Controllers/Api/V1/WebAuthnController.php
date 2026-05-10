<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Services\WebAuthnService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class WebAuthnController extends Controller
{
    public function __construct(
        private WebAuthnService $webauthn,
    ) {}

    public function registerOptions(Request $request): JsonResponse
    {
        $options = $this->webauthn->generateRegistrationOptions($request->user());

        return response()->json($options);
    }

    public function registerVerify(Request $request): JsonResponse
    {
        $request->validate([
            'id' => ['required', 'string'],
            'response.clientDataJSON' => ['required', 'string'],
            'response.attestationObject' => ['required', 'string'],
            'device_name' => ['nullable', 'string', 'max:255'],
        ]);

        try {
            $credential = $this->webauthn->verifyRegistration($request->user(), $request->all());

            return response()->json([
                'message' => 'Biometric credential registered.',
                'credential' => [
                    'id' => $credential->id,
                    'device_name' => $credential->device_name,
                    'created_at' => $credential->created_at->toIso8601String(),
                ],
            ], 201);
        } catch (\RuntimeException $e) {
            return response()->json(['message' => $e->getMessage()], 400);
        }
    }

    public function authenticateOptions(Request $request): JsonResponse
    {
        $user = $request->user();

        if (!$this->webauthn->hasCredentials($user)) {
            return response()->json(['message' => 'No biometric credentials registered.'], 404);
        }

        $options = $this->webauthn->generateAuthenticationChallenge($user);

        return response()->json($options);
    }

    public function authenticateVerify(Request $request): JsonResponse
    {
        $request->validate([
            'id' => ['required', 'string'],
            'response.clientDataJSON' => ['required', 'string'],
            'response.authenticatorData' => ['required', 'string'],
            'response.signature' => ['required', 'string'],
        ]);

        try {
            $verified = $this->webauthn->verifyAuthenticationResponse($request->user(), $request->all());

            if (!$verified) {
                return response()->json(['message' => 'Biometric verification failed.'], 401);
            }

            $token = encrypt(json_encode([
                'user_id' => $request->user()->id,
                'verified_at' => time(),
            ]));

            return response()->json([
                'message' => 'Biometric verified.',
                'biometric_token' => $token,
            ]);
        } catch (\RuntimeException $e) {
            return response()->json(['message' => $e->getMessage()], 400);
        }
    }

    public function listCredentials(Request $request): JsonResponse
    {
        $credentials = $this->webauthn->getUserCredentials($request->user());

        return response()->json([
            'data' => $credentials->map(fn ($c) => [
                'id' => $c->id,
                'device_name' => $c->device_name,
                'is_active' => $c->is_active,
                'last_used_at' => $c->last_used_at?->toIso8601String(),
                'created_at' => $c->created_at->toIso8601String(),
            ]),
        ]);
    }

    public function deleteCredential(Request $request, int $id): JsonResponse
    {
        $this->webauthn->revokeCredential($request->user(), $id);

        return response()->json(['message' => 'Credential revoked.']);
    }

    public function renameCredential(Request $request, int $id): JsonResponse
    {
        $request->validate(['device_name' => ['required', 'string', 'max:255']]);

        $this->webauthn->renameCredential($request->user(), $id, $request->input('device_name'));

        return response()->json(['message' => 'Credential renamed.']);
    }
}
