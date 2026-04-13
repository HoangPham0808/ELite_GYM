"""
debug_ollama.py — Xem raw response từ Ollama để biết tại sao parse lỗi
Chạy: python debug_ollama.py
"""
import json
import urllib.request

OLLAMA_URL   = "http://localhost:11434/api/generate"
OLLAMA_MODEL = "qwen2.5:1.5b"

# Test prompt giống hệt generate_data.py
PROMPTS = {
    "warmup": 'Return ONLY a JSON object (no text, no markdown) for ONE gym warm-up exercise using "Adjustable Bench 1" equipment.\nFormat: {"type":"warmup","name":"Vietnamese name","equipment":"Adjustable Bench 1","duration_min":5,"sets":null,"reps":null,"rest_sec":null,"kcal":20,"goals":["Giảm mỡ","Tăng cơ","Tăng sức bền","Duy trì thể hình"],"intensity":"low","bmi_min":0,"bmi_max":99}',
    "main":   'Return ONLY a JSON object (no text, no markdown) for ONE gym main exercise using "Adjustable Bench 1" equipment. Goal: Tăng cơ.\nFormat: {"type":"main","name":"Vietnamese name","equipment":"Adjustable Bench 1","duration_min":null,"sets":3,"reps":12,"rest_sec":60,"kcal":40,"goals":["Tăng cơ"],"intensity":"medium","bmi_min":0,"bmi_max":99}',
}

def call_ollama(prompt):
    payload = json.dumps({
        "model": OLLAMA_MODEL,
        "prompt": prompt,
        "stream": False,
        "options": {"temperature": 0.5, "num_predict": 200, "num_ctx": 1024}
    }).encode("utf-8")
    req = urllib.request.Request(OLLAMA_URL, data=payload,
                                  headers={"Content-Type": "application/json"}, method="POST")
    with urllib.request.urlopen(req, timeout=120) as resp:
        return json.loads(resp.read().decode("utf-8")).get("response", "")

for label, prompt in PROMPTS.items():
    print(f"\n{'═'*60}")
    print(f"TEST: {label}")
    print(f"{'─'*60}")
    raw = call_ollama(prompt)
    print(f"RAW RESPONSE ({len(raw)} chars):")
    print(repr(raw[:500]))   # in cả ký tự ẩn
    print(f"\nPRINTED:")
    print(raw[:500])
