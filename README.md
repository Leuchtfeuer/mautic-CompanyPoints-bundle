# Plugin: Company Points by Leuchtfeuer

## Overview

This plugin brings massively enhanced Company-based Scoring to Mautic, including events triggered by those Points.

It is part of the "ABM" suite of plugins that extends Mautic capabilities for working with Companies.

## Requirements
- Mautic 5.x (minimum 5.1)
- PHP 8.1 or higher
- Company Tags and Company Segments Plugins

## Installation
### Composer
This plugin can be installed through composer.

### Manual install
Alternatively, it can be installed manually, following the usual steps:

* Download the plugin
* Unzip to the Mautic `plugins` directory
* Rename folder to `LeuchtfeuerCompanyPointsBundle` 

-
* In the Mautic backend, go to the `Plugins` page as an administrator
* Click on the `Install/Upgrade Plugins` button to install the Plugin.

OR

* If you have shell access, execute `php bin\console cache:clear` and `php bin\console mautic:plugins:reload` to install the plugins.

## Plugin Activation and Configuration
1. Go to `Plugins` page
2. Click on the `Company Points` plugin
3. ENABLE the plugin

## Usage
The plugin brings a new menu item `Companies -> Company Point Triggers`. Here you can define what point limits you want, and what to do when a limit is reached.

There are currently no Point Action (i.e. points being automatically added to the Company when a certain condition is met).

Instead, the aggregated score is calculated by a console command as cron job:
`php bin/console leuchtfeuer:abm:points-update`
You should set up a cron entry accordingly.

The only current algorithm for the aggregation is "static company points PLUS average among all contacts that currently have points)"

## Troubleshooting
Make sure you have not only installed but also enabled the Plugin.

If things are still funny, please try

`php bin/console cache:clear`

and 

`php bin/console mautic:assets:generate`

## Known Issues
* Console command only works ever second time
* Misplaced "edit" icons for Trigger events (this is a Mautic issue, cannot be fixed here)

## Future Ideas
* Additional Triggered Event `Send Email To User`
* Choice of aggregation algorithms (including time)
* Additional Triggered Events like `Send Print Mailing` and `Modify Company Segments`
* Support for Point Groups

## Credits
* @lenonleite
* @ekkeguembel
* @PatrickJenkner

## Author and Contact
Leuchtfeuer Digital Marketing GmbH

Please raise any issues in GitHub.

For all other things, please email mautic-plugins@Leuchtfeuer.com
