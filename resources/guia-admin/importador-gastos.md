# Importador Gastos Comunes (Notificaciones)

> Cargar desde Excel la lista de destinatarios del aviso mensual de gastos comunes: email, nombre, lote, CVU y alias de pago. Plantilla, formato de columnas y errores comunes.

Se entra desde **Menú → Administración → Importador Gastos Comunes**. La pantalla se titula "Importador Gastos Comunes Notificaciones".

## Para qué sirve

Esta lista define **a quién le llega el mail "Nuevos gastos comunes disponibles"** que se manda desde Gastos Comunes → Enviar aviso por mail, y qué **CVU y alias** de pago aparecen en ese mail para cada lote.

Es una lista independiente de los gastos comunes publicados. Importarla **no** publica ningún período y **no** manda mails.

## Qué muestra

Una tabla con Email, Nombre, Número de Lote, CVU y Alias de la lista vigente, con filtros por **Email** y **Número de Lote**, el total de registros y paginado.

## Cómo importar

1. Tocar **Descargar plantilla**. Se baja un Excel con las columnas correctas y la columna del CVU ya formateada como Texto.
2. Completar una fila por destinatario:
   - **A – Email** (obligatorio)
   - **B – Nombre** (obligatorio)
   - **C – Lote** (obligatorio)
   - **D – CVU** (opcional): 22 dígitos exactos. Se aceptan espacios o guiones, que se ignoran.
   - **E – Alias** (opcional): de 6 a 20 caracteres, sólo letras, números, punto y guion.
   - La **primera fila es el encabezado** y se ignora.
3. Tocar **Importar Excel**, elegir el archivo (.xlsx o .xls) y tocar **Importar**.

## Importante

- **Reemplaza la lista entera**: lo que no esté en el Excel nuevo desaparece de la lista. Siempre hay que importar la lista completa, no sólo los cambios.
- **Todo o nada**: si alguna fila tiene un error, **no se importa nada** y la lista anterior queda igual. La ventana muestra los errores con el número de fila de Excel, para corregirlos y volver a importar.
- Las filas sin email, nombre o lote se saltean sin avisar.
- Un lote o un email pueden aparecer en varias filas (por ejemplo, un propietario con dos lotes).

## Errores comunes

- **"el CVU está cargado como número y Excel le redondea los últimos dígitos"**: la columna D no está como Texto. Excel guarda los números con 15 dígitos y cambia los últimos del CVU sin avisar; el vecino le transferiría a otra cuenta. Solución: usar la plantilla, o formatear la columna D como **Texto** y **volver a escribir** los CVU (los dígitos perdidos no se recuperan solos).
- **"el CVU … no es válido (tiene que tener 22 dígitos)"**: le faltan o le sobran dígitos.
- **"el alias … no es válido"**: tiene espacios, tildes u otros símbolos, o menos de 6 o más de 20 caracteres.
- **"El archivo está vacío o no tiene datos válidos"**: el Excel sólo tiene el encabezado.
