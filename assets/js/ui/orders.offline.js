/**
 * Orders Page — Offline Bridge
 * 
 * This script intercepts the existing PHP form submissions for:
 *   - Status changes
 *   - Payment recording
 * 
 * When OFFLINE: saves to IndexedDB via window.lavadora, queues for sync
 * When ONLINE:  lets the normal PHP form submit proceed as usual
 * 
 * The existing PHP UI is preserved. This JS quietly wraps it.
 */

document.addEventListener('lavadora:ready', () => {
    const lavadora = window.lavadora;

    // ---- Status Update Interception ----
    document.querySelectorAll('form[data-offline-action="status"]').forEach(form => {
        form.addEventListener('submit', async (e) => {
            // Only intercept when offline
            if (lavadora.detector.isOnline()) return;
            e.preventDefault();

            const orderId = form.querySelector('[name="order_id"]')?.value;
            const newStatus = form.querySelector('[name="status"]')?.value;
            if (!orderId || !newStatus) return;

            try {
                await lavadora.orders.updateStatus(orderId, newStatus);
                showToast(`✅ Status updated to "${newStatus}" (saved offline)`, 'success');

                // Update UI immediately without page reload
                const badge = document.querySelector(`[data-order-id="${orderId}"] .order-status-badge`);
                if (badge) {
                    badge.textContent = formatStatus(newStatus);
                    badge.className = `order-status-badge badge status-${newStatus}`;
                }
            } catch (err) {
                showToast(`❌ Failed: ${err.message}`, 'danger');
            }
        });
    });

    // ---- Payment Interception ----
    document.querySelectorAll('form[data-offline-action="payment"]').forEach(form => {
        form.addEventListener('submit', async (e) => {
            if (lavadora.detector.isOnline()) return;
            e.preventDefault();

            const orderId = form.querySelector('[name="order_id"]')?.value;
            const addPayment = parseFloat(form.querySelector('[name="add_payment"]')?.value || '0');
            if (!orderId || addPayment <= 0) return;

            try {
                // Get current order from IndexedDB
                const order = await lavadora.orders.getOrder(orderId);
                if (!order) {
                    showToast('Order not found in local database. Reload the page.', 'warning');
                    return;
                }
                const newTotal = (parseFloat(order.amount_paid) || 0) + addPayment;
                await lavadora.orders.recordPayment(orderId, newTotal);
                showToast(`✅ Payment of ₱${addPayment.toFixed(2)} recorded offline`, 'success');
            } catch (err) {
                showToast(`❌ Payment failed: ${err.message}`, 'danger');
            }
        });
    });

    // ---- Offline Order Create ----
    const createForm = document.getElementById('offline-order-create-form');
    if (createForm) {
        createForm.addEventListener('submit', async (e) => {
            if (lavadora.detector.isOnline()) return; // Let PHP handle it online
            e.preventDefault();

            try {
                const fd = new FormData(createForm);
                const items = [];
                const serviceIds = fd.getAll('service_id[]');
                const quantities = fd.getAll('quantity[]');
                const unitPrices = fd.getAll('unit_price[]');
                const serviceNames = fd.getAll('service_name[]');
                const units = fd.getAll('unit[]');

                serviceIds.forEach((svcId, i) => {
                    if (!svcId) return;
                    items.push({
                        service_id: svcId,
                        service_name: serviceNames[i] || '',
                        unit: units[i] || 'kg',
                        quantity: parseFloat(quantities[i]) || 1,
                        unit_price: parseFloat(unitPrices[i]) || 0,
                    });
                });

                // Check if new customer inline
                let customerId = fd.get('customer_id');
                if (!customerId || customerId === '0') {
                    const newCustomer = await lavadora.customers.createCustomer({
                        first_name: fd.get('c_first_name'),
                        phone: fd.get('c_phone'),
                        email: fd.get('c_email'),
                        address: fd.get('c_address'),
                    });
                    customerId = newCustomer.id;
                }

                const order = await lavadora.orders.createOrder({
                    customer_id: customerId,
                    pickup_type: fd.get('pickup_type') || 'walk_in',
                    pickup_date: fd.get('pickup_date'),
                    delivery_address: fd.get('delivery_address'),
                    notes: fd.get('notes'),
                    discount: parseFloat(fd.get('discount') || '0'),
                    items,
                });

                showToast(`✅ Order ${order.order_no} created offline! Will sync when online.`, 'success');

                // Inject the new order into the table without reloading
                const tbody = document.querySelector('#orders-table tbody');
                if (tbody) {
                    const tr = buildOfflineOrderRow(order);
                    tbody.insertAdjacentHTML('afterbegin', tr);
                }

                // Close modal if open
                const modal = bootstrap.Modal.getInstance(document.getElementById('newOrderModal'));
                if (modal) modal.hide();

            } catch (err) {
                showToast(`❌ Error: ${err.message}`, 'danger');
            }
        });
    }

    // ---- Display offline-only orders that exist in IndexedDB but not PHP ----
    loadOfflineOnlyOrders();
});

async function loadOfflineOnlyOrders() {
    try {
        const lavadora = window.lavadora;
        if (!lavadora?.orders) return;
        const unsyncedOrders = await lavadora.db.queryByIndex('orders', 'synced', false);
        if (unsyncedOrders.length === 0) return;

        const tbody = document.querySelector('#orders-table tbody');
        if (!tbody) return;

        for (const order of unsyncedOrders) {
            // Avoid duplication if PHP already rendered it
            if (document.querySelector(`[data-order-id="${order.id}"]`)) continue;
            tbody.insertAdjacentHTML('afterbegin', buildOfflineOrderRow(order));
        }
    } catch (e) {
        console.warn('[Orders] Could not load offline orders:', e);
    }
}

function buildOfflineOrderRow(order) {
    const date = new Date(order.created_at).toLocaleDateString();
    return `
        <tr data-order-id="${order.id}" class="table-warning" title="Offline — not synced yet">
            <td><span class="badge bg-warning text-dark" title="Offline order">⏳ OFFLINE</span></td>
            <td><strong>${order.order_no}</strong></td>
            <td>${order.customer_id || '—'}</td>
            <td><span class="badge status-${order.status}">${formatStatus(order.status)}</span></td>
            <td>₱${parseFloat(order.total).toFixed(2)}</td>
            <td>${date}</td>
            <td><small class="text-muted">Will sync when online</small></td>
        </tr>
    `;
}

function formatStatus(s) {
    return { pending:'Pending', washing:'Washing', drying:'Drying', ready:'Ready', completed:'Completed', cancelled:'Cancelled' }[s] ?? s;
}

function showToast(message, type = 'info') {
    const container = document.getElementById('lav-toast-container') || (() => {
        const el = document.createElement('div');
        el.id = 'lav-toast-container';
        el.style.cssText = 'position:fixed;top:20px;right:20px;z-index:99999;display:flex;flex-direction:column;gap:8px;';
        document.body.appendChild(el);
        return el;
    })();

    const toast = document.createElement('div');
    const colors = { success:'#198754', danger:'#dc3545', warning:'#f59e0b', info:'#0dcaf0' };
    toast.style.cssText = `background:${colors[type]||colors.info};color:#fff;padding:12px 20px;border-radius:10px;font-size:.87rem;font-weight:600;box-shadow:0 4px 16px rgba(0,0,0,.3);max-width:340px;`;
    toast.textContent = message;
    container.appendChild(toast);
    setTimeout(() => toast.remove(), 4000);
}
