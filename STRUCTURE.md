# Project Structure

```
.
├── .cursor/                          # Cursor IDE configuration
├── .git/                             # Git repository
├── .gitignore                        # Git ignore rules
├── api.php                           # Main API endpoint (18KB, 523 lines)
├── BatchService.php                  # Batch processing service (2.6KB, 93 lines)
├── CapitecApiService.php             # Capitec API integration (4.8KB, 117 lines)
├── css/
│   └── style.css                     # Main stylesheet (406B, 25 lines)
├── DEALS.csv                         # Sample deals data (856B, 5 lines)
├── fx_trader.db                      # SQLite database (248KB, 763 lines)
├── GeminiApiService.php              # Gemini API integration (1.8KB, 49 lines)
├── HttpClientService.php             # HTTP client service (3.3KB, 95 lines)
├── index.html                        # Main application interface (20KB, 310 lines)
├── js/
│   └── app.js                        # Main JavaScript application (25KB, 583 lines)
├── migrations/
│   ├── 2025_07_08_01_rename_config_url_columns.php    # Migration: rename config URL columns (2.2KB, 60 lines)
│   ├── 2025_07_08_02_add_trade_columns.php            # Migration: add trade columns (1.3KB, 39 lines)
│   └── 2025_07_10_01_add_batch_management_tables.php  # Migration: add batch management tables (4.3KB, 137 lines)
├── MigrationService.php              # Database migration service (4.4KB, 133 lines)
├── PLAN.md                           # Project planning and todos (2.6KB, 65 lines)
├── PRD.md                            # Product Requirements Document (4.5KB, 94 lines)
├── README                            # Project documentation (3.4KB, 116 lines)
├── repair-database.php               # Database repair utility (7.9KB, 270 lines)
├── specs/
│   ├── BalanceEnquiry.md             # Balance enquiry specification (1.7KB, 78 lines)
│   ├── BookQuotedDeal.md             # Book quoted deal specification (1.9KB, 84 lines)
│   ├── CancelQuote.md                # Cancel quote specification (1.7KB, 85 lines)
│   ├── CreateQuote.md                # Create quote specification (2.4KB, 104 lines)
│   ├── GetLatestQuote.md             # Get latest quote specification (1.6KB, 73 lines)
│   ├── GetTrxnStatus.md              # Get transaction status specification (1.9KB, 86 lines)
│   ├── OAuthToken.md                 # OAuth token specification (1.8KB, 74 lines)
│   └── StatementEquiry.md            # Statement enquiry specification (3.2KB, 133 lines)
├── sqlite-viewer.php                 # SQLite database viewer (18KB, 517 lines)
├── STRUCTURE.md                      # This file - project structure documentation
├── tests/
│   └── SchemaVerificationTest.php    # Database schema verification tests (7.4KB, 218 lines)
└── verify-schema.php                 # Schema verification utility (3.2KB, 114 lines)
```

## Key Components

- **API Layer**: `api.php` - Main API endpoint handling all requests
- **Services**: Various service classes for API integrations and business logic
- **Database**: SQLite database with migration system
- **Frontend**: HTML/CSS/JS interface for user interaction
- **Documentation**: Comprehensive specs and documentation files
- **Testing**: Schema verification and testing utilities
