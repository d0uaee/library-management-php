$config = "C:\xampp\htdocs\bibliotheque\project\mariadb-bibliotheque.ini"
$mysql = "C:\xampp\mysql\bin\mysqld.exe"

$existing = Get-CimInstance Win32_Process -Filter "name='mysqld.exe'" |
    Where-Object { $_.CommandLine -like "*mariadb-bibliotheque.ini*" }

if (-not $existing) {
    Start-Process -FilePath $mysql -ArgumentList "--defaults-file=$config","--standalone" -WindowStyle Hidden
    Start-Sleep -Seconds 3
}

$listening = Get-NetTCPConnection -LocalPort 3307 -State Listen -ErrorAction SilentlyContinue
if ($listening) {
    Write-Output "Bibliotheque DB is running on 127.0.0.1:3307"
} else {
    Write-Output "Bibliotheque DB failed to start"
    exit 1
}
