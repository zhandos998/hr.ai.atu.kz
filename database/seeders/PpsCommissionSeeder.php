<?php

namespace Database\Seeders;

use App\Models\CommissionMember;
use App\Models\PpsFacultyCommissionMember;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class PpsCommissionSeeder extends Seeder
{
    public function run(): void
    {
        foreach ($this->permanentMembers() as $name => $email) {
            $user = $this->ensureUser($name, $email);

            $this->markCommissionMember($user, isPps: true);
        }

        foreach ($this->staffMembers() as $name => $email) {
            $user = $this->ensureUser($name, $email);

            $this->markCommissionMember($user, isStaff: true);
        }

        foreach ($this->facultyMembers() as $facultyName => $member) {
            $user = $this->ensureUser($member['name'], $member['email']);

            PpsFacultyCommissionMember::query()->updateOrCreate(
                [
                    'faculty_name' => $facultyName,
                    'user_id' => $user->id,
                ],
            );
        }
    }

    private function ensureUser(string $name, string $email): User
    {
        $email = trim($email);
        $user = User::query()->where('email', $email)->first();

        if ($user) {
            $user->forceFill([
                'name' => $name,
                'email_verified_at' => $user->email_verified_at ?: now(),
            ])->save();

            return $user;
        }

        return User::query()->create([
            'name' => $name,
            'email' => $email,
            'phone' => null,
            'email_verified_at' => now(),
            'password' => Hash::make('1234'),
            'role' => 'user',
        ]);
    }

    private function markCommissionMember(User $user, bool $isPps = false, bool $isStaff = false): void
    {
        $member = CommissionMember::query()->firstOrNew([
            'user_id' => $user->id,
        ]);

        $member->is_pps = $isPps || (bool) $member->is_pps;
        $member->is_staff = $isStaff || (bool) $member->is_staff;
        $member->save();
    }

    private function permanentMembers(): array
    {
        return [
            'Сабралиева Марина' => 'm.sabralieva@atu.edu.kz',
            'Талип Шарбану' => 'talipkyzy@atu.edu.kz',
            'Есембаева Балзада' => 'b.esembaeva@atu.edu.kz',
            'Медеубаева Жанар' => 'zh.medeubaeva@atu.edu.kz',
            'Джуринская Индира' => 'd.dzhurinskaya@atu.edu.kz',
            'Кошербаева Ляззат' => 'l.kosherbaeva@atu.edu.kz',
            'Василина Гулзира' => 'vasilina.g@atu.edu.kz',
            'Ахметова Нурсулу' => 'n.ahmetova@atu.edu.kz',
            'Балхыбекова Коркем' => 'k.balkhybekova@atu.edu.kz',
            'Алиев Баходир' => 'b.aliev@atu.edu.kz',
            'Нурахметов Бауржан' => 'b.nurakhmetov@atu.edu.kz',
        ];
    }

    private function staffMembers(): array
    {
        return [
            'Нурахметов Бауржан' => 'b.nurakhmetov@atu.edu.kz',
            'Есембаева Балзада' => 'b.esembaeva@atu.edu.kz',
            'Василина Гулзира' => 'vasilina.g@atu.edu.kz',
            'Медеубаева Жанар' => 'zh.medeubaeva@atu.edu.kz',
            'Ахметова Нурсулу' => 'n.ahmetova@atu.edu.kz',
        ];
    }

    private function facultyMembers(): array
    {
        return [
            'Факультет интеллектуальных и инженерных систем' => [
                'name' => 'Орманбекова Айнур',
                'email' => 'a.ormanbekova@atu.edu.kz',
            ],
            'Факультет информационных технологий' => [
                'name' => 'Маликова Феруза',
                'email' => 'malikova.f@atu.edu.kz',
            ],
            'Факультет пищевых технологий' => [
                'name' => 'Жаксылыкова Гульшат',
                'email' => 'g.zhaksylykova@atu.edu.kz',
            ],
            'Факультет биотехнологии и химических технологий' => [
                'name' => 'Матибаева Айнур',
                'email' => 'a.matibaeva@atu.edu.kz',
            ],
            'Факультет экономики и бизнеса' => [
                'name' => 'Абдраимова Диана',
                'email' => 'd.abdraimova@atu.edu.kz',
            ],
            'Факультет дизайна, технологий текстиля и одежды' => [
                'name' => 'Сарттарова Ляззат',
                'email' => 'l.sarttarova@atu.edu.kz',
            ],
            'Другое' => [
                'name' => 'Пазылхайыр Бауыржан',
                'email' => 'b.pazylkhaiyr@atu.edu.kz',
            ],
        ];
    }
}
