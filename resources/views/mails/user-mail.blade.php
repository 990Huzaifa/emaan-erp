{{-- resources/views/mails/user-mail.blade.php --}}

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Mail Template</title>
</head>
<body style="margin:0; padding:0; background-color:#f4f7fb; font-family:Arial, sans-serif; color:#333333;">

    <table width="100%" cellpadding="0" cellspacing="0" border="0" style="background-color:#f4f7fb; padding:30px 0;">
        <tr>
            <td align="center">
                <table width="600" cellpadding="0" cellspacing="0" border="0" style="background:#ffffff; border-radius:12px; overflow:hidden; box-shadow:0 4px 12px rgba(0,0,0,0.08);">
                    
                    <!-- Header -->
                    <tr>
                        <td align="center" style="background:linear-gradient(135deg, #4f46e5, #7c3aed); padding:30px 20px;">
                            <h1 style="margin:0; font-size:24px; color:#ffffff;">New Message</h1>
                            <p style="margin:8px 0 0; font-size:14px; color:#e0e7ff;">You have received a new notification</p>
                        </td>
                    </tr>

                    <!-- Body -->
                    <tr>
                        <td style="padding:35px 30px;">
                            <p style="margin:0 0 20px; font-size:16px; line-height:1.7; color:#444444;">
                                {{ $message }}
                            </p>

                            @if($is_url)
                                <div style="margin-top:25px; padding:18px; background-color:#f9fafb; border:1px solid #e5e7eb; border-radius:8px;">
                                    <p style="margin:0 0 10px; font-size:14px; font-weight:bold; color:#111827;">
                                        Related Link
                                    </p>
                                    <a href="{{ $url }}" target="_blank" style="font-size:14px; color:#4f46e5; text-decoration:none; word-break:break-all;">
                                        {{ $url }}
                                    </a>
                                </div>
                            @endif
                        </td>
                    </tr>

                    <!-- Footer -->
                    <tr>
                        <td align="center" style="padding:20px; background-color:#f3f4f6; font-size:12px; color:#6b7280;">
                            <p style="margin:0;">This is an automated email. Please do not reply.</p>
                        </td>
                    </tr>

                </table>
            </td>
        </tr>
    </table>

</body>
</html>