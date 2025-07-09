# **Trade batching feature diagnosis & plan:**

## **1. Your description of the problem**

* Start a batch and wait for it to complete, but feedback can take a while.
* Need to track & make decisions based on state of the batch and/or individual trades.
* Multiple batches may be running/active at once.
* Want to see past batches for reference.
* System does not remember/track batch-trade relationships or batch history details.

---

## **Where you are lacking:**

1. **Batch Lifecycle & State Tracking**

   * No persistent model for tracking batches and their states (pending, running, complete, failed, etc).
   * Batches aren't managed as first-class objects.

2. **Trade-to-Batch Linking**

   * Individual trades aren’t linked to their batch after execution, so you can't retrieve batch composition or results.

3. **Concurrent Batch Management**

   * No structure for handling multiple concurrent batches and their individual statuses.

4. **Batch History & Persistence**

   * No persistent/logged history of batches or their constituent trades for later review.

5. **Feedback/Async Completion**

   * Feedback from batch processing is not associated back to the batch or surfaced to the user in a timely or structured way.

6. **Decision Workflow**

   * No interface or process for the user to act on completed, partially completed, or failed batches.

---

## **Proposed Fix Plan (Step by Step)**

### **A. Model & Storage Redesign**

1. **Batch Entity**

   * Introduce a Batch model:
     `id`, `created_at`, `status`, `completed_at`, `trades: [Trade IDs]`, `results`, `error_messages`, etc.
2. **Trade Entity Update**

   * Add a `batch_id` field to Trade model.
3. **Batch Status Enum**

   * E.g. `pending`, `running`, `success`, `partial_success`, `failed`, `cancelled`

### **B. Core Logic**

4. **Start Batch**

   * When a batch is started, create a new Batch object (in DB or memory).
   * Assign all trades to that batch (update their `batch_id` field).
5. **Process Batches Async**

   * Batches run independently. Use background jobs/async processing.
   * As trades finish, update their status and report progress to the parent batch.
   * When all trades are done, batch status transitions accordingly.

### **C. State/Feedback/History**

6. **Batch & Trade Query APIs**

   * Endpoints/UI to:

     * Get all active batches (with progress).
     * Get batch details: status, start/end, constituent trades and their states.
     * List N most recent batches, with filters.
7. **Feedback/Completion Hooks**

   * Notify user or trigger workflow when batch completes (success/failure/partial).
   * Optionally trigger callbacks/actions for specific batch outcomes.

### **D. UI/UX**

8. **Batch Dashboard**

   * Display:

     * All active batches and their status/progress.
     * Completed/recent batches.
     * Drilldown: batch → trades.
   * Allow viewing trade results/errors per batch.

### **E. Edge Cases**

9. **Concurrent Handling**

   * Ensure system can handle multiple simultaneous batches robustly.
10. **Persistence & Recovery**

    * Batch and trade states should persist through restarts/crashes.

---

## **Pseudocode Plan**

```plaintext
# Models
class Batch:
    id
    created_at
    completed_at
    status
    trade_ids: list
    results: dict
    errors: dict

class Trade:
    id
    batch_id
    status
    result
    error

# Start batch
def start_batch(trade_list):
    batch = Batch.create(status='pending', trade_ids=[])
    for trade in trade_list:
        trade.batch_id = batch.id
        batch.trade_ids.append(trade.id)
        trade.status = 'pending'
    run_batch_async(batch.id)

# Run batch async
def run_batch_async(batch_id):
    batch = get_batch(batch_id)
    batch.status = 'running'
    for trade_id in batch.trade_ids:
        run_trade_async(trade_id, batch_id)
    # As trades complete, update batch progress
    # On completion of all trades, update batch.status

# On trade completion
def on_trade_complete(trade_id, batch_id, result, error):
    trade = get_trade(trade_id)
    trade.status = 'complete' if not error else 'failed'
    trade.result = result
    trade.error = error
    update_batch_status(batch_id)

def update_batch_status(batch_id):
    batch = get_batch(batch_id)
    # check status of all trades
    if all trades complete:
        batch.status = 'success' or 'partial' or 'failed'
        batch.completed_at = now
    # else keep running

# Query API
def get_batches(filter):
    # returns recent/active batches

def get_batch_details(batch_id):
    # returns batch, trades, progress, results, errors
```

---

### **Summary of Next Steps**

* **Design/implement Batch model and persist it.**
* **Link trades to batches.**
* **Implement async/concurrent batch handling.**
* **Expose query APIs for batch/trade status/history.**
* **UI: Add batch dashboard and history view.**