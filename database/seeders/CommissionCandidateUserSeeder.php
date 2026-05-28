<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class CommissionCandidateUserSeeder extends Seeder
{
    public function run(): void
    {
        foreach ($this->users() as $name => $email) {
            $this->ensureUser($name, $email);
        }
    }

    private function ensureUser(string $name, string $email): void
    {
        $email = trim($email);
        $user = User::query()->firstOrNew([
            'email' => $email,
        ]);

        $user->forceFill([
            'name' => $name,
            'email_verified_at' => $user->email_verified_at ?: now(),
            'password' => Hash::make('1234'),
        ]);

        if (!$user->exists) {
            $user->phone = null;
            $user->role = 'user';
        }

        $user->save();
    }

    private function users(): array
    {
        return [
            'Изтилеуов Максат' => 'm.iztileuov@atu.edu.kz',
            'Ердинбеков Болат' => 'b.erdinbekov@atu.edu.kz',
            'Сарттарова Ляззат' => 'l.sarttarova@atu.edu.kz',
            'Абдраимова Диана' => 'd.abdraimova@atu.edu.kz',
            'Шиндаулетова Айгуль' => 'a.shindauletova@atu.edu.kz',
            'Серикқызы Мира' => 'competence.center@atu.edu.kz',
            'Талип Шарбану' => 'talipkyzy@atu.edu.kz',
            'Балхыбекова Коркем' => 'k.balkhybekova@atu.edu.kz',
            'Вишневская Юлия' => 'j.vishnevskaya@atu.edu.kz',
            'Садыков Мурат' => 'm.sadykov@atu.edu.kz',
            'Толекова Бакыт' => 'b.tolekova@atu.edu.kz',
            'Кошербаева Ляззат' => 'l.kosherbaeva@atu.edu.kz',
            'Тусупова Жибек' => 'rio@atu.edu.kz',
            'Абдикаева Айгуль' => 'a.abdikaeva@atu.edu.kz',
            'Елшибаева Калима' => 'k.elshibaeva@atu.edu.kz',
            'Джапабаева Гульжан' => 'g.dzhapabaeva@atu.edu.kz',
            'Алиярова Мадина' => 'm.aliyarova@atu.edu.kz',
            'Алиев Баходир' => 'b.aliev@atu.edu.kz',
            'Раимбаева Нагима' => 'n.raimbaeva@atu.edu.kz',
            'Жаксылыкова Гульшат' => 'g.zhaksylykova@atu.edu.kz',
            'Орманбекова Айнур' => 'a.ormanbekova@atu.edu.kz',
            'Батхолдин Калтай' => 'k.batholdin@atu.edu.kz',
            'Пазылхайыр Бауыржан' => 'b.pazylkhaiyr@atu.edu.kz',
            'Байболова Ляззат' => 'l.baybolova@atu.edu.kz',
            'Сарсекова Ляззат' => 'l.sarsekova@atu.edu.kz',
            'Мухтарханова Рауан' => 'r.mukhtarhanova@atu.edu.kz',
        ];
    }
}
