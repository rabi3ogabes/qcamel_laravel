<?php

return [
    // Validation (F-ORD-003, all errors at once)
    'v' => [
        'body_object' => 'يجب أن يكون الطلب كائن JSON.',
        'customer_required' => 'بيانات العميل مطلوبة',
        'customer' => 'العميل',
        'holder' => 'التذكرة #:n',
        'holder_invalid' => 'بيانات حامل التذكرة #:n غير صالحة',
        'required' => ':label مطلوب',
        'too_long' => ':label طويل جداً',
        'invalid_chars' => ':label يحتوي على أحرف غير صالحة',
        'name_of' => 'اسم :label',
        'name_short' => 'اكتب الاسم الكامل الحقيقي (:label)',
        'phone_of' => 'رقم هاتف :label',
        'phone_invalid' => 'رقم هاتف :label غير صالح',
        'phone_country_invalid' => 'رقم هاتف :label لا يطابق صيغة الدولة المختارة',
        'nationality_of' => 'جنسية :label',
        'id_invalid' => 'رقم هوية :label غير صالح',
        'email' => 'البريد الإلكتروني',
        'email_invalid' => 'البريد الإلكتروني غير صالح',
        'customer_cc_invalid' => 'رمز دولة العميل غير صالح',
        'holder_cc_invalid' => 'رمز دولة حامل التذكرة #:n غير صالح',
        'items_required' => 'يجب اختيار تذكرة واحدة على الأقل',
        'too_many_items' => 'أنواع تذاكر كثيرة في طلب واحد',
        'ticket_id_invalid' => 'معرّف التذكرة غير صالح',
        'duplicate_ticket' => 'نوع التذكرة مكرر في الطلب',
        'quantity_invalid' => 'يجب أن تكون الكمية رقماً صحيحاً بين 1 و 20',
        'too_many_tickets' => 'يمكن أن يحتوي الطلب على 30 تذكرة كحد أقصى',
        'holders_count' => 'كل تذكرة تحتاج حاملاً واحداً بالضبط',
        'holders_mismatch' => 'حاملو التذاكر لا يطابقون التذاكر المختارة',
        'method_invalid' => 'طريقة الدفع غير مدعومة',
        'source_invalid' => 'مصدر الطلب غير مدعوم',
        'cashier_invalid' => 'معرّف الكاشير غير صالح',
        'cashier_required' => 'يرجى اختيار الكاشير قبل إتمام البيع',
        'pos_cash_only' => 'طلبات نقطة البيع نقدية فقط',
    ],

    // Availability & limits (R-023, R-054)
    'ticket_not_found' => 'نوع التذكرة غير موجود لهذه الفعالية',
    'sold_out_now' => 'عذراً، نفدت التذاكر من هذا النوع حالياً',
    'not_enough' => 'عذراً، لا تتوفر تذاكر كافية. المتاح: :n تذكرة',
    'limit_reached' => ':name بلغ الحد الأقصى (:cap تذاكر) لهذه الفعالية',
    'limit_partial' => ':name لديه :existing تذكرة — يمكنه إضافة :remaining فقط (طلب :requested)',
    'limit_default' => 'الحد الأقصى هو 5 تذاكر لكل شخص',
    'availability_failed' => 'فشل في التحقق من توفر التذاكر',

    // Admin actions
    'manual_reference_required' => 'لطلبات سداد يجب إدخال رقم عملية سداد أو سبب التأكيد اليدوي',
    'move_same_event' => 'الحجز مرتبط بهذه الفعالية بالفعل',
    'move_invalid_target' => 'الفعالية الجديدة غير موجودة أو مؤرشفة',
];
