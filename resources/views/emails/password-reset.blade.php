@extends('emails.layout')

@section('content')
<div class="greeting">
    مرحباً {{ $memberName }}،
</div>

<div class="content">
    <p>تلقينا طلباً لإعادة تعيين كلمة المرور الخاصة بحسابك في <strong>قصة سهلة</strong>.</p>

    <p>للمتابعة، يرجى النقر على الزر أدناه لإعادة تعيين كلمة المرور:</p>
</div>

<div class="button-container">
    <a href="{{ $resetUrl }}" class="button">
        إعادة تعيين كلمة المرور
    </a>
</div>

<div class="info-box">
    <p><strong>⚠️ معلومات مهمة:</strong></p>
    <p>• هذا الرابط صالح لمدة ساعتين فقط</p>
    <p>• إذا لم تطلب إعادة تعيين كلمة المرور، يمكنك تجاهل هذه الرسالة</p>
    <p>• حسابك آمن ولن يتم تغيير أي شيء</p>
</div>

<div class="content">
    <p>إذا واجهت أي مشكلة في النقر على الزر، يمكنك نسخ الرابط التالي ولصقه في المتصفح:</p>
    <p style="direction: ltr; text-align: left; background-color: #f8f9fa; padding: 10px; border-radius: 4px; word-break: break-all; font-size: 12px;">
        {{ $resetUrl }}
    </p>

    <p style="margin-top: 30px;">
        <strong>مع أطيب التمنيات،</strong><br>
        فريق قصة سهلة
    </p>
</div>
@endsection