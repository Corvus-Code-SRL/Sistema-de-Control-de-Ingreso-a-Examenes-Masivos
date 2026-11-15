# Respaldo y restauración de la base (RNF-04)

Si la base se cae, se corrompe o alguien borra datos por error, se vuelve al último respaldo. Este documento
dice cómo se respalda, cómo se restaura y cómo comprobar que un respaldo sirve **antes** de necesitarlo.

> **Los respaldos (`.dump`) contienen datos personales reales**: nombres, códigos SIS, CI, incidencias.
> Nunca se suben a git, nunca se dejan en una carpeta compartida ni en un Drive compartido y nunca se envían
> por chat o por correo. `*.dump` y `*.dump.parcial` están en el `.gitignore` y `storage/app` también, pero eso
> es una red de seguridad, no una autorización: el comando se niega a guardar en un directorio que esté
> dentro de una copia de git (salvo `storage/app`).

## Dónde corre cada cosa

| Entorno | Base | Cómo se respalda |
|---|---|---|
| **Producción** | PostgreSQL 15 nativo en un servidor **Windows** | `sciem:respaldar` con el `pg_dump.exe` de esa instalación. Es el destino principal de este documento. |
| **Desarrollo compartido** | Supabase (PostgreSQL 17.x) | Solo lo respalda y restaura el integrador (ver [Supabase](#supabase-solo-desarrollo)). |
| **Desarrollo local** | PostgreSQL del `docker-compose.dev.yml` | La imagen de Docker de desarrollo **no incluye** `pg_dump` (ver [Desarrollo local con Docker](#desarrollo-local-con-docker)). |

## Qué hace

| Comando | Qué hace |
|---|---|
| `php artisan sciem:respaldar` | `pg_dump` del esquema `public` (formato custom, comprimido) en `sciem_AAAAMMDD_HHMMSS.dump` (hora de La Paz) dentro del directorio de respaldos. Deja solo los **7** más recientes. |
| `php artisan sciem:restaurar` | Lista los respaldos guardados. Siempre se puede ejecutar. |
| `php artisan sciem:restaurar <archivo>` | Restaura. **Se niega salvo que `SCIEM_RESTAURAR_PERMITIDO=true`**, respalda primero la base actual y carga el respaldo en una sola transacción: si algo falla, la base queda como estaba. |

- Se respalda solo `public`, que es lo de SCIEM. En Supabase los demás esquemas (`auth`, `storage`…) son de la
  plataforma.
- Al restaurar se borran y recrean las tablas, tipos y funciones del respaldo, pero **no el esquema `public` en
  sí** (borrarlo arrastraría la extensión `pgcrypto`).
- La contraseña de la base viaja por el entorno del proceso (`PGPASSWORD`), nunca en la línea de comandos ni en
  los mensajes de error. `pg_dump` y `pg_restore` se ejecutan **sin shell**: una ruta con espacios, como
  `C:\Program Files\...`, funciona tal cual, sin comillas adicionales.
- Un respaldo que falla a la mitad no deja archivo: se escribe como `.parcial` y se renombra al terminar.
- La retención **nunca borra** el respaldo que acaba de crear, ni el respaldo previo de una restauración, ni el
  archivo que se está restaurando (aunque eso deje temporalmente más de 7).
- Si `pg_dump` falla, `sciem:respaldar` termina con código 1 y deja el error en `storage/logs/laravel.log`: es
  la señal para el respaldo diario, que corre sin nadie mirando.

## Variables del `.env`

| Variable | Por defecto | Para qué |
|---|---|---|
| `SCIEM_PG_DUMP` | `pg_dump` | Ruta de `pg_dump`. |
| `SCIEM_PG_RESTORE` | `pg_restore` | Ruta de `pg_restore`. |
| `SCIEM_RESPALDO_DIRECTORIO` | `storage/app/respaldos` | Carpeta de los respaldos. |
| `SCIEM_RESPALDO_RETENCION` | `7` | Cuántos respaldos se conservan. |
| `SCIEM_RESPALDO_DIARIO` | `false` | Programa el respaldo diario en el planificador de Laravel. **Solo `true` en el servidor de producción.** |
| `SCIEM_RESPALDO_HORA` | `03:15` | Hora del respaldo diario, en hora de La Paz. |
| `SCIEM_RESTAURAR_PERMITIDO` | `false` | Habilita `sciem:restaurar`. Solo `true` mientras se restaura. |
| `SCIEM_RESPALDO_TIMEOUT` | `1800` | Segundos máximos de cada `pg_dump` o `pg_restore`. |

### Rutas de Windows en el `.env`: comillas simples o barras `/`

En el `.env` **no use comillas dobles con rutas de Windows**. Con comillas dobles, `\r`, `\n` y `\t` se
convierten en caracteres de control (en `D:\respaldos`, el `\r` pasa a ser un retorno de carro), y cualquier
otra barra invertida (`\P` de `\Program Files`, `\b` de `\bin`) hace que la aplicación **falle al arrancar**
con «Encountered an unexpected escape sequence». Use comillas simples, o barras normales:

```bash
SCIEM_PG_DUMP='C:\Program Files\PostgreSQL\15\bin\pg_dump.exe'
SCIEM_PG_RESTORE='C:\Program Files\PostgreSQL\15\bin\pg_restore.exe'
SCIEM_RESPALDO_DIRECTORIO='D:\respaldos\sciem'
# equivalente, con barras:
# SCIEM_RESPALDO_DIRECTORIO=D:/respaldos/sciem
```

## Producción en Windows (PostgreSQL 15)

### 1. Cliente de PostgreSQL

El `pg_dump` debe ser de la **misma versión mayor del servidor o de una mayor**; uno más viejo se niega a
respaldar. El servidor de producción ya trae el cliente en `bin` de su instalación de PostgreSQL 15:

```text
C:\Program Files\PostgreSQL\15\bin\pg_dump.exe
C:\Program Files\PostgreSQL\15\bin\pg_restore.exe
```

Compruebe la versión desde una consola: `& 'C:\Program Files\PostgreSQL\15\bin\pg_dump.exe' --version` y
apunte el `.env` a esas rutas (con comillas simples, ver arriba).

### 2. Directorio de respaldos fuera del despliegue

Ponga los respaldos en una **unidad persistente fuera de la carpeta del despliegue** (un redeploy o una
reinstalación no debe llevárselos) y fuera de cualquier repositorio de git:

```bash
SCIEM_RESPALDO_DIRECTORIO='D:\respaldos\sciem'
```

El comando crea la carpeta si no existe y se niega a usar una que cuelgue de una copia de git.

### 3. Permisos de la carpeta (NTFS)

El `mkdir` con permisos `0700` **no tiene efecto en Windows**: la carpeta hereda los permisos de su padre.
Restrinja el acceso a la cuenta de servicio que ejecuta el planificador (aquí `SVC_SCIEM`, reemplácela por la
suya) y a los administradores, y quite la herencia:

```powershell
icacls "D:\respaldos\sciem" /inheritance:r
icacls "D:\respaldos\sciem" /grant:r "SVC_SCIEM:(OI)(CI)M" "BUILTIN\Administrators:(OI)(CI)F"
icacls "D:\respaldos\sciem"   # verificar: no debe aparecer Users, Everyone ni Authenticated Users
```

Haga lo mismo con la carpeta donde guarde las copias fuera de la máquina.

### 4. Respaldo diario: dos opciones

> `SCIEM_RESPALDO_DIARIO=true` va **solo en el servidor de producción**. En las máquinas de desarrollo
> se deja en `false`. Y use **una** de las dos opciones, no ambas: con las dos se generaría un respaldo doble.

**Opción (a) — el planificador de Laravel (recomendada).** Una tarea del Programador de tareas de Windows
ejecuta `php artisan schedule:run` **cada minuto**, como **una sola instancia**, y el respaldo se programa con
`SCIEM_RESPALDO_DIARIO=true` y `SCIEM_RESPALDO_HORA=03:15` (hora de La Paz, no la del servidor).

Se recomienda porque SCIEM **ya exige** ese planificador en producción: la apertura del control de ingreso y el
cierre de exámenes vencidos corren cada minuto desde `schedule:run`. Así hay una sola tarea que vigilar y la
hora del respaldo se define en `.env`, en hora de La Paz.

Tarea del Programador de tareas (ajuste las rutas):

- **General:** ejecutar con la cuenta de servicio, «Ejecutar tanto si el usuario inició sesión como si no».
- **Desencadenador:** diario, repetir cada **1 minuto** indefinidamente.
- **Acción:** programa `C:\php\php.exe`, argumentos `artisan schedule:run`, **Iniciar en** `C:\sciem\backend`.
- **Configuración → «Si la tarea ya se está ejecutando»:** **«No iniciar una nueva instancia»**. Es lo que
  garantiza una sola instancia.

Comprobar que quedó programado: `php artisan schedule:list` debe mostrar `sciem:respaldar` a las `15 3 * * *`.

**Opción (b) — tarea directa, una vez al día.** Una tarea del Programador de tareas ejecuta
`php artisan sciem:respaldar` a diario, **sin** pasar por el planificador de Laravel; en `.env` deje
`SCIEM_RESPALDO_DIARIO=false`.

- **Acción:** programa `C:\php\php.exe`, argumentos `artisan sciem:respaldar`, **Iniciar en** `C:\sciem\backend`.
- **Desencadenador:** diario a la hora deseada. Aquí la hora es la **del servidor Windows**, no la de La Paz.

Es más simple y no depende de que el planificador esté vivo, pero es una segunda tarea que mantener y
`SCIEM_RESPALDO_HORA` no se aplica. Elíjala si el planificador de Laravel no puede correr en esa máquina.

**Una copia fuera de la máquina.** Siete respaldos en el mismo servidor no bastan si el servidor se pierde. Una
vez por semana copie el último `.dump` a un lugar privado fuera de él (un disco externo, un recurso de red
privado con permisos restringidos). Copie también `storage/app/evidencias`.

## Desarrollo local con Docker

La imagen de desarrollo (`sciem-php:dev`, Debian 11) **no incluye `pg_dump` ni `pg_restore`**, a propósito: el
repositorio de PostgreSQL para esa versión de Debian está archivado y instalar su cliente reemplaza la `libpq`
de todos los desarrolladores. Dentro de ese contenedor, `sciem:respaldar` falla con
`pg_dump terminó con el código 127 … Revise SCIEM_PG_DUMP y SCIEM_PG_RESTORE`.

Si necesita un respaldo de la base **local** de Docker, hay dos caminos:

1. **`pg_dump` del propio contenedor de PostgreSQL** (trae su cliente). Se escribe a un archivo dentro del
   contenedor y se copia afuera (no use `>` en PowerShell: corrompe el binario):

    ```bash
    DC="docker compose -f deployment/docker/docker-compose.dev.yml"
    $DC exec postgres pg_dump -U postgres --format=custom --no-owner --no-privileges --schema=public \
        --file=/tmp/sciem_local.dump sciem_db
    $DC cp postgres:/tmp/sciem_local.dump ./sciem_local.dump
    ```

   El `.dump` queda en la carpeta actual: borre o mueva ese archivo cuando termine (está ignorado por git, pero
   tiene los mismos datos que la base).

2. **Cliente instalado en su máquina** (PostgreSQL ≥ 15, basta «Command Line Tools»; o `postgresql-client`
   en WSL) y `php artisan sciem:respaldar` ejecutado **fuera** del contenedor, con `SCIEM_PG_DUMP` apuntando
   a ese cliente. Solo aplica si tiene PHP en su máquina.

Para **restaurar** en local no use `sciem:restaurar`: reconstruya la base con `migrate` y los seeders, o
cargue el respaldo en una base **nueva** como en [Comprobar que un respaldo sirve](#comprobar-que-un-respaldo-sirve-una-vez-al-mes).

## Antes de una operación riesgosa

Antes de `migrate` en la base compartida, de un seeder sobre datos reales o de cualquier cambio a mano:

```bash
php artisan sciem:respaldar
```

y anotar el nombre del archivo que muestra.

## Restaurar

`sciem:restaurar` **reemplaza todos los datos** de la base de la conexión actual y tiene varias protecciones:

1. **`SCIEM_RESTAURAR_PERMITIDO`.** Si es `false` (el valor por defecto) el comando se niega antes de hacer nada:
   «La restauración está deshabilitada en esta máquina…». Listar los respaldos sí se puede siempre.
2. **Producción exige `--force`**, y `--force` sigue exigiendo `SCIEM_RESTAURAR_PERMITIDO=true`.
3. **Confirmación escrita.** El comando muestra el host y la base que va a reemplazar y pide **escribirlos**:
   primero el host exacto y luego el nombre exacto de la base (se distingue mayúsculas de minúsculas), y
   después responder `yes`. En **producción**, `--force` salta esa confirmación; en cualquier otro entorno
   `--force` no salta nada.
4. **Supabase.** Si el host termina en `supabase.com` o `supabase.co`, además exige `--supabase` y muestra un
   aviso de que reemplaza la base de desarrollo compartida.
5. **Respaldo previo.** Antes de restaurar guarda un respaldo de cómo está la base (salvo `--sin-respaldo-previo`,
   solo si la base está tan rota que `pg_dump` no puede leerla).

### Procedimiento

1. Avisar al equipo y detener lo que escriba en la base (el planificador, el backend). `pg_restore` espera a que
   se liberen las tablas que otros están usando.
2. **Habilitar la restauración** poniendo `SCIEM_RESTAURAR_PERMITIDO=true` en el `.env` **solo mientras restaura**.
3. Ver los respaldos y restaurar el elegido:

    ```bash
    php artisan sciem:restaurar
    php artisan sciem:restaurar sciem_20261111_031500.dump
    # en producción:
    php artisan sciem:restaurar sciem_20261111_031500.dump --force
    ```

4. **Volver a poner `SCIEM_RESTAURAR_PERMITIDO=false`** en cuanto termine.
5. Revisar `php artisan migrate:status`. El respaldo trae su propia tabla `migrations`: si es de antes de una
   migración, esa migración aparece en `No` y **sus tablas nuevas siguen en la base** (no estaban en el respaldo,
   así que no se borraron). Antes de volver a migrar, coordinar con el líder.
6. Volver a encender el backend y el planificador.

> **Con `config:cache`.** Si el servidor usa la configuración cacheada, un cambio en el `.env` **no tiene
> efecto** hasta volver a generarla. Después de **cada** cambio de `SCIEM_RESTAURAR_PERMITIDO` (al habilitar y al
> deshabilitar) ejecute `php artisan config:cache` de nuevo, o `php artisan config:clear` si no usa la caché.
> Una variable de entorno puesta solo en la sesión de la consola tampoco se aplica con la configuración cacheada.

## Supabase (solo desarrollo)

Supabase es únicamente la base de **desarrollo compartida**. Para respaldarla:

- `pg_dump` de versión **igual o mayor** que la del servidor de Supabase (hoy PostgreSQL 17.x; compruébelo con
  `show server_version;` en su consola SQL). Un `pg_dump` 15 se niega a respaldar un servidor 17.
- Conectar por el **Session Pooler, puerto 5432** (el Transaction Pooler, 6543, no sirve para `pg_dump`), con
  `DB_SSLMODE=require` y el usuario `postgres.<id-proyecto>`.
- Es una máquina de desarrollo: **no** active `SCIEM_RESPALDO_DIARIO` en ella.

**Restaurar sobre Supabase lo hace solo el integrador.** Exige `SCIEM_RESTAURAR_PERMITIDO=true`, la opción
`--supabase` y la confirmación escrita del host y la base, porque reemplaza los datos de todo el equipo.

## Comprobar que un respaldo sirve (una vez al mes)

Un respaldo que nunca se restauró no está probado. Se prueba en una base **nueva y descartable**, nunca en la
de producción ni en la compartida. En el servidor de pruebas (con `psql` del cliente de PostgreSQL en el `PATH`):

```bash
createdb -U postgres sciem_restore_prueba
pg_restore --no-owner --no-privileges --exit-on-error -U postgres -d sciem_restore_prueba \
  "D:\respaldos\sciem\sciem_20261111_031500.dump"
psql -U postgres -d sciem_restore_prueba -c "select count(*) from usuario;" -c "select max(fecha_hora) from log;"
dropdb -U postgres sciem_restore_prueba
```

Si las cifras coinciden con las de la base de origen (la última fila de `log` es de la hora del respaldo), el
respaldo sirve. Borre la base de prueba al terminar: tiene datos reales.

## Qué no cubre

- Los archivos que viven fuera de la base, como las evidencias de reportes (`storage/app/evidencias`): de ellos
  solo se respalda la ruta guardada. Copie esa carpeta junto con el `.dump` semanal.
- El archivo `.env`: guárdelo aparte, en un lugar privado.

## Lista de pruebas manuales en el servidor Windows

La suite automática simula `pg_dump` y `pg_restore`, y el respaldo y la restauración reales se verificaron contra
PostgreSQL 15 en Linux. **Nada de lo siguiente se ha probado en Windows**; hágalo una vez en el servidor (o en una
copia) antes de confiar en el respaldo:

1. [ ] `& 'C:\Program Files\PostgreSQL\15\bin\pg_dump.exe' --version` y `pg_restore.exe --version` muestran 15.x.
2. [ ] El `.env` usa comillas simples (o `/`) y `php artisan sciem:restaurar` (sin archivo, solo lista) arranca sin
       el error «unexpected escape sequence».
3. [ ] `php artisan sciem:respaldar` guarda `sciem_AAAAMMDD_HHMMSS.dump` en `SCIEM_RESPALDO_DIRECTORIO`, con un
       tamaño razonable, sin archivos `.parcial` sobrantes, y la hora del nombre es la de La Paz.
4. [ ] Con `SCIEM_PG_DUMP` apuntando a una ruta que no existe, el comando falla con «Revise SCIEM_PG_DUMP…» y
       código 1, y el error queda en `storage/logs/laravel.log`.
5. [ ] Con `SCIEM_RESPALDO_DIRECTORIO` dentro del repositorio (o de una carpeta con `.git`), el comando se niega.
6. [ ] `icacls` de la carpeta de respaldos no muestra `Users`, `Everyone` ni `Authenticated Users`; una cuenta
       sin permiso no puede abrirla.
7. [ ] **Restauración en una copia primero:** con `DB_DATABASE` apuntando a una base de pruebas, con
       `SCIEM_RESTAURAR_PERMITIDO=false` el comando se niega; con `true` pide el host, la base y `yes`; una
       entrada equivocada cancela sin cambiar nada; la fila insertada después del respaldo desaparece, existe el
       respaldo previo y `pgcrypto` sigue en `public`.
8. [ ] En producción (`APP_ENV=production`) sin `--force` se niega; con `--force` restaura sin preguntar. Pruébelo
       solo contra la base de pruebas.
9. [ ] Con `config:cache`, cambiar `SCIEM_RESTAURAR_PERMITIDO` en el `.env` no surte efecto hasta
       `php artisan config:cache`.
10. [ ] La opción (a) o (b) del Programador de tareas se ejecutó a la hora prevista, el respaldo apareció, y no
       hay dos tareas haciendo lo mismo. Con la (a): `schedule:list` muestra `sciem:respaldar` y la tarea tiene
       «No iniciar una nueva instancia».
11. [ ] Con una base de tamaño real, el respaldo termina dentro de `SCIEM_RESPALDO_TIMEOUT` (1800 s por defecto) y
       el antivirus no bloquea el renombrado final (el comando reintenta 3 veces).
12. [ ] Una vez al mes, la prueba de la sección «Comprobar que un respaldo sirve».
