import os

login_html = """{% extends 'base.html.twig' %}

{% block title %}S'authentifier | GoVibe{% endblock %}

{% block stylesheets %}
{{ parent() }}
<style>
    :root {
        --primary-emerald: #50C878;
        --dark-green: #013220;
        --mid-green: #084E36;
        --light-green: #2E8B57;
        --bg-creme: #F5F3E7;
        --white: #FFFFFF;
        --text-dark: #1E293B;
        --text-muted: #64748B;
        --glass-bg: rgba(255, 255, 255, 0.85);
        --glass-border: rgba(80, 200, 120, 0.2);
    }

    body {
        background-color: var(--bg-creme);
        background-image: 
            radial-gradient(circle at 15% 50%, rgba(80, 200, 120, 0.1) 0%, transparent 50%),
            radial-gradient(circle at 85% 30%, rgba(1, 50, 32, 0.05) 0%, transparent 50%);
        min-height: 100vh;
        display: flex;
        flex-direction: column;
        overflow-x: hidden;
    }

    .auth-wrapper {
        flex: 1;
        display: flex;
        align-items: center;
        justify-content: center;
        padding: 4rem 1rem;
        position: relative;
        z-index: 1;
    }

    /* Floating Travel Elements */
    .floating-element {
        position: absolute;
        opacity: 0.6;
        z-index: -1;
        animation: floatAnimation 8s ease-in-out infinite;
    }
    .el-1 { top: 15%; left: 10%; font-size: 3rem; color: var(--light-green); animation-delay: 0s; }
    .el-2 { bottom: 20%; right: 15%; font-size: 4rem; color: var(--primary-emerald); animation-delay: -2s; }
    .el-3 { top: 40%; right: 8%; font-size: 2.5rem; color: var(--dark-green); opacity: 0.3; animation-delay: -4s; }

    @keyframes floatAnimation {
        0%, 100% { transform: translateY(0) rotate(0deg); }
        50% { transform: translateY(-20px) rotate(5deg); }
    }

    .auth-card {
        background: var(--glass-bg);
        backdrop-filter: blur(16px);
        -webkit-backdrop-filter: blur(16px);
        border: 1px solid var(--glass-border);
        border-radius: 24px;
        padding: 3rem;
        width: 100%;
        max-width: 480px;
        box-shadow: 0 25px 50px -12px rgba(1, 50, 32, 0.15);
        transform: translateY(20px);
        opacity: 0;
        animation: slideUpFade 0.8s cubic-bezier(0.16, 1, 0.3, 1) forwards;
    }

    @keyframes slideUpFade {
        to {
            transform: translateY(0);
            opacity: 1;
        }
    }

    .auth-header {
        text-align: center;
        margin-bottom: 2.5rem;
    }

    .auth-title {
        color: var(--dark-green);
        font-family: 'Outfit', sans-serif;
        font-weight: 700;
        font-size: 2.25rem;
        margin-bottom: 0.5rem;
        letter-spacing: -0.02em;
    }

    .auth-subtitle {
        color: var(--text-muted);
        font-size: 1rem;
        font-weight: 400;
        line-height: 1.5;
    }

    .form-group {
        margin-bottom: 1.5rem;
        position: relative;
    }

    .form-label {
        display: block;
        color: var(--dark-green);
        font-weight: 500;
        margin-bottom: 0.5rem;
        font-size: 0.95rem;
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
        color: var(--light-green);
        font-size: 1.2rem;
        transition: color 0.3s ease;
    }

    .form-control {
        width: 100%;
        background: rgba(255, 255, 255, 0.9);
        border: 2px solid rgba(80, 200, 120, 0.2);
        border-radius: 12px;
        padding: 0.875rem 1rem 0.875rem 3rem;
        color: var(--text-dark);
        font-size: 1rem;
        transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
        box-shadow: none;
    }

    .form-control:focus {
        outline: none;
        border-color: var(--primary-emerald);
        background: var(--white);
        box-shadow: 0 0 0 4px rgba(80, 200, 120, 0.1);
    }

    .form-control:focus + .input-icon {
        color: var(--primary-emerald);
    }

    .password-toggle {
        position: absolute;
        right: 1rem;
        background: none;
        border: none;
        color: var(--text-muted);
        cursor: pointer;
        padding: 0;
        display: flex;
        align-items: center;
        justify-content: center;
        transition: color 0.3s ease;
    }

    .password-toggle:hover {
        color: var(--primary-emerald);
    }

    .forgot-link {
        display: block;
        text-align: right;
        color: var(--light-green);
        font-size: 0.875rem;
        font-weight: 500;
        text-decoration: none;
        margin-top: 0.5rem;
        transition: color 0.3s ease;
    }

    .forgot-link:hover {
        color: var(--primary-emerald);
        text-decoration: underline;
    }

    .btn-submit {
        width: 100%;
        background: linear-gradient(135deg, var(--light-green), var(--primary-emerald));
        color: white;
        border: none;
        border-radius: 12px;
        padding: 1rem;
        font-size: 1.1rem;
        font-weight: 600;
        cursor: pointer;
        display: flex;
        align-items: center;
        justify-content: center;
        gap: 0.75rem;
        transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
        box-shadow: 0 4px 15px rgba(80, 200, 120, 0.3);
        margin-top: 2rem;
    }

    .btn-submit:hover {
        transform: translateY(-2px);
        box-shadow: 0 8px 25px rgba(80, 200, 120, 0.4);
        background: linear-gradient(135deg, var(--mid-green), var(--primary-emerald));
    }

    .btn-submit i {
        transition: transform 0.3s ease;
    }

    .btn-submit:hover i {
        transform: translateX(4px);
    }

    .auth-footer {
        text-align: center;
        margin-top: 2.5rem;
        color: var(--text-muted);
        font-size: 0.95rem;
    }

    .auth-footer a {
        color: var(--dark-green);
        font-weight: 600;
        text-decoration: none;
        transition: color 0.3s ease;
        margin-left: 0.25rem;
    }

    .auth-footer a:hover {
        color: var(--primary-emerald);
        text-decoration: underline;
    }

    .alert-container {
        margin-bottom: 1.5rem;
    }

    .alert {
        background: rgba(220, 38, 38, 0.1);
        border: 1px solid rgba(220, 38, 38, 0.3);
        color: #B91C1C;
        border-radius: 12px;
        padding: 1rem;
        display: flex;
        align-items: center;
        gap: 0.75rem;
        font-size: 0.9rem;
        font-weight: 500;
    }

    .geo-shapes {
        position: absolute;
        top: 0; left: 0; width: 100%; height: 100%;
        overflow: hidden;
        z-index: -1;
        pointer-events: none;
    }

    .shape-circle {
        position: absolute;
        border-radius: 50%;
        background: linear-gradient(135deg, rgba(80,200,120,0.1), rgba(1,50,32,0.05));
        animation: pulse 6s infinite alternate ease-in-out;
    }

    @keyframes pulse {
        0% { transform: scale(1); opacity: 0.5; }
        100% { transform: scale(1.1); opacity: 0.8; }
    }
</style>
{% endblock %}

{% block body %}
<div class="geo-shapes">
    <div class="shape-circle" style="width: 400px; height: 400px; top: -100px; left: -100px;"></div>
    <div class="shape-circle" style="width: 300px; height: 300px; bottom: -50px; right: -50px; animation-delay: -3s;"></div>
</div>

<div class="auth-wrapper">
    <i class="bi bi-airplane floating-element el-1"></i>
    <i class="bi bi-globe-americas floating-element el-2"></i>
    <i class="bi bi-compass floating-element el-3"></i>

    <div class="auth-card">
        <div class="auth-header">
            <h1 class="auth-title">Bon Retour parmi nous !</h1>
            <p class="auth-subtitle">Votre prochaine aventure vous attend. Connectez-vous pour explorer nos meilleures destinations.</p>
        </div>

        {% if error %}
            <div class="alert-container">
                <div class="alert">
                    <i class="bi bi-exclamation-triangle-fill"></i>
                    {{ error.messageKey|trans(error.messageData, 'security') }}
                </div>
            </div>
        {% endif %}

        <form method="post" action="{{ path('app_login') }}">
            <div class="form-group">
                <label for="inputEmail" class="form-label">Adresse Email</label>
                <div class="input-wrapper">
                    <i class="bi bi-envelope input-icon"></i>
                    <input type="email" value="{{ last_username }}" name="_username" id="inputEmail" class="form-control" autocomplete="email" required autofocus placeholder="votre@email.com">
                </div>
            </div>

            <div class="form-group">
                <label for="inputPassword" class="form-label">Mot de passe</label>
                <div class="input-wrapper">
                    <i class="bi bi-lock input-icon"></i>
                    <input type="password" name="_password" id="inputPassword" class="form-control" autocomplete="current-password" required placeholder="••••••••">
                    <button type="button" class="password-toggle" onclick="togglePwd('inputPassword')">
                        <i class="bi bi-eye" id="toggleIcon"></i>
                    </button>
                </div>
                <a href="{{ path('app_forgot_password') }}" class="forgot-link">Mot de passe oublié ?</a>
            </div>

            <input type="hidden" name="_csrf_token" value="{{ csrf_token('authenticate') }}">

            <button type="submit" class="btn-submit">
                Démarrer l'Aventure <i class="bi bi-arrow-right"></i>
            </button>
        </form>

        <div class="auth-footer">
            Nouveau voyageur ? <a href="{{ path('app_register') }}">Créer un compte</a>
        </div>
    </div>
</div>

<script>
    function togglePwd(inputId) {
        const input = document.getElementById(inputId);
        const icon = document.getElementById('toggleIcon');
        if (input.type === 'password') {
            input.type = 'text';
            icon.classList.replace('bi-eye', 'bi-eye-slash');
        } else {
            input.type = 'password';
            icon.classList.replace('bi-eye-slash', 'bi-eye');
        }
    }
</script>
{% endblock %}
"""

