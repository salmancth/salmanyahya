# Manual Deploy Script
param(
    [string]$Message = "Deployment update"
)

Write-Host "Starting deployment process..." -ForegroundColor Green

# 1. Commit changes
git add .
git commit -m $Message

# 2. Push to GitHub
git push origin main

Write-Host "✅ Changes pushed to GitHub" -ForegroundColor Green
Write-Host "GitHub Actions will automatically deploy to cPanel" -ForegroundColor Yellow
Write-Host "Monitor deployment at: https://github.com/salmancth/salmanyahya/actions" -ForegroundColor Cyan