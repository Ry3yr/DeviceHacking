

from selenium import webdriver
from selenium.webdriver.chrome.options import Options
import time
import json
import os
from datetime import datetime

# Create timestamp for filenames
timestamp = datetime.now().strftime("%Y%m%d_%H%M%S")

# Set path to Brave
brave_path = "C:\\Program Files\\BraveSoftware\\Brave-Browser\\Application\\brave.exe"

# Configure options - ADD NETWORK LOGGING
options = Options()
options.binary_location = brave_path
options.set_capability('goog:loggingPrefs', {'performance': 'ALL'})

# Create driver
driver = webdriver.Chrome(options=options)

print("Opening Brave browser...")
driver.get("http://192.168.0.1")

# Save initial page
with open(f'login_page_{timestamp}.html', 'w', encoding='utf-8') as f:
    f.write(driver.page_source)
print(f"✓ Saved login page to login_page_{timestamp}.html")

print("\n=== MANUAL LOGIN REQUIRED ===")
print("Please:")
print("1. Enter your password in the browser")
print("2. Click the login button")
print("3. Wait for page to load completely")
print("4. Then come back here and press Enter")

input("\nPress Enter AFTER you've logged in...")

print("\n=== CAPTURING NETWORK TRAFFIC ===")

# Get all performance logs
logs = driver.get_log('performance')
print(f"Found {len(logs)} performance entries")

# Save raw logs
with open(f'raw_logs_{timestamp}.json', 'w', encoding='utf-8') as f:
    json.dump(logs, f, indent=2, default=str)
print(f"✓ Saved raw logs to raw_logs_{timestamp}.json")

# Filter for network requests
api_calls = []
for entry in logs:
    try:
        log = json.loads(entry['message'])['message']
        
        # Look for network requests
        if log.get('method') == 'Network.requestWillBeSent':
            request = log['params']['request']
            url = request.get('url', '')
            
            # Look for CGI/API calls
            if 'cgi-bin' in url:
                api_calls.append({
                    'url': url,
                    'method': request.get('method'),
                    'postData': request.get('postData'),
                    'headers': request.get('headers', {}),
                    'timestamp': entry.get('timestamp', '')
                })
    except Exception as e:
        continue

print(f"\nFound {len(api_calls)} CGI/API calls")

# Save API calls to file
with open(f'api_calls_{timestamp}.json', 'w', encoding='utf-8') as f:
    json.dump(api_calls, f, indent=2, default=str)
print(f"✓ Saved API calls to api_calls_{timestamp}.json")

# Create a human-readable summary
with open(f'api_summary_{timestamp}.txt', 'w', encoding='utf-8') as f:
    f.write(f"=== API CALLS CAPTURED AT {timestamp} ===\n")
    f.write(f"Total API calls: {len(api_calls)}\n\n")
    
    for i, call in enumerate(api_calls, 1):
        f.write(f"\n{'='*60}\n")
        f.write(f"API CALL #{i}\n")
        f.write(f"{'='*60}\n")
        f.write(f"URL: {call['url']}\n")
        f.write(f"Method: {call['method']}\n")
        f.write(f"Time: {call.get('timestamp', 'N/A')}\n")
        
        if call.get('postData'):
            f.write("\nREQUEST BODY:\n")
            try:
                # Try to parse as JSON
                data = json.loads(call['postData'])
                f.write(json.dumps(data, indent=2))
                
                # Check if this is a login request
                if data.get('module') == 'authenticator' and data.get('action') == 1:
                    f.write("\n\n✓ THIS IS THE LOGIN REQUEST!\n")
                    f.write(f"Digest used: {data.get('data', {}).get('digest', 'N/A')}")
            except:
                f.write(call['postData'])
        
        f.write("\n\nHEADERS:\n")
        for key, value in call.get('headers', {}).items():
            f.write(f"  {key}: {value}\n")

print(f"✓ Saved API summary to api_summary_{timestamp}.txt")

