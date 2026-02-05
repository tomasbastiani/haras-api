<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Gastos Comunes Disponibles</title>
    <meta name="color-scheme" content="light dark">
    <meta name="supported-color-schemes" content="light dark">
</head>
<body style="margin:0; padding:0; background-color:#eef1f4; font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Arial, sans-serif;">

<table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="background-color:#eef1f4; padding:32px 0;">
    <tr>
        <td align="center">
            <!-- Tarjeta principal -->
            <table role="presentation" width="600" cellpadding="0" cellspacing="0" style="max-width:600px; background:#ffffff; border-radius:14px; overflow:hidden; box-shadow:0 3px 14px rgba(0,0,0,0.08);">

                {{-- Logo centrado --}}
                <tr>
                    <td align="center" style="padding:28px 24px 16px 24px;">
                        @if(!empty($logoUrl))
                            <img src="{{ $logoUrl }}" alt="Haras Santa María" style="max-height:70px; display:block; border:0;">
                        @else
                            <img src="https://harassantamaria.com.ar/icons/icon-512x512.png"
                                 alt="Haras Santa María"
                                 style="max-height:70px; display:block; border:0;">
                        @endif
                    </td>
                </tr>

                {{-- Encabezado textual --}}
                <tr>
                    <td align="center" style="padding:0 24px 24px 24px;">
                        <div style="font-size:20px; font-weight:600; color:#0b7285; margin-bottom:4px;">
                            Haras Santa María
                        </div>
                    </td>
                </tr>

                {{-- Contenido principal --}}
                <tr>
                    <td style="padding:0 32px 8px 32px; color:#111827; font-size:15px; line-height:1.6;">
                        <p style="margin:0 0 10px 0;">
                            Estimado, <strong>{{ $nombre }}</strong>
                        </p>

                        <p style="margin:10px 0;">
                            Ya están disponibles tus últimos gastos comunes
                            para tus lotes: <strong>{{ $lotes }}</strong>.
                        </p>

                        @if(!empty($periodo))
                            <p style="margin:10px 0; color:#4b5563;">
                                Período: <strong>{{ $periodo }}</strong>
                            </p>
                        @endif

                        <p style="margin:16px 0; color:#4b5563;">
                            Podés acceder al portal de propietarios para consultar el detalle,
                            descargar la documentación y ver el historial de tus gastos comunes.
                        </p>
                    </td>
                </tr>

                {{-- Botón principal --}}
                <tr>
                    <td align="center" style="padding:8px 32px 24px 32px;">
                        <a href="{{ $loginUrl }}"
                           style="
                               display:inline-block;
                               padding:12px 28px;
                               background-color:#1d4ed8;
                               color:#ffffff;
                               text-decoration:none;
                               border-radius:999px;
                               font-size:15px;
                               font-weight:bold;
                               letter-spacing:0.03em;
                               text-transform:uppercase;
                           ">
                            Ir al portal
                        </a>
                    </td>
                </tr>

                {{-- Separador sutil --}}
                <tr>
                    <td style="padding:0 32px;">
                        <hr style="border:none; border-top:1px solid #e9ecef; margin:8px 0 12px 0;">
                    </td>
                </tr>

                {{-- Nota aclaratoria --}}
                <tr>
                    <td style="padding:0 32px 20px 32px; color:#6b7280; font-size:12px; line-height:1.5;">
                        <p style="margin:0;">
                            Si no esperabas este correo, podés ignorarlo. 
                            Este mensaje es informativo y forma parte de las comunicaciones del barrio privado Haras Santa María.
                        </p>
                    </td>
                </tr>

                {{-- Footer con legales --}}
                <tr>
                    <td align="center" style="padding:12px 32px 16px 32px; background-color:#f9fafb; color:#9ca3af; font-size:11px; line-height:1.4;">
                        © {{ date('Y') }} Haras Santa María. Todos los derechos reservados.
                    </td>
                </tr>
            </table>
            <!-- Fin tarjeta -->
        </td>
    </tr>
</table>

</body>
</html>
