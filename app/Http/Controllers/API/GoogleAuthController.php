<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\GoogleIdentityVerifier;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\RequestException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class GoogleAuthController extends Controller
{
    private const COOKIE = 'google_signin_challenge';

    public function challenge(Request $request)
    {
        if (! config('services.google.client_id')) {
            return response()->json(['enabled' => false])->header('Cache-Control', 'no-store');
        }

        $nonce = Str::random(64);
        $secret = Str::random(64);
        Cache::put('google-signin:nonce:'.$nonce, hash('sha256', $secret), now()->addMinutes(5));

        return response()->json([
            'enabled' => true,
            'client_id' => config('services.google.client_id'),
            'nonce' => $nonce,
        ])->header('Cache-Control', 'no-store')->cookie(
            self::COOKIE, $secret, 5, '/api/auth/google', null, $request->isSecure(), true, false, 'strict'
        );
    }

    public function login(Request $request, GoogleIdentityVerifier $verifier)
    {
        abort_unless(config('services.google.client_id'), 404);
        $fields = $request->validate([
            'credential' => 'required|string|max:12000',
            'nonce' => 'required|string|size:64|alpha_num:ascii',
        ]);

        // Bind the nonce to this browser and consume it once, even on invalid credentials.
        $accepted = Cache::lock('google-signin:lock:'.$fields['nonce'], 10)->get(function () use ($request, $fields) {
            $key = 'google-signin:nonce:'.$fields['nonce'];
            $expected = Cache::get($key);
            $secret = $request->cookie(self::COOKIE);
            if (! is_string($expected) || ! is_string($secret) || ! hash_equals($expected, hash('sha256', $secret))) {
                return false;
            }
            Cache::forget($key);

            return true;
        });
        if (! $accepted) {
            $this->invalid();
        }

        try {
            $identity = $verifier->verify($fields['credential'], $fields['nonce']);
        } catch (ConnectionException|RequestException $exception) {
            return response()->json(['message' => __('messages.google_unavailable')], 503);
        } catch (\UnexpectedValueException|\DomainException|\InvalidArgumentException $exception) {
            $this->invalid();
        }

        $result = DB::transaction(function () use ($identity) {
            $user = User::where('google_sub', $identity['sub'])->lockForUpdate()->first();
            if (! $user) {
                // Only Google-hosted mailboxes may establish a new account link by email.
                $authoritative = str_ends_with($identity['email'], '@gmail.com')
                    || (is_string($identity['hd'] ?? null) && $identity['hd'] !== '');
                if (! $authoritative) {
                    $this->invalid('google_use_password');
                }
                $matches = User::whereRaw('LOWER(email) = ?', [$identity['email']])->lockForUpdate()->get();
                if ($matches->count() !== 1) {
                    $this->invalid('google_account_missing');
                }
                $user = $matches->first();
                if ($user->google_sub !== null) {
                    $this->invalid();
                }
                $user->google_sub = $identity['sub'];
                $user->email_verified_at ??= now();
                $user->save();
            }
            // A local email change must not let the former Google identity keep access.
            if (mb_strtolower($user->email) !== $identity['email'] || str_ends_with($identity['email'], '@hr-ai.invalid')) {
                $this->invalid('google_use_password');
            }

            return [
                'user' => $user->append(['has_personnel_approvals', 'is_commission_member']),
                'token' => $user->createToken('auth_token')->plainTextToken,
            ];
        });

        return response()->json($result)->header('Cache-Control', 'no-store');
    }

    private function invalid(string $message = 'google_invalid'): never
    {
        throw ValidationException::withMessages(['credential' => [__('messages.'.$message)]]);
    }
}
