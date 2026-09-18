/**
 * Customers Page — Offline Bridge
 * 
 * Intercepts customer create/edit form when offline.
 * Saves to IndexedDB, queues for sync, and visually updates the UI.
 */

document.addEventListener('lavadora:ready', () => {
    const lavadora = window.lavadora;

    // ---- Form Interception ----
    const form = document.getElementById('customer-form');
    if (form) {
        form.addEventListener('submit', async (e) => {
            // Let normal PHP form submission handle it if online
            if (lavadora.detector.isOnline()) return;
            e.preventDefault();

            try {
                const fd = new FormData(form);
                const isEdit = fd.get('form') === 'update';
                const id = fd.get('id');

                const data = {
                    first_name: fd.get('first_name'),
                    phone: fd.get('phone'),
                    email: fd.get('email'),
                    address: fd.get('address'),
                    notes: fd.get('notes'),
                };

                if (isEdit) {
                    await lavadora.customers.updateCustomer(id, data);
                    showToast('✅ Customer updated offline', 'success');
                } else {
                    await lavadora.customers.createCustomer(data);
                    showToast('✅ Customer created offline', 'success');
                }

                // Redirect back to list
                setTimeout(() => {
                    window.location.href = 'customers';
                }, 800);

            } catch (err) {
                showToast(`❌ Error: ${err.message}`, 'danger');
            }
        });
    }

    // ---- Display offline-only customers in the table ----
    loadOfflineCustomers();
});

async function loadOfflineCustomers() {
    try {
        const lavadora = window.lavadora;
        if (!lavadora?.customers) return;
        
        // Find customers where synced === false
        const unsynced = await lavadora.db.queryByIndex('customers', 'synced', false);
        if (unsynced.length === 0) return;

        const tbody = document.querySelector('#customers-table tbody');
        if (!tbody) return;

        for (const customer of unsynced) {
            // Don't duplicate if already rendered by PHP
            if (document.querySelector(`tr[data-customer-id="${customer.id}"]`)) continue;
            tbody.insertAdjacentHTML('afterbegin', buildCustomerRow(customer));
        }

        // Remove the "No customers yet" row if it exists
        const emptyState = tbody.querySelector('.empty-state');
        if (emptyState) emptyState.closest('tr').remove();

    } catch (e) {
        console.warn('[Customers] Could not load offline customers:', e);
    }
}

function buildCustomerRow(customer) {
    const initial = (customer.first_name || 'U')[0].toUpperCase();
    const date = new Date(customer.created_at || Date.now()).toLocaleDateString();
    
    return `
        <tr data-customer-id="${customer.id}" class="table-warning" title="Offline — not synced yet">
            <td>
                <div class="d-flex align-items-center gap-2">
                    <div class="ia-avatar ia-avatar-sm bg-warning text-dark">${initial}</div>
                    <div class="fw-semibold">${customer.first_name} <span class="badge bg-warning text-dark ms-1">⏳ OFFLINE</span></div>
                </div>
            </td>
            <td class="text-ia-muted">${customer.phone}</td>
            <td class="text-ia-muted">${customer.email || '—'}</td>
            <td>0</td>
            <td class="fw-semibold">₱0.00</td>
            <td class="text-ia-muted text-nowrap">${date}</td>
            <td class="text-end">
                <a class="btn btn-sm btn-icon disabled" title="Cannot edit offline-created record until synced">
                    <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"></path><path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z"></path></svg>
                </a>
            </td>
        </tr>
    `;
}

function showToast(message, type = 'info') {
    let container = document.getElementById('lav-toast-container');
    if (!container) {
        container = document.createElement('div');
        container.id = 'lav-toast-container';
        container.style.cssText = 'position:fixed;top:20px;right:20px;z-index:99999;display:flex;flex-direction:column;gap:8px;';
        document.body.appendChild(container);
    }

    const toast = document.createElement('div');
    const colors = { success:'#198754', danger:'#dc3545', warning:'#f59e0b', info:'#0dcaf0' };
    toast.style.cssText = `background:${colors[type]||colors.info};color:#fff;padding:12px 20px;border-radius:10px;font-size:.87rem;font-weight:600;box-shadow:0 4px 16px rgba(0,0,0,.3);max-width:340px;`;
    toast.textContent = message;
    container.appendChild(toast);
    setTimeout(() => toast.remove(), 4000);
}
