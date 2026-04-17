from python_assistant.python_voice_agent import build_command, is_tts_status_line


def test_build_command_splits_py_launcher():
    assert build_command("py -3.14", "-u", "script.py") == ["py", "-3.14", "-u", "script.py"]


def test_build_command_keeps_absolute_path_intact():
    path = r"C:\Users\user\AppData\Local\Python\bin\python3.14.exe"
    assert build_command(path, "-u", "run.py") == [path, "-u", "run.py"]


def test_is_tts_status_line_detection():
    assert is_tts_status_line('{"type":"tts_status","speaking":true}')
    assert not is_tts_status_line('{"status":"ready"}')
    assert not is_tts_status_line(None)
