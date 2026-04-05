import requests
import json
import mysql.connector

# ── Kết nối MySQL WAMP ────────────────────────────────────────
conn = mysql.connector.connect(
    host     = "localhost",
    user     = "root",
    password = "",        # WAMP mặc định không có password
    database = "datn"
)
cursor = conn.cursor(dictionary=True)

# ── Lấy lớp tập + phòng + thiết bị từ DB ─────────────────────
# Fix: dùng đúng tên bảng theo schema (TrainingClass, GymRoom, Equipment)
cursor.execute("""
    SELECT DISTINCT
        tc.class_name,
        gr.room_name,
        GROUP_CONCAT(
            eq.equipment_name ORDER BY eq.equipment_name SEPARATOR ', '
        ) AS equipment_list
    FROM TrainingClass tc
    JOIN GymRoom gr ON gr.room_id = tc.room_id
    LEFT JOIN Equipment eq ON eq.room_id = gr.room_id
        AND LOWER(TRIM(eq.condition_status)) NOT IN ('broken', 'hỏng', 'damaged')
    WHERE gr.status = 'Active'
    GROUP BY tc.class_name, gr.room_name
    ORDER BY tc.class_name
""")
classes_from_db = cursor.fetchall()
cursor.close()
conn.close()

print(f"✅ Lấy được {len(classes_from_db)} loại lớp từ DB:")
for c in classes_from_db:
    print(f"  - {c['class_name']} | {c['room_name']} | {c['equipment_list']}")

if not classes_from_db:
    print("❌ Không có dữ liệu lớp học. Kiểm tra DB.")
    exit()

# ── Tham số training ──────────────────────────────────────────
OLLAMA_URL = "http://localhost:11434/api/generate"
MODEL      = "qwen2.5:3b"     # đủ mạnh để tạo data chi tiết, nhỏ hơn llama3.2:3b
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
genders   = ["Nam", "Nữ"]  # thêm giới tính để data đa dạng hơn

dataset = []
total   = len(classes_from_db) * len(goals) * len(bmis) * len(durations) * len(genders)
count   = 0

print(f"📊 Tổng số mẫu cần tạo: {total}")

for cls in classes_from_db:
    for goal in goals:
        for bmi in bmis:
            for dur in durations:
                for gender in genders:
                    count += 1
                    equip = cls['equipment_list'] or 'thiết bị cơ bản (thảm tập, tạ tay nhẹ)'

                    # Prompt ngắn gọn để model trả lời đúng format
                    prompt_text = (
                        f"BMI: {bmi['val']} ({bmi['cat']}) | "
                        f"Giới tính: {gender} | "
                        f"Mục tiêu: {goal} | "
                        f"Lớp: {cls['class_name']} | "
                        f"Phòng: {cls['room_name']} | "
                        f"Thời gian: {dur} phút"
                    )

                    main_time = dur - 18
                    full_prompt = (
                        f"Bạn là HLV thể hình chuyên nghiệp tại Elite Gym.\n"
                        f"Tạo lịch tập {dur} phút CHI TIẾT cho khách hàng {gender}:\n"
                        f"- BMI: {bmi['val']} ({bmi['cat']})\n"
                        f"- Mục tiêu: {goal}\n"
                        f"- Lớp: {cls['class_name']} | Phòng: {cls['room_name']}\n"
                        f"- Thiết bị có sẵn trong phòng: {equip}\n\n"
                        f"QUY TẮC BẮT BUỘC:\n"
                        f"1. CHỈ dùng thiết bị liệt kê ở trên, ghi đúng tên thiết bị\n"
                        f"2. Mỗi bài tập PHẢI ghi rõ: tên thiết bị + thời gian hoặc số hiệp + kcal đốt\n"
                        f"3. Viết tiếng Việt, cụ thể, thực tế\n\n"
                        f"FORMAT BẮT BUỘC (giữ nguyên các dòng ### và emoji):\n\n"
                        f"### 🔥 KHỞI ĐỘNG (5-8 phút)\n"
                        f"(3-4 bài, mỗi bài theo format: Tên bài [thiết bị] • X phút • ~N kcal)\n"
                        f"Ví dụ: Đi bộ khởi động [Commercial Treadmill 1] • 5 phút • tốc độ 5km/h • ~25 kcal\n\n"
                        f"### 💪 BÀI TẬP CHÍNH ({main_time} phút)\n"
                        f"(5-7 bài, mỗi bài theo format: Tên bài [thiết bị] • Xsets×Yreps • nghỉ Zs • ~N kcal)\n"
                        f"Ví dụ: Chạy bộ đốt mỡ [Commercial Treadmill 1] • 15 phút • tốc độ 8km/h • ~120 kcal\n"
                        f"Ví dụ: Kéo xà đơn [Lat Pulldown Machine] • 3sets×12reps • nghỉ 60s • ~45 kcal\n\n"
                        f"### 🧘 GIÃN CƠ (5-8 phút)\n"
                        f"(3 bài giãn cơ phù hợp với bài tập chính)\n\n"
                        f"### 📊 TỔNG KẾT\n"
                        f"Tổng kcal đốt: ~X kcal | Đạt Y% mục tiêu | Lời khuyên dinh dưỡng: (1 câu cụ thể)"
                    )

                    try:
                        res = requests.post(OLLAMA_URL, json={
                            "model":  MODEL,
                            "prompt": full_prompt,
                            "stream": False,
                            "options": {
                                "num_gpu":     99,   # ✅ ép 100% GPU
                                "num_ctx":     2048, # ✅ tăng context để đọc prompt dài + output chi tiết
                                "temperature": 0.8,  # tăng nhẹ để output đa dạng hơn
                                "top_p":       0.9,
                                "repeat_penalty": 1.1
                            }
                        }, timeout=180)  # ✅ tăng timeout lên 180s
                        reply = res.json().get("response", "").strip()

                        if reply:
                            dataset.append({
                                "prompt":     prompt_text,
                                "completion": reply,
                                "equipment":  equip,
                                "room":       cls['room_name'],
                                "class_name": cls['class_name'],
                                "goal":       goal,
                                "gender":     gender,
                                "bmi_val":    bmi['val'],
                                "bmi_cat":    bmi['cat'],
                                "duration":   dur
                            })
                            print(f"[{count}/{total}] ✅ {cls['class_name']} | {goal} | {bmi['cat']} | {gender} | {dur}p")
                        else:
                            print(f"[{count}/{total}] ⚠️ Rỗng — {cls['class_name']} | {goal} | {bmi['cat']}")

                    except Exception as e:
                        print(f"[{count}/{total}] ❌ {e}")

                    # Lưu tạm mỗi 50 mẫu
                    if count % 50 == 0:
                        with open(OUTPUT, "w", encoding="utf-8") as f:
                            for item in dataset:
                                f.write(json.dumps(item, ensure_ascii=False) + "\n")
                        print(f"💾 Lưu tạm {len(dataset)} mẫu")

# Lưu lần cuối
with open(OUTPUT, "w", encoding="utf-8") as f:
    for item in dataset:
        f.write(json.dumps(item, ensure_ascii=False) + "\n")

print(f"\n✅ Hoàn thành! {len(dataset)}/{total} mẫu → {OUTPUT}")
