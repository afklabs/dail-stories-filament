<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('submission_settings', function (Blueprint $table) {
            $table->id();
            $table->string('setting_key', 50)->unique();
            $table->longText('setting_value');
            $table->enum('setting_type', ['guide', 'terms'])->index();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        // Insert default values
        DB::table('submission_settings')->insert([
            [
                'setting_key' => 'submission_guide',
                'setting_value' => 'مرحباً بك في صفحة إرسال القصص!

نحن سعداء باستقبال قصتك. يرجى اتباع الإرشادات التالية:

- اكتب قصة أصلية من تأليفك
- يجب أن تكون القصة ملائمة لجميع الأعمار
- اختر التصنيف المناسب لقصتك
- تأكد من مراجعة قصتك قبل الإرسال

ملاحظة: سيتم مراجعة جميع القصص قبل النشر.',
                'setting_type' => 'guide',
                'is_active' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'setting_key' => 'submission_terms',
                'setting_value' => 'الشروط والأحكام:

1. أقر بأن هذه القصة من تأليفي الخاص
2. أمنح التطبيق الحق في نشر وتعديل القصة
3. أوافق على أن القصة قد يتم تحريرها أو إعادة صياغتها
4. لن يتم نشر أي محتوى مسيء أو غير لائق
5. القرار النهائي للنشر يعود لإدارة التطبيق

بالموافقة على هذه الشروط، فإنك توافق على جميع ما ورد أعلاه.',
                'setting_type' => 'terms',
                'is_active' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ],
        ]);
    }

    public function down(): void
    {
        Schema::dropIfExists('submission_settings');
    }
};
