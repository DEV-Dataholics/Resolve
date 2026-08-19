"""
Dataholics Resolve — Deploy single agent file to /home1/noodluis/agentes/
Usage: python deploy_agent.py [agent-filename.agent.md]
Default: uploads all *.agent.md files from the local agentes/ folder.
"""
import ftplib
import os
import sys
import glob

# ---- FTP CONFIG ----
FTP_HOST = "ftp.dataholics.com.mx"
FTP_USER = "DEV_resolve@dataholics.com.mx"
FTP_PASS = "Wwl[rV9m+T{+"
FTP_PORT = 21

# ---- PATHS ----
GUIDELINES_ROOT = r"c:\Users\luisc\Documents\Dataholics\Dataholics Guidelines"
LOCAL_AGENTS_DIR = os.path.join(GUIDELINES_ROOT, "agentes")
REMOTE_AGENTS_DIR = "/agentes"   # /home1/noodluis/agentes/ relative to FTP root


def connect_ftp():
    """Connect via FTPS, fall back to plain FTP."""
    ftp = None
    try:
        print("Connecting via FTPS (TLS)...")
        ftp = ftplib.FTP_TLS()
        ftp.connect(FTP_HOST, FTP_PORT, timeout=60)
        ftp.auth()
        ftp.login(FTP_USER, FTP_PASS)
        ftp.prot_p()
        ftp.set_pasv(True)
        print(f"Connected via FTPS as {FTP_USER}\n")
    except Exception as e:
        print(f"FTPS failed ({e}), retrying with plain FTP...")
        try:
            ftp = ftplib.FTP()
            ftp.connect(FTP_HOST, FTP_PORT, timeout=60)
            ftp.login(FTP_USER, FTP_PASS)
            ftp.set_pasv(True)
            print(f"Connected via FTP as {FTP_USER}\n")
        except Exception as e2:
            print(f"FTP connection failed: {e2}")
            sys.exit(1)
    return ftp


def ensure_remote_dir(ftp, path):
    """Create remote directory if it doesn't exist."""
    try:
        ftp.mkd(path)
        print(f"  Created remote dir: {path}")
    except ftplib.error_perm:
        pass  # already exists


def upload_file(ftp, local_path, remote_path):
    """Upload a single file."""
    size_kb = os.path.getsize(local_path) / 1024
    with open(local_path, 'rb') as f:
        ftp.storbinary(f'STOR {remote_path}', f)
    print(f"  OK  {os.path.basename(local_path)}  ({size_kb:.1f} KB)  →  {remote_path}")


def main():
    print("=" * 60)
    print("  Dataholics Resolve — Agent Deploy")
    print(f"  Target: {FTP_HOST}{REMOTE_AGENTS_DIR}/")
    print("=" * 60)

    # Determine which files to upload
    if len(sys.argv) > 1:
        filenames = sys.argv[1:]
        local_files = [os.path.join(LOCAL_AGENTS_DIR, f) for f in filenames]
    else:
        local_files = glob.glob(os.path.join(LOCAL_AGENTS_DIR, "*.agent.md"))

    if not local_files:
        print("\nNo agent files found to upload.")
        sys.exit(0)

    print(f"\nFiles to upload ({len(local_files)}):")
    for f in local_files:
        print(f"  - {os.path.basename(f)}")

    ftp = connect_ftp()
    ensure_remote_dir(ftp, REMOTE_AGENTS_DIR)

    print("\nUploading...")
    for local_path in local_files:
        if not os.path.exists(local_path):
            print(f"  SKIP  {local_path} (not found)")
            continue
        filename = os.path.basename(local_path)
        remote_path = f"{REMOTE_AGENTS_DIR}/{filename}"
        try:
            upload_file(ftp, local_path, remote_path)
        except Exception as e:
            print(f"  ERROR  {filename}: {e}")

    ftp.quit()

    print("\n" + "=" * 60)
    print("  DONE")
    print("=" * 60)
    print(f"\nAgents are live at: https://resolve.dataholics.com.mx/agentes.html")
    print()


if __name__ == "__main__":
    main()
