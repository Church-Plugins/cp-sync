# System Requirements

Before installing CP-Sync, please ensure your environment meets the following requirements.

## WordPress Requirements

- WordPress version 5.3 or higher
- A WordPress theme that follows standard coding practices

## Server Requirements

- PHP 7.2 or higher
- MySQL 5.6 or higher (or MariaDB equivalent)
- A database using the `utf8mb4` character set (see below)
- HTTPS support for secure API communication
- PHP cURL extension
- PHP JSON extension
- PHP XML extension (for CCB integration)

### Database Character Set

Your database should use the `utf8mb4` character set. This is the WordPress default, so most sites already meet this requirement — but older sites that have been migrated between hosts are sometimes still on `latin1` or `utf8`, and the setting travels with the database when it moves.

Church management systems routinely contain characters these older character sets cannot represent: bullets (`•`), curly quotes (`’`), and emoji are all common in group and event titles. When the database cannot store a character, MySQL replaces it with `?`, so content imports with substitutions like `Summer Series ? Week 1`.

To check your character set, go to **Tools → Site Health → Info → Database** and look at **Database charset**. CP-Sync also records it at the start of every sync in the logs at **Settings → CP Sync → Logs**:

```
Queue column wp_options.option_value charset: utf8mb4
```

If this reports anything other than `utf8mb4`, ask your host to convert the database. WordPress will not convert `latin1` tables automatically, so a database in this state stays that way through upgrades and migrations until it is converted manually.

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