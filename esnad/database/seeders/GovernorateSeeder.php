<?php

namespace Database\Seeders;

use App\Models\Governorate;
use Illuminate\Database\Seeder;

/** All 27 governorates, with the code used in the national ID. Cairo, Giza and Alexandria start active. */
class GovernorateSeeder extends Seeder
{
    public const DATA = [
        ['01', 'cairo', 'القاهرة', 'Cairo', true],
        ['21', 'giza', 'الجيزة', 'Giza', true],
        ['02', 'alexandria', 'الإسكندرية', 'Alexandria', true],
        ['14', 'qalyubia', 'القليوبية', 'Qalyubia', false],
        ['03', 'port-said', 'بورسعيد', 'Port Said', false],
        ['04', 'suez', 'السويس', 'Suez', false],
        ['11', 'damietta', 'دمياط', 'Damietta', false],
        ['12', 'dakahlia', 'الدقهلية', 'Dakahlia', false],
        ['13', 'sharqia', 'الشرقية', 'Sharqia', false],
        ['15', 'kafr-el-sheikh', 'كفر الشيخ', 'Kafr El Sheikh', false],
        ['16', 'gharbia', 'الغربية', 'Gharbia', false],
        ['17', 'monufia', 'المنوفية', 'Monufia', false],
        ['18', 'beheira', 'البحيرة', 'Beheira', false],
        ['19', 'ismailia', 'الإسماعيلية', 'Ismailia', false],
        ['22', 'beni-suef', 'بني سويف', 'Beni Suef', false],
        ['23', 'faiyum', 'الفيوم', 'Faiyum', false],
        ['24', 'minya', 'المنيا', 'Minya', false],
        ['25', 'asyut', 'أسيوط', 'Asyut', false],
        ['26', 'sohag', 'سوهاج', 'Sohag', false],
        ['27', 'qena', 'قنا', 'Qena', false],
        ['28', 'aswan', 'أسوان', 'Aswan', false],
        ['29', 'luxor', 'الأقصر', 'Luxor', false],
        ['31', 'red-sea', 'البحر الأحمر', 'Red Sea', false],
        ['32', 'new-valley', 'الوادي الجديد', 'New Valley', false],
        ['33', 'matrouh', 'مطروح', 'Matrouh', false],
        ['34', 'north-sinai', 'شمال سيناء', 'North Sinai', false],
        ['35', 'south-sinai', 'جنوب سيناء', 'South Sinai', false],
    ];

    public function run(): void
    {
        foreach (self::DATA as $i => [$code, $slug, $ar, $en, $active]) {
            Governorate::updateOrCreate(['id_code' => $code], [
                'slug' => $slug, 'name_ar' => $ar, 'name_en' => $en, 'sort' => $i + 1,
            ] + (Governorate::where('id_code', $code)->exists() ? [] : ['is_active' => $active]));
        }
    }
}
