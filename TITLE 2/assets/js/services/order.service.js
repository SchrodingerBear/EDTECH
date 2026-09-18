/**
 * Order Service — CRUD operations for Orders via IndexedDB
 * 
 * All reads/writes go through IndexedDB FIRST.
 * SyncManager handles pushing to PHP in the background.
 * This means the UI is always instant — no PHP wait times.
 */

import { OrderModel } from '../models/order.model.js';

export class OrderService {
    constructor(db, syncManager) {
        this.db = db;
        this.syncManager = syncManager;
    }

    /** Create a new order — works OFFLINE */
    async createOrder(formData) {
        const order = OrderModel.create(formData);

        // 1. Save order to IndexedDB (instant, no network needed)
        await this.db.put('orders', order);

        // 2. Save order items separately for easy querying
        for (const item of order.items) {
            await this.db.put('order_items', {
                id: `${order.id}-${item.service_id}`,
                order_id: order.id,
                service_id: item.service_id,
                service_name: item.service_name,
                quantity: item.quantity,
                unit: item.unit,
                unit_price: item.unit_price,
                line_total: item.line_total,
                synced: false
            });
        }

        // 3. Queue sync — will push to PHP when online
        await this.syncManager.addToQueue('order', 'create', order, 5);

        console.log(`[OrderService] Created order: ${order.order_no}`);
        return order;
    }

    /** Get all orders from IndexedDB */
    async getAllOrders() {
        const orders = await this.db.getAll('orders');
        // Sort by created_at descending (newest first)
        return orders.sort((a, b) => new Date(b.created_at) - new Date(a.created_at));
    }

    /** Get a single order by its UUID */
    async getOrder(id) {
        return this.db.get('orders', id);
    }

    /** Get orders for a specific customer */
    async getOrdersByCustomer(customerId) {
        return this.db.queryByIndex('orders', 'customer_id', customerId);
    }

    /** Get orders by status */
    async getOrdersByStatus(status) {
        return this.db.queryByIndex('orders', 'status', status);
    }

    /** Get unsynced orders count */
    async getUnsyncedCount() {
        const unsynced = await this.db.queryByIndex('orders', 'synced', false);
        return unsynced.length;
    }

    /** Update order status — works OFFLINE */
    async updateStatus(orderId, newStatus) {
        const order = await this.db.get('orders', orderId);
        if (!order) throw new Error('Order not found in local database');

        const updated = OrderModel.updateStatus(order, newStatus);
        await this.db.put('orders', updated);

        await this.syncManager.addToQueue('order', 'update', {
            id: orderId,
            status: newStatus,
            updated_at: updated.updated_at
        }, 5);

        console.log(`[OrderService] Updated status: ${orderId} -> ${newStatus}`);
        return updated;
    }

    /** Record a payment — works OFFLINE */
    async recordPayment(orderId, amountPaid) {
        const order = await this.db.get('orders', orderId);
        if (!order) throw new Error('Order not found in local database');

        const updated = OrderModel.updatePayment(order, amountPaid);
        await this.db.put('orders', updated);

        await this.syncManager.addToQueue('order', 'update', {
            id: orderId,
            amount_paid: updated.amount_paid,
            payment_status: updated.payment_status,
            updated_at: updated.updated_at
        }, 5);

        return updated;
    }

    /** Seed orders from PHP (called on first load or full sync) */
    async seedFromServer(ordersJson) {
        for (const order of ordersJson) {
            await this.db.put('orders', { ...order, synced: true });
        }
        console.log(`[OrderService] Seeded ${ordersJson.length} orders from server`);
    }

    /** Get dashboard summary stats */
    async getDashboardStats() {
        const all = await this.getAllOrders();
        return {
            total: all.length,
            pending: all.filter(o => o.status === 'pending').length,
            washing: all.filter(o => o.status === 'washing').length,
            drying: all.filter(o => o.status === 'drying').length,
            ready: all.filter(o => o.status === 'ready').length,
            completed: all.filter(o => o.status === 'completed').length,
            unsynced: all.filter(o => !o.synced).length,
            totalRevenue: all.filter(o => o.status === 'completed').reduce((sum, o) => sum + o.total, 0)
        };
    }
}
