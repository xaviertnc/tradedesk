## 🧮 BalanceEnquiry API Summary

**Purpose**: Retrieves a client's account balance and related data.

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
  "accountNumber": "1234567890",
  "cifNumber": "CIF123456"
}
```

* **accountNumber**: Customer’s account number (String, required)
* **cifNumber**: Customer Information File number (String, required)

---

### 📥 Response Structure

#### Success

```json
{
  "result": {
    "resultCode": 0
  },
  "header": {
    "transactionId": "UUID-string"
  },
  "accountName": "John Doe",
  "accountStatus": "OPEN",
  "currentBalance": 1500,
  "availableBalance": 1300
}
```

#### Failure

Includes:

* `resultCode > 0`
* `resultReasonCode`
* `resultMsg`
* Optional `validationErrors` (e.g., "INVALID CIF NUMBER")

---

### 🧠 Key Fields

* `transactionId`: UUID generated per request for traceability
* `accountStatus`: OPEN, DORM (dormant), CLOS (closed)
* `currentBalance`: Total funds in the account (includes holds)
* `availableBalance`: Funds available for withdrawal

---

### 📍 Environments

* **DEV**: `https://toc-gw-dev.forex.npr-plbfrx.asgard.capi/account/api/v1/balance`
* **INT**: `https://toc-gw-int.forex.npr-plbfrx.asgard.capi/account/api/v1/balance`
* **QA**: `https://toc-gw-qa.forex.npr-plbfrx.asgard.capi/account/api/v1/balance`
* **PROD**: Provided on request
