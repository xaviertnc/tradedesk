## 🧩 GetLatestQuote API Summary

**Purpose**: Retrieves the latest quote for FX trades.

**Protocol**: REST over HTTP
**Message Format**: JSON
**Auth**: Bearer token (OAuth2) + userId in header

### 🔐 Authentication

* **Bearer Token**: Retrieved from OAuth `accessToken`.
* **Header Field**: `userId` (String, required)

---

### 📤 Request Structure

```json
{
  "header": {
    "userId": "string@example.com"
  },
  "quoteID": 123456789
}
```

* **quoteID**: Required INT64 – the ID of the quote to fetch.

---

### 📥 Response Structure

#### Success

```json
{
  "header": {
    "transactionId": "UUID-string"
  },
  "result": {
    "resultCode": 0,
    "resultMsg": "Success"
  },
  "payload": {
    "quoteId": 123456789,
    "quoteExpiryDateTime": "2025-01-13T09:38:02.692Z"
  }
}
```

#### Failure

Includes `resultCode > 0`, `resultReasonCode`, `resultMsg`, and optional `validationErrors`.

---

### 📍 Environments

* **DEV**: `https://toc-gw-dev.forex.npr-plbfrx.asgard.capi/trading-channel-service/api/v1/getlatestquote`
* **INT**: `https://toc-gw-int.forex.npr-plbfrx.asgard.capi/trading-channel-service/api/v1/getlatestquote`
* **QA**: `https://toc-gw-qa.forex.npr-plbfrx.asgard.capi/trading-channel-service/api/v1/getlatestquote`
* **PROD**: Available upon request

---

### 🧠 Key Fields

* `transactionId`: Server-generated UUID for request tracing
* `resultCode`: `0` = success, `>0` = failure
* `quoteExpiryDateTime`: ISO datetime string indicating when quote expires

Let me know if you want this turned into a reference diagram or flashcards.
