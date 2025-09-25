# Simple Reservation Data Analysis WordPress Plugin

A comprehensive WordPress plugin for managing and analyzing space reservation data with advanced reporting capabilities.

## What This Plugin Does

This plugin provides a complete reservation management system for organizations that need to track space usage by different groups. It's designed for facilities management, event coordination, and space utilization analysis.

### Core Functionality

**📊 Reservation Management**
- Create, read, update, and delete reservation records
- Track group names, event dates, locations, and duration
- Paginated table view with sortable columns
- Duplicate prevention using SHA256 hashing

**📈 Advanced Analytics & Reports**
- Interactive pie charts showing usage distribution
- Group time usage analysis with clickable drill-down
- Space utilization reports
- Modal popups showing detailed breakdowns

**📁 Bulk Data Import**
- Excel file upload with column mapping
- Duplicate detection and prevention
- Error reporting with specific cell references
- Batch processing with detailed statistics

**🎨 Professional Interface**
- WordPress admin theme integration
- Twig templating for clean, maintainable views
- Responsive design with Chart.js visualizations
- Admin bar quick access menus

## Technical Architecture

### Dependencies
- **Symfony Components**: Dependency injection, configuration, YAML parsing
- **Doctrine ORM**: Object-relational mapping for data persistence
- **Twig**: Modern templating engine for clean view separation
- **PhpSpreadsheet**: Excel file processing and data extraction
- **Chart.js**: Interactive data visualizations

### Data Model
```php
Reservation Entity:
- Group Name (string)
- Date of Event (string)
- Location Name (string)  
- Duration (integer, hours)
- Hash (SHA256 for duplicate detection)
```

### Key Features

**Smart Sorting & Pagination**
- Server-side sorting across entire dataset
- Maintains sort state during pagination
- Clickable column headers for intuitive UX

**Duplicate Prevention**
- Automatic hash generation from all fields
- Prevents identical reservations during bulk upload
- Maintains data integrity

**Interactive Reports**
- Clickable group names reveal space usage patterns
- Real-time modal popups with detailed breakdowns
- Visual pie charts for immediate insights

**Excel Integration**
- Flexible column mapping during upload
- Comprehensive error reporting with cell references
- Support for various Excel formats via PhpSpreadsheet

## File Structure

```
reservation-data-analysis/
├── src/
│   └── Entity/
│       └── Reservation.php          # ORM entity class
├── templates/
│   └── reservations/
│       ├── index.html.twig          # Main listing with pagination
│       ├── create.html.twig         # Add new reservation form
│       ├── edit.html.twig           # Edit existing reservation
│       ├── upload.html.twig         # Excel upload interface
│       ├── upload-result.html.twig  # Upload results display
│       └── reports.html.twig        # Analytics dashboard
├── vendor/                          # Composer dependencies
├── composer.json                    # Dependency configuration
└── reservation-data-analysis.php    # Main plugin file
```

## Installation

1. Upload plugin folder to `/wp-content/plugins/`
2. Run `composer install` in plugin directory
3. Activate plugin in WordPress admin
4. Access via "Reservations" menu in admin dashboard

## Usage

### Adding Reservations
- Navigate to Reservations → Add New
- Fill in group name, date, location, and duration
- Submit to save

### Bulk Upload
- Go to Reservations → Bulk Upload
- Upload Excel file and map columns
- Review results and error reports

### Viewing Reports
- Access Reservations → Reports
- View pie charts and usage tables
- Click group names for detailed space breakdowns

### Managing Data
- Use main reservations page for CRUD operations
- Sort by any column (group, date, location, duration)
- Navigate through pages while maintaining sort order

## Target Use Cases

- **Facility Management**: Track which groups use which spaces most frequently
- **Event Planning**: Analyze booking patterns and space utilization
- **Resource Allocation**: Make data-driven decisions about space assignments
- **Usage Reporting**: Generate insights for stakeholders and planning committees
- **Data Migration**: Import existing reservation data from Excel spreadsheets

## Security Features

- WordPress nonce verification for all form submissions
- Capability checks (`manage_options`) for admin access
- Input sanitization and validation
- SQL injection prevention through WordPress APIs
- XSS protection via Twig auto-escaping

This plugin transforms basic reservation tracking into a powerful analytics platform, providing actionable insights for space management and organizational planning.