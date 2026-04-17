src = open('src/main/resources/scripts/voice_agent.py', encoding='utf-8').read()

checks = [
    ('oh it was you',                      'Recognition line'),
    ('do you need help or should I guide', 'Guidance offer'),
    ('okaaayy',                            'Eager confirmation'),
    ('Alright, what can I do for you',     'Polite decline'),
    ('Wait, what',                         'Surprised logout'),
    ('what was that for',                  'Post-logout confusion'),
    ('yaaaawn',                            'Sleepy yawn'),
    ('Safe travels! Call me when you need','Farewell'),
    ('oh sorry, hahaha, time for work',    'Apologetic new user'),
    ("didn't quite catch that",            'Unknown response'),
    ('but still warm and velvety',         'Sleepy instr updated'),
    ('but still warm and captivating',     'Surprised instr updated'),
    ('with a flirtatious undertone',       'Helpful offer instr updated'),
    ('teasing and charming',               'Eager instr updated'),
    ('like falling asleep',                'Sleepy fade instr updated'),
    ('with a seductive undertone',         'General instr updated'),
    ('_INSTR_FAREWELL',                    'Farewell instr defined'),
    ('_INSTR_UNKNOWN',                     'Unknown instr defined'),
    ('3.175.86.81',                        'DNS patch present'),
    ('AWAITING_WELLNESS',                  'Guidance state present'),
    ('PROCESSING_LOGOUT',                  'Logout state present'),
]

ok = fail = 0
for term, label in checks:
    found = term in src
    print(f"  [{'OK' if found else 'MISSING'}] {label}")
    if found: ok += 1
    else: fail += 1

print(f"\nResult: {ok}/{len(checks)} checks passed")
