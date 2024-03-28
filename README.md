# Church Plugins Connect
Connection plugin for various ChMS

##### First-time installation  #####

- Copy or clone the code into `wp-content/plugins/cp-connect/`
- Run these commands
```
composer install
npm install
cd app
npm install
npm run build
```

##### Dev updates  #####

- There is currently no watcher that will update the React app in the WordPress context, so changes are executed through `npm run build` which can be run from either the `cp-connect`

### Change Log

#### 1.0.3
* Add more mapping fields for MinistryPlatform

#### 1.0.2
* Add CCB support

#### 1.0.1
* Better handling for location setting
* Update post after initial save to handle duplicate slugs

#### 1.0.0
* Initial release
