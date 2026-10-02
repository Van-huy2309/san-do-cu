import base64
import json
import shutil
import subprocess
import time
from pathlib import Path

import requests
import websocket


BASE = "http://localhost/san_giao_d%E1%BB%8Bch_do_cu/public"
ROOT = Path(r"D:\xampp\htdocs\san_giao_dịch_do_cu\bao-cao\report-build")
SCREENSHOTS = ROOT / "screenshots"
EDGE = Path(r"C:\Program Files (x86)\Microsoft\Edge\Application\msedge.exe")


class CdpBrowser:
    def __init__(self, name: str, port: int):
        self.name = name
        self.port = port
        self.profile = ROOT / "profiles" / name
        if self.profile.exists():
            shutil.rmtree(self.profile, ignore_errors=True)
        self.profile.mkdir(parents=True, exist_ok=True)
        self.process = subprocess.Popen(
            [
                str(EDGE),
                "--headless=new",
                "--disable-gpu",
                "--no-first-run",
                "--no-default-browser-check",
                "--disable-extensions",
                "--disable-features=Translate",
                "--remote-allow-origins=*",
                f"--remote-debugging-port={port}",
                f"--user-data-dir={self.profile}",
                "--window-size=1440,900",
                "about:blank",
            ],
            stdout=subprocess.DEVNULL,
            stderr=subprocess.DEVNULL,
        )
        self._wait_for_debugger()
        tabs = requests.get(f"http://127.0.0.1:{port}/json", timeout=10).json()
        page = next(tab for tab in tabs if tab.get("type") == "page")
        self.ws = websocket.create_connection(
            page["webSocketDebuggerUrl"], timeout=30, suppress_origin=True
        )
        self.next_id = 0
        self.call("Page.enable")
        self.call("Runtime.enable")
        self.call(
            "Emulation.setDeviceMetricsOverride",
            {
                "width": 1440,
                "height": 900,
                "deviceScaleFactor": 1,
                "mobile": False,
            },
        )

    def _wait_for_debugger(self):
        deadline = time.time() + 30
        while time.time() < deadline:
            try:
                requests.get(
                    f"http://127.0.0.1:{self.port}/json/version", timeout=1
                ).raise_for_status()
                return
            except requests.RequestException:
                time.sleep(0.2)
        raise RuntimeError(f"Edge DevTools did not start on port {self.port}")

    def call(self, method: str, params=None):
        self.next_id += 1
        request_id = self.next_id
        self.ws.send(
            json.dumps(
                {"id": request_id, "method": method, "params": params or {}},
                ensure_ascii=False,
            )
        )
        while True:
            message = json.loads(self.ws.recv())
            if message.get("id") == request_id:
                if "error" in message:
                    raise RuntimeError(f"{method}: {message['error']}")
                return message.get("result", {})

    def evaluate(self, expression: str, await_promise=False):
        result = self.call(
            "Runtime.evaluate",
            {
                "expression": expression,
                "returnByValue": True,
                "awaitPromise": await_promise,
            },
        )
        return result.get("result", {}).get("value")

    def wait_ready(self, timeout=30):
        deadline = time.time() + timeout
        while time.time() < deadline:
            try:
                if self.evaluate("document.readyState") == "complete":
                    body = self.evaluate(
                        "document.body ? document.body.innerText.length : 0"
                    )
                    if body and body > 20:
                        time.sleep(0.8)
                        return
            except Exception:
                pass
            time.sleep(0.2)
        raise RuntimeError("Timed out waiting for page readiness")

    def goto(self, path: str):
        url = path if path.startswith("http") else BASE + path
        self.call("Page.navigate", {"url": url})
        self.wait_ready()
        title = self.evaluate("document.title")
        current = self.evaluate("location.href")
        print(f"{self.name}: {title} -> {current}", flush=True)

    def login(self, email: str, password: str):
        self.goto("/login")
        email_js = json.dumps(email)
        password_js = json.dumps(password)
        self.evaluate(
            f"""
            (() => {{
                document.querySelector('#email').value = {email_js};
                document.querySelector('#password').value = {password_js};
                document.querySelector('form').requestSubmit();
                return true;
            }})()
            """
        )
        self.wait_ready()
        current = self.evaluate("location.href")
        if "/login" in current:
            raise RuntimeError(f"Login failed for {email}")

    def capture(self, filename: str, scroll_y=0):
        self.evaluate(f"window.scrollTo(0, {int(scroll_y)}); true")
        time.sleep(0.5)
        result = self.call(
            "Page.captureScreenshot",
            {
                "format": "png",
                "fromSurface": True,
                "captureBeyondViewport": False,
            },
        )
        output = SCREENSHOTS / filename
        output.write_bytes(base64.b64decode(result["data"]))
        print(f"SAVED {output}", flush=True)

    def close(self):
        try:
            self.ws.close()
        finally:
            self.process.terminate()
            try:
                self.process.wait(timeout=10)
            except subprocess.TimeoutExpired:
                self.process.kill()


