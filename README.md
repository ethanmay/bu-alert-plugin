# BU Alert Plugin

This plugin can display an alert message at the top of all pages for sites in a WordPress network. The alert consists of a message box which is inserted into the markup of the page during the WP `init` hook.

The alerts are stored and rendered from the `wp_sitemeta` table. Alerts can be started and ended through 2 interfaces:

* WP REST API
* WP-CLI

Stopping and starting alerts will also trigger a full cache flush using `$wp_object_cache->flush( 0 )` to ensure the message is rendered immediately. It also includes an integration with the static homepage capture setup in [r-editorial](https://github.com/bu-ist/r-editorial/blob/d189727d9f4b0e27350ff0d5d511e67d717eddb6/includes/bu-homepage-admin.php#L205), so stopping and starting alerts will trigger new homepage renders through the page-capture lambda if it is setup in the r-editorial homepage admin.

In case of emergency, [check the wiki for command recipies to start and stop alerts manually.](https://github.com/bu-ist/bu-alert-plugin/wiki/Emergency-wp-cli-recipes)

The wiki also contains [some additional details about the Everbridge integration.](https://github.com/bu-ist/bu-alert-plugin/wiki/Everbridge-Web-Posting-Structure)

## REST API

The plugin includes a custom REST API endpoint with `start` and `stop` commands that are secured by a custom token.  This interface is designed primarily for the Everbridge incident integration, but can be used by anything that has the security token.

The REST API token is defined in the environment outside the plugin (through wp-config.php) and is called `BU_ALERT_API_TOKEN`.  It is [converted from the bu-wordpress.ini file](https://github.com/bu-ist/bu-wordpress-core-additions/commit/5f9c8208a8ce9645d6cf2cc58b3d634aec4d9c39#diff-fe8d66f10b1281812b961da2fcf17de765f4b3ad79a3c32dcaddcb38820e7b28R245).

### API Start Alert

To start an alert through the API, send a POST request to the endpoint `/wp-json/bu-alert/v1/start/` with the [json encoded body of the alert message in the POST body,](https://github.com/bu-ist/bu-alert-plugin/wiki/Everbridge-Web-Posting-Structure) and a `?token=` url parameter for the secure token.  The alert will be added for the domain that receives the API request, but additional domains can be activated by passing a comma delimited list through an optional `additionalDomains=` parameter.  The structure of the `start` endpoint is determined mostly by the Everbridge Web Posting that it is designed to support.

### API Stop Alert

There is an endpoint `/wp-json/bu-alert/v1/stop/` that will remove the alert for the targeted domain.  Because the Everbridge integration uses a separate mechanism to remove alerts (see incident expiration in the CLI), the `/stop/` endpoint is much simpler and is mostly useful as an aid during development.  It only accepts the security `?token=` parameter.

## WP CLI

The WP CLI interface provides commands to stop and start alerts, as well as commands to work with the Everbridge integration. Notably, removing expired Everbridge incidents is handled through the WP CLI interface (along with a dedicated cron task that invokes a WP CLI command).

There is online help available through:

```bash
wp help alert
```

Below is a description of the individual commands:

### wp alert start-manual

Manual alerts can be started for a targeted domain with the `start-manual` command.  It takes the body of the message as a command line argument.

```bash
wp alert start-manual --url=http://www-test.bu.edu "<message body>"
```

These manual alerts use a special [hardcoded incident_id consisting of the string "manual"](https://github.com/bu-ist/bu-alert-plugin/blob/3755b10528e8392b2cafb4197f63b4d7a175403e/src/alert-wp-cli.php#L199), which tells the Everbridge integration to leave these alerts alone and not remove them.

Starting alerts in this way will also trigger an r-editorial homepage rebuild, if available.

### wp alert stop-all

All alerts for all domains in the network can be immediately removed with the `stop-all` command, which requires no arguments.

```bash
wp alert stop-all
```

Because it removes alerts from *all* domains in the multisite WP network, it does not matter which domain is targeted.  Every alert will be removed.

Stopping alerts in this way will also trigger an r-editorial homepage rebuild, if available.

### wp alert list

The `list` command will return a table containing all active alerts in a network.

```bash
wp alert list
```

It requires no arguments, and because it queries the entire network it doesn't matter which domain is targeted.

### wp alert list-everbridge

The `list-everbridge` command queries the Everbridge API to get all of the incidents that are currently active in Everbridge.  It is primarly for troubleshooting the Everbridge integration.

```bash
wp alert list-everbridge
```

### wp alert expire

The `expire` command handles removing alerts once they are no longer active in Everbridge.  This is necessary because Everbridge does not provide an event when incidents are closed.  To work around this limitation, `expire` gets the list of current active incidents from the Everbridge API and compares it to the list of currently active alerts in the WordPress network.  Any alerts that are active in WordPress, but not present in Everbridge are removed.  

There's no way to tell when an incident may be closed, so `expire` is designed to be run from a system cron task that regularly checks for expired incidents, currently every minute.

This should also have the side effect of guarding against stateless Everbridge 'notifications' from persisting.  If a Web Posting is accidentally included with an Everbridge notification, the incident id should come through as blank.  Running `expire` will [remove alerts with empty incident ids](https://github.com/bu-ist/bu-alert-plugin/blob/3755b10528e8392b2cafb4197f63b4d7a175403e/src/alert-wp-cli.php#L194). This is also the reason why manual alerts have a hardcoded incident id, in order to distinguish them from malformed Everbridge Web Postings.

This command can also be run manually and doesn't require any arguments.

```bash
wp alert expire
```

## Everbridge API credentials

Accessing the Everbridge API requires 4 environment variables from `bu-wordpress.ini`, [which are parsed from wp-config.php](https://github.com/bu-ist/bu-wordpress-core-additions/blob/5f9c8208a8ce9645d6cf2cc58b3d634aec4d9c39/wp-config.php#L247-L251).

* `EB_ENDPOINT`:          Generally 'https://api.everbridge.net/'
* `EB_BU_ORG_ID`:         The Everbridge organization ID to use
* `EB_KEY_ID`:            Key ID of the API access account
* `EB_SECRET_ACCESS_KEY`: Secret key of the API access account

## Everbridge Incident Expiration Cron Task

In order to remove closed Everbridge incidents, a cron task is required to run the `wp alert expire` command on a regular basis. This cron entry should live in the `wpcms` user crontab for each environment, and can be added with the following command:

```bash
sudo -u wpcms crontab -e
```

Below are examples for the TEST and PROD environment.  Because DEVL consists of many independent sandboxes, a cron task there would probably only complicate matters. These definitions run every minute.

TEST (as installed):

```crontab
# expire everbridge alerts every minute for www-test
* * * * * /fs/test/scratch/shared/bin/wp alert expire --url="http://www-test.bu.edu" --path="/var/www/cms-test/current/"
```

PROD (to be installed):

```crontab
# expire everbridge alerts every minute for www
* * * * * /fs/prod/scratch/shared/bin/wp alert expire --url="http://www.bu.edu" --path="/var/www/cms-prod/current/"
```
