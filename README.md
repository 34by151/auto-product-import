# Auto Product Import for WooCommerce

**Version:** 2.0.0  
**Author:** Kadafs, ArtInMetal  
**License:** GPL v2 or later

A WordPress plugin that allows you to automatically add WooCommerce products from URLs.

## Description

Auto Product Import is a WordPress plugin that allows administrators to import products directly from external URLs. The plugin extracts product information such as title, description, price, and images, and creates a new WooCommerce product with the extracted data.

## Features

- Import products from any URL with just one click
- Automatically extract product title, description, price, and images
- Configure default product settings (category and status)
- Backend admin interface in the WooCommerce menu
- Frontend shortcode to display the import form
- AJAX-powered import process with real-time feedback
- Responsive design for both admin and frontend
- **NEW in v2.0.0:** Full WooCommerce HPOS (High-Performance Order Storage) compatibility
- BigCommerce-specific image extraction with high-resolution support
- Smart filtering to exclude non-product images (icons, logos, UI elements)
- Related products section detection to avoid importing irrelevant images

## Requirements

- WordPress 5.0 or higher
- WooCommerce 6.0 or higher (tested up to 9.0)
- PHP 7.2 or higher

## HPOS Compatibility (NEW in v2.0.0)

This plugin is **fully compatible** with WooCommerce's High-Performance Order Storage (HPOS):

✅ Explicitly declares compatibility with `custom_order_tables` feature  
✅ Works seamlessly with HPOS-enabled WooCommerce stores  
✅ No compatibility warnings in WordPress admin  
✅ Future-proof for WooCommerce's evolution  

**Note:** Although this plugin imports products (not orders), HPOS compatibility declaration is required by WooCommerce to prevent admin warnings. Products remain stored as custom post types regardless of HPOS settings.

## Installation

1. Upload the `auto-product-import` folder to the `/wp-content/plugins/` directory
2. Activate the plugin through the 'Plugins' menu in WordPress
3. Go to WooCommerce > Auto Product Import to access the admin interface
4. Verify no HPOS warnings appear (v2.0.0+)

## Usage

### Admin Interface

To access the admin interface, go to **WooCommerce > Auto Product Import** in your WordPress admin dashboard. From there, you can:

1. Import products by entering a URL
2. Configure default settings for imported products
3. Set maximum number of images to import per product

### Frontend Usage

You can display the import form on the frontend using the following shortcode:

```
[auto_product_import_form]
```

**Note:** Only users with the `manage_woocommerce` capability will be able to use the frontend form.

## How It Works

1. The plugin makes a request to the provided URL and retrieves the HTML content
2. It uses DOM parsing to extract product information from the page
3. Product data is processed and used to create a new WooCommerce product
4. Images are downloaded and attached to the product (with smart filtering)
5. Product attributes are automatically extracted and added
6. Source URL is stored as product meta data

## Settings

- **Default Category**: The default category to assign to imported products
- **Default Status**: The default publication status for imported products (Draft, Published, or Pending)
- **Maximum Images**: Maximum number of images to import per product (1-50, default: 20)

## Changelog

### Version 2.0.0 (Current) - HPOS Compatibility Release
**Released:** Latest Version

**CRITICAL UPDATE - Full HPOS Compatibility**

**Changes:**
- **CRITICAL FIX:** Resolved WooCommerce HPOS compatibility warning that prevented smooth operation
- **Enhanced:** Full compatibility with WooCommerce High-Performance Order Storage (HPOS)
- **Enhanced:** Explicit HPOS compatibility declaration to eliminate WordPress admin warnings
- **Enhanced:** Verified compatibility with WooCommerce 9.0
- **Updated:** Author information changed to "Kadafs, ArtInMetal"
- **Updated:** Plugin version bumped to 2.0.0 for major compatibility release
- **Updated:** WooCommerce tested up to 9.0
- **Updated:** WooCommerce requires at least 6.0
- **Improved:** Enhanced code documentation with HPOS compatibility notes
- **Improved:** Better version management and constants

**HPOS Compatibility Notes:**
- This plugin manages WooCommerce **products**, not orders
- Products remain stored as custom post types even with HPOS enabled
- HPOS (High-Performance Order Storage) specifically handles order data
- Plugin explicitly declares HPOS compatibility to prevent WordPress warnings
- All product operations use standard WooCommerce APIs
- Fully tested and compatible with stores using HPOS for order storage