def guest_screens():
    browser = CdpBrowser("guest", 9333)
    try:
        for path, filename in [
            ("/register", "01-dang-ky.png"),
            ("/login", "02-dang-nhap.png"),
            ("/", "03-trang-chu.png"),
            ("/cho", "04-cho-tim-kiem.png"),
            ("/tin/demo-samsung-a54-ou4n", "05-chi-tiet-tin.png"),
        ]:
            browser.goto(path)
            browser.capture(filename)
    finally:
        browser.close()


def buyer_screens():
    browser = CdpBrowser("buyer", 9334)
    try:
        browser.login("buyer@relic.test", "password")
        browser.goto("/yeu-thich")
        browser.capture("06-yeu-thich.png")

        browser.goto("/tin/demo-samsung-a54-ou4n")
        browser.evaluate(
            """
            (() => {
                const forms = [...document.querySelectorAll('form')];
                const form = forms.find(f => f.action.includes('/gio'));
                if (!form) return false;
                const buyNow = form.querySelector('[name=buy_now]');
                if (buyNow) buyNow.remove();
                form.requestSubmit();
                return true;
            })()
            """
        )
        browser.wait_ready()
        browser.goto("/gio-hang")
        browser.capture("07-gio-hang.png")
        browser.goto("/thanh-toan")
        browser.capture("08-thanh-toan.png")
        browser.goto("/don-hang")
        browser.capture("09-don-hang.png")
        browser.goto("/don-hang/2")
        browser.capture("10-chi-tiet-don.png")
        browser.goto("/tin-nhan")
        browser.capture("11-tin-nhan.png")
        browser.goto("/ho-so")
        browser.capture("12-ho-so.png")
        browser.goto("/ho-so/kyc")
        browser.capture("13-kyc-nguoi-dung.png")
    finally:
        browser.close()


def seller_screens():
    browser = CdpBrowser("seller", 9335)
    try:
        browser.login("seller@relic.test", "password")
        browser.goto("/ban")
        browser.capture("14-quan-ly-tin-ban.png")
        browser.goto("/ban/dang-tin")
        browser.capture("15-dang-tin-ban.png")
        browser.goto("/cua-hang/9")
        browser.capture("16-cua-hang-nguoi-ban.png")
    finally:
        browser.close()


def admin_screens():
    browser = CdpBrowser("admin", 9336)
    try:
        browser.login("admin@relic.test", "RelicAdmin!234")
        for path, filename in [
            ("/admin", "17-admin-tong-quan.png"),
            ("/admin/listings", "18-admin-tin-dang.png"),
            ("/admin/orders", "19-admin-don-hang.png"),
            ("/admin/users", "20-admin-nguoi-dung.png"),
            ("/admin/kyc", "21-admin-kyc.png"),
            ("/admin/finance", "22-admin-tai-chinh.png"),
            ("/admin/analytics", "23-admin-doanh-thu.png"),
            ("/admin/disputes", "24-admin-khieu-nai.png"),
        ]:
            browser.goto(path)
            browser.capture(filename)
    finally:
        browser.close()


if __name__ == "__main__":
    SCREENSHOTS.mkdir(parents=True, exist_ok=True)
    guest_screens()
    buyer_screens()
    seller_screens()
    admin_screens()
    print("CAPTURE_COMPLETE", flush=True)
