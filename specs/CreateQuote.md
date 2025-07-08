## 🧠 BBFTS - CreateQuote API Summary for LLMs

This interface allows third-party systems to **create foreign exchange (FX) quotes** for trading with Capitec Bank. It's a **REST/HTTP API** with **JSON** message format, secured via **Bearer Token** authentication obtained from an OAuth flow.

---

## 🔚 Endpoint URLs

* **DEV**: `https://toc-gw-dev.forex.npr-plbfrx.asgard.capi/trading-channel-service/api/v1/createquote`
* **INT**: `https://toc-gw-int.forex.npr-plbfrx.asgard.capi/trading-channel-service/api/v1/createquote`
* **QA**: `https://toc-gw-qa.forex.npr-plbfrx.asgard.capi/trading-channel-service/api/v1/createquote`

---

## 📨 Request Structure

```json
{
  "header": {
    "userId": "string@example.co.za"
  },
  "customer": {
    "cifNo": "string",
    "fromSettlementAccount": {
      "currency": "string",
      "accountNumber": "string"
    },
    "toSettlementAccount": {
      "currency": "string",
      "accountNumber": "string"
    }
  },
  "trade": {
    "tradeType": "Buy|Sell",
    "deliveryType": "Spot|Tom|Cash",
    "currencyPair": "USD/ZAR",
    "dealCurrency": "ZAR",
    "dealCurrencyAmount": 0,
    "settlementDate": "YYYYMMDD",
    "dealDateTime": "ISO8601 timestamp"
  }
}
```

**Required Fields**:

* `userId`, `cifNo`, `tradeType`, `deliveryType`, `currencyPair`, `dealCurrency`, `dealCurrencyAmount`, `settlementDate`, `dealDateTime`

**Conditional Fields**:

* `fromSettlementAccount.currency/accountNumber`, `toSettlementAccount.currency/accountNumber`

---

## 📤 Response Structure (Success)

```json
{
  "header": {
    "transactionId": "UUID"
  },
  "result": {
    "resultCode": 0,
    "resultMsg": "string"
  },
  "payload": {
    "quoteId": 0,
    "quoteExpiryDateTime": "ISO8601 timestamp"
  }
}
```

## ❌ Response Structure (Error)

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

* **Bearer Token** required in header, retrieved from OAuth's `accessToken`
* Header Example: `Authorization: Bearer {token}`

---

Let me know if you want this converted into a diagram, table, flashcards, or quiz format.
