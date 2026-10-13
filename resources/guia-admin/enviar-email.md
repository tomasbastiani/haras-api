# Enviar Email (correo personalizado)

> Mandar un mail con asunto y texto libres a una lista de direcciones, con variables que se reemplazan por los datos de cada propietario (nombre, lotes, deuda). Cómo cargar destinatarios, qué hace cada variable, y el progreso, pausa y reenvío.

Se entra desde **Menú → Administración → Enviar Email**. La pantalla se titula "Enviar correo personalizado".

## 1. Destinatarios

Hay dos formas de cargarlos, y se pueden combinar:
- **Destinatarios (uno por uno)**: escribir un email y apretar **Enter** o **coma**. Queda como una etiqueta; la **×** lo saca. **Limpiar lista** borra todos.
- **Carga masiva de emails**: pegar una lista (por ejemplo, una columna copiada de Excel) separada por coma, punto y coma, espacio o salto de línea, y tocar **Procesar lista y agregar**. Muestra cuántos se agregaron, cuántos estaban duplicados y cuáles son inválidos.

Abajo se ve el total en **Destinatarios válidos**.

## 2. Contenido

- **Asunto**.
- **Cuerpo del mensaje**: texto libre. Las variables se ven resaltadas en color y se reemplazan **por los datos de cada destinatario** al enviar:

| Variable | Se reemplaza por |
|---|---|
| `{nombre}` | El nombre del usuario con ese email. |
| `{lote}` | Los lotes que tiene ese email en gastos comunes, separados por coma. |
| `{nombremoroso}` | El nombre que figura en la lista de morosos para ese email. |
| `{lotemoroso}` | Los lotes de ese email que están en la lista de morosos. |
| `{detalledeudaxlote}` | Por cada lote con deuda: el lote, el monto y los datos de la cuenta (CVU y alias) para transferir. |

Las variables de morosos salen del **Importador Morosos**: hay que importar la lista actualizada antes de mandar un mail de deuda. Si un destinatario no es moroso, esas variables quedan vacías. Los CVU y alias de `{detalledeudaxlote}` salen de una tabla de datos de pago que no tiene pantalla en la app; si salen vacíos, hay que pedirle al soporte técnico que la cargue.

Ejemplo de mail de deuda:

```
Estimado/a {nombremoroso}:
Le informamos que registramos un saldo pendiente en su/s lote/s {lotemoroso}.

{detalledeudaxlote}

Ante cualquier consulta, comuníquese con la administración.
```

## 3. Enviar

Tocar **Enviar correo**. Igual que el aviso de gastos comunes:
- Los mails salen **de a tandas, una por minuto**. Se puede salir de la pantalla: el envío sigue solo. Arriba se ve el progreso.
- Se puede **Pausar**, **Reanudar** o **Cancelar envío**.
- **No deja mandar dos veces el mismo mail** (mismo asunto, mismo texto, misma lista): responde que ya se envió. Esto evita duplicados por doble clic o por reintentar.
- Cuando terminó, aparece **Reenviar a quienes no lo recibieron**, que manda sólo a los que les faltó.
- Los emails que rebotan o marcaron spam se **omiten** solos.

## Consejos

- Para probar el texto, mandalo primero sólo a tu propio email.
- Para avisos generales cortos y urgentes, como un corte de agua, conviene una notificación al celular desde el **Centro de Notificaciones**.
