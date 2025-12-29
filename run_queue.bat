@echo off
cd /d C:\Users\thean\Herd\jaffa
REM Start default queue worker
start "Queue Worker - Default" php artisan queue:work --queue=default --sleep=3 --tries=3
REM Start calculations queue worker (for slow monthly summary jobs)
start "Queue Worker - Calculations" php artisan queue:work --queue=calculations --sleep=3 --tries=3 --timeout=300
pause