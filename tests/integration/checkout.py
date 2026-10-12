#!/usr/bin/env python3
"""Exercise real classic AJAX and Store API checkout before payment, in local wp-env."""
from pathlib import Path
import argparse
import concurrent.futures
import http.cookiejar
import json
import os
import re
import subprocess
import urllib.error
import urllib.parse
import urllib.request

parser = argparse.ArgumentParser(description=__doc__)
parser.add_argument("--account-checks-only", action="store_true", help="Run the six admin/logout/notification checks on existing local fixtures")
arguments = parser.parse_args()

ROOT = Path(__file__).resolve().parents[2]
BASE = os.environ.get("TCB_TEST_URL", "http://localhost:8894").rstrip("/")
SLUG = "talistech-checkout-blocker-for-woocommerce"
ENV_ROOT = Path(os.environ.get("TCB_ENV_DIRECTORY", str(ROOT)))
assert urllib.parse.urlparse(BASE).hostname in ("localhost", "127.0.0.1"), "Checkout checks must target a local environment"

def wp(code):
    for attempt in range(3):
        result = subprocess.run([str(ROOT / "node_modules/.bin/wp-env"), "run", "cli", "wp", "eval", code], cwd=ENV_ROOT, text=True, capture_output=True)
        # wp-env checks WordPress.org before executing the container command.
        # Parallel wp-env cache writes can also make its initialization lookup fail.
        # Retry only errors before PHP starts; never replay a PHP/test failure.
        pre_execution_errors = ("ERR_SOCKET_CLOSED_BEFORE_CONNECTION", "Environment not initialized. Run `wp-env start` first.")
        if result.returncode and any(error in result.stdout + result.stderr for error in pre_execution_errors) and attempt < 2:
            print("Retrying wp-env after a pre-execution tooling failure.", flush=True)
            continue
        break
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
    ("unicode exact name", {"name_rules": "Γιάννης Παπαδόπουλος"}, {"first_name": "ΓΙΆΝΝΗΣ", "last_name": "ΠΑΠΑΔΌΠΟΥΛΟΣ"}, None, True),
    ("unicode wildcard name", {"name_rules": "*Иванов"}, {"first_name": "ИВАН", "last_name": "ИВАНОВ"}, None, True),
    ("wildcard name", {"name_rules": "* de smet"}, {"last_name": "De Smet"}, None, True),
    ("normalized address", {"address_rules": "street 1|1000|be"}, {"address_1": "Street   1", "postcode": "100 0"}, None, True),
    ("wildcard address", {"address_rules": "street 1|*|be"}, {}, None, True),
    ("unicode exact address", {"address_rules": "Улица 1|1000|be"}, {"address_1": "УЛИЦА 1"}, None, True),
    ("allowed account", {}, {}, "tcb-test-customer", False),
    ("account email bypass", {"email_rules": "blocked@example.com"}, {"email": "different@example.net"}, "tcb-test-customer", True),
    ("account domain bypass", {"email_rules": "@example.com"}, {"email": "different@example.net"}, "tcb-test-customer", True),
    ("account wildcard bypass", {"email_rules": "blocked*@example.com"}, {"email": "different@example.net"}, "tcb-test-customer", True),
]
count = 0
for hpos in ([] if arguments.account_checks_only else [False, True]):
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

# Pending/failed orders use separate payment entrypoints. Track the real COD gateway
# through the local-only fixture filter, so an error response cannot hide a payment.
wp("if(wp_get_environment_type()!=='local'){throw new Exception('Local only');} "
   "$user=get_user_by('login','tcb-test-alt-account');"
   "if(!$user){$id=wp_insert_user(['user_login'=>'tcb-test-alt-account','user_email'=>'alternate@example.net',"
   "'user_pass'=>'tcb-local-checkout-1!','role'=>'customer']);if(is_wp_error($id)){throw new Exception($id->get_error_message());}}")

def create_pay_order(address, owner, initial_status):
    payload = json.dumps({"billing": address, "owner": owner, "status": initial_status})
    code = "if(wp_get_environment_type()!=='local'){throw new Exception('Local only');} "
    code += "$data=json_decode(" + json.dumps(payload) + ",true);"
    code += "$owner=$data['owner']?get_user_by('login',$data['owner']):false;"
    code += "$order=wc_create_order(['customer_id'=>$owner?$owner->ID:0]);"
    code += "if(is_wp_error($order)){throw new Exception($order->get_error_message());}"
    code += "$order->set_billing_address($data['billing']);$order->set_shipping_address($data['billing']);"
    code += "$order->add_product(wc_get_product((int)get_option('tcb_test_product_id')),1);"
    code += "$order->calculate_totals();$order->set_status($data['status']);$order->save();"
    code += "echo 'TCB_RESULT:'.json_encode(['id'=>$order->get_id(),'key'=>$order->get_order_key(),'url'=>$order->get_checkout_payment_url()]);"
    return wp(code)

