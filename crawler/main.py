import requests
from bs4 import BeautifulSoup
import csv
import time

# Function to get vulnerabilities from a single page with custom headers
def get_vulnerabilities_from_page(page_number):
    url = f"https://www.wordfence.com/threat-intel/vulnerabilities?page={page_number}#jump"
    
    # Custom headers to simulate a real browser request
    headers = {
        "User-Agent": "Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/91.0.4472.124 Safari/537.36"
    }
    
    # Send GET request with custom headers
    try:
        response = requests.get(url, headers=headers, timeout=10)  # timeout set to 10 seconds
        response.raise_for_status()  # Raise an exception for bad responses (4xx, 5xx)
    except (requests.exceptions.HTTPError, requests.exceptions.RequestException) as e:
        print(f"Failed to retrieve page {page_number}: {e}")
        return []
    
    soup = BeautifulSoup(response.text, 'html.parser')
    vulnerabilities = []
    
    # Find all rows in the table
    rows = soup.find_all('tr')
    for row in rows:
        try:
            title = row.find('td', {'class': 'vuln-card-title'}).get_text(strip=True)
            cve_link = row.find('a', href=True)
            cve_id = cve_link['href'].split('id=')[1] if cve_link else None
            cvss = row.find('span', {'class': 'cvss-score-badge'}).get_text(strip=True)
            researcher = row.find('a', {'class': 'researcher-name'}).get_text(strip=True)
            date = row.find('td', {'class': 'date-column'}).get_text(strip=True)
            
            vulnerability = {
                'title': title,
                'cve_id': cve_id,
                'cvss_score': cvss,
                'researcher': researcher,
                'date': date,
            }
            vulnerabilities.append(vulnerability)
        except AttributeError:
            continue
    
    return vulnerabilities

# Function to scrape all vulnerabilities from multiple pages
def scrape_all_vulnerabilities(total_pages=1294):
    all_vulnerabilities = []
    failed_pages = 0
    
    # Loop through all the pages
    for page_number in range(1, total_pages + 1):
        print(f"Scraping page {page_number}...")
        page_vulnerabilities = get_vulnerabilities_from_page(page_number)
        
        if page_vulnerabilities:
            all_vulnerabilities.extend(page_vulnerabilities)
            failed_pages = 0  # reset the failure counter
        else:
            failed_pages += 1
            print(f"Failed to scrape page {page_number}.")
        
        # Stop if too many pages failed
        if failed_pages > 5:
            print("Too many failed attempts. Stopping the script.")
            break
        
        # Add delay to prevent overwhelming the server
        time.sleep(3)  # Increase delay if necessary (3 seconds)
        
    return all_vulnerabilities

# Function to save vulnerabilities to a CSV file
def save_to_csv(vulnerabilities, filename="vulnerabilities.csv"):
    # Define CSV headers
    headers = ['title', 'cve_id', 'cvss_score', 'researcher', 'date']
    
    # Write data to CSV file
    with open(filename, 'w', newline='', encoding='utf-8') as file:
        writer = csv.DictWriter(file, fieldnames=headers)
        writer.writeheader()
        writer.writerows(vulnerabilities)
    
    print(f"Data saved to {filename}")

# Main function to scrape and save data
if __name__ == "__main__":
    # Start scraping vulnerabilities from the first page to total_pages (1294 by default)
    vulnerabilities = scrape_all_vulnerabilities(total_pages=10)  # Limit to 10 pages for testing
    
    if vulnerabilities:
        save_to_csv(vulnerabilities)
    else:
        print("No vulnerabilities found.")
