Plan
1. Phase 1: Foundation (July 8th)
1.1 [x] Create project structure
1.2 [x] Set up SQLite database
1.3 [x] Create config table
1.4 [x] Create trades table
1.5 [x] Create logs table
1.6 [x] Implement Migration Service
1.7 [x] Implement basic logging

2. Phase 2: Batch & State Management (July 10th)
2.1 [x] Database:
2.1.1 [x] Create batches table
2.1.2 [x] Add batch_id foreign key to trades table
2.1.3 [x] Add last_error text column to trades table
2.1.4 [x] Create a new migration file for these schema changes
2.2 [ ] Backend:
2.2.1 [x] Implement a BatchService to handle the logic of creating batches
2.2.2 [ ] Create POST /api/batches endpoint for CSV upload
2.2.3 [ ] Create GET /api/batches endpoint to list all batches
2.2.4 [ ] Create GET /api/batches/{id} endpoint to get a specific batch
2.2.5 [ ] Implement a background processing mechanism or cron job

3. Phase 3: Capitec API Integration (July 11th)
3.1 [x] Implement HttpClientService
3.2 [x] Implement CapitecApiService
3.3 [ ] Refactor Trade Processing:
3.3.1 [ ] Integrate CapitecApiService calls into the BatchService
3.3.2 [ ] Update the status of each trade and batch
3.3.3 [ ] Store any API errors in the trades.last_error field
3.4 [ ] Implement API Endpoints:
3.4.1 [x] Implement OAuth2 token generation (/token)
3.4.2 [ ] Implement createQuote
3.4.3 [ ] Implement getLatestQuote
3.4.4 [ ] Implement bookQuotedDeal
3.4.5 [ ] Implement cancelQuote
3.4.6 [ ] Implement getTransactionStatus
3.4.7 [ ] Implement getBalance
3.4.8 [ ] Implement getStatement

4. Phase 4: Gemini API Integration (July 12th)
4.1 [ ] Implement GeminiApiService
4.2 [ ] Create endpoint to analyze trade data for a completed batch
4.3 [ ] Create endpoint to suggest new trades based on analysis

5. Phase 5: Frontend for Batch Management (July 13th)
5.1 [ ] Batch Creation & Monitoring:
5.1.1 [ ] Create UI for uploading a CSV file
5.1.2 [ ] Create a dashboard to view all batches and their statuses
5.2 [ ] Batch Detail View:
5.2.1 [ ] Create a view to inspect a single batch
5.2.2 [ ] Display a list of all trades within the batch
5.2.3 [ ] Add a "Retry Failed Trades" button
5.3 [ ] Refactor Existing UI:
5.3.1 [ ] Remove the old single-trade form
5.3.2 [ ] Ensure the trade status display is driven by the new batch endpoints

6. Phase 6: Refinement & Testing (July 14th)
6.1 [x] Add database schema verification script
6.2 [x] Add PHPUnit test for schema verification
6.3 [ ] Add tests for the BatchService
6.4 [ ] Add tests for Capitec API endpoint integrations
6.5 [ ] Add tests for Gemini API endpoints
6.6 [ ] Refine UI/UX for the batch management interface
6.7 [ ] Update documentation