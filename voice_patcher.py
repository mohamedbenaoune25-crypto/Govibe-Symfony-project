import re

with open('templates/base.html.twig', 'r', encoding='utf-8') as f:
    html = f.read()

# 1. Update HTML buttons
actions_old = '''            <div class="gv-voice-panel-actions">
                <button class="gv-voice-action-btn" onclick="gvVoice.replay()">?? Réécouter</button>
                <button class="gv-voice-action-btn" onclick="openChatModal()">?? Chat complet</button>
                <button class="gv-voice-action-btn primary" onclick="gvVoice.startListening()">?? Parler</button>
            </div>'''
# Using flexible regex to catch anything since sometimes accents break:
actions_regex = r'<div class="gv-voice-panel-actions">.*?</div>'

actions_new = '''            <div class="gv-voice-panel-actions" style="flex-wrap:wrap; gap:8px;">
                <button class="gv-voice-action-btn" onclick="gvVoice.startListening()" style="color:#10b981; border-color:rgba(16,185,129,0.3);">?? Démarrer</button>
                <button class="gv-voice-action-btn" onclick="gvVoice.stopListening()" style="color:#ef4444; border-color:rgba(239,68,68,0.3);">?? Arrêter Micro</button>
                <button class="gv-voice-action-btn" onclick="gvVoice.toggleMute()" id="gvBtnMute" style="color:#f59e0b; border-color:rgba(245,158,11,0.3);">?? Rendre Muet</button>
                <button class="gv-voice-action-btn primary" onclick="gvVoice.emergencyStop()" style="background:#ef4444; color:white; border:none; width:100%;">?? Stop Urgence / Vider file</button>
                <button class="gv-voice-action-btn w-100 mt-1" onclick="openChatModal()">?? Ouvrir le Chat Texte</button>
            </div>'''
html = re.sub(actions_regex, actions_new, html, flags=re.DOTALL)

# Let's fix JS
with open('templates/base.html.twig', 'w', encoding='utf-8') as f:
    f.write(html)
print("HTML buttons patched.")
