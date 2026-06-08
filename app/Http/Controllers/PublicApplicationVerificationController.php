<?php

namespace App\Http\Controllers;

use App\Models\Application;

class PublicApplicationVerificationController extends Controller
{
    public function __invoke(Application $application)
    {
        $application->load([
            'user:id,name',
            'vacancy:id,title,type,position_id',
            'vacancy.position:id,department_id,name',
            'vacancy.position.department:id,name',
            'ppsProfile',
        ]);

        return view('public.application-verification', [
            'application' => $application,
            'profileSections' => $this->profileSections($application),
            'applicationSections' => $this->applicationSections($application),
        ]);
    }

    private function applicationSections(Application $application): array
    {
        $vacancy = $application->vacancy;
        $profile = $application->ppsProfile;

        return [
            [
                'title' => 'Основные данные',
                'fields' => [
                    ['label' => 'ID заявки', 'value' => $application->id],
                    ['label' => 'Тип заявки', 'value' => $vacancy?->type === 'staff' ? 'АУП' : 'ППС'],
                    ['label' => 'Кандидат', 'value' => $application->user?->name],
                    ['label' => 'Вакансия', 'value' => $vacancy?->title],
                    ['label' => 'Департамент / кафедра', 'value' => $profile?->department_name ?: $vacancy?->position?->department?->name],
                    ['label' => 'Факультет', 'value' => $profile?->faculty_name],
                    ['label' => 'Должность', 'value' => $profile?->desired_position ?: $vacancy?->position?->name ?: $vacancy?->title],
                    ['label' => 'Создана', 'value' => optional($application->created_at)->format('d.m.Y H:i')],
                    ['label' => 'Обновлена', 'value' => optional($application->updated_at)->format('d.m.Y H:i')],
                ],
            ],
        ];
    }

    private function profileSections(Application $application): array
    {
        $profile = $application->ppsProfile;

        if (!$profile) {
            return [];
        }

        return [
            [
                'title' => 'Профиль ППС',
                'fields' => [
                    ['label' => 'ФИО', 'value' => $profile->full_name],
                    ['label' => 'Претендуемая должность', 'value' => $profile->desired_position],
                    ['label' => 'Факультет', 'value' => $profile->faculty_name],
                    ['label' => 'Кафедра', 'value' => $profile->department_name],
                    ['label' => 'Год рождения', 'value' => $profile->birth_year],
                    ['label' => 'Базовое образование', 'value' => $profile->basic_education],
                    ['label' => 'Магистратура', 'value' => $profile->magistracy],
                    ['label' => 'Научная степень / докторантура', 'value' => $profile->scientific_degree],
                    ['label' => 'Учёное звание', 'value' => $profile->academic_title],
                    ['label' => 'Стаж работы', 'value' => $profile->work_experience],
                ],
            ],
            [
                'title' => 'Наука и цифровое развитие',
                'fields' => [
                    ['label' => 'Научные труды преподавателя', 'value' => $profile->scientific_works],
                    ['label' => 'Наличие ЦОР / МООК', 'value' => $profile->digital_mooc],
                    ['label' => 'Заключение Департамента науки', 'value' => $profile->science_conclusion],
                ],
            ],
            [
                'title' => 'Стратегическое развитие',
                'fields' => [
                    ['label' => 'Итоговый рейтинговый балл', 'value' => $profile->final_rating_score],
                    ['label' => 'Результаты анкетирования студентов', 'value' => $profile->student_survey_results],
                    ['label' => 'КРК', 'value' => $profile->krk],
                ],
            ],
            [
                'title' => 'Академическое развитие',
                'fields' => [
                    ['label' => 'Качество открытого занятия', 'value' => $profile->open_lesson_quality],
                    ['label' => 'Преподаваемые дисциплины', 'value' => $profile->taught_disciplines],
                    ['label' => 'Учебно-методическая литература', 'value' => $profile->educational_methodical_literature],
                    ['label' => 'Заключение', 'value' => $profile->academic_conclusion],
                ],
            ],
            [
                'title' => 'Библиотека и комплаенс',
                'fields' => [
                    ['label' => 'Показатели учебных публикаций', 'value' => $profile->educational_publication_metrics],
                    ['label' => 'Антикоррупционное анкетирование', 'value' => $profile->anti_corruption_survey_results],
                    ['label' => 'Дисциплинарные взыскания', 'value' => $profile->disciplinary_actions_info],
                ],
            ],
        ];
    }

}
