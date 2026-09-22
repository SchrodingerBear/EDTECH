s = open('organizations/immaculada-concepcion-college/assets/aframe.min.js').read()
for needle in ['hasLoaded', 'hasLoaded=']:
    idx = 0
    count = 0
    while True:
        i = s.find(needle, idx)
        if i == -1 or count >= 6:
            break
        print(repr(needle), '=>', s[i-60:i+60].replace('\n',' '))
        idx = i + 1
        count += 1
    print('--- total up to 6 shown ---')