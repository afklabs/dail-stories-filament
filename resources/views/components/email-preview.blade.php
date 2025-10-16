<div class="bg-gray-50 p-4 rounded-lg border border-gray-200">
    <div class="mb-4">
        <h3 class="text-lg font-bold text-gray-900">{{ $subject }}</h3>
    </div>

    <div class="bg-white p-6 rounded-lg shadow-sm border border-gray-200">
        <div class="prose prose-sm max-w-none" dir="rtl">
            {!! $body !!}
        </div>
    </div>

    <div class="mt-4 text-sm text-gray-500">
        <p>ℹ️ هذه معاينة تقريبية. قد يختلف المظهر الفعلي قليلاً في صندوق البريد الإلكتروني.</p>
    </div>
</div>