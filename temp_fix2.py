import re

with open('templates/base.html.twig', 'r', encoding='utf-8') as f:
    text = f.read()

# 1. stopSpeaking
text = text.replace(
    '''stopSpeaking() {
        if (window.speechSynthesis) window.speechSynthesis.cancel();
        this.setState('idle');
    }''',
    '''stopSpeaking() {
        if (this.currentAudio) {
            this.currentAudio.pause();
            this.currentAudio.currentTime = 0;
            this.currentAudio = null;
        }
        this.setState('idle');
    }'''
)

# 2. speak
text = text.replace(
    '''speak(text) {
        if (!window.speechSynthesis || !text || !_gvTtsEnabled) { this.setState('idle'); return; }
        let clean = text
            .replace(/[\\u{1F600}-\\u{1F9FF}\\u{2600}-\\u{2B55}\\u{1F300}-\\u{1F5FF}\\u{1F680}-\\u{1F6FF}]/gu, '')
            .replace(/https?:\\/\\/[^\\s]+/g, '').replace(/[*_`#>\\[\\]]/g, '').replace(/\\n+/g, '. ').trim();
        if (!clean || clean.length < 3) { this.setState('idle'); return; }
        if (clean.length > 400) clean = clean.substring(0, 400) + '...';

        this.lastResponse = clean;
        this.setState('speaking');
        window.speechSynthesis.cancel();
        const u = new SpeechSynthesisUtterance(clean);
        u.lang = 'fr-FR'; u.rate = 1.05; u.pitch = 1.0; u.volume = 1.0;
        if (_gvFrenchVoice) u.voice = _gvFrenchVoice;
        u.onend = () => this.setState('idle');
        u.onerror = () => this.setState('idle');
        window.speechSynthesis.speak(u);
    }''',
    '''async speak(text) {
        if (!text || !_gvTtsEnabled) { this.setState('idle'); return Promise.resolve(); }
        return new Promise(async (resolve) => {
            let clean = text
                .replace(/[\\u{1F600}-\\u{1F9FF}\\u{2600}-\\u{2B55}\\u{1F300}-\\u{1F5FF}\\u{1F680}-\\u{1F6FF}]/gu, '')
                .replace(/https?:\\/\\/[^\\s]+/g, '').replace(/[*_`#>\\[\\]]/g, '').replace(/\\n+/g, '. ').trim();
            if (!clean || clean.length < 3) { this.setState('idle'); resolve(); return; }
            if (clean.length > 400) clean = clean.substring(0, 400) + '...';

            this.lastResponse = clean;
            this.setState('speaking');

            this.stopSpeaking();

            try {
                const resp = await fetch('/api/voice/speak', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({ text: clean, voice: 'qween' })
                });

                if (!resp.ok) throw new Error('TTS failed');
                
                const blob = await resp.blob();
                this.currentAudio = new Audio(URL.createObjectURL(blob));
                
                this.currentAudio.addEventListener('ended', () => {
                    this.setState('idle');
                    this.currentAudio = null;
                    resolve();
                });
                this.currentAudio.addEventListener('error', () => {
                    this.setState('idle');
                    this.currentAudio = null;
                    resolve();
                });
                
                this.currentAudio.play();
            } catch (e) {
                console.error('TTS error:', e);
                this.setState('idle');
                resolve();
            }
        });
    }'''
)

# 3. action === stop_speaking
text = text.replace(
    '''            if (data.success && data.action === 'stop_speaking') {
                window.speechSynthesis.cancel();
                this.setState('idle');''',
    '''            if (data.success && data.action === 'stop_speaking') {
                this.stopSpeaking();
                this.setState('idle');'''
)

# 4. _loadVoices
text = re.sub(
    r"// Preload TTS voices.*?function _loadVoices\(\) \{.*?(?=function checkAiHealth)",
    "// Preload TTS voices (handled server-side)\n    });\n\n    let _gvEnglishVoice = null;\n    function _loadVoices() {}\n\n    ",
    text,
    flags=re.DOTALL
)

# 5. speakText function cleanup
text = re.sub(
    r"function speakText\(text\) \{.*?window\.speechSynthesis\.speak\(u\);\s*\}",
    "function speakText(text) {\n    if (window.gvVoice) window.gvVoice.speak(text);\n}",
    text,
    flags=re.DOTALL
)

# 6. toggleTts cancel check cleanup
text = re.sub(
    r"if \(!_gvTtsEnabled.*?cancel\(\);",
    "if (!_gvTtsEnabled && window.gvVoice) window.gvVoice.stopSpeaking();",
    text
)

# 7. logout keyword detection
text = text.replace(
    '''            if (finalTranscript) {
                processed = true;
                clearTimeout(safetyTimeout);
                if (silenceTimer) clearTimeout(silenceTimer);
                self.forcedRecognitionLang = null;
                try { self.recognition.stop(); } catch(e) {}
                self.processCommand(finalTranscript);''',
    '''            if (finalTranscript) {
                const lowerText = finalTranscript.toLowerCase();
                const logOutKeywords = ["log out", "logout", "sign out", "signout", "exit session"];
                if (logOutKeywords.some(kw => lowerText.includes(kw))) {
                    processed = true;
                    clearTimeout(safetyTimeout);
                    if (silenceTimer) clearTimeout(silenceTimer);
                    self.forcedRecognitionLang = null;
                    try { self.recognition.stop(); } catch(e) {}
                    
                    self.showPanel('"' + finalTranscript + '"', "Logging you out...");
                    self.speak("Logging you out").then(() => {
                        fetch('/logout', { method: 'GET' })
                            .then(() => window.location.href = '/login');
                    });
                    return;
                }

                processed = true;
                clearTimeout(safetyTimeout);
                if (silenceTimer) clearTimeout(silenceTimer);
                self.forcedRecognitionLang = null;
                try { self.recognition.stop(); } catch(e) {}
                self.processCommand(finalTranscript);''')

with open('templates/base.html.twig', 'w', encoding='utf-8') as f:
    f.write(text)

