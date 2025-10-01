# Changelog

All notable changes to the Auto Product Import plugin will be documented in this file.

## [2.0.0] - 2025-01-XX - HPOS Compatibility Release

### Critical Update
This major version release focuses on WooCommerce HPOS (High-Performance Order Storage) compatibility and resolves admin warnings.

### Added
- **HPOS Compatibility Declaration**: Explicitly declares compatibility with WooCommerce's `custom_order_tables` feature
- **Enhanced Documentation**: Comprehensive HPOS compatibility notes in code
- **Version Display**: Better version management and tracking

### Changed
- **Version**: Bumped to 2.0.0 to reflect major compatibility milestone
- **Author**: Updated to "Kadafs, ArtInMetal"
- **WC Requires**: Minimum WooCommerce version 6.0.0 (for HPOS support)
- **WC Tested**: Verified compatibility up to WooCommerce 9.0.0
- **Plugin Header**: Enhanced with detailed HPOS compatibility documentation

### Fixed
- **CRITICAL**: Resolved "incompatible with HPOS" warning in WordPress admin
- **Admin Notices**: Eliminated compatibility warnings for HPOS-enabled stores

### Technical Notes
- Plugin manages **products** (not orders), so no functional changes needed for HPOS
- Products remain as custom post types even with HPOS enabled
- HPOS specifically affects order storage, not product storage
- Compatibility declaration required by WooCommerce for all active plugins
- No breaking changes - all existing functionality preserved
- No database migrations required
- Zero impact on performance or user experience

## [1.1.1] - 2023-04-01

### Fixed
- Resolved issue with no images being extracted from product pages
- Fixed undefined property and method errors in image extraction code
- Corrected parameter handling in image extraction functions
- Added proper URL resolving for relative image paths
- Enhanced BigCommerce-specific selectors for thumbnail images
- Implemented safer DOM attribute checks with domHasAttribute and domGetAttribute

### Added
- Improved test script for verifying image extraction functionality
- More robust error handling and debugging for image extraction

## [1.1.0] - 2023-03-24

### Added
- BigCommerce-specific selectors for product images extraction
- Support for `data-image-gallery-new-image-url` attribute for high-quality images
- Auto-conversion of image URLs to higher resolution (1280x1280)
- Blacklist filtering for non-product images (icons, logos, UI elements, etc.)
- DOM-safe wrapper methods for getAttribute and hasAttribute
- Comprehensive testing scripts for image extraction
- Detailed documentation of image extraction improvements (README-image-extraction.md)
- Detection and filtering of images from "Related Products" sections

### Fixed
- Resolved issue with extracting only one product image instead of all available images
- Fixed DOM method errors with getAttribute and hasAttribute calls
- Improved filtering to exclude irrelevant images from results
- Prevented extraction of images from related products, similar items, and recommended products sections

### Changed
- Restructured image extraction logic with a multi-tier prioritization approach
- Enhanced validation of image URLs with pattern matching
- Implemented more robust filtering for non-product images
- Added DOM structure analysis to identify and exclude related products containers

## [1.0.0] - Initial Release

### Added
- Initial plugin functionality
- Basic product extraction from URLs
- WooCommerce product creation with standard WC_Product API
- Admin interface for importing products
- Shortcode for frontend product import form
- AJAX-powered import process with real-time feedback
- Product title extraction from various HTML structures
- Product description extraction with fallback selectors
- Product price extraction with regex pattern matching
- Product image extraction and download
- Image attachment to WooCommerce products
- Gallery image support
- Product category assignment
- Product status configuration (draft/published/pending)
- SKU generation for imported products
- Source URL tracking as product meta data
- User capability checks for security
- Admin settings page
- Frontend form template
- Responsive admin interface
- Error handling and logging
- Translation-ready with text domain

### Features
- DOM-based HTML parsing
- Multiple selector strategies for data extraction
- Image validation and filtering
- Automatic image resolution detection
- Support for relative and absolute URLs
- User-friendly admin interface
- Frontend shortcode support
- AJAX progress indicators
- Success/error messaging
- Product edit and view links after import

---

## Version Comparison

| Version | Focus | Breaking Changes | HPOS Compatible |
|---------|-------|------------------|-----------------|
| 2.0.0 | HPOS Compatibility | None | ✅ Yes |
| 1.1.1 | Bug Fixes | None | ⚠️ Not Declared |
| 1.1.0 | Image Extraction | None | ⚠️ Not Declared |
| 1.0.0 | Initial Release | N/A | ⚠️ Not Declared |

## Upgrade Notes

### From 1.x to 2.0.0
- **Safe to upgrade**: No breaking changes
- **Backup recommended**: Standard best practice
- **Zero configuration**: Auto-updates with no setup needed
- **HPOS warning disappears**: Immediately after activation
- **All features preserved**: 100% backward compatible
- **Test after upgrade**: Recommended to verify imports still work

### What's Not Changing
- User interface remains identical
- All import functionality works the same
- No database schema changes
- No settings migration needed
- No performance impact
- No new dependencies

## Future Roadmap

Potential features for future versions:
- Bulk import from CSV/Excel
- Scheduled automatic imports
- Import templates for different sites
- Advanced image quality detection
- Product variation support
- Custom field mapping
- Import history and logging
- Webhook support for real-time imports
- API endpoints for programmatic imports

---

For detailed information about HPOS compatibility, see the main README.md file.
