<!DOCTYPE html>
<html dir="rtl" lang="ar">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>تأكيد البريد الإلكتروني</title>
    <style>
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background-color: #f4f4f4;
            margin: 0;
            padding: 20px;
        }

        .container {
            max-width: 600px;
            margin: 0 auto;
            background-color: #ffffff;
            border-radius: 10px;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.1);
            overflow: hidden;
        }

        .header {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 30px;
            text-align: center;
        }

        .content {
            padding: 40px 30px;
        }

        .button {
            display: inline-block;
            padding: 15px 40px;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            text-decoration: none;
            border-radius: 50px;
            font-weight: bold;
            margin: 20px 0;
            transition: transform 0.2s;
        }

        .button:hover {
            transform: scale(1.05);
        }

        .footer {
            background-color: #f8f9fa;
            padding: 20px;
            text-align: center;
            font-size: 12px;
            color: #666;
        }

        .warning {
            background-color: #fff3cd;
            border-right: 4px solid #ffc107;
            padding: 15px;
            margin: 20px 0;
            border-radius: 5px;
        }
    </style>
</head>

<body>
    <div class="container">
        <div class="header">
            <h1>مرحباً {{ $member->name }}!</h1>
            <p>أهلاً بك في قصصي</p>
        </div>

        <div class="content">
            <h2>تأكيد البريد الإلكتروني</h2>

            <p>شكراً لتسجيلك معنا! يرجى تأكيد بريدك الإلكتروني بالنقر على الزر أدناه:</p>

            <div style="text-align: center;">
                <a href="{{ $verificationUrl }}" class="button">
                    تأكيد البريد الإلكتروني
                </a>
            </div>

            <div class="warning">
                <strong>⚠️ تنبيه:</strong> هذا الرابط صالح لمدة {{ $expiresIn }} ساعة فقط.
            </div>

            <p>إذا لم تقم بإنشاء حساب معنا، يمكنك تجاهل هذه الرسالة بأمان.</p>

            <hr style="border: none; border-top: 1px solid #eee; margin: 30px 0;">

            <p style="color: #666; font-size: 12px;">
                إذا واجهتك مشكلة في النقر على الزر، انسخ الرابط التالي والصقه في متصفحك:
            </p>
            <p style="word-break: break-all; color: #667eea; font-size: 11px;">
                {{ $verificationUrl }}
            </p>
        </div>

        <div class="footer">
            <p>© {{ date('Y') }} قصصي. جميع الحقوق محفوظة.</p>
            <p>معرف التتبع: {{ $trackingId }}</p>
        </div>
    </div>
</body>

</html>