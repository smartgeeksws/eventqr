# EventQR

Aplicación sencilla de recepción privada de fotografías para PHP 8.3, MySQL y Apache. No usa frameworks, Composer ni servicios QR externos.

## Instalación en cPanel

1. En cPanel crea una base MySQL y un usuario, asígnalo a la base con todos los permisos e importa `database.sql` con phpMyAdmin. Si la base ya existe, importa una sola vez `database_migration_uploader.sql` antes de desplegar esta versión.
2. Crea una carpeta privada fuera de `public_html`, por ejemplo `/home/TU_USUARIO/eventqr_private`, y copia allí el directorio `private` completo. Copia `private/config.example.php` como `private/config.php`.
3. Edita `config.php`: completa los datos MySQL, `storage_path` como `/home/TU_USUARIO/eventqr_fotos` y `public_url` con el dominio HTTPS del sitio. Mantén `config.php` fuera de `public_html`.
4. Copia el contenido de `public/` a la carpeta donde se publicará EventQR. Para instalarlo en un subdirectorio como `/qr-eventos`, copia allí los archivos y conserva `assets/` debajo de ese directorio.
5. El núcleo privado se busca primero en `$HOME/eventqr_private/` cuando el sitio está desplegado; en el código fuente local se carga desde `private/`. El CLI carga el bootstrap relativo a su propia carpeta.
6. Crea la carpeta indicada en `storage_path` fuera de `public_html` con permisos 700 (si PHP no puede crearla automáticamente). No configures esa carpeta como subdominio ni alias web.
7. Desde Terminal de cPanel ejecuta: `php /home/TU_USUARIO/eventqr_private/bin/create_admin.php admin 'Una-clave-larga-y-unica'`. Usa una contraseña de al menos 12 caracteres y no la guardes en archivos públicos.
8. En cPanel selecciona PHP 8.3 y habilita PDO MySQL, Fileinfo y GD para crear vistas previas ligeras. Para aceptar HEIC, habilita EXIF con soporte HEIC. Activa también ZIP para descargar todas las fotos juntas. Ajusta `upload_max_filesize` y `post_max_size` a más de 50M, y `max_file_uploads` a 1 o más. Cada archivo se envía en una petición individual.
9. Comprueba que el sitio usa HTTPS e inicia sesión en `/qr-eventos/admin.php` (o la ruta que hayas elegido).

## Despliegue automático desde GitHub

El archivo `.github/workflows/deploy-cpanel.yml` reenvía cada push a `main` a un repositorio administrado por cPanel. cPanel ejecuta `.cpanel.yml`, que publica `public/` en `$HOME/lamoradadelviento.com/qr-eventos/` y copia el código privado a `~/eventqr_private/`. El archivo `config.php` no está versionado ni se sobrescribe.

Para esta instalación configura `public_url` como `https://lamoradadelviento.com/qr-eventos` en `~/eventqr_private/config.php` para que los QR abran la página correcta.

1. En cPanel abre **Git Version Control** y crea un repositorio vacío dentro de tu cuenta. No lo coloques dentro del document root. Copia el **Clone URL** SSH que muestra cPanel.
2. Asegúrate de que **SSH Access** está habilitado. Autoriza en cPanel una clave pública dedicada y conserva su clave privada para el siguiente paso.
3. En GitHub, abre el repositorio → **Settings → Secrets and variables → Actions**. Crea la variable `CPANEL_GIT_URL` con el Clone URL SSH de cPanel; crea el secreto `CPANEL_SSH_PRIVATE_KEY` con la clave privada autorizada; y crea `CPANEL_KNOWN_HOSTS` con la línea de host verificada de tu servidor (`ssh-keyscan TU_HOST`, comparada con la huella SSH publicada por tu proveedor).
4. `DEPLOYPATH` está configurado como `$HOME/lamoradadelviento.com/qr-eventos`, según la carpeta mostrada en el Administrador de archivos. El sitio que ya existe en la carpeta padre queda intacto. Confirma que sea la carpeta deseada en **cPanel → Dominios**; si `qr-eventos` todavía no existe, `.cpanel.yml` la crea. `PRIVATEDIR` debe permanecer fuera del document root. PHP debe recibir la variable de entorno `HOME` de cPanel para que las páginas encuentren el núcleo privado desde una ruta personalizada.
5. Comprueba en cPanel que el repositorio está configurado para desplegar la rama `main` y que contiene `.cpanel.yml`. Haz push a la rama `main` de GitHub; la acción lo reenviará a cPanel y el hook de cPanel desplegará los archivos.

La cuenta de hosting necesita Git, SSH y la función Git Version Control de cPanel. Si el proveedor no habilita SSH o repositorios Git administrados, se necesita otro método de despliegue, como un cliente SFTP.

## Uso

Desde Administración crea un evento. Descarga su QR SVG; se genera localmente en el navegador y contiene el enlace público del evento. Comparte el QR. Los invitados indican su nombre al subir. La galería muestra una vista previa reducida cuando GD está disponible; los archivos originales se conservan sin cambios. Desde la galería puedes descargar cada original, descargar todas las fotos del evento en un ZIP o eliminar fotos. También puedes cerrar o reactivar eventos.

## Notas operativas

- Las fotos se guardan con su contenido original y un nombre aleatorio fuera del document root. El navegador del invitado solo puede enviar archivos, nunca consultar imágenes.
- JPG, PNG y WebP se validan por MIME detectado con Fileinfo. HEIC/HEIF se acepta solo si la instalación de PHP informa soporte seguro mediante EXIF; si no, se rechaza.
- El límite de aplicación es 50 MiB por imagen. El hosting también debe permitir una petición POST superior a ese tamaño.
- Haz copias de seguridad tanto de MySQL como de la carpeta privada `eventqr_fotos`.
