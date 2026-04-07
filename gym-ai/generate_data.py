import requests, json, os, time
import mysql.connector

conn = mysql.connector.connect(
    host="localhost", user="root", password="", database="datn"
)
cursor = conn.cursor(dictionary=True)

cursor.execute("""
    SELECT
        TRIM(SUBSTRING_INDEX(tc.class_name, ' - ', 1)) AS class_name,
        gr.room_name,
        GROUP_CONCAT(
            DISTINCT eq.equipment_name
            ORDER BY eq.equipment_name SEPARATOR ', '
        ) AS equipment_list
    FROM TrainingClass tc
    JOIN GymRoom gr ON gr.room_id = tc.room_id
    LEFT JOIN Equipment eq ON eq.room_id = gr.room_id
        AND LOWER(TRIM(eq.condition_status)) NOT IN ('broken','hỏng','damaged')
    WHERE gr.status = 'Active'
    GROUP BY TRIM(SUBSTRING_INDEX(tc.class_name, ' - ', 1)), gr.room_name
    ORDER BY class_name
""")
classes_from_db = cursor.fetchall()
cursor.close()
conn.close()

print(f"✅ Lấy được {len(classes_from_db)} loại lớp:")
for c in classes_from_db:
    print(f"  - {c['class_name']} | {c['room_name']}")

OLLAMA_URL = "http://localhost:11434/api/generate"
MODEL      = "qwen2.5:3b"
OUTPUT     = r"C:\wamp64\www\PHP\ELite_GYM\gym-ai\gym_training_data.jsonl"

goals     = ["Giảm mỡ", "Tăng cơ", "Tăng sức bền", "Duy trì thể hình"]
bmis      = [
    {"val": 17.0, "cat": "Thiếu cân"},
    {"val": 21.0, "cat": "Bình thường"},
    {"val": 23.5, "cat": "Thừa cân"},
    {"val": 27.0, "cat": "Béo phì I"},
    {"val": 32.0, "cat": "Béo phì II"}
]
durations = [45, 60, 90]
genders   = ["Nam", "Nữ"]

total = len(classes_from_db) * len(goals) * len(bmis) * len(durations) * len(genders)

# Resume
done_prompts = set()
if os.path.exists(OUTPUT):
    with open(OUTPUT, "r", encoding="utf-8") as f:
        for line in f:
            try:
                done_prompts.add(json.loads(line.strip())["prompt"])
            except: pass
    print(f"🔄 Resume: đã có {len(done_prompts)}/{total} mẫu")
else:
    print(f"🆕 Bắt đầu mới, tổng {total} mẫu")

out_file = open(OUTPUT, "a", encoding="utf-8")
count = success = 0

for cls in classes_from_db:
    for goal in goals:
        for bmi in bmis:
            for dur in durations:
                for gender in genders:
                    count += 1
                    equip = cls['equipment_list'] or 'thiết bị cơ bản'
                    prompt_text = (
                        f"BMI: {bmi['val']} ({bmi['cat']}) | "
                        f"Giới tính: {gender} | Mục tiêu: {goal} | "
                        f"Lớp: {cls['class_name']} | Phòng: {cls['room_name']} | "
                        f"Thời gian: {dur} phút"
                    )
                    if prompt_text in done_prompts:
                        continue

                    full_prompt = (
                        f"Bạn là HLV thể hình tại Elite Gym.\n"
                        f"Tạo lịch tập {dur} phút cho khách {gender}:\n"
                        f"- BMI: {bmi['val']} ({bmi['cat']}), Mục tiêu: {goal}\n"
                        f"- Lớp: {cls['class_name']} | Phòng: {cls['room_name']}\n"
                        f"- Thiết bị: {equip}\n\n"
                        f"Chỉ dùng thiết bị liệt kê trên. Format:\n"
                        f"### 🔥 KHỞI ĐỘNG (5-8 phút)\n"
                        f"### 💪 BÀI TẬP CHÍNH ({dur-18} phút)\n"
                        f"(tên bài [thiết bị] • sets×reps • nghỉ • ~kcal)\n"
                        f"### 🧘 GIÃN CƠ (5 phút)\n"
                        f"### 📊 TỔNG KẾT: tổng kcal | lời khuyên dinh dưỡng"
                    )

                    try:
                        res = requests.post(OLLAMA_URL, json={
                            "model": MODEL, "prompt": full_prompt, "stream": False
                        }, timeout=360)
                        reply = res.json().get("response", "").strip()
                        if reply:
                            out_file.write(json.dumps({
                                "prompt": prompt_text, "completion": reply,
                                "class_name": cls['class_name'], "room": cls['room_name'],
                                "goal": goal, "gender": gender,
                                "bmi_val": bmi['val'], "bmi_cat": bmi['cat'], "duration": dur
                            }, ensure_ascii=False) + "\n")
                            out_file.flush()
                            success += 1
                            print(f"[{count}/{total}] ✅ {cls['class_name']} | {goal} | {bmi['cat']} | {gender} | {dur}p ({success} mẫu)")
                        else:
                            print(f"[{count}/{total}] ⚠️ Rỗng")
                    except Exception as e:
                        print(f"[{count}/{total}] ❌ {e}")
                    time.sleep(0.5)

out_file.close()
print(f"\n✅ Xong! {success}/{total} mẫu → {OUTPUT}")