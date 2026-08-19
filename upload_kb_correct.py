import ftplib

FTP_HOST = 'ftp.dataholics.com.mx'
FTP_USER = 'DEV_resolve@dataholics.com.mx'
FTP_PASS = 'Wwl[rV9m+T{+'
LOCAL    = r'C:\Users\luisc\Documents\Dataholics\Dataholics Guidelines\proyectos\Dataholics Resolve\api\app\Controllers\KbController.php'

TARGETS = [
    '/home1/noodluis/resolve.dataholics.com.mx/api/app/Controllers/KbController.php',
    '/home1/noodluis/api_resolve/app/Controllers/KbController.php',
]

ftp = ftplib.FTP()
ftp.connect(FTP_HOST, 21, timeout=60)
ftp.login(FTP_USER, FTP_PASS)
ftp.set_pasv(True)
print('Connected.')

for remote in TARGETS:
    try:
        with open(LOCAL, 'rb') as f:
            ftp.storbinary('STOR ' + remote, f)
        print(f'OK: {remote}')
    except Exception as e:
        print(f'FAILED: {remote} -> {e}')

ftp.quit()
print('Done.')
