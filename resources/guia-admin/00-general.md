# Cómo está organizada la app

> Estructura del menú, quién ve qué, sesión, notificaciones y una tabla de tareas frecuentes con la sección donde se hace cada una.

## El menú

Al entrar se ve el **Menú** con tarjetas en dos bloques.

**General** (lo ven todos los propietarios, también el admin):
- **Gastos Comunes**: para el admin, es donde se publican los períodos y se manda el aviso por mail (sección `gastos-comunes`).
- **Sacar turno**: reservar SUM o Quincho (sección `turnos`).
- **Mis Paquetes**: los paquetes del propio usuario (sección `paqueteria-oficina`, al final).
- **Mi Perfil**: lotes y contraseña (sección `mi-perfil`).
- **Contacto/Servicios**: teléfonos y mails del barrio (sección `contacto`).
- **Ver Archivos**: en el admin; los vecinos la ven como "Adjuntar Archivos" (sección `archivos`).
- **Mensajería Interna**: sólo si la persona está habilitada (sección `mensajeria`).

**Administración** (sólo admin):
- Oficina de Paquetería → `paqueteria-oficina`
- Cuentas de Paquetería → `cuentas-paqueteria`
- Acceso a Mensajería → `mensajeria`
- Administrar Turnos → `turnos`
- Listado Total Gastos Comunes → `listado-gastos`
- Editar Usuarios por Lote → `editar-usuarios-lote`
- Enviar Email → `enviar-email`
- Importador Gastos Comunes → `importador-gastos`
- Importador Morosos → `importador-morosos`
- Asistente Claude → `asistente-claude`
- Centro de Notificaciones → `centro-notificaciones`

En todas las pantallas, la flecha **←** de arriba a la izquierda vuelve al menú. El ícono **☰** de la barra superior abre un panel lateral con las secciones y el botón **Cerrar sesión**. La **campana** de la barra superior muestra las notificaciones recibidas.

## Tipos de cuenta

- **Propietario**: ve sus gastos comunes, sus paquetes, sus turnos y sus archivos.
- **Admin**: además ve el bloque Administración.
- **Cuenta de paquetería** (portería): sólo ve la Oficina de Paquetería y Mi Perfil. Se crean en Cuentas de Paquetería.
- **Mensajería interna**: es un permiso que se suma a cualquier cuenta personal. Se da en Acceso a Mensajería.

La sesión no vence sola: dura hasta que la persona toca **Cerrar sesión**.

## Tareas frecuentes y dónde se hacen

| Quiero… | Sección |
|---|---|
| Publicar los gastos comunes del mes y avisar por mail | `gastos-comunes` (tiene el paso a paso completo) |
| Cargar o actualizar la lista de emails, CVU y alias del aviso | `importador-gastos` |
| Cargar la lista de deudores | `importador-morosos` |
| Corregir el link de la carta o liquidación de un lote | `listado-gastos` |
| Cambiar el email de un lote o dar de alta a un propietario | `editar-usuarios-lote` |
| Mandar un mail a una lista (por ejemplo, a los morosos con su deuda) | `enviar-email` |
| Mandar una notificación al celular | `centro-notificaciones` |
| Cancelar una reserva del SUM o Quincho | `turnos` |
| Recibir o entregar un paquete | `paqueteria-oficina` |
| Crear el usuario de portería | `cuentas-paqueteria` |
| Habilitar a un empleado en el chat interno | `mensajeria` |
| Ver los reclamos de los vecinos | `reclamos` |
| Un vecino olvidó su contraseña | `mi-perfil` (apartado contraseñas) |
