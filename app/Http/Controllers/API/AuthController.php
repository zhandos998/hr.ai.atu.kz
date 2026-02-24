<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class AuthController extends Controller
{
    public function register(Request $request): JsonResponse
    {
        $fields = $request->validate([
            'name' => 'required|string|max:255',
            'email' => 'required|string|email|unique:users,email',
            'phone' => 'nullable|string|unique:users,phone',
            'password' => 'required|string|min:6|confirmed',
        ]);

        User::create([
            'name' => $fields['name'],
            'email' => $fields['email'],
            'phone' => $fields['phone'] ?? null,
            'password' => Hash::make($fields['password']),
        ]);

        $this->sendEmailVerificationLink($fields['email']);

        return response()->json([
            'message' => 'Регистрация завершена. Подтвердите email по ссылке из письма.',
        ], 201);
    }

    public function login(Request $request): JsonResponse
    {
        $fields = $request->validate([
            'email' => 'required|string|email',
            'password' => 'required|string',
        ]);

        $user = User::where('email', $fields['email'])->first();

        if (!$user || !Hash::check($fields['password'], $user->password)) {
            throw ValidationException::withMessages([
                'email' => ['Неверные учетные данные.'],
            ]);
        }

        // if (is_null($user->email_verified_at)) {
        //     $this->sendEmailVerificationLink($user->email);

        //     throw ValidationException::withMessages([
        //         'email' => ['Email не подтвержден. Мы отправили новое письмо для подтверждения.'],
        //     ]);
        // }

        $token = $user->createToken('auth_token')->plainTextToken;

        return response()->json([
            'user' => $user,
            'token' => $token,
        ]);
    }

    public function resendVerification(Request $request): JsonResponse
    {
        $fields = $request->validate([
            'email' => 'required|email',
        ]);

        $user = User::where('email', $fields['email'])->first();

        if (!$user) {
            return response()->json([
                'message' => 'Если такой email зарегистрирован, письмо отправлено.',
            ]);
        }

        if (!is_null($user->email_verified_at)) {
            return response()->json([
                'message' => 'Email уже подтвержден.',
            ]);
        }

        $this->sendEmailVerificationLink($user->email);

        return response()->json([
            'message' => 'Письмо с подтверждением отправлено.',
        ]);
    }

    public function verifyEmail(Request $request): JsonResponse
    {
        $fields = $request->validate([
            'email' => 'required|email',
            'token' => 'required|string',
        ]);

        $record = DB::table('email_verification_tokens')
            ->where('email', $fields['email'])
            ->first();

        if (!$record || !hash_equals($record->token, hash('sha256', $fields['token']))) {
            throw ValidationException::withMessages([
                'token' => ['Ссылка подтверждения недействительна.'],
            ]);
        }

        $createdAt = Carbon::parse($record->created_at);
        if ($createdAt->lt(now()->subHours(24))) {
            throw ValidationException::withMessages([
                'token' => ['Срок действия ссылки истек. Запросите новое письмо.'],
            ]);
        }

        $user = User::where('email', $fields['email'])->first();

        if (!$user) {
            throw ValidationException::withMessages([
                'email' => ['Пользователь не найден.'],
            ]);
        }

        $user->forceFill([
            'email_verified_at' => now(),
        ])->save();

        DB::table('email_verification_tokens')->where('email', $fields['email'])->delete();

        $token = $user->createToken('auth_token')->plainTextToken;

        return response()->json([
            'message' => 'Email успешно подтвержден.',
            'user' => $user,
            'token' => $token,
        ]);
    }

    public function forgotPassword(Request $request): JsonResponse
    {
        $fields = $request->validate([
            'email' => 'required|email',
        ]);

        $user = User::where('email', $fields['email'])->first();

        if ($user) {
            $plainToken = Str::random(64);

            DB::table('password_reset_tokens')->updateOrInsert(
                ['email' => $user->email],
                [
                    'token' => hash('sha256', $plainToken),
                    'created_at' => now(),
                ]
            );

            $url = $this->frontendUrl('/reset-password', [
                'email' => $user->email,
                'token' => $plainToken,
            ]);

            Mail::raw(
                "Для сброса пароля перейдите по ссылке:\n{$url}\n\nСсылка действует 60 минут.",
                function ($message) use ($user) {
                    $message->to($user->email)->subject('Сброс пароля');
                }
            );
        }

        return response()->json([
            'message' => 'Если такой email зарегистрирован, письмо для сброса отправлено.',
        ]);
    }

    public function resetPassword(Request $request): JsonResponse
    {
        $fields = $request->validate([
            'email' => 'required|email',
            'token' => 'required|string',
            'password' => 'required|string|min:6|confirmed',
        ]);

        $record = DB::table('password_reset_tokens')
            ->where('email', $fields['email'])
            ->first();

        if (!$record || !hash_equals($record->token, hash('sha256', $fields['token']))) {
            throw ValidationException::withMessages([
                'token' => ['Неверный или устаревший токен сброса пароля.'],
            ]);
        }

        $expireMinutes = (int) config('auth.passwords.users.expire', 60);
        if (Carbon::parse($record->created_at)->lt(now()->subMinutes($expireMinutes))) {
            throw ValidationException::withMessages([
                'token' => ['Срок действия токена истек. Запросите новый сброс пароля.'],
            ]);
        }

        $user = User::where('email', $fields['email'])->first();

        if (!$user) {
            throw ValidationException::withMessages([
                'email' => ['Пользователь не найден.'],
            ]);
        }

        $user->forceFill([
            'password' => Hash::make($fields['password']),
        ])->save();

        DB::table('password_reset_tokens')->where('email', $fields['email'])->delete();
        $user->tokens()->delete();

        return response()->json([
            'message' => 'Пароль успешно изменен. Войдите с новым паролем.',
        ]);
    }

    public function changePassword(Request $request): JsonResponse
    {
        $fields = $request->validate([
            'current_password' => 'required|string',
            'password' => 'required|string|min:6|confirmed|different:current_password',
        ]);

        $user = $request->user();

        if (!$user || !Hash::check($fields['current_password'], $user->password)) {
            throw ValidationException::withMessages([
                'current_password' => ['Текущий пароль указан неверно.'],
            ]);
        }

        $user->forceFill([
            'password' => Hash::make($fields['password']),
        ])->save();

        return response()->json([
            'message' => 'Пароль успешно обновлен.',
        ]);
    }

    public function requestEmailChange(Request $request): JsonResponse
    {
        $fields = $request->validate([
            'new_email' => 'required|email|unique:users,email',
            'password' => 'required|string',
        ]);

        $user = $request->user();

        if (!$user || !Hash::check($fields['password'], $user->password)) {
            throw ValidationException::withMessages([
                'password' => ['Текущий пароль указан неверно.'],
            ]);
        }

        $plainToken = Str::random(64);

        DB::table('email_change_requests')->where('user_id', $user->id)->delete();
        DB::table('email_change_requests')->where('new_email', $fields['new_email'])->delete();

        DB::table('email_change_requests')->insert([
            'user_id' => $user->id,
            'new_email' => $fields['new_email'],
            'token' => hash('sha256', $plainToken),
            'created_at' => now(),
        ]);

        $url = $this->frontendUrl('/email-change-confirm', [
            'token' => $plainToken,
        ]);

        Mail::raw(
            "Для подтверждения нового email перейдите по ссылке:\n{$url}\n\nСсылка действует 24 часа.",
            function ($message) use ($fields) {
                $message->to($fields['new_email'])->subject('Подтверждение смены email');
            }
        );

        return response()->json([
            'message' => 'Письмо для подтверждения отправлено на новый email.',
        ]);
    }

    public function confirmEmailChange(Request $request): JsonResponse
    {
        $fields = $request->validate([
            'token' => 'required|string',
        ]);

        $hashedToken = hash('sha256', $fields['token']);

        $record = DB::table('email_change_requests')
            ->where('token', $hashedToken)
            ->first();

        if (!$record) {
            throw ValidationException::withMessages([
                'token' => ['Ссылка подтверждения недействительна.'],
            ]);
        }

        if (Carbon::parse($record->created_at)->lt(now()->subHours(24))) {
            DB::table('email_change_requests')->where('id', $record->id)->delete();

            throw ValidationException::withMessages([
                'token' => ['Срок действия ссылки истек. Запросите смену email заново.'],
            ]);
        }

        $emailUsedByAnother = User::where('email', $record->new_email)
            ->where('id', '!=', $record->user_id)
            ->exists();

        if ($emailUsedByAnother) {
            throw ValidationException::withMessages([
                'token' => ['Этот email уже используется другим пользователем.'],
            ]);
        }

        $user = User::find($record->user_id);

        if (!$user) {
            throw ValidationException::withMessages([
                'token' => ['Пользователь для смены email не найден.'],
            ]);
        }

        $oldEmail = $user->email;

        $user->forceFill([
            'email' => $record->new_email,
            'email_verified_at' => now(),
        ])->save();

        DB::table('email_change_requests')->where('id', $record->id)->delete();
        DB::table('email_verification_tokens')->where('email', $oldEmail)->delete();
        DB::table('email_verification_tokens')->where('email', $user->email)->delete();

        $token = $user->createToken('auth_token')->plainTextToken;

        return response()->json([
            'message' => 'Email успешно изменен.',
            'user' => $user,
            'token' => $token,
        ]);
    }

    public function logout(Request $request): JsonResponse
    {
        $request->user()->currentAccessToken()->delete();

        return response()->json([
            'message' => 'Вы успешно вышли из системы.',
        ]);
    }

    public function user(Request $request): JsonResponse
    {
        return response()->json(
            $request->user()->load([
                'commissionMember',
                'commissionVacancies:id',
            ])
        );
    }

    private function sendEmailVerificationLink(string $email): void
    {
        $plainToken = Str::random(64);

        DB::table('email_verification_tokens')->updateOrInsert(
            ['email' => $email],
            [
                'token' => hash('sha256', $plainToken),
                'created_at' => now(),
            ]
        );

        $url = $this->frontendUrl('/verify-email', [
            'email' => $email,
            'token' => $plainToken,
        ]);

        Mail::raw(
            "Для подтверждения email перейдите по ссылке:\n{$url}\n\nСсылка действует 24 часа.",
            function ($message) use ($email) {
                $message->to($email)->subject('Подтверждение email');
            }
        );
    }

    private function frontendUrl(string $path, array $query = []): string
    {
        $base = rtrim((string) env('FRONTEND_URL', config('app.url')), '/');
        $queryString = http_build_query($query);

        return $base . $path . ($queryString !== '' ? ('?' . $queryString) : '');
    }
}
