## 📦 GetTxnStatus API Summary

**Purpose**: Retrieves the status of a specific transaction.

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
  "transactionID": "0b22ca0c-2b2f-4ab1-9bf6-78a0d2ff3c51"
}
```

* **transactionID**: UUID – Required; used to track and identify the transaction.

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
    "resultMsg": "Transaction successful"
  },
  "transaction": {
    "transactionId": "UUID",
    "userID": "string@example.co.za",
    "organisation": "ORG_CODE",
    "operation": "OPERATION_TYPE",
    "correlationID": "UNIQUE_ID",
    "dateTiLme": "2025-01-13T09:38:02.692Z",
    "status": "COMPLETED",
    "systemID": 101,
    "context": {
      "transactionId": "UUID",
      "thirdPartyDealID": "TP123456"
    }
  }
}
```

#### Failure

Includes:

* `resultCode > 0`
* `resultReasonCode`
* `resultMsg`

---

### 🧠 Key Fields

* `status`: Current status of the transaction (e.g., COMPLETED, PENDING)
* `correlationID`: Tracks the transaction across systems
* `operation`: Specifies the action (e.g., createQuote, cancelQuote)
* `context`: Includes third-party deal reference

---

### 📍 Environments

* **DEV**: `https://toc-gw-dev.forex.npr-plbfrx.asgard.capi/trading-channel-service/api/v1/GetTxnStatus`
* **INT**: `https://toc-gw-int.forex.npr-plbfrx.asgard.capi/trading-channel-service/api/v1/GetTxnStatus`
* **QA**: `https://toc-gw-qa.forex.npr-plbfrx.asgard.capi/trading-channel-service/api/v1/GetTxnStatus`
* **PROD**: Available on request
