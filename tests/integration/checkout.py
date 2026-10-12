#!/usr/bin/env python3
"""Exercise real classic AJAX and Store API checkout before payment, in local wp-env."""
from pathlib import Path
import concurrent.futures
import http.cookiejar
import json
import os
import re
import subprocess
import urllib.error
import urllib.parse
import urllib.request

ROOT = Path(__file__).resolve().parents[2]
BASE = os.environ.get("TCB_TEST_URL", "http://localhost:8894").rstrip("/")
SLUG = "checkout-blocker-by-talistech"
ENV_ROOT = Path(os.environ.get("TCB_ENV_DIRECTORY", str(ROOT)))
assert urllib.parse.urlparse(BASE).hostname in ("localhost", "127.0.0.1"), "Checkout checks must target a local environment"

def wp(code):
    result = subprocess.run([str(ROOT / "node_modules/.bin/wp-env"), "run", "cli", "wp", "eval", code], cwd=ENV_ROOT, text=True, capture_output=True)
    if result.returncode:
        raise AssertionError(result.stdout + result.stderr)
    for line in result.stdout.splitlines():
        clean = re.sub(r"\x1b\[[0-9;]*m", "", line)
        if clean.startswith("TCB_RESULT:"):
            return json.loads(clean[11:])
    return None

def configure(settings, hpos):
    global _configured_hpos
    if hpos != _configured_hpos:
        # WooCommerce caches sync counts within a request. Run sync and the storage switch
        # in separate requests so the switch sees the newly synchronized database.
        wp("update_option('woocommerce_custom_orders_table_data_sync_enabled','yes');")
        wp("$sync=wc_get_container()->get(Automattic\\WooCommerce\\Internal\\DataStores\\Orders\\DataSynchronizer::class);while($batch=$sync->get_next_batch_to_process(100)){$sync->process_batch($batch);}")
        wp("update_option('woocommerce_custom_orders_table_enabled','" + ("yes" if hpos else "no") + "');")
        wp("update_option('woocommerce_custom_orders_table_data_sync_enabled','no');")
        _configured_hpos = hpos
    encoded = json.dumps(settings)
    # JSON is passed as a CLI argument, not interpolated into shell command text.
    code = "if(wp_get_environment_type()!=='local'){throw new Exception('Local only');} "
    code += "$data=json_decode(" + json.dumps(encoded) + ",true); "
    code += "update_option('talistech_checkout_blocker_settings',array_merge(Talistech\\CheckoutBlocker\\Settings::defaults(),$data));"
    wp(code)

class Browser:
    def __init__(self, login=None):
        self.jar = http.cookiejar.CookieJar()
        self.client = urllib.request.build_opener(urllib.request.HTTPCookieProcessor(self.jar))
        if login:
            self.request("/wp-login.php")
            self.request("/wp-login.php", {"log": login, "pwd": "tcb-local-checkout-1!", "redirect_to": BASE + "/", "testcookie": "1"}, form=True)
            assert self.logged_in(), "Fixture login failed"

    def logged_in(self):
        return any(cookie.name.startswith("wordpress_logged_in_") for cookie in self.jar)

    def request(self, path, data=None, form=False, headers=None):
        headers = dict(headers or {})
        if data is None:
            body = None
        elif form:
            body = urllib.parse.urlencode(data).encode()
            headers["Content-Type"] = "application/x-www-form-urlencoded"
        else:
            body = json.dumps(data).encode()
            headers["Content-Type"] = "application/json"
        request = urllib.request.Request(BASE + path, data=body, headers=headers)
        try:
            response = self.client.open(request, timeout=60)
        except urllib.error.HTTPError as error:
            response = error
        raw = response.read().decode()
        try:
            result = json.loads(raw)
        except json.JSONDecodeError:
            result = raw
        return response.status, response.headers, result

billing = {"first_name": "Jan", "last_name": "Jansen", "company": "", "address_1": "Street 1", "address_2": "", "city": "Brussels", "state": "", "postcode": "1000", "country": "BE", "email": "allowed@example.net", "phone": "0470123456"}
product = wp("echo 'TCB_RESULT:'.json_encode((int)get_option('tcb_test_product_id'));")
assert product, "Run safety.php to create checkout fixtures first"
_configured_hpos = wp("echo 'TCB_RESULT:'.json_encode(get_option('woocommerce_custom_orders_table_enabled')==='yes');")

def checkout(mode, changes, login=None):
    browser = Browser(login)
    address = {**billing, **changes}
    if mode == "blocks":
        status, headers, cart = browser.request("/wp-json/wc/store/v1/cart")
        nonce = headers.get("Nonce")
        assert status == 200 and nonce, ("Cart nonce", status, cart)
        status, headers, cart = browser.request("/wp-json/wc/store/v1/cart/add-item", {"id": product, "quantity": 1}, headers={"Nonce": nonce})
        assert status == 201 or status == 200, ("Add item", status, cart)
        nonce = headers.get("Nonce", nonce)
        return browser.request("/wp-json/wc/store/v1/checkout", {"billing_address": address, "shipping_address": address, "payment_method": "cod", "payment_data": []}, headers={"Nonce": nonce})
    browser.request("/?add-to-cart=" + str(product))
    status, headers, page = browser.request("/checkout/")
    assert isinstance(page, str), ("Classic page", status, page)
    match = re.search(r'name="woocommerce-process-checkout-nonce"\s+value="([^"]+)"', page)
    assert match, "Classic checkout nonce missing"
    data = {"billing_" + key: value for key, value in address.items()}
    data.update({"payment_method": "cod", "woocommerce-process-checkout-nonce": match.group(1), "terms": "on", "terms-field": "1", "_wp_http_referer": "/checkout/"})
    return browser.request("/?wc-ajax=checkout", data, form=True)

