"""
generate_data.py  v6
Fixes:
  - Parser: xóa ```json ... ``` đúng cách
  - Prompt: dùng tên bài tập ví dụ thật thay vì "Vietnamese name"
  - Thêm system-style instruction buộc model không giải thích
"""

import json, os, re, time
import mysql.connector
import urllib.request

# ══════════════════════════════════════════════════════════════
# CẤU HÌNH
# ══════════════════════════════════════════════════════════════

DB_CONFIG    = dict(host="localhost", user="root", password="", database="datn")
OLLAMA_URL   = "http://localhost:11434/api/generate"
OLLAMA_MODEL = "qwen2.5:1.5b"
OUTPUT       = r"C:\wamp64\www\PHP\ELite_GYM\gym-ai\gym_exercise_library.jsonl"

VALID_GOALS     = ["Giảm mỡ", "Tăng cơ", "Tăng sức bền", "Duy trì thể hình"]
VALID_INTENSITY = {"low", "medium", "high"}
VALID_TYPES     = {"warmup", "main", "cooldown"}

TARGET_WARMUP   = 4
TARGET_MAIN     = 8
TARGET_COOLDOWN = 3
MAX_RETRIES     = 4
OLLAMA_TIMEOUT  = 120

GOAL_CYCLE = ["Giảm mỡ","Tăng cơ","Tăng sức bền","Duy trì thể hình",
              "Tăng cơ","Giảm mỡ","Duy trì thể hình","Tăng sức bền"]

# Dải BMI cho từng biến thể bài tập
BMI_RANGES = [
    (0.0,  18.5, "người thiếu cân (BMI < 18.5)"),
    (18.5, 25.0, "người bình thường (BMI 18.5-25)"),
    (25.0, 99.0, "người thừa cân hoặc béo phì (BMI > 25)"),
]


# ══════════════════════════════════════════════════════════════
# DB
# ══════════════════════════════════════════════════════════════

def fetch_rooms():
    conn   = mysql.connector.connect(**DB_CONFIG)
    cursor = conn.cursor(dictionary=True)
    cursor.execute("""
        SELECT gr.room_name,
               GROUP_CONCAT(DISTINCT eq.equipment_name
                            ORDER BY eq.equipment_name SEPARATOR ', ') AS equipment_list
        FROM GymRoom gr
        LEFT JOIN Equipment eq
               ON eq.room_id = gr.room_id
              AND LOWER(TRIM(eq.condition_status)) NOT IN ('broken','hong','damaged')
        WHERE gr.status = 'Active'
        GROUP BY gr.room_name ORDER BY gr.room_name
    """)
    rows = cursor.fetchall()
    cursor.close(); conn.close()
    return rows


# ══════════════════════════════════════════════════════════════
# PROMPT — dùng tên bài thật làm ví dụ, không dùng placeholder
# ══════════════════════════════════════════════════════════════

PROMPTS = {
"warmup": """\
Output ONLY raw JSON (no markdown, no explanation). Do NOT copy the example name.
Example: {{"type":"warmup","name":"Xoay khớp vai","equipment":"{equip}","duration_min":5,"sets":null,"reps":null,"rest_sec":null,"kcal":18,"goals":["Giảm mỡ","Tăng cơ","Tăng sức bền","Duy trì thể hình"],"intensity":"low","bmi_min":{bmi_min},"bmi_max":{bmi_max}}}

Generate ONE *different* warmup exercise (name must NOT be "Xoay khớp vai") for equipment "{equip}", suitable for {bmi_desc}. Output ONLY the JSON object:""",

"main": """\
Output ONLY raw JSON (no markdown, no explanation). Do NOT copy the example name.
Example: {{"type":"main","name":"Đẩy tạ ngực nằm","equipment":"{equip}","duration_min":null,"sets":3,"reps":12,"rest_sec":60,"kcal":45,"goals":["{goal}"],"intensity":"medium","bmi_min":{bmi_min},"bmi_max":{bmi_max}}}

Generate ONE *different* main exercise (name must NOT be "Đẩy tạ ngực nằm") for equipment "{equip}" with goal "{goal}", suitable for {bmi_desc}. Output ONLY the JSON object:""",

"cooldown": """\
Output ONLY raw JSON (no markdown, no explanation). Do NOT copy the example name.
Example: {{"type":"cooldown","name":"Kéo giãn cơ ngực","equipment":"{equip}","duration_min":3,"sets":null,"reps":null,"rest_sec":null,"kcal":6,"goals":["Giảm mỡ","Tăng cơ","Tăng sức bền","Duy trì thể hình"],"intensity":"low","bmi_min":{bmi_min},"bmi_max":{bmi_max}}}

Generate ONE *different* cooldown stretch (name must NOT be "Kéo giãn cơ ngực") for equipment "{equip}", suitable for {bmi_desc}. Output ONLY the JSON object:""",
}


