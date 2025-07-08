FX Trading System - Project Plan
This plan outlines the necessary tasks to build the batch trading, validation, and history features for the FX Batch Trader application.

🏗️ Phase 1: Backend Refactoring & Setup
The first step is to refactor the backend to make it more modular and easier to maintain as we add more complex features.

[x] - Create CapitecApiService.php:
1.1. [x] - Create a new file named php/services/CapitecApiService.php.
1.2. [x] - Move all cURL and direct API interaction logic from api.php into this new class.
1.3. [x] - The CapitecApiService.php will handle constructing API payloads and making the raw requests.

[x] - Update api.php to act as a Controller:
2.1. [x] - Modify api.php to include and instantiate the new CapitecApiService.
2.2. [x] - All API-related actions in api.php will now call methods on the CapitecApiService instance.

[x] - Enhance Database Schema:
3.1. [x] - Add a batches table to store information about each trading batch.
3.2. [x] - Modify the trades table to include batch_id, status, and columns for API response details (quote_id, quote_rate, deal_ref, etc.).

[x] - Extract curl related functionality out of CapitecApiService
4.1. [x] - Created php/services/HttpClientService.php to encapsulate all cURL logic.
4.2. [x] - Refactored CapitecApiService to use the new HttpClientService for all API requests.
4..3. [x] - Updated api.php to inject the HttpClientService into the CapitecApiService.

[ ] - Separate Front-end Code into Distinct Files:
5.1. [ ] - Create js/app.js and move all <script> content into it.
5.2. [ ] - Create css/style.css and move all <style> content into it.
5.3. [ ] - Update index.html to link to the new .js and .css files.

🎨 Phase 2: UI for Batch Trading (Trade Tab)
This phase focuses on building the user interface that allows for the creation and execution of a trade batch.

[x] - Develop Batch Creation UI:
1.1. [x] - On the "Trade" tab, create a table listing all clients.
1.2. [x] - Add a checkbox next to each client for selection.
1.3. [x] - Add an input field for the ZAR amount for each client.
1.4. [x] - Add a "Create Trade Batch" button.

[x] - Develop Batch Staging & Validation UI:
2.1. [x] - On "Create Trade Batch" click, display the selected clients in a "Staged Batch" area.
2.2. [x] - Show client, amount, and placeholders for validation status.
2.3. [x] - Add a "Validate Batch" button.

[x] - Develop Batch Execution UI:
3.1. [x] - Display the final, validated batch with a master "Execute Trades" button.
3.2. [x] - The UI should provide real-time feedback for each trade as it progresses.

⚙️ Phase 3: Backend Logic - Validation & Quoting
This phase implements the core server-side logic for preparing and executing a trade.

[ ] - Implement Batch Staging Logic:
1.1. [ ] - Create a new stage_batch action in api.php.
1.2. [ ] - Generate a unique Batch UID and save the initial batch and trades to the database.

[ ] - Implement Pluggable Validation System:
2.1. [ ] - Create a ValidationService to run a series of checks.
2.2. [ ] - Balance Check: Call the BalanceEnquiry API to check availableBalance.
2.3. [ ] - SDA/AIT Check: Create placeholder validation rules for SDA and AIT.
2.4. [ ] - Update the status of each trade in the database based on validation results.

[ ] - Implement Quoting Sequence:
3.1. [ ] - Create an execute_batch action in api.php.
3.2. [ ] - For each trade in the batch:
3.2.1. [ ] - Step 1: Create Quote: Call the createquote endpoint.
3.2.2. [ ] - Step 2: Save quoteId: Store the quoteId in the trades table.
3.2.3. [ ] - Step 3: Get Latest Quote: Call the getlatestquote endpoint.
3.2.4. [ ] - Step 4: Validate Rate: Compare the dealRate against the otc_rate and spread.
3.2.5. [ ] - Update trade status based on rate validation.

✅ Phase 4: Backend Logic - Booking & Confirmation
This phase finalizes the trade by booking it and confirming its status.

[ ] - Implement Deal Booking:
1.1. [ ] - For each trade that passes rate validation:
1.1.1. [ ] - Step 5: Book Deal: Call the bookquoteddeal endpoint.
1.1.2. [ ] - Step 6: Save Booking Info: Save the transactionId and update status to booked.

[ ] - Implement Status Polling:
2.1. [ ] - Create a confirm_batch_status action in api.php.
2.2. [ ] - Loop through all booked trades in the batch.
2.3. [ ] - Call the gettxnstatus endpoint for each trade.
2.4. [ ] - Poll at intervals until the status is no longer "PENDING".
2.5. [ ] - Update the final status in the database (completed or failed_booking).

[ ] - Implement Trade History Tab:
3.1. [ ] - Create a UI on the "History" tab to display a list of all past batches.
3.2. [ ] - Allow the user to click on a batch to see a detailed view of its trades.