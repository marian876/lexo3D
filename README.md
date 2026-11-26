# LEXO — El desafío de las palabras

Aplicación web multijugador en **PHP 8.1+ y MySQL / SQLite** (sin dependencias externas obligatorias, lista para subir a **Hostinger**) que acompaña a **Lexo 3D** (compartido en la misma sala entre todos los participantes).

## Características implementadas

- **Estética "Tactile Arcade"**: botones con relieve 3D, fichas tipográficas de marca, modo **Mesa de Día** y **Noche de Torneo** (`color-scheme` nativo + preferencias).
- **Entrada en 5 segundos**: modo **Invitado rápido** con opción de vincular cuenta de **Google OAuth 2.0** sin perder progreso, XP ni estrellas.
- **Salas privadas con QR real**: código corto (`LEX-XXXX`), código QR SVG escaneable generado localmente y botón para compartir por WhatsApp.
- **Partidas sincronizadas por servidor**: temporizador calibrado con `server_now_ms` + `ends_at_ms` (sin desfase aunque el reloj del móvil esté mal), cierre automático transaccional y polling adaptativo compatible con hosting compartido Hostinger.
- **Normalización léxica en español**: conserva la `Ñ` distinta de la `N`, normaliza tildes para detectar duplicados (`árbol` = `ARBOL`), soporta modo **Mesa libre** (con anulación/consenso por el anfitrión) y modo **Diccionario español local** (`database/words_es.txt`).
- **Podio, logros y tarjeta compartible**: podio `2º - 1º - 3º`, recompensas idempotentes en `reward_transactions`, estadísticas acumuladas, ranking público sin exposición de emails y descarga de imagen PNG para Stories/WhatsApp.

## Prueba local rápida (SQLite)

1. Copiá el entorno de ejemplo:
   ```bash
   cp .env.example .env
   ```
2. Iniciá el servidor PHP integrado:
   ```bash
   php -S 127.0.0.1:8000 -t public
   ```
3. Abrí `http://127.0.0.1:8000` en dos ventanas (o una normal y una incógnito) para probar una sala multijugador completa.
4. Ejecutá las pruebas automatizadas:
   ```bash
   php tests/integration_test.php
   ```

## Despliegue en Hostinger (PHP + MySQL)

1. En **hPanel → Bases de datos MySQL**, creá una base de datos y un usuario.
2. En **phpMyAdmin**, importá `database/schema.mysql.sql`.
3. Subí el proyecto a tu hosting (idealmente apuntando el dominio a `public/`, o subiendo a `public_html/` donde el `.htaccess` raíz redirige a `public/` y bloquea carpetas internas).
4. Creá `.env` en la raíz con:
   ```env
   APP_ENV="production"
   APP_URL="https://tudominio.com"
   APP_DEBUG="false"
   DB_DRIVER="mysql"
   DB_HOST="localhost"
   DB_PORT="3306"
   DB_DATABASE="tu_db_hostinger"
   DB_USERNAME="tu_usuario_hostinger"
   DB_PASSWORD="tu_password"
   GOOGLE_CLIENT_ID="tu_client_id.apps.googleusercontent.com"
   GOOGLE_CLIENT_SECRET="tu_secret"
   GOOGLE_REDIRECT_URI="https://tudominio.com/auth/google/callback"
   ```
5. En Google Cloud Console, agregá `https://tudominio.com/auth/google/callback` como URI de redirección autorizada.
