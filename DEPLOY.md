# Guía de Despliegue: Dataholics Resolve → Site5
# Dominio: resolve.dataholics.com.mx
# FTP User: DEV_resolve@dataholics.com.mx

## Estructura Final en el Servidor Site5

```
/home1/noodluis/
│
├── api_resolve/          ← Carpeta PRIVADA (fuera de public_html)
│   ├── app/
│   ├── system/
│   ├── writable/
│   └── .env              ← Credenciales de producción
│
└── dataholics.com.mx/
    └── resolve.dataholics.com.mx/   ← Document Root del dominio
        ├── index.php
        ├── index.html    (Login)
        ├── client.html
        ├── servicedesk.html
        ├── admin.html
        ├── .htaccess
        └── setup_admin.php  ← ELIMINAR tras usarlo
```

---

## Paso 1: Configurar la Base de Datos en cPanel

La base de datos ya está creada: `noodluis_resolve`
Las tablas ya están creadas vía phpMyAdmin.

✅ **No necesitas hacer nada más en este paso.**

---

## Paso 2: Configurar el `.env` de Producción

Antes de subir, edita `api/.env` y actualiza:

```bash
CI_ENVIRONMENT = production

app.baseURL = 'https://resolve.dataholics.com.mx/'

database.default.hostname = localhost
database.default.database = noodluis_resolve
database.default.username = noodluis_DEV_resolve
database.default.password = +wxM$&RkY^Ye
database.default.DBDriver = MySQLi
database.default.port     = 3306
```

---

## Paso 3: Subir archivos vía FTP

**Credenciales FTP:**
- Host: `dataholics.com.mx`
- Usuario: `DEV_resolve@dataholics.com.mx`
- Password: `lOv[M{eM4^M}` ← (provista por el usuario)
- Puerto: `21`

### Destinos:

| Local | Servidor |
|---|---|
| `proyectos/Dataholics Resolve/api/` | `/home1/noodluis/api_resolve/` |
| `proyectos/Dataholics Resolve/public_html/` | `/home1/noodluis/dataholics.com.mx/resolve.dataholics.com.mx/` |
| `proyectos/Dataholics Resolve/setup_admin.php` | `/home1/noodluis/dataholics.com.mx/resolve.dataholics.com.mx/` |

> [!WARNING]
> La carpeta `api_resolve/` debe ir EN `/home1/noodluis/` directamente,
> NUNCA dentro de `resolve.dataholics.com.mx/` para mantener el backend privado.

---

## Paso 4: Verificar Permisos de Carpetas

En el File Manager de cPanel, verifica:

| Carpeta | Permiso |
|---|---|
| `api_resolve/writable/` | `755` |
| `api_resolve/writable/cache/` | `755` |
| `api_resolve/writable/logs/` | `755` |
| `api_resolve/.env` | `600` |

---

## Paso 5: Crear el Primer Usuario Admin

1. Sube `setup_admin.php` al Document Root.
2. Edita las líneas de configuración del script:
   - `ADMIN_NAME`
   - `ADMIN_EMAIL`  
   - `ADMIN_PASS` ← Usa una contraseña fuerte
3. Accede desde tu IP local al servidor (o corre via SSH):
   ```
   https://resolve.dataholics.com.mx/setup_admin.php
   ```
   > El script está protegido: solo funciona desde `localhost` o CLI.
   > En Site5, usa el **Terminal de cPanel** para ejecutarlo:
   ```bash
   php /home1/noodluis/dataholics.com.mx/resolve.dataholics.com.mx/setup_admin.php
   ```
4. **Elimina el archivo inmediatamente tras usarlo.**

---

## Paso 6: Verificación Final

Visita `https://resolve.dataholics.com.mx` y verifica:

- [ ] La pantalla de Login carga correctamente.
- [ ] Puedes autenticarte con el usuario Admin creado.
- [ ] El Dashboard de ServiceDesk es visible.
- [ ] El Panel Admin carga la lista de empresas y usuarios.
- [ ] Un cliente no puede ver tickets de otra empresa (aislamiento de tenant).
- [ ] Las cookies de sesión son `HttpOnly` (verificar en DevTools → Application → Cookies).

---

> [!CAUTION]
> **NO subas el archivo `.env` al repositorio de Git.**
> **ELIMINA `setup_admin.php` del servidor tras usarlo.**
