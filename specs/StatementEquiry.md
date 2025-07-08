## 📄 **StatementEnquiry API – Full Summary**

**Purpose**:
Enables third-party clients to **fetch transaction history**, **account statements**, and **related account metadata** within a specified date range for customer accounts maintained at Capitec Bank.

---

### 🔐 **Authentication**

* **Protocol**: REST/HTTP
* **Authentication**: Bearer Token (OAuth 2.0)
* **Required Header**:

  ```json
  {
    "userId": "string@example.co.za"
  }
  ```
* `userId`: Unique identifier of the requesting system or user

---

### 📤 **Request Format**

```json
{
  "header": {
    "userId": "string@example.co.za"
  },
  "accountNumber": "1234567890",
  "cifNumber": "CIF001122",
  "fromDate": "YYYY-MM-DD",
  "toDate": "YYYY-MM-DD"
}
```

#### Fields:

* `accountNumber` (String, required): Customer's account number
* `cifNumber` (String, required): Customer Information File number
* `fromDate` (Date, required): Start date for statement query
* `toDate` (Date, required): End date for statement query

---

### 📥 **Successful Response Format**

```json
{
  "result": {
    "resultCode": 0
  },
  "header": {
    "transactionId": "UUID-string"
  },
  "payload": {
    "accountNumber": "string",
    "productName": "FX Trade Account",
    "currencyCode": "ZAR",
    "accountName": "John Doe",
    "addressLine1": "123 Street",
    "transactionDetails": {
      "balanceBroughtForward": 5000,
      "entry": [
        {
          "tranDate": "2025-01-05",
          "tranType": "Cr Trf",
          "reference": "Salary Payment",
          "tranAmount": 1000,
          "balance": 6000,
          "fees": 0
        },
        ...
      ]
    },
    "total": {
      "amount": 1000,
      "fees": 0
    }
  }
}
```

---

### ❌ **Error Response Format**

```json
{
  "result": {
    "resultCode": 1,
    "resultReasonCode": "INTERNAL",
    "resultMsg": "Invalid CIF/Account Number"
  }
}
```

---

### 🧠 **Response Key Fields Explained**

* `transactionId`: A unique Capitec-generated UUID for tracing the request
* `accountNumber`, `productName`, `currencyCode`, `accountName`: Basic metadata
* `addressLine1–4`: Optional customer address details
* `transactionDetails.balanceBroughtForward`: Opening balance before `fromDate`
* `transactionDetails.entry[]`: Array of transactions with:

  * `tranDate`: Posting date
  * `tranType`: Type (e.g., Cr Trf, Dr EFT)
  * `reference`: Description
  * `tranAmount`: Transaction amount
  * `balance`: Balance after transaction
  * `fees`: Applicable charges
* `total.amount`: Net transaction volume for the period
* `total.fees`: Total fees incurred during the period

---

### 🧪 **Validation & Logic**

* Valid date ranges are mandatory.
* Errors may occur for invalid or unlinked `accountNumber`/`cifNumber` pairs.
* Date format must be strict ISO: `YYYY-MM-DD`.

---

### 🌐 **Environment Endpoints**

* **DEV**: `https://toc-gw-dev.forex.npr-plbfrx.asgard.capi/account/api/v1/statement`
* **INT**: `https://toc-gw-int.forex.npr-plbfrx.asgard.capi/account/api/v1/statement`
* **QA**: `https://toc-gw-qa.forex.npr-plbfrx.asgard.capi/account/api/v1/statement`
* **PROD**: Provided upon request