# ══════════════════════════════════════════════════════════════
# OLLAMA
# ══════════════════════════════════════════════════════════════

def call_ollama(prompt: str) -> str:
    payload = json.dumps({
        "model":  OLLAMA_MODEL,
        "prompt": prompt,
        "stream": False,
        "options": {"temperature": 0.5, "num_predict": 250, "num_ctx": 1024},
    }).encode("utf-8")
    req = urllib.request.Request(
        OLLAMA_URL, data=payload,
        headers={"Content-Type": "application/json"}, method="POST"
    )
    with urllib.request.urlopen(req, timeout=OLLAMA_TIMEOUT) as resp:
        return json.loads(resp.read().decode("utf-8")).get("response", "")


# ══════════════════════════════════════════════════════════════
# PARSER — xử lý ```json ... ``` và tìm object {} đầu tiên
# ══════════════════════════════════════════════════════════════

def clean_text(text: str) -> str:
    """Xóa markdown fences: ```json, ```, và whitespace thừa."""
    # Xóa ```json hoặc ``` ở bất kỳ vị trí nào
    text = re.sub(r"```json\s*", "", text)
    text = re.sub(r"```\s*",     "", text)
    return text.strip()


def extract_object(text: str) -> dict | None:
    """Tìm JSON object {...} đầu tiên hợp lệ."""
    text = clean_text(text)

    start = text.find("{")
    if start == -1:
        return None

    depth = 0
    for i, ch in enumerate(text[start:], start):
        if   ch == "{": depth += 1
        elif ch == "}":
            depth -= 1
            if depth == 0:
                fragment = text[start : i+1]
                # Thử parse trực tiếp
                try:
                    return json.loads(fragment)
                except json.JSONDecodeError:
                    # Sửa trailing comma: {"a":1,} → {"a":1}
                    fixed = re.sub(r",\s*([\]}])", r"\1", fragment)
                    try:
                        return json.loads(fixed)
                    except Exception:
                        return None
    return None


# ══════════════════════════════════════════════════════════════
# VALIDATE
# ══════════════════════════════════════════════════════════════

BAD_NAME_PATTERNS = [
    r"vietnamese name", r"tên bài", r"example", r"your exercise",
    r"exercise name", r"\bvba\b",
]

def is_bad_name(name: str) -> bool:
    low = name.lower()
    return any(re.search(p, low) for p in BAD_NAME_PATTERNS)


def validate(ex: dict, room_name: str, ex_type: str, fallback_equip: str) -> dict | None:
    name = str(ex.get("name", "")).strip()
    if not name or len(name) < 4 or is_bad_name(name):
        return None

    equipment = str(ex.get("equipment", "")).strip() or fallback_equip

    try:    kcal = int(max(1, round(float(ex.get("kcal") or 20))))
    except: kcal = 20

    raw_goals = ex.get("goals", VALID_GOALS)
    if not isinstance(raw_goals, list): raw_goals = VALID_GOALS
    goals = [g for g in raw_goals if g in VALID_GOALS] or list(VALID_GOALS)

    intensity = str(ex.get("intensity","medium")).lower()
    if intensity not in VALID_INTENSITY: intensity = "medium"

    try:
        bmi_min = float(ex.get("bmi_min") or 0)
        bmi_max = float(ex.get("bmi_max") or 99)
    except: bmi_min, bmi_max = 0.0, 99.0
    if bmi_max < bmi_min: bmi_max = 99.0

    entry = {
        "room": room_name, "type": ex_type, "name": name,
        "equipment": equipment, "kcal": kcal, "goals": goals,
        "intensity": intensity, "bmi_min": bmi_min, "bmi_max": bmi_max,
    }

    if ex_type in ("warmup", "cooldown"):
        try:    entry["duration_min"] = int(ex.get("duration_min") or 5)
        except: entry["duration_min"] = 5
        entry["sets"] = entry["reps"] = entry["rest_sec"] = None
    else:
        entry["duration_min"] = None
        try:
            entry["sets"]     = int(ex.get("sets")     or 3)
            entry["reps"]     = int(ex.get("reps")     or 12)
            entry["rest_sec"] = int(ex.get("rest_sec") or 60)
        except:
            entry["sets"], entry["reps"], entry["rest_sec"] = 3, 12, 60

    return entry


# ══════════════════════════════════════════════════════════════
# SINH TỪNG BÀI
# ══════════════════════════════════════════════════════════════

def generate_one(ex_type: str, equip: str, room_name: str,
                 goal: str = "Tăng cơ",
                 bmi_min: float = 0.0, bmi_max: float = 99.0,
                 bmi_desc: str = "người bình thường") -> dict | None:
    if ex_type == "main":
        prompt = PROMPTS["main"].format(equip=equip, goal=goal,
                                        bmi_min=bmi_min, bmi_max=bmi_max, bmi_desc=bmi_desc)
    else:
        prompt = PROMPTS[ex_type].format(equip=equip,
                                          bmi_min=bmi_min, bmi_max=bmi_max, bmi_desc=bmi_desc)

    for attempt in range(1, MAX_RETRIES + 1):
        try:
            raw = call_ollama(prompt)
        except Exception as e:
            print(f" ❌{e}", end="", flush=True)
            time.sleep(2)
            continue

        obj = extract_object(raw)
        if obj:
            obj["type"] = ex_type   # đảm bảo type đúng
            v = validate(obj, room_name, ex_type, equip)
            if v:
                return v

        print(f" ⚠️r{attempt}", end="", flush=True)

    return None


