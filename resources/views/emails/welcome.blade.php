?>

<!DOCTYPE html>
<html dir="rtl" lang="ar">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>مرحباً بك</title>
</head>

<body style="font-family: Arial, sans-serif; background-color: #f4f4f4; margin: 0; padding: 0;">
    <table width="100%" cellpadding="0" cellspacing="0" style="background-color: #f4f4f4; padding: 20px;">
        <tr>
            <td align="center">
                <table width="600" cellpadding="0" cellspacing="0" style="background-color: #ffffff; border-radius: 10px; overflow: hidden; box-shadow: 0 4px 6px rgba(0,0,0,0.1);">

                    <!-- Header -->
                    <tr>
                        <td style="background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: white; padding: 40px 20px; text-align: center;">
                            <h1 style="margin: 0; font-size: 28px;">🎉 مرحباً بك في {{ config('app.name') }}</h1>
                        </td>
                    </tr>

                    <!-- Content -->
                    <tr>
                        <td style="padding: 40px 30px;">
                            <h2 style="color: #333; font-size: 22px; margin-bottom: 20px;">
                                مرحباً {{ $memberName }}! 👋
                            </h2>

                            <p style="color: #666; line-height: 1.8; font-size: 16px;">
                                نحن سعداء جداً بانضمامك إلى مجتمعنا! شكراً لثقتك بنا.
                            </p>

                            <div style="background: #f9f9f9; padding: 20px; border-radius: 8px; margin: 20px 0;">
                                <h3 style="color: #333; font-size: 18px; margin-top: 0;">ما الذي يمكنك فعله الآن:</h3>
                                <ul style="color: #555; line-height: 1.8;">
                                    <li>✅ قراءة قصة يومية جديدة</li>
                                    <li>✅ تقييم القصص ومشاركة رأيك</li>
                                    <li>✅ حفظ القصص المفضلة</li>
                                    <li>✅ استكشاف فئات متنوعة</li>
                                </ul>
                            </div>

                            <div style="text-align: center; margin: 30px 0;">
                                <a href="{{ $appUrl }}" style="display: inline-block; background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: white; padding: 15px 40px; text-decoration: none; border-radius: 25px; font-weight: bold;">
                                    ابدأ القراءة الآن
                                </a>
                            </div>

                            <p style="color: #999; font-size: 14px; margin-top: 30px;">
                                <strong>نصيحة:</strong> أضف بريدنا الإلكتروني إلى جهات الاتصال لتضمن وصول إشعاراتنا.
                            </p>

                            <!-- Tracking Pixel -->
                            @if(isset($trackingId))
                            <img src="{{ config('app.url') }}/api/v1/email-track/open/{{ $trackingId }}" width="1" height="1" alt="" />
                            @endif
                        </td>
                    </tr>

                    <!-- Footer -->
                    <tr>
                        <td style="background: #f9f9f9; padding: 30px; text-align: center; color: #999; font-size: 14px;">
                            <p style="margin: 10px 0;">© {{ date('Y') }} {{ config('app.name') }}. جميع الحقوق محفوظة.</p>
                        </td>
                    </tr>

                </table>
            </td>
        </tr>
    </table>
</body>

</html>


<?php
