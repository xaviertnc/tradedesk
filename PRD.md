1. Project Goal
To create a web-based testing application that allows a user to perform batch foreign exchange (FX) trades for a list of clients. The application will interface with the provided bank API, allowing the user to buy USD with ZAR while managing custom spreads for each client and setting a base OTC (Over-the-Counter) rate to ensure profitability.

2. Technology Stack
Front-end: HTML, Tailwind CSS, Vanilla JavaScript (no-build process)

Back-end: PHP

Database: SQLite

3. Client Management
3.1. CSV Import & Manual Entry
The application will support two methods for managing clients:

CSV Import (Primary Method):

Users can upload a CSV file (like DEALS.csv) to bulk-add or update clients.

The application will parse the CSV and map the columns to the database. Based on your file, the mapping will be:

Client -> name

Client CIF -> cif_number

Fixed Spread -> spread (The app will interpret this value, assuming 358 means 3.58%. This can be adjusted.)

Since the CSV does not contain account numbers, the UI will prompt the user to manually enter the ZAR and USD settlement accounts for any newly imported clients.

Manual Management: Users will also have a simple interface to add new clients one-by-one, or to edit the details (like account numbers or spread) of existing clients.

4. Core Application Workflow
Configuration: The user will first configure the application with their API credentials (Client ID, Secret, etc.), the base API URLs, and their desired master OTC rate.

Client Population: The user will import their clients using the CSV upload feature.

Authentication: The application will automatically fetch an OAuth access_token before making API calls.

Batch Trade Execution:

The user selects clients from the imported list and specifies the ZAR amount for each trade.

The application initiates the batch, performing the following steps for each client:
a.  Pre-Trade Check: Call BalanceEnquiry API.
b.  Create Quote: Call CreateQuote API.
c.  Fetch Latest Rate: Call GetLatestQuote API to get the bankRate.
d.  Profitability Calculation: Compare the bankRate against the calculated clientRate (OTC Rate + client's spread).
e.  Book Deal: If profitable, call BookQuotedDeal API.
f.  Log Transaction: Save the outcome to the trades database table.

Status Monitoring & History: A dashboard will show the real-time progress of the batch and a persistent history of all trades.

5. Database Schema (SQLite)
config - Stores API credentials and global settings.
| Column | Type | Description |
| :--- | :--- | :--- |
| id | INTEGER | Primary Key |
| api_base_url | TEXT | Base URL for the API (e.g., QA environment) |
| auth_url | TEXT | URL for the OAuth token endpoint |
| client_id | TEXT | Your API Client ID |
| client_secret| TEXT | Your API Client Secret |
| username | TEXT | API Username |
| password | TEXT | API Password |
| otc_rate | REAL | Your base counter-party rate for USD/ZAR |
| access_token | TEXT | Cached API access token |
| token_expiry | INTEGER | Timestamp for when the token expires |

clients - Stores information about your clients.
| Column | Type | Description |
| :--- | :--- | :--- |
| id | INTEGER | Primary Key |
| name | TEXT | Client's full name |
| cif_number | TEXT | Client's CIF number |
| zar_account | TEXT | Client's ZAR settlement account (to be entered manually) |
| usd_account | TEXT | Client's USD settlement account (to be entered manually) |
| spread | REAL | Custom spread in percent (e.g., 0.0358 for 3.58%) |

trades - Logs every attempted transaction.
| Column | Type | Description |
| :--- | :--- | :--- |
| id | INTEGER | Primary Key |
| client_id | INTEGER | Foreign Key to the clients table |
| status | TEXT | e.g., 'SUCCESS', 'FAILED', 'PENDING' |
| status_message| TEXT | Error message if the trade failed |
| bank_quote_id| TEXT | The quote ID from the API |
| bank_rate | REAL | The final rate provided by the bank |
| client_rate | REAL | The rate quoted to your client (OTC + spread) |
| amount_zar | REAL | The amount of ZAR traded |
| bank_trxn_id| TEXT | The final deal transaction ID from the API |
| created_at | TEXT | Timestamp of the trade |

6. Next Steps
This revised plan is now more aligned with your operational needs. The next step is to begin development. I will start by creating the initial file structure and the backend logic required to support the Configuration and Client Import features.

Please let me know if this updated plan meets your approval, and I will proceed with generating the code.