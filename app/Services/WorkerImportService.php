<?php

namespace App\Services;

use App\Models\Position;
use App\Models\User;
use App\Models\WorkerImportBatch;
use App\Support\Username;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class WorkerImportService
{
    public function preview(UploadedFile $file, User $actor): array
    {
        $source = app(WorkerSpreadsheetReader::class)->read($file->getRealPath());
        $users = User::query()->where(fn ($query) => $query->whereNull('role')->orWhere('role', '!=', 'admin'))
            ->orderBy('name')->get(['id', 'name', 'email', 'username', 'role', 'contact_info', 'email_verified_at', 'updated_at']);
        $options = $users->map(fn (User $user) => $this->userOption($user))->values()->all();
        $versions = $users->mapWithKeys(fn (User $user) => [$user->id => $this->userVersion($user)])->all();
        $rows = [];
        $publicRows = [];
        $seen = [];
        foreach ($source as $row) {
            $row['username'] = Username::normalize($row['username']);
            $errors = $this->rowErrors($row);
            $warnings = [];
            if (isset($seen[$row['username']])) {
                $warnings[] = 'Идентификатор повторяется в файле.';
            }
            $seen[$row['username']] = true;
            $matches = $this->matchUsers($row, $users);
            $automatic = $matches['exact']->count() === 1 ? $matches['exact']->first() : null;
            $owner = $users->first(fn ($user) => $user->username === $row['username'] || $user->email === $this->technicalEmail($row['username']));
            if ($owner && (! $automatic || $owner->id !== $automatic->id)) {
                $warnings[] = 'Идентификатор уже связан с другим пользователем.';
                $automatic = null;
            }
            if (preg_match('/^user\d+$/i', $row['name'])) {
                $warnings[] = 'Уточните ФИО.';
            }
            $password = $row['password'];
            $validPassword = mb_strlen($password) >= 4 && mb_strlen($password) <= 64;
            unset($row['password']);
            $row['password_hash'] = $validPassword ? Hash::make($password) : null;
            $row['has_usable_password'] = $validPassword;
            $rows[] = $row;
            $action = 'skip';
            if ($errors === [] && $warnings === []) {
                if ($automatic) {
                    $action = 'existing';
                } elseif ($matches['suggestions']->isEmpty() && $validPassword) {
                    $action = 'create';
                }
            }
            if (! $validPassword) {
                $warnings[] = 'Пароль из файла короче 4 или длиннее 64 символов. Создание аккаунта недоступно.';
            }
            if (! $automatic && $matches['suggestions']->isNotEmpty()) {
                $warnings[] = 'Выберите существующего пользователя вручную.';
            }
            $publicRows[] = [
                ...array_diff_key($row, ['password_hash' => true]),
                'action' => $action,
                'user_id' => $automatic?->id,
                'position_id' => null,
                'suggestions' => $matches['suggestions']->map(fn ($user) => $user->id)->values()->all(),
                'errors' => $errors,
                'warnings' => array_values(array_unique($warnings)),
            ];
        }

        WorkerImportBatch::query()->whereNull('completed_at')->where('expires_at', '<', now())->update(['payload' => null]);
        $batch = WorkerImportBatch::create([
            'created_by' => $actor->id,
            'filename' => Str::limit(basename($file->getClientOriginalName()), 250, ''),
            'file_sha256' => hash_file('sha256', $file->getRealPath()),
            'payload' => ['rows' => $rows, 'versions' => $versions],
            'expires_at' => now()->addMinutes(30),
        ]);

        return ['batch_id' => $batch->id, 'filename' => $batch->filename, 'expires_at' => $batch->expires_at, 'rows' => $publicRows, 'users' => $options];
    }

    public function apply(string $batchId, array $selections, User $actor): array
    {
        return DB::transaction(function () use ($batchId, $selections, $actor) {
            $batch = WorkerImportBatch::query()->where('created_by', $actor->id)->lockForUpdate()->findOrFail($batchId);
            if ($batch->completed_at) {
                return $batch->result;
            }
            if ($batch->expires_at->isPast() || ! $batch->payload) {
                $this->fail('Предпросмотр истёк. Загрузите файл повторно.');
            }
            $payload = $batch->payload;
            $rows = collect($payload['rows'])->keyBy('row_number');
            $plan = [];
            $usedUsers = [];
            $usedLogins = [];

            // Validate the complete plan before writing any user or assignment.
            foreach ($selections as $selection) {
                $number = $selection['row_number'];
                $row = $rows->get($number);
                if (! $row) {
                    $this->fail('В предпросмотре нет выбранной строки.');
                }
                if ($selection['action'] === 'skip') {
                    continue;
                }
                if ($errors = $this->rowErrors($row)) {
                    $this->rowFail($number, implode(' ', $errors));
                }
                if (isset($usedLogins[$row['username']])) {
                    $this->rowFail($number, 'Идентификаторы выбранных строк не должны повторяться.');
                }
                $usedLogins[$row['username']] = true;
                $user = null;
                if ($selection['action'] === 'existing') {
                    $id = $selection['user_id'] ?? null;
                    if (! $id || ! isset($payload['versions'][$id])) {
                        $this->rowFail($number, 'Выберите пользователя из предпросмотра.');
                    }
                    $user = User::query()->lockForUpdate()->findOrFail($id);
                    if ($user->isAdmin() || $this->userVersion($user) !== $payload['versions'][$id]) {
                        $this->rowFail($number, 'Данные пользователя изменились. Загрузите файл повторно.');
                    }
                    if (isset($usedUsers[$id])) {
                        $this->rowFail($number, 'Один пользователь выбран в нескольких строках.');
                    }
                    $usedUsers[$id] = true;
                } elseif (! $row['password_hash']) {
                    $this->rowFail($number, 'В файле нет подходящего пароля для нового пользователя.');
                }
                if (User::query()->where('username', $row['username'])->when($user, fn ($query) => $query->whereKeyNot($user->id))->exists()) {
                    $this->rowFail($number, 'Идентификатор уже связан с другим пользователем.');
                }
                if (! $user && User::query()->where('email', $this->technicalEmail($row['username']))->exists()) {
                    $this->rowFail($number, 'Такой импортированный аккаунт уже существует. Загрузите файл повторно.');
                }
                $positionId = $selection['position_id'] ?? null;
                if ($positionId && ! Position::query()->whereKey($positionId)->exists()) {
                    $this->rowFail($number, 'Должность больше не существует.');
                }
                $plan[] = ['row' => $row, 'user' => $user, 'position_id' => $positionId];
            }
            if ($plan === []) {
                $this->fail('Выберите хотя бы одну строку для импорта.');
            }

            $result = ['created' => [], 'updated' => [], 'positions_assigned' => 0, 'skipped' => $rows->count() - count($plan)];
            foreach ($plan as $item) {
                $row = $item['row'];
                $user = $item['user'];
                if ($user) {
                    if (trim((string) $user->contact_info) === '' && $row['details'] !== '') {
                        $user->contact_info = $row['details'];
                    }
                    $user->save();
                    $result['updated'][] = $user->id;
                } else {
                    $user = User::create([
                        'name' => $row['name'], 'email' => $this->technicalEmail($row['username']),
                        'password' => $row['password_hash'], 'contact_info' => $row['details'] ?: null, 'role' => 'user',
                    ]);
                    $user->forceFill(['email_verified_at' => now()])->save();
                    $result['created'][] = $user->id;
                }
                if ($item['position_id']) {
                    $assignment = $user->positions()->syncWithoutDetaching([(int) $item['position_id']]);
                    $result['positions_assigned'] += count($assignment['attached']);
                }
            }
            $batch->update(['result' => $result, 'completed_at' => now(), 'payload' => null]);

            return $result;
        }, 3);
    }

    private function rowErrors(array $row): array
    {
        return Validator::make($row, [
            'name' => ['required', 'string', 'max:255'],
            'username' => ['required', ...array_slice(Username::rules(), 0, -1)],
            'details' => ['nullable', 'string', 'max:1000'],
        ], [], ['username' => __('Идентификатор из файла')])->errors()->all();
    }

    private function matchUsers(array $row, $users): array
    {
        $name = $this->nameKey($row['name']);
        $exact = $users->filter(fn ($user) => $this->nameKey($user->name) === $name)->values();
        $tokens = explode(' ', $name);
        $suggestions = $users->filter(function ($user) use ($name, $tokens, $row) {
            if ($user->username === $row['username'] || $user->email === $this->technicalEmail($row['username']) || $this->nameKey($user->name) === $name) {
                return true;
            }
            $other = explode(' ', $this->nameKey($user->name));

            return count($tokens) >= 2 && in_array($tokens[0], $other, true) && in_array($tokens[1], $other, true);
        })->values();
        if ($suggestions->isEmpty() && count($tokens) >= 2) {
            $suggestions = $users->filter(function ($user) use ($tokens) {
                $other = explode(' ', $this->nameKey($user->name));
                if (count($other) < 2) {
                    return false;
                }

                return levenshtein(Str::ascii($tokens[0]), Str::ascii($other[0])) <= 2
                    && levenshtein(Str::ascii($tokens[1]), Str::ascii($other[1])) <= 2;
            })->values();
        }

        return ['exact' => $exact, 'suggestions' => $suggestions];
    }

    private function nameKey(string $value): string
    {
        return Username::normalize(preg_replace('/\s+/u', ' ', $value));
    }

    private function userVersion(User $user): string
    {
        return hash_hmac('sha256', json_encode($user->only(['id', 'name', 'email', 'username', 'role', 'contact_info', 'email_verified_at', 'updated_at']), JSON_THROW_ON_ERROR), config('app.key'));
    }

    private function userOption(User $user): array
    {
        return ['id' => $user->id, 'name' => $user->name, 'email' => $user->email,
            'technical_email' => str_ends_with(mb_strtolower((string) $user->email), '@hr-ai.invalid')];
    }

    private function technicalEmail(string $username): string
    {
        return 'legacy-worker-'.substr(hash('sha256', $username), 0, 32).'@hr-ai.invalid';
    }

    private function rowFail(int $number, string $message): never
    {
        throw ValidationException::withMessages(['rows' => [__('Строка').' '.$number.': '.$message]]);
    }

    private function fail(string $message): never
    {
        throw ValidationException::withMessages(['rows' => [$message]]);
    }
}
