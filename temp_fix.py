import sys

file_path = 'templates/vol/index.html.twig'
with open(file_path, 'r', encoding='utf-8') as f:
    text = f.read()

try:
    # Try reversing double-encoded utf-8
    fixed = text.encode('latin1').decode('utf-8')
    with open('templates/vol/index.html.twig', 'w', encoding='utf-8') as f2:
        f2.write(fixed)
    print("Fixed via latin1->utf8")
except Exception as e:
    print("Failed reverse encoding:", e)
