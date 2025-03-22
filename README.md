# Auto Product Import for WooCommerce

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

## Requirements

- WordPress 5.0 or higher
- WooCommerce 3.0 or higher
- PHP 7.2 or higher

## Installation

1. Upload the `auto-product-import` folder to the `/wp-content/plugins/` directory
2. Activate the plugin through the 'Plugins' menu in WordPress
3. Go to WooCommerce > Auto Product Import to access the admin interface

## Usage

### Admin Interface

To access the admin interface, go to WooCommerce > Auto Product Import in your WordPress admin dashboard. From there, you can:

1. Import products by entering a URL
2. Configure default settings for imported products

### Frontend Usage

You can display the import form on the frontend using the following shortcode:

```
[auto_product_import_form]
```

Note: Only users with the `manage_woocommerce` capability will be able to use the frontend form.

## How It Works

1. The plugin makes a request to the provided URL and retrieves the HTML content
2. It uses DOM parsing to extract product information from the page
3. Product data is processed and used to create a new WooCommerce product
4. Images are downloaded and attached to the product

## Settings

- **Default Category**: The default category to assign to imported products
- **Default Status**: The default publication status for imported products (Draft, Published, or Pending)

## Notes

- The plugin attempts to extract product information from common HTML structures, but may not work with all websites
- For best results, use URLs from e-commerce sites with standard product page layouts
- The plugin respects user capabilities, ensuring only users with proper permissions can import products

## License

This plugin is released under the GPL v2 or later license.

## Credits

Developed by Your Name 