"""
patch_jsonl.py
Sửa lại class_name + prompt trong các mẫu JSONL cũ cho đúng với DB.

Vấn đề:
  - 120 mẫu đầu: class_name = "Basic A1"  (sai, thiếu số phòng)
  - Mẫu mới:     class_name = "Basic A101 - 06/04/2026 08:00–10:00"  (đúng nhưng có ngày)
  - DB dùng:     class_name = "Basic A101"  (tên chuẩn, không có ngày)

Sau khi patch:
  - Tất cả mẫu:  class_name = "Basic A101"
  - prompt key cũng được cập nhật tương ứng

Cách dùng:
  1. Chỉnh MAPPING bên dưới nếu có thêm lớp khác bị sai
  2. python patch_jsonl.py
  3. Kiểm tra file output rồi replace file gốc
"""

import json
import re
import os

INPUT  = r'C:\wamp64\www\PHP\ELite_GYM\gym-ai\gym_training_data.jsonl'
OUTPUT = r'C:\wamp64\www\PHP\ELite_GYM\gym-ai\gym_training_data_patched.jsonl'

# ── Mapping: tên sai → tên đúng ──────────────────────────────
# Thêm vào đây nếu phát hiện thêm lớp bị sai tên
MAPPING = {
    'Basic A1': 'Basic A101',
    # 'Yoga B2': 'Yoga B102',  # ví dụ thêm
}

def normalize_class_name(raw: str) -> str:
    """Cắt bỏ phần ngày giờ: 'Basic A101 - 06/04/2026 08:00–10:00' → 'Basic A101'"""
    return re.sub(r'\s*[-–]\s*\d{2}/\d{2}/\d{4}.*$', '', raw).strip()

def patch_item(item: dict) -> tuple[dict, str]:
    """Trả về (item đã patch, loại thay đổi)"""
    changed = 'unchanged'
    cn = item.get('class_name', '')

    # Trường hợp 1: tên ngắn sai (120 mẫu cũ)
    if cn in MAPPING:
        correct = MAPPING[cn]
        item['class_name'] = correct
        # Sửa prompt key
        if 'prompt' in item:
            item['prompt'] = item['prompt'].replace(
                f'| Lớp: {cn} |',
                f'| Lớp: {correct} |'
            )
        changed = f'mapped: {cn!r} → {correct!r}'

    # Trường hợp 2: tên có ngày giờ (mẫu mới từ 121+)
    elif re.search(r'\d{2}/\d{2}/\d{4}', cn):
        correct = normalize_class_name(cn)
        item['class_name'] = correct
        if 'prompt' in item:
            item['prompt'] = item['prompt'].replace(
                f'| Lớp: {cn} |',
                f'| Lớp: {correct} |'
            )
        changed = f'normalized: {cn!r} → {correct!r}'

    return item, changed

# ── Chạy patch ────────────────────────────────────────────────
stats = {}
total = 0

with open(INPUT,  'r', encoding='utf-8') as fin, \
     open(OUTPUT, 'w', encoding='utf-8') as fout:

    for line in fin:
        line = line.strip()
        if not line:
            continue
        try:
            item = json.loads(line)
            item, change_type = patch_item(item)
            fout.write(json.dumps(item, ensure_ascii=False) + '\n')
            stats[change_type] = stats.get(change_type, 0) + 1
            total += 1
        except json.JSONDecodeError as e:
            print(f'⚠️  Dòng lỗi JSON: {e}')

print(f'\n✅ Hoàn thành! {total} dòng')
print(f'   Output: {OUTPUT}')
print('\nThống kê thay đổi:')
for k, v in sorted(stats.items()):
    icon = '✏️ ' if k != 'unchanged' else '⏭️ '
    print(f'  {icon} {v:4d} | {k}')

print(f'\n👉 Kiểm tra file output xong thì:')
print(f'   copy "{OUTPUT}" "{INPUT}"')
