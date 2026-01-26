<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Recuperar contraseña</title>
</head>
<body>
    <p>Hola,</p>

    <p>Recibimos una solicitud para restablecer la contraseña de tu cuenta.</p>

    <p>Podés hacerlo haciendo clic en el siguiente enlace:</p>

    <p>
        <a href="{{ $resetLink }}">{{ $resetLink }}</a>
    </p>

    <p>Si vos no solicitaste este cambio, podés ignorar este correo.</p>

    <p>Saludos,<br>Equipo Haras</p>
</body>
</html>
