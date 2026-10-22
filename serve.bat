@echo off
cd /d "%~dp0"
echo Starting Volt Gym at http://localhost:8080
start "" "http://localhost:8080"
"C:\wamp64\bin\php\php8.2.29\php.exe" -S localhost:8080 -t "%~dp0." "%~dp0router.php"
