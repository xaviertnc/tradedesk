# Project Structure

```
.
├── .cursor/                          # Cursor IDE configuration
├── .git/                             # Git repository
├── .gitignore                        # Git ignore rules
├── api.php                           # Main API endpoint (21KB, 643 lines)
├── BatchService.php                  # Batch processing service (3.1KB, 112 lines)
├── CapitecApiService.php             # Capitec API integration (5.1KB, 130 lines)
├── chat/                             # Chat summaries (empty)
├── css/
│   └── style.css                     # Main stylesheet (406B, 25 lines)
├── data/
│   ├── deals.csv                     # Sample deals data (856B, 5 lines)
│   ├── tradedesk.db                  # SQLite database (248KB, 785 lines)
│   └── batch.csv                     # Sample batch data (651B, 5 lines)
├── debug.log                         # Debug log file (521B, 9 lines)
├── docs/
│   ├── api/capitec/
│   │   ├── BalanceEnquiry.md             # Balance enquiry specification (1.7KB, 78 lines)
│   │   ├── BookQuotedDeal.md             # Book quoted deal specification (1.9KB, 84 lines)
│   │   ├── CancelQuote.md                # Cancel quote specification (1.7KB, 85 lines)
│   │   ├── CreateQuote.md                # Create quote specification (2.4KB, 104 lines)
│   │   ├── GetLatestQuote.md             # Get latest quote specification (1.6KB, 73 lines)
│   │   ├── GetTrxnStatus.md              # Get transaction status specification (1.9KB, 86 lines)
│   │   ├── OAuthToken.md                 # OAuth token specification (1.8KB, 74 lines)
│   │   └── StatementEquiry.md            # Statement enquiry specification (3.2KB, 133 lines)
│   ├── PLAN.md                           # Project planning and todos (3.4KB, 75 lines)
│   ├── PRD.md                            # Product Requirements Document (4.5KB, 94 lines)
│   └── STRUCTURE.md                      # This file - project structure documentation
├── GeminiApiService.php              # Gemini API integration (1.8KB, 49 lines)
├── HttpClientService.php             # HTTP client service (3.3KB, 95 lines)
├── index.html                        # Main application interface (22KB, 343 lines)
├── js/
│   └── app.js                        # Main JavaScript application (25KB, 765 lines)
├── migrations/
│   ├── 2025_07_08_01_rename_config_url_columns.php    # Migration: rename config URL columns (2.2KB, 60 lines)
│   ├── 2025_07_08_02_add_trade_columns.php            # Migration: add trade columns (1.3KB, 39 lines)
│   ├── 2025_07_10_01_add_batch_management_tables.php  # Migration: add batch management tables (4.3KB, 137 lines)
│   └── 2025_07_10_02_add_missing_columns.php          # Migration: add missing columns (1.7KB, 51 lines)
├── MigrationService.php              # Database migration service (4.4KB, 133 lines)
├── README                            # Project documentation (340B, 7 lines)
├── tests/
│   ├── SchemaVerificationTest.php    # Database schema verification tests (7.4KB, 218 lines)
│   └── test_batch.php                # Batch processing tests (2.8KB, 93 lines)
└── tools/
    ├── check_schema.php              # Schema verification utility (675B, 22 lines)
    ├── README                        # Tools documentation (2.8KB, 72 lines)
    ├── repair-database.php           # Database repair utility (7.9KB, 270 lines)
    ├── run_migration.php             # Migration runner utility (1.4KB, 50 lines)
    ├── sqlite-viewer.php             # SQLite database viewer (18KB, 517 lines)
    └── verify-schema.php             # Schema verification utility (3.2KB, 114 lines)
```

## Key Components

- **API Layer**: `api.php` - Main API endpoint handling all requests (643 lines)
- **Services**: Various service classes for API integrations and business logic
  - `BatchService.php` - Batch processing operations
  - `CapitecApiService.php` - Capitec bank API integration
  - `GeminiApiService.php` - Gemini AI API integration
  - `HttpClientService.php` - HTTP request handling
  - `MigrationService.php` - Database migration management
- **Database**: SQLite database (`data/tradedesk.db`) with migration system
- **Frontend**: HTML/CSS/JS interface for user interaction
  - `index.html` - Main application interface (343 lines)
  - `js/app.js` - Client-side logic (765 lines)
  - `css/style.css` - Styling
- **Data**: Sample data files and database
  - `data/DEALS.csv` - Sample deals data
  - `data/sample_batch.csv` - Sample batch data
- **Documentation**: Comprehensive specs and documentation files
- **Testing**: Schema verification and testing utilities
- **Tools**: Development and maintenance utilities
- **Migrations**: Database schema evolution files (4 migration files)

## Recent Structural Changes

- **Database moved**: `tradedesk.db` relocated to `data/` directory
- **Sample data organized**: CSV files moved to `data/` directory
- **Tools consolidated**: All utility scripts moved to `tools/` directory
- **Documentation organized**: All docs moved to `docs/` directory
- **Checkpoints added**: New `checkpoints/` directory for state management
- **Debug logging**: Added `debug.log` for development tracking
