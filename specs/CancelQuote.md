## ❌ CancelQuote API Summary

**Purpose**: Cancels a previously created FX quote.

**Protocol**: REST over HTTP
**Message Format**: JSON
**Auth**: Bearer token (OAuth2) + `userId` in header

### 🔐 Authentication

* **Bearer Token**: Retrieved from OAuth `accessToken`.
* **Header Field**: `userId` (String, required)

---

### 📤 Request Structure

```json
{
  "header": {
    "userId": "string@example.co.za"
  },
  "quoteID": 123456789
}
```

* **quoteID**: Required INT64 – the unique identifier of the quote to cancel.

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
    "resultMsg": "Quote successfully cancelled"
  }
}
```

#### Failure

```json
{
  "header": {
    "transactionId": "UUID-string"
  },
  "result": {
    "resultCode": 1,
    "resultReasonCode": "ERROR_TYPE",
    "resultMsg": "Failure reason",
    "validationErrors": [
      {
        "validation": "field",
        "error": "error description"
      }
    ]
  }
}
```

---

### 🧠 Key Fields

* `transactionId`: UUID used to trace the request
* `resultCode`: `0` = success, `>0` = failure
* `resultMsg`: Human-readable result message
* `validationErrors`: Optional array for validation-related issues

---

### 📍 Environments

* **DEV**: `https://toc-gw-dev.forex.npr-plbfrx.asgard.capi/trading-channel-service/api/v1/cancelquote`
* **INT**: `https://toc-gw-int.forex.npr-plbfrx.asgard.capi/trading-channel-service/api/v1/cancelquote`
* **QA**: `https://toc-gw-qa.forex.npr-plbfrx.asgard.capi/trading-channel-service/api/v1/cancelquote`
* **PROD**: Provided on request
