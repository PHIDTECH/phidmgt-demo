@echo off
echo Starting PhidTech Server...
echo.
echo Access the site at: http://localhost:8000
echo Press Ctrl+C to stop the server
echo.
cd /d "%~dp0"
"C:\xampp\php\php.exe" -S localhost:8000
pause
