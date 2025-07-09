## 🧠 BBFTS - BookQuotedDeal API Summary for LLMs

This API is used to **book a trade** based on a previously created quote. It operates over **REST/HTTP** with **JSON** format, utilizing a **Bearer Token** from OAuth for authentication. It's synchronous and part of Capitec's Treasury FX services.

---

## 🔚 Endpoint URLs

* **DEV**: `https://toc-gw-dev.forex.npr-plbfrx.asgard.capi/trading-channel-service/api/v1/bookquoteddeal`
* **INT**: `https://toc-gw-int.forex.npr-plbfrx.asgard.capi/trading-channel-service/api/v1/bookquoteddeal`
* **QA**: `https://toc-gw-qa.forex.npr-plbfrx.asgard.capi/trading-channel-service/api/v1/bookquoteddeal`

---

## 📨 Request Structure

```json
{
  "header": {
    "userId": "string@example.co.za"
  },
  "trade": {
    "quoteID": 0,
    "dealTransactionID": "string",
    "quoteRate": 0,
    "dealRate": 0
  }
}
```

**Required Fields**:

* `userId` – Consumer's identifier
* `quoteID` – Unique ID of the quoted trade to be booked
* `dealTransactionID` – Unique transaction reference for this booking
* `quoteRate` – The quoted FX rate provided previously
* `dealRate` – The actual rate the deal is executed at

---

## 📤 Response Structure (Success)

```json
{
  "header": {
    "transactionId": "UUID"
  },
  "result": {
    "resultCode": 0,
    "resultmsg": "Trade successfully booked"
  }
}
```

## ❌ Response Structure (Failure)

```json
{
  "header": {
    "transactionId": "UUID"
  },
  "result": {
    "resultCode": >0,
    "resultReasonCode": "string",
    "resultMsg": "string",
    "validationErrors": [
      {
        "validation": "string",
        "error": "string"
      }
    ]
  }
}
```

---

## 🔐 Authentication

* Requires Bearer Token in header
* Obtained from standard OAuth2 flow
* Header: `Authorization: Bearer {accessToken}`

