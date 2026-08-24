page = open(r"C:\laragon\www\numart\tools\deploy-out\transfer-stock-cabang.php","rb").read()
fix = open(r"C:\laragon\www\numart\tools\deploy-out\numart-transfer-fix.php","rb").read()
import re
cp = re.search(br"tambah\w+_fixed", page).group(0)
cf = re.search(br"function (tambah\w+_fixed)", fix).group(1)
print("page:", cp)
print("fix:", cf)
print("equal:", cp==cf)
# check broken echo
idx = page.find(b"dbg396290-v3")
print("marker context:", page[idx-20:idx+80])
