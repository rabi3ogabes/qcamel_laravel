<?php

/*
| One shared country list (F-POS-014 rebuild, R-089, F-PUB-043/044).
| key = ISO 3166-1 alpha-2; 'ar' = Arabic name (stored nationality value,
| verbatim from the spec); 'code' = dialling code.
*/

return [
    'all' => [
        'SA' => ['ar' => 'السعودية', 'en' => 'Saudi Arabia', 'flag' => '🇸🇦', 'code' => '+966'],
        'AE' => ['ar' => 'الإمارات', 'en' => 'UAE', 'flag' => '🇦🇪', 'code' => '+971'],
        'QA' => ['ar' => 'قطر', 'en' => 'Qatar', 'flag' => '🇶🇦', 'code' => '+974'],
        'KW' => ['ar' => 'الكويت', 'en' => 'Kuwait', 'flag' => '🇰🇼', 'code' => '+965'],
        'BH' => ['ar' => 'البحرين', 'en' => 'Bahrain', 'flag' => '🇧🇭', 'code' => '+973'],
        'OM' => ['ar' => 'عمان', 'en' => 'Oman', 'flag' => '🇴🇲', 'code' => '+968'],
        'EG' => ['ar' => 'مصر', 'en' => 'Egypt', 'flag' => '🇪🇬', 'code' => '+20'],
        'JO' => ['ar' => 'الأردن', 'en' => 'Jordan', 'flag' => '🇯🇴', 'code' => '+962'],
        'LB' => ['ar' => 'لبنان', 'en' => 'Lebanon', 'flag' => '🇱🇧', 'code' => '+961'],
        'IQ' => ['ar' => 'العراق', 'en' => 'Iraq', 'flag' => '🇮🇶', 'code' => '+964'],
        'SY' => ['ar' => 'سوريا', 'en' => 'Syria', 'flag' => '🇸🇾', 'code' => '+963'],
        'YE' => ['ar' => 'اليمن', 'en' => 'Yemen', 'flag' => '🇾🇪', 'code' => '+967'],
        'LY' => ['ar' => 'ليبيا', 'en' => 'Libya', 'flag' => '🇱🇾', 'code' => '+218'],
        'SD' => ['ar' => 'السودان', 'en' => 'Sudan', 'flag' => '🇸🇩', 'code' => '+249'],
        'DZ' => ['ar' => 'الجزائر', 'en' => 'Algeria', 'flag' => '🇩🇿', 'code' => '+213'],
        'MA' => ['ar' => 'المغرب', 'en' => 'Morocco', 'flag' => '🇲🇦', 'code' => '+212'],
        'TN' => ['ar' => 'تونس', 'en' => 'Tunisia', 'flag' => '🇹🇳', 'code' => '+216'],
        'MR' => ['ar' => 'موريتانيا', 'en' => 'Mauritania', 'flag' => '🇲🇷', 'code' => '+222'],
        'SO' => ['ar' => 'الصومال', 'en' => 'Somalia', 'flag' => '🇸🇴', 'code' => '+252'],
        'DJ' => ['ar' => 'جيبوتي', 'en' => 'Djibouti', 'flag' => '🇩🇯', 'code' => '+253'],
        'PS' => ['ar' => 'فلسطين', 'en' => 'Palestine', 'flag' => '🇵🇸', 'code' => '+970'],
        'PK' => ['ar' => 'باكستان', 'en' => 'Pakistan', 'flag' => '🇵🇰', 'code' => '+92'],
        'IN' => ['ar' => 'الهند', 'en' => 'India', 'flag' => '🇮🇳', 'code' => '+91'],
        'BD' => ['ar' => 'بنغلاديش', 'en' => 'Bangladesh', 'flag' => '🇧🇩', 'code' => '+880'],
        'PH' => ['ar' => 'الفلبين', 'en' => 'Philippines', 'flag' => '🇵🇭', 'code' => '+63'],
        'ID' => ['ar' => 'إندونيسيا', 'en' => 'Indonesia', 'flag' => '🇮🇩', 'code' => '+62'],
        'NP' => ['ar' => 'نيبال', 'en' => 'Nepal', 'flag' => '🇳🇵', 'code' => '+977'],
        'US' => ['ar' => 'أمريكا', 'en' => 'USA', 'flag' => '🇺🇸', 'code' => '+1'],
        'GB' => ['ar' => 'بريطانيا', 'en' => 'United Kingdom', 'flag' => '🇬🇧', 'code' => '+44'],
        'FR' => ['ar' => 'فرنسا', 'en' => 'France', 'flag' => '🇫🇷', 'code' => '+33'],
        'DE' => ['ar' => 'ألمانيا', 'en' => 'Germany', 'flag' => '🇩🇪', 'code' => '+49'],
        'IT' => ['ar' => 'إيطاليا', 'en' => 'Italy', 'flag' => '🇮🇹', 'code' => '+39'],
        'ES' => ['ar' => 'أسبانيا', 'en' => 'Spain', 'flag' => '🇪🇸', 'code' => '+34'],
    ],

    // R-089 / F-PUB-043: public checkout nationalities, exact order.
    'checkout' => ['SA', 'AE', 'QA', 'KW', 'BH', 'OM', 'EG', 'JO', 'LB', 'IQ', 'SY', 'YE', 'LY', 'SD', 'DZ', 'MA', 'TN', 'MR', 'SO', 'DJ', 'PS'],

    // F-POS-014: 30 dialling codes, exact order.
    'pos_codes' => ['QA', 'SA', 'AE', 'KW', 'BH', 'OM', 'EG', 'JO', 'LB', 'SY', 'IQ', 'YE', 'MA', 'DZ', 'TN', 'LY', 'SD', 'PS', 'PK', 'IN', 'BD', 'PH', 'ID', 'NP', 'US', 'GB', 'FR', 'DE', 'IT', 'ES'],

    // F-POS-015: Gulf 6 then 24 more.
    'gulf' => ['QA', 'SA', 'AE', 'KW', 'BH', 'OM'],
    'pos_more' => ['EG', 'JO', 'LB', 'SY', 'IQ', 'YE', 'MA', 'DZ', 'TN', 'LY', 'SD', 'PS', 'PK', 'IN', 'BD', 'PH', 'ID', 'NP', 'US', 'GB', 'FR', 'DE', 'IT', 'ES'],
];
