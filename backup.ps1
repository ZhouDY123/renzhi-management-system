$ErrorActionPreference = 'Stop'
$root = Split-Path -Parent $MyInvocation.MyCommand.Path
$backupDir = Join-Path $root 'data\backups'
New-Item -ItemType Directory -Path $backupDir -Force | Out-Null
$stamp = Get-Date -Format 'yyyyMMdd-HHmmss'
$target = Join-Path $backupDir "renzhi-$stamp.db"
$database = (Join-Path $root 'data\app.db') -replace '\\','/'
$targetSql = $target -replace '\\','/'
& php -c (Join-Path $root 'php.ini') -r "`$pdo=new PDO('sqlite:$database');`$pdo->exec('VACUUM INTO '.`$pdo->quote('$targetSql'));"
if ($LASTEXITCODE -ne 0) { throw '数据库备份失败' }
Write-Host "备份已生成：$target"
