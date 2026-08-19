"""
Dataholics Resolve - SFTP Upload Script
Usa SSH/SFTP en lugar de FTP (puerto 22).
Requiere: pip install paramiko
"""
import os
import sys

try:
    import paramiko
except ImportError:
    print("Instalando paramiko (libreria SFTP)...")
    os.system(f"{sys.executable} -m pip install paramiko")
    import paramiko

# ---- SFTP CONFIG ----
SFTP_HOST = "dataholics.com.mx"
SFTP_PORT = 22
SFTP_USER = "DEV_resolve"        # Usuario SSH de cPanel (sin @dominio)
SFTP_PASS = "lOv[M{eM4^M}"

# ---- LOCAL PATHS ----
PROJECT_ROOT = r"c:\Users\luisc\Documents\Dataholics\Dataholics Guidelines\proyectos\Dataholics Resolve"
LOCAL_API     = os.path.join(PROJECT_ROOT, "api")
LOCAL_PUBLIC  = os.path.join(PROJECT_ROOT, "public_html")
LOCAL_SETUP   = os.path.join(PROJECT_ROOT, "setup_admin.php")

# ---- REMOTE PATHS en Site5 ----
REMOTE_API    = "/home1/noodluis/api_resolve"
REMOTE_PUBLIC = "/home1/noodluis/dataholics.com.mx/resolve.dataholics.com.mx"

EXCLUDE_DIRS  = {'.git', '__pycache__', 'node_modules', '.idea'}
EXCLUDE_FILES = {'.DS_Store', 'Thumbs.db'}

uploaded = 0
errors   = 0


def ensure_remote_dir(sftp, path):
    """Crea directorio remoto recursivamente si no existe."""
    parts = path.replace("\\", "/").split("/")
    current = ""
    for part in parts:
        if not part:
            current = "/"
            continue
        current = (current.rstrip("/") + "/" + part) if current != "/" else "/" + part
        try:
            sftp.stat(current)
        except FileNotFoundError:
            try:
                sftp.mkdir(current)
            except Exception:
                pass


def upload_dir(sftp, local_dir, remote_dir):
    global uploaded, errors
    ensure_remote_dir(sftp, remote_dir)
    for item in sorted(os.listdir(local_dir)):
        if item in EXCLUDE_DIRS or item in EXCLUDE_FILES:
            continue
        local_path  = os.path.join(local_dir, item)
        remote_path = remote_dir.rstrip("/") + "/" + item
        if os.path.isdir(local_path):
            upload_dir(sftp, local_path, remote_path)
        else:
            size_kb = os.path.getsize(local_path) / 1024
            try:
                sftp.put(local_path, remote_path)
                print(f"  ✅ {remote_path}  ({size_kb:.1f} KB)")
                uploaded += 1
            except Exception as e:
                print(f"  ❌ ERROR {remote_path}: {e}")
                errors += 1


def main():
    print("=" * 60)
    print("  Dataholics Resolve — SFTP Upload")
    print(f"  Host: {SFTP_HOST}:{SFTP_PORT}")
    print("=" * 60)

    # Conectar por SSH/SFTP
    ssh = paramiko.SSHClient()
    ssh.set_missing_host_key_policy(paramiko.AutoAddPolicy())

    try:
        print(f"\n🔗 Conectando por SFTP a {SFTP_HOST}...")
        ssh.connect(SFTP_HOST, port=SFTP_PORT, username=SFTP_USER, password=SFTP_PASS, timeout=30)
        sftp = ssh.open_sftp()
        print(f"✅ Conectado como {SFTP_USER}\n")
    except Exception as e:
        print(f"\n❌ Error SFTP: {e}")
        print("\n💡 Site5 puede requerir el usuario principal de cPanel (noodluis).")
        print("   Verifica en cPanel → SSH Access qué usuario está habilitado.")
        sys.exit(1)

    # Subir API
    print(f"\n📦 Subiendo API → {REMOTE_API}")
    print("-" * 50)
    upload_dir(sftp, LOCAL_API, REMOTE_API)

    # Subir public_html
    print(f"\n🌐 Subiendo Frontend → {REMOTE_PUBLIC}")
    print("-" * 50)
    upload_dir(sftp, LOCAL_PUBLIC, REMOTE_PUBLIC)

    # Subir setup_admin.php
    if os.path.exists(LOCAL_SETUP):
        remote_setup = f"{REMOTE_PUBLIC}/setup_admin.php"
        sftp.put(LOCAL_SETUP, remote_setup)
        print(f"\n  ✅ setup_admin.php")
        uploaded += 1

    sftp.close()
    ssh.close()

    print("\n" + "=" * 60)
    print(f"  ✅ UPLOAD COMPLETADO — {uploaded} archivos subidos, {errors} errores")
    print("=" * 60)
    print("\n📋 Próximos pasos:")
    print("  1. En cPanel Terminal ejecuta:")
    print(f"     php {REMOTE_PUBLIC}/setup_admin.php")
    print("  2. Elimina setup_admin.php del servidor.")
    print("  3. Visita: https://resolve.dataholics.com.mx")


if __name__ == "__main__":
    main()
