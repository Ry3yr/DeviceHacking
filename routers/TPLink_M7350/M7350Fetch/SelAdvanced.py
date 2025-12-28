from selenium import webdriver
from selenium.webdriver.chrome.options import Options
import time
import json
import os
from datetime import datetime

# Create unified log file with timestamp
timestamp = datetime.now().strftime("%Y%m%d_%H%M%S")
log_file = f'unified_log_{timestamp}.jsonl'  # JSON Lines format for streaming
print(f"All logs will be saved to: {log_file}")

# Set path to Brave
brave_path = "C:\\Program Files\\BraveSoftware\\Brave-Browser\\Application\\brave.exe"

# Configure options
options = Options()
options.binary_location = brave_path
options.set_capability('goog:loggingPrefs', {'performance': 'ALL'})

# Create driver
driver = webdriver.Chrome(options=options)

def log_event(event_type, data, metadata=None):
    """Log an event to the unified log file"""
    log_entry = {
        'timestamp': datetime.now().isoformat(),
        'event_type': event_type,
        'data': data
    }
    
    if metadata:
        log_entry['metadata'] = metadata
    
    with open(log_file, 'a', encoding='utf-8') as f:
        f.write(json.dumps(log_entry, default=str) + '\n')

print("Opening Brave browser...")
log_event('browser_start', {'url': 'http://192.168.0.1'})

driver.get("http://192.168.0.1")

# Save initial page
initial_page = driver.page_source
log_event('initial_page', {
    'url': driver.current_url,
    'title': driver.title,
    'html_snippet': initial_page[:1000] + '...' if len(initial_page) > 1000 else initial_page,
    'html_length': len(initial_page)
})

print("\n=== MANUAL LOGIN REQUIRED ===")
print("Please:")
print("1. Enter your password in the browser")
print("2. Click the login button")
print("3. Wait for page to load completely")
print("4. Then come back here and press Enter")

input("\nPress Enter AFTER you've logged in...")

# Function to capture and log all network traffic
def capture_all_traffic():
    """Capture all performance logs and save to unified log"""
    logs = driver.get_log('performance')
    
    for entry in logs:
        try:
            log_event('performance_log', entry)
            
            # Parse network messages
            log_message = json.loads(entry['message'])['message']
            
            # Capture different types of network events
            if log_message.get('method') == 'Network.requestWillBeSent':
                request = log_message['params']['request']
                log_event('network_request', {
                    'type': 'request',
                    'url': request.get('url'),
                    'method': request.get('method'),
                    'headers': request.get('headers', {}),
                    'post_data': request.get('postData'),
                    'timestamp': entry.get('timestamp')
                })
                
            elif log_message.get('method') == 'Network.responseReceived':
                response = log_message['params']['response']
                log_event('network_response', {
                    'type': 'response',
                    'url': response.get('url'),
                    'status': response.get('status'),
                    'headers': response.get('headers', {}),
                    'timestamp': entry.get('timestamp')
                })
                
            elif log_message.get('method') == 'Network.loadingFinished':
                log_event('network_loading_finished', {
                    'type': 'loading_finished',
                    'request_id': log_message['params'].get('requestId'),
                    'timestamp': entry.get('timestamp')
                })
                
        except Exception as e:
            log_event('parse_error', {
                'error': str(e),
                'raw_entry': entry
            })
    
    return len(logs)

print("\n=== CAPTURING NETWORK TRAFFIC (PRE-LOGIN) ===")
count = capture_all_traffic()
print(f"Captured {count} performance entries")

# Save current state after login
log_event('post_login_state', {
    'url': driver.current_url,
    'title': driver.title,
    'cookies': driver.get_cookies(),
    'page_source_length': len(driver.page_source)
})

print("\n=== CONTINUOUS MONITORING STARTED ===")
print("Now capturing ALL network traffic and user interactions...")
print("Press Ctrl+C in this terminal to stop monitoring")

try:
    last_url = driver.current_url
    interaction_count = 0
    
    while True:
        # Monitor for URL changes
        current_url = driver.current_url
        if current_url != last_url:
            log_event('navigation', {
                'from': last_url,
                'to': current_url,
                'title': driver.title
            })
            last_url = current_url
        
        # Periodically capture network traffic
        time.sleep(2)  # Capture every 2 seconds
        
        count = capture_all_traffic()
        if count > 0:
            log_event('periodic_capture', {
                'entries_captured': count,
                'current_url': current_url
            })
        
        # Capture page state periodically
        interaction_count += 1
        if interaction_count % 5 == 0:  # Every 10 seconds
            log_event('periodic_snapshot', {
                'url': current_url,
                'title': driver.title,
                'window_size': driver.get_window_size(),
                'page_source_snippet': driver.page_source[:500] if driver.page_source else ''
            })
        
        print(f".", end="", flush=True)  # Progress indicator