cases = [
    ("allowed guest", {}, {}, None, False),
    ("exact email", {"email_rules": "blocked@example.com"}, {"email": "blocked@example.com"}, None, True),
    ("email casing", {"email_rules": "blocked@example.com"}, {"email": "BLOCKED@EXAMPLE.COM"}, None, True),
    ("domain", {"email_rules": "@example.com"}, {"email": "guest@example.com"}, None, True),
    ("domain excludes subdomain", {"email_rules": "@example.com"}, {"email": "guest@shop.example.com"}, None, False),
    ("wildcard", {"email_rules": "test-*@example.net"}, {"email": "test-123@example.net"}, None, True),
    ("accented name", {"name_rules": "jose de smet"}, {"first_name": "José", "last_name": "De Smet"}, None, True),
    ("wildcard name", {"name_rules": "* de smet"}, {"last_name": "De Smet"}, None, True),
    ("normalized address", {"address_rules": "street 1|1000|be"}, {"address_1": "Street   1", "postcode": "100 0"}, None, True),
    ("wildcard address", {"address_rules": "street 1|*|be"}, {}, None, True),
    ("allowed account", {}, {}, "tcb-test-customer", False),
    ("account email bypass", {"email_rules": "blocked@example.com"}, {"email": "different@example.net"}, "tcb-test-customer", True),
    ("account domain bypass", {"email_rules": "@example.com"}, {"email": "different@example.net"}, "tcb-test-customer", True),
    ("account wildcard bypass", {"email_rules": "blocked*@example.com"}, {"email": "different@example.net"}, "tcb-test-customer", True),
]
count = 0
for hpos in [False, True]:
    for mode in ["classic", "blocks"]:
        for label, settings, changes, login, blocked in cases:
            configure({**settings, "notice_text": "TCB checkout blocked"}, hpos)
            status, headers, result = checkout(mode, changes, login)
            if blocked:
                assert "TCB checkout blocked" in json.dumps(result), (hpos, mode, label, status, result)
                assert mode != "blocks" or status == 400, (status, result)
                assert mode != "classic" or result.get("result") == "failure", result
                assert not isinstance(result, dict) or "payment_result" not in result, result
            else:
                assert status == 200, (hpos, mode, label, status, result)
                if mode == "classic":
                    assert result.get("result") == "success", (label, result)
                else:
                    assert result.get("payment_result", {}).get("payment_status") == "success", (label, result)
                    assert result.get("status") not in ("checkout-draft", "failed"), result
            count += 1
            print(f"PASS HPOS={'on' if hpos else 'off'} {mode}: {label}", flush=True)

# Verify actual administrator pages, including privacy text added on admin_init.
configure({}, True)
admin_browser = Browser("tcb-test-admin")
status, headers, page = admin_browser.request("/wp-admin/options-general.php?page=talistech-checkout-blocker")
assert status == 200 and "Account access" in page and "WooCommerce protection by Talistech" in page
count += 1
status, headers, page = admin_browser.request("/wp-admin/privacy-policy-guide.php")
assert status == 200 and "Checkout Blocker by Talistech" in page and "keyed IP hashes" in page
count += 1

# Force logout is observed via real cookies on a front-end request.
configure({"email_rules": "@example.com", "force_logout": 1}, True)
for login, protected in [("tcb-test-admin", True), ("tcb-test-manager", True)]:
    browser = Browser(login)
    browser.request("/")
    assert browser.logged_in() == protected, login
    count += 1
configure({"force_logout": 0}, True)
browser = Browser("tcb-test-customer")
configure({"email_rules": "blocked@example.com", "force_logout": 1, "logout_redirect": BASE + "/"}, True)
browser.request("/")
assert not browser.logged_in(), "Blocked customer was not logged out"
count += 1

# Concurrent notification reservations use the real database lock.
wp("delete_option('talistech_checkout_blocker_notifications');delete_option('talistech_checkout_blocker_notification_lock');")
configure({"email_rules": "@example.com", "notify_admins": 1}, True)
def reserve(i):
    wp("add_filter('pre_wp_mail',static fn()=>true);(new Talistech\\CheckoutBlocker\\Notifications())->maybeSend(['type'=>'email','rule'=>'@example.com','value'=>'blocked@example.com'],['email'=>'blocked@example.com','ip'=>'192.0.2.1']);")
with concurrent.futures.ThreadPoolExecutor(max_workers=4) as pool:
    list(pool.map(reserve, range(8)))
state = wp("echo 'TCB_RESULT:'.json_encode(get_option('talistech_checkout_blocker_notifications'));")
assert state["count"] == 1, state
count += 1
configure({}, True)
print(f"Checkout HTTP integration: {count} checks passed.")
