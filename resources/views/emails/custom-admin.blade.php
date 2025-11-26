<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>{{ $subject ?? 'Haras Santa María' }}</title>
</head>
<body style="margin:0; padding:0; background-color:#f4f4f4; font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Arial, sans-serif;">

    <!-- Fondo general -->
    <table role="presentation" border="0" cellpadding="0" cellspacing="0" width="100%">
        <tr>
            <td align="center" style="padding: 30px 10px;">
                <!-- Card principal -->
                <table role="presentation" border="0" cellpadding="0" cellspacing="0" width="600" style="max-width: 600px; background-color:#ffffff; border-radius:12px; overflow:hidden; box-shadow:0 2px 10px rgba(0,0,0,0.08);">
                    
                    <!-- Header con logo -->
                    <tr>
                        <td style="background: linear-gradient(90deg, #004c6d, #0b7285); padding: 18px 24px; color:#ffffff;">
                            <table role="presentation" width="100%" cellpadding="0" cellspacing="0">
                                <tr>
                                    <td align="left" style="vertical-align: middle;">
                                        <img src="https://harassantamaria.com.ar/img/hsm.png" alt="Haras Santa María" style="height: 42px; display:block; border:0; outline:none;">
                                    </td>
                                    <td align="right" style="vertical-align: middle; font-size:13px; opacity:0.9;">
                                        <div style="font-weight:600; font-size:15px;">Haras Santa María</div>
                                        <div style="margin-top:2px;">Administración de expensas</div>
                                    </td>
                                </tr>
                            </table>
                        </td>
                    </tr>

                    <!-- Contenido principal -->
                    <tr>
                        <td style="padding: 24px 24px 16px 24px; color:#333333; font-size:14px; line-height:1.6;">
                            {{-- Título del mail (asunto, por si el cliente no lo muestra grande) --}}
                            @if(!empty($subject))
                                <h1 style="margin:0 0 16px 0; font-size:18px; font-weight:600; color:#222222;">
                                    {{ $subject }}
                                </h1>
                            @endif

                            {{-- Contenido custom que arma el admin (con {lote}, {detalleDeudaxLote}, etc.) --}}
                            <div style="font-size:14px; line-height:1.7; color:#333333;">
                                {!! nl2br(e($bodyContent)) !!}
                            </div>
                        </td>
                    </tr>

                    <!-- Línea separadora sutil -->
                    <tr>
                        <td style="padding: 0 24px;">
                            <hr style="border:none; border-top:1px solid #eeeeee; margin:16px 0 12px 0;">
                        </td>
                    </tr>

                    <!-- Footer con info de contacto -->
                    <tr>
                        <td style="padding: 0 24px 20px 24px; font-size:12px; color:#777777; line-height:1.5;">
                            <p style="margin:0 0 6px 0;">
                                Si tenés alguna consulta, podés comunicarte con la administración de Haras Santa María.
                            </p>
                            <p style="margin:0;">
                                <strong>Haras Santa María</strong>
                            </p>
                        </td>
                    </tr>

                    <!-- Pie legal / disclaimer (opcional, dejé algo suave) -->
                    <tr>
                        <td style="background-color:#f8f9fa; padding: 10px 24px 14px 24px; font-size:11px; color:#999999; text-align:center;">
                            Este mensaje ha sido enviado de forma automática desde el sistema de administración de Haras Santa María.
                        </td>
                    </tr>

                </table>
                <!-- Fin Card principal -->
            </td>
        </tr>
    </table>
</body>
</html>