except KeyboardInterrupt:
    print("\n\n=== MONITORING STOPPED BY USER ===")

# Final comprehensive capture
print("\n=== FINAL CAPTURE ===")
final_logs = driver.get_log('performance')
log_event('final_capture', {
    'performance_entries_count': len(final_logs),
    'final_url': driver.current_url,
    'final_title': driver.title
})

# Save all cookies
cookies = driver.get_cookies()
log_event('cookies', {
    'count': len(cookies),
    'cookies': cookies
})

# Save final page state
log_event('final_page_state', {
    'url': driver.current_url,
    'title': driver.title,
    'page_source_length': len(driver.page_source),
    'html_snippet': driver.page_source[:2000] if driver.page_source else ''
})

print("\n=== GENERATING SUMMARY ===")

# Read all logs and create summary
all_events = []
with open(log_file, 'r', encoding='utf-8') as f:
    for line in f:
        if line.strip():
            all_events.append(json.loads(line))

# Count event types
event_counts = {}
api_calls = []
login_attempts = []
for event in all_events:
    event_type = event['event_type']
    event_counts[event_type] = event_counts.get(event_type, 0) + 1
    
    # Extract API calls
    if event_type == 'network_request' and 'data' in event:
        if 'cgi-bin' in event['data'].get('url', ''):
            api_calls.append(event)
    
    # Extract login attempts
    if event_type == 'network_request' and 'data' in event:
        post_data = event['data'].get('post_data', '')
        if 'authenticator' in str(post_data):
            login_attempts.append(event)

# Create summary file
summary_file = f'summary_{timestamp}.txt'
with open(summary_file, 'w', encoding='utf-8') as f:
    f.write(f"=== UNIFIED LOG SUMMARY ===\n")
    f.write(f"Log file: {log_file}\n")
    f.write(f"Total events: {len(all_events)}\n")
    f.write(f"Monitoring duration: From {all_events[0]['timestamp'] if all_events else 'N/A'} to {datetime.now().isoformat()}\n\n")
    
    f.write("=== EVENT COUNTS ===\n")
    for event_type, count in sorted(event_counts.items()):
        f.write(f"{event_type}: {count}\n")
    
    f.write(f"\n=== API CALLS FOUND: {len(api_calls)} ===\n")
    for i, api in enumerate(api_calls, 1):
        f.write(f"\n{i}. {api['data'].get('method', 'N/A')} {api['data'].get('url', 'N/A')}\n")
        if api['data'].get('post_data'):
            f.write(f"   Data: {api['data'].get('post_data')[:200]}...\n")
    
    f.write(f"\n=== LOGIN ATTEMPTS: {len(login_attempts)} ===\n")
    for i, login in enumerate(login_attempts, 1):
        f.write(f"\n{i}. {login['timestamp']}\n")
        f.write(f"   URL: {login['data'].get('url', 'N/A')}\n")
        f.write(f"   Data: {login['data'].get('post_data', 'N/A')}\n")
    
    f.write(f"\n=== NAVIGATION HISTORY ===\n")
    nav_events = [e for e in all_events if e['event_type'] == 'navigation']
    for nav in nav_events:
        f.write(f"{nav['timestamp']}: {nav['data'].get('from', 'N/A')} -> {nav['data'].get('to', 'N/A')}\n")
    
    f.write(f"\n=== FILE INFO ===\n")
    f.write(f"Unified log: {log_file} ({os.path.getsize(log_file)} bytes)\n")
    f.write(f"Contains {len(all_events)} events\n")

print(f"✓ All data saved to: {log_file}")
print(f"✓ Summary saved to: {summary_file}")
print(f"✓ Total events captured: {len(all_events)}")
print(f"✓ API calls found: {len(api_calls)}")
print(f"✓ Login attempts found: {len(login_attempts)}")

# Keep browser open for final inspection
print("\nBrowser will remain open for 60 seconds...")
print(f"Final URL: {driver.current_url}")
print(f"Final title: {driver.title}")

time.sleep(60)
driver.quit()

print("\n=== MONITORING COMPLETE ===")
print(f"1. Complete log: {log_file} (JSONL format - one JSON object per line)")
print(f"2. Human-readable summary: {summary_file}")
print("\nTo read the log file:")
print(f"- Use: tail -f {log_file} for real-time viewing")
print(f"- Or: cat {log_file} | python -m json.tool for pretty printing")
print(f"- Or import in Python: import json; [json.loads(line) for line in open('{log_file}')]")