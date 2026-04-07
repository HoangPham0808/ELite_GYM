import json

INPUT  = r'C:\wamp64\www\PHP\ELite_GYM\gym-ai\gym_training_data.jsonl'
OUTPUT = r'C:\wamp64\www\PHP\ELite_GYM\gym-ai\gym_training_data.jsonl'

kept, removed = [], 0
with open(INPUT, encoding='utf-8') as f:
    for line in f:
        item = json.loads(line)
        cn = item.get('class_name', '') or item.get('prompt', '')
        if 'Basic A1' in cn and 'Basic A101' not in cn and 'Basic A102' not in cn:
            removed += 1
            print(f"Xóa: {item.get('prompt','')[:60]}")
        else:
            kept.append(item)

with open(OUTPUT, 'w', encoding='utf-8') as f:
    for item in kept:
        f.write(json.dumps(item, ensure_ascii=False) + '\n')

print(f'\nGiu lai: {len(kept)} | Da xoa: {removed}')