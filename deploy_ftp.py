"""
Dataholics Resolve - FTP Upload Script
Sube api/ a /home1/noodluis/api_resolve/
Sube public_html/ a /home1/noodluis/dataholics.com.mx/resolve.dataholics.com.mx/
"""
import ftplib
import os
import sys

# ---- FTP CONFIG ----
FTP_HOST = "ftp.dataholics.com.mx"
FTP_USER = "DEV_resolve@dataholics.com.mx"
FTP_PASS = "Wwl[rV9m+T{+"
FTP_PORT = 21

# ---- LOCAL PATHS ----
PROJECT_ROOT = r"c:\Users\luisc\Documents\Dataholics\Dataholics Guidelines\proyectos\Dataholics Resolve"
LOCAL_API     = os.path.join(PROJECT_ROOT, "api")
LOCAL_PUBLIC  = os.path.join(PROJECT_ROOT, "public_html")
LOCAL_SETUP   = os.path.join(PROJECT_ROOT, "setup_admin.php")

# ---- REMOTE PATHS ----
# DEV_resolve FTP root = /home1/noodluis/resolve.dataholics.com.mx/
# Usamos rutas relativas al FTP root del usuario
REMOTE_API    = "/api"        # Queda en resolve.../api/ (protegido por .htaccess)
REMOTE_PUBLIC = "/"           # Raiz del dominio

# ---- EXCLUSIONES ----
EXCLUDE_DIRS  = {'.git', '__pycache__', 'node_modules', '.idea', '.vscode'}
EXCLUDE_FILES = {'.DS_Store', 'Thumbs.db'}


def ensure_remote_dir(ftp, remote_path):
    """Crea el directorio remoto si no existe (recursivo)."""
    parts = remote_path.split('/')
    current = ""
    for part in parts:
        if not part:
            current = "/"
            continue
        current = current.rstrip('/') + '/' + part
        try:
            ftp.mkd(current)
        except ftplib.error_perm:
            pass  # ya existe


def upload_dir(ftp, local_dir, remote_dir, label=""):
    """Sube un directorio local completo al servidor FTP."""
    ensure_remote_dir(ftp, remote_dir)
    items = sorted(os.listdir(local_dir))
    for item in items:
        if item in EXCLUDE_DIRS or item in EXCLUDE_FILES:
            continue
        local_path  = os.path.join(local_dir, item)
        remote_path = remote_dir.rstrip('/') + '/' + item

        if os.path.isdir(local_path):
            upload_dir(ftp, local_path, remote_path, label)
        else:
            size_kb = os.path.getsize(local_path) / 1024
            try:
                with open(local_path, 'rb') as f:
                    ftp.storbinary(f'STOR {remote_path}', f)
                print(f"  OK {label}{remote_path.replace(remote_dir, '')}  ({size_kb:.1f} KB)")
            except Exception as e:
                print(f"  ERROR {remote_path}: {e}")


def main():
    print("=" * 60)
    print("  Dataholics Resolve — FTP Upload")
    print(f"  Host: {FTP_HOST}")
    print("=" * 60)

    ftp = None

    # Intentar primero con FTPS (puerto 21 con TLS implicito)
    try:
        print("\n--- Intentando FTPS (TLS)...")
        ftp = ftplib.FTP_TLS()
        ftp.connect(FTP_HOST, FTP_PORT, timeout=60)
        ftp.auth()
        ftp.login(FTP_USER, FTP_PASS)
        ftp.prot_p()  # Proteccion de datos con TLS
        ftp.set_pasv(True)
        print(f"\nOK: Conectado con FTPS como {FTP_USER}\n")
    except Exception as e_tls:
        print(f"  Aviso: FTPS fallo: {e_tls}")
        print("  Retrying: Intentando FTP estandar...")
        try:
            ftp = ftplib.FTP()
            ftp.connect(FTP_HOST, FTP_PORT, timeout=60)
            ftp.login(FTP_USER, FTP_PASS)
            ftp.set_pasv(True)
            print(f"\nOK: Conectado con FTP como {FTP_USER}\n")
        except Exception as e_ftp:
            print(f"\nError de conexion FTP: {e_ftp}")
            print("\nPosibles causas:")
            print("   - El servidor bloquea conexiones FTP externas")
            print("   - Firewall local bloqueando el puerto 21")
            print("   - Credenciales incorrectas")
            sys.exit(1)

    # ---- 1. Subir API (Backend privado) ----
    print(f"\nSubiendo API -> {REMOTE_API}")
    print("-" * 50)
    upload_dir(ftp, LOCAL_API, REMOTE_API, "api/")

    # ---- 2. Subir public_html (Frontend) ----
    print(f"\nSubiendo public_html -> {REMOTE_PUBLIC}")
    print("-" * 50)
    upload_dir(ftp, LOCAL_PUBLIC, REMOTE_PUBLIC, "public/")

    # ---- 3. Subir setup_admin.php ----
    if os.path.exists(LOCAL_SETUP):
        remote_setup = f"{REMOTE_PUBLIC}/setup_admin.php"
        with open(LOCAL_SETUP, 'rb') as f:
            ftp.storbinary(f'STOR {remote_setup}', f)
        print(f"\n  OK: public/setup_admin.php")

    ftp.quit()

    print("\n" + "=" * 60)
    print("  CARGA COMPLETADA")
    print("=" * 60)
    print("\nSiguientes pasos:")
    print("  1. Ve al Terminal de cPanel y ejecuta:")
    print(f"     php {REMOTE_PUBLIC}/setup_admin.php")
    print("  2. Elimina setup_admin.php del servidor.")
    print("  3. Visita: https://resolve.dataholics.com.mx")
    print()


if __name__ == "__main__":
    main()
