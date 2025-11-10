# TODO: Admin Color Configuration System

## Feature Description
Allow admins to customize the colors used for parent and child features throughout the site via an admin page.

## Current Implementation
- Parent features: Hard-coded colors in PHP files
- Child features: Hard-coded to `#17a2b8` (teal/info color)
- These colors are scattered throughout:
  - `/data/moop/tools/display/parent_display.php`
  - `/data/moop/tools/display/display_functions.php`
  - Other display pages

## Current Color References
- **Parent feature badge:** `#17a2b8` (used in line 180 of parent_display.php)
- **Child feature badge:** `#17a2b8` (used in lines 203, 280 of parent_display.php)
- **Child card header background:** `rgba(23, 162, 184, 0.1)` (light teal, line 276)
- **Annotation section borders:** Various Bootstrap colors based on annotation type

## Proposed Solution

### 1. Database Storage
Create a `feature_colors` configuration in site_config or new JSON file:
```json
{
  "colors": {
    "parent_badge": "#17a2b8",
    "parent_text": "text-white",
    "child_badge": "#17a2b8",
    "child_text": "text-white",
    "child_header_bg": "rgba(23, 162, 184, 0.1)"
  }
}
```

### 2. Admin Page
Create `/data/moop/admin/manage_colors.php`
- Color picker for parent feature color
- Color picker for child feature color
- Preview of how colors will look
- Save button to update configuration

### 3. Helper Function
Create `getFeatureColors()` function in `common_functions.php`:
```php
function getFeatureColors() {
    $config_file = CONFIG_PATH . '/feature_colors.json';
    if (file_exists($config_file)) {
        return json_decode(file_get_contents($config_file), true);
    }
    // Return defaults
    return [
        'parent_badge' => '#17a2b8',
        'parent_text' => 'text-white',
        'child_badge' => '#17a2b8',
        'child_text' => 'text-white',
        'child_header_bg' => 'rgba(23, 162, 184, 0.1)'
    ];
}
```

### 4. Update Display Pages
Replace hard-coded colors with dynamic values:
```php
$colors = getFeatureColors();
echo "<span style=\"background-color: {$colors['child_badge']};\">";
```

### 5. Add to Admin Index
Add card to `/data/moop/admin/index.php`:
```
Feature Colors Configuration
- Link to color management page
- Preview of current color scheme
```

## Implementation Steps
1. Create configuration file structure
2. Build helper function for color retrieval
3. Create admin management page with color pickers
4. Update parent_display.php to use dynamic colors
5. Update display_functions.php to use dynamic colors
6. Add link to admin index
7. Test color changes propagate across all pages

## Files to Modify
- `/data/moop/admin/manage_colors.php` (NEW)
- `/data/moop/admin/index.php` (ADD LINK)
- `/data/moop/common_functions.php` (ADD HELPER)
- `/data/moop/tools/display/parent_display.php` (USE DYNAMIC COLORS)
- `/data/moop/tools/display/display_functions.php` (USE DYNAMIC COLORS)
- Configuration file (new or updated site_config)

## Notes
- Use HTML5 color input for color picker
- Validate color format before saving
- Ensure colors have sufficient contrast with text
- Consider adding preset color schemes
- Add color preview before saving changes
