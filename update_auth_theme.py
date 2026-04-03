import os
import re

CSS = """<style>
    :root {
        --primary-emerald: #10B981;
        --dark-green: #064E3B;
        --light-green: #D1FAE5;
        --bg-main: #020617; /* Very dark blue/black */
        --card-bg: rgba(6, 78, 59, 0.15); /* Dark green glass */
        --card-border: rgba(16, 185, 129, 0.3);
        --input-bg: rgba(0, 0, 0, 0.4);
        --input-border: rgba(16, 185, 129, 0.4);
        --primary-btn: #10B981;
        --primary-hover: #059669;
        --primary-glow: rgba(16, 185, 129, 0.3);
        --text-main: #F8FAFC;
        --text-muted: #94A3B8;
        --accent: #10B981;
    }

    body {
        font-family: 'Inter', sans-serif;
        background: radial-gradient(circle at top left, #064E3B 0%, #111827 50%, #000000 100%);
        min-height: 100vh;
        display: flex;
        flex-direction: column;
        overflow-x: hidden;
        color: var(--text-main);
        position: relative;
    }

    /* Animated Cyber Grid Background */
    .cyber-grid {
        position: fixed;
        top: 0; left: 0; width: 200%; height: 200%;
        background-size: 50px 50px;
        background-image:
            linear-gradient(to right, rgba(16, 185, 129, 0.05) 1px, transparent 1px),
            linear-gradient(to bottom, rgba(16, 185, 129, 0.05) 1px, transparent 1px);
        transform: perspective(600px) rotateX(60deg) translateY(-100px) translateZ(-250px);
        animation: gridMove 20s linear infinite;
        z-index: 0;
        pointer-events: none;
    }

    @keyframes gridMove {
        0% { transform: perspective(600px) rotateX(60deg) translateY(0) translateZ(-250px); }
        100% { transform: perspective(600px) rotateX(60deg) translateY(50px) translateZ(-250px); }
    }

    /* Glowing Orbs */
    .glow-orb {
        position: fixed;
        border-radius: 50%;
        filter: blur(80px);
        opacity: 0.4;
        z-index: 0;
        animation: orbFloat 10s infinite alternate;
        pointer-events: none;
    }
    .orb-1 { width: 400px; height: 400px; background: #10B981; top: 10%; left: 10%; }
    .orb-2 { width: 300px; height: 300px; background: #064E3B; bottom: 20%; right: 10%; animation-delay: -5s; }

    @keyframes orbFloat {
        0% { transform: translate(0, 0) scale(1); }
        100% { transform: translate(50px, 50px) scale(1.1); }
    }

    .auth-wrapper {
        flex: 1;
        display: flex;
        align-items: center;
        justify-content: center;
        padding: 4rem 1rem;
        position: relative;
        z-index: 10;
    }

    /* Corporate Card - Glassmorphism */
    .auth-card {
        background: var(--card-bg);
        backdrop-filter: blur(20px);
        -webkit-backdrop-filter: blur(20px);
        border: 1px solid var(--card-border);
        border-radius: 20px;
        padding: 3rem;
        width: 100%;
        max-width: 480px;
        box-shadow: 0 30px 60px -15px rgba(0, 0, 0, 0.8), inset 0 1px 0 rgba(255,255,255,0.1);
        transform: translateY(30px);
        opacity: 0;
        animation: slideUpFade 0.6s cubic-bezier(0.16, 1, 0.3, 1) forwards;
    }
    
    .register-card { max-width: 550px; }

    @keyframes slideUpFade {
        to { transform: translateY(0); opacity: 1; }
    }

    .auth-header {
        text-align: center;
        margin-bottom: 2.5rem;
    }

    .auth-title {
        color: #fff;
        font-family: 'Outfit', sans-serif;
        font-weight: 700;
        font-size: 2.25rem;
        margin-bottom: 0.5rem;
        letter-spacing: -0.02em;
        text-shadow: 0 0 15px var(--primary-glow);
    }

    .auth-subtitle {
        color: var(--text-muted);
        font-size: 1rem;
        font-weight: 400;
        line-height: 1.5;
    }

    /* Forms */
    .form-group {
        margin-bottom: 1.5rem;
    }

    .form-row {
        display: flex;
        gap: 15px;
        margin-bottom: 0; /* Handled by child form-group */
    }
    .form-row .form-group { width: 50%; }

    .form-group label {
        display: block;
        font-size: 0.8rem;
        font-weight: 600;
        color: var(--light-green);
        margin-bottom: 0.5rem;
        text-transform: uppercase;
        letter-spacing: 0.5px;
    }

    .form-group input, .form-control {
        width: 100%;
        padding: 0.875rem 1rem;
        background: var(--input-bg);
        border: 1px solid var(--input-border);
        border-radius: 12px;
        color: #fff;
        font-size: 0.95rem;
        transition: all 0.3s ease;
        outline: none;
    }

    .form-group input::placeholder, .form-control::placeholder {
        color: var(--text-muted);
    }

    .form-group input:focus, .form-control:focus {
        border-color: var(--primary-btn);
        box-shadow: 0 0 0 4px var(--primary-glow);
        background: rgba(0, 0, 0, 0.6);
    }

    /* Role selector for register */
    .role-selector {
        display: flex; gap: 20px; align-items: center; background: var(--input-bg); padding: 10px 16px; border-radius: 12px; border: 1px solid var(--input-border);
    }
    .form-check { display: flex; align-items: center; gap: 8px; cursor: pointer; margin-bottom: 0; }
    .form-check input { cursor: pointer; accent-color: var(--primary-btn); width: 16px; height: 16px; }
    .form-check label { margin-bottom: 0; font-size: 0.85rem; text-transform: none; color: #fff; font-weight: 500; letter-spacing: 0; }

    /* Button */
    .auth-btn {
        width: 100%;
        padding: 1rem;
        background: linear-gradient(135deg, var(--primary-emerald), var(--primary-hover));
        border: none;
        border-radius: 12px;
        color: white;
        font-weight: 600;
        font-size: 1rem;
        cursor: pointer;
        transition: all 0.3s ease;
        margin-top: 1rem;
        box-shadow: 0 10px 20px -10px var(--primary-emerald);
    }

    .auth-btn:hover {
        transform: translateY(-2px);
        box-shadow: 0 15px 25px -10px var(--primary-emerald);
        background: linear-gradient(135deg, var(--primary-hover), var(--primary-emerald));
    }

    /* Links */
    .auth-link {
        color: var(--accent);
        text-decoration: none;
        font-weight: 500;
        transition: all 0.2s;
    }
    .auth-link:hover {
        color: #fff;
        text-decoration: underline;
    }
    
    .forgot-link {
        display: block; text-align: right; font-size: 0.85rem; margin-top: -0.5rem; margin-bottom: 1.5rem;
    }

    .auth-footer {
        text-align: center;
        margin-top: 2rem;
        padding-top: 1.5rem;
        border-top: 1px solid var(--card-border);
        color: var(--text-muted);
        font-size: 0.9rem;
    }

    /* Messages & Errors */
    .error-box { background: rgba(220, 38, 38, 0.1); border: 1px solid rgba(220, 38, 38, 0.3); color: #fca5a5; padding: 12px; border-radius: 8px; font-size: 13px; margin-bottom: 20px; font-weight: 500; }
    .success-box { background: rgba(16, 185, 129, 0.1); border: 1px solid rgba(16, 185, 129, 0.3); color: #6ee7b7; padding: 12px; border-radius: 8px; font-size: 13px; margin-bottom: 20px; font-weight: 500; }
    .invalid-feedback { color: #fca5a5; font-size: 12px; margin-top: 6px; display: block; }
    ul.form-errors { list-style: none; margin-bottom: 15px; padding: 0; }
    ul.form-errors li { background: rgba(220, 38, 38, 0.1); border: 1px solid rgba(220, 38, 38, 0.3); color: #fca5a5; padding: 10px; border-radius: 8px; font-size: 12px; margin-bottom: 5px; }

    @media (max-width: 480px) {
        .auth-card { padding: 2rem 1.5rem; border-radius: 16px; }
        .form-row { flex-direction: column; gap: 0; }
        .form-row .form-group { width: 100%; }
        .auth-title { font-size: 1.75rem; }
    }
</style>
"""