# Display summary
print("\n=== API CALLS SUMMARY ===")
for i, call in enumerate(api_calls, 1):
    print(f"\nCall #{i}: {call['method']} {call['url']}")
    if call.get('postData'):
        try:
            data = json.loads(call['postData'])
            print(f"  Module: {data.get('module', 'N/A')}")
            print(f"  Action: {data.get('action', 'N/A')}")
            
            # Highlight login request
            if data.get('module') == 'authenticator' and data.get('action') == 1:
                print("  ⭐ LOGIN REQUEST FOUND!")
                digest = data.get('data', {}).get('digest', 'N/A')
                print(f"  Digest: {digest}")
                
                # Save login details separately
                login_data = {
                    'timestamp': timestamp,
                    'url': call['url'],
                    'method': call['method'],
                    'request': data,
                    'digest': digest
                }
                with open(f'login_request_{timestamp}.json', 'w', encoding='utf-8') as f:
                    json.dump(login_data, f, indent=2)
                print(f"  ✓ Saved login request to login_request_{timestamp}.json")
        except:
            print(f"  Raw data: {call['postData'][:100]}...")

print("\n=== SAVING CURRENT PAGE ===")

# Save current page after login
current_url = driver.current_url
print(f"Current URL: {current_url}")
print(f"Page title: {driver.title}")

# Save HTML
with open(f'page_after_login_{timestamp}.html', 'w', encoding='utf-8') as f:
    f.write(driver.page_source)
print(f"✓ Saved page HTML to page_after_login_{timestamp}.html")

# Take screenshot
screenshot_file = f'screenshot_{timestamp}.png'
driver.save_screenshot(screenshot_file)
print(f"✓ Saved screenshot to {screenshot_file}")

print("\n=== NOW LET'S FIND STORAGE INFO ===")

# Try to navigate to storage page if we can find the link
page_source = driver.page_source

# Look for storage-related links
import re
storage_links = re.findall(r'href="([^"]*[Ss]torage[^"]*)"', page_source)
if storage_links:
    print(f"\nFound storage links: {storage_links}")
    
    # Save storage links
    with open(f'storage_links_{timestamp}.txt', 'w', encoding='utf-8') as f:
        for link in storage_links:
            f.write(f"{link}\n")
    print(f"✓ Saved storage links to storage_links_{timestamp}.txt")

# Look for SD card status
if 'icon-top-sdcard' in page_source:
    print("✓ SD card icon found (card is plugged in)")

# Look for any storage data in the page
storage_data = re.findall(r'>([^<>]*\s*(?:GB|MB|gb|mb)[^<>]*)<', page_source)
if storage_data:
    print("\nFound storage-related data in page:")
    for data in set(storage_data):  # Remove duplicates
        clean_data = data.strip()
        if clean_data:
            print(f"  - {clean_data}")

# Save all found storage info
storage_info = {
    'timestamp': timestamp,
    'url': current_url,
    'storage_links': storage_links,
    'sd_card_detected': 'icon-top-sdcard' in page_source,
    'storage_data_found': storage_data,
    'page_contains_gb_mb': any(x in page_source.lower() for x in ['gb', 'mb'])
}

with open(f'storage_info_{timestamp}.json', 'w', encoding='utf-8') as f:
    json.dump(storage_info, f, indent=2)
print(f"✓ Saved storage info to storage_info_{timestamp}.json")

print("\n=== SUMMARY OF FILES SAVED ===")
files = [
    f'login_page_{timestamp}.html',
    f'raw_logs_{timestamp}.json',
    f'api_calls_{timestamp}.json',
    f'api_summary_{timestamp}.txt',
    f'page_after_login_{timestamp}.html',
    screenshot_file,
    f'storage_links_{timestamp}.txt',
    f'storage_info_{timestamp}.json'
]

for file in files:
    if os.path.exists(file):
        size = os.path.getsize(file)
        print(f"✓ {file} ({size} bytes)")

# Check if login request was captured
login_file = f'login_request_{timestamp}.json'
if os.path.exists(login_file):
    print(f"\n⭐ MOST IMPORTANT FILE: {login_file}")
    print("This contains the exact login API call!")
    
    # Show login details
    with open(login_file, 'r') as f:
        login_data = json.load(f)
    print(f"\nLogin URL: {login_data.get('url')}")
    print(f"Login method: {login_data.get('method')}")
    print(f"Digest: {login_data.get('digest')}")

print("\n=== NEXT STEPS ===")
print("1. Check api_summary_*.txt for the login API call")
print("2. Look for a request with module='authenticator' and action=1")
print("3. That's the exact login request we need to replicate!")

print("\nBrowser will remain open for 30 seconds...")
time.sleep(30)
driver.quit()
print("\nDone! All files have been saved.")