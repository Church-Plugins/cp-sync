# Church Plugins Sync
Connection plugin for various ChMS

##### First-time installation  #####

- Copy or clone the code into `wp-content/plugins/cp-sync/`
- Run these commands
```
composer install
npm install
cd app
npm install
npm run build
```

##### Dev updates  #####

- There is currently no watcher that will update the React app in the WordPress context, so changes are executed through `npm run build` which can be run from either the `cp-sync`

### Change Log

#### 0.1.7.2
* Bug Fix: Fixed PCO event import to use event instance ID instead of event ID for chms_id
* Bug Fix: Fixed thumbnail update logic to properly check for existing thumbnails
* Enhancement: Added PCO-specific image URL normalization using unique 'key' parameter
* Enhancement: Implemented image reuse system to avoid duplicate downloads from media library
* Enhancement: Optimized image import by checking for existing attachments before sideloading
* Bug Fix: Fixed wp_insert_attachment parameter to use correct post ID
* Enhancement: Store normalized URL in attachment meta for efficient image reuse
* Bug Fix: Fixed React console warnings by adding proper keys to components

#### 0.1.7
* Enhancement: Added comprehensive logging to PCO registration event import process
* Enhancement: Improved debugging capabilities for troubleshooting import issues
* Enhancement: Added detailed logging for API responses, filtering, and event processing
* Bug Fix: Fixed OAuth redirect URL construction for admin pages

#### 0.1.6
* Enhancement: to the CCB sync
* Enhancement: better image importing
* Enhancement: add security check to api endpoints
* Bug Fix: Update api endpoints

#### 0.1.5
* Re-build assets

#### 0.1.4
* Re-build assets

#### 0.1.3
* Add more mapping fields for MinistryPlatform

#### 0.1.2
* Add CCB support

#### 0.1.1
* Better handling for location setting
* Update post after initial save to handle duplicate slugs

#### 1.0.0
* Initial release
