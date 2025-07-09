/**
 * app.js
 *
 * Main Application JavaScript - 28 Jun 2025 ( Start Date )
 *
 * Purpose: Frontend application logic for trade desk with real-time batch monitoring,
 *          WebSocket integration, and comprehensive batch management interface.
 *
 * @package TradeDesk Frontend
 *
 * @author Assistant <assistant@example.com>
 *
 * Last 3 version commits:
 * @version 1.0 - INIT - 28 Jun 2025 - Initial commit
 * @version 1.1 - UPD - 10 Jul 2025 - Added batch management features
 * @version 1.2 - UPD - 10 Jul 2025 - Added WebSocket real-time updates and notifications
 */
document.addEventListener('DOMContentLoaded', () => {
    const API_URL = './api.php';
    let batchRefreshInterval = null; // Auto-refresh interval for active batches
  
    // --- Toast Notification Handler ---
    function showToast(message, isError = false) {
      const toast = document.getElementById('toast');
      const toastMessage = document.getElementById('toast-message');
      toastMessage.textContent = message;
      toast.className = `fixed top-5 right-5 text-white py-2 px-4 rounded-lg shadow-md transition-opacity duration-300 ${isError ? 'bg-red-500' : 'bg-green-500'}`;
      toast.classList.remove('opacity-0');
  
      const duration = isError ? 10000 : 3000;
  
      setTimeout(() => {
        toast.classList.add('opacity-0');
      }, duration);
    }
  
    // --- Tab Navigation ---
    function showTab(tabName) {
      document.querySelectorAll('.tab-content').forEach(tab => tab.classList.add('hidden'));
      document.getElementById(tabName).classList.remove('hidden');
      document.querySelectorAll('.tab-button').forEach(button => {
        button.classList.remove('tab-active');
        if (button.dataset.tab === tabName) {
          button.classList.add('tab-active');
        }
      });
      
      // Clean up auto-refresh when leaving batches tab
      if (tabName !== 'batches' && batchRefreshInterval) {
        clearInterval(batchRefreshInterval);
        batchRefreshInterval = null;
        const refreshIndicator = document.getElementById('auto-refresh-indicator');
        if (refreshIndicator) {
          refreshIndicator.classList.add('hidden');
        }
      }
      
      if ( tabName === 'clients' ) loadClients();
      if ( tabName === 'accounts' ) loadBankAccounts();
      if ( tabName === 'settings' ) loadMigrationsAndVerifySchema();
      if ( tabName === 'trade' ) loadClientsForTrading();
      if ( tabName === 'batches' ) loadBatches();
    }
  
    // --- Settings Management ---
    async function loadSettings() {
      try {
        const response = await fetch(`${API_URL}?action=get_config`);
        if ( !response.ok ) {
          const errorData = await response.json().catch(() => ( { message: response.statusText } ));
          throw new Error(errorData.message || 'Network response was not ok');
        }
        const config = await response.json();
        if ( config ) {
          Object.keys(config).forEach(key => {
            const input = document.getElementById(key);
            if ( input ) input.value = config[key];
          });
        }
      } catch ( error ) {
        console.error('Error loading settings:', error);
        showToast(`Could not load settings: ${error.message}`, true);
      }
    }
  
    // --- Client Management ---
    const clientModal = document.getElementById('client-modal');
    
    function openClientModal(client = null) {
      document.getElementById('client-form').reset();
      const modalTitle = document.getElementById('modal-title');
      if ( client ) {
        modalTitle.textContent = 'Edit Client';
        document.getElementById('client-id').value = client.id;
        document.getElementById('client-name').value = client.name;
        document.getElementById('client-cif').value = client.cif_number;
        document.getElementById('client-zar').value = client.zar_account;
        document.getElementById('client-usd').value = client.usd_account;
        document.getElementById('client-spread').value = client.spread;
      } else {
        modalTitle.textContent = 'Add New Client';
      }
      clientModal.classList.remove('hidden');
    }
  
    function closeClientModal() {
      clientModal.classList.add('hidden');
    }
  
    async function loadClients() {
      try {
        const response = await fetch(`${API_URL}?action=get_clients`);
        const clients = await response.json();
        const clientList = document.getElementById('client-list');
        clientList.innerHTML = ''; 
        if ( clients.length === 0 ) {
          clientList.innerHTML = `<tr><td colspan="6" class="text-center py-4">No clients found. Add one or import from CSV.</td></tr>`;
        } else {
          clients.forEach(client => {
            const row = `
              <tr class="client-row" data-client-id="${client.id}">
                <td class="px-6 py-4 whitespace-nowrap">${client.name}</td>
                <td class="px-6 py-4 whitespace-nowrap">${client.cif_number}</td>
                <td class="px-6 py-4 whitespace-nowrap">
                  <div class="flex items-center">
                    <span class="zar-account-text">${client.zar_account || 'N/A'}</span>
                    <button class="btn-find-account ml-3 text-xs bg-gray-200 hover:bg-gray-300 text-gray-700 font-semibold py-1 px-2 rounded" data-cif="${client.cif_number}">
                      Find
                    </button>
                    <div class="loader find-loader ml-3 hidden"></div>
                  </div>
                </td>
                <td class="px-6 py-4 whitespace-nowrap">${client.usd_account || 'N/A'}</td>
                <td class="px-6 py-4 whitespace-nowrap">${client.spread}</td>
                <td class="px-6 py-4 whitespace-nowrap text-right text-sm font-medium">
                  <button class="btn-edit-client text-indigo-600 hover:text-indigo-900" data-client='${JSON.stringify(client)}'>Edit</button>
                  <button class="btn-delete-client text-red-600 hover:text-red-900 ml-4">Delete</button>
                </td>
              </tr>
            `;
            clientList.insertAdjacentHTML('beforeend', row);
          });
        }
      } catch ( error ) {
        console.error('Error loading clients:', error);
        showToast('Could not load clients.', true);
      }
    }
    
    async function findZarAccount(clientId, cifNumber, button) {
      const row = button.closest('.client-row');
      const loader = row.querySelector('.find-loader');
      
      button.classList.add('hidden');
      loader.classList.remove('hidden');
  
      try {
        const response = await fetch(`${API_URL}?action=find_zar_account&cif=${cifNumber}&id=${clientId}`);
        const result = await response.json();
  
        if ( result.success ) {
          showToast(`Found account: ${result.accountNumber}`);
          row.querySelector('.zar-account-text').textContent = result.accountNumber;
        } else {
          throw new Error(result.message || 'Could not find an active ZAR account.');
        }
      } catch ( error ) {
         console.error('Error finding account:', error);
         showToast(error.message, true);
      } finally {
        button.classList.remove('hidden');
        loader.classList.add('hidden');
      }
    }
  
    async function deleteClient(clientId, button) {
      if ( !confirm('Are you sure you want to delete this client?') ) return;
  
      try {
        const response = await fetch(`${API_URL}?action=delete_client&id=${clientId}`, { method: 'DELETE' });
        const result = await response.json();
        if ( result.success ) {
          showToast('Client deleted successfully!');
          button.closest('tr').remove();
        } else {
          throw new Error(result.message || 'Failed to delete client');
        }
      } catch ( error ) {
        console.error('Error deleting client:', error);
        showToast(error.message, true);
      }
    }
  
    // --- Bank Account Sync & Display ---
    async function loadBankAccounts() {
       try {
          const response = await fetch(`${API_URL}?action=get_bank_accounts`);
          const accounts = await response.json();
          const accountList = document.getElementById('bank-account-list');
          accountList.innerHTML = ''; 
          if ( accounts.length === 0 ) {
              accountList.innerHTML = `<tr><td colspan="6" class="text-center py-4">No bank accounts found. Click 'Sync Bank Accounts' to fetch them.</td></tr>`;
          } else {
              accounts.forEach(acc => {
                  const row = `
                      <tr>
                          <td class="px-6 py-2 whitespace-nowrap">${acc.cus_name}</td>
                          <td class="px-6 py-2 whitespace-nowrap">${acc.cus_cif_no}</td>
                          <td class="px-6 py-2 whitespace-nowrap">${acc.account_no}</td>
                          <td class="px-6 py-2 whitespace-nowrap">${acc.account_currency}</td>
                          <td class="px-6 py-2 whitespace-nowrap">
                              <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full ${acc.account_status === 'OPEN' ? 'bg-green-100 text-green-800' : 'bg-red-100 text-red-800'}">
                                  ${acc.account_status}
                              </span>
                          </td>
                          <td class="px-6 py-2 whitespace-nowrap text-right">${new Intl.NumberFormat('en-ZA', { style: 'currency', currency: acc.account_currency }).format(acc.curr_account_balance)}</td>
                      </tr>
                  `;
                  accountList.insertAdjacentHTML('beforeend', row);
              });
          }
      } catch ( error ) {
          console.error('Error loading bank accounts:', error);
          showToast('Could not load bank accounts.', true);
      }
  }
  
    // --- Migrations ---
    async function loadMigrationsAndVerifySchema() {
      // Load available migrations
      try {
        const response = await fetch(`${API_URL}?action=get_migrations`);
        const migrations = await response.json();
        const select = document.getElementById('migration-select');
        const button = document.getElementById('run-migration-btn');
        const noMigrationsMsg = document.getElementById('no-migrations-message');
        
        select.innerHTML = '';
        console.log('[MIGRATION] Available migrations:', migrations.available);

        // Store for later use in schema logic
        window._latestMigrations = migrations;
      } catch ( error ) {
        console.error('Error loading migrations:', error);
        showToast('Could not load migrations list.', true);
        return;
      }
  
      // Verify schema integrity
      try {
        const response = await fetch(`${API_URL}?action=verify_schema`);
        const result = await response.json();
        const errorMsgDiv = document.getElementById('schema-error-message');
        const errorList = document.getElementById('schema-error-list');
        const noMigrationsMsg = document.getElementById('no-migrations-message');
        const select = document.getElementById('migration-select');
        const button = document.getElementById('run-migration-btn');
        const migrations = window._latestMigrations || { available: [] };

        console.log('[SCHEMA] verify_schema result:', result);
        console.log('[UI] Current migration select hidden:', select.classList.contains('hidden'));

        if ( result.success && result.is_valid ) {
          // Schema is valid
          console.log('[UI] Schema is valid. Hiding warning and migration controls.');
          errorMsgDiv.classList.add('hidden');
          select.classList.add('hidden');
          button.classList.add('hidden');
          button.disabled = true;
          noMigrationsMsg.classList.remove('hidden');
          // Remove any repair hint
          const existingHint = document.querySelector('.text-orange-700.bg-orange-50');
          if ( existingHint ) existingHint.remove();
        } else {
          // Schema is invalid
          console.log('[UI] Schema is INVALID. Showing warning.');
          errorList.innerHTML = '';
          if ( result.errors && result.errors.missing_tables && result.errors.missing_tables.length > 0 ) {
            const li = document.createElement('li');
            li.textContent = `Missing tables: ${result.errors.missing_tables.join(', ')}`;
            errorList.appendChild(li);
          }
          if ( result.errors && result.errors.missing_columns ) {
            Object.keys(result.errors.missing_columns).forEach(table => {
              const li = document.createElement('li');
              li.textContent = `Table '${table}' is missing columns: ${result.errors.missing_columns[table].join(', ')}`;
              errorList.appendChild(li);
            });
          }
          errorMsgDiv.classList.remove('hidden');
          // Show migration controls only if there are available migrations
          if ( migrations.available && migrations.available.length > 0 ) {
            console.log('[UI] Pending migrations found. Showing migration controls.');
            select.innerHTML = '';
            migrations.available.forEach(m => {
              const option = document.createElement('option');
              option.value = m;
              option.textContent = m;
              select.appendChild(option);
            });
            select.classList.remove('hidden');
            button.classList.remove('hidden');
            button.disabled = false;
            noMigrationsMsg.classList.add('hidden');
          } else {
            console.log('[UI] No pending migrations. Hiding migration controls and showing repair hint.');
            select.classList.add('hidden');
            button.classList.add('hidden');
            button.disabled = true;
            noMigrationsMsg.classList.add('hidden');
            // Show repair hint if needed...
            const repairHint = document.createElement('div');
            repairHint.className = 'text-sm text-orange-700 bg-orange-50 p-3 rounded-lg mt-4';
            repairHint.innerHTML = `
              <span class="font-medium">⚠️ Schema Repair Required</span><br>
              Your database schema is inconsistent. Consider deleting the <code>tradedesk.db</code> file 
              to let the application recreate it with the correct schema.
            `;
            // Remove any existing repair hint
            const existingHint = document.querySelector('.text-orange-700.bg-orange-50');
            if ( existingHint ) existingHint.remove();
            document.getElementById('migration-section').appendChild(repairHint);
          }
        }
      } catch ( error ) {
        console.error('Error verifying schema:', error);
        showToast('Could not verify database schema.', true);
      }
    }
  
    // --- Trading ---
    async function loadClientsForTrading() {
      try {
        const response = await fetch(`${API_URL}?action=get_clients`);
        const clients = await response.json();
        const clientList = document.getElementById('trade-client-list');
        clientList.innerHTML = '';
        if ( clients.length === 0 ) {
          clientList.innerHTML = `<tr><td colspan="4" class="text-center py-4">No clients found. Please add clients in the 'Clients' tab first.</td></tr>`;
        } else {
          clients.forEach(client => {
            const row = `
              <tr class="trade-client-row" data-client-id="${client.id}">
                <td class="p-4"><input type="checkbox" class="client-checkbox"></td>
                <td class="px-6 py-4 whitespace-nowrap">${client.name}</td>
                <td class="px-6 py-4 whitespace-nowrap">${client.cif_number}</td>
                <td class="px-6 py-4"><input type="number" class="zar-amount-input w-full rounded-md border-gray-300 shadow-sm" placeholder="0.00"></td>
              </tr>
            `;
            clientList.insertAdjacentHTML('beforeend', row);
          });
        }
      } catch ( error ) {
        console.error('Error loading clients for trading:', error);
        showToast('Could not load clients for trading.', true);
      }
    }
    
    function toggleAllClients(checked) {
      document.querySelectorAll('.client-checkbox').forEach(checkbox => checkbox.checked = checked);
    }
  
    function displayStagedBatch(batch) {
      document.getElementById('client-selection-view').classList.add('hidden');
      document.getElementById('staged-batch-view').classList.remove('hidden');
      document.getElementById('batch-uid-display').textContent = batch.batch_uid;
  
      const stagedList = document.getElementById('staged-batch-list');
      stagedList.innerHTML = '';
      batch.trades.forEach(trade => {
        const row = `
          <tr>
            <td class="px-6 py-4 whitespace-nowrap">${trade.client_name}</td>
            <td class="px-6 py-4 whitespace-nowrap">${new Intl.NumberFormat('en-ZA', { style: 'currency', currency: 'ZAR' }).format(trade.amount_zar)}</td>
            <td class="px-6 py-4 whitespace-nowrap"><span class="font-mono text-xs">${trade.status}</span></td>
            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500"></td>
          </tr>
        `;
        stagedList.insertAdjacentHTML('beforeend', row);
      });
    }


    // --- Batch Management ---
    async function loadBatches() {
      try {
        const response = await fetch(`${API_URL}?action=get_batches`);
        const result = await response.json();
        
        if ( !result.success ) {
          throw new Error(result.message || 'Failed to load batches');
        }

        const batchList = document.getElementById('batch-list');
        batchList.innerHTML = '';
        
        if ( result.batches.length === 0 ) {
          batchList.innerHTML = `<tr><td colspan="8" class="text-center py-4">No batches found. Create a new batch or upload a CSV file.</td></tr>`;
        } else {
          result.batches.forEach(batch => {
            const statusClass = getStatusClass(batch.status);
            const createdDate = new Date(batch.created_at).toLocaleString();
            const progressPercentage = batch.total_trades > 0 ? 
              Math.round(((batch.processed_trades + batch.failed_trades) / batch.total_trades) * 100) : 0;
            
            const row = `
              <tr class="batch-row" data-batch-id="${batch.id}">
                <td class="px-6 py-4 whitespace-nowrap font-mono text-sm">${batch.batch_uid}</td>
                <td class="px-6 py-4 whitespace-nowrap">
                  <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full ${statusClass}">
                    ${batch.status}
                  </span>
                </td>
                <td class="px-6 py-4 whitespace-nowrap text-sm">${batch.total_trades}</td>
                <td class="px-6 py-4 whitespace-nowrap text-sm">${batch.processed_trades}</td>
                <td class="px-6 py-4 whitespace-nowrap text-sm">${batch.failed_trades}</td>
                <td class="px-6 py-4 whitespace-nowrap">
                  <div class="w-full bg-gray-200 rounded-full h-2">
                    <div class="bg-blue-600 h-2 rounded-full transition-all duration-300" style="width: ${progressPercentage}%"></div>
                  </div>
                  <div class="text-xs text-gray-500 mt-1">${progressPercentage}%</div>
                </td>
                <td class="px-6 py-4 whitespace-nowrap text-sm">${createdDate}</td>
                <td class="px-6 py-4 whitespace-nowrap text-right text-sm font-medium">
                  <button class="btn-view-batch text-indigo-600 hover:text-indigo-900 mr-2">View</button>
                  ${batch.status === 'PENDING' ? `<button class="btn-start-batch text-green-600 hover:text-green-900">Start</button>` : ''}
                </td>
              </tr>
            `;
            batchList.insertAdjacentHTML('beforeend', row);
          });
        }

        // Setup auto-refresh for active batches
        setupBatchAutoRefresh(result.batches);
      } catch ( error ) {
        console.error('Error loading batches:', error);
        showToast('Could not load batches.', true);
      }
    }


    /**
     * Setup auto-refresh for active batches
     * @param {Array} batches - Array of batch objects
     */
    function setupBatchAutoRefresh(batches) {
      // Clear existing interval
      if (batchRefreshInterval) {
        clearInterval(batchRefreshInterval);
        batchRefreshInterval = null;
      }

      // Check if there are any active batches (PENDING or RUNNING)
      const activeBatches = batches.filter(batch => 
        ['PENDING', 'RUNNING'].includes(batch.status)
      );

      if (activeBatches.length > 0) {
        // Start auto-refresh every 3 seconds for active batches
        batchRefreshInterval = setInterval(() => {
          loadBatches();
        }, 3000);
        
        // Show auto-refresh indicator
        const refreshIndicator = document.getElementById('auto-refresh-indicator');
        if (refreshIndicator) {
          refreshIndicator.classList.remove('hidden');
          refreshIndicator.textContent = `Auto-refreshing every 3s (${activeBatches.length} active batch${activeBatches.length > 1 ? 'es' : ''})`;
        }
      } else {
        // Hide auto-refresh indicator when no active batches
        const refreshIndicator = document.getElementById('auto-refresh-indicator');
        if (refreshIndicator) {
          refreshIndicator.classList.add('hidden');
        }
      }
    }


    function getStatusClass(status) {
      switch ( status.toUpperCase() ) {
        case 'PENDING':
          return 'bg-yellow-100 text-yellow-800';
        case 'RUNNING':
          return 'bg-blue-100 text-blue-800';
        case 'SUCCESS':
          return 'bg-green-100 text-green-800';
        case 'PARTIAL_SUCCESS':
          return 'bg-orange-100 text-orange-800';
        case 'FAILED':
          return 'bg-red-100 text-red-800';
        case 'CANCELLED':
          return 'bg-gray-100 text-gray-800';
        default:
          return 'bg-gray-100 text-gray-800';
      }
    }


    async function viewBatchDetails(batchId) {
      const modal = document.getElementById('batch-detail-modal');
      const loader = document.getElementById('batch-summary-loader');
      const content = document.getElementById('batch-summary-content');
      
      // Show modal and loader
      modal.classList.remove('hidden');
      loader.classList.remove('hidden');
      content.classList.add('hidden');

      try {
        const response = await fetch(`${API_URL}?action=get_batch&id=${batchId}`);
        const result = await response.json();
        
        if ( !result.success ) {
          throw new Error(result.message || 'Failed to load batch details');
        }

        const batch = result.batch;
        const trades = result.trades;
        
        // Update batch summary
        document.getElementById('batch-uid').textContent = batch.batch_uid;
        document.getElementById('batch-status').textContent = batch.status;
        document.getElementById('batch-total-trades').textContent = batch.total_trades;
        document.getElementById('batch-created').textContent = new Date(batch.created_at).toLocaleString();
        
        // Update progress
        const progressPercentage = batch.total_trades > 0 ? 
          Math.round(((batch.processed_trades + batch.failed_trades) / batch.total_trades) * 100) : 0;
        document.getElementById('batch-progress-text').textContent = `${progressPercentage}%`;
        document.getElementById('batch-progress-bar').style.width = `${progressPercentage}%`;
        
        // Update statistics
        const successCount = trades.filter(t => t.status === 'SUCCESS').length;
        const failedCount = trades.filter(t => t.status === 'FAILED').length;
        const pendingCount = trades.filter(t => ['PENDING', 'EXECUTING', 'QUOTED'].includes(t.status)).length;
        
        document.getElementById('batch-success-count').textContent = successCount;
        document.getElementById('batch-failed-count').textContent = failedCount;
        document.getElementById('batch-pending-count').textContent = pendingCount;
        
        // Update trades table
        const tradesList = document.getElementById('batch-trades-list');
        tradesList.innerHTML = '';
        
        trades.forEach(trade => {
          const statusClass = getTradeStatusClass(trade.status);
          const row = `
            <tr>
              <td class="px-6 py-4 whitespace-nowrap text-sm font-medium text-gray-900">${trade.client_name || 'Unknown'}</td>
              <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">${trade.cif_number || 'N/A'}</td>
              <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">${new Intl.NumberFormat('en-ZA', { style: 'currency', currency: 'ZAR' }).format(trade.amount_zar)}</td>
              <td class="px-6 py-4 whitespace-nowrap">
                <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full ${statusClass}">
                  ${trade.status}
                </span>
              </td>
              <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">${trade.quote_rate ? trade.quote_rate.toFixed(4) : 'N/A'}</td>
              <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500 font-mono">${trade.bank_trxn_id || 'N/A'}</td>
              <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">${trade.status_message || 'N/A'}</td>
            </tr>
          `;
          tradesList.insertAdjacentHTML('beforeend', row);
        });
        
        // Store batch ID for refresh functionality
        modal.dataset.batchId = batchId;
        
        // Hide loader and show content
        loader.classList.add('hidden');
        content.classList.remove('hidden');
        
      } catch ( error ) {
        console.error('Error loading batch details:', error);
        showToast('Could not load batch details.', true);
        modal.classList.add('hidden');
      }
    }


    function getTradeStatusClass(status) {
      switch ( status.toUpperCase() ) {
        case 'PENDING':
          return 'bg-yellow-100 text-yellow-800';
        case 'EXECUTING':
          return 'bg-blue-100 text-blue-800';
        case 'QUOTED':
          return 'bg-purple-100 text-purple-800';
        case 'SUCCESS':
          return 'bg-green-100 text-green-800';
        case 'FAILED':
          return 'bg-red-100 text-red-800';
        case 'CANCELLED':
          return 'bg-gray-100 text-gray-800';
        default:
          return 'bg-gray-100 text-gray-800';
      }
    }


    async function viewBatchErrors(batchId) {
      try {
        const response = await fetch(`${API_URL}?action=get_batch_errors&id=${batchId}`);
        const result = await response.json();
        
        if ( !result.success ) {
          throw new Error(result.message || 'Failed to load batch errors');
        }

        const data = result.data;
        
        if ( data.total_failed === 0 ) {
          alert('No errors found for this batch.');
          return;
        }

        let errorDetails = `Batch: ${data.batch.batch_uid}\n`;
        errorDetails += `Total Failed: ${data.total_failed}\n\n`;
        errorDetails += `Error Summary:\n`;
        
        Object.entries(data.error_summary).forEach(([errorType, info]) => {
          errorDetails += `\n${errorType} (${info.count} trades):\n`;
          info.trades.forEach(trade => {
            errorDetails += `  - ${trade.client_name} (${trade.client_cif}): ${new Intl.NumberFormat('en-ZA', { style: 'currency', currency: 'ZAR' }).format(trade.amount_zar)}\n`;
          });
        });
        
        alert(errorDetails);
      } catch ( error ) {
        console.error('Error loading batch errors:', error);
        showToast('Could not load batch errors.', true);
      }
    }


    async function startBatch(batchId, button) {
      if ( !confirm('Are you sure you want to start processing this batch? This will execute all pending trades.') ) {
        return;
      }

      const originalText = button.textContent;
      button.textContent = 'Starting...';
      button.disabled = true;

      try {
        const formData = new FormData();
        formData.append('batch_id', batchId);
        
        const response = await fetch(`${API_URL}?action=start_batch`, {
          method: 'POST',
          body: formData
        });
        
        const result = await response.json();
        
        if ( result.success ) {
          showToast('Batch processing started successfully!');
          // Refresh the batches list to show updated status
          loadBatches();
        } else {
          throw new Error(result.message || 'Failed to start batch processing');
        }
      } catch ( error ) {
        console.error('Error starting batch:', error);
        showToast(error.message, true);
      } finally {
        button.textContent = originalText;
        button.disabled = false;
      }
    }
  
    // --- Event Listeners ---
    
    document.querySelectorAll('.tab-button').forEach(button => {
      button.addEventListener('click', () => showTab(button.dataset.tab));
    });

    // --- Settings Form Submission ---
    document.getElementById('settings-form').addEventListener('submit', async (e) => {
      e.preventDefault(); // Prevent default form submission
      
      const form = e.target;
      const formData = new FormData(form);
      const submitButton = form.querySelector('button[type="submit"]');
      
      submitButton.disabled = true;
      submitButton.textContent = 'Saving...';

      try {
        const response = await fetch(`${API_URL}?action=save_config`, {
          method: 'POST',
          body: formData
        });
        
        const result = await response.json();
        
        if (result.success) {
          showToast('Settings saved successfully!');
        } else {
          throw new Error(result.message || 'Failed to save settings');
        }
      } catch (error) {
        console.error('Error saving settings:', error);
        showToast(`Error: ${error.message}`, true);
      } finally {
        submitButton.disabled = false;
        submitButton.textContent = 'Save Settings';
      }
    });    
  
    document.getElementById('add-client-btn').addEventListener('click', () => openClientModal());
    document.getElementById('cancel-modal-btn').addEventListener('click', closeClientModal);
    document.getElementById('import-csv-btn').addEventListener('click', () => document.getElementById('csv-file').click());
    document.getElementById('select-all-clients').addEventListener('change', (e) => toggleAllClients(e.target.checked));
    
    document.getElementById('save-client-btn').addEventListener('click', async () => {
      const form = document.getElementById('client-form');
      const spreadInput = document.getElementById('client-spread');
      const formData = new FormData(form);
      try {
        const response = await fetch(`${API_URL}?action=save_client`, {
          method: 'POST',
          body: formData,
        });
        const result = await response.json();
        if ( result.success ) {
          showToast('Client saved successfully!');
          closeClientModal();
          loadClients();
        } else {
          throw new Error(result.message || 'Failed to save client');
        }
      } catch ( error ) {
        console.error('Error saving client:', error);
        showToast(error.message, true);
      }
    });
  
    document.getElementById('csv-file').addEventListener('change', async (e) => {
      const file = e.target.files[0];
      if ( !file ) return;
      const formData = new FormData();
      formData.append('csv', file);
      try {
        const response = await fetch(`${API_URL}?action=import_clients`, {
          method: 'POST',
          body: formData
        });
        const result = await response.json();
         if ( result.success ) {
          showToast(`${result.imported} clients imported successfully! Please review and add any missing account numbers.`);
          loadClients();
        } else {
          throw new Error(result.message || 'Failed to import CSV.');
        }
      } catch ( error ) {
        console.error('Error importing CSV:', error);
        showToast(error.message, true);
      }
      e.target.value = '';
    });
  
    document.getElementById('sync-accounts-btn').addEventListener('click', async (e) => {
      const button = e.currentTarget;
      const syncBtnText = button.querySelector('#sync-btn-text');
      const syncLoader = button.querySelector('#sync-loader');
      button.disabled = true;
      syncBtnText.classList.add('hidden');
      syncLoader.classList.remove('hidden');
      try {
        const response = await fetch(`${API_URL}?action=sync_bank_accounts`, { method: 'POST' });
        const result = await response.json();
        if ( result.success ) {
          showToast(`Successfully synced ${result.synced_count} trading accounts!`);
          if ( !document.getElementById('accounts').classList.contains('hidden') ) {
            loadBankAccounts();
          }
        } else {
          throw new Error(result.message || 'Failed to sync accounts.');
        }
      } catch ( error ) {
        console.error('Error syncing accounts:', error);
        showToast(`Error syncing accounts: ${error.message}`, true);
      } finally {
        syncBtnText.classList.remove('hidden');
        syncLoader.classList.add('hidden');
        button.disabled = false;
      }
    });
    
    document.getElementById('run-migration-btn').addEventListener('click', async (e) => {
      const button = e.currentTarget;
      const select = document.getElementById('migration-select');
      const migrationFile = select.value;
      if ( !migrationFile ) {
        showToast('Please select a migration to run.', true);
        return;
      }
      if ( !confirm(`Are you sure you want to run the migration: ${migrationFile}? This action cannot be undone.`) ) {
        return;
      }
      const btnText = button.querySelector('#migration-btn-text');
      const loader = button.querySelector('#migration-loader');
      btnText.classList.add('hidden');
      loader.classList.remove('hidden');
      button.disabled = true;
      try {
        const formData = new FormData();
        formData.append('migration', migrationFile);
        const response = await fetch(`${API_URL}?action=run_migration`, {
          method: 'POST',
          body: formData
        });
        const result = await response.json();
        if ( result.success ) {
          showToast(`Migration '${migrationFile}' ran successfully!`);
          loadMigrationsAndVerifySchema(); 
        } else {
          throw new Error(result.message || 'Failed to run migration.');
        }
      } catch(error) {
        console.error('Error running migration:', error);
        showToast(`Error: ${error.message}`, true);
      } finally {
        btnText.classList.remove('hidden');
        loader.classList.add('hidden');
        button.disabled = false;
      }
    });
  
    document.getElementById('create-batch-btn').addEventListener('click', async () => {
      const trades = [];
      document.querySelectorAll('.trade-client-row').forEach(row => {
        const checkbox = row.querySelector('.client-checkbox');
        if ( checkbox.checked ) {
          const clientId = row.dataset.clientId;
          const amount = row.querySelector('.zar-amount-input').value;
          if ( amount && parseFloat(amount) > 0 ) {
            trades.push({ clientId, amount });
          }
        }
      });
      if ( trades.length === 0 ) {
        showToast('Please select at least one client and enter a valid amount.', true);
        return;
      }
      try {
        const response = await fetch(`${API_URL}?action=stage_batch`, {
          method: 'POST',
          headers: { 'Content-Type': 'application/json' },
          body: JSON.stringify({ trades })
        });
        const result = await response.json();
        if ( result.success ) {
          showToast('Trade batch created successfully!');
          displayStagedBatch(result.batch);
        } else {
          throw new Error(result.message || 'Failed to create batch.');
        }
      } catch ( error ) {
        console.error('Error creating batch:', error);
        showToast(`Error: ${error.message}`, true);
      }
    });
  
    document.getElementById('cancel-batch-btn').addEventListener('click', () => {
       document.getElementById('staged-batch-view').classList.add('hidden');
       document.getElementById('client-selection-view').classList.remove('hidden');
       loadClientsForTrading();
    });
  
    // Delegated event listeners for dynamic content
    document.getElementById('client-list').addEventListener('click', (e) => {
      if ( e.target.classList.contains('btn-edit-client') ) {
        openClientModal(JSON.parse(e.target.dataset.client));
      }
      if ( e.target.classList.contains('btn-delete-client') ) {
        const row = e.target.closest('.client-row');
        deleteClient(row.dataset.clientId, e.target);
      }
      if ( e.target.classList.contains('btn-find-account') ) {
        const row = e.target.closest('.client-row');
        findZarAccount(row.dataset.clientId, e.target.dataset.cif, e.target);
      }
    });
    
    // Gemini API Feature
    const analysisModal = document.getElementById('market-analysis-modal');
    const analysisLoader = document.getElementById('analysis-loader');
    const analysisContent = document.getElementById('analysis-content');
  
    document.getElementById('get-market-analysis-btn').addEventListener('click', async () => {
      analysisModal.classList.remove('hidden');
      analysisContent.innerHTML = '';
      analysisLoader.classList.remove('hidden');
      try {
        const response = await fetch(`${API_URL}?action=get_market_analysis`);
        const result = await response.json();
        if ( result.success ) {
          analysisContent.textContent = result.analysis;
        } else {
          throw new Error(result.message || 'Failed to get market analysis.');
        }
      } catch ( error ) {
        analysisContent.textContent = `Error: ${error.message}`;
        analysisContent.classList.add('text-red-500');
      } finally {
        analysisLoader.classList.add('hidden');
      }
    });


    // --- Batch Management Event Listeners ---
    document.getElementById('upload-batch-csv-btn').addEventListener('click', () => {
      document.getElementById('batch-csv-file').click();
    });


    document.getElementById('refresh-batches-btn').addEventListener('click', () => {
      loadBatches();
    });


    document.getElementById('batch-csv-file').addEventListener('change', async (e) => {
      const file = e.target.files[0];
      if ( !file ) return;
      
      const formData = new FormData();
      formData.append('csv', file);
      
      try {
        const response = await fetch(`${API_URL}?action=upload_batch_csv`, {
          method: 'POST',
          body: formData
        });
        const result = await response.json();
        
        if ( result.success ) {
          showToast('Batch uploaded successfully!');
          loadBatches();
        } else {
          throw new Error(result.message || 'Failed to upload batch CSV.');
        }
      } catch ( error ) {
        console.error('Error uploading batch CSV:', error);
        showToast(error.message, true);
      }
      
      e.target.value = '';
    });


    // Delegated event listener for batch actions
    document.getElementById('batch-list').addEventListener('click', (e) => {
      if ( e.target.classList.contains('btn-view-batch') ) {
        const row = e.target.closest('.batch-row');
        const batchId = row.dataset.batchId;
        viewBatchDetails(batchId);
      }
      if ( e.target.classList.contains('btn-start-batch') ) {
        const row = e.target.closest('.batch-row');
        const batchId = row.dataset.batchId;
        startBatch(batchId, e.target);
      }
    });
  
    document.getElementById('close-analysis-modal-btn').addEventListener('click', () => {
      analysisModal.classList.add('hidden');
    });

    // Batch Detail Modal Event Listeners
    document.getElementById('close-batch-modal-btn').addEventListener('click', () => {
      document.getElementById('batch-detail-modal').classList.add('hidden');
    });

    document.getElementById('close-batch-modal-btn-2').addEventListener('click', () => {
      document.getElementById('batch-detail-modal').classList.add('hidden');
    });

    document.getElementById('refresh-batch-details-btn').addEventListener('click', () => {
      const modal = document.getElementById('batch-detail-modal');
      const batchId = modal.dataset.batchId;
      if ( batchId ) {
        viewBatchDetails(batchId);
      }
    });

    document.getElementById('view-batch-errors-btn').addEventListener('click', () => {
      const modal = document.getElementById('batch-detail-modal');
      const batchId = modal.dataset.batchId;
      if ( batchId ) {
        viewBatchErrors(batchId);
      }
    });
  
    // Initial Load
    loadSettings();
    loadMigrationsAndVerifySchema();

    // WebSocket connection for real-time updates
    let websocket = null;
    let websocketReconnectAttempts = 0;
    const MAX_RECONNECT_ATTEMPTS = 5;
    const RECONNECT_DELAY = 3000;
    let websocketConnectionState = 'disconnected'; // Track connection state

    // Initialize WebSocket connection
    function initWebSocket() {
      if ( websocket && websocket.readyState === WebSocket.OPEN ) {
        return; // Already connected
      }
      
      try {
        const eventSourceUrl = `${window.location.protocol}//${window.location.host}/currencyhub/tradedesk/v8/api.php?action=websocket`;
        websocket = new EventSource( eventSourceUrl );
        
        websocket.onopen = function( event ) {
          console.log( 'EventSource connected for real-time updates' );
          websocketReconnectAttempts = 0;
          updateWebSocketStatus( 'connected' );
          if ( websocketConnectionState !== 'connected' ) {
            showNotification( 'Real-time updates connected', 'success' );
          }
          websocketConnectionState = 'connected';
        };
        
        websocket.onmessage = function( event ) {
          try {
            const data = JSON.parse( event.data );
            handleWebSocketMessage( data );
          } catch ( error ) {
            console.error( 'Error parsing WebSocket message:', error );
          }
        };
        
        websocket.onerror = function( error ) {
          console.error( 'EventSource error:', error );
          updateWebSocketStatus( 'disconnected' );
          handleWebSocketError();
        };
        
        websocket.onclose = function( event ) {
          console.log( 'EventSource connection closed' );
          updateWebSocketStatus( 'disconnected' );
          handleWebSocketClose();
        };
        
      } catch ( error ) {
        console.error( 'Failed to initialize WebSocket:', error );
        handleWebSocketError();
      }
    } // initWebSocket


    // Handle WebSocket messages
    function handleWebSocketMessage( data ) {
      switch ( data.type ) {
        case 'connection':
          console.log( 'WebSocket connection established' );
          break;
          
        case 'heartbeat':
          // Keep connection alive
          break;
          
        case 'batch_update':
          handleBatchUpdate( data );
          break;
          
        case 'trade_update':
          handleTradeUpdate( data );
          break;
          
        case 'batch_notification':
          handleBatchNotification( data );
          break;
          
        case 'disconnect':
          console.log( 'WebSocket disconnected:', data.status );
          break;
          
        default:
          console.log( 'Unknown WebSocket message type:', data.type );
      }
    } // handleWebSocketMessage


    // Handle batch updates from WebSocket
    function handleBatchUpdate( data ) {
      console.log( 'Batch update received:', data );
      
      // Update batch in UI if it's currently displayed
      const batchElement = document.querySelector( `[data-batch-id="${data.id}"]` );
      if ( batchElement ) {
        updateBatchElement( batchElement, data );
      }
      
      // Refresh batch lists if they're visible
      if ( currentView === 'batches' ) {
        refreshBatchList();
      }
      
      // Show notification for significant status changes
      if ( data.status && [ 'success', 'partial_success', 'failed', 'cancelled' ].includes( data.status ) ) {
        showNotification( `Batch ${data.batch_uid} ${data.status}`, 'info' );
      }
    } // handleBatchUpdate


    // Handle trade updates from WebSocket
    function handleTradeUpdate( data ) {
      console.log( 'Trade update received:', data );
      
      // Update trade in UI if it's currently displayed
      const tradeElement = document.querySelector( `[data-trade-id="${data.id}"]` );
      if ( tradeElement ) {
        updateTradeElement( tradeElement, data );
      }
      
      // Refresh batch progress if trade belongs to a visible batch
      const batchElement = document.querySelector( `[data-batch-id="${data.batch_id}"]` );
      if ( batchElement ) {
        updateBatchProgress( data.batch_id );
      }
    } // handleTradeUpdate


    // Handle batch notifications from WebSocket
    function handleBatchNotification( data ) {
      console.log( 'Batch notification received:', data );
      
      const notificationType = data.notification_type;
      const batchUid = data.batch_uid;
      const status = data.status;
      
      let message = '';
      let type = 'info';
      
      switch ( notificationType ) {
        case 'completion':
          message = `Batch ${batchUid} completed with status: ${status}`;
          type = status === 'success' ? 'success' : ( status === 'failed' ? 'error' : 'warning' );
          break;
          
        case 'started':
          message = `Batch ${batchUid} started processing`;
          type = 'info';
          break;
          
        case 'cancelled':
          message = `Batch ${batchUid} was cancelled`;
          type = 'warning';
          break;
          
        case 'status_change':
          message = `Batch ${batchUid} status changed to: ${status}`;
          type = 'info';
          break;
          
        default:
          message = `Batch ${batchUid}: ${notificationType}`;
          type = 'info';
      }
      
      showNotification( message, type );
      
      // Play sound for important notifications
      if ( [ 'completion', 'cancelled' ].includes( notificationType ) ) {
        playNotificationSound();
      }
    } // handleBatchNotification


         // Update WebSocket status indicator
     function updateWebSocketStatus( status ) {
       let indicator = document.getElementById( 'websocket-status' );
       if ( ! indicator ) {
         indicator = document.createElement( 'div' );
         indicator.id = 'websocket-status';
         indicator.className = 'websocket-status';
         indicator.innerHTML = `
           <div class="websocket-indicator"></div>
           <span class="websocket-text">Real-time updates</span>
         `;
         document.body.appendChild( indicator );
       }
       
       indicator.className = `websocket-status ${status}`;
       const textElement = indicator.querySelector( '.websocket-text' );
       
       switch ( status ) {
         case 'connected':
           textElement.textContent = 'Real-time updates connected';
           break;
         case 'connecting':
           textElement.textContent = 'Connecting...';
           break;
         case 'disconnected':
           textElement.textContent = 'Real-time updates disconnected';
           break;
       }
     } // updateWebSocketStatus


     // Handle WebSocket errors
     function handleWebSocketError() {
       console.error( 'EventSource connection error' );
       if ( websocketConnectionState !== 'disconnected' ) {
         showNotification( 'Real-time updates disconnected. Attempting to reconnect...', 'warning' );
       }
       websocketConnectionState = 'disconnected';
       if ( websocketReconnectAttempts < MAX_RECONNECT_ATTEMPTS ) {
         websocketReconnectAttempts++;
         console.log( `Attempting to reconnect EventSource (${websocketReconnectAttempts}/${MAX_RECONNECT_ATTEMPTS})` );
         updateWebSocketStatus( 'connecting' );
         setTimeout( function() {
           initWebSocket();
         }, RECONNECT_DELAY * websocketReconnectAttempts );
       } else {
         console.error( 'Max EventSource reconnection attempts reached' );
         showNotification( 'Real-time updates disconnected. Please refresh the page.', 'error' );
       }
     } // handleWebSocketError


         // Handle WebSocket connection close
     function handleWebSocketClose() {
       console.log( 'EventSource connection closed' );
       
       // Attempt to reconnect if not manually closed
       if ( websocketReconnectAttempts < MAX_RECONNECT_ATTEMPTS ) {
         updateWebSocketStatus( 'connecting' );
         setTimeout( function() {
           initWebSocket();
         }, RECONNECT_DELAY );
       }
     } // handleWebSocketClose


    // Update batch element in UI
    function updateBatchElement( element, data ) {
      // Update status
      const statusElement = element.querySelector( '.batch-status' );
      if ( statusElement ) {
        statusElement.textContent = data.status;
        statusElement.className = `batch-status status-${data.status}`;
      }
      
      // Update progress
      const progressElement = element.querySelector( '.batch-progress' );
      if ( progressElement && data.total_trades ) {
        const progress = ( data.processed_trades / data.total_trades ) * 100;
        progressElement.style.width = `${progress}%`;
        progressElement.textContent = `${Math.round( progress )}%`;
      }
      
      // Update trade counts
      const countsElement = element.querySelector( '.batch-counts' );
      if ( countsElement ) {
        countsElement.textContent = `${data.processed_trades}/${data.total_trades} trades`;
      }
      
      // Update timestamp
      const timeElement = element.querySelector( '.batch-time' );
      if ( timeElement ) {
        timeElement.textContent = new Date( data.updated_at ).toLocaleString();
      }
    } // updateBatchElement


    // Update trade element in UI
    function updateTradeElement( element, data ) {
      // Update status
      const statusElement = element.querySelector( '.trade-status' );
      if ( statusElement ) {
        statusElement.textContent = data.status;
        statusElement.className = `trade-status status-${data.status}`;
      }
      
      // Update timestamp
      const timeElement = element.querySelector( '.trade-time' );
      if ( timeElement ) {
        timeElement.textContent = new Date( data.updated_at ).toLocaleString();
      }
    } // updateTradeElement


    // Play notification sound
    function playNotificationSound() {
      try {
        // Create a simple notification sound
        const audioContext = new ( window.AudioContext || window.webkitAudioContext )();
        const oscillator = audioContext.createOscillator();
        const gainNode = audioContext.createGain();
        
        oscillator.connect( gainNode );
        gainNode.connect( audioContext.destination );
        
        oscillator.frequency.setValueAtTime( 800, audioContext.currentTime );
        oscillator.frequency.setValueAtTime( 600, audioContext.currentTime + 0.1 );
        
        gainNode.gain.setValueAtTime( 0.1, audioContext.currentTime );
        gainNode.gain.exponentialRampToValueAtTime( 0.01, audioContext.currentTime + 0.2 );
        
        oscillator.start( audioContext.currentTime );
        oscillator.stop( audioContext.currentTime + 0.2 );
      } catch ( error ) {
        console.log( 'Could not play notification sound:', error );
      }
    } // playNotificationSound


    // Enhanced notification system
    function showNotification( message, type = 'info', duration = 5000 ) {
      const notification = document.createElement( 'div' );
      notification.className = `notification notification-${type}`;
      notification.innerHTML = `
        <div class="notification-content">
          <span class="notification-message">${message}</span>
          <button class="notification-close" onclick="this.parentElement.parentElement.remove()">×</button>
        </div>
      `;
      
      // Add to notification container
      let container = document.getElementById( 'notification-container' );
      if ( ! container ) {
        container = document.createElement( 'div' );
        container.id = 'notification-container';
        container.className = 'notification-container';
        document.body.appendChild( container );
      }
      
      container.appendChild( notification );
      
      // Auto-remove after duration
      setTimeout( function() {
        if ( notification.parentElement ) {
          notification.remove();
        }
      }, duration );
      
      return notification;
    } // showNotification


         // Initialize WebSocket on page load
     updateWebSocketStatus( 'connecting' );
     initWebSocket();
  });
  