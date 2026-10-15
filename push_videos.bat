@echo off
echo ===================================================
echo   BSD BIM TRAINING CENTER - PUSH TO GITHUB
echo ===================================================
echo Adding all updated website files and videos...
git add .
set /p commit_msg="Enter commit message (or press ENTER for default): "
if "%commit_msg%"=="" set commit_msg="Upload new BIM training videos and content"
git commit -m "%commit_msg%"
echo Pushing to GitHub repository...
git push origin main
echo.
echo ===================================================
echo   SUCCESS! Your updates have been pushed to Git.
echo ===================================================
pause
