/**
 * UUID Generator for Lavadora Offline System
 * Generates unique IDs for locally created records BEFORE they are synced to PHP/MySQL.
 * This allows us to create orders and customers offline with no server needed.
 */

export class UUIDGenerator {
    /**
     * Generate a standard UUID v4
     * Used as the primary key for all locally-created records
     */
    static generateUUID() {
        if (typeof crypto !== 'undefined' && crypto.randomUUID) {
            return crypto.randomUUID();
        }
        // Fallback for older browsers
        return 'xxxxxxxx-xxxx-4xxx-yxxx-xxxxxxxxxxxx'.replace(/[xy]/g, function (c) {
            const r = (Math.random() * 16) | 0;
            const v = c === 'x' ? r : (r & 0x3) | 0x8;
            return v.toString(16);
        });
    }

    /**
     * Generate a short ID — good for human-readable references
     */
    static generateShortId() {
        return Math.random().toString(36).substr(2, 9).toUpperCase();
    }

    /**
     * Generate a laundry order number like: LAV-2026-A3B1
     * Unique, human-readable, and sortable by year
     */
    static generateOrderNumber() {
        const year = new Date().getFullYear();
        const random = Math.random().toString(36).substr(2, 4).toUpperCase();
        return `LAV-${year}-${random}`;
    }

    /**
     * Generate a receipt share token (short, URL-safe)
     */
    static generateReceiptToken() {
        return Math.random().toString(36).substr(2, 16);
    }
}
