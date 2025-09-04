# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

## Project Overview

This is **Violet_VioletConnect**, a Magento 2 module that integrates Magento stores with Violet's omnichannel distribution platform. The module enables seamless product synchronization, order management, and cart operations through REST API endpoints.

## Architecture

### Module Structure
- **Api/**: Interface definitions for all service contracts
- **Model/**: Business logic, data models, and repository implementations
- **Observer/**: Event observers for product updates, order placement, and inventory changes
- **Controller/**: Web controllers for admin functionality
- **Helper/**: Utility classes and helper functions
- **Block/**: View layer components
- **Setup/**: Database schema and data installation scripts
- **etc/**: Configuration files (XML)
- **view/**: Frontend templates and assets

### Key Components

#### Service Contracts (Api Layer)
The module follows Magento's service contract pattern with interfaces in `Api/`:
- `VioletRepositoryInterface`: Core product and order operations
- `VioletGuestCartItemRepositoryInterface`: Guest cart item management
- `VioletGuestCartRepositoryInterface`: Guest cart operations
- `VioletOrderRepositoryInterface`: Order creation and management

#### Dependency Injection (etc/di.xml)
All API interfaces are mapped to concrete implementations in `Model/ResourceModel/` using Magento's DI system.

#### Event System (etc/events.xml)
The module listens to critical Magento events:
- `catalog_product_save_after`: Product updates
- `checkout_submit_all_after`: Order placement
- `order_cancel_after`: Order cancellations
- `catalog_product_delete_after`: Product deletions
- `cataloginventory_stock_item_save_commit_after`: Inventory changes

#### REST API Endpoints (etc/webapi.xml)
All endpoints are prefixed with `/V1/violet/` and require admin authentication:
- Product operations: `/V1/violet/skus/`, `/V1/violet/sku-children/:sku`
- Cart operations: `/V1/violet/guest-carts/`, `/V1/violet/guest-carts/:cartId/items`
- Order operations: `/V1/violet/orders`, `/V1/violet/orders/:orderId/`
- Configuration: `/V1/violet/configuration/`

#### Payment Integration (etc/config.xml, etc/payment.xml)
Defines a custom "Violet" payment method with specific configuration for offline payment processing.

### Namespace and Registration
- **Namespace**: `Violet\VioletConnect`
- **Module Name**: `Violet_VioletConnect`
- **Registration**: Standard Magento 2 module registration via `registration.php`

## Development Commands

### Magento Commands
Since this is a Magento 2 module, use standard Magento CLI commands:

```bash
# Module management
bin/magento module:enable Violet_VioletConnect
bin/magento module:disable Violet_VioletConnect
bin/magento setup:upgrade
bin/magento setup:di:compile
bin/magento cache:clean
bin/magento cache:flush

# Development mode
bin/magento deploy:mode:set developer
bin/magento deploy:mode:set production

# Static content deployment
bin/magento setup:static-content:deploy
```

### Testing API Endpoints
Test REST API endpoints using tools like Postman or curl:
```bash
# Example: Get SKU count
curl -X GET "https://your-magento-site.com/rest/V1/violet/skus/count" \
  -H "Authorization: Bearer YOUR_ADMIN_TOKEN"
```

## Dependencies

### Magento Core Dependencies (etc/module.xml)
- Magento_Backend
- Magento_Sales
- Magento_Payment  
- Magento_Quote
- Magento_Directory
- Magento_Checkout
- Magento_Config

### Composer
- Type: `magento2-module` 
- License: OSL-3.0, AFL-3.0
- No external PHP dependencies (empty require block)

## Key Files to Understand

When working with this module, these files are essential:
- `etc/module.xml`: Module definition and dependencies
- `etc/di.xml`: Dependency injection configuration
- `etc/webapi.xml`: REST API endpoint definitions
- `etc/events.xml`: Event observer configuration
- `Api/VioletRepositoryInterface.php`: Main service contract
- `registration.php`: Module registration with Magento

## Configuration

The module uses Magento's standard configuration system with XML files in the `etc/` directory. Payment method configuration is defined in `etc/config.xml` and `etc/payment.xml`.

## Development Notes

- Follow Magento 2 coding standards and best practices
- All API endpoints require admin authentication
- The module integrates with Magento's event system for real-time data synchronization
- Use Magento's service contract pattern for all new functionality
- Database schema changes should be handled through Setup scripts