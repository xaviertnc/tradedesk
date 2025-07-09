/**
 * js/app.js
 *
 * FX Batch Trader Frontend - 28 Jun 2025 ( Start Date )
 *
 * Purpose: Handles all UI and client-side logic for FX Batch Trader, including CRUD and batch trading.
 *
 * @package FXBatchTrader
 *
 * @author Your Name <email@domain.com>
 *
 * Last 3 version commits:
 * @version 1.0 - INIT - 28 Jun 2025 - Initial commit
 * @version x.x - FT|UPD - 29 Jun 2025 - Migrate spread to integer bips
 */
document.addEventListener('DOMContentLoaded', () => {
    const API_URL = './api.php';
  
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
  
        if ( migrations.available && migrations.available.length > 0 ) {
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
          select.classList.add('hidden');
          button.classList.add('hidden');
          button.disabled = true;
          noMigrationsMsg.classList.remove('hidden');
        }
      } catch ( error ) {
        console.error('Error loading migrations:', error);
        showToast('Could not load migrations list.', true);
      }
  
      // Verify schema integrity
      try {
        const response = await fetch(`${API_URL}?action=verify_schema`);
        const result = await response.json();
        const errorMsgDiv = document.getElementById('schema-error-message');
        const errorList = document.getElementById('schema-error-list');
        const noMigrationsMsg = document.getElementById('no-migrations-message');
        const migrationControls = document.querySelector('#migration-select, #run-migration-btn');
  
        if ( result.success && !result.is_valid ) {
          errorList.innerHTML = ''; // Clear previous errors
          if ( result.errors.missing_tables.length > 0 ) {
            const li = document.createElement('li');
            li.textContent = `Missing tables: ${result.errors.missing_tables.join(', ')}`;
            errorList.appendChild(li);
          }
          Object.keys(result.errors.missing_columns).forEach(table => {
            const li = document.createElement('li');
            li.textContent = `Table '${table}' is missing columns: ${result.errors.missing_columns[table].join(', ')}`;
            errorList.appendChild(li);
          });
          errorMsgDiv.classList.remove('hidden');
          
          // Hide the "up to date" message when schema is invalid
          noMigrationsMsg.classList.add('hidden');
          
          // Show repair hint when no pending migrations but schema is invalid
          if ( !document.getElementById('migration-select').classList.contains('hidden') ) {
            // There are pending migrations, so keep controls visible
          } else {
            // No pending migrations but schema is invalid - show repair hint
            const repairHint = document.createElement('div');
            repairHint.className = 'text-sm text-orange-700 bg-orange-50 p-3 rounded-lg mt-4';
            repairHint.innerHTML = `
              <span class="font-medium">⚠️ Schema Repair Required</span><br>
              Your database schema is inconsistent. Consider deleting the <code>tradedesk.db</code> file 
              to let the application recreate it with the correct schema.
            `;
            
            // Remove any existing repair hint
            const existingHint = document.querySelector('.text-orange-700.bg-orange-50');
            if ( existingHint ) {
              existingHint.remove();
            }
            
            document.getElementById('migration-section').appendChild(repairHint);
          }
        } else {
          errorMsgDiv.classList.add('hidden');
          
          // Remove any repair hint when schema is valid
          const existingHint = document.querySelector('.text-orange-700.bg-orange-50');
          if ( existingHint ) {
            existingHint.remove();
          }
          
          // Only show "up to date" message when schema is valid AND no pending migrations
          if ( !document.getElementById('migration-select').classList.contains('hidden') ) {
            noMigrationsMsg.classList.add('hidden');
          } else {
            noMigrationsMsg.classList.remove('hidden');
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
          batchList.innerHTML = `<tr><td colspan="7" class="text-center py-4">No batches found. Create a new batch or upload a CSV file.</td></tr>`;
        } else {
          result.batches.forEach(batch => {
            const statusClass = getStatusClass(batch.status);
            const createdDate = new Date(batch.created_at).toLocaleString();
            
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
                <td class="px-6 py-4 whitespace-nowrap text-sm">${createdDate}</td>
                <td class="px-6 py-4 whitespace-nowrap text-right text-sm font-medium">
                  <button class="btn-view-batch text-indigo-600 hover:text-indigo-900">View</button>
                </td>
              </tr>
            `;
            batchList.insertAdjacentHTML('beforeend', row);
          });
        }
      } catch ( error ) {
        console.error('Error loading batches:', error);
        showToast('Could not load batches.', true);
      }
    }


    function getStatusClass(status) {
      switch ( status.toUpperCase() ) {
        case 'PENDING':
          return 'bg-yellow-100 text-yellow-800';
        case 'PROCESSING':
          return 'bg-blue-100 text-blue-800';
        case 'COMPLETED':
          return 'bg-green-100 text-green-800';
        case 'FAILED':
          return 'bg-red-100 text-red-800';
        case 'STAGED':
          return 'bg-purple-100 text-purple-800';
        default:
          return 'bg-gray-100 text-gray-800';
      }
    }


    async function viewBatchDetails(batchId) {
      try {
        const response = await fetch(`${API_URL}?action=get_batch&id=${batchId}`);
        const result = await response.json();
        
        if ( !result.success ) {
          throw new Error(result.message || 'Failed to load batch details');
        }

        // For now, just show the batch details in an alert
        // In a real app, you'd show this in a modal or separate page
        const batch = result.batch;
        const trades = result.trades;
        
        let details = `Batch: ${batch.batch_uid}\n`;
        details += `Status: ${batch.status}\n`;
        details += `Total Trades: ${batch.total_trades}\n`;
        details += `Processed: ${batch.processed_trades}\n`;
        details += `Failed: ${batch.failed_trades}\n\n`;
        details += `Trades:\n`;
        
        trades.forEach((trade, index) => {
          details += `${index + 1}. ${trade.client_name} (${trade.cif_number}) - ${new Intl.NumberFormat('en-ZA', { style: 'currency', currency: 'ZAR' }).format(trade.amount_zar)} - ${trade.status}\n`;
        });
        
        alert(details);
      } catch ( error ) {
        console.error('Error loading batch details:', error);
        showToast('Could not load batch details.', true);
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
    });
  
    document.getElementById('close-analysis-modal-btn').addEventListener('click', () => {
      analysisModal.classList.add('hidden');
    });
  
    // Initial Load
    loadSettings();
    loadMigrationsAndVerifySchema();
  });
  