{{-- F-ADM-SET-032: static Sadad troubleshooting guide (Web Checkout 2.1) --}}
@if(app()->getLocale() === 'en')
<div class="space-y-4 text-sm leading-relaxed">
    <div class="rounded-xl bg-danger-50 p-4 dark:bg-danger-500/10">
        <p class="font-bold">❌ Error: "Checksumhash did not match"</p>
        <p class="mt-1">The request data does not match what is registered in the Sadad panel. Common causes:</p>
        <ol class="mt-2 list-inside list-decimal space-y-1">
            <li><b>Test Mode not enabled</b> — Merchant panel → API → Test Mode.</li>
            <li><b>Domain mismatch</b> — must equal the domain registered when the secret was created. Current: <code dir="ltr">{{ $domain ?: 'not set' }}</code> (qcamelmc.org ≠ www.qcamelmc.org).</li>
            <li><b>Wrong secret key</b> — regenerate it from Merchant panel → API → Generate New Test Key.</li>
            <li><b>Web Checkout not activated</b> — contact Sadad support to enable Web Checkout 2.1.</li>
        </ol>
    </div>
    <div class="rounded-xl bg-success-50 p-4 dark:bg-success-500/10">
        <p class="font-bold">✅ Quick checks</p>
        <ol class="mt-2 list-inside list-decimal space-y-1">
            <li>Open the <a class="font-bold text-primary-600 underline" href="https://webpanel.sadad.qa/authentication/login" target="_blank" rel="noopener">Sadad merchant panel</a>.</li>
            <li>Go to "API" in the side menu and confirm "Test Mode" (for sandbox).</li>
            <li>Check that "Sadad ID" = <code dir="ltr">{{ $merchant ?: '???' }}</code>.</li>
            <li>Paste the secret key above without spaces and save.</li>
            <li>Run the readiness check, then review the payment events log on the booking page.</li>
        </ol>
    </div>
</div>
@else
<div class="space-y-4 text-sm leading-relaxed">
    <div class="rounded-xl bg-danger-50 p-4 dark:bg-danger-500/10">
        <p class="font-bold">❌ خطأ: "Checksumhash did not match"</p>
        <p class="mt-1">هذا الخطأ يحدث عندما لا تتطابق بيانات الطلب مع ما هو مسجل في لوحة سداد. الأسباب الشائعة:</p>
        <ol class="mt-2 list-inside list-decimal space-y-1">
            <li><b>وضع الاختبار (Test Mode) غير مفعّل</b> — يجب تفعيله من: لوحة التاجر ← API ← Test Mode.</li>
            <li><b>النطاق (Domain) غير متطابق</b> — يجب أن يطابق ما هو مسجل عند إنشاء المفتاح السري. النطاق الحالي: <code dir="ltr">{{ $domain ?: 'غير محدد' }}</code> — انتبه: الفرق بين "qcamelmc.org" و "www.qcamelmc.org" مهم!</li>
            <li><b>المفتاح السري غير صحيح</b> — جرّب إعادة توليده من: لوحة التاجر ← API ← Generate New Test Key.</li>
            <li><b>Web Checkout غير مفعّل</b> — تواصل مع دعم سداد لتفعيل Web Checkout 2.1.</li>
        </ol>
    </div>
    <div class="rounded-xl bg-success-50 p-4 dark:bg-success-500/10">
        <p class="font-bold">✅ خطوات التحقق السريع</p>
        <ol class="mt-2 list-inside list-decimal space-y-1">
            <li>افتح <a class="font-bold text-primary-600 underline" href="https://webpanel.sadad.qa/authentication/login" target="_blank" rel="noopener">لوحة التاجر سداد</a>.</li>
            <li>اذهب إلى قسم "API" وتأكد أن "Test Mode" مفعّل (للبيئة التجريبية).</li>
            <li>تحقق من أن "Sadad ID" = <code dir="ltr">{{ $merchant ?: '؟؟؟' }}</code>.</li>
            <li>انسخ المفتاح السري والصقه أعلاه (بدون مسافات) ثم احفظ.</li>
            <li>شغّل فحص الجاهزية ثم راجع سجل أحداث الدفع في صفحة الحجز.</li>
        </ol>
    </div>
</div>
@endif