**Upgrade Notice:**
This is a **non-breaking update** focused on HPOS compatibility. All existing functionality remains unchanged. Update immediately to eliminate HPOS warnings.

### Version 1.1.1
**Released:** 2023-04-01

**Fixed:**
- Resolved issue with no images being extracted from product pages
- Fixed undefined property and method errors in image extraction code
- Corrected parameter handling in image extraction functions
- Added proper URL resolving for relative image paths
- Enhanced BigCommerce-specific selectors for thumbnail images
- Implemented safer DOM attribute checks with domHasAttribute and domGetAttribute

**Added:**
- Improved test script for verifying image extraction functionality
- More robust error handling and debugging for image extraction

### Version 1.1.0
**Released:** 2023-03-24

**Added:**
- BigCommerce-specific selectors for product images extraction
- Support for `data-image-gallery-new-image-url` attribute for high-quality images
- Auto-conversion of image URLs to higher resolution (1280x1280)
- Blacklist filtering for non-product images (icons, logos, UI elements, etc.)
- DOM-safe wrapper methods for getAttribute and hasAttribute
- Comprehensive testing scripts for image extraction
- Detailed documentation of image extraction improvements
- Detection and filtering of images from "Related Products" sections

**Fixed:**
- Resolved issue with extracting only one product image instead of all available images
- Fixed DOM method errors with getAttribute and hasAttribute calls
- Improved filtering to exclude irrelevant images from results
- Prevented extraction of images from related products, similar items, and recommended products sections

**Changed:**
- Restructured image extraction logic with a multi-tier prioritization approach
- Enhanced validation of image URLs with pattern matching
- Implemented more robust filtering for non-product images
- Added DOM structure analysis to identify and exclude related products containers

### Version 1.0.0 - Initial Release

**Features:**
- Initial plugin functionality
- Basic product extraction from URLs
- WooCommerce product creation
- Admin interface for importing products
- Shortcode for frontend product import form

## Upgrade Guide

### Upgrading from v1.x to v2.0.0

**This is a compatibility-focused update:**

1. **Backup First**: Always backup your database before updating
2. **No Data Loss**: All existing settings and data are preserved
3. **No Manual Steps**: Plugin auto-updates with zero configuration needed
4. **HPOS Warning Resolved**: The compatibility warning will disappear after update
5. **Testing Recommended**: Test a product import after upgrading
6. **No Breaking Changes**: All existing functionality works identically

**What Changed:**
- HPOS compatibility explicitly declared
- Version numbers updated
- Author attribution updated
- Enhanced documentation

**What Stayed the Same:**
- All features and functionality
- User interface unchanged
- Settings and configurations preserved
- Import logic unchanged
- No performance impact

## Notes

- The plugin attempts to extract product information from common HTML structures, but may not work with all websites
- For best results, use URLs from e-commerce sites with standard product page layouts
- The plugin respects user capabilities, ensuring only users with proper permissions can import products
- Smart image filtering prevents importing icons, logos, and UI elements
- BigCommerce sites receive specialized extraction with high-resolution image support
- Related products sections are automatically detected and excluded

## License

This plugin is released under the GPL v2 or later license.

## Credits

Developed by **Kadafs, ArtInMetal**

## Support

For issues, feature requests, or contributions, please visit:
- GitHub: https://github.com/34by151/auto-product-import

## Technical Details

### File Structure
```
auto-product-import/
├── auto-product-import.php          (Main plugin file with HPOS declaration)
├── README.md                        (This file)
├── changelog.md                     (Detailed version history)
├── release-notes.md                 (Release notes)
├── includes/
│   └── class-auto-product-import.php (Core functionality)
├── assets/
│   └── js/
│       ├── admin.js                 (Admin JavaScript)
│       └── frontend.js              (Frontend JavaScript)
└── templates/
    ├── admin-page.php               (Admin interface template)
    └── import-form.php              (Frontend form template)
```

### API Compatibility
- Uses standard WooCommerce product creation APIs
- Compatible with WooCommerce REST API
- Works with multisite installations
- HPOS-compatible (custom_order_tables feature)

The plugin is production-ready and fully compatible with the latest WooCommerce versions!
