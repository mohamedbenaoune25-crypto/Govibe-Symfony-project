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
        --input-focus-border: #10B981;
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
        margin: 0;
    }

    .auth-wrapper {
        flex: 1;
        display: flex;
        align-items: center;
        justify-content: center;
        padding: 4rem 1rem;
        position: relative;
        z-index: 10;
        min-height: 100vh;
    }

    /* Cyber Grid */
    .cyber-grid {
        position: fixed;
        top: 0; left: 0; width: 200%; height: 200%;
        background-size: 50px 50px;
        background-image:
            linear-gradient(to right, rgba(16, 185, 129, 0.05) 1px, transparent 1px),
            linear-gradient(to bottom, rgba(16, 185, 129, 0.05) 1px, transparent 1px);
        transform: perspective(500px) rotateX(60deg) translateY(-100px) translateZ(-200px);
        animation: gridMove 20s linear infinite;
        z-index: 0;
        pointer-events: none;
    }

    @keyframes gridMove {
        0% { transform: perspective(500px) rotateX(60deg) translateY(0) translateZ(-200px); }
        100% { transform: perspective(500px) rotateX(60deg) translateY(50px) translateZ(-200px); }
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

    /* Auth Card */
    .auth-card {
        background: rgba(255, 255, 255, 0.05);
        backdrop-filter: blur(20px);
        -webkit-backdrop-filter: blur(20px);
        border: 1px solid rgba(255, 255, 255, 0.1);
        border-radius: 20px;
        padding: 3rem;
        width: 100%;
        max-width: 480px;
        box-shadow: 0 25px 50px -12px rgba(0, 0, 0, 0.5);
        transform: translateY(20px);
        opacity: 0;
        animation: slideUpFade 0.6s cubic-bezier(0.16, 1, 0.3, 1) forwards;
        position: relative;
        z-index: 10;
        color: white;
    }
    
    .register-card, .auth-card[style*="max-width"] {
        max-width: 550px !important;
    }

    @keyframes slideUpFade {
        to { transform: translateY(0); opacity: 1; }
    }

    .auth-header {
        text-align: center;
        margin-bottom: 2.5rem;
    }

    .auth-title {
        color: white;
        font-family: 'Outfit', sans-serif;
        font-weight: 700;
        font-size: 2.25rem;
        margin-bottom: 0.5rem;
        letter-spacing: -0.02em;
    }

    .auth-subtitle {
        color: #D1FAE5;
        font-size: 1rem;
        font-weight: 400;
        line-height: 1.5;
    }

    /* Forms */
    .form-group {
        margin-bottom: 1.5rem;
        position: relative;
        width: 100%;
    }

    .form-row {
        display: flex; gap: 15px; margin-bottom: 0; width: 100%;
    }
    .form-row .form-group { width: 50%; }

    .form-label, label {
        display: block;
        color: var(--light-green);
        font-weight: 600;
        margin-bottom: 0.5rem;
        font-size: 0.85rem;
        text-transform: uppercase;
        letter-spacing: 0.5px;
        transition: color 0.3s ease;
    }

    .input-wrapper {
        position: relative;
        display: flex;
        align-items: center;
    }

    .input-icon {
        position: absolute;
        left: 1rem;
        color: rgba(255,255,255,0.5);
        font-size: 1.2rem;
        transition: color 0.3s ease;
        z-index: 2;
    }

    .form-control, .form-group input, input[type="email"], input[type="password"], input[type="text"] {
        width: 100%;
        background: rgba(0, 0, 0, 0.3);
        border: 2px solid rgba(16, 185, 129, 0.2);
        border-radius: 12px;
        padding: 0.875rem 1rem 0.875rem 1rem;
        color: white;
        font-size: 1rem;
        transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
        box-shadow: none;
    }

    /* Override padding if there's an icon wrapper */
    .input-wrapper .form-control, .input-wrapper input {
        padding-left: 3rem;
    }

    .form-control:focus, .form-group input:focus, input[type="email"]:focus, input[type="password"]:focus, input[type="text"]:focus {
        outline: none;
        border-color: var(--primary-emerald);
        background: rgba(0, 0, 0, 0.6);
        box-shadow: 0 0 0 4px rgba(16, 185, 129, 0.15);
    }

    .form-control:focus + .input-icon {
        color: var(--primary-emerald);
    }

    .form-control::placeholder, input::placeholder { color: rgba(255,255,255,0.4); }

    .password-toggle {
        position: absolute;
        right: 1rem;
        background: none;
        border: none;
        color: rgba(255,255,255,0.5);
        cursor: pointer;
        padding: 0;
        display: flex;
        align-items: center;
        justify-content: center;
        transition: color 0.3s ease;
    }
    .password-toggle:hover { color: var(--primary-emerald); }

    /* Buttons */
    .btn-submit, button[type="submit"], .btn-login, .auth-btn {
        width: 100%;
        background: linear-gradient(135deg, var(--light-green), var(--primary-emerald));
        color: #064E3B;
        border: none;
        border-radius: 12px;
        padding: 1rem;
        font-size: 1.1rem;
        font-weight: 700;
        cursor: pointer;
        display: flex;
        align-items: center;
        justify-content: center;
        gap: 0.75rem;
        transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
        box-shadow: 0 10px 20px -10px rgba(16, 185, 129, 0.5);
        margin-top: 1rem;
    }
    .btn-submit:hover, button[type="submit"]:hover, .btn-login:hover, .auth-btn:hover {
        transform: translateY(-2px);
        box-shadow: 0 15px 25px -10px rgba(16, 185, 129, 0.6);
    }

    /* Links */
    .auth-footer { margin-top: 2rem; text-align: center; color: var(--text-muted); font-size: 0.95rem; }
    .auth-link { color: var(--primary-emerald); font-weight: 600; text-decoration: none; transition: color 0.3s ease; }
    .auth-link:hover { color: var(--light-green); text-decoration: underline; }
    
    .forgot-link {
        display: block; text-align: right; color: var(--light-green); font-size: 0.85rem; 
        font-weight: 500; text-decoration: none; margin-top: 0.5rem; transition: color 0.3s ease;
    }
    .forgot-link:hover { color: var(--primary-emerald); text-decoration: underline; }

    /* Checkboxes/Role Select */
    .role-selector {
        display: flex; gap: 20px; align-items: center; background: rgba(0,0,0,0.3); padding: 10px 16px; border-radius: 12px; border: 1px solid rgba(16,185,129,0.2); width: 100%; justify-content: space-around;
        margin-bottom: 25px;
    }
    .form-check { display: flex; align-items: center; gap: 8px; cursor: pointer; margin-bottom: 0; }
    .form-check input { cursor: pointer; accent-color: var(--primary-btn); width: 16px; height: 16px; }
    .form-check label { margin-bottom: 0; font-size: 0.9rem; text-transform: none; color: #fff; font-weight: 500; letter-spacing: 0; }


    /* Error / Success Alerts */
    .error-box, .alert-danger { background: rgba(220,38,38,0.1); border: 1px solid rgba(220,38,38,0.3); color: #fca5a5; padding: 12px; border-radius: 8px; font-size: 13px; margin-bottom: 20px; }
    .success-box, .alert-success { background: rgba(16,185,129,0.1); border: 1px solid rgba(16,185,129,0.3); color: #86efac; padding: 12px; border-radius: 8px; font-size: 13px; margin-bottom: 20px; }
    ul.form-errors { list-style: none; margin-bottom: 15px; padding: 0; }
    ul.form-errors li { background: rgba(220,38,38,0.1); border: 1px solid rgba(220,38,38,0.3); color: #fca5a5; padding: 10px; border-radius: 8px; font-size: 12px; margin-bottom: 5px; }

    /* Floating elements cleanup for new dark background (disabling old script's items) */
    .floating-element { display: none !important; }
</style>
"""

BACKGROUND_HTML = """
    <div class="cyber-grid"></div>
    <div class="glow-orb orb-1"></div>
    <div class="glow-orb orb-2"></div>
    <div class="auth-wrapper">
"""

def update_file(filepath):
    if not os.path.exists(filepath): return
    with open(filepath, 'r', encoding='utf-8') as f:
        content = f.read()

    # Replace <style> block
    content = re.sub(r'<style>.*?</style>', CSS, content, flags=re.DOTALL)
    
    # Remove old floating elements
    content = re.sub(r'<div class="floating-element.*?>.*?</div>\n*', '', content)
    
    # Ensure background div exists
    if '<div class="cyber-grid"></div>' not in content:
        if '<div class="auth-wrapper">' in content:
            content = content.replace('<div class="auth-wrapper">', BACKGROUND_HTML)
        else:
            # If no auth-wrapper, it might just need it wrapped inside block body
            content = re.sub(r'({% block body %})', r'\1\n' + BACKGROUND_HTML, content)

    with open(filepath, 'w', encoding='utf-8') as f:
        f.write(content)

for filepath in [
    'templates/security/login.html.twig',
    'templates/registration/register.html.twig',
    'templates/security/forgot_password.html.twig',
    'templates/security/reset_password.html.twig'
]:
    update_file(filepath)

print("Theme updated successfully!")