# Changelog

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
