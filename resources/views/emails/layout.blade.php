<!DOCTYPE html>
<html lang="ar" dir="rtl">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{{ $subject ?? 'قصة سهلة' }}</title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background-color: #f4f4f4;
            direction: rtl;
            text-align: right;
        }

        .email-wrapper {
            max-width: 600px;
            margin: 40px auto;
            background-color: #ffffff;
            border-radius: 12px;
            overflow: hidden;
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
        }

        .email-header {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            padding: 40px 30px;
            text-align: center;
        }

        .logo-placeholder {
            width: 120px;
            height: 120px;
            margin: 0 auto 20px;
            background-color: rgba(255, 255, 255, 0.2);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            border: 3px solid rgba(255, 255, 255, 0.3);
        }

        .logo-text {
            color: white;
            font-size: 14px;
            font-weight: bold;
        }

        .email-header h1 {
            color: #ffffff;
            font-size: 28px;
            margin-bottom: 10px;
        }

        .email-header p {
            color: rgba(255, 255, 255, 0.9);
            font-size: 16px;
        }

        .email-body {
            padding: 40px 30px;
        }

        .greeting {
            font-size: 20px;
            color: #333333;
            margin-bottom: 20px;
            font-weight: 600;
        }

        .content {
            font-size: 16px;
            color: #666666;
            line-height: 1.8;
            margin-bottom: 30px;
        }

        .button-container {
            text-align: center;
            margin: 30px 0;
        }

        .button {
            display: inline-block;
            padding: 15px 40px;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: #ffffff;
            text-decoration: none;
            border-radius: 8px;
            font-weight: bold;
            font-size: 16px;
            transition: transform 0.2s;
        }

        .button:hover {
            transform: translateY(-2px);
        }

        .info-box {
            background-color: #f8f9fa;
            border-right: 4px solid #667eea;
            padding: 20px;
            margin: 20px 0;
            border-radius: 8px;
        }

        .info-box p {
            margin: 5px 0;
            color: #555555;
        }

        .email-footer {
            background-color: #f8f9fa;
            padding: 30px;
            text-align: center;
            border-top: 1px solid #e0e0e0;
        }

        .email-footer p {
            color: #999999;
            font-size: 14px;
            margin: 5px 0;
        }

        .social-links {
            margin: 20px 0;
        }

        .social-links a {
            display: inline-block;
            margin: 0 10px;
            color: #667eea;
            text-decoration: none;
        }

        .divider {
            height: 1px;
            background-color: #e0e0e0;
            margin: 20px 0;
        }

        /* Tracking pixel */
        .tracking-pixel {
            width: 1px;
            height: 1px;
            opacity: 0;
        }

        @media only screen and (max-width: 600px) {
            .email-wrapper {
                margin: 0;
                border-radius: 0;
            }

            .email-header,
            .email-body,
            .email-footer {
                padding: 20px;
            }

            .button {
                padding: 12px 30px;
                font-size: 14px;
            }
        }
    </style>
</head>

<body>
    <div class="email-wrapper">
        <!-- Header -->
        <div class="email-header">
            <div class="logo-placeholder">
                <span class="logo-text">ضع الشعار هنا</span>
            </div>
            <h1>{{ $headerTitle ?? 'قصة سهلة' }}</h1>
            <p>{{ $headerSubtitle ?? 'قصص يومية ملهمة' }}</p>
        </div>

        <!-- Body -->
        <div class="email-body">
            @yield('content')
        </div>

        <!-- Footer -->
        <div class="email-footer">
            <p><strong>قصة سهلة</strong></p>
            <p>اكتشف قصة جديدة كل يوم</p>

            <div class="divider"></div>

            <p style="font-size: 12px;">
                تلقيت هذه الرسالة لأنك عضو في قصة سهلة<br>
                إذا كنت تريد إلغاء الاشتراك، يمكنك ذلك من إعدادات التطبيق
            </p>

            <p style="font-size: 12px; margin-top: 15px;">
                © {{ date('Y') }} قصة سهلة. جميع الحقوق محفوظة.
            </p>
        </div>
    </div>

    <!-- Tracking Pixel -->
    @if(isset($trackingId))
    <img src="{{ config('app.url') }}/api/email-tracking/{{ $trackingId }}/open"
        class="tracking-pixel" alt="">
    @endif
</body>

</html>