import json
bad, good = 0, 0
with open(r'C:\wamp64\www\PHP\ELite_GYM\gym-ai\gym_training_data.jsonl', encoding='utf-8') as f:
    for line in f:
        item = json.loads(line)
        text = item.get('completion','')
        if any(ord(c) > 0x4E00 and ord(c) < 0x9FFF for c in text):
            bad += 1
        else:
            good += 1
print(f'Mau tot: {good}, Mau loi tieng Trung: {bad}')