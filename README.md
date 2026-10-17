# EventQR

Aplicación sencilla de recepción privada de fotografías para PHP 8.3, MySQL y Apache. No usa frameworks, Composer ni servicios QR externos.

## Instalación en cPanel

1. En cPanel crea una base MySQL y un usuario, asígnalo a la base con todos los permisos e importa `database.sql` con phpMyAdmin.
2. Crea una carpeta privada fuera de `public_html`, por ejemplo `/home/TU_USUARIO/eventqr_private`, y copia allí el directorio `private` completo. Copia `private/config.example.php` como `private/config.php`.
3. Edita `config.php`: completa los datos MySQL, `storage_path` como `/home/TU_USUARIO/eventqr_fotos` y `public_url` con el dominio HTTPS del sitio. Mantén `config.php` fuera de `public_html`.
4. Copia el contenido de `public/` al document root del dominio (normalmente `public_html`). La carpeta `assets` debe quedar en `public_html/assets`.
5. El núcleo privado se busca primero en `$HOME/eventqr_private/` cuando el sitio está desplegado; en el código fuente local se carga desde `private/`. El CLI carga el bootstrap relativo a su propia carpeta.
6. Crea la carpeta indicada en `storage_path` fuera de `public_html` con permisos 700 (si PHP no puede crearla automáticamente). No configures esa carpeta como subdominio ni alias web.
7. Desde Terminal de cPanel ejecuta: `php /home/TU_USUARIO/eventqr_private/bin/create_admin.php admin 'Una-clave-larga-y-unica'`. Usa una contraseña de al menos 12 caracteres y no la guardes en archivos públicos.
8. En cPanel selecciona PHP 8.3 y habilita PDO MySQL, Fileinfo y, para aceptar HEIC, EXIF con soporte HEIC. Ajusta `upload_max_filesize` y `post_max_size` a más de 50M, y `max_file_uploads` a 1 o más. Cada archivo se envía en una petición individual.
9. Comprueba que el sitio usa HTTPS e inicia sesión en `/admin.php`.

## Despliegue automático desde GitHub

El archivo `.github/workflows/deploy-cpanel.yml` reenvía cada push a `main` a un repositorio administrado por cPanel. cPanel ejecuta `.cpanel.yml`, que publica `public/` en `public_html/` y copia el código privado a `~/eventqr_private/`. El archivo `config.php` no está versionado ni se sobrescribe.

1. En cPanel abre **Git Version Control** y crea un repositorio vacío dentro de tu cuenta. No lo coloques dentro del document root. Copia el **Clone URL** SSH que muestra cPanel.
2. Asegúrate de que **SSH Access** está habilitado. Autoriza en cPanel una clave pública dedicada y conserva su clave privada para el siguiente paso.
3. En GitHub, abre el repositorio → **Settings → Secrets and variables → Actions**. Crea la variable `CPANEL_GIT_URL` con el Clone URL SSH de cPanel; crea el secreto `CPANEL_SSH_PRIVATE_KEY` con la clave privada autorizada; y crea `CPANEL_KNOWN_HOSTS` con la línea de host verificada de tu servidor (`ssh-keyscan TU_HOST`, comparada con la huella SSH publicada por tu proveedor).
4. Si el document root del dominio no es `public_html`, cambia `DEPLOYPATH` en `.cpanel.yml` a su ruta exacta. `PRIVATEDIR` debe permanecer fuera del document root. PHP debe recibir la variable de entorno `HOME` de cPanel para que las páginas encuentren el núcleo privado desde una ruta personalizada.
5. Comprueba en cPanel que el repositorio está configurado para desplegar la rama `main` y que contiene `.cpanel.yml`. Haz push a la rama `main` de GitHub; la acción lo reenviará a cPanel y el hook de cPanel desplegará los archivos.

La cuenta de hosting necesita Git, SSH y la función Git Version Control de cPanel. Si el proveedor no habilita SSH o repositorios Git administrados, se necesita otro método de despliegue, como un cliente SFTP.

## Uso

Desde Administración crea un evento. Descarga su QR SVG; se genera localmente en el navegador y contiene el enlace público del evento. Comparte el QR. En la galería privada puedes descargar cada original o eliminarlo, y puedes cerrar o volver a activar el evento.

## Notas operativas

- Las fotos se guardan con su contenido original y un nombre aleatorio fuera del document root. El navegador del invitado solo puede enviar archivos, nunca consultar imágenes.
- JPG, PNG y WebP se validan por MIME detectado con Fileinfo. HEIC/HEIF se acepta solo si la instalación de PHP informa soporte seguro mediante EXIF; si no, se rechaza.
- El límite de aplicación es 50 MiB por imagen. El hosting también debe permitir una petición POST superior a ese tamaño.
- Haz copias de seguridad tanto de MySQL como de la carpeta privada `eventqr_fotos`.
