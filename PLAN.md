FX Trading System - Project Plan
This plan outlines the necessary tasks to build the batch trading, validation, and history features for the FX Batch Trader application.

🏗️ Phase 1: Backend Refactoring & Setup
The first step is to refactor the backend to make it more modular and easier to maintain as we add more complex features.

1.1. [x] - Create CapitecApiService.php:
1.1.1. [x] - Create a new file named php/services/CapitecApiService.php.
1.1.2. [x] - Move all cURL and direct API interaction logic from api.php into this new class.
1.1.3. [x] - The CapitecApiService.php will handle constructing API payloads and making the raw requests.

1.2. [x] - Update api.php to act as a Controller:
1.2.1. [x] - Modify api.php to include and instantiate the new CapitecApiService.
1.2.2. [x] - All API-related actions in api.php will now call methods on the CapitecApiService instance.

1.3. [x] - Enhance Database Schema:
1.3.1. [x] - Add a batches table to store information about each trading batch.
1.3.2. [x] - Modify the trades table to include batch_id, status, and columns for API response details (quote_id, quote_rate, deal_ref, etc.).

1.4. [x] - Extract curl related functionality out of CapitecApiService
1.4.1. [x] - Created php/services/HttpClientService.php to encapsulate all cURL logic.
1.4.2. [x] - Refactored CapitecApiService to use the new HttpClientService for all API requests.
1.4.3. [x] - Updated api.php to inject the HttpClientService into the CapitecApiService.

1.5. [x] - Separate Front-end Code into Distinct Files:
1.5.1. [x] - Create js/app.js and move all <script> content into it.
1.5.2. [x] - Create css/style.css and move all <style> content into it.
1.5.3. [x] - Update index.html to link to the new .js and .css files.

1.6. [ ] - Implement Database Schema Verification:
1.6.1. [ ] - Enhance MigrationService to check if all required tables and columns exist.
1.6.2. [ ] - Display a clear warning on the Settings page if the schema is out of date, even if all migration files have been "run".
1.6.3. [ ] - This will prevent the "DB is up to date" message when it's actually missing structures from manual deletions or old versions.

🎨 Phase 2: UI for Batch Trading (Trade Tab)
This phase focuses on building the user interface that allows for the creation and execution of a trade batch.

2.1. [x] - Develop Batch Creation UI:
2.1.1. [x] - On the "Trade" tab, create a table listing all clients.
2.1.2. [x] - Add a checkbox next to each client for selection.
2.1.3. [x] - Add an input field for the ZAR amount for each client.
2.1.4. [x] - Add a "Create Trade Batch" button.

2.2. [x] - Develop Batch Staging & Validation UI:
2.2.1. [x] - On "Create Trade Batch" click, display the selected clients in a "Staged Batch" area.
2.2.2. [x] - Show client, amount, and placeholders for validation status.
2.2.3. [x] - Add a "Validate Batch" button.

2.3. [x] - Develop Batch Execution UI:
2.3.1. [x] - Display the final, validated batch with a master "Execute Trades" button.
2.3.2. [x] - The UI should provide real-time feedback for each trade as it progresses.

⚙️ Phase 3: Backend Logic - Validation & Quoting
This phase implements the core server-side logic for preparing and executing a trade.

3.1. [ ] - Implement Batch Staging Logic:
3.1.1. [ ] - Create a new stage_batch action in api.php.
3.1.2. [ ] - Generate a unique Batch UID and save the initial batch and trades to the database.

3.2. [ ] - Implement Pluggable Validation System:
3.2.1. [ ] - Create a ValidationService to run a series of checks.
3.2.2. [ ] - Balance Check: Call the BalanceEnquiry API to check availableBalance.
3.2.3. [ ] - SDA/AIT Check: Create placeholder validation rules for SDA and AIT.
3.2.4. [ ] - Update the status of each trade in the database based on validation results.

3.3. [ ] - Implement Quoting Sequence:
3.3.1. [ ] - Create an execute_batch action in api.php.
3.3.2. [ ] - For each trade in the batch:
3.3.2.1. [ ] - Step 1: Create Quote: Call the createquote endpoint.
3.3.2.2. [ ] - Step 2: Save quoteId: Store the quoteId in the trades table.
3.3.2.3. [ ] - Step 3: Get Latest Quote: Call the getlatestquote endpoint.
3.3.2.4. [ ] - Step 4: Validate Rate: Compare the dealRate against the otc_rate and spread.
3.3.2.5. [ ] - Update trade status based on rate validation.

3.4. [ ] - Convert Spread from Percentage to Basis Points (Bips):
3.4.1. [ ] - Update database schema for clients table to store spread as an integer (bips).
3.4.2. [ ] - Update front-end UI (Clients and Trade tabs) to display and accept spread in bips.
3.4.3. [ ] - Update backend logic for CSV import and rate validation to use bips.

✅ Phase 4: Backend Logic - Booking & Confirmation
This phase finalizes the trade by booking it and confirming its status.

4.1. [ ] - Implement Deal Booking:
4.1.1. [ ] - For each trade that passes rate validation:
4.1.1.1. [ ] - Step 5: Book Deal: Call the bookquoteddeal endpoint.
4.1.1.2. [ ] - Step 6: Save Booking Info: Save the transactionId and update status to booked.

4.2. [ ] - Implement Status Polling:
4.2.1. [ ] - Create a confirm_batch_status action in api.php.
4.2.2. [ ] - Loop through all booked trades in the batch.
4.2.3. [ ] - Call the gettxnstatus endpoint for each trade.
4.2.4. [ ] - Poll at intervals until the status is no longer "PENDING".
4.2.5. [ ] - Update the final status in the database (completed or failed_booking).

4.3. [ ] - Implement Trade History Tab:
4.3.1. [ ] - Create a UI on the "History" tab to display a list of all past batches.
4.3.2. [ ] - Allow the user to click on a batch to see a detailed view of its trades.

✨ Phase 5: Gemini API Integrations
This phase focuses on adding AI-powered features to enhance the trading workflow.

5.1. [x] - Implement Live Market Analysis:
5.1.1. [x] - Add a "Get Market Analysis" button to the "Trade" tab.
5.1.2. [x] - Create a GeminiApiService.php to handle communication with the Gemini API.
5.1.3. [x] - Implement a backend action (get_market_analysis) to fetch a USD/ZAR market summary.
5.1.4. [x] - Display the analysis in a modal window on the front-end.

💎 Phase 6: UI/UX & Performance Enhancements
This phase focuses on improving the user experience and application performance.

6.1. [ ] - Implement Server-Side Pagination for Bank Accounts:
6.1.1. [ ] - Update handleGetBankAccounts in api.php to accept page and size parameters.
6.1.2. [ ] - Modify the SQL query to use LIMIT and OFFSET for pagination.
6.1.3. [ ] - Return total record count along with the paginated data.
6.1.4. [ ] - Add "Next" and "Previous" buttons to the "Bank Accounts" tab UI in index.html.
6.1.5. [ ] - Implement the front-end logic in js/app.js to handle page navigation and display page information.