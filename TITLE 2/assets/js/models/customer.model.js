/**
 * Customer Model — Business Logic for Customers
 * 
 * Handles validation and creation of customer records locally (IndexedDB).
 * These get synced to PHP/MySQL via SyncManager when online.
 */

import { UUIDGenerator } from '../core/uuid-generator.js';

export class CustomerModel {

    static validate(data) {
        const errors = [];

        if (!data.first_name || data.first_name.trim() === '') {
            errors.push('First name is required.');
        }

        if (!data.phone || data.phone.trim() === '') {
            errors.push('Phone number is required.');
        } else if (!/^[0-9+\-\s()]{7,15}$/.test(data.phone.trim())) {
            errors.push('Phone number format is invalid.');
        }

        if (data.email && data.email.trim() !== '') {
            if (!/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(data.email.trim())) {
                errors.push('Email format is invalid.');
            }
        }

        return { isValid: errors.length === 0, errors };
    }

    /**
     * Create a new customer record ready for IndexedDB.
     */
    static create(formData) {
        const validation = this.validate(formData);
        if (!validation.isValid) {
            throw new Error(validation.errors.join('\n'));
        }

        const now = new Date().toISOString();

        return {
            id: UUIDGenerator.generateUUID(),
            first_name: formData.first_name.trim(),
            phone: formData.phone.trim(),
            email: formData.email?.trim() || null,
            address: formData.address?.trim() || null,
            notes: formData.notes?.trim() || '',
            created_at: now,
            updated_at: now,
            synced: false,
            sync_timestamp: null
        };
    }

    /** Update customer data */
    static update(customer, changes) {
        return {
            ...customer,
            ...changes,
            updated_at: new Date().toISOString(),
            synced: false
        };
    }

    /** Format display name */
    static getDisplayName(customer) {
        return customer.first_name || 'Unknown Customer';
    }
}
