<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Turno confirmado</title>
    <meta name="color-scheme" content="light dark">
    <meta name="supported-color-schemes" content="light dark">
</head>
<body style="margin:0; padding:0; background-color:#eef1f4; font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Arial, sans-serif;">

    <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="background-color:#eef1f4; padding:32px 0;">
        <tr>
            <td align="center">

                <table role="presentation" width="600" cellpadding="0" cellspacing="0" style="max-width:600px; background:#ffffff; border-radius:14px; overflow:hidden; box-shadow:0 3px 14px rgba(0,0,0,0.08);">

                    <tr>
                        <td align="center" style="padding:28px 24px 16px 24px;">
                            <img src="https://harassantamaria.com.ar/icons/icon-512x512.png" alt="Haras Santa María" style="height:66px; display:block; border:0;">
                        </td>
                    </tr>

                    <tr>
                        <td align="center" style="padding:0 24px 24px 24px;">
                            <div style="font-size:20px; font-weight:600; color:#0b7285; margin-bottom:4px;">
                                Haras Santa María
                            </div>
                        </td>
                    </tr>

                    <tr>
                        <td style="padding:0 24px 20px 24px; font-size:15px; color:#333; line-height:1.6;">
                            <div style="font-size:18px; font-weight:600; color:#222; margin-bottom:14px;">
                                ✅ Tu turno fue confirmado
                            </div>

                            <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="background:#f8f9fa; border-radius:10px; margin-bottom:16px;">
                                <tr>
                                    <td style="padding:16px 18px; font-size:14px; color:#333; line-height:1.8;">
                                        <strong>Cancha:</strong> {{ $turno->cancha->nombre }}<br>
                                        <strong>Fecha:</strong> {{ \Carbon\Carbon::parse($turno->fecha)->locale('es')->isoFormat('dddd D [de] MMMM') }}<br>
                                        <strong>Horario:</strong> {{ \Carbon\Carbon::parse($turno->hora_inicio)->format('H:i') }} hs<br>
                                        <strong>Lote:</strong> {{ $turno->nlote ?? '-' }}
                                    </td>
                                </tr>
                            </table>

                            <div style="font-size:14px; color:#555;">
                                Recordá que podés cancelarlo desde la app hasta 24 horas antes del horario reservado.
                            </div>
                        </td>
                    </tr>

                    <tr>
                        <td style="padding:0 24px;">
                            <hr style="border:none; border-top:1px solid #e9ecef; margin:16px 0 12px 0;">
                        </td>
                    </tr>

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

                    <tr>
                        <td style="background-color:#f8f9fa; padding:12px 24px 14px 24px; font-size:11px; color:#999; text-align:center; line-height:1.5;">
                            Este mensaje ha sido enviado automáticamente desde el sistema de turnos de Haras Santa María.
                        </td>
                    </tr>

                </table>
            </td>
        </tr>
    </table>
</body>
</html>
