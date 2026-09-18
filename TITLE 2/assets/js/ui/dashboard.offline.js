/**
 * Dashboard Page — Offline Bridge
 * 
 * Injects offline (unsynced) data into the dashboard UI.
 */

document.addEventListener('lavadora:ready', () => {
    loadOfflineDashboardStats();
});

async function loadOfflineDashboardStats() {
    try {
        const lavadora = window.lavadora;
        if (!lavadora?.orders) return;

        // Fetch all offline-only orders (not yet synced to PHP)
        const unsyncedOrders = await lavadora.db.queryByIndex('orders', 'synced', false);
        if (unsyncedOrders.length === 0) return;

        console.log(`[Dashboard] Found ${unsyncedOrders.length} offline orders to merge into UI`);

        // Update Today's Orders stat
        const todayStat = document.getElementById('stat-todays-orders');
        if (todayStat) {
            // Count how many unsynced orders are from today
            const today = new Date().toISOString().split('T')[0];
            const todayUnsynced = unsyncedOrders.filter(o => o.created_at.startsWith(today));
            
            const currentVal = parseInt(todayStat.textContent) || 0;
            const newVal = currentVal + todayUnsynced.length;
            todayStat.innerHTML = `${newVal} <span style="font-size:12px;color:#f59e0b;vertical-align:top">(+${todayUnsynced.length})</span>`;
        }

        // Update In Queue stat (pending, washing, drying)
        const queueStat = document.getElementById('stat-in-queue');
        if (queueStat) {
            const queueUnsynced = unsyncedOrders.filter(o => ['pending', 'washing', 'drying'].includes(o.status));
            if (queueUnsynced.length > 0) {
                const currentVal = parseInt(queueStat.textContent) || 0;
                queueStat.innerHTML = `${currentVal + queueUnsynced.length} <span style="font-size:12px;color:#f59e0b;vertical-align:top">(+${queueUnsynced.length})</span>`;
            }
        }

        // Update Ready stat
        const readyStat = document.getElementById('stat-ready');
        if (readyStat) {
            const readyUnsynced = unsyncedOrders.filter(o => o.status === 'ready');
            if (readyUnsynced.length > 0) {
                const currentVal = parseInt(readyStat.textContent) || 0;
                readyStat.innerHTML = `${currentVal + readyUnsynced.length} <span style="font-size:12px;color:#f59e0b;vertical-align:top">(+${readyUnsynced.length})</span>`;
            }
        }

        // Inject into Recent Orders list
        const list = document.getElementById('dashboard-orders-list');
        if (list) {
            // Remove empty state if present
            const emptyState = list.querySelector('.empty-state');
            if (emptyState) emptyState.remove();

            // We reverse so the newest is first, then prepend to the list
            const sorted = [...unsyncedOrders].sort((a, b) => new Date(b.created_at) - new Date(a.created_at));
            
            for (const o of sorted) {
                // Check if already rendered
                if (document.querySelector(`[data-receipt="${o.id}"]`)) continue;
                
                const time = new Date(o.created_at).toLocaleTimeString([], {hour: '2-digit', minute:'2-digit'});
                const dateStr = new Date(o.created_at).toLocaleDateString([], {month: 'short', day: 'numeric'});
                
                const btn = document.createElement('button');
                btn.type = 'button';
                btn.className = 'pos-row';
                btn.style.borderLeft = '3px solid #f59e0b';
                btn.title = 'Offline Order';
                btn.dataset.receipt = o.id; // Usually offline IDs are UUIDs
                
                btn.innerHTML = `
                    <span class="pos-row-id">
                        <span class="pos-row-no">${o.order_no} <span class="badge bg-warning text-dark ms-1" style="font-size:9px">OFFLINE</span></span>
                        <span class="pos-row-sub">${time}</span>
                    </span>
                    <span class="pos-row-date">
                        <span class="pos-row-time">${dateStr}</span>
                        <span class="pos-row-chev">
                            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"></path><circle cx="12" cy="12" r="3"></circle></svg>
                        </span>
                    </span>
                `;
                
                // Prepend to top of list
                list.insertBefore(btn, list.firstChild);

                // Inject mock receipt data for the modal so tapping it works
                // Note: Offline orders will only show basic data until synced, as full PHP hydration isn't available
                let receiptData = {};
                try {
                    const scriptTag = document.getElementById('receipt-data');
                    if (scriptTag) {
                        receiptData = JSON.parse(scriptTag.textContent);
                    }
                } catch (e) {}

                receiptData[o.id] = {
                    id: o.id,
                    order_no: o.order_no,
                    created_at: o.created_at,
                    customer: 'Offline Customer', // Requires joining local tables
                    phone: '',
                    status: formatStatus(o.status),
                    status_slug: o.status,
                    payment: formatPaymentStatus(o.payment_status, o.total, o.amount_paid),
                    subtotal: o.subtotal,
                    delivery_fee: o.delivery_fee || 0,
                    discount: o.discount || 0,
                    total: o.total,
                    amount_paid: o.amount_paid || 0,
                    balance: (o.total || 0) - (o.amount_paid || 0),
                    items: o.items ? o.items.map(i => ({
                        name: i.service_name || 'Service',
                        qty: i.quantity,
                        unit: i.unit || 'kg',
                        price: i.unit_price,
                        line: i.quantity * i.unit_price
                    })) : []
                };

                const dataTag = document.getElementById('receipt-data');
                if (dataTag) dataTag.textContent = JSON.stringify(receiptData);
            }
        }

    } catch (e) {
        console.warn('[Dashboard] Could not load offline stats:', e);
    }
}

function formatStatus(s) {
    return { pending:'Pending', washing:'Washing', drying:'Drying', ready:'Ready', completed:'Claimed', cancelled:'Cancelled' }[s] ?? s;
}

function formatPaymentStatus(s, total, paid) {
    if (paid >= total && total > 0) return 'Paid';
    if (paid > 0) return 'Partial';
    return 'Unpaid';
}
