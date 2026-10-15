export const reviewConfigs = {
    science: {
        title: 'Научные труды ППС', description: 'Очередь Департамента науки по преподавательским заявкам.', basePath: '/science/applications', apiPath: '/api/science/applications',
        sectionTitle: 'Научные труды преподавателя', fields: [{ key: 'scientific_works', label: 'Научные труды преподавателя' }, { key: 'science_conclusion', label: 'Заключение' }], saveSuffix: 'scientific-works', documentsField: 'scientific_works_documents', deleteSuffix: 'scientific-works-documents', pdfSuffix: 'science-response-pdf', pdfField: 'science_conclusion',
    },
    digital: {
        title: 'ЦОР / МООК ППС', description: 'Очередь Центра искусственного интеллекта и цифрового развития.', basePath: '/digital/applications', apiPath: '/api/digital/applications',
        sectionTitle: 'Наличие ЦОР / МООК', fields: [{ key: 'digital_mooc', label: 'Наличие ЦОР / МООК и подтверждающих документов' }], saveSuffix: 'digital-mooc', documentsField: 'digital_mooc_documents', deleteSuffix: 'digital-mooc-documents',
    },
    strategy: {
        title: 'Стратегическое развитие ППС', description: 'Очередь Департамента стратегического развития.', basePath: '/strategy/applications', apiPath: '/api/strategy/applications',
        sectionTitle: 'Показатели стратегического развития', fields: [{ key: 'final_rating_score', label: 'Итоговый рейтинговый балл' }, { key: 'student_survey_results', label: 'Результаты анкетирования студентов' }, { key: 'krk', label: 'КРК' }], saveSuffix: 'strategy-review',
    },
    academic: {
        title: 'Академическое развитие ППС', description: 'Очередь Департамента академического развития.', basePath: '/academic/applications', apiPath: '/api/academic/applications',
        sectionTitle: 'Показатели академического развития', fields: [{ key: 'open_lesson_quality', label: 'Качество проведения открытого занятия' }, { key: 'taught_disciplines', label: 'Преподаваемые дисциплины' }, { key: 'educational_methodical_literature', label: 'Учебно-методическая литература' }, { key: 'academic_conclusion', label: 'Заключение' }], saveSuffix: 'academic-review', pdfSuffix: 'academic-response-pdf', pdfField: 'academic_conclusion',
    },
    library: {
        title: 'Научная библиотека ППС', description: 'Очередь Научной библиотеки по преподавательским заявкам.', basePath: '/library/applications', apiPath: '/api/library/applications',
        sectionTitle: 'Показатели учебных изданий', fields: [{ key: 'educational_publication_metrics', label: 'Показатели учебных изданий' }], saveSuffix: 'library-metrics',
    },
    professional: {
        title: 'Повышение квалификации ППС', description: 'Очередь по повышению квалификации преподавателей.', basePath: '/professional-development/applications', apiPath: '/api/professional-development/applications',
        sectionTitle: 'Повышение квалификации', fields: [{ key: 'professional_development', label: 'Повышение квалификации' }], saveSuffix: 'professional-development', documentsField: 'professional_development_documents', deleteSuffix: 'professional-development-documents',
    },
    compliance: {
        title: 'Право и комплаенс', description: 'Очередь Департамента правового обеспечения и комплаенса по заявкам ППС и АУП.', basePath: '/compliance/applications', apiPath: '/api/compliance/applications',
        sectionTitle: 'Данные комплаенса', fields: [{ key: 'anti_corruption_survey_results', label: 'Результаты антикоррупционного анкетирования' }, { key: 'disciplinary_actions_info', label: 'Сведения о дисциплинарных взысканиях' }], saveSuffix: 'department-review', compliance: true, pdfSuffix: 'lawyer-response-pdf',
    },
};

export const reviewConfig = (key) => reviewConfigs[key] || reviewConfigs.science;

