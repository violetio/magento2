# Changelog

## 1.4.6

### Security

- Added `PriceGuard` bounds checks (admin-configurable under Violet → Settings → Price Override Guards) on caller-supplied item prices, shipping amounts and tax amounts, re-validated in `BeforeSalesOrderPlaced` before totals are overridden.
- Products are force-reloaded before price mutation so overridden prices cannot leak through the `ProductRepository` SKU cache.
- Guest billing-address and guest cart-delete endpoints now reject unknown masked IDs and non-guest quotes; billing email is validated and cannot silently replace an existing cart email.
- The API path override must be an https URL on `*.violet.io`; outbound requests enforce TLS verification, https-only, no redirects and timeouts.
- The Create API User admin page enforces its own ACL resource.

### Changed

- Replaced direct `ObjectManager` usage with constructor injection.
- `composer.json` now declares PHP and Magento module requirements; `module.xml` sequence and schema references fixed.
- Order-totals override failures are now logged instead of silently swallowed.
- Added `can_use_for_multishipping=0` to the Violet payment configuration.

## 1.4.5

- `VioletGuestCartRepository::placeOrder` now repairs an existing quote billing address with the shipping country when billing has no country, while still leaving the order-level fallback to create a billing address from shipping data if the billing row is missing entirely.
- The shipping → billing fallback now logs an info message in both layers (quote-level in `placeOrder`, order-level in `BeforeSalesOrderPlaced`) so production telemetry shows when the fallback is engaged.

## 1.4.4

- The `BeforeSalesOrderPlaced` observer now copies the order's shipping address to the billing address when billing is missing or has no country, preventing `AbstractMethod::validate()` from raising a 500 during payment validation on Violet-sourced orders.

## 1.4.3

- Fixed guest billing address assignment to use `validateForCart` and persist the customer email on the quote.

## 1.4.2

- Made the `isAvailable()` override on the `Violet` payment model context-aware.

## 1.4.1

- Restricted the `violet` payment method from being available for internal use.The method is now only usable programmatically through Violet.

## 1.4.0

- Direct order submission support.

## 1.0.0

- Initial release.
