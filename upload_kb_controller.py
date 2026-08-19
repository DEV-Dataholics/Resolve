import ftplib

FTP_HOST = 'ftp.dataholics.com.mx'
FTP_USER = 'DEV_resolve@dataholics.com.mx'
FTP_PASS = 'Wwl[rV9m+T{+'
LOCAL    = r'C:\Users\luisc\Documents\Dataholics\Dataholics Guidelines\proyectos\Dataholics Resolve\api\app\Controllers\KbController.php'
REMOTE   = '/api/app/Controllers/KbController.php'

ftp = None
connected = False

try:
    ftp = ftplib.FTP_TLS()
    ftp.connect(FTP_HOST, 21, timeout=60)
    ftp.auth()
    ftp.login(FTP_USER, FTP_PASS)
    ftp.prot_p()
    ftp.set_pasv(True)
    print('Connected via FTPS')
    connected = True
except Exception as e:
    print(f'FTPS failed: {e}')

if not connected:
    try:
        ftp = ftplib.FTP()
        ftp.connect(FTP_HOST, 21, timeout=60)
        ftp.login(FTP_USER, FTP_PASS)
        ftp.set_pasv(True)
        print('Connected via plain FTP')
        connected = True
    except Exception as e:
        print(f'FTP also failed: {e}')

if connected:
    print('FTP root:', ftp.pwd())
    with open(LOCAL, 'rb') as f:
        ftp.storbinary('STOR ' + REMOTE, f)
    print(f'Uploaded KbController.php -> {REMOTE}')
    ftp.quit()
    print('Done.')