register_html = """{% extends 'base.html.twig' %}

{% block title %}S'inscrire | GoVibe{% endblock %}

{% block stylesheets %}
{{ parent() }}
<style>
    :root {
        --primary-emerald: #50C878;
        --dark-green: #013220;
        --mid-green: #084E36;
        --light-green: #2E8B57;
        --bg-creme: #F5F3E7;
        --white: #FFFFFF;
        --text-dark: #1E293B;
        --text-muted: #64748B;
        --glass-bg: rgba(255, 255, 255, 0.85);
        --glass-border: rgba(80, 200, 120, 0.2);
    }

    body {
        background-color: var(--bg-creme);
        background-image: 
            radial-gradient(circle at 15% 50%, rgba(80, 200, 120, 0.1) 0%, transparent 50%),
            radial-gradient(circle at 85% 30%, rgba(1, 50, 32, 0.05) 0%, transparent 50%);
        min-height: 100vh;
        display: flex;
        flex-direction: column;
        overflow-x: hidden;
    }

    .auth-wrapper {
        flex: 1;
        display: flex;
        align-items: center;
        justify-content: center;
        padding: 4rem 1rem;
        position: relative;
        z-index: 1;
    }

    .auth-card {
        background: var(--glass-bg);
        backdrop-filter: blur(16px);
        -webkit-backdrop-filter: blur(16px);
        border: 1px solid var(--glass-border);
        border-radius: 24px;
        padding: 3rem;
        width: 100%;
        max-width: 580px;
        box-shadow: 0 25px 50px -12px rgba(1, 50, 32, 0.15);
        transform: translateY(20px);
        opacity: 0;
        animation: slideUpFade 0.8s cubic-bezier(0.16, 1, 0.3, 1) forwards;
    }

    @keyframes slideUpFade {
        to { transform: translateY(0); opacity: 1; }
    }

    .auth-header {
        text-align: center;
        margin-bottom: 2.5rem;
    }

    .auth-title {
        color: var(--dark-green);
        font-family: 'Outfit', sans-serif;
        font-weight: 700;
        font-size: 2.25rem;
        margin-bottom: 0.5rem;
    }

    .auth-subtitle {
        color: var(--text-muted);
        font-size: 1rem;
    }

    .form-row {
        display: flex;
        gap: 1.5rem;
    }
    .form-col { flex: 1; }

    .form-group {
        margin-bottom: 1.5rem;
        position: relative;
    }

    .form-label {
        display: block;
        color: var(--dark-green);
        font-weight: 500;
        margin-bottom: 0.5rem;
        font-size: 0.95rem;
    }

    .input-wrapper {
        position: relative;
        display: flex;
        align-items: center;
    }

    .input-icon {
        position: absolute;
        left: 1rem;
        color: var(--light-green);
        font-size: 1.2rem;
    }

    .form-control {
        width: 100%;
        background: rgba(255, 255, 255, 0.9);
        border: 2px solid rgba(80, 200, 120, 0.2);
        border-radius: 12px;
        padding: 0.875rem 1rem 0.875rem 3rem;
        color: var(--text-dark);
        font-size: 1rem;
        transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
    }

    .form-control:focus {
        outline: none;
        border-color: var(--primary-emerald);
        background: var(--white);
        box-shadow: 0 0 0 4px rgba(80, 200, 120, 0.1);
    }

    .btn-submit {
        width: 100%;
        background: linear-gradient(135deg, var(--light-green), var(--primary-emerald));
        color: white;
        border: none;
        border-radius: 12px;
        padding: 1rem;
        font-size: 1.1rem;
        font-weight: 600;
        cursor: pointer;
        display: flex;
        align-items: center;
        justify-content: center;
        gap: 0.75rem;
        transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
        box-shadow: 0 4px 15px rgba(80, 200, 120, 0.3);
        margin-top: 2rem;
    }

    .btn-submit:hover {
        transform: translateY(-2px);
        box-shadow: 0 8px 25px rgba(80, 200, 120, 0.4);
        background: linear-gradient(135deg, var(--mid-green), var(--primary-emerald));
    }

    .auth-footer {
        text-align: center;
        margin-top: 2.5rem;
        color: var(--text-muted);
    }

    .auth-footer a {
        color: var(--dark-green);
        font-weight: 600;
        text-decoration: none;
    }

    .auth-footer a:hover {
        color: var(--primary-emerald);
        text-decoration: underline;
    }
</style>
{% endblock %}

{% block body %}
<div class="auth-wrapper">
    <div class="auth-card">
        <div class="auth-header">
            <h1 class="auth-title">Rejoignez GoVibe</h1>
            <p class="auth-subtitle">Créez votre compte et planifiez vos vacances de rêve.</p>
        </div>

        {{ form_start(registrationForm, {'attr': {'class': 'modern-form'}}) }}
            
            <div class="form-row">
                <div class="form-col">
                    <div class="form-group">
                        <label class="form-label">Nom</label>
                        <div class="input-wrapper">
                            <i class="bi bi-person input-icon"></i>
                            {{ form_widget(registrationForm.nom, {'attr': {'class': 'form-control', 'placeholder': 'Votre nom'}}) }}
                        </div>
                        <div class="text-danger small mt-1">{{ form_errors(registrationForm.nom) }}</div>
                    </div>
                </div>
                <div class="form-col">
                    <div class="form-group">
                        <label class="form-label">Prénom</label>
                        <div class="input-wrapper">
                            <i class="bi bi-person input-icon"></i>
                            {{ form_widget(registrationForm.prenom, {'attr': {'class': 'form-control', 'placeholder': 'Votre prénom'}}) }}
                        </div>
                        <div class="text-danger small mt-1">{{ form_errors(registrationForm.prenom) }}</div>
                    </div>
                </div>
            </div>

            <div class="form-group">
                <label class="form-label">Adresse Email</label>
                <div class="input-wrapper">
                    <i class="bi bi-envelope input-icon"></i>
                    {{ form_widget(registrationForm.email, {'attr': {'class': 'form-control', 'placeholder': 'votre@email.com'}}) }}
                </div>
                <div class="text-danger small mt-1">{{ form_errors(registrationForm.email) }}</div>
            </div>

            <div class="form-group">
                <label class="form-label">Numéro de téléphone</label>
                <div class="input-wrapper">
                    <i class="bi bi-phone input-icon"></i>
                    {{ form_widget(registrationForm.tel, {'attr': {'class': 'form-control', 'placeholder': '+216 00 000 000'}}) }}
                </div>
                <div class="text-danger small mt-1">{{ form_errors(registrationForm.tel) }}</div>
            </div>

            <div class="form-group">
                <label class="form-label">Mot de passe</label>
                <div class="input-wrapper">
                    <i class="bi bi-lock input-icon"></i>
                    {{ form_widget(registrationForm.plainPassword, {'attr': {'class': 'form-control', 'placeholder': '••••••••'}}) }}
                </div>
                <div class="text-danger small mt-1">{{ form_errors(registrationForm.plainPassword) }}</div>
            </div>

            <div class="form-group d-flex align-items-center gap-2 mt-4">
                {{ form_widget(registrationForm.agreeTerms) }}
                <label class="form-check-label text-muted small">J'accepte les conditions d'utilisation</label>
            </div>

            <button type="submit" class="btn-submit">
                Créer mon compte <i class="bi bi-check2-circle"></i>
            </button>
        {{ form_end(registrationForm) }}

        <div class="auth-footer">
            Déjà inscrit ? <a href="{{ path('app_login') }}">Connectez-vous</a>
        </div>
    </div>
</div>
{% endblock %}
"""

