# Gastos Comunes (vista del admin)

> Publicar un período nuevo de gastos comunes (Agregar Gasto), eliminar un período y enviar el aviso por mail a todos los propietarios. Incluye el paso a paso mensual completo.

Se entra desde **Menú → Gastos Comunes**. El admin no ve facturas acá (eso lo ven los vecinos). Ve los botones **Agregar Gasto** y **📧 Enviar aviso por mail**, y una tabla con los **períodos** cargados, cada uno con su botón **Eliminar**.

## Qué es un "período"

Cada liquidación mensual tiene un número de período (por ejemplo **199**). Por cada período, cada lote tiene dos PDF:
- **Carta** (una por lote): `https://harassantamaria.com.ar/gcomunes/{periodo}/Cartas/L-{lote}.pdf`
- **Liquidación** (una sola, general): `https://harassantamaria.com.ar/gcomunes/{periodo}/LiqGastosComunes{periodo}.pdf`

La app **no sube esos PDF**. Tienen que estar subidos al servidor, en la carpeta `gcomunes/{periodo}/` del sitio (por FTP o el administrador de archivos del hosting), con esos nombres exactos, **antes** de publicar el período. Si falta un archivo, el vecino ve "No se encontró el documento para este período/lote".

## Paso a paso mensual: publicar los gastos comunes y avisar

1. **Subir los PDF** del período al servidor: las cartas en `gcomunes/{periodo}/Cartas/L-{lote}.pdf` y la liquidación como `gcomunes/{periodo}/LiqGastosComunes{periodo}.pdf`.
2. **Agregar el período**: Menú → Gastos Comunes → **Agregar Gasto** → escribir el número en **Periodo** → **Guardar**. Aparece "Gasto agregado con éxito" y el período se suma a la tabla.
3. **Revisar la lista de destinatarios** del aviso (emails, nombre, lote, CVU y alias) en **Importador Gastos Comunes**. Si cambió algo, importar el Excel nuevo (ver sección `importador-gastos`).
4. **Enviar el aviso**: Menú → Gastos Comunes → **📧 Enviar aviso por mail** → **Sí, enviar**.

## Agregar Gasto en detalle

- El formulario muestra dos **ejemplos** de link (carta y liquidación) sólo como referencia. Lo único que se completa es **Periodo**.
- Al guardar, se crea el período para **todos los pares email + lote que ya existen** en el sistema, con los links armados automáticamente según el número de período.
- Si el período ya existía para un lote, no se duplica.
- Los vecinos lo ven al instante en su pantalla de Gastos Comunes, con los botones **Ver Carta** y **Ver Liquidación**.
- Un lote que nunca tuvo gastos comunes cargados no aparece. La app no tiene pantalla para dar de alta un lote nuevo desde cero: hay que pedirlo al soporte técnico.

## Eliminar un período

Tocar **Eliminar** en la fila del período y después **Confirmar**. Borra ese período para **todos los lotes**: los vecinos dejan de verlo. No borra los PDF del servidor. No se puede deshacer; habría que volver a agregarlo con Agregar Gasto.

Para corregir un solo lote de un período no hace falta eliminar: se edita en **Listado Total Gastos Comunes**.

## Enviar aviso por mail

- Manda el mail "Nuevos gastos comunes disponibles" a **todos los emails** de la lista del **Importador Gastos Comunes**. Cada mail lleva el nombre, los lotes, el **período más alto cargado** y los datos de pago (CVU y alias) de cada lote.
- Los mails **no salen todos juntos**: se envían de a tandas, una por minuto. La ventana muestra el progreso y se puede cerrar: el envío sigue solo.
- Mientras está en curso, se puede **Pausar**, **Reanudar** o **Cancelar envío**.
- **Un solo aviso por período**: si se vuelve a tocar Enviar, responde "El aviso del período X ya fue enviado.", así no se manda dos veces por error.
- Cuando terminó, aparece **Reenviar a quienes no lo recibieron**. Manda sólo a los emails que todavía no lo recibieron en ese período, por ejemplo los que se agregaron o corrigieron en un import posterior.
- Si hay un aviso del mismo período en curso o pausado, no deja mandar otro hasta reanudarlo o cancelarlo.
- Se **omiten** automáticamente los emails que rebotan (direcciones inexistentes o que marcaron el mail como spam). Esto cuida la reputación del dominio: en agosto de 2026, un envío repetido a direcciones muertas hizo que el proveedor de mail bloqueara los envíos.

Para ver cómo salió un envío (enviados, errores, omitidos), se le puede preguntar al asistente Claude por los envíos masivos.
