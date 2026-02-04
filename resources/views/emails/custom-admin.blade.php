<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>{{ $subject ?? 'Haras Santa María' }}</title>
    <meta name="color-scheme" content="light dark">
    <meta name="supported-color-schemes" content="light dark">
</head>
<body style="margin:0; padding:0; background-color:#eef1f4; font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Arial, sans-serif;">

    <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="background-color:#eef1f4; padding:32px 0;">
        <tr>
            <td align="center">

                <!-- Tarjeta principal -->
                <table role="presentation" width="600" cellpadding="0" cellspacing="0" style="max-width:600px; background:#ffffff; border-radius:14px; overflow:hidden; box-shadow:0 3px 14px rgba(0,0,0,0.08);">

                    <!-- Logo centrado -->
                    <tr>
                        <td align="center" style="padding:28px 24px 16px 24px;">
                            <img src="https://harassantamaria.com.ar/icons/icon-512x512.png" alt="Haras Santa María" style="height:66px; display:block; border:0;">
                        </td>
                    </tr>

                    <!-- Encabezado textual -->
                    <tr>
                        <td align="center" style="padding:0 24px 24px 24px;">
                            <div style="font-size:20px; font-weight:600; color:#0b7285; margin-bottom:4px;">
                                Haras Santa María
                            </div>
                        </td>
                    </tr>

                    <!-- Contenido -->
                    <tr>
                        <td style="padding:0 24px 20px 24px; font-size:15px; color:#333; line-height:1.6;">

                            @if(!empty($subject))
                                <div style="font-size:18px; font-weight:600; color:#222; margin-bottom:14px;">
                                    {{ $subject }}
                                </div>
                            @endif

                            <div style="font-size:15px; line-height:1.7; color:#333333;">
                                {!! nl2br(e($bodyContent)) !!}
                            </div>
                        </td>
                    </tr>

                    <!-- Divider -->
                    <tr>
                        <td style="padding:0 24px;">
                            <hr style="border:none; border-top:1px solid #e9ecef; margin:16px 0 12px 0;">
                        </td>
                    </tr>

                    <!-- Footer de contacto -->
                    <tr>
                        <td style="padding:0 24px 24px 24px; font-size:13px; color:#6c757d; line-height:1.5;">
                            <p style="margin:0 0 6px 0;">
                                Si tenés alguna consulta, podés comunicarte con la administración.
                            </p>
                            <p style="margin:0; font-weight:600; color:#0b7285;">
                                Haras Santa María
                            </p>
                        </td>
                    </tr>

                    <!-- Disclaimer -->
                    <tr>
                        <td style="background-color:#f8f9fa; padding:12px 24px 14px 24px; font-size:11px; color:#999; text-align:center; line-height:1.5;">
                            Este mensaje ha sido enviado automáticamente desde el sistema de administración de Haras Santa María.
                        </td>
                    </tr>

                </table>
                <!-- Fin tarjeta -->
            </td>
        </tr>
    </table>
</body>
</html>
