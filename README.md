# EventQR

Aplicación sencilla de recepción privada de fotografías para PHP 8.3, MySQL y Apache. No usa frameworks, Composer ni servicios QR externos.

## Instalación en cPanel

1. En cPanel crea una base MySQL y un usuario, asígnalo a la base con todos los permisos e importa `database.sql` con phpMyAdmin.
2. Crea una carpeta privada fuera de `public_html`, por ejemplo `/home/TU_USUARIO/eventqr_private`, y copia allí el directorio `private` completo. Copia `private/config.example.php` como `private/config.php`.
3. Edita `config.php`: completa los datos MySQL, `storage_path` como `/home/TU_USUARIO/eventqr_fotos` y `public_url` con el dominio HTTPS del sitio. Mantén `config.php` fuera de `public_html`.
4. Copia el contenido de `public/` al document root del dominio (normalmente `public_html`). La carpeta `assets` debe quedar en `public_html/assets`.
5. Edita los `require dirname(__DIR__) . '/private/bootstrap.php'` de los PHP públicos para que apunten a la ruta real del núcleo privado si no se encuentra como carpeta hermana de `public_html`. Ejemplo: `require '/home/TU_USUARIO/eventqr_private/bootstrap.php';`. El CLI también debe cargar el bootstrap privado desde su ruta instalada.
6. Crea la carpeta indicada en `storage_path` fuera de `public_html` con permisos 700 (si PHP no puede crearla automáticamente). No configures esa carpeta como subdominio ni alias web.
7. Desde Terminal de cPanel ejecuta: `php /home/TU_USUARIO/eventqr_private/bin/create_admin.php admin 'Una-clave-larga-y-unica'`. Usa una contraseña de al menos 12 caracteres y no la guardes en archivos públicos.
8. En cPanel selecciona PHP 8.3 y habilita PDO MySQL, Fileinfo y, para aceptar HEIC, EXIF con soporte HEIC. Ajusta `upload_max_filesize` y `post_max_size` a más de 50M, y `max_file_uploads` a 1 o más. Cada archivo se envía en una petición individual.
9. Comprueba que el sitio usa HTTPS e inicia sesión en `/admin.php`.

## Uso

Desde Administración crea un evento. Descarga su QR SVG; se genera localmente en el navegador y contiene el enlace público del evento. Comparte el QR. En la galería privada puedes descargar cada original o eliminarlo, y puedes cerrar o volver a activar el evento.

## Notas operativas

- Las fotos se guardan con su contenido original y un nombre aleatorio fuera del document root. El navegador del invitado solo puede enviar archivos, nunca consultar imágenes.
- JPG, PNG y WebP se validan por MIME detectado con Fileinfo. HEIC/HEIF se acepta solo si la instalación de PHP informa soporte seguro mediante EXIF; si no, se rechaza.
- El límite de aplicación es 50 MiB por imagen. El hosting también debe permitir una petición POST superior a ese tamaño.
- Haz copias de seguridad tanto de MySQL como de la carpeta privada `eventqr_fotos`.