forgot_password_html = """{% extends 'base.html.twig' %}

{% block title %}Mot de passe oublié | GoVibe{% endblock %}

{% block stylesheets %}
{{ parent() }}
<style>
    :root {
        --primary-emerald: #50C878;
        --dark-green: #013220;
        --mid-green: #084E36;
        --light-green: #2E8B57;
        --bg-creme: #F5F3E7;
        --white: #FFFFFF;
        --text-dark: #1E293B;
        --text-muted: #64748B;
        --glass-bg: rgba(255, 255, 255, 0.85);
        --glass-border: rgba(80, 200, 120, 0.2);
    }

    body {
        background-color: var(--bg-creme);
        background-image: 
            radial-gradient(circle at 15% 50%, rgba(80, 200, 120, 0.1) 0%, transparent 50%);
        min-height: 100vh;
        display: flex;
        flex-direction: column;
        overflow-x: hidden;
    }

    .auth-wrapper {
        flex: 1;
        display: flex;
        align-items: center;
        justify-content: center;
        padding: 4rem 1rem;
        position: relative;
    }

    .auth-card {
        background: var(--glass-bg);
        backdrop-filter: blur(16px);
        -webkit-backdrop-filter: blur(16px);
        border: 1px solid var(--glass-border);
        border-radius: 24px;
        padding: 3rem;
        width: 100%;
        max-width: 480px;
        box-shadow: 0 25px 50px -12px rgba(1, 50, 32, 0.15);
        transform: translateY(20px);
        opacity: 0;
        animation: slideUpFade 0.8s cubic-bezier(0.16, 1, 0.3, 1) forwards;
    }

    @keyframes slideUpFade {
        to { transform: translateY(0); opacity: 1; }
    }

    .auth-header {
        text-align: center;
        margin-bottom: 2.5rem;
    }

    .auth-title {
        color: var(--dark-green);
        font-family: 'Outfit', sans-serif;
        font-weight: 700;
        font-size: 2.25rem;
        margin-bottom: 0.5rem;
    }

    .auth-subtitle {
        color: var(--text-muted);
        font-size: 1rem;
    }

    .form-group {
        margin-bottom: 1.5rem;
    }

    .form-label {
        display: block;
        color: var(--dark-green);
        font-weight: 500;
        margin-bottom: 0.5rem;
    }

    .input-wrapper {
        position: relative;
        display: flex;
        align-items: center;
    }

    .input-icon {
        position: absolute;
        left: 1rem;
        color: var(--light-green);
        font-size: 1.2rem;
    }

    .form-control {
        width: 100%;
        background: rgba(255, 255, 255, 0.9);
        border: 2px solid rgba(80, 200, 120, 0.2);
        border-radius: 12px;
        padding: 0.875rem 1rem 0.875rem 3rem;
        color: var(--text-dark);
        font-size: 1rem;
        transition: all 0.3s ease;
    }

    .form-control:focus {
        outline: none;
        border-color: var(--primary-emerald);
        background: var(--white);
        box-shadow: 0 0 0 4px rgba(80, 200, 120, 0.1);
    }

    .btn-submit {
        width: 100%;
        background: linear-gradient(135deg, var(--light-green), var(--primary-emerald));
        color: white;
        border: none;
        border-radius: 12px;
        padding: 1rem;
        font-size: 1.1rem;
        font-weight: 600;
        cursor: pointer;
        display: flex;
        align-items: center;
        justify-content: center;
        gap: 0.75rem;
        box-shadow: 0 4px 15px rgba(80, 200, 120, 0.3);
        margin-top: 2rem;
    }

    .auth-footer {
        text-align: center;
        margin-top: 2.5rem;
        color: var(--text-muted);
    }

    .auth-footer a {
        color: var(--dark-green);
        font-weight: 600;
        text-decoration: none;
    }
</style>
{% endblock %}

{% block body %}
<div class="auth-wrapper">
    <div class="auth-card">
        <div class="auth-header">
            <h1 class="auth-title">Réinitialiser </h1>
            <p class="auth-subtitle">Entrez votre email pour recevoir votre code de réinitialisation.</p>
        </div>

        {{ form_start(requestForm) }}
            <div class="form-group">
                <label class="form-label">Adresse Email</label>
                <div class="input-wrapper">
                    <i class="bi bi-envelope input-icon"></i>
                    {{ form_widget(requestForm.email, {'attr': {'class': 'form-control', 'placeholder': 'votre@email.com'}}) }}
                </div>
                <div class="text-danger small mt-1">{{ form_errors(requestForm.email) }}</div>
            </div>

            <button type="submit" class="btn-submit">
                Envoyer le lien <i class="bi bi-send"></i>
            </button>
        {{ form_end(requestForm) }}

        <div class="auth-footer">
            <a href="{{ path('app_login') }}"><i class="bi bi-arrow-left"></i> Retour à la connexion</a>
        </div>
    </div>
</div>
{% endblock %}
"""

