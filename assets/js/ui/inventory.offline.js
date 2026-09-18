/**
 * Inventory Page — Offline Bridge
 * 
 * Intercepts inventory add/edit and stock adjustments when offline.
 * Saves to IndexedDB, queues for sync, and visually updates the UI.
 */

document.addEventListener('lavadora:ready', () => {
    const lavadora = window.lavadora;

    // ---- Item Add / Edit Interception ----
    const itemForm = document.getElementById('inventory-item-form');
    if (itemForm) {
        itemForm.addEventListener('submit', async (e) => {
            if (lavadora.detector.isOnline()) return;
            e.preventDefault();

            try {
                const fd = new FormData(itemForm);
                const isEdit = fd.get('form') === 'update';
                const id = fd.get('id');

                const data = {
                    name: fd.get('name'),
                    category: fd.get('category'),
                    unit: fd.get('unit'),
                    current_stock: fd.get('current_stock'),
                    minimum_stock: fd.get('minimum_stock'),
                    cost_per_unit: fd.get('cost_per_unit'),
                    is_active: fd.get('is_active') === 'on' ? 1 : 0
                };

                if (isEdit) {
                    await lavadora.inventory.updateItem(id, data);
                    showToast('✅ Item updated offline', 'success');
                } else {
                    await lavadora.inventory.createItem(data);
                    showToast('✅ Item added offline', 'success');
                }

                setTimeout(() => window.location.href = 'inventory', 800);
            } catch (err) {
                showToast(`❌ Error: ${err.message}`, 'danger');
            }
        });
    }

    // ---- Stock Adjustment Interception ----
    const stockForm = document.getElementById('stock-form');
    if (stockForm) {
        stockForm.addEventListener('submit', async (e) => {
            if (lavadora.detector.isOnline()) return;
            e.preventDefault();

            try {
                const fd = new FormData(stockForm);
                const itemId = fd.get('item_id');
                const qty = fd.get('quantity');
                const action = fd.get('form'); // receive or adjust
                const ref = fd.get('reference');
                const notes = fd.get('notes');

                await lavadora.inventory.adjustStock(itemId, qty, action, ref, notes);
                
                showToast(`✅ Stock ${action === 'receive' ? 'received' : 'adjusted'} offline`, 'success');
                setTimeout(() => window.location.href = 'inventory', 800);
            } catch (err) {
                showToast(`❌ Error: ${err.message}`, 'danger');
            }
        });
    }

    // ---- Render Offline-Only Items in Table ----
    loadOfflineInventory();
});

async function loadOfflineInventory() {
    try {
        const lavadora = window.lavadora;
        if (!lavadora?.inventory) return;

        // Note: For simplicity, we only prepend newly created items. 
        // Sync manager will push updates and adjustments to the server.
        
        const unsynced = await lavadora.db.queryByIndex('inventory_items', 'synced', false);
        if (unsynced.length === 0) return;

        const tbody = document.querySelector('#inventory-table tbody');
        if (!tbody) return;

        // Filter only newly created offline (they usually have string UUIDs)
        const offlineNew = unsynced.filter(i => isNaN(parseInt(i.id)));

        for (const item of offlineNew) {
            if (document.querySelector(`tr[data-item-id="${item.id}"]`)) continue;
            tbody.insertAdjacentHTML('afterbegin', buildInventoryRow(item));
        }

        const emptyState = tbody.querySelector('.empty-state');
        if (emptyState && offlineNew.length > 0) emptyState.closest('tr').remove();

    } catch (e) {
        console.warn('[Inventory] Could not load offline items:', e);
    }
}

function buildInventoryRow(item) {
    const isLow = parseFloat(item.current_stock) <= parseFloat(item.minimum_stock);
    const stockClass = isLow ? 'text-danger' : '';
    const statusBadge = isLow ? '<span class="badge badge-off">Low</span>' : '<span class="badge badge-live">OK</span>';
    const inactiveBadge = !item.is_active ? ' <span class="badge badge-off">inactive</span>' : '';

    return `
        <tr data-item-id="${item.id}" class="table-warning" title="Offline — not synced yet">
            <td class="fw-semibold">
                ${item.name}${inactiveBadge} 
                <span class="badge bg-warning text-dark ms-1">⏳ OFFLINE</span>
            </td>
            <td class="text-ia-muted">${item.category}</td>
            <td class="text-ia-muted">${item.unit}</td>
            <td class="text-end fw-semibold ${stockClass}">${item.current_stock}</td>
            <td class="text-end text-ia-muted">${item.minimum_stock}</td>
            <td>${statusBadge}</td>
            <td class="text-end text-nowrap">
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