def payment_path(order):
    parts = urllib.parse.urlparse(order["url"])
    return parts.path + ("?" + parts.query if parts.query else "")

pay_cases = [
    ("allowed guest", {}, {}, None, None, False),
    ("blocked guest email", {"email_rules": "blocked@example.com"}, {"email": "blocked@example.com"}, None, None, True),
    ("blocked guest name", {"name_rules": "jan jansen"}, {}, None, None, True),
    ("blocked guest address", {"address_rules": "street 1|1000|be"}, {}, None, None, True),
    ("allowed account", {}, {}, "tcb-test-customer", "tcb-test-customer", False),
    ("blocked account alternate billing", {"email_rules": "blocked@example.com"}, {}, "tcb-test-customer", "tcb-test-customer", True),
    ("blocked owner logged out", {"email_rules": "blocked@example.com"}, {}, "tcb-test-customer", None, True),
    ("blocked owner signed in elsewhere", {"email_rules": "blocked@example.com"}, {}, "tcb-test-customer", "tcb-test-alt-account", True),
    ("blocked account forced logout", {"email_rules": "blocked@example.com", "force_logout": 1}, {}, "tcb-test-customer", "tcb-test-customer", True),
]
for hpos in ([] if arguments.account_checks_only else [False, True]):
    for mode in ["classic-pay", "blocks-pay"]:
        for initial_status in ["pending", "failed"]:
            for label, settings, changes, owner, login, blocked in pay_cases:
                # Registered Store API orders require their owner to authenticate.
                # Classic pay POST only verifies the nonce/key, so test its extra paths.
                if mode == "blocks-pay" and (login != owner or settings.get("force_logout")):
                    continue
                configure({}, hpos)
                address = {**billing, **changes}
                order = create_pay_order(address, owner, initial_status)
                browser = Browser(login)
                if mode == "classic-pay":
                    nonce_order = order
                    if owner and login != owner:
                        # A payment nonce is scoped to its action/session, not an order.
                        # Use a real guest payment form to exercise direct registered-order POSTs.
                        nonce_order = create_pay_order(billing, None, "pending")
                    response_status, headers, page = browser.request(payment_path(nonce_order))
                    assert isinstance(page, str), ("Pay form", response_status, page)
                    nonce_match = re.search(r'name="woocommerce-pay-nonce"\s+value="([^"]+)"', page)
                    assert nonce_match, ("Pay form nonce missing", label, response_status, page)
                    configure({**settings, "notice_text": "TCB checkout blocked"}, hpos)
                    response_status, headers, result = browser.request(payment_path(order), {
                        "woocommerce_pay": "1", "payment_method": "cod", "woocommerce-pay-nonce": nonce_match.group(1),
                        "terms": "on", "terms-field": "1",
                    }, form=True)
                else:
                    response_status, headers, cart = browser.request("/wp-json/wc/store/v1/cart")
                    nonce = headers.get("Nonce")
                    assert response_status == 200 and nonce, ("Existing order cart nonce", response_status, cart)
                    configure({**settings, "notice_text": "TCB checkout blocked"}, hpos)
                    response_status, headers, result = browser.request("/wp-json/wc/store/v1/checkout/" + str(order["id"]), {
                        "key": order["key"], "billing_email": address["email"],
                        "billing_address": address, "shipping_address": address, "payment_method": "cod", "payment_data": [],
                    }, headers={"Nonce": nonce})
                state = wp("$order=wc_get_order(" + str(order["id"]) + ");echo 'TCB_RESULT:'.json_encode(["
                           "'status'=>$order->get_status(),'gateway_calls'=>(int)$order->get_meta('_tcb_test_gateway_calls')]);")
                if blocked:
                    assert state == {"status": initial_status, "gateway_calls": 0}, (hpos, mode, label, state, result)
                    if mode == "blocks-pay":
                        assert response_status == 400 and "TCB checkout blocked" in json.dumps(result), (response_status, result)
                        assert "payment_result" not in result, result
                else:
                    assert state["gateway_calls"] == 1 and state["status"] not in ("pending", "failed"), (hpos, mode, label, state, result)
                    if mode == "blocks-pay":
                        assert response_status == 200 and result.get("payment_result", {}).get("payment_status") == "success", (response_status, result)
                count += 1
                print(f"PASS HPOS={'on' if hpos else 'off'} {mode} {initial_status}: {label}; gateway calls={state['gateway_calls']}", flush=True)

# Verify actual administrator pages, including privacy text added on admin_init.
configure({}, True)
admin_browser = Browser("tcb-test-admin")
status, headers, page = admin_browser.request("/wp-admin/options-general.php?page=talistech-checkout-blocker")
assert status == 200 and "Account access" in page and "Independent extension for WooCommerce" in page
count += 1
status, headers, page = admin_browser.request("/wp-admin/privacy-policy-guide.php")
assert status == 200 and "Talistech Checkout Blocker for WooCommerce" in page and "keyed IP hashes" in page
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