reset_password_html = """{% extends 'base.html.twig' %}

{% block title %}Nouveau mot de passe | GoVibe{% endblock %}

{% block stylesheets %}
{{ parent() }}
<style>
    /* Same Travel theme CSS Variables */
    :root {
        --primary-emerald: #50C878;
        --dark-green: #013220;
        --mid-green: #084E36;
        --light-green: #2E8B57;
        --bg-creme: #F5F3E7;
        --white: #FFFFFF;
        --text-dark: #1E293B;
        --text-muted: #64748B;
        --glass-bg: rgba(255, 255, 255, 0.85);
        --glass-border: rgba(80, 200, 120, 0.2);
    }

    body {
        background-color: var(--bg-creme);
        background-image: 
            radial-gradient(circle at 15% 50%, rgba(80, 200, 120, 0.1) 0%, transparent 50%);
        min-height: 100vh;
        display: flex;
        flex-direction: column;
        overflow-x: hidden;
    }

    .auth-wrapper {
        flex: 1;
        display: flex;
        align-items: center;
        justify-content: center;
        padding: 4rem 1rem;
        position: relative;
    }

    .auth-card {
        background: var(--glass-bg);
        backdrop-filter: blur(16px);
        -webkit-backdrop-filter: blur(16px);
        border: 1px solid var(--glass-border);
        border-radius: 24px;
        padding: 3rem;
        width: 100%;
        max-width: 480px;
        box-shadow: 0 25px 50px -12px rgba(1, 50, 32, 0.15);
        transform: translateY(20px);
        opacity: 0;
        animation: slideUpFade 0.8s cubic-bezier(0.16, 1, 0.3, 1) forwards;
    }

    @keyframes slideUpFade {
        to { transform: translateY(0); opacity: 1; }
    }

    .auth-header {
        text-align: center;
        margin-bottom: 2.5rem;
    }

    .auth-title {
        color: var(--dark-green);
        font-family: 'Outfit', sans-serif;
        font-weight: 700;
        font-size: 2.25rem;
        margin-bottom: 0.5rem;
    }

    .form-group {
        margin-bottom: 1.5rem;
    }

    .form-label {
        display: block;
        color: var(--dark-green);
        font-weight: 500;
        margin-bottom: 0.5rem;
    }

    .input-wrapper {
        position: relative;
        display: flex;
        align-items: center;
    }

    .input-icon {
        position: absolute;
        left: 1rem;
        color: var(--light-green);
        font-size: 1.2rem;
    }

    .form-control {
        width: 100%;
        background: rgba(255, 255, 255, 0.9);
        border: 2px solid rgba(80, 200, 120, 0.2);
        border-radius: 12px;
        padding: 0.875rem 1rem 0.875rem 3rem;
        color: var(--text-dark);
        font-size: 1rem;
        transition: all 0.3s ease;
    }

    .form-control:focus {
        outline: none;
        border-color: var(--primary-emerald);
        background: var(--white);
        box-shadow: 0 0 0 4px rgba(80, 200, 120, 0.1);
    }

    .btn-submit {
        width: 100%;
        background: linear-gradient(135deg, var(--light-green), var(--primary-emerald));
        color: white;
        border: none;
        border-radius: 12px;
        padding: 1rem;
        font-size: 1.1rem;
        font-weight: 600;
        cursor: pointer;
        display: flex;
        align-items: center;
        justify-content: center;
        gap: 0.75rem;
        box-shadow: 0 4px 15px rgba(80, 200, 120, 0.3);
        margin-top: 2rem;
    }

</style>
{% endblock %}

{% block body %}
<div class="auth-wrapper">
    <div class="auth-card">
        <div class="auth-header">
            <h1 class="auth-title">Sécuriser le compte</h1>
            <p class="auth-subtitle">Entrez le code reçu et choisissez votre nouveau mot de passe.</p>
        </div>

        {{ form_start(resetForm) }}
            <div class="form-group">
                <label class="form-label">Code de vérification (6 caractères)</label>
                <div class="input-wrapper">
                    <i class="bi bi-shield-check input-icon"></i>
                    {{ form_widget(resetForm.code, {'attr': {'class': 'form-control', 'placeholder': 'XXXXXX'}}) }}
                </div>
                <div class="text-danger small mt-1">{{ form_errors(resetForm.code) }}</div>
            </div>

            <div class="form-group" style="display:none;">
                {{ form_widget(resetForm.email) }}
            </div>

            <div class="form-group">
                <label class="form-label">Nouveau mot de passe</label>
                <div class="input-wrapper">
                    <i class="bi bi-lock input-icon"></i>
                    {{ form_widget(resetForm.plainPassword, {'attr': {'class': 'form-control', 'placeholder': '••••••••'}}) }}
                </div>
                <div class="text-danger small mt-1">{{ form_errors(resetForm.plainPassword) }}</div>
            </div>

            <button type="submit" class="btn-submit">
                Confirmer la modification <i class="bi bi-check-circle"></i>
            </button>
        {{ form_end(resetForm) }}
    </div>
</div>
{% endblock %}
"""

# File paths
files = {
    'templates/security/login.html.twig': login_html,
    'templates/registration/register.html.twig': register_html,
    'templates/security/forgot_password.html.twig': forgot_password_html,
    'templates/security/reset_password.html.twig': reset_password_html
}

for path, content in files.items():
    with open(path, 'w', encoding='utf-8') as f:
        f.write(content)

print("Travel theme templates updated successfully!")
