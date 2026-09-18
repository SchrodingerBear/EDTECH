# Technical Implementation Guide
## Offline-First Lavadora System - Code Examples & Implementation Details

---

## Table of Contents
1. [Quick Start Setup](#quick-start-setup)
2. [Core JavaScript Modules](#core-javascript-modules)
3. [Data Models Implementation](#data-models-implementation)
4. [Service Layer Implementation](#service-layer-implementation)
5. [Synchronization System](#synchronization-system)
6. [PHP Backend API](#php-backend-api)
7. [UI Integration](#ui-integration)
8. [Testing & Debugging](#testing--debugging)

---

## Quick Start Setup

### Step 1: Project Structure Setup

Create the following directory structure:

```bash
mkdir -p assets/js/core
mkdir -p assets/js/models
mkdir -p assets/js/services
mkdir -p assets/js/workers
mkdir -p assets/js/ui
mkdir -p assets/js/utils
mkdir -p api
```

### Step 2: Main Application Entry Point

Create `assets/js/app.js`:

```javascript
/**
 * Main Application Entry Point
 * Lavadora Offline-First System
 */

import { Database } from './core/database.js';
import { SyncManager } from './core/sync-manager.js';
import { OfflineDetector } from './core/offline-detector.js';
import { OrderService } from './services/order.service.js';
import { CustomerService } from './services/customer.service.js';
import { AuthService } from './services/auth.service.js';

class LavadoraApp {
    constructor() {
        this.db = null;
        this.syncManager = null;
        this.offlineDetector = null;
        this.services = {};
        this.isInitialized = false;
    }

    async initialize() {
        try {
            console.log('Initializing Lavadora Offline-First System...');
            
            // Initialize core components
            this.db = new Database();
            await this.db.open();
            
            this.offlineDetector = new OfflineDetector();
            this.offlineDetector.initialize();
            
            this.syncManager = new SyncManager(this.db, this.offlineDetector);
            await this.syncManager.initialize();
            
            // Initialize services
            this.services.orders = new OrderService(this.db, this.syncManager);
            this.services.customers = new CustomerService(this.db, this.syncManager);
            this.services.auth = new AuthService(this.db, this.syncManager);
            
            // Set up offline event listeners
            this.setupOfflineListeners();
            
            this.isInitialized = true;
            console.log('Lavadora System initialized successfully');
            
            // Trigger initial sync if online
            if (this.offlineDetector.isOnline()) {
                this.syncManager.fullSync();
            }
            
        } catch (error) {
            console.error('Failed to initialize Lavadora System:', error);
            throw error;
        }
    }

    setupOfflineListeners() {
        this.offlineDetector.onStatusChange((isOnline) => {
            if (isOnline) {
                console.log('Connection restored - initiating sync...');
                this.syncManager.processQueue();
            } else {
                console.log('Connection lost - working offline...');
                this.showOfflineNotification();
            }
        });
    }

    showOfflineNotification() {
        // Show UI notification for offline mode
        const notification = document.createElement('div');
        notification.className = 'offline-notification';
        notification.textContent = 'You are offline. Changes will sync when connection is restored.';
        document.body.appendChild(notification);
        
        setTimeout(() => notification.remove(), 5000);
    }

    // Service accessors
    get orders() { return this.services.orders; }
    get customers() { return this.services.customers; }
    get auth() { return this.services.auth; }
}

// Global app instance
window.lavadoraApp = new LavadoraApp();

// Auto-initialize when DOM is ready
if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', () => {
        window.lavadoraApp.initialize();
    });
} else {
    window.lavadoraApp.initialize();
}
```

---

## Core JavaScript Modules

### 1. Database Module (IndexedDB Wrapper)

Create `assets/js/core/database.js`:

```javascript
/**
 * IndexedDB Wrapper for Lavadora Offline System
 * Provides Promise-based API for database operations
 */

export class Database {
    constructor() {
        this.db = null;
        this.dbName = 'LavadoraOfflineDB';
        this.dbVersion = 1;
        this.stores = [
            'orders',
            'customers', 
            'inventory',
            'services',
            'users',
            'sync_queue',
            'settings',
            'audit_logs'
        ];
    }

    async open() {
        return new Promise((resolve, reject) => {
            const request = indexedDB.open(this.dbName, this.dbVersion);

            request.onerror = () => reject(request.error);
            request.onsuccess = () => {
                this.db = request.result;
                resolve(this.db);
            };

            request.onupgradeneeded = (event) => {
                const db = event.target.result;
                this.createStores(db);
            };
        });
    }

    createStores(db) {
        // Orders store
        if (!db.objectStoreNames.contains('orders')) {
            const orderStore = db.createObjectStore('orders', { keyPath: 'id' });
            orderStore.createIndex('customer_id', 'customer_id', { unique: false });
            orderStore.createIndex('status', 'status', { unique: false });
            orderStore.createIndex('synced', 'synced', { unique: false });
            orderStore.createIndex('created_at', 'created_at', { unique: false });
        }

        // Customers store
        if (!db.objectStoreNames.contains('customers')) {
            const customerStore = db.createObjectStore('customers', { keyPath: 'id' });
            customerStore.createIndex('phone', 'phone', { unique: true });
            customerStore.createIndex('synced', 'synced', { unique: false });
        }

        // Inventory store
        if (!db.objectStoreNames.contains('inventory')) {
            const inventoryStore = db.createObjectStore('inventory', { keyPath: 'id' });
            inventoryStore.createIndex('synced', 'synced', { unique: false });
        }

        // Services store
        if (!db.objectStoreNames.contains('services')) {
            db.createObjectStore('services', { keyPath: 'id' });
        }

        // Users store
        if (!db.objectStoreNames.contains('users')) {
            const userStore = db.createObjectStore('users', { keyPath: 'id' });
            userStore.createIndex('email', 'email', { unique: true });
        }

        // Sync queue store
        if (!db.objectStoreNames.contains('sync_queue')) {
            const syncStore = db.createObjectStore('sync_queue', { keyPath: 'id' });
            syncStore.createIndex('status', 'status', { unique: false });
            syncStore.createIndex('priority', 'priority', { unique: false });
        }

        // Settings store
        if (!db.objectStoreNames.contains('settings')) {
            db.createObjectStore('settings', { keyPath: 'key' });
        }

        // Audit logs store
        if (!db.objectStoreNames.contains('audit_logs')) {
            const auditStore = db.createObjectStore('audit_logs', { keyPath: 'id' });
            auditStore.createIndex('entity_type', 'entity_type', { unique: false });
            auditStore.createIndex('created_at', 'created_at', { unique: false });
        }
    }

    async getAll(storeName) {
        return new Promise((resolve, reject) => {
            const transaction = this.db.transaction(storeName, 'readonly');
            const store = transaction.objectStore(storeName);
            const request = store.getAll();

            request.onsuccess = () => resolve(request.result);
            request.onerror = () => reject(request.error);
        });
    }

    async get(storeName, id) {
        return new Promise((resolve, reject) => {
            const transaction = this.db.transaction(storeName, 'readonly');
            const store = transaction.objectStore(storeName);
            const request = store.get(id);

            request.onsuccess = () => resolve(request.result);
            request.onerror = () => reject(request.error);
        });
    }

    async add(storeName, data) {
        return new Promise((resolve, reject) => {
            const transaction = this.db.transaction(storeName, 'readwrite');
            const store = transaction.objectStore(storeName);
            const request = store.add(data);

            request.onsuccess = () => resolve(request.result);
            request.onerror = () => reject(request.error);
        });
    }

    async update(storeName, data) {
        return new Promise((resolve, reject) => {
            const transaction = this.db.transaction(storeName, 'readwrite');
            const store = transaction.objectStore(storeName);
            const request = store.put(data);

            request.onsuccess = () => resolve(request.result);
            request.onerror = () => reject(request.error);
        });
    }

    async delete(storeName, id) {
        return new Promise((resolve, reject) => {
            const transaction = this.db.transaction(storeName, 'readwrite');
            const store = transaction.objectStore(storeName);
            const request = store.delete(id);

            request.onsuccess = () => resolve();
            request.onerror = () => reject(request.error);
        });
    }

    async query(storeName, indexName, value) {
        return new Promise((resolve, reject) => {
            const transaction = this.db.transaction(storeName, 'readonly');
            const store = transaction.objectStore(storeName);
            const index = store.index(indexName);
            const request = index.getAll(value);

            request.onsuccess = () => resolve(request.result);
            request.onerror = () => reject(request.error);
        });
    }

    async clearStore(storeName) {
        return new Promise((resolve, reject) => {
            const transaction = this.db.transaction(storeName, 'readwrite');
            const store = transaction.objectStore(storeName);
            const request = store.clear();

            request.onsuccess = () => resolve();
            request.onerror = () => reject(request.error);
        });
    }

    async count(storeName) {
        return new Promise((resolve, reject) => {
            const transaction = this.db.transaction(storeName, 'readonly');
            const store = transaction.objectStore(storeName);
            const request = store.count();

            request.onsuccess = () => resolve(request.result);
            request.onerror = () => reject(request.error);
        });
    }
}
```

### 2. UUID Generator Module

Create `assets/js/core/uuid-generator.js`:

```javascript
/**
 * UUID Generator for Lavadora System
 * Generates unique identifiers for offline entities
 */

export class UUIDGenerator {
    static generateUUID() {
        return 'xxxxxxxx-xxxx-4xxx-yxxx-xxxxxxxxxxxx'.replace(/[xy]/g, function(c) {
            const r = Math.random() * 16 | 0;
            const v = c === 'x' ? r : (r & 0x3 | 0x8);
            return v.toString(16);
        });
    }

    static generateShortId() {
        return Math.random().toString(36).substr(2, 9);
    }

    static generateOrderNumber() {
        const date = new Date();
        const year = date.getFullYear();
        const random = Math.floor(Math.random() * 10000).toString().padStart(4, '0');
        return `LAV-${year}-${random}`;
    }
}
```

### 3. Offline Detector Module

Create `assets/js/core/offline-detector.js`:

```javascript
/**
 * Offline Detector for Lavadora System
 * Monitors network connectivity status
 */

export class OfflineDetector {
    constructor() {
        this.isOnlineStatus = navigator.onLine;
        this.listeners = [];
        this.checkInterval = null;
    }

    initialize() {
        // Set up event listeners
        window.addEventListener('online', this.handleOnline.bind(this));
        window.addEventListener('offline', this.handleOffline.bind(this));
        
        // Set up periodic connection check
        this.startPeriodicCheck();
    }

    handleOnline() {
        this.isOnlineStatus = true;
        this.notifyListeners(true);
        console.log('Connection restored');
    }

    handleOffline() {
        this.isOnlineStatus = false;
        this.notifyListeners(false);
        console.log('Connection lost');
    }

    startPeriodicCheck() {
        this.checkInterval = setInterval(() => {
            this.checkConnection();
        }, 30000); // Check every 30 seconds
    }

    async checkConnection() {
        try {
            const response = await fetch(window.location.href, { 
                method: 'HEAD',
                cache: 'no-cache'
            });
            const wasOnline = this.isOnlineStatus;
            this.isOnlineStatus = response.ok;
            
            if (wasOnline !== this.isOnlineStatus) {
                this.notifyListeners(this.isOnlineStatus);
            }
        } catch (error) {
            if (this.isOnlineStatus) {
                this.isOnlineStatus = false;
                this.notifyListeners(false);
            }
        }
    }

    isOnline() {
        return this.isOnlineStatus;
    }

    onStatusChange(callback) {
        this.listeners.push(callback);
    }

    notifyListeners(isOnline) {
        this.listeners.forEach(callback => callback(isOnline));
    }

    destroy() {
        window.removeEventListener('online', this.handleOnline.bind(this));
        window.removeEventListener('offline', this.handleOffline.bind(this));
        if (this.checkInterval) {
            clearInterval(this.checkInterval);
        }
    }
}
```

---

## Data Models Implementation

### 1. Order Model

Create `assets/js/models/order.model.js`:

```javascript
/**
 * Order Model - Business Logic for Orders
 */

import { UUIDGenerator } from '../core/uuid-generator.js';

export class OrderModel {
    static validateOrderData(orderData) {
        const errors = [];

        if (!orderData.customer_id) {
            errors.push('Customer ID is required');
        }

        if (!orderData.items || !Array.isArray(orderData.items) || orderData.items.length === 0) {
            errors.push('At least one item is required');
        }

        orderData.items?.forEach((item, index) => {
            if (!item.service_id) {
                errors.push(`Item ${index + 1}: Service ID is required`);
            }
            if (!item.quantity || item.quantity <= 0) {
                errors.push(`Item ${index + 1}: Valid quantity is required`);
            }
        });

        return {
            isValid: errors.length === 0,
            errors
        };
    }

    static calculateTotals(orderData) {
        let subtotal = 0;
        
        orderData.items.forEach(item => {
            const itemTotal = (item.quantity || 0) * (item.unit_price || 0);
            item.subtotal = itemTotal;
            subtotal += itemTotal;
        });

        const deliveryFee = orderData.pickup_type === 'delivery' ? 50 : 0;
        const discount = orderData.discount || 0;
        const total = subtotal + deliveryFee - discount;

        return {
            subtotal,
            delivery_fee: deliveryFee,
            discount,
            total
        };
    }

    static createOrderObject(orderData) {
        const validation = this.validateOrderData(orderData);
        if (!validation.isValid) {
            throw new Error(validation.errors.join(', '));
        }

        const totals = this.calculateTotals(orderData);
        const now = new Date().toISOString();

        return {
            id: UUIDGenerator.generateUUID(),
            order_no: UUIDGenerator.generateOrderNumber(),
            customer_id: orderData.customer_id,
            status: 'pending',
            items: orderData.items,
            ...totals,
            payment_status: 'unpaid',
            payment_amount: 0,
            pickup_type: orderData.pickup_type || 'pickup',
            pickup_date: orderData.pickup_date,
            notes: orderData.notes || '',
            receipt_token: this.generateReceiptToken(),
            created_at: now,
            updated_at: now,
            synced: false,
            sync_timestamp: null
        };
    }

    static generateReceiptToken() {
        return Math.random().toString(36).substr(2, 16);
    }

    static updateStatus(order, newStatus) {
        const validStatuses = ['pending', 'washing', 'drying', 'ready', 'completed', 'cancelled'];
        
        if (!validStatuses.includes(newStatus)) {
            throw new Error(`Invalid status: ${newStatus}`);
        }

        const updatedOrder = { ...order };
        updatedOrder.status = newStatus;
        updatedOrder.updated_at = new Date().toISOString();
        updatedOrder.synced = false;

        return updatedOrder;
    }

    static getStatusProgress(status) {
        const progressMap = {
            'pending': 10,
            'washing': 30,
            'drying': 50,
            'ready': 80,
            'completed': 100,
            'cancelled': 0
        };
        return progressMap[status] || 0;
    }

    static getStatusLabel(status) {
        const labelMap = {
            'pending': 'Order Received',
            'washing': 'In Progress - Washing',
            'drying': 'In Progress - Drying',
            'ready': 'Ready for Pickup',
            'completed': 'Claimed',
            'cancelled': 'Cancelled'
        };
        return labelMap[status] || status;
    }
}
```

### 2. Customer Model

Create `assets/js/models/customer.model.js`:

```javascript
/**
 * Customer Model - Business Logic for Customers
 */

import { UUIDGenerator } from '../core/uuid-generator.js';

export class CustomerModel {
    static validateCustomerData(customerData) {
        const errors = [];

        if (!customerData.first_name || customerData.first_name.trim() === '') {
            errors.push('First name is required');
        }

        if (!customerData.phone || customerData.phone.trim() === '') {
            errors.push('Phone number is required');
        }

        // Basic phone validation
        const phoneRegex = /^[0-9]{10,15}$/;
        if (customerData.phone && !phoneRegex.test(customerData.phone.replace(/[^0-9]/g, ''))) {
            errors.push('Invalid phone number format');
        }

        // Email validation if provided
        if (customerData.email && customerData.email.trim() !== '') {
            const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
            if (!emailRegex.test(customerData.email)) {
                errors.push('Invalid email format');
            }
        }

        return {
            isValid: errors.length === 0,
            errors
        };
    }

    static createCustomerObject(customerData) {
        const validation = this.validateCustomerData(customerData);
        if (!validation.isValid) {
            throw new Error(validation.errors.join(', '));
        }

        const now = new Date().toISOString();

        return {
            id: UUIDGenerator.generateUUID(),
            first_name: customerData.first_name.trim(),
            phone: customerData.phone.trim(),
            email: customerData.email?.trim() || '',
            address: customerData.address?.trim() || '',
            notes: customerData.notes?.trim() || '',
            created_at: now,
            updated_at: now,
            synced: false,
            sync_timestamp: null
        };
    }

    static searchCustomers(customers, searchTerm) {
        if (!searchTerm || searchTerm.trim() === '') {
            return customers;
        }

        const term = searchTerm.toLowerCase();
        return customers.filter(customer => 
            customer.first_name.toLowerCase().includes(term) ||
            customer.phone.includes(term) ||
            (customer.email && customer.email.toLowerCase().includes(term))
        );
    }
}
```

---

## Service Layer Implementation

### 1. Order Service

Create `assets/js/services/order.service.js`:

```javascript
/**
 * Order Service - High-level Order Operations
 */

import { OrderModel } from '../models/order.model.js';
import { UUIDGenerator } from '../core/uuid-generator.js';

export class OrderService {
    constructor(database, syncManager) {
        this.db = database;
        this.syncManager = syncManager;
    }

    async createOrder(orderData) {
        try {
            // Create order object using model
            const order = OrderModel.createOrderObject(orderData);
            
            // Save to local database
            await this.db.add('orders', order);
            
            // Queue for sync
            await this.syncManager.queueOperation({
                id: UUIDGenerator.generateUUID(),
                operation: 'create',
                entity_type: 'orders',
                entity_id: order.id,
                data: order,
                priority: 1,
                retry_count: 0,
                created_at: new Date().toISOString(),
                status: 'pending'
            });

            // Try immediate sync if online
            if (this.syncManager.offlineDetector.isOnline()) {
                this.syncManager.processQueue();
            }

            return order;
        } catch (error) {
            console.error('Error creating order:', error);
            throw error;
        }
    }

    async getOrders(filters = {}) {
        try {
            let orders = await this.db.getAll('orders');
            
            // Apply filters
            if (filters.status) {
                orders = orders.filter(order => order.status === filters.status);
            }
            
            if (filters.customer_id) {
                orders = orders.filter(order => order.customer_id === filters.customer_id);
            }
            
            if (filters.date_from) {
                orders = orders.filter(order => new Date(order.created_at) >= new Date(filters.date_from));
            }
            
            if (filters.date_to) {
                orders = orders.filter(order => new Date(order.created_at) <= new Date(filters.date_to));
            }

            // Sort by created date descending
            orders.sort((a, b) => new Date(b.created_at) - new Date(a.created_at));

            return orders;
        } catch (error) {
            console.error('Error getting orders:', error);
            throw error;
        }
    }

    async getOrder(orderId) {
        try {
            return await this.db.get('orders', orderId);
        } catch (error) {
            console.error('Error getting order:', error);
            throw error;
        }
    }

    async updateOrderStatus(orderId, newStatus) {
        try {
            const order = await this.getOrder(orderId);
            if (!order) {
                throw new Error('Order not found');
            }

            const updatedOrder = OrderModel.updateStatus(order, newStatus);
            
            // Update local database
            await this.db.update('orders', updatedOrder);
            
            // Queue for sync
            await this.syncManager.queueOperation({
                id: UUIDGenerator.generateUUID(),
                operation: 'update',
                entity_type: 'orders',
                entity_id: orderId,
                data: { status: newStatus },
                priority: 1,
                retry_count: 0,
                created_at: new Date().toISOString(),
                status: 'pending'
            });

            // Try immediate sync if online
            if (this.syncManager.offlineDetector.isOnline()) {
                this.syncManager.processQueue();
            }

            return updatedOrder;
        } catch (error) {
            console.error('Error updating order status:', error);
            throw error;
        }
    }

    async getOrderStats(dateRange = { start: null, end: null }) {
        try {
            const orders = await this.getOrders();
            
            let filteredOrders = orders;
            if (dateRange.start) {
                filteredOrders = filteredOrders.filter(order => 
                    new Date(order.created_at) >= new Date(dateRange.start)
                );
            }
            if (dateRange.end) {
                filteredOrders = filteredOrders.filter(order => 
                    new Date(order.created_at) <= new Date(dateRange.end)
                );
            }

            const stats = {
                total_orders: filteredOrders.length,
                pending: filteredOrders.filter(o => o.status === 'pending').length,
                washing: filteredOrders.filter(o => o.status === 'washing').length,
                drying: filteredOrders.filter(o => o.status === 'drying').length,
                ready: filteredOrders.filter(o => o.status === 'ready').length,
                completed: filteredOrders.filter(o => o.status === 'completed').length,
                cancelled: filteredOrders.filter(o => o.status === 'cancelled').length,
                total_revenue: filteredOrders.reduce((sum, o) => sum + (o.total || 0), 0),
                paid_orders: filteredOrders.filter(o => o.payment_status === 'paid').length,
                unpaid_orders: filteredOrders.filter(o => o.payment_status === 'unpaid').length
            };

            return stats;
        } catch (error) {
            console.error('Error getting order stats:', error);
            throw error;
        }
    }
}
```

### 2. Customer Service

Create `assets/js/services/customer.service.js`:

```javascript
/**
 * Customer Service - High-level Customer Operations
 */

import { CustomerModel } from '../models/customer.model.js';
import { UUIDGenerator } from '../core/uuid-generator.js';

export class CustomerService {
    constructor(database, syncManager) {
        this.db = database;
        this.syncManager = syncManager;
    }

    async createCustomer(customerData) {
        try {
            // Create customer object using model
            const customer = CustomerModel.createCustomerObject(customerData);
            
            // Save to local database
            await this.db.add('customers', customer);
            
            // Queue for sync
            await this.syncManager.queueOperation({
                id: UUIDGenerator.generateUUID(),
                operation: 'create',
                entity_type: 'customers',
                entity_id: customer.id,
                data: customer,
                priority: 1,
                retry_count: 0,
                created_at: new Date().toISOString(),
                status: 'pending'
            });

            // Try immediate sync if online
            if (this.syncManager.offlineDetector.isOnline()) {
                this.syncManager.processQueue();
            }

            return customer;
        } catch (error) {
            console.error('Error creating customer:', error);
            throw error;
        }
    }

    async getCustomers() {
        try {
            return await this.db.getAll('customers');
        } catch (error) {
            console.error('Error getting customers:', error);
            throw error;
        }
    }

    async getCustomer(customerId) {
        try {
            return await this.db.get('customers', customerId);
        } catch (error) {
            console.error('Error getting customer:', error);
            throw error;
        }
    }

    async searchCustomers(searchTerm) {
        try {
            const customers = await this.getCustomers();
            return CustomerModel.searchCustomers(customers, searchTerm);
        } catch (error) {
            console.error('Error searching customers:', error);
            throw error;
        }
    }

    async updateCustomer(customerId, updates) {
        try {
            const customer = await this.getCustomer(customerId);
            if (!customer) {
                throw new Error('Customer not found');
            }

            const updatedCustomer = {
                ...customer,
                ...updates,
                updated_at: new Date().toISOString(),
                synced: false
            };

            // Update local database
            await this.db.update('customers', updatedCustomer);
            
            // Queue for sync
            await this.syncManager.queueOperation({
                id: UUIDGenerator.generateUUID(),
                operation: 'update',
                entity_type: 'customers',
                entity_id: customerId,
                data: updates,
                priority: 1,
                retry_count: 0,
                created_at: new Date().toISOString(),
                status: 'pending'
            });

            // Try immediate sync if online
            if (this.syncManager.offlineDetector.isOnline()) {
                this.syncManager.processQueue();
            }

            return updatedCustomer;
        } catch (error) {
            console.error('Error updating customer:', error);
            throw error;
        }
    }
}
```

---

## Synchronization System

### Sync Manager Implementation

Create `assets/js/core/sync-manager.js`:

```javascript
/**
 * Sync Manager - Handles offline-to-online synchronization
 */

export class SyncManager {
    constructor(database, offlineDetector) {
        this.db = database;
        this.offlineDetector = offlineDetector;
        this.syncInProgress = false;
        this.lastSyncTimestamp = null;
        this.apiBaseUrl = '/api';
    }

    async initialize() {
        // Load last sync timestamp from settings
        try {
            const setting = await this.db.get('settings', 'last_sync_timestamp');
            this.lastSyncTimestamp = setting?.value || null;
        } catch (error) {
            console.log('No previous sync timestamp found');
        }

        // Set up auto-sync on reconnection
        this.offlineDetector.onStatusChange((isOnline) => {
            if (isOnline && !this.syncInProgress) {
                this.processQueue();
            }
        });
    }

    async queueOperation(operation) {
        try {
            await this.db.add('sync_queue', operation);
            console.log('Operation queued for sync:', operation.entity_type, operation.operation);
        } catch (error) {
            console.error('Error queueing operation:', error);
            throw error;
        }
    }

    async processQueue() {
        if (this.syncInProgress || !this.offlineDetector.isOnline()) {
            return;
        }

        this.syncInProgress = true;
        console.log('Starting sync process...');

        try {
            // Get pending operations
            const pendingOps = await this.db.getAll('sync_queue');
            const operationsToProcess = pendingOps
                .filter(op => op.status === 'pending')
                .sort((a, b) => b.priority - a.priority); // Higher priority first

            console.log(`Processing ${operationsToProcess.length} pending operations`);

            for (const operation of operationsToProcess) {
                await this.processOperation(operation);
            }

            // Pull server changes
            await this.pullServerChanges();

            // Update sync timestamp
            await this.updateSyncTimestamp();

            console.log('Sync process completed');
        } catch (error) {
            console.error('Sync process error:', error);
        } finally {
            this.syncInProgress = false;
        }
    }

    async processOperation(operation) {
        try {
            // Update operation status to processing
            operation.status = 'processing';
            await this.db.update('sync_queue', operation);

            // Send to server based on operation type
            let response;
            switch (operation.operation) {
                case 'create':
                    response = await this.apiPost(`/data/${operation.entity_type}`, operation.data);
                    break;
                case 'update':
                    response = await this.apiPut(`/data/${operation.entity_type}/${operation.entity_id}`, operation.data);
                    break;
                case 'delete':
                    response = await this.apiDelete(`/data/${operation.entity_type}/${operation.entity_id}`);
                    break;
                default:
                    throw new Error(`Unknown operation: ${operation.operation}`);
            }

            // If successful, update local entity as synced
            if (response.success) {
                const localEntity = await this.db.get(operation.entity_type, operation.entity_id);
                if (localEntity) {
                    localEntity.synced = true;
                    localEntity.sync_timestamp = new Date().toISOString();
                    await this.db.update(operation.entity_type, localEntity);
                }

                // Mark operation as completed
                operation.status = 'completed';
                await this.db.update('sync_queue', operation);
            } else {
                throw new Error(response.message || 'Sync operation failed');
            }

        } catch (error) {
            console.error(`Error processing operation ${operation.id}:`, error);
            
            // Update operation with error info
            operation.status = 'failed';
            operation.retry_count = (operation.retry_count || 0) + 1;
            await this.db.update('sync_queue', operation);

            // If too many retries, give up
            if (operation.retry_count >= 3) {
                console.error(`Operation ${operation.id} failed after 3 retries`);
            }
        }
    }

    async pullServerChanges() {
        try {
            const response = await this.apiGet('/sync/pull', {
                since: this.lastSyncTimestamp
            });

            if (response.success && response.changes) {
                for (const change of response.changes) {
                    await this.applyServerChange(change);
                }
            }
        } catch (error) {
            console.error('Error pulling server changes:', error);
        }
    }

    async applyServerChange(change) {
        const { entity_type, operation, data } = change;

        try {
            switch (operation) {
                case 'create':
                case 'update':
                    // Check if local version exists
                    const local = await this.db.get(entity_type, data.id);
                    if (local) {
                        // Conflict resolution - server wins for now
                        const merged = { ...local, ...data, synced: true };
                        await this.db.update(entity_type, merged);
                    } else {
                        // New entity from server
                        data.synced = true;
                        await this.db.add(entity_type, data);
                    }
                    break;
                case 'delete':
                    await this.db.delete(entity_type, data.id);
                    break;
            }
        } catch (error) {
            console.error('Error applying server change:', error);
        }
    }

    async fullSync() {
        if (!this.offlineDetector.isOnline()) {
            console.log('Cannot perform full sync - offline');
            return;
        }

        console.log('Starting full sync...');
        await this.processQueue();
    }

    async updateSyncTimestamp() {
        const now = new Date().toISOString();
        this.lastSyncTimestamp = now;
        await this.db.update('settings', {
            key: 'last_sync_timestamp',
            value: now
        });
    }

    // API helper methods
    async apiGet(endpoint, params = {}) {
        const url = new URL(this.apiBaseUrl + endpoint, window.location.origin);
        Object.keys(params).forEach(key => url.searchParams.append(key, params[key]));

        const response = await fetch(url.toString(), {
            method: 'GET',
            headers: {
                'Content-Type': 'application/json',
                'Authorization': `Bearer ${this.getAuthToken()}`
            }
        });

        return await response.json();
    }

    async apiPost(endpoint, data) {
        const response = await fetch(this.apiBaseUrl + endpoint, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'Authorization': `Bearer ${this.getAuthToken()}`
            },
            body: JSON.stringify(data)
        });

        return await response.json();
    }

    async apiPut(endpoint, data) {
        const response = await fetch(this.apiBaseUrl + endpoint, {
            method: 'PUT',
            headers: {
                'Content-Type': 'application/json',
                'Authorization': `Bearer ${this.getAuthToken()}`
            },
            body: JSON.stringify(data)
        });

        return await response.json();
    }

    async apiDelete(endpoint) {
        const response = await fetch(this.apiBaseUrl + endpoint, {
            method: 'DELETE',
            headers: {
                'Content-Type': 'application/json',
                'Authorization': `Bearer ${this.getAuthToken()}`
            }
        });

        return await response.json();
    }

    getAuthToken() {
        // Get auth token from localStorage or session
        return localStorage.getItem('auth_token') || '';
    }
}
```

---

## PHP Backend API

### Sync API Endpoint

Create `api/sync.php`:

```php
<?php
/**
 * Sync API Endpoint
 * Handles synchronization between offline clients and server
 */

require_once '../includes/bootstrap.php';

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

// Handle preflight requests
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

// Verify authentication
$token = $_SERVER['HTTP_AUTHORIZATION'] ?? '';
if (empty($token) || !str_starts_with($token, 'Bearer ')) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

$token = str_replace('Bearer ', '', $token);
// Verify token logic here (simplified for example)

$action = $_GET['action'] ?? '';

try {
    switch ($action) {
        case 'pull':
            handlePull();
            break;
        case 'push':
            handlePush();
            break;
        case 'status':
            handleStatus();
            break;
        default:
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => 'Invalid action']);
    }
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}

function handlePull() {
    $since = $_GET['since'] ?? null;
    $pdo = db();
    
    $changes = [];
    
    // Get order changes
    if ($since) {
        $stmt = $pdo->prepare("SELECT * FROM laundry_orders WHERE updated_at > ? ORDER BY updated_at ASC");
        $stmt->execute([$since]);
        $orders = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        foreach ($orders as $order) {
            $changes[] = [
                'entity_type' => 'orders',
                'operation' => 'update',
                'data' => $order
            ];
        }
    }
    
    // Get customer changes
    if ($since) {
        $stmt = $pdo->prepare("SELECT * FROM customers WHERE updated_at > ? ORDER BY updated_at ASC");
        $stmt->execute([$since]);
        $customers = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        foreach ($customers as $customer) {
            $changes[] = [
                'entity_type' => 'customers',
                'operation' => 'update',
                'data' => $customer
            ];
        }
    }
    
    echo json_encode([
        'success' => true,
        'changes' => $changes,
        'timestamp' => date('c')
    ]);
}

function handlePush() {
    $input = json_decode(file_get_contents('php://input'), true);
    $pdo = db();
    
    if (!$input || !isset($input['changes'])) {
        throw new Exception('Invalid input data');
    }
    
    $results = [];
    
    foreach ($input['changes'] as $change) {
        try {
            $result = processChange($pdo, $change);
            $results[] = ['success' => true, 'change' => $change];
        } catch (Exception $e) {
            $results[] = ['success' => false, 'change' => $change, 'error' => $e->getMessage()];
        }
    }
    
    echo json_encode([
        'success' => true,
        'results' => $results
    ]);
}

function processChange($pdo, $change) {
    $entityType = $change['entity_type'];
    $operation = $change['operation'];
    $data = $change['data'];
    
    switch ($entityType) {
        case 'orders':
            return processOrderChange($pdo, $operation, $data);
        case 'customers':
            return processCustomerChange($pdo, $operation, $data);
        default:
            throw new Exception("Unknown entity type: $entityType");
    }
}

function processOrderChange($pdo, $operation, $data) {
    switch ($operation) {
        case 'create':
            $stmt = $pdo->prepare("INSERT INTO laundry_orders (id, order_no, customer_id, status, subtotal, delivery_fee, discount, total, payment_status, pickup_type, pickup_date, notes, receipt_token, created_at, updated_at) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
            $stmt->execute([
                $data['id'],
                $data['order_no'],
                $data['customer_id'],
                $data['status'],
                $data['subtotal'],
                $data['delivery_fee'],
                $data['discount'],
                $data['total'],
                $data['payment_status'],
                $data['pickup_type'],
                $data['pickup_date'],
                $data['notes'],
                $data['receipt_token'],
                $data['created_at'],
                $data['updated_at']
            ]);
            break;
            
        case 'update':
            $fields = [];
            $values = [];
            
            foreach ($data as $key => $value) {
                if ($key !== 'id') {
                    $fields[] = "$key = ?";
                    $values[] = $value;
                }
            }
            
            $values[] = $data['id'];
            $sql = "UPDATE laundry_orders SET " . implode(', ', $fields) . " WHERE id = ?";
            $stmt = $pdo->prepare($sql);
            $stmt->execute($values);
            break;
    }
    
    return true;
}

function processCustomerChange($pdo, $operation, $data) {
    // Similar implementation for customers
    return true;
}

function handleStatus() {
    echo json_encode([
        'success' => true,
        'status' => 'online',
        'timestamp' => date('c')
    ]);
}
```

### Data API Endpoint

Create `api/data.php`:

```php
<?php
/**
 * Data API Endpoint
 * Handles CRUD operations for offline clients
 */

require_once '../includes/bootstrap.php';

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

// Handle preflight requests
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

// Verify authentication (simplified)
$token = $_SERVER['HTTP_AUTHORIZATION'] ?? '';
if (empty($token)) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

$method = $_SERVER['REQUEST_METHOD'];
$path = $_SERVER['PATH_INFO'] ?? '';

try {
    $segments = explode('/', trim($path, '/'));
    $entityType = $segments[0] ?? null;
    $entityId = $segments[1] ?? null;

    if (!$entityType) {
        throw new Exception('Entity type required');
    }

    switch ($method) {
        case 'GET':
            if ($entityId) {
                getEntity($entityType, $entityId);
            } else {
                getEntities($entityType);
            }
            break;
        case 'POST':
            createEntity($entityType);
            break;
        case 'PUT':
            if (!$entityId) {
                throw new Exception('Entity ID required for update');
            }
            updateEntity($entityType, $entityId);
            break;
        case 'DELETE':
            if (!$entityId) {
                throw new Exception('Entity ID required for delete');
            }
            deleteEntity($entityType, $entityId);
            break;
        default:
            throw new Exception('Method not allowed');
    }
} catch (Exception $e) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}

function getEntities($entityType) {
    $pdo = db();
    
    switch ($entityType) {
        case 'orders':
            $stmt = $pdo->query("SELECT * FROM laundry_orders ORDER BY created_at DESC");
            $data = $stmt->fetchAll(PDO::FETCH_ASSOC);
            break;
        case 'customers':
            $stmt = $pdo->query("SELECT * FROM customers ORDER BY created_at DESC");
            $data = $stmt->fetchAll(PDO::FETCH_ASSOC);
            break;
        default:
            throw new Exception("Unknown entity type: $entityType");
    }
    
    echo json_encode(['success' => true, 'data' => $data]);
}

function getEntity($entityType, $entityId) {
    $pdo = db();
    
    switch ($entityType) {
        case 'orders':
            $stmt = $pdo->prepare("SELECT * FROM laundry_orders WHERE id = ?");
            $stmt->execute([$entityId]);
            $data = $stmt->fetch(PDO::FETCH_ASSOC);
            break;
        case 'customers':
            $stmt = $pdo->prepare("SELECT * FROM customers WHERE id = ?");
            $stmt->execute([$entityId]);
            $data = $stmt->fetch(PDO::FETCH_ASSOC);
            break;
        default:
            throw new Exception("Unknown entity type: $entityType");
    }
    
    if (!$data) {
        http_response_code(404);
        echo json_encode(['success' => false, 'message' => 'Entity not found']);
        return;
    }
    
    echo json_encode(['success' => true, 'data' => $data]);
}

function createEntity($entityType) {
    $input = json_decode(file_get_contents('php://input'), true);
    $pdo = db();
    
    switch ($entityType) {
        case 'orders':
            $stmt = $pdo->prepare("INSERT INTO laundry_orders (id, order_no, customer_id, status, subtotal, delivery_fee, discount, total, payment_status, pickup_type, pickup_date, notes, receipt_token, created_at, updated_at) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
            $stmt->execute([
                $input['id'],
                $input['order_no'],
                $input['customer_id'],
                $input['status'],
                $input['subtotal'],
                $input['delivery_fee'],
                $input['discount'],
                $input['total'],
                $input['payment_status'],
                $input['pickup_type'],
                $input['pickup_date'],
                $input['notes'],
                $input['receipt_token'],
                $input['created_at'],
                $input['updated_at']
            ]);
            break;
        case 'customers':
            $stmt = $pdo->prepare("INSERT INTO customers (id, first_name, phone, email, address, notes, created_at, updated_at) VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
            $stmt->execute([
                $input['id'],
                $input['first_name'],
                $input['phone'],
                $input['email'],
                $input['address'],
                $input['notes'],
                $input['created_at'],
                $input['updated_at']
            ]);
            break;
        default:
            throw new Exception("Unknown entity type: $entityType");
    }
    
    echo json_encode(['success' => true, 'id' => $input['id']]);
}

function updateEntity($entityType, $entityId) {
    $input = json_decode(file_get_contents('php://input'), true);
    $pdo = db();
    
    switch ($entityType) {
        case 'orders':
            $fields = [];
            $values = [];
            
            foreach ($input as $key => $value) {
                if ($key !== 'id') {
                    $fields[] = "$key = ?";
                    $values[] = $value;
                }
            }
            
            $values[] = $entityId;
            $sql = "UPDATE laundry_orders SET " . implode(', ', $fields) . " WHERE id = ?";
            $stmt = $pdo->prepare($sql);
            $stmt->execute($values);
            break;
        case 'customers':
            $fields = [];
            $values = [];
            
            foreach ($input as $key => $value) {
                if ($key !== 'id') {
                    $fields[] = "$key = ?";
                    $values[] = $value;
                }
            }
            
            $values[] = $entityId;
            $sql = "UPDATE customers SET " . implode(', ', $fields) . " WHERE id = ?";
            $stmt = $pdo->prepare($sql);
            $stmt->execute($values);
            break;
        default:
            throw new Exception("Unknown entity type: $entityType");
    }
    
    echo json_encode(['success' => true]);
}

function deleteEntity($entityType, $entityId) {
    $pdo = db();
    
    switch ($entityType) {
        case 'orders':
            $stmt = $pdo->prepare("DELETE FROM laundry_orders WHERE id = ?");
            $stmt->execute([$entityId]);
            break;
        case 'customers':
            $stmt = $pdo->prepare("DELETE FROM customers WHERE id = ?");
            $stmt->execute([$entityId]);
            break;
        default:
            throw new Exception("Unknown entity type: $entityType");
    }
    
    echo json_encode(['success' => true]);
}
```

---

## UI Integration

### Example: Orders Page Integration

Modify `admin/orders.php` to include JavaScript:

```php
<?php
require_once '../includes/bootstrap.php';
require_once '../includes/auth.php';

session_start_secure();
require_login();

$pageTitle = 'Orders Management';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= h($pageTitle) ?> - Lavadora</title>
    <link rel="stylesheet" href="<?= url('admin/assets/css/dashboard.css') ?>">
    <style>
        .offline-indicator {
            position: fixed;
            top: 10px;
            right: 10px;
            padding: 10px 20px;
            border-radius: 5px;
            background: #ff6b6b;
            color: white;
            display: none;
            z-index: 1000;
        }
        .offline-indicator.active {
            display: block;
        }
        .sync-status {
            font-size: 12px;
            color: #666;
        }
        .loading {
            opacity: 0.5;
            pointer-events: none;
        }
    </style>
</head>
<body>
    <div id="offline-indicator" class="offline-indicator">
        ⚠️ You are offline - Changes will sync when connection is restored
    </div>

    <?php include 'layout/header.php'; ?>
    
    <main class="dashboard-content">
        <div class="page-header">
            <h1><?= h($pageTitle) ?></h1>
            <div class="sync-status" id="sync-status">
                Sync: <span id="sync-time">Never</span>
            </div>
        </div>

        <div class="orders-container" id="orders-container">
            <div class="loading">Loading orders...</div>
        </div>
    </main>

    <?php include 'layout/footer.php'; ?>

    <script type="module" src="<?= url('assets/js/app.js') ?>"></script>
    <script type="module" src="<?= url('assets/js/ui/orders.ui.js') ?>"></script>
</body>
</html>
```

### Orders UI Module

Create `assets/js/ui/orders.ui.js`:

```javascript
/**
 * Orders UI Module
 * Handles order management interface
 */

export class OrdersUI {
    constructor() {
        this.container = document.getElementById('orders-container');
        this.offlineIndicator = document.getElementById('offline-indicator');
        this.syncStatus = document.getElementById('sync-time');
        this.orders = [];
        this.init();
    }

    async init() {
        // Wait for app to initialize
        while (!window.lavadoraApp?.isInitialized) {
            await new Promise(resolve => setTimeout(resolve, 100));
        }

        this.setupEventListeners();
        await this.loadOrders();
        this.startAutoRefresh();
    }

    setupEventListeners() {
        // Listen for offline status changes
        window.lavadoraApp.offlineDetector.onStatusChange((isOnline) => {
            if (!isOnline) {
                this.offlineIndicator.classList.add('active');
            } else {
                this.offlineIndicator.classList.remove('active');
            }
        });
    }

    async loadOrders() {
        try {
            this.container.innerHTML = '<div class="loading">Loading orders...</div>';
            
            this.orders = await window.lavadoraApp.orders.getOrders();
            this.renderOrders();
            this.updateSyncStatus();
        } catch (error) {
            console.error('Error loading orders:', error);
            this.container.innerHTML = '<div class="error">Error loading orders</div>';
        }
    }

    renderOrders() {
        if (this.orders.length === 0) {
            this.container.innerHTML = '<div class="no-orders">No orders found</div>';
            return;
        }

        let html = '<div class="orders-table-container"><table class="orders-table">';
        html += `
            <thead>
                <tr>
                    <th>Order #</th>
                    <th>Customer</th>
                    <th>Status</th>
                    <th>Total</th>
                    <th>Date</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
        `;

        this.orders.forEach(order => {
            html += `
                <tr data-order-id="${order.id}">
                    <td>${order.order_no}</td>
                    <td>${this.getCustomerName(order.customer_id)}</td>
                    <td><span class="status-badge status-${order.status}">${this.getStatusLabel(order.status)}</span></td>
                    <td>₱${order.total.toFixed(2)}</td>
                    <td>${this.formatDate(order.created_at)}</td>
                    <td>
                        <button class="btn-view" data-order-id="${order.id}">View</button>
                        <button class="btn-status" data-order-id="${order.id}">Update Status</button>
                    </td>
                </tr>
            `;
        });

        html += '</tbody></table></div>';
        this.container.innerHTML = html;

        // Add event listeners to buttons
        this.container.querySelectorAll('.btn-view').forEach(btn => {
            btn.addEventListener('click', (e) => this.viewOrder(e.target.dataset.orderId));
        });

        this.container.querySelectorAll('.btn-status').forEach(btn => {
            btn.addEventListener('click', (e) => this.updateOrderStatus(e.target.dataset.orderId));
        });
    }

    async getCustomerName(customerId) {
        try {
            const customer = await window.lavadoraApp.customers.getCustomer(customerId);
            return customer ? customer.first_name : 'Unknown';
        } catch (error) {
            return 'Unknown';
        }
    }

    getStatusLabel(status) {
        const labels = {
            'pending': 'Pending',
            'washing': 'Washing',
            'drying': 'Drying',
            'ready': 'Ready',
            'completed': 'Completed',
            'cancelled': 'Cancelled'
        };
        return labels[status] || status;
    }

    formatDate(dateString) {
        const date = new Date(dateString);
        return date.toLocaleDateString('en-US', {
            year: 'numeric',
            month: 'short',
            day: 'numeric'
        });
    }

    async viewOrder(orderId) {
        try {
            const order = await window.lavadoraApp.orders.getOrder(orderId);
            // Show order details modal or navigate to order details page
            console.log('View order:', order);
        } catch (error) {
            console.error('Error viewing order:', error);
        }
    }

    async updateOrderStatus(orderId) {
        const newStatus = prompt('Enter new status (pending, washing, drying, ready, completed, cancelled):');
        if (!newStatus) return;

        try {
            await window.lavadoraApp.orders.updateOrderStatus(orderId, newStatus);
            await this.loadOrders(); // Refresh the list
        } catch (error) {
            console.error('Error updating order status:', error);
            alert('Error updating order status: ' + error.message);
        }
    }

    updateSyncStatus() {
        const lastSync = localStorage.getItem('last_sync_timestamp');
        if (lastSync) {
            const date = new Date(lastSync);
            this.syncStatus.textContent = `Last sync: ${date.toLocaleTimeString()}`;
        } else {
            this.syncStatus.textContent = 'Not synced';
        }
    }

    startAutoRefresh() {
        // Refresh orders every 30 seconds
        setInterval(() => {
            this.loadOrders();
        }, 30000);
    }
}

// Initialize orders UI when DOM is ready
if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', () => {
        new OrdersUI();
    });
} else {
    new OrdersUI();
}
```

---

## Testing & Debugging

### Testing IndexedDB Operations

```javascript
// Test database operations
async function testDatabase() {
    const db = new Database();
    await db.open();
    
    // Test adding data
    await db.add('customers', {
        id: 'test-123',
        first_name: 'Test Customer',
        phone: '1234567890',
        email: 'test@example.com',
        created_at: new Date().toISOString()
    });
    
    // Test retrieving data
    const customer = await db.get('customers', 'test-123');
    console.log('Retrieved customer:', customer);
    
    // Test querying
    const allCustomers = await db.getAll('customers');
    console.log('All customers:', allCustomers);
}

testDatabase();
```

### Testing Sync Operations

```javascript
// Test sync functionality
async function testSync() {
    const app = window.lavadoraApp;
    
    // Test offline detection
    console.log('Online status:', app.offlineDetector.isOnline());
    
    // Test creating order offline
    const order = await app.orders.createOrder({
        customer_id: 'customer-123',
        items: [{
            service_id: 'service-1',
            quantity: 5,
            unit_price: 30
        }],
        pickup_type: 'pickup'
    });
    
    console.log('Created order:', order);
    
    // Test sync queue
    const queue = await app.db.getAll('sync_queue');
    console.log('Sync queue:', queue);
}

testSync();
```

### Debugging Tips

1. **Browser DevTools**: Use Application tab to inspect IndexedDB
2. **Console Logging**: Extensive logging in all modules for debugging
3. **Network Tab**: Monitor API calls and sync operations
4. **Service Worker**: Use Service Worker DevTools for offline debugging

---

## Deployment Checklist

- [ ] All JavaScript modules created and tested
- [ ] PHP API endpoints deployed
- [ ] Service Worker registered and functional
- [ ] IndexedDB schema initialized
- [ ] Offline functionality tested
- [ ] Sync operations tested
- [ ] Error handling implemented
- [ ] User training completed
- [ ] Documentation finalized
- [ ] Production deployment completed

---

## Conclusion

This technical implementation guide provides the foundational code and structure for building the offline-first Lavadora system. The modular architecture allows for incremental development and testing, ensuring a stable and robust final product.

The key benefits of this approach include:
- **Offline capability** for uninterrupted operation
- **Automatic synchronization** when connectivity is restored
- **Client-side processing** for improved performance
- **Minimal server dependency** for reduced infrastructure costs
- **Progressive enhancement** for broad compatibility

Start with Phase 1 (Foundation) and work through each phase systematically, testing thoroughly before proceeding to the next phase.