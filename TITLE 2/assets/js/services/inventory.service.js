export class InventoryService {
    constructor(db, syncManager) {
        this.db = db;
        this.sync = syncManager;
    }

    async seedFromServer(inventoryItems) {
        if (!inventoryItems || !Array.isArray(inventoryItems)) return;
        for (const item of inventoryItems) {
            await this.db.put('inventory_items', item);
        }
        console.log(`[InventoryService] Seeded ${inventoryItems.length} inventory items`);
    }

    async getItems() {
        return await this.db.getAll('inventory_items');
    }

    async getItem(id) {
        return await this.db.get('inventory_items', id);
    }

    async createItem(data) {
        const item = {
            id: window.lavadora.uuid(),
            name: data.name,
            category: data.category || 'other',
            unit: data.unit || 'ml',
            current_stock: parseFloat(data.current_stock) || 0,
            minimum_stock: parseFloat(data.minimum_stock) || 0,
            cost_per_unit: parseFloat(data.cost_per_unit) || 0,
            is_active: data.is_active !== undefined ? data.is_active : 1,
            created_at: new Date().toISOString(),
            synced: false
        };

        await this.db.add('inventory_items', item);
        await this.sync.addToQueue('inventory_item', 'create', item);
        return item;
    }

    async updateItem(id, data) {
        const item = await this.getItem(id);
        if (!item) throw new Error('Item not found');

        const updated = {
            ...item,
            ...data,
            current_stock: parseFloat(data.current_stock ?? item.current_stock),
            minimum_stock: parseFloat(data.minimum_stock ?? item.minimum_stock),
            cost_per_unit: parseFloat(data.cost_per_unit ?? item.cost_per_unit),
            synced: false
        };

        await this.db.put('inventory_items', updated);
        await this.sync.addToQueue('inventory_item', 'update', updated);
        return updated;
    }

    async adjustStock(itemId, quantity, type = 'receive', reference = '', notes = '') {
        const item = await this.getItem(itemId);
        if (!item) throw new Error('Item not found');

        let newStock = parseFloat(item.current_stock);
        const qty = parseFloat(quantity);

        if (type === 'receive') {
            if (qty < 0) throw new Error('Received quantity cannot be negative.');
            newStock += qty;
        } else if (type === 'adjust') {
            if (qty < 0) throw new Error('Stock cannot be negative.');
            newStock = qty;
        } else if (type === 'consume') {
            newStock -= qty;
            // Allow negative stock offline just in case, or clamp it to 0
        }

        const updated = { ...item, current_stock: newStock, synced: false };
        await this.db.put('inventory_items', updated);

        // Also record a movement
        const movement = {
            id: window.lavadora.uuid(),
            inventory_item_id: itemId,
            type: type,
            quantity: qty,
            reference: reference,
            notes: notes,
            created_at: new Date().toISOString(),
            synced: false
        };
        await this.db.add('inventory_movements', movement);

        // Queue both the item update and the movement for sync
        await this.sync.addToQueue('inventory_item', 'update', updated, 5); // higher priority
        await this.sync.addToQueue('inventory_movement', 'create', movement, 10);

        return updated;
    }
}
