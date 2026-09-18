/**
 * Order Model — Business Logic for Orders
 * 
 * All business logic (totals, validation, status rules) lives here in JS.
 * PHP no longer needs to calculate these — it only stores/syncs the result.
 */

import { UUIDGenerator } from '../core/uuid-generator.js';

export class OrderModel {

    /** Validate raw order form data before saving */
    static validate(orderData) {
        const errors = [];

        if (!orderData.customer_id) {
            errors.push('Customer is required.');
        }

        if (!orderData.items || !Array.isArray(orderData.items) || orderData.items.length === 0) {
            errors.push('At least one laundry item/service is required.');
        }

        orderData.items?.forEach((item, index) => {
            if (!item.service_id) errors.push(`Item ${index + 1}: Service is required.`);
            if (!item.quantity || Number(item.quantity) <= 0) errors.push(`Item ${index + 1}: Quantity must be greater than 0.`);
            if (!item.unit_price || Number(item.unit_price) <= 0) errors.push(`Item ${index + 1}: Unit price is required.`);
        });

        if (!orderData.pickup_date) {
            errors.push('Pickup date is required.');
        }

        return { isValid: errors.length === 0, errors };
    }

    /** Calculate order totals from items */
    static calculateTotals(items, pickupType = 'pickup', discount = 0) {
        let subtotal = 0;

        const computedItems = items.map(item => {
            const lineTotal = Number(item.quantity) * Number(item.unit_price);
            subtotal += lineTotal;
            return { ...item, line_total: lineTotal };
        });

        const deliveryFee = pickupType === 'delivery' ? 50 : 0;
        const total = subtotal + deliveryFee - Number(discount);

        return {
            computedItems,
            subtotal,
            delivery_fee: deliveryFee,
            discount: Number(discount),
            total
        };
    }

    /**
     * Create a complete order object ready for IndexedDB.
     * This is what gets saved locally (offline) and later synced.
     */
    static create(formData) {
        const validation = this.validate(formData);
        if (!validation.isValid) {
            throw new Error(validation.errors.join('\n'));
        }

        const { computedItems, subtotal, delivery_fee, discount, total } = this.calculateTotals(
            formData.items,
            formData.pickup_type,
            formData.discount
        );

        const now = new Date().toISOString();

        return {
            id: UUIDGenerator.generateUUID(),           // Local UUID (used as PK in IndexedDB)
            order_no: UUIDGenerator.generateOrderNumber(),
            customer_id: formData.customer_id,
            status: 'pending',
            pickup_type: formData.pickup_type || 'pickup',
            pickup_date: formData.pickup_date,
            delivery_address: formData.delivery_address || null,
            notes: formData.notes || '',
            items: computedItems,                       // Stored inline for local use
            subtotal,
            delivery_fee,
            discount,
            total,
            amount_paid: 0,
            payment_status: 'unpaid',
            receipt_token: UUIDGenerator.generateReceiptToken(),
            created_at: now,
            updated_at: now,
            synced: false,                              // KEY: marks this as not yet sent to PHP
            sync_timestamp: null
        };
    }

    /** Update status — always marks as unsynced so SyncManager picks it up */
    static updateStatus(order, newStatus) {
        const VALID = ['pending', 'washing', 'drying', 'ready', 'completed', 'cancelled'];
        if (!VALID.includes(newStatus)) throw new Error(`Invalid status: ${newStatus}`);
        return {
            ...order,
            status: newStatus,
            updated_at: new Date().toISOString(),
            synced: false
        };
    }

    /** Update payment */
    static updatePayment(order, amountPaid) {
        const paid = Number(amountPaid);
        let paymentStatus = 'unpaid';
        if (paid >= order.total) paymentStatus = 'paid';
        else if (paid > 0) paymentStatus = 'partial';

        return {
            ...order,
            amount_paid: paid,
            payment_status: paymentStatus,
            updated_at: new Date().toISOString(),
            synced: false
        };
    }

    static getStatusProgress(status) {
        return { pending: 10, washing: 30, drying: 50, ready: 80, completed: 100, cancelled: 0 }[status] ?? 0;
    }

    static getStatusLabel(status) {
        return {
            pending: 'Order Received',
            washing: 'In Progress — Washing',
            drying: 'In Progress — Drying',
            ready: 'Ready for Pickup',
            completed: 'Completed / Claimed',
            cancelled: 'Cancelled'
        }[status] ?? status;
    }
}