def process_file(filepath):
    if not os.path.exists(filepath):
        return
    with open(filepath, 'r', encoding='utf-8') as f:
        content = f.read()

    # Replace <style> block
    content = re.sub(r'<style>.*?</style>', CSS, content, flags=re.DOTALL)
    
    # Replace body with body including BG elements
    body_elements = """  <div class="cyber-grid"></div>
<div class="glow-orb orb-1"></div>
<div class="glow-orb orb-2"></div>

<div class="auth-wrapper">
    <div class="auth-card register-card">"""
    
    # Simple regex to fix auth-card structure if old one was used
    if 'class="auth-card' in content and 'register-card' not in content and 'register' in filepath:
        content = content.replace('class="auth-card"', 'class="auth-card register-card"')
    
    if '<div class="cyber-grid"></div>' not in content:
        content = content.replace('<div class="auth-wrapper">', f'<div class="cyber-grid"></div>\n    <div class="glow-orb orb-1"></div>\n    <div class="glow-orb orb-2"></div>\n    <div class="auth-wrapper">')

    # Ensure elements don't get duplicated
    content = re.sub(r'<div class="cyber-grid"></div>\s*<div class="cyber-grid"></div>', '<div class="cyber-grid"></div>', content)
    
    with open(filepath, 'w', encoding='utf-8') as f:
        f.write(content)


files = [
    'templates/security/login.html.twig',
    'templates/registration/register.html.twig',
    'templates/security/forgot_password.html.twig',
    'templates/security/reset_password.html.twig'
]

for file in files:
    process_file(file)

print("Styles updated successfully!")