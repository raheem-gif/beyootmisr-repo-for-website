<?php

// Arabic validation messages for the rules the site uses. Missing rules
// fall back to English (APP_FALLBACK_LOCALE).
return [

    'accepted' => 'لازم توافق على :attribute.',
    'array' => ':attribute لازم يكون قايمة.',
    'boolean' => 'قيمة :attribute مش صحيحة.',
    'confirmed' => 'تأكيد :attribute مش مطابق.',
    'date' => ':attribute مش تاريخ صحيح.',
    'digits' => ':attribute لازم يكون :digits رقم.',
    'digits_between' => ':attribute لازم يكون من :min إلى :max رقم.',
    'email' => 'اكتب :attribute صحيح.',
    'exists' => 'القيمة المختارة في :attribute مش موجودة.',
    'file' => ':attribute لازم يكون ملف.',
    'image' => ':attribute لازم يكون صورة.',
    'in' => 'القيمة المختارة في :attribute مش صحيحة.',
    'integer' => ':attribute لازم يكون رقم صحيح.',
    'max' => [
        'numeric' => ':attribute لازم ميزيدش عن :max.',
        'file' => 'حجم :attribute لازم ميزيدش عن :max كيلوبايت.',
        'string' => ':attribute لازم ميزيدش عن :max حرف.',
        'array' => ':attribute لازم ميزيدش عن :max عناصر.',
    ],
    'mimes' => ':attribute لازم يكون من نوع :values.',
    'min' => [
        'numeric' => ':attribute لازم يكون :min على الأقل.',
        'file' => 'حجم :attribute لازم يكون :min كيلوبايت على الأقل.',
        'string' => ':attribute لازم يكون :min حروف على الأقل.',
        'array' => ':attribute لازم يكون فيه :min عناصر على الأقل.',
    ],
    'numeric' => ':attribute لازم يكون رقم.',
    'regex' => 'صيغة :attribute مش صحيحة.',
    'required' => ':attribute مطلوب.',
    'required_if' => ':attribute مطلوب في الحالة دي.',
    'size' => [
        'numeric' => ':attribute لازم يكون :size.',
        'string' => ':attribute لازم يكون :size حرف.',
    ],
    'string' => ':attribute لازم يكون نص.',
    'unique' => ':attribute مستخدم قبل كده.',
    'url' => 'صيغة :attribute مش صحيحة.',

    'custom' => [],

    'attributes' => [
        'phone' => 'رقم الموبايل',
        'code' => 'كود الدخول',
        'name_ar' => 'الاسم',
        'name' => 'الاسم',
        'address_form' => 'طريقة المخاطبة',
        'governorate_id' => 'المحافظة',
        'email' => 'البريد الإلكتروني',
        'national_id' => 'الرقم القومي',
    ],

];
