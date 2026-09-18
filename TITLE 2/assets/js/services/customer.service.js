/**
 * Customer Service — CRUD operations for Customers via IndexedDB
 * Works completely offline. Syncs to PHP in background via SyncManager.
 */

import { CustomerModel } from '../models/customer.model.js';

export class CustomerService {
    constructor(db, syncManager) {
        this.db = db;
        this.syncManager = syncManager;
    }

    /** Create a new customer — works OFFLINE */
    async createCustomer(formData) {
        const customer = CustomerModel.create(formData);
        await this.db.put('customers', customer);
        await this.syncManager.addToQueue('customer', 'create', customer, 8);
        console.log(`[CustomerService] Created: ${customer.first_name}`);
        return customer;
    }

    /** Get all customers */
    async getAllCustomers() {
        const customers = await this.db.getAll('customers');
        return customers.sort((a, b) => a.first_name.localeCompare(b.first_name));
    }

    /** Get a single customer by UUID */
    async getCustomer(id) {
        return this.db.get('customers', id);
    }

    /** Search customers by name or phone */
    async searchCustomers(query) {
        const all = await this.db.getAll('customers');
        const q = query.toLowerCase();
        return all.filter(c =>
            c.first_name.toLowerCase().includes(q) ||
            c.phone.includes(q) ||
            (c.email || '').toLowerCase().includes(q)
        );
    }

    /** Update customer — works OFFLINE */
    async updateCustomer(id, changes) {
        const customer = await this.db.get('customers', id);
        if (!customer) throw new Error('Customer not found in local database');

        const updated = CustomerModel.update(customer, changes);
        await this.db.put('customers', updated);

        await this.syncManager.addToQueue('customer', 'update', updated, 8);
        return updated;
    }

    /** Seed customers from server (initial load or full sync) */
    async seedFromServer(customersJson) {
        for (const customer of customersJson) {
            await this.db.put('customers', { ...customer, synced: true });
        }
        console.log(`[CustomerService] Seeded ${customersJson.length} customers from server`);
    }
}
