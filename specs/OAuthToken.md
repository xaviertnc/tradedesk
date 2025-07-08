## 🔑 OAuthToken API Summary

**Purpose**: Provides a bearer token for authenticating access to Capitec Bank’s Forex APIs.

**Protocol**: REST over HTTP
**Message Format**: `application/x-www-form-urlencoded`
**Message Pattern**: Synchronous Request-Response

---

### 📤 Request Structure

**Method**: `GET` with URL-encoded body parameters

```text
client_id=...&client_secret=...&scope=...&grant_type=...&username=...&password=...
```

**Fields**:

* `client_id` (required): Static ID assigned to the client
* `client_secret` (required): Static secret assigned to the client
* `scope` (required): Assigned access scope
* `grant_type` (required): Type of OAuth grant (e.g., password)
* `username` (required): Dynamic end-user identifier
* `password` (required): Dynamic password

---

### 📥 Response Structure

#### Success

```json
{
  "token_type": "Bearer",
  "scope": "fx_api_access",
  "expires_in": 3600,
  "ext_expires_in": 7200,
  "access_token": "abc123",
  "refresh_token": "refresh456"
}
```

#### Error

```json
{
  "error": "invalid_grant",
  "error_description": "Incorrect username or password",
  "error_codes": [70002],
  "timestamp": "2025-01-13T09:38:02.692Z",
  "trace_id": "trace-uuid",
  "correlation_id": "corr-uuid",
  "error_uri": "https://error.help.uri"
}
```

---

### 🧠 Key Fields

* `access_token`: Main bearer token for use in all secured endpoints
* `refresh_token`: To request a new access token when expired
* `expires_in`: Validity of the token in seconds
* `error_*`: Detailed error reporting for debugging

---

### 📍 Environments

* **QA**: `https://capitecexternal.ciamlogin.com/fe28b3fd-eb00-4d73-a0e8-9bbb7af1b8a6/oauth2/v2.0/token`
* **PROD**: Available on request
