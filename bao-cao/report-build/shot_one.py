import base64
import json
import shutil
import subprocess
import time
from pathlib import Path

import requests
import websocket

EDGE = Path(r"C:\Program Files (x86)\Microsoft\Edge\Application\msedge.exe")
ROOT = Path(r"D:\xampp\htdocs\san_giao_dịch_do_cu\bao-cao\report-build")
PROFILE = ROOT / "profiles" / "shot1"
OUT = ROOT / "screenshots" / "01-dang-ky.png"
PORT = 9339

if PROFILE.exists():
    shutil.rmtree(PROFILE, ignore_errors=True)
PROFILE.mkdir(parents=True, exist_ok=True)
OUT.parent.mkdir(parents=True, exist_ok=True)

proc = subprocess.Popen(
    [
        str(EDGE),
        "--headless=new",
        "--disable-gpu",
        "--no-first-run",
        "--no-default-browser-check",
        "--disable-extensions",
        "--remote-allow-origins=*",
        f"--remote-debugging-port={PORT}",
        f"--user-data-dir={PROFILE}",
        "--window-size=1440,900",
        "about:blank",
    ],
    stdout=subprocess.DEVNULL,
    stderr=subprocess.DEVNULL,
)

try:
    ok = False
    for _ in range(40):
        try:
            requests.get(f"http://127.0.0.1:{PORT}/json/version", timeout=1).raise_for_status()
            ok = True
            break
        except Exception:
            time.sleep(0.25)
    if not ok:
        raise RuntimeError("Edge debugger did not start")

    tabs = requests.get(f"http://127.0.0.1:{PORT}/json", timeout=5).json()
    page = next(t for t in tabs if t.get("type") == "page")
    ws = websocket.create_connection(page["webSocketDebuggerUrl"], timeout=20, suppress_origin=True)
    state = {"nid": 0}

    def call(method, params=None):
        state["nid"] += 1
        ws.send(json.dumps({"id": state["nid"], "method": method, "params": params or {}}))
        while True:
            message = json.loads(ws.recv())
            if message.get("id") == state["nid"]:
                if "error" in message:
                    raise RuntimeError(message["error"])
                return message.get("result", {})

    call("Page.enable")
    call("Runtime.enable")
    call(
        "Emulation.setDeviceMetricsOverride",
        {"width": 1440, "height": 900, "deviceScaleFactor": 1, "mobile": False},
    )
    call(
        "Page.navigate",
        {"url": "http://localhost/san_giao_d%E1%BB%8Bch_do_cu/public/register"},
    )
    time.sleep(3)
    title = call("Runtime.evaluate", {"expression": "document.title", "returnByValue": True})
    print("title", title)
    shot = call("Page.captureScreenshot", {"format": "png", "fromSurface": True})
    OUT.write_bytes(base64.b64decode(shot["data"]))
    print("saved", OUT, OUT.stat().st_size)
    ws.close()
finally:
    proc.terminate()
    try:
        proc.wait(timeout=8)
    except subprocess.TimeoutExpired:
        proc.kill()
