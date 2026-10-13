# Oficina de Paquetería y Mis Paquetes

> Recibir un paquete y avisar al propietario con su PIN, la Bandeja de paquetes, entregar con PIN o de forma manual (firma y foto), devolver al correo, agregar observaciones, el acuse del vecino y qué ve el vecino en Mis Paquetes.

Se entra desde **Menú → Administración → Oficina de Paquetería**. La usan el admin, los operarios habilitados y las cuentas de portería (ver sección `cuentas-paqueteria`).

Tiene dos pestañas: **Recibir paquete** y **Bandeja** (con un número que indica cuántos hay para retirar).

## Recibir un paquete

En la pestaña **Recibir paquete**:
1. **Lote \*** (obligatorio): el lote de la etiqueta. Ejemplo: 45.
2. **Correo \***: Mercado Libre, Andreani, OCA, Correo Argentino, Urbano u Otro.
3. **Tipo \***: Sobre, Caja chica, Caja grande o Bulto.
4. **Destinatario (según etiqueta)**: el nombre que figura en el paquete (opcional).
5. **ID del correo**: el número de seguimiento (opcional).
6. **Foto del paquete**: conviene sacarla siempre. Prueba en qué estado llegó si después aparece abierto o dañado.
7. **Observaciones** (opcional).
8. Tocar **Registrar y avisar al propietario**.

Resultado:
- La app genera un **código** del paquete, del tipo **P-7K3M9Q**. **Hay que escribirlo en la etiqueta**: sirve para encontrarlo en el estante.
- Genera un **PIN de 6 dígitos** y se lo manda a los propietarios del lote como notificación al celular y en la campana de la app. El vecino también lo ve en **Mis Paquetes**.
- Si el lote **no tiene ningún usuario vinculado**, el paquete se registra igual pero aparece como **⚠ sin vincular**, nadie recibe el PIN y **sólo se puede entregar por método manual**.

## Bandeja

Lista los paquetes con Código, Lote, Propietario, Correo y Estado. Filtros:
- Buscador por **código, nombre, ID del correo o lote** (Enter o **Buscar**).
- Estado: **Para retirar** (por defecto), **Retirados**, **Devueltos** o **Todos**.
- **Sin propietario vinculado**: sólo los "⚠ sin vincular".

Tocar una fila abre el **detalle**: datos del paquete, acta de entrega, fotos y firma (se tocan para verlas en grande) y el **Seguimiento** completo, que es el mismo historial que ve el vecino.

Cada fila tiene las acciones **Entregar** y **Devolver** (sólo si sigue para retirar) y **Observar**.

## Entregar un paquete

Tocar **Entregar** y elegir el método.

**Con PIN** (el normal):
1. Quien retira **dicta el PIN** de 6 dígitos que le llegó y se escribe en **PIN que dicta quien retira**.
2. Si se equivoca **5 veces**, el PIN se bloquea y la entrega sólo puede hacerse por método manual.

**Manual** (cuando no hay PIN: el vecino no tiene el celular, el paquete está sin vincular o el PIN se bloqueó):
1. Completar **Motivo de la entrega sin PIN** (obligatorio). Ejemplo: "el propietario no tiene el celular a mano".
2. Queda registrada como una **evidencia más débil**, que se revisa en los reportes.

En los dos casos, completar además:
- **Quién retira**: El titular, Una persona autorizada u Otro.
- **Nombre y apellido** (obligatorio) y **DNI** (opcional).
- **Firma de quien retira** (obligatoria): se firma con el dedo o el mouse en el recuadro. **Borrar firma** la limpia.
- **Foto de la entrega** (obligatoria).

Tocar **Confirmar entrega**. Se genera un **acta con número de folio** y el propietario recibe un aviso de que su paquete se entregó.

## Acuse del vecino y solidez de la entrega

Después de la entrega, el propietario puede responder desde Mis Paquetes **"Sí, la recibí"** o **"No la reconozco"**. Tiene **48 horas**; si no responde, el acuse se cierra solo como **tácito**.

En la Bandeja, cada entrega muestra qué tan sólida es como prueba:
- **PIN + acuse**: entregada con PIN y confirmada por el vecino. Es la más fuerte.
- **PIN**: entregada con PIN, sin confirmación todavía.
- **manual**: entregada sin PIN.
- **impugnada**: el vecino dijo que no la reconoce. **Los admins reciben una alerta** ("⚠️ Entrega desconocida") y hay que revisar el acta: firma, foto y quién retiró.

## Devolver un paquete al correo

**Devolver** → escribir el **Motivo** (obligatorio) → **Registrar devolución**. El paquete pasa a "Devuelto" y queda asentado en el seguimiento.

## Observaciones

**Observar** → escribir la **Nueva observación** (hasta 500 caracteres) → **Agregar al seguimiento**.
- **El propietario la ve.**
- **No se puede editar ni borrar después.**

## Nada se puede borrar

Todo lo que pasa con un paquete (ingreso, avisos, entrega, devolución, observaciones) queda en una **bitácora sellada**: no se puede modificar ni eliminar. Por eso hay que revisar bien el lote antes de registrar. Si un paquete se cargó con el lote equivocado, se deja una observación explicándolo; no se puede borrar.

Para pruebas se usa siempre el **Lote 0**, nunca el lote de un vecino real: lo que se registra queda para siempre.

## Mis Paquetes (lo que ve el vecino)

**Menú → Mis Paquetes** muestra cada paquete del propietario con su estado, código, fecha, correo, tipo, la **foto** (📷 Ver foto del paquete) y:
- Mientras está en la oficina: el **PIN de retiro**, que puede compartir con quien autorice a retirarlo.
- Cuando se entregó: quién lo retiró, el folio del acta, la foto de la entrega y los botones **Sí, la recibí** / **No la reconozco**.
- **Ver seguimiento**: el historial completo.

Si un vecino dice que no le llegó el PIN, puede verlo en Mis Paquetes. Si el lote está "sin vincular", hay que asociarle un email en **Editar Usuarios por Lote**.
