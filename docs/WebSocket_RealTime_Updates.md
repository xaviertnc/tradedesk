# WebSocket (Server-Sent Events) Real-Time Updates

**Last updated:** 11 Jul 2025

## Overview

This project uses Server-Sent Events (SSE) to provide real-time updates for batch and trade status changes. The frontend connects to a special API endpoint using the EventSource API (a one-way, browser-native streaming protocol), which allows the server to push updates to the client as they happen.

---

## How It Works

### 1. **Backend (PHP) Implementation**
- The endpoint `/currencyhub/tradedesk/v8/api.php?action=websocket` acts as the SSE stream.
- When a client connects, the handler:
  - Sets the `Content-Type: text/event-stream` header.
  - Disables all PHP error output and cleans output buffers to prevent any non-SSE data from breaking the stream.
  - Sends an initial `data: { ... }` message to confirm connection.
  - Enters a loop, checking for new batch/trade updates every second.
  - Sends updates as `data: { ... }\n\n` messages whenever there is new data.
  - Sends a heartbeat message every 30 seconds to keep the connection alive.
  - Closes the connection after 5 minutes (client will auto-reconnect).

#### **Key Backend Files:**
- `api.php` (function: `handleWebSocket`)
- `BatchService.php` (function: `getRecentUpdates`)

---

### 2. **Frontend (JavaScript) Implementation**
- The frontend uses the `EventSource` API to connect to the SSE endpoint.
- On connection, it:
  - Shows a "Real-time updates connected" notification and status indicator.
  - Listens for messages (`onmessage`), parses the JSON, and updates the UI in real time.
  - Handles connection errors and automatically attempts to reconnect with exponential backoff.
  - Shows a warning notification if the connection is lost and a success notification when reconnected.
- The connection URL is hardcoded to the correct project path for reliability.

#### **Key Frontend Files:**
- `js/app.js` (functions: `initWebSocket`, `handleWebSocketMessage`, etc.)
- `css/style.css` (for notification and status indicator styling)

---

## Typical Flow
1. User opens the app in their browser.
2. The frontend JS calls `new EventSource('/currencyhub/tradedesk/v8/api.php?action=websocket')`.
3. The server responds with a stream of `data: ...` messages.
4. The frontend updates the UI in real time as batches/trades change.
5. If the connection drops, the frontend automatically reconnects and notifies the user.

---

## Troubleshooting
- **Connection loops or disconnects:**
  - Check the PHP error log for warnings or SQL errors (these will break the SSE stream).
  - Ensure the `trades` table has an `updated_at` column.
  - Make sure the server allows long-running HTTP requests (no short timeouts or buffering).
- **No real-time updates:**
  - Ensure the frontend is using the correct path for the EventSource URL.
  - Check that the backend is sending updates (try triggering a batch/trade change).
- **Notifications spam:**
  - This usually means the connection is unstable or the server is sending errors. Fix the root cause in the backend.

---

## Design Notes
- **Why SSE and not WebSocket?**
  - SSE is simpler for one-way, server-to-client updates and is natively supported in all modern browsers.
  - No need for a separate WebSocket server or protocol.
- **Security:**
  - The SSE endpoint is read-only and does not accept client data.
  - CORS headers are set to allow cross-origin requests if needed.
- **Performance:**
  - The server only sends updates when there is new data, minimizing bandwidth.
  - Heartbeats keep the connection alive and detect disconnects.

---

## Example: Minimal SSE Handler (PHP)
```php
header('Content-Type: text/event-stream');
header('Cache-Control: no-cache');
echo "data: {\"hello\":\"world\"}\n\n";
flush();
```

## Example: Minimal EventSource (JS)
```js
const es = new EventSource('/currencyhub/tradedesk/v8/api.php?action=websocket');
es.onmessage = e => console.log('Update:', e.data);
```

---

## See Also
- [MDN: Server-Sent Events](https://developer.mozilla.org/en-US/docs/Web/API/Server-sent_events)
- [MDN: EventSource API](https://developer.mozilla.org/en-US/docs/Web/API/EventSource)

--- 