# Corrected setup.ps1
Write-Host "Setting up CI/CD for Portfolio Website..." -ForegroundColor Green

# 1. Initialize Git if not already
if (-not (Test-Path .git)) {
    git init
    git add .
    git commit -m "Initial commit"
    Write-Host "Git repository initialized" -ForegroundColor Green
}

# 2. Check if GitHub remote is already added
$remotes = git remote -v
if ($remotes -notlike "*origin*") {
    git remote add origin https://github.com/salmancth/salmanyahya.git
    Write-Host "GitHub remote added" -ForegroundColor Green
}

# 3. Create basic HTML template
$htmlTemplate = @'
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Salman Yahya - Portfolio</title>
    <link rel="stylesheet" href="css/style.css">
</head>
<body>
    <header>
        <h1>Salman Yahya</h1>
        <nav>
            <a href="index.html">Home</a> |
            <a href="en/index.html">English Version</a>
        </nav>
    </header>
    <main>
        <section>
            <h2>Welcome to my portfolio</h2>
            <p>This site is currently under construction. Content coming soon!</p>
        </section>
    </main>
    <footer>
        <p>© 2025 Salman Yahya. All rights reserved.</p>
    </footer>
    <script src="js/main.js"></script>
</body>
</html>
'@

# 4. Check and create basic HTML files if they're empty
$htmlFiles = Get-ChildItem -Filter *.html -Recurse
foreach ($file in $htmlFiles) {
    $content = Get-Content $file.FullName -Raw
    if ([string]::IsNullOrWhiteSpace($content)) {
        $htmlTemplate | Out-File -FilePath $file.FullName -Encoding UTF8
        Write-Host "Added template to: $($file.FullName)" -ForegroundColor Yellow
    }
}

# 5. Create basic CSS if empty
if (Test-Path "css/style.css") {
    $cssContent = Get-Content "css/style.css" -Raw
    if ([string]::IsNullOrWhiteSpace($cssContent)) {
        @"
/* Main Stylesheet */
body {
    font-family: Arial, sans-serif;
    line-height: 1.6;
    margin: 0;
    padding: 20px;
    max-width: 1200px;
    margin: 0 auto;
    background-color: #f5f5f5;
}

header {
    background-color: #333;
    color: white;
    padding: 1rem;
    margin-bottom: 2rem;
}

nav a {
    color: white;
    margin-right: 15px;
    text-decoration: none;
}

nav a:hover {
    text-decoration: underline;
}

footer {
    margin-top: 3rem;
    padding-top: 1rem;
    border-top: 1px solid #ccc;
    text-align: center;
    color: #666;
}
"@ | Out-File -FilePath "css/style.css" -Encoding UTF8
        Write-Host "Created basic CSS file" -ForegroundColor Green
    }
} else {
    # Create CSS directory and file
    New-Item -ItemType Directory -Force -Path "css"
    @"
/* Main Stylesheet */
body { font-family: Arial, sans-serif; margin: 20px; }
"@ | Out-File -FilePath "css/style.css" -Encoding UTF8
    Write-Host "Created CSS file and directory" -ForegroundColor Green
}

# 6. Create basic JavaScript file
if (Test-Path "js/main.js") {
    $jsContent = Get-Content "js/main.js" -Raw
    if ([string]::IsNullOrWhiteSpace($jsContent)) {
        @"
// Main JavaScript file
console.log('Portfolio website loaded');

// Simple example function
function updateYear() {
    const yearSpan = document.getElementById('current-year');
    if (yearSpan) {
        yearSpan.textContent = new Date().getFullYear();
    }
}

// Run when page loads
document.addEventListener('DOMContentLoaded', function() {
    updateYear();
    console.log('JavaScript initialized');
});
"@ | Out-File -FilePath "js/main.js" -Encoding UTF8
        Write-Host "Created basic JavaScript file" -ForegroundColor Green
    }
} else {
    New-Item -ItemType Directory -Force -Path "js"
    "// JavaScript file" | Out-File -FilePath "js/main.js" -Encoding UTF8
}

Write-Host "`nSetup complete!" -ForegroundColor Green
Write-Host "=" * 50
Write-Host "NEXT STEPS:" -ForegroundColor Yellow
Write-Host "1. Generate SSH key for cPanel:" -ForegroundColor Cyan
Write-Host "   ssh-keygen -t rsa -b 4096 -f `"$env:USERPROFILE\.ssh\id_rsa_cpanel`" -N `"`"" -ForegroundColor White
Write-Host "2. Get public key: type `"$env:USERPROFILE\.ssh\id_rsa_cpanel.pub`"" -ForegroundColor White
Write-Host "3. Add public key to cPanel SSH Access" -ForegroundColor Cyan
Write-Host "4. Add private key as GitHub Secret named CPANEL_SSH_PRIVATE_KEY" -ForegroundColor Cyan
Write-Host "5. Push to GitHub: git push -u origin main" -ForegroundColor Cyan
Write-Host "=" * 50