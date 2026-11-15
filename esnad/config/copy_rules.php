<?php

// Section 16 of the build brief. The copy check (php artisan esnad:copy-check)
// scans lang/ and database/content/ and fails on any hit.
return [

    'english' => [
        'underscore', 'emphasise', 'emphasize', 'additionally', 'furthermore', 'notably', 'noteworthy', 'crucial', 'pivotal',
        'comprehensive', 'insights', 'nuanced', 'interplay', 'multifaceted', 'enhance', 'garner', 'meticulous', 'commendable',
        'invaluable', 'indispensable', 'ultimately', 'consequently', 'align', 'surpass', 'advancements', 'versatile',
        'strategically', 'significant', 'delve', 'intricate', 'realm', 'showcase', 'tapestry', 'landscape', 'boast',
        'groundbreaking', 'transformative', 'seamless', 'unveil', 'bolster', 'harness', 'leverage', 'pave the way', 'testament',
        'in conclusion', 'in summary', 'it is worth noting', 'it is important to note', 'plays a key role', 'shed light on',
        'deep dive', 'dive into', 'at its core', 'in essence', 'key takeaway', 'bridge the gap', 'cornerstone', 'hallmark',
        'when it comes to', "in today's world", 'in an era of', 'a wide range of', 'a plethora of', 'game-changer', 'cutting-edge',
        'journey', 'robust', 'paradigm', 'synergy', 'navigate', 'holistic', 'foster', 'facilitate', 'unlock', 'innovative',
        'paramount', 'vital', 'ecosystem', 'streamline', 'optimise', 'optimize', 'compelling', 'profound', 'remarkable', 'empower',
        'elevate', 'revolutionise', 'revolutionize', 'tailored', 'bespoke', 'truly', 'impactful', 'effortless', 'hassle-free',
        'state-of-the-art', 'world-class', 'one-stop shop', 'peace of mind', 'look no further', "we've got you covered",
        'trusted partner', 'best-in-class', 'top-notch', 'unparalleled', 'rest assured', 'at your fingertips',
        'in just a few clicks', 'say goodbye to', 'gone are the days', 'guaranteed', 'best lawyer', 'lowest price',
        'welcome to our', 'lorem ipsum', 'your text here',
    ],

    'arabic' => [
        'في عالمنا المتسارع', 'في ظل التطور الرقمي', 'في العصر الرقمي', 'لا شك أن', 'مما لا شك فيه', 'تجدر الإشارة إلى',
        'من الجدير بالذكر', 'علاوة على ذلك', 'فضلًا عن ذلك', 'فضلا عن ذلك', 'في الختام', 'ختامًا', 'ختاما', 'خلاصة القول', 'باختصار',
        'يلعب دورًا محوريًا', 'دورًا حيويًا', 'حجر الزاوية', 'نقلة نوعية', 'ثورة في عالم', 'عصر جديد', 'حلول متكاملة', 'حلول مبتكرة',
        'حلول ذكية', 'تجربة فريدة', 'تجربة سلسة', 'تجربة استثنائية', 'بكل سهولة ويسر', 'بضغطة زر', 'في متناول يدك', 'شريكك الأمثل',
        'شريكك الموثوق', 'وجهتك الأولى', 'نسعى جاهدين', 'على أعلى مستوى', 'بأعلى معايير الجودة', 'خدمات متميزة', 'رحلتك القانونية',
        'راحة البال', 'مما يساهم في', 'مما يعزز', 'الأمر الذي يؤدي إلى', 'منظومة متكاملة', 'غني عن القول', 'ليس هذا فحسب', 'وداعًا لـ',
        'الأفضل في مصر', 'رقم واحد في مصر', 'قم ب', 'يرجى العلم بأن', 'لا تتردد في التواصل', 'مرحبًا بك في موقعنا', 'منصتنا تؤمن',
        'قبل فوات الأوان', 'كارثة', 'آلاف المحامين', 'نتيجة مضمونة', 'مكسب مضمون', 'مضمون 100', 'ليس مجرد', 'مما يضمن لك',
    ],

    // Characters and patterns banned in any language.
    'patterns' => [
        '/—/u' => 'em dash',
        '/–/u' => 'en dash used as a dash',
        '/!/u' => 'exclamation mark',
        '/[\x{1F300}-\x{1FAFF}\x{2600}-\x{27BF}]/u' => 'emoji',
    ],

    // Files where some patterns are allowed (Laravel framework strings, success screens).
    'allow' => [
        'lang/en/validation.php' => ['exclamation mark'],
    ],

];
