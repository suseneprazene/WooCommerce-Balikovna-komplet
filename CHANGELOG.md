# Changelog

All notable changes to this project will be documented in this file.

## [1.0.0] - 2024-02-05

### Added
- Initial release of WooCommerce Balíkovna Komplet plugin
- Custom WooCommerce shipping method for Czech Post Balíkovna
- Integration with Czech Post API (http://napostu.ceskaposta.cz/vystupy/balikovny.xml)
- Database tables for branches and opening hours
- REST API endpoints:
  - `/wp-json/balikovna/v1/search` - Search branches
  - `/wp-json/balikovna/v1/hours/{id}` - Get opening hours
- Checkout integration with Select2 dropdown for branch selection
- Advanced search functionality (by city, ZIP code, address)
- Opening hours tooltip on branch selection
- Admin settings page in WooCommerce → Settings → Balíkovna
- Manual data synchronization from Czech Post API
- Branch information display in:
  - Admin order page (meta box)
  - Customer order details
  - Email notifications (HTML and plain text)
- Configurable shipping cost
- Free shipping threshold option
- WordPress transients caching (24 hours)
- Full internationalization support
- Git Updater plugin support for automatic updates
- Responsive design for mobile devices

### Security
- All inputs sanitized using WordPress functions
- All outputs properly escaped
- SQL queries use prepared statements
- Table names properly escaped with `esc_sql()`
- AJAX requests validated (where applicable)
- HPOS-compatible order meta methods

### Technical
- WordPress 5.0+ compatibility
- WooCommerce 5.0+ compatibility
- PHP 7.4+ compatibility
- WooCommerce HPOS (High-Performance Order Storage) compatible
- Follows WordPress Coding Standards
- Singleton pattern implementation
- Clean OOP architecture

### Files Structure
```
woocommerce-balikovna-komplet/
├── woocommerce-balikovna-komplet.php  # Main plugin file
├── includes/
│   ├── class-wc-balikovna-install.php    # Installation & sync
│   ├── class-wc-balikovna-api.php        # REST API endpoints
│   ├── class-wc-balikovna-shipping.php   # Shipping method
│   ├── class-wc-balikovna-checkout.php   # Checkout integration
│   ├── class-wc-balikovna-admin.php      # Admin functionality
│   ├── class-wc-balikovna-settings.php   # Settings page
│   └── class-wc-balikovna-order.php      # Order display
├── assets/
│   ├── css/balikovna-checkout.css        # Frontend styles
│   └── js/balikovna-checkout.js          # Frontend JavaScript
├── README.md                             # Documentation
├── CHANGELOG.md                          # This file
└── .gitignore                            # Git ignore rules
```

### Dependencies
- Select2 4.1.0 (loaded from CDN)
- jQuery (WordPress default)

## Future Plans

### [1.1.0] - Planned
- [ ] Local Select2 library (remove CDN dependency)
- [ ] Additional language translations
- [ ] Export branch data to CSV
- [ ] Integration with shipping label printing
- [ ] Track & Trace integration
- [ ] Automatic branch selection based on customer address
- [ ] Map view for branch selection

### [1.2.0] - Planned
- [ ] Multiple shipping methods support
- [ ] Custom email templates
- [ ] Advanced filtering options in admin
- [ ] Statistics and reporting
- [ ] API rate limiting and error handling improvements

---

## Legend
- `Added` for new features
- `Changed` for changes in existing functionality
- `Deprecated` for soon-to-be removed features
- `Removed` for now removed features
- `Fixed` for any bug fixes
- `Security` for security-related changes
