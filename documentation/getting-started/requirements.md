# System Requirements

Before installing CP-Sync, please ensure your environment meets the following requirements.

## WordPress Requirements

- WordPress version 5.3 or higher
- A WordPress theme that follows standard coding practices

## Server Requirements

- PHP 7.2 or higher
- MySQL 5.6 or higher (or MariaDB equivalent)
- HTTPS support for secure API communication
- PHP cURL extension
- PHP JSON extension
- PHP XML extension (for CCB integration)

## Church Management System Requirements

### Planning Center Online (PCO)

- An active Planning Center Online account
- API access enabled for your account
- Permissions to access PCO Groups and/or Calendar modules
- (Optional) Admin access to create an application in the PCO Developer portal

### Church Community Builder (CCB)

- An active CCB account with API access
- API credentials (Username and Password)
- Appropriate permission levels to access group and/or event data

## Browser Requirements

For the admin interface:
- Chrome (latest 2 versions)
- Firefox (latest 2 versions)
- Safari (latest 2 versions)
- Edge (latest 2 versions)

## Additional Requirements

- Stable internet connection for API communication
- WordPress permalinks enabled and configured
- Sufficient PHP memory limit (64MB or higher recommended)
- PHP max_execution_time of 60 seconds or higher for large data imports

## Integration-Specific Requirements

If you plan to use specific integrations:

### CP Groups Integration
- CP Groups plugin installed and activated (version 1.0.0 or higher)

### The Events Calendar Integration
- The Events Calendar plugin (by Modern Tribe) installed and activated (version 5.0 or higher)

## Checking Your Configuration

You can verify your PHP configuration by navigating to **Tools → Site Health** in your WordPress admin dashboard to ensure all requirements are met.