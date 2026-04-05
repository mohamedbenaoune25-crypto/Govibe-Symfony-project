$files = @(
    "templates/security/login.html.twig",
    "templates/registration/register.html.twig",
    "templates/security/forgot_password.html.twig",
    "templates/security/reset_password.html.twig"
)

$oldRoot = @"
        :root {
            --bg-color: #030712;
            --card-bg: #0B1120;
            --card-border: rgba(255, 255, 255, 0.05);
            --input-bg: #050810;
            --input-border: rgba(255, 255, 255, 0.1);
            --primary-btn: #2563EB;
            --primary-hover: #1D4ED8;
            --primary-glow: rgba(37, 99, 235, 0.2);
            --text-main: #F8FAFC;
            --text-muted: #64748B;
            --accent: #38BDF8;
        }
"@

$newRoot = @"
        /* Design GoVibe - Voyage (Emerald) */
        :root {
            --bg-color: #F5F3E7;
            --card-bg: rgba(255, 255, 255, 0.85);
            --card-border: rgba(80, 200, 120, 0.2);
            --input-bg: #FFFFFF;
            --input-border: rgba(80, 200, 120, 0.3);
            --primary-btn: #50C878;
            --primary-hover: #2E8B57;
            --primary-glow: rgba(80, 200, 120, 0.4);
            --text-main: #013220;
            --text-muted: #64748B;
            --accent: #2E8B57;
        }
"@

$oldGrid = @"
    <div class="cyber-grid"></div>
    <div class="ambient-light"></div>
    <div class="ambient-light-2"></div>
"@

$newGrid = @"
    <div style="position:fixed; top:0; left:0; width:100%; height:100%; z-index:0; pointer-events:none; overflow:hidden;">
        <i class="bi bi-airplane-engines-fill" style="position:absolute; opacity:0.05; color:#013220; font-size:120px; top:10%; left:10%; animation: floatElement 15s ease-in-out infinite alternate;"></i>
        <i class="bi bi-globe-americas" style="position:absolute; opacity:0.05; color:#013220; font-size:300px; bottom:-50px; right:-50px; animation: floatElement 20s ease-in-out infinite alternate;"></i>
        <style>@keyframes floatElement { 0% { transform: translateY(0) rotate(0); } 100% { transform: translateY(-30px) rotate(5deg); } }</style>
    </div>
"@

$oldBrand = 'background: linear-gradient(135deg, var(--primary-btn), #0ea5e9);'
$newBrand = 'background: linear-gradient(135deg, var(--primary-btn), #2E8B57);'

$oldBoxShadow = 'box-shadow: 0 30px 60px -15px rgba(0, 0, 0, 0.8), inset 0 1px 0 rgba(255,255,255,0.05);'
$newBoxShadow = 'box-shadow: 0 30px 60px -15px rgba(1, 50, 32, 0.15); border-radius: 24px; backdrop-filter: blur(16px); -webkit-backdrop-filter: blur(16px);'


foreach ($file in $files) {
    if (Test-Path $file) {
        $content = Get-Content -Raw $file
        
        $content = $content.Replace($oldRoot, $newRoot)
        $content = $content.Replace($oldGrid, $newGrid)
        $content = $content.Replace($oldBrand, $newBrand)
        $content = $content.Replace($oldBoxShadow, $newBoxShadow)

        $content = $content -replace "color: #fff;", "color: var(--text-main);"
        $content = $content -replace "color: #cbd5e1;", "color: var(--text-main);"
        $content = $content -replace "background-image:.*?;", "background-image: radial-gradient(circle at 15% 50%, rgba(80, 200, 120, 0.1) 0%, transparent 50%), radial-gradient(circle at 85% 30%, rgba(1, 50, 32, 0.05) 0%, transparent 50%);"

        Set-Content $file -Value $content -Encoding UTF8
    }
}
Write-Output "UI updated to Travel Theme!"
