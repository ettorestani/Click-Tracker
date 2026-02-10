# Click Tracker - PrestaShop Module

[![Support via PayPal](https://img.shields.io/badge/Support-PayPal-blue?logo=paypal)](https://www.paypal.com/paypalme/ettorestani)
[![PrestaShop](https://img.shields.io/badge/PrestaShop-1.7.x--8.x-blue.svg)](https://www.prestashop.com/)
[![Version](https://img.shields.io/badge/version-1.0.0-green.svg)](https://github.com/ettorestani/Click-Tracker)
[![License](https://img.shields.io/badge/license-AFL%203.0-orange.svg)](https://opensource.org/licenses/AFL-3.0)
[![PHP](https://img.shields.io/badge/php-7.2%2B-blue.svg)](https://www.php.net/)

A PrestaShop module for tracking user clicks on specific page elements (WhatsApp buttons, phone links, Google Maps, CTAs). Monitor customer engagement with actionable analytics and exportable data.

## Disclaimer

This module is provided "as is", without warranty of any kind, express or implied, including but not limited to the warranties of merchantability, fitness for a particular purpose and noninfringement.

**Important:**
- Always test in a **development/staging environment** before production
- Create a **complete backup** of your store before installation
- Verify compatibility with your PrestaShop version and other modules
- The author is **not responsible** for any damage, data loss, or issues caused by using this module

**Use at your own risk.** For production environments, consider using [professional installation services](#-professional-services).

---

## Key Features

### Customizable Click Tracking
- **CSS selector-based tracking**: Track clicks on any element identified by CSS class or ID
- **Automatic element type detection**: WhatsApp, Phone, Maps, Other
- **Page Selectors**: Activate tracking on specific pages using body classes or IDs (e.g., `.module-smartblog-details`, `#checkout`)
- **Two tracking contexts**:
  - Product Pages: captures product ID, name, and category
  - CMS/Blog Pages: captures page URL and title

### Statistics Dashboard
- **Overview cards**: Total clicks, monthly clicks, most clicked type, top product
- **Interactive charts**: Clicks over time (line chart), distribution by element type (pie chart)
- **Rankings**: Top 10 products and top 10 CMS pages by click count
- **Date range filtering**: Analyze any time period

### Logs & Export
- **Complete click log** with filters (context, element type, date range, search)
- **Pagination and sorting**
- **Bulk actions**: Select and delete multiple entries
- **CSV export** with applied filters and UTF-8 support

### Performance & Security
- **Vanilla JavaScript**: Zero dependencies, no jQuery on frontend
- **Async loading**: No impact on page load speed
- **Fire-and-forget tracking**: User clicks are never blocked or delayed
- **CSRF token protection** on every AJAX request
- **Rate limiting**: 30 requests per minute per session
- **SQL injection prevention**: ObjectModel ORM with parameterized queries
- **Input validation and sanitization** on all data

## Compatibility

- **PrestaShop:** 1.7.0.0 - 8.x
- **PHP:** 7.2+
- All standard PrestaShop themes

## Requirements

- PrestaShop 1.7.x or 8.x
- PHP 7.2 or higher

## Installation

### Method 1: Back Office (Recommended)

1. Download the latest version from [Releases](https://github.com/ettorestani/Click-Tracker/releases)
2. Go to your PrestaShop back office
3. Navigate to **Modules** > **Module Manager** > **Upload a module**
4. Select the downloaded ZIP file
5. Click **Configure** after installation

### Method 2: FTP

1. Upload the `clicktracker` folder to `/modules/` on your server
2. In the back office, go to **Modules** > **Module Manager**
3. Search for "Click Tracker" and click **Install**

## Configuration

After installation, go to **Modules** > **Click Tracker** > **Configure**.

### Tab: Configuration

#### CSS Classes to Track

Enter the CSS selectors of elements to track, one per line:

```
.btn-whatsapp
.btn-phone
.btn-maps
.cta-contact
```

#### Page Selectors (Optional)

Activate tracking on specific pages by entering body classes or IDs, one per line. Use a dot for classes and a hash for IDs:

```
.module-smartblog-details
.cms-id-3
#checkout
```

This is useful when you need to track clicks on pages that are neither product nor CMS pages (e.g., blog pages from third-party modules). Leave empty to rely only on the product/CMS detection switches below.

#### Tracking Options

| Setting | Description |
|---------|-------------|
| **Track Product Pages** | Enable/disable tracking on product pages |
| **Track CMS/Blog Pages** | Enable/disable tracking on CMS pages |
| **Track External Links Only** | If enabled, only tracks links with `http://` or `https://` href |
| **Debug Mode** | If enabled, logs tracking events to the browser console |

### Tab: Logs

View all tracked clicks with:
- Filters by context, element type, date range, and search
- Pagination
- Single or bulk delete
- CSV export

### Tab: Statistics

Dashboard with:
- Overview cards (total clicks, monthly clicks, most clicked type, top product)
- Clicks over time chart
- Element type distribution chart
- Context distribution (Product vs CMS)
- Top 10 most clicked products
- Top 10 most clicked CMS pages

## How It Works

### Frontend

1. The module injects a lightweight JavaScript into the page header via the `displayHeader` hook
2. The script uses **event delegation** to capture clicks on configured elements
3. When a tracked click is detected:
   - Collects contextual data (product info or CMS page info)
   - Auto-detects element type (WhatsApp, Phone, Maps, Other)
   - Sends data to the server via an asynchronous AJAX call (fire-and-forget)
4. The user's click is **never blocked or delayed**

### Backend

1. The AJAX controller receives the POST data
2. Validates the CSRF security token
3. Applies rate limiting (30 requests/minute)
4. Sanitizes and validates all inputs
5. Persists data to the database via ObjectModel

## Automatic Element Type Detection

The script auto-detects element types based on:

- **WhatsApp**: href contains `wa.me`, `whatsapp`, `api.whatsapp`; or class contains `whatsapp`, `wa-`
- **Phone**: href starts with `tel:`; or class contains `phone`, `tel`, `call`
- **Maps**: href contains `maps.google`, `google.com/maps`, `goo.gl/maps`; or class contains `maps`, `map`
- **Other**: Everything else

## Usage Examples

### Track a WhatsApp button

```html
<a href="https://wa.me/391234567890" class="btn-whatsapp">
    Contact us on WhatsApp
</a>
```

Add `.btn-whatsapp` to the CSS Classes to Track.

### Track a phone button

```html
<a href="tel:+391234567890" class="btn-phone">
    Call us
</a>
```

Add `.btn-phone` to the CSS Classes to Track.

### Track a Google Maps link

```html
<a href="https://goo.gl/maps/xyz123" class="btn-maps">
    Get directions
</a>
```

Add `.btn-maps` to the CSS Classes to Track.

### Track clicks on a specific blog page

If your blog module adds `.module-smartblog-details` to the body on detail pages, add it to **Page Selectors** to activate tracking on those pages.

## Database Structure

Table: `ps_clicktracker_log`

| Column | Type | Description |
|--------|------|-------------|
| id_clicktracker_log | INT | Primary key (auto-increment) |
| context_type | VARCHAR(20) | `product` or `cms` |
| id_product | INT | Product ID (nullable) |
| product_name | VARCHAR(255) | Product name (nullable) |
| id_category | INT | Category ID (nullable) |
| page_url | VARCHAR(500) | Page URL |
| page_title | VARCHAR(255) | Page title (nullable) |
| clicked_class | VARCHAR(100) | Clicked CSS class |
| element_type | VARCHAR(50) | `whatsapp`, `phone`, `maps`, `other` |
| date_add | DATETIME | Click date/time |

## Troubleshooting

### Clicks are not being tracked

1. Verify CSS classes are configured correctly in the module
2. Verify tracking is enabled for the page type (Product/CMS) or that a matching Page Selector is set
3. Enable Debug Mode and check the browser console
4. Verify there are no JavaScript errors on the page

### JavaScript errors in console

1. Make sure there are no conflicts with other scripts
2. Verify that elements actually have the configured classes

### CSV export not working

1. Verify there is data in the database
2. Check PHP temporary folder write permissions

## Uninstallation

### Warning

Uninstalling this module will **permanently delete**:
- All click tracking data
- All module configuration settings
- The `ps_clicktracker_log` database table

### Uninstall Procedure

1. Go to **Modules** > **Module Manager**
2. Search for "Click Tracker"
3. Click the dropdown arrow and select **Uninstall**
4. Confirm the uninstallation

## Security

- CSRF token protection on every AJAX request
- SQL injection prevention (pSQL, ObjectModel ORM)
- XSS protection (htmlspecialchars, strip_tags)
- Rate limiting (30 requests/minute per session)
- No direct file access (index.php protection)
- Input validation with whitelist approach

---

## Support This Project

This module is **completely free** and will always be.

If you're using it in your business and it's saving you development time, please consider supporting its development:

**[Support via PayPal](https://www.paypal.com/paypalme/ettorestani)**

Even a small contribution helps me:
- Keep the module updated with new PrestaShop versions
- Fix bugs faster
- Add new features based on community feedback

Thank you for your support!

---

*Business using this module? I also offer [professional services](#-professional-services).*

## Professional Services

Need help with your PrestaShop store? I offer:

- **Module Customization** - Tailored modifications to fit your specific needs
- **Complete PrestaShop E-commerce Development** - From setup to launch
- **Performance Optimization** - Speed up your store
- **Custom Module Development** - Build exactly what you need

**Contact:** info@ettorestani.it | **Website:** https://www.ettorestani.it

## License

This module is released under the [Academic Free License (AFL 3.0)](https://opensource.org/licenses/AFL-3.0).

## Author

**Ettore Stani**
- Email: info@ettorestani.it
- Website: https://www.ettorestani.it

## Changelog

### Version 1.0.0
- Initial release
- Click tracking on product and CMS pages
- Page Selectors for custom page activation
- Automatic element type detection (WhatsApp, Phone, Maps, Other)
- Statistics dashboard with interactive charts
- CSV export with filters
- Multi-language support (Italian, English)

## Show Your Support

If this module has been helpful:
- Star this repository
- Share it with other developers
- Contribute translations or improvements
- [Support via PayPal](https://www.paypal.com/paypalme/ettorestani)

---

**Made with love by [Ettore Stani](https://www.ettorestani.it)**
