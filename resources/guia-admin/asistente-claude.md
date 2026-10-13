# Asistente Claude

> Conectar Claude Desktop con la app para hacerle preguntas sobre lotes, morosos, reclamos, paquetes, envíos y sobre cómo usar la app: generar, renovar y revocar el token, e instalar la extensión.

Se entra desde **Menú → Administración → Asistente Claude**.

## Qué es

Una conexión de **sólo lectura** entre la app y **Claude Desktop** (el programa de escritorio de Claude). Permite preguntarle a Claude cosas como "¿qué pasa con el lote 214?", "¿cuántos morosos hay?" o "¿cómo importo los morosos?". Claude **no puede modificar nada** ni mandar mails.

## Pantalla

Muestra si hay un token activo, cuándo vence y cuándo se usó por última vez, con dos botones:
- **Generar token** o **Generar uno nuevo (revoca el actual)**: crea el token. **Se muestra una sola vez**: hay que copiarlo en el momento con el botón **Copiar**.
- **Revocar**: el token deja de funcionar al instante. Sirve si se pierde la computadora o si alguien pudo verlo.

El token **vence a los 90 días**. Cada admin tiene su propio token.

## Instalar en Claude Desktop

1. Instalar **Claude Desktop** (claude.ai/download) y entrar con la cuenta de Claude.
2. Abrir el archivo de la extensión **haras-admin.mcpb** (lo provee el soporte técnico) con doble clic, o arrastrarlo a **Configuración → Extensiones** de Claude Desktop, y tocar **Instalar**.
3. En **Token de acceso**, pegar el token generado en esta pantalla. La **URL de la API** se deja como viene.
4. Verificar que la extensión quede **activada** y abrir un chat nuevo.

## Cuando vence o deja de andar

Si Claude responde "La API rechazó el token: venció o fue revocado":
1. Entrar a **Asistente Claude** → **Generar uno nuevo** → **Copiar**.
2. En Claude Desktop, ir a **Configuración → Extensiones → Haras Santa María — Administración** y pegar el token nuevo en **Token de acceso**.
