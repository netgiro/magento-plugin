# Magento Payment Gateway plugins for Netgiro

Netgiro plugin is available in [Magento Marketplace](https://marketplace.magento.com/netgiro-gateway.html) 

For any extra information or questions please contact us at netgiro@netgiro.is.

# Test environment inside docker

Run docker-compose to setup all necessary containers
```
docker-compose up -d
```

You can access your magento on [http://localhost](http://localhost)  
You can access Magento admin on [http://localhost/admin](http://localhost/admin)  
Username: `admin`  
password `meerko1`  

## Docker for windows

If you get the `Invalid kernel settings. Elasticsearch requires at least: vm.max_map_count = 262144` error you need to run `wsl` and then this command
```
sysctl -w vm.max_map_count=262144
```

Volumes are stored in
```
\\wsl$\docker-desktop-data\version-pack-data\community\docker\volumes\
```

Extension is stored in
```
\\wsl$\docker-desktop-data\version-pack-data\community\docker\volumes\magento-plugin_magento_data\_data\vendor\netgiro\gateway
```

# Install extension from marketplace
1. Please ensure you are using correct access keys (My Profile - Access Keys)
2. Paste the access keys in your auth.json file inside your project
3. Use the "composer require netgiro/gateway:1.0.4" command to add the extension to Magento.

## Magento CLI commands
When you install the extension its disabled you need to enable it

```
cd /bitnami/magento
composer require netgiro/gateway:1.0.4
magento module:status netgiro_gateway
magento module:enable netgiro_gateway --clear-static-content
magento setup:upgrade
magento setup:di:compile
magento cache:clean
magento cache:flush
```