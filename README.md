# WC Fraud Blocker

A lightweight WordPress plugin for WooCommerce that helps prevent fraudulent orders by blocking suspicious customers based on email addresses and shipping addresses.

**Version:** 1.0.0  
**Author:** [Roly Estemonio](https://rolyestemonio.website)  
**License:** GPL-2.0-or-later

## Description

WC Fraud Blocker provides a simple yet effective way to manage fraud prevention in your WooCommerce store. Add email addresses or shipping addresses to a blocklist, and the plugin automatically prevents those customers from:

- Completing checkout
- Placing orders through alternative channels
- Logging into their accounts

Orders that slip through are automatically cancelled with an admin email alert.

## Requirements

- WordPress 6.0 or later
- PHP 8.0 or later
- WooCommerce 7.0 or later

## Installation

1. Download the plugin files or clone the repository
2. Upload the `wc-fraud-blocker` folder to `/wp-content/plugins/`
3. Activate the plugin through the WordPress admin panel (Plugins > Installed Plugins)
4. WooCommerce must be active for the plugin to function

## Features

### 🛡️ Email Blocking
- Block specific email addresses from checking out or logging in
- Emails are stored in lowercase for case-insensitive matching
- Prevents account access even if the customer tries to log in directly

### 📍 Address Blocking
- Block full shipping and billing addresses
- Smart address matching that ignores:
  - Punctuation (commas, periods, hyphens)
  - Case sensitivity
  - Extra whitespace
- Example: `2526 Delray St, Kalamazoo, MI 49004` matches `2526 delray st kalamazoo mi 49004`

### 🔔 Admin Notifications
- Receive email alerts when orders are auto-cancelled
- Includes customer email and address in the alert
- Easy access to review flagged orders

### ⚡ Performance
- Uses caching to minimize database queries (only 1 database read per page load)
- Lightweight and efficient implementation
- No impact on checkout speed

## Usage

### Managing Blocked Emails and Addresses

1. Go to **WooCommerce > Fraud Blocker** in the WordPress admin
2. Add email addresses or shipping addresses to the blocklist:
   - Enter an email or address in the input field
   - Click **+ Block Email** or **+ Block Address**
3. View and manage your blocklist:
   - Current entries are displayed below the input fields
   - Click the **✕** button to remove any entry
   - A badge shows the total count of blocked entries

### How Blocking Works

**Checkout Validation:**
- When a customer attempts to checkout, their billing email and both shipping/billing addresses are checked against the blocklist
- If a match is found, checkout is prevented with a customer-friendly error message
- No order is created

**Order Safety Net:**
- If an order is created through non-standard flows (REST API, etc.), the plugin automatically cancels it
- An admin email notification is sent with order details
- The order status is set to "Cancelled" with a note about the fraud blocker

**Login Prevention:**
- Blocked email addresses cannot authenticate
- Users see an error: "This account has been suspended. Please contact support."

## How Addresses Are Matched

Addresses are normalized during comparison:
1. Converted to lowercase
2. Punctuation is stripped (commas, periods, hyphens, etc.)
3. Whitespace is collapsed
4. Full address is built from: street address 1, street address 2, city, state, postcode

This ensures flexibility while maintaining accuracy. For example:
- Input: `123 Main St, Apt 4A, Denver, CO 80202`
- Stored: `123 main st apt 4a denver co 80202`
- Both match regardless of punctuation or formatting

## Security

- **Nonce verification** on all AJAX requests
- **Capability check** requiring `manage_woocommerce` permission
- **Input sanitization** for all user inputs
- **Security headers** on all administrative functions
- Values are stored as non-autoload options to minimize overhead

## Database Schema

The plugin uses two WordPress options:

- `wcfb_blocked_emails` — Array of lowercase email addresses
- `wcfb_blocked_addresses` — Array of normalized address strings

Data is cached in memory during page load to minimize database queries.

## File Structure

```
wc-fraud-blocker/
├── assets/
│   ├── admin.css          # Admin page styling
│   └── admin.js           # Admin interface interaction
├── includes/
│   ├── class-wcfb-admin.php      # Admin menu and page rendering
│   ├── class-wcfb-ajax.php       # AJAX handlers for add/remove
│   ├── class-wcfb-blocker.php    # Core blocking logic
│   └── class-wcfb-store.php      # Data storage and retrieval
├── wc-fraud-blocker.php   # Main plugin file
├── README.md              # This file
└── composer.json          # (Optional) PHP dependencies
```

## Code Architecture

### WCFB_Store
Handles all data persistence and retrieval. Uses caching to read the database only once per request.

### WCFB_Blocker
Core blocking logic that hooks into WooCommerce checkout and login flows:
- Validates checkout before order creation
- Auto-cancels orders that slip through
- Blocks login attempts

### WCFB_Admin
Manages the admin interface:
- Creates the settings page under WooCommerce menu
- Renders the blocklist UI
- Enqueues CSS and JavaScript assets

### WCFB_Ajax
Handles AJAX requests for adding and removing entries:
- Input validation
- Security checks
- Error handling with appropriate HTTP status codes

## Filters & Hooks

The plugin uses standard WordPress hooks. Future versions may add custom filters for extending functionality.

## Troubleshooting

### Plugin doesn't appear in admin menu
- Ensure WooCommerce is active
- Verify your account has `manage_woocommerce` capability

### Blocked customers can still checkout
- Verify the email/address is correctly added to the blocklist
- Check that the input matches the format used during checkout
- Note: Shipping address matching uses the full combined address

### Not receiving admin email alerts
- Check your WordPress mail configuration
- Verify the site's `admin_email` option is set correctly
- Check spam/junk folders

## Performance Considerations

- Database reads are limited to 1 per page load via caching
- Options are stored with `autoload=no` to minimize memory overhead
- Efficient string comparison using `str_contains()` for address matching
- Suitable for stores with thousands of blocked entries

## Future Enhancements

Potential features for future versions:
- Bulk import/export functionality
- Regular expression matching for addresses
- IP address blocking
- Geolocation-based blocking
- Audit log of blocked attempts
- Block by phone number or name patterns

## Support

For issues, questions, or feature requests, please visit the plugin repository or contact the author at [rolyestemonio.website](https://rolyestemonio.website)

## License

This plugin is licensed under the GNU General Public License v2.0 or later. See the LICENSE file for details.

---

**Made with ❤️ for WooCommerce store owners**
