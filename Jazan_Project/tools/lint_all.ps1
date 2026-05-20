$root = 'c:\xampp\htdocs\Jazan_Project'
Set-Location $root
Get-ChildItem -Recurse -Filter *.php | ForEach-Object {
    Write-Output "---- $($_.FullName)"
    & 'C:\xampp\php\php.exe' -l $_.FullName
}
