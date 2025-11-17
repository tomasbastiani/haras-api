<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Gastos Comunes Disponibles</title>
</head>
<body style="margin:0; padding:0; background-color:#f3f4f6; font-family: Arial, Helvetica, sans-serif;">

<table width="100%" cellpadding="0" cellspacing="0" style="background-color:#f3f4f6; padding:24px 0;">
    <tr>
        <td align="center">
            <table width="600" cellpadding="0" cellspacing="0" style="background-color:#ffffff; border-radius:12px; overflow:hidden; box-shadow:0 8px 20px rgba(15,23,42,0.12);">

                {{-- Header con logo y franja de color --}}
                <tr>
                    <td align="center" style="background:linear-gradient(135deg,#0f172a,#1d4ed8); padding:24px 24px 16px 24px;">
                        @if(!empty($logoUrl))
                            <img src="{{ $logoUrl }}" alt="Haras Santa Maria"
                                 style="max-width:220px; height:auto; display:block; margin-bottom:8px;">
                        @endif
                    </td>
                </tr>

                {{-- Contenido principal --}}
                <tr>
                    <td style="padding:24px 32px 8px 32px; color:#111827; font-size:15px; line-height:1.6;">
                        <p style="margin:0 0 10px 0;">
                            Hola, <strong>{{ $nombre }}</strong>
                        </p>

                        <p style="margin:10px 0;">
                            Ya están disponibles tus últimos gastos comunes
                            para sus lotes: <strong>{{ $lotes }}</strong>.
                        </p>

                        @if(!empty($periodo))
                            <p style="margin:10px 0; color:#4b5563;">
                                Período: <strong>{{ $periodo }}</strong>
                            </p>
                        @endif

                        <p style="margin:16px 0; color:#4b5563;">
                            Podés acceder al portal de propietarios para consultar los detalles y descargar la documentación.
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

                {{-- Nota aclaratoria --}}
                <tr>
                    <td style="padding:0 32px 24px 32px; color:#6b7280; font-size:12px; line-height:1.5;">
                        <p style="margin:0;">
                            Si no esperabas este correo, podés ignorarlo. Este mensaje es informativo y forma parte de las comunicaciones del barrio privado Haras Santa Maria.
                        </p>
                    </td>
                </tr>

                {{-- Footer con legales --}}
                <tr>
                    <td align="center" style="padding:14px 32px 16px 32px; background-color:#f9fafb; color:#9ca3af; font-size:11px;">
                        © 2025 Haras Santa Maria. All rights reserved.
                    </td>
                </tr>
            </table>
        </td>
    </tr>
</table>

</body>
</html>
