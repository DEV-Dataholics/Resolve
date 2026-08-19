import ftplib
import urllib.request
import urllib.error
import json
import os

FTP_HOST = "ftp.dataholics.com.mx"
FTP_USER = "DEV_resolve@dataholics.com.mx"
FTP_PASS = "Wwl[rV9m+T{+"

LOCAL_DIR = r"C:\Users\luisc\Documents\Dataholics\Dataholics Guidelines\knowledgebase"
LOCAL_WARHORSE_PLAYBOOK = os.path.join(LOCAL_DIR, "proyectos", "Warhorse", "SITE5_PLAYBOOK.md")
LOCAL_GUIDELINE = os.path.join(LOCAL_DIR, "Site5_Shared_Hosting_Deployment_Guideline.md")
LOCAL_SCRIPT = r"C:\Users\luisc\Documents\Dataholics\Dataholics Guidelines\proyectos\Dataholics Resolve\insert_kb.php"

def ftp_connect():
    ftp = ftplib.FTP()
    ftp.connect(FTP_HOST, 21, timeout=30)
    ftp.login(FTP_USER, FTP_PASS)
    ftp.set_pasv(True)
    return ftp

def main():
    uploads = [
        (LOCAL_WARHORSE_PLAYBOOK, "SITE5_PLAYBOOK.md"),
        (LOCAL_GUIDELINE, "Site5_Shared_Hosting_Deployment_Guideline.md"),
        (LOCAL_SCRIPT, "insert_kb.php")
    ]

    # Verify local files exist before connecting
    for local_path, _ in uploads:
        if not os.path.exists(local_path):
            print(f"ERROR: Local file not found: {local_path}")
            return

    print("Connecting to FTP...")
    ftp = ftp_connect()
    print("Connected.")

    for local_path, remote_name in uploads:
        print(f"Uploading {local_path} -> {remote_name}...")
        with open(local_path, "rb") as f:
            ftp.storbinary(f"STOR {remote_name}", f)
        print(f"  OK: {remote_name}")

    print("All uploads complete.")
    ftp.quit()

    # Hit the URL to execute database inserts
    url = "https://resolve.dataholics.com.mx/insert_kb.php"
    print(f"\nExecuting database inserts via: {url}")
    req = urllib.request.Request(url, headers={
        "User-Agent": "Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36",
        "Accept": "application/json, text/plain, */*",
        "Accept-Language": "en-US,en;q=0.9",
    })

    success = False
    try:
        with urllib.request.urlopen(req, timeout=30) as r:
            res = r.read().decode("utf-8")
            print("Response:", res)
            try:
                data = json.loads(res)
                if data.get("ok"):
                    print("\nSUCCESS: KB articles inserted into database.")
                    success = True
                else:
                    print("\nWARNING: PHP returned ok=false:", data)
            except json.JSONDecodeError:
                print("\nWARNING: Could not parse JSON response.")
    except urllib.error.HTTPError as e:
        body = e.read().decode("utf-8", errors="replace")
        print(f"HTTP Error {e.code} {e.reason}")
        print("Body:", body[:500])
    except Exception as e:
        print("HTTP execution failed:", e)

    # Clean up remote files
    print("\nCleaning up remote files...")
    ftp = ftp_connect()
    for _, remote_name in uploads:
        try:
            ftp.delete(remote_name)
            print(f"  Deleted: {remote_name}")
        except Exception as e:
            print(f"  Could not delete {remote_name}: {e}")
    ftp.quit()

    if success:
        print("\nDone! Knowledgebase articles are now live on resolve.dataholics.com.mx")
    else:
        print("\nUpload completed but DB insert may have failed. Check the response above.")

if __name__ == "__main__":
    main()
