# E-Receipt System Setup Instructions

## Overview
The e-receipt system has been implemented to allow customers to view their laundry order details via a shareable shortened link. The receipt includes order progress, estimated completion time, and customer information.

## Setup Steps

### 1. Clear Services (Keep Only 30/kg Service)
Run the script to remove all existing services and keep only the "Laundry Service" at 30 per kilogram:

```
http://localhost/G7-4D-THESIS/TITLE%202/run_clear_services.php
```

Or run directly:
```
http://localhost/G7-4D-THESIS/TITLE%202/database/clear_services.php
```

### 2. Add E-Receipt System to Database
Run the script to add the receipt tracking columns to the database:

```
http://localhost/G7-4D-THESIS/TITLE%202/run_add_receipt_system.php
```

Or run directly:
```
http://localhost/G7-4D-THESIS/TITLE%202/database/add_receipt_system.php
```

This will add:
- `receipt_token` column (unique token for public access)
- `receipt_sent_at` column (when receipt was sent)
- `receipt_viewed_at` column (when customer first viewed)
- `receipt_view_count` column (view tracking)
- Index for faster token lookups
- Generate tokens for existing orders

## Features Implemented

### ✅ E-Receipt Page (`receipt.php`)
- **Public Access**: Customers can view receipts via `receipt.php?token=XYZ`
- **Order Progress**: Visual progress bar showing order status
- **Status Descriptions**: Clear status messages (pending, washing, drying, ready, completed)
- **Customer Information**: Name, phone, email, address
- **Order Details**: Order number, date, pickup type, items breakdown
- **Financial Summary**: Subtotal, delivery fee, discount, total, payment status
- **Ready Date**: Shows "Ready for Pickup on [date]" when status is ready
- **Estimated Completion**: Shows estimated ready date for active orders
- **Copy Link**: Button to copy receipt link to clipboard
- **Business Info**: Displays laundry business contact details

### ✅ Admin Integration
- **Receipt Button**: Added mail icon button in orders list to generate receipt links
- **Auto-Generation**: New orders automatically get receipt tokens
- **Send Tracking**: Records when receipts are sent to customers
- **View Tracking**: Records when customers view their receipts

### ✅ Helper Functions
- `generate_receipt_token()`: Creates unique receipt tokens
- `get_receipt_url()`: Generates public receipt URLs
- `get_or_create_receipt_token()`: Gets or creates receipt tokens for orders

## Receipt Features

### Progress Bar Status
- **Pending**: 10% - "Order Received"
- **Washing**: 30% - "In Progress - Washing"  
- **Drying**: 50% - "In Progress - Drying"
- **Ready**: 80% - "Ready for Pickup"
- **Completed**: 100% - "Completed"
- **Cancelled**: 0% - "Cancelled"

### Receipt Link Format
```
http://localhost/G7-4D-THESIS/TITLE%202/receipt.php?token=ABC123XYZ
```

The token is a shortened, unique identifier that maps to the order without exposing the internal order ID.

## Usage

### For Admin Staff
1. Go to Orders page
2. Click the mail icon (📧) next to any order
3. The system generates a receipt link
4. Copy and send the link to the customer via SMS, email, or messaging app

### For Customers
1. Click the receipt link sent by the laundry service
2. View their order details, progress, and estimated completion
3. See contact information for the laundry service
4. Copy the link to share with others if needed

## Database Changes

### New Columns in `laundry_orders` table:
- `receipt_token` VARCHAR(32) UNIQUE - Public access token
- `receipt_sent_at` DATETIME - When receipt was sent
- `receipt_viewed_at` DATETIME - First view timestamp
- `receipt_view_count` INT UNSIGNED - View counter

### New Index:
- `idx_receipt_token` on `receipt_token` column

## Testing

1. **Test Database Setup**: Run the add_receipt_system.php script
2. **Test Receipt Generation**: Create a new order and check if receipt token is generated
3. **Test Receipt Viewing**: Access the receipt page using the token
4. **Test Progress Updates**: Change order status and verify progress bar updates
5. **Test Copy Link**: Verify the copy link functionality works

## Customization

### Modify Progress Percentages
Edit the `$statusProgress` array in `receipt.php`:
```php
$statusProgress = [
    'pending' => 10,
    'washing' => 30,
    'drying' => 50,
    'ready' => 80,
    'completed' => 100,
    'cancelled' => 0
];
```

### Modify Status Labels
Edit the `$statusLabels` array in `receipt.php`:
```php
$statusLabels = [
    'pending' => 'Order Received',
    'washing' => 'In Progress - Washing',
    // etc.
];
```

### Modify Ready Date Calculation
Edit the estimated ready date calculation in `receipt.php`:
```php
$estimatedReady = clone $pickupDate;
$estimatedReady->modify('+1 day'); // Change this line
```

## Troubleshooting

### Receipt link not working
- Verify the receipt token exists in the database
- Check that the token is being passed correctly in the URL
- Ensure the receipt.php file is accessible

### Progress bar not showing
- Verify the order status matches one of the defined statuses
- Check that the status is being updated correctly in the database

### Receipt token not generating for new orders
- Ensure the add_receipt_system.php script was run successfully
- Check that the database columns were added correctly
- Verify the order creation code in orders.php

## Security Notes

- Receipt tokens are generated using MD5 hash of order details + order ID
- Tokens are unique and not easily guessable
- Receipt page is public but only shows order information (no admin access)
- View tracking helps monitor if receipts are being accessed appropriately

## Future Enhancements

Potential improvements:
- Email integration to automatically send receipts
- SMS integration for text message delivery
- QR code generation for easy scanning
- Customer notification system for status updates
- Receipt download as PDF
- Multi-language support
