$php = (Get-Command php).Source
& $php -c "$PSScriptRoot\php.ini" -S 127.0.0.1:8080 -t "$PSScriptRoot\public"

