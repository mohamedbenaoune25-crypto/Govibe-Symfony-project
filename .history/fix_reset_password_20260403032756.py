import re

with open('templates/security/reset_password.html.twig', 'r', encoding='utf-8') as f:
    content = f.read()

# Replace resetForm.code with resetForm.token
content = content.replace('resetForm.code', 'resetForm.token')

# Remove the display:none form.email block completely
content = re.sub(r'<div class="form-group" style="display:none;">.*?</div>', '', content, flags=re.DOTALL)

new_pw_block = """            <div class="form-group">
                <label class="form-label">Nouveau mot de passe</label>
                <div class="input-wrapper">
                    <i class="bi bi-lock input-icon"></i>
                    {{ form_widget(resetForm.plainPassword.first, {'attr': {'class': 'form-control', 'placeholder': '••••••••'}}) }}
                </div>
                <div class="text-danger small mt-1">{{ form_errors(resetForm.plainPassword.first) }}</div>
            </div>

            <div class="form-group">
                <label class="form-label">Confirmer le mot de passe</label>
                <div class="input-wrapper">
                    <i class="bi bi-lock-fill input-icon"></i>
                    {{ form_widget(resetForm.plainPassword.second, {'attr': {'class': 'form-control', 'placeholder': '••••••••'}}) }}
                </div>
                <div class="text-danger small mt-1">{{ form_errors(resetForm.plainPassword.second) }}</div>
            </div>"""

content = re.sub(
    r'<div class="form-group">\s*<label class="form-label">Nouveau mot de passe</label>\s*<div class="input-wrapper">\s*<i class="bi bi-lock input-icon"></i>\s*\{\{\s*form_widget\([^}]*plainPassword[^}]*\}\}\s*</div>\s*<div class="text-danger small mt-1">\{\{\s*form_errors\([^}]*plainPassword[^}]*\}\}\s*</div>\s*</div>',
    new_pw_block,
    content
)


with open('templates/security/reset_password.html.twig', 'w', encoding='utf-8') as f:
    f.write(content)

print('done')