def generate_for_room(room_name: str, equipment_list: str) -> list[dict]:
    equips   = [e.strip() for e in (equipment_list or "").split(",") if e.strip()]
    if not equips: equips = ["Adjustable Bench"]
    exercises = []

    # Warmup: luân phiên cả 3 BMI range để có đa dạng bài
    print(f"    [warmup]   ", end="", flush=True)
    for i in range(TARGET_WARMUP):
        bmi_min, bmi_max, bmi_desc = BMI_RANGES[i % len(BMI_RANGES)]
        ex = generate_one("warmup", equips[i % len(equips)], room_name,
                          bmi_min=bmi_min, bmi_max=bmi_max, bmi_desc=bmi_desc)
        print("✓" if ex else "✗", end="", flush=True)
        if ex: exercises.append(ex)
    print(f" → {sum(1 for e in exercises if e['type']=='warmup')} bài")

    # Main: luân phiên goal + BMI range
    print(f"    [main]     ", end="", flush=True)
    for i in range(TARGET_MAIN):
        bmi_min, bmi_max, bmi_desc = BMI_RANGES[i % len(BMI_RANGES)]
        ex = generate_one("main", equips[i % len(equips)], room_name, GOAL_CYCLE[i],
                          bmi_min=bmi_min, bmi_max=bmi_max, bmi_desc=bmi_desc)
        print("✓" if ex else "✗", end="", flush=True)
        if ex: exercises.append(ex)
    print(f" → {sum(1 for e in exercises if e['type']=='main')} bài")

    # Cooldown: luân phiên BMI range
    print(f"    [cooldown] ", end="", flush=True)
    for i in range(TARGET_COOLDOWN):
        bmi_min, bmi_max, bmi_desc = BMI_RANGES[i % len(BMI_RANGES)]
        ex = generate_one("cooldown", equips[i % len(equips)], room_name,
                          bmi_min=bmi_min, bmi_max=bmi_max, bmi_desc=bmi_desc)
        print("✓" if ex else "✗", end="", flush=True)
        if ex: exercises.append(ex)
    print(f" → {sum(1 for e in exercises if e['type']=='cooldown')} bài")

    return exercises


# ══════════════════════════════════════════════════════════════
# MAIN
# ══════════════════════════════════════════════════════════════

def main():
    print("🔌 Kết nối DB...")
    try:
        rooms = fetch_rooms()
    except mysql.connector.Error as e:
        print(f"❌ DB: {e}"); return

    print(f"✅ {len(rooms)} phòng Active:")
    for r in rooms:
        print(f"  - {r['room_name']} | {(r['equipment_list'] or 'N/A')[:80]}")

    done_rooms: set[str] = set()
    if os.path.exists(OUTPUT):
        with open(OUTPUT, "r", encoding="utf-8") as f:
            for line in f:
                try: done_rooms.add(json.loads(line.strip()).get("room",""))
                except: pass
        if done_rooms:
            print(f"\n🔄 Resume — đã có: {done_rooms}\n")

    total = 0
    with open(OUTPUT, "a", encoding="utf-8") as fout:
        for room in rooms:
            rname = room["room_name"]
            equip = room["equipment_list"] or ""

            if rname in done_rooms:
                print(f"  ⏭️  Skip: {rname}"); continue

            print(f"\n  📋 {rname}  [{equip[:80]}]")
            exercises = generate_for_room(rname, equip)

            # Dedup theo tên bài — loại bỏ trùng lặp trước khi ghi
            seen_names: set[str] = set()
            unique_exercises = []
            for ex in exercises:
                if ex['name'] not in seen_names:
                    seen_names.add(ex['name'])
                    unique_exercises.append(ex)
                else:
                    print(f"  ⚠️  Bỏ trùng: {ex['name']}")

            for ex in unique_exercises:
                fout.write(json.dumps(ex, ensure_ascii=False) + "\n")
                total += 1
            fout.flush()

            c = {t: sum(1 for e in unique_exercises if e["type"]==t)
                 for t in ("warmup","main","cooldown")}
            print(f"  💾 {len(unique_exercises)} bài (sau dedup) "
                  f"(warmup:{c['warmup']} main:{c['main']} cooldown:{c['cooldown']})")

    print(f"\n{'═'*50}")
    print(f"✅ XONG! {total} bài → {OUTPUT}")

if __name__ == "__main__":
    main()
