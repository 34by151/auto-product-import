# Auto Product Import v1.1.1

## Fixed
- Resolved issue with no images being extracted from product pages
- Fixed undefined property and method errors in image extraction code
- Corrected parameter handling in image extraction functions
- Added proper URL resolving for relative image paths
- Enhanced BigCommerce-specific selectors for thumbnail images
- Implemented safer DOM attribute checks with domHasAttribute and domGetAttribute

## Added
- Improved test script for verifying image extraction functionality
- More robust error handling and debugging for image extraction

# Auto Product Import v1.1.0

## Added
- BigCommerce-specific selectors for product images extraction
- Support for `data-image-gallery-new-image-url` attribute for high-quality images
- Auto-conversion of image URLs to higher resolution (1280x1280)
- Blacklist filtering for non-product images (icons, logos, UI elements, etc.)
- DOM-safe wrapper methods for getAttribute and hasAttribute
- Comprehensive testing scripts for image extraction
- Detailed documentation of image extraction improvements
- Detection and filtering of images from "Related Products" sections

## Fixed
- Resolved issue with extracting only one product image instead of all available images
- Fixed DOM method errors with getAttribute and hasAttribute calls
- Improved filtering to exclude irrelevant images from results
- Prevented extraction of images from related products, similar items, and recommended products sections

## Changed
- Restructured image extraction logic with a multi-tier prioritization approach
- Enhanced validation of image URLs with pattern matching
- Implemented more robust filtering for non-product images
- Added DOM structure analysis to identify and exclude related products containers 