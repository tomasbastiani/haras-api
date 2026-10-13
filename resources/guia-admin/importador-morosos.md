# Importador Morosos

> Cargar desde Excel la lista de lotes con deuda (email, nombre, lote, monto). Para qué se usa la lista y formato del archivo.

Se entra desde **Menú → Administración → Importador Morosos**.

## Para qué sirve

La lista de morosos se usa en:
- **Enviar Email**: las variables `{nombremoroso}`, `{lotemoroso}` y `{detalledeudaxlote}` toman los datos de acá (ver sección `enviar-email`).
- **Asistente Claude**: las consultas de morosos y la ficha de cada lote.

Los vecinos **no** ven esta lista en la app.

## Qué muestra

Una tabla con Email, Nombre, Número de Lote y Monto, con filtros por **Email** y **Número de Lote**.

## Cómo importar

1. Armar un Excel con una fila por lote deudor:
   - **A – Email**
   - **B – Nombre**
   - **C – Lote**
   - **D – Monto** (número, sin el signo $)
   - La **primera fila es el encabezado** y se ignora.
2. Tocar **Importar Excel**, elegir el archivo (.xlsx o .xls) y tocar **Importar**.

## Importante

- **Reemplaza la lista entera**: los lotes que no estén en el Excel nuevo dejan de figurar como morosos. Siempre hay que importar la lista completa y actualizada.
- Las filas con **alguna celda vacía se saltean sin avisar**. Lo mismo pasa con un monto en 0 y con el lote "0".
- La lista guarda **un monto total por lote**, sin detalle de períodos adeudados.
