# CP-Sync Development Guide

## Build Commands
- `npm run build` - Build assets using wpackio-scripts
- `npm run build:wp` - Build assets using wp-scripts
- `npm run build:all` - Build all assets
- `npm run start` - Start development server with hot reloading
- `npm run start:wp` - Start WP Scripts development server
- `npm run lint:wp` - Run ESLint on JavaScript files
- `npm run format:wp` - Format code with Prettier

## Code Style
- Follow WordPress coding standards for PHP and JavaScript
- Use PSR-4 autoloading for PHP classes with CP_Sync namespace
- React components are functional with hooks
- Organize SCSS with variables and component-based structure
- Use namespaced exceptions for error handling
- Maintain consistent naming: CamelCase for classes, snake_case for functions
- Include comprehensive docblocks for functions and classes
- Keep code DRY, especially with ChMS integrations

## Project Structure
- `includes/` - Main PHP classes and core functionality
  - `Admin/` - Admin-side settings and functionality
  - `ChMS/` - Church Management System integrations (PCO, CCB)
  - `ChurchPlugins/` - Shared libraries and utilities
  - `Integrations/` - Integration with other plugins (CP_Groups, TEC)
  - `Setup/` - Plugin initialization and setup
- `src/` - JavaScript/React source files
  - `admin/settings/` - Admin settings UI components
- `assets/` - Static assets (images, compiled JS/CSS)
- `dist/` - Build output directory
- `documentation/` - Customer documentation

## ChMS Integrations
- Planning Center Online (PCO) - REST API integration
- Church Community Builder (CCB) - XML API integration
- API credentials stored in WordPress options
- Data filters and rate limiters in place for API requests

## Documentation
- Customer documentation in `/documentation/` organized by topic
- Main sections: Getting Started, Configuration, ChMS, Integrations, Advanced, Support
- Reference `/documentation/README.md` for documentation structure overview
- When adding features, update relevant documentation files

## Git Workflow
- Make descriptive commit messages
- Reference issue numbers in commits when applicable
- Test your code before committing changes