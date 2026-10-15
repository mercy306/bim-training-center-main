@echo off
echo ===================================================
echo   PUSHING TO GITHUB: mercy306/bim-training-center-main
echo ===================================================
cd /d "%~dp0"
git add .
git commit -m "Update BIM Training Center"
echo Pushing to GitHub...
git push -u origin main
echo ===================================================
pause